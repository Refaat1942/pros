<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupplyRequestService
{
    /**
     * إنشاء بند طلب توريد — بدون تأثير على المخزون أو الكتالوج.
     *
     * @param  array{line_type: string, stock_item_id?: int|null, description?: string|null, qty: int, uom?: string|null, spec?: string|null, notes?: string|null}  $data
     */
    public function createLine(array $data, User $user): SupplyRequestLine
    {
        return DB::transaction(function () use ($data, $user) {
            $lineType = $data['line_type'];

            if ($lineType === SupplyRequestLine::TYPE_CATALOG) {
                $stockItem = StockItem::query()->findOrFail($data['stock_item_id']);
            } else {
                $stockItem = null;
            }

            $request = SupplyRequest::create([
                'request_no' => $this->nextRequestNo(),
                'status' => SupplyRequest::STATUS_OPEN,
                'requested_by_user_id' => $user->id,
            ]);

            $line = SupplyRequestLine::create([
                'supply_request_id' => $request->id,
                'line_type' => $lineType,
                'stock_item_id' => $stockItem?->id,
                'description' => $lineType === SupplyRequestLine::TYPE_NON_CATALOG
                    ? trim((string) $data['description'])
                    : null,
                'qty' => (int) $data['qty'],
                'uom' => $this->nullableString($data['uom'] ?? null),
                'spec' => $this->nullableString($data['spec'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'status' => SupplyRequestLine::STATUS_PENDING,
            ]);

            AuditService::log(
                action: 'create',
                description: "طلب توريد {$request->request_no} — {$line->displayLabel()}",
                tag: 'warehouse',
                after: $line->load('supplyRequest')->toArray(),
            );

            return $line->load(['supplyRequest', 'stockItem']);
        });
    }

    /** @return \Illuminate\Support\Collection<int, SupplyRequestLine> */
    public function listOpenLines(): \Illuminate\Support\Collection
    {
        return SupplyRequestLine::query()
            ->with([
                'supplyRequest:id,request_no,requested_by_user_id,created_at',
                'stockItem:id,code,name,uom,barcode',
                'resolvedStockItem:id,code,name,uom,barcode',
            ])
            ->whereIn('status', [SupplyRequestLine::STATUS_PENDING, SupplyRequestLine::STATUS_RESOLVED])
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    /**
     * ربط بند غير مكود بصنف كتالوج حقيقي قبل الاستلام.
     */
    public function resolveNonCatalogLine(SupplyRequestLine $line, int $stockItemId): SupplyRequestLine
    {
        return DB::transaction(function () use ($line, $stockItemId) {
            $line = SupplyRequestLine::query()->lockForUpdate()->findOrFail($line->id);

            if (! $line->isNonCatalog()) {
                abort(422, 'هذا البند مرتبط بالفعل بصنف كتالوج.');
            }

            if (! in_array($line->status, [SupplyRequestLine::STATUS_PENDING], true)) {
                abort(422, 'لا يمكن ربط هذا البند في حالته الحالية.');
            }

            $stockItem = StockItem::query()->findOrFail($stockItemId);

            $before = $line->toArray();
            $line->update([
                'resolved_stock_item_id' => $stockItem->id,
                'status' => SupplyRequestLine::STATUS_RESOLVED,
            ]);

            AuditService::log(
                action: 'update',
                description: "ربط طلب توريد غير مكود بصنف {$stockItem->code}",
                tag: 'warehouse',
                before: $before,
                after: $line->fresh()->toArray(),
            );

            return $line->fresh(['supplyRequest', 'resolvedStockItem']);
        });
    }

    public function markLineReceived(SupplyRequestLine $line, StockMovement $movement): SupplyRequestLine
    {
        return DB::transaction(function () use ($line, $movement) {
            $line = SupplyRequestLine::query()->lockForUpdate()->findOrFail($line->id);

            if ($line->status === SupplyRequestLine::STATUS_RECEIVED) {
                return $line;
            }

            $receivableId = $line->receivableStockItemId();
            if ($receivableId === null) {
                abort(422, 'يجب ربط الصنف غير المكود بصنف كتالوج قبل الاستلام.');
            }

            if ((int) $movement->stock_item_id !== (int) $receivableId) {
                abort(422, 'الصنف المستلم لا يطابق بند طلب التوريد.');
            }

            $before = $line->toArray();
            $line->update([
                'status' => SupplyRequestLine::STATUS_RECEIVED,
                'stock_movement_id' => $movement->id,
            ]);

            AuditService::log(
                action: 'update',
                description: "استلام طلب توريد — {$line->displayLabel()}",
                tag: 'warehouse',
                before: $before,
                after: $line->fresh()->toArray(),
            );

            return $line->fresh(['supplyRequest', 'stockItem', 'resolvedStockItem']);
        });
    }

    /**
     * بحث خفيف للأصناف — بدون تحميل الكتالوج الكامل.
     *
     * @return list<array{id: int, code: string, name: string, uom: string|null, barcode: string|null}>
     */
    public function searchCatalogItems(string $query, int $limit = 30): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $catalogService = app(StockCatalogService::class);

        return StockItem::query()
            ->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('alt_codes', 'like', "%{$term}%")
                    ->orWhere('catalog_number', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(min(50, max(1, $limit)))
            ->get(['id', 'code', 'name', 'uom', 'barcode', 'catalog_number'])
            ->map(fn (StockItem $item) => [
                'id' => $item->id,
                'code' => $catalogService->displayCatalogCode($item),
                'name' => $item->name,
                'uom' => $item->uom,
                'barcode' => $item->barcode,
            ])
            ->values()
            ->all();
    }

    public function formatLine(SupplyRequestLine $line): array
    {
        $receivableId = $line->receivableStockItemId();

        return [
            'id' => $line->id,
            'request_no' => $line->supplyRequest?->request_no,
            'line_type' => $line->line_type,
            'line_type_label' => $line->isNonCatalog() ? 'غير مكود' : 'كتالوج',
            'display_name' => $line->displayLabel(),
            'description' => $line->description,
            'qty' => $line->qty,
            'uom' => $line->uom,
            'spec' => $line->spec,
            'notes' => $line->notes,
            'status' => $line->status,
            'status_label' => $this->statusLabel($line->status),
            'stock_item_id' => $line->stock_item_id,
            'resolved_stock_item_id' => $line->resolved_stock_item_id,
            'receivable_stock_item_id' => $receivableId,
            'can_receive' => $receivableId !== null && in_array($line->status, [
                SupplyRequestLine::STATUS_PENDING,
                SupplyRequestLine::STATUS_RESOLVED,
            ], true),
            'needs_link' => $line->isNonCatalog() && $line->resolved_stock_item_id === null,
            'stock_item' => $line->stockItem?->only(['id', 'code', 'name', 'uom', 'barcode']),
            'resolved_stock_item' => $line->resolvedStockItem?->only(['id', 'code', 'name', 'uom', 'barcode']),
            'created_at' => $line->created_at?->toDateTimeString(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            SupplyRequestLine::STATUS_PENDING => 'بانتظار التوريد',
            SupplyRequestLine::STATUS_RESOLVED => 'جاهز للاستلام',
            SupplyRequestLine::STATUS_RECEIVED => 'تم الاستلام',
            SupplyRequestLine::STATUS_CANCELLED => 'ملغي',
            default => $status,
        };
    }

    private function nextRequestNo(): string
    {
        $prefix = 'SR-'.now()->format('ym');

        $last = SupplyRequest::query()
            ->where('request_no', 'like', $prefix.'%')
            ->orderByDesc('request_no')
            ->lockForUpdate()
            ->value('request_no');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function nullableString(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
