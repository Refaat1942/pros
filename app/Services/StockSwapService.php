<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockSwapRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ClinicTime;
use Illuminate\Support\Facades\DB;

/**
 * تبديل صنف مع المورد (طلب واعتماد أدمن المخازن — صلاحية manage-item-swaps):
 * عند الاعتماد يُخصم الصنف الخارج من المخزون (FIFO على دفعات السعر)، ويتعمل الصنف الجديد في
 * الكتالوج لو مش موجود، ويُضاف بتكلفة = قيمة الخارج + فرق السعر. مديونية المورد = فرق السعر فقط.
 */
class StockSwapService
{
    public const PERMISSION = 'manage-item-swaps';

    public function __construct(
        private readonly PriceBatchDispenseService $batches,
        private readonly StockReceiveService $receive,
        private readonly StockCatalogService $catalog,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function request(array $data, User $user): StockSwapRequest
    {
        abort_unless($user->can(self::PERMISSION), 403, 'تبديل الأصناف من صلاحيات مسؤول المخازن فقط.');

        $out = StockItem::findOrFail($data['out_stock_item_id']);
        $outQty = round((float) $data['out_qty'], 4);
        abort_if($outQty > (float) $out->availableQty() + 0.0001, 422, 'الكمية الخارجة أكبر من المتاح من «'.$out->name.'» ('.$out->availableQty().').');

        $inItemId = ! empty($data['in_stock_item_id']) ? (int) $data['in_stock_item_id'] : null;
        abort_if($inItemId === $out->id, 422, 'يجب أن يختلف الصنف البديل عن الصنف الخارج.');
        $newItem = null;
        if ($inItemId === null) {
            $name = trim((string) ($data['new_item']['name'] ?? ''));
            abort_if($name === '', 422, 'اكتب اسم الصنف الجديد أو اختر صنفاً موجوداً.');
            $newItem = array_filter([
                'name' => $name,
                'uom' => trim((string) ($data['new_item']['uom'] ?? '')) ?: $out->uom,
                'brand' => trim((string) ($data['new_item']['brand'] ?? '')),
                'spec' => trim((string) ($data['new_item']['spec'] ?? '')),
                // نفس تصنيف الصنف الخارج وخاصية الصرف السريع.
                'category_id' => $out->category_id,
                'is_quick_dispense' => (bool) $out->is_quick_dispense,
            ], fn ($v) => $v !== '' && $v !== null);
        }

        return DB::transaction(function () use ($data, $user, $out, $outQty, $inItemId, $newItem) {
            $swap = StockSwapRequest::create([
                'swap_no' => $this->nextNo(),
                'status' => StockSwapRequest::STATUS_PENDING,
                'supplier_id' => Supplier::findOrFail($data['supplier_id'])->id,
                'out_stock_item_id' => $out->id,
                'out_qty' => $outQty,
                'in_stock_item_id' => $inItemId,
                'new_item' => $newItem,
                'in_qty' => round((float) $data['in_qty'], 4),
                'price_difference' => round(max(0, (float) ($data['price_difference'] ?? 0)), 2),
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'requested_by_user_id' => $user->id,
            ]);

            AuditService::log(
                action: 'create',
                description: "طلب تبديل صنف {$swap->swap_no}: {$out->name} × {$outQty} ← {$swap->inLabel()} × {$swap->in_qty}",
                tag: 'warehouse',
                after: $swap->toArray(),
            );

            return $swap;
        });
    }

    public function approve(StockSwapRequest $swap, User $approver): StockSwapRequest
    {
        abort_unless($approver->can(self::PERMISSION), 403, 'اعتماد تبديل الأصناف من صلاحيات مسؤول المخازن فقط.');

        return DB::transaction(function () use ($swap, $approver) {
            $swap = StockSwapRequest::query()->lockForUpdate()->findOrFail($swap->id);
            abort_unless($swap->status === StockSwapRequest::STATUS_PENDING, 422, 'طلب التبديل تمت معالجته مسبقاً.');

            $supplier = Supplier::findOrFail($swap->supplier_id);
            $performer = $swap->requestedBy ?? $approver;
            $now = now();

            // 1) خروج الصنف القديم من المخزون بتكلفته الفعلية (دفعات FIFO).
            $out = StockItem::query()->lockForUpdate()->findOrFail($swap->out_stock_item_id);
            $outQty = (float) $swap->out_qty;
            abort_if($outQty > (float) $out->availableQty() + 0.0001, 422, 'المتاح من «'.$out->name.'» أصبح أقل من كمية التبديل ('.$out->availableQty().').');

            $allocations = $this->batches->allocateForDispense($out, $outQty);
            $this->batches->applyDecrements($allocations);
            $outValue = round(array_sum(array_map(fn ($a) => $a['qty'] * $a['unit_price'], $allocations)), 2);
            if ($outValue <= 0) {
                $outValue = round($outQty * (float) ($out->wac ?? 0), 2);
            }

            $balanceAfter = (float) $out->qty - $outQty;
            $outMovement = StockMovement::create([
                'stock_item_id' => $out->id,
                'stock_item_price_id' => $allocations[0]['batch_id'] ?? null,
                'movement_type' => StockMovement::TYPE_SWAP_OUT,
                'quantity' => -$outQty,
                'unit_cost' => $outQty > 0 ? round($outValue / $outQty, 4) : 0,
                'balance_after' => $balanceAfter,
                'invoice_no' => $swap->swap_no,
                'supplier_id' => $supplier->id,
                'reference_type' => $swap->getMorphClass(),
                'reference_id' => $swap->id,
                'performed_by_user_id' => $performer->id,
                'moved_at' => $now,
            ]);
            $out->update(['qty' => $balanceAfter, 'last_moved_at' => $now->toDateString()]);
            $out->refresh()->recalculateAndSaveStatus();

            // 2) الصنف البديل: موجود، أو يتعمل في الكتالوج تلقائياً باسمه ومواصفاته الجديدة.
            $in = $swap->in_stock_item_id
                ? StockItem::findOrFail($swap->in_stock_item_id)
                : $this->catalog->create(array_merge($swap->new_item ?? [], [
                    'qty' => 0,
                    'supplier_ids' => [$supplier->id],
                ]));

            // 3) دخول البديل بتكلفة = قيمة الخارج + فرق السعر؛ المديونية = فرق السعر فقط.
            $inQty = (float) $swap->in_qty;
            $inValue = $outValue + (float) $swap->price_difference;
            $inMovement = $this->receive->receive(
                item: $in,
                qty: $inQty,
                unitPrice: $inQty > 0 ? round($inValue / $inQty, 4) : 0,
                supplier: $supplier,
                invoiceNo: $swap->swap_no,
                movedAt: $now,
                performedBy: $performer,
                movementType: StockMovement::TYPE_SWAP_IN,
                supplierDebtAmount: (float) $swap->price_difference,
                reference: $swap,
            );

            $swap->update([
                'status' => StockSwapRequest::STATUS_APPROVED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => $now,
                'in_stock_item_id' => $in->id,
                'out_value' => $outValue,
                'out_movement_id' => $outMovement->id,
                'in_movement_id' => $inMovement->id,
            ]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد تبديل صنف {$swap->swap_no}: خصم {$out->name} × {$outQty} (قيمة {$outValue}) وإضافة {$in->name} × {$inQty}",
                tag: 'warehouse',
                after: ['swap_no' => $swap->swap_no, 'out_value' => $outValue, 'in_item_id' => $in->id, 'approved_by' => $approver->name],
            );

            return $swap->fresh(['outItem', 'inItem', 'supplier']);
        });
    }

    public function reject(StockSwapRequest $swap, User $approver, string $reason): StockSwapRequest
    {
        abort_unless($approver->can(self::PERMISSION), 403, 'اعتماد تبديل الأصناف من صلاحيات مسؤول المخازن فقط.');
        $reason = trim($reason);
        abort_if($reason === '', 422, 'اكتب سبب الرفض.');

        return DB::transaction(function () use ($swap, $approver, $reason) {
            $swap = StockSwapRequest::query()->lockForUpdate()->findOrFail($swap->id);
            abort_unless($swap->status === StockSwapRequest::STATUS_PENDING, 422, 'طلب التبديل تمت معالجته مسبقاً.');
            $swap->update([
                'status' => StockSwapRequest::STATUS_REJECTED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => mb_substr($reason, 0, 500),
            ]);
            AuditService::log(action: 'reject', description: "رفض تبديل صنف {$swap->swap_no} — {$reason}", tag: 'warehouse');

            return $swap;
        });
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        return StockSwapRequest::query()
            ->with(['outItem', 'inItem', 'supplier:id,name', 'requestedBy:id,name'])
            ->where('status', StockSwapRequest::STATUS_PENDING)
            ->orderBy('id')
            ->get()
            ->map(fn (StockSwapRequest $s) => [
                'id' => $s->id,
                'swap_no' => $s->swap_no,
                'out' => trim(($s->outItem?->code ?? '').' — '.($s->outItem?->name ?? '')),
                'out_qty' => (float) $s->out_qty,
                'out_available' => (float) ($s->outItem?->availableQty() ?? 0),
                'in' => $s->inLabel(),
                'in_is_new' => $s->in_stock_item_id === null,
                'new_item' => $s->new_item,
                'in_qty' => (float) $s->in_qty,
                'price_difference' => (float) $s->price_difference,
                'supplier' => $s->supplier?->name,
                'notes' => $s->notes,
                'requested_by' => $s->requestedBy?->name,
                'requested_at' => ClinicTime::format($s->created_at, 'd/m/Y H:i'),
            ])->values()->all();
    }

    private function nextNo(): string
    {
        $prefix = 'SW-'.now()->format('ym');
        $last = StockSwapRequest::query()->where('swap_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('swap_no')->value('swap_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
