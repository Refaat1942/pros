<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockReceiptRequest;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\ClinicTime;
use App\Support\StockSupplyUom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * اعتمادات أدمن المخازن: طلبات التوريد واستلام الوارد.
 * صاحب الصلاحية ينفّذ فوراً (زي الأول)؛ غيره يتسجّل طلبه «بانتظار الاعتماد».
 */
class StockApprovalService
{
    public const PERM_SUPPLY = 'approve-supply-requests';

    public const PERM_RECEIPT = 'approve-stock-receipts';

    public function __construct(private readonly StockReceiveService $stockReceiveService) {}

    public function supplyNeedsApproval(User $user): bool
    {
        return (bool) config('inventory.supply_requires_approval', true) && ! $user->can(self::PERM_SUPPLY);
    }

    public function receiptNeedsApproval(User $user): bool
    {
        return (bool) config('inventory.receipt_requires_approval', true) && ! $user->can(self::PERM_RECEIPT);
    }

    // ── استلام الوارد ────────────────────────────────────────────────────────

    /**
     * تنفيذ الاستلام الفعلي (إضافة الرصيد + دفعة سعر + مديونية المورد + WAC + ربط بند التوريد).
     *
     * @param  array{stock_item_id: int, supplier_id: int, qty: float, unit_price: float, quantity_basis?: string|null, invoice_no: string, moved_at: string, supply_request_line_id?: int|null}  $data
     * @param  array{path?: string|null, name?: string|null, mime?: string|null}  $document
     */
    public function executeReceipt(array $data, User $performedBy, array $document = []): StockMovement
    {
        return DB::transaction(function () use ($data, $performedBy, $document) {
            $item = StockItem::findOrFail($data['stock_item_id']);
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $lineId = $data['supply_request_line_id'] ?? null;

            $resolved = StockSupplyUom::resolveReceiveQuantities(
                $item,
                (float) $data['qty'],
                (float) $data['unit_price'],
                $data['quantity_basis'] ?? 'auto',
            );

            $movement = $this->stockReceiveService->receive(
                item: $item,
                qty: $resolved['base_qty'],
                unitPrice: $resolved['base_unit_price'],
                supplier: $supplier,
                invoiceNo: $data['invoice_no'],
                movedAt: Carbon::parse($data['moved_at']),
                performedBy: $performedBy,
                documentPath: $document['path'] ?? null,
                documentOriginalName: $document['name'] ?? null,
                documentMime: $document['mime'] ?? null,
                supplyRequestLineId: $lineId ? (int) $lineId : null,
                supplyQuantity: $resolved['supply_qty'],
                supplyUnitCost: $resolved['supply_unit_price'],
            );

            if ($lineId) {
                $line = SupplyRequestLine::query()->findOrFail($lineId);
                app(SupplyRequestService::class)->markLineReceived($line, $movement);
            }

            return $movement;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function submitReceipt(array $data, User $user, array $document = []): StockReceiptRequest
    {
        return DB::transaction(function () use ($data, $user, $document) {
            $lineId = $data['supply_request_line_id'] ?? null;
            if ($lineId && StockReceiptRequest::query()
                ->where('supply_request_line_id', $lineId)
                ->where('status', StockReceiptRequest::STATUS_PENDING)
                ->lockForUpdate()->exists()) {
                abort(422, 'يوجد استلام لبند التوريد نفسه بانتظار الاعتماد بالفعل.');
            }
            if ($lineId) {
                $this->assertLineReceivable(SupplyRequestLine::query()->with('supplyRequest')->findOrFail($lineId));
            }

            $receipt = StockReceiptRequest::create([
                'status' => StockReceiptRequest::STATUS_PENDING,
                'stock_item_id' => (int) $data['stock_item_id'],
                'supplier_id' => (int) $data['supplier_id'],
                'supply_request_line_id' => $lineId ? (int) $lineId : null,
                'qty' => (float) $data['qty'],
                'unit_price' => (float) $data['unit_price'],
                'quantity_basis' => $data['quantity_basis'] ?? 'auto',
                'invoice_no' => (string) $data['invoice_no'],
                'moved_at' => $data['moved_at'],
                'document_path' => $document['path'] ?? null,
                'document_original_name' => $document['name'] ?? null,
                'document_mime' => $document['mime'] ?? null,
                'requested_by_user_id' => $user->id,
            ]);

            AuditService::log(
                action: 'create',
                description: "طلب استلام وارد بانتظار الاعتماد — {$receipt->stockItem?->name} × {$receipt->qty} (فاتورة {$receipt->invoice_no})",
                tag: 'warehouse',
                after: $receipt->toArray(),
            );

            $this->notifyApprovers(
                '📥 استلام وارد بانتظار الاعتماد',
                "{$receipt->stockItem?->name} × ".rtrim(rtrim(number_format((float) $receipt->qty, 4, '.', ''), '0'), '.').' — فاتورة '.$receipt->invoice_no,
                'stock_receipt_pending',
            );

            return $receipt;
        });
    }

    public function approveReceipt(StockReceiptRequest $receipt, User $approver): StockReceiptRequest
    {
        abort_unless($approver->can(self::PERM_RECEIPT), 403, 'ليس لديك صلاحية اعتماد استلام الوارد.');

        return DB::transaction(function () use ($receipt, $approver) {
            $receipt = StockReceiptRequest::query()->lockForUpdate()->findOrFail($receipt->id);
            abort_unless($receipt->status === StockReceiptRequest::STATUS_PENDING, 422, 'طلب الاستلام تمت معالجته مسبقاً.');

            // الحركة باسم اللي استلم فعلاً؛ الاعتماد مسجّل على الطلب وفي سجل الرقابة.
            $movement = $this->executeReceipt([
                'stock_item_id' => $receipt->stock_item_id,
                'supplier_id' => $receipt->supplier_id,
                'qty' => (float) $receipt->qty,
                'unit_price' => (float) $receipt->unit_price,
                'quantity_basis' => $receipt->quantity_basis,
                'invoice_no' => $receipt->invoice_no,
                'moved_at' => $receipt->moved_at->toDateString(),
                'supply_request_line_id' => $receipt->supply_request_line_id,
            ], $receipt->requestedBy ?? $approver, [
                'path' => $receipt->document_path,
                'name' => $receipt->document_original_name,
                'mime' => $receipt->document_mime,
            ]);

            $receipt->update([
                'status' => StockReceiptRequest::STATUS_APPROVED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'stock_movement_id' => $movement->id,
            ]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد استلام وارد #{$receipt->id} — {$receipt->stockItem?->name} (فاتورة {$receipt->invoice_no})",
                tag: 'warehouse',
                after: ['receipt_id' => $receipt->id, 'movement_id' => $movement->id, 'approved_by' => $approver->name],
            );

            return $receipt->fresh(['stockItem', 'supplier', 'requestedBy', 'approvedBy']);
        });
    }

    public function rejectReceipt(StockReceiptRequest $receipt, User $approver, string $reason): StockReceiptRequest
    {
        abort_unless($approver->can(self::PERM_RECEIPT), 403, 'ليس لديك صلاحية اعتماد استلام الوارد.');
        $reason = trim($reason);
        abort_if($reason === '', 422, 'اكتب سبب الرفض.');

        return DB::transaction(function () use ($receipt, $approver, $reason) {
            $receipt = StockReceiptRequest::query()->lockForUpdate()->findOrFail($receipt->id);
            abort_unless($receipt->status === StockReceiptRequest::STATUS_PENDING, 422, 'طلب الاستلام تمت معالجته مسبقاً.');

            $receipt->update([
                'status' => StockReceiptRequest::STATUS_REJECTED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => mb_substr($reason, 0, 500),
            ]);

            AuditService::log(
                action: 'reject',
                description: "رفض استلام وارد #{$receipt->id} — {$reason}",
                tag: 'warehouse',
                after: ['receipt_id' => $receipt->id, 'by' => $approver->name, 'reason' => $reason],
            );

            return $receipt;
        });
    }

    // ── طلبات التوريد ────────────────────────────────────────────────────────

    public function approveSupply(SupplyRequest $request, User $approver): SupplyRequest
    {
        abort_unless($approver->can(self::PERM_SUPPLY), 403, 'ليس لديك صلاحية اعتماد طلبات التوريد.');

        return DB::transaction(function () use ($request, $approver) {
            $request = SupplyRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === SupplyRequest::STATUS_PENDING_APPROVAL, 422, 'طلب التوريد تمت معالجته مسبقاً.');

            $request->update([
                'status' => SupplyRequest::STATUS_OPEN,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
            ]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد طلب توريد {$request->request_no}",
                tag: 'warehouse',
                after: ['request_no' => $request->request_no, 'approved_by' => $approver->name],
            );

            return $request;
        });
    }

    public function rejectSupply(SupplyRequest $request, User $approver, string $reason): SupplyRequest
    {
        abort_unless($approver->can(self::PERM_SUPPLY), 403, 'ليس لديك صلاحية اعتماد طلبات التوريد.');
        $reason = trim($reason);
        abort_if($reason === '', 422, 'اكتب سبب الرفض.');

        return DB::transaction(function () use ($request, $approver, $reason) {
            $request = SupplyRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === SupplyRequest::STATUS_PENDING_APPROVAL, 422, 'طلب التوريد تمت معالجته مسبقاً.');

            $request->update([
                'status' => SupplyRequest::STATUS_REJECTED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => mb_substr($reason, 0, 500),
            ]);
            $request->lines()->update(['status' => SupplyRequestLine::STATUS_CANCELLED]);

            AuditService::log(
                action: 'reject',
                description: "رفض طلب توريد {$request->request_no} — {$reason}",
                tag: 'warehouse',
                after: ['request_no' => $request->request_no, 'by' => $approver->name, 'reason' => $reason],
            );

            return $request;
        });
    }

    public function assertLineReceivable(SupplyRequestLine $line): void
    {
        $request = $line->supplyRequest;
        abort_if($request && ! $request->isApproved(), 422, 'طلب التوريد '.$request->request_no.' لا يزال بانتظار اعتماد مسؤول المخازن.');
    }

    public function notifySupplyPending(SupplyRequest $request): void
    {
        $this->notifyApprovers(
            '🛒 طلب توريد بانتظار الاعتماد',
            "طلب {$request->request_no} — ".$request->lines()->count().' صنف',
            'supply_request_pending',
        );
    }

    /** @return array{supply_requests: list<array<string, mixed>>, receipts: list<array<string, mixed>>} */
    public function pending(): array
    {
        return [
            'supply_requests' => SupplyRequest::query()
                ->with(['lines.stockItem', 'requestedBy:id,name'])
                ->where('status', SupplyRequest::STATUS_PENDING_APPROVAL)
                ->orderBy('id')
                ->get()
                ->map(fn (SupplyRequest $r) => [
                    'id' => $r->id,
                    'request_no' => $r->request_no,
                    'notes' => $r->notes,
                    'requested_by' => $r->requestedBy?->name,
                    'requested_at' => ClinicTime::format($r->created_at, 'd/m/Y H:i'),
                    'lines' => $r->lines->map(fn (SupplyRequestLine $l) => [
                        'label' => $l->displayLabel(),
                        'qty' => $l->qty,
                        'uom' => $l->uom,
                        'spec' => $l->spec,
                    ])->values()->all(),
                ])->values()->all(),
            'receipts' => StockReceiptRequest::query()
                ->with(['stockItem', 'supplier:id,name', 'requestedBy:id,name', 'supplyRequestLine.supplyRequest'])
                ->where('status', StockReceiptRequest::STATUS_PENDING)
                ->orderBy('id')
                ->get()
                ->map(fn (StockReceiptRequest $r) => [
                    'id' => $r->id,
                    'item' => trim(($r->stockItem?->code ?? '').' — '.($r->stockItem?->name ?? '')),
                    'qty' => (float) $r->qty,
                    'uom' => $r->stockItem?->uom,
                    'unit_price' => (float) $r->unit_price,
                    'total' => round((float) $r->qty * (float) $r->unit_price, 2),
                    'supplier' => $r->supplier?->name,
                    'invoice_no' => $r->invoice_no,
                    'moved_at' => $r->moved_at?->format('d/m/Y'),
                    'supply_request_no' => $r->supplyRequestLine?->supplyRequest?->request_no,
                    'has_document' => (bool) $r->document_path,
                    'requested_by' => $r->requestedBy?->name,
                    'requested_at' => ClinicTime::format($r->created_at, 'd/m/Y H:i'),
                ])->values()->all(),
        ];
    }

    private function notifyApprovers(string $title, string $body, string $event): void
    {
        foreach (['admin', 'technical'] as $role) {
            try {
                app(NotificationService::class)->push(
                    roleSlug: $role,
                    title: $title,
                    body: $body,
                    event: $event,
                    data: ['url' => "/{$role}/stock-approvals"],
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
