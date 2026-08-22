<?php

namespace App\Http\Controllers\Bom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bom\CompleteReturnNoteRequest;
use App\Http\Requests\Bom\StoreReturnNoteRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\ReturnNote;
use App\Models\StockItem;
use App\Models\User;
use App\Services\ReturnNoteService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReturnNoteController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly ReturnNoteService $returnNoteService) {}

    /**
     * قائمة إذونات الارتجاع — مرشَّحة بالحالة.
     */
    public function index(Request $request): JsonResponse
    {
        $inboxOnly = $request->boolean('inbox');

        $notes = $this->fetchForDashboard(
            ReturnNote::with(['bom:id,bom_no', 'lines', 'caseRecord:id,case_no', 'createdByUser:id,name'])
                ->when($inboxOnly, fn ($q) => $q->whereIn('status', [
                    ReturnNote::STATUS_AUTHORIZED,
                    ReturnNote::STATUS_PARTIAL,
                ]))
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('return_no', 'like', "%{$s}%")
                        ->orWhere('order_ref', 'like', "%{$s}%")
                        ->orWhere('patient_name', 'like', "%{$s}%");
                }))
                ->orderByDesc('created_at')
        );

        $barcodes = $this->barcodesForNotes(collect($notes));

        return response()->json([
            'data' => collect($notes)->map(fn ($n) => $this->formatNote($n, $barcodes))->values(),
            'total' => $notes->count(),
        ]);
    }

    /**
     * BOMs المتاحة لإنشاء إذن ارتجاع.
     * - افتراضي (قسم الإنتاج): wip فقط.
     * - post_delivery=1 (استقبال): BOM تام + حالة مُسلَّمة.
     */
    public function create(Request $request): JsonResponse
    {
        $postDelivery = $request->boolean('post_delivery');

        $boms = Bom::with(['items', 'caseRecord:id,work_order_no,stage_key'])
            ->when($postDelivery, function ($q) {
                $q->where('stage', Bom::STAGE_FINISHED)
                    ->whereHas('caseRecord', fn ($c) => $c->where('stage_key', CaseRecord::STAGE_DELIVERED));
            }, fn ($q) => $q->where('stage', Bom::STAGE_WIP))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('bom_no', 'like', "%{$s}%")
                    ->orWhere('patient_name', 'like', "%{$s}%")
                    ->orWhere('order_ref', 'like', "%{$s}%");
            }))
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $pendingByItem = BomItem::pendingReturnQtyMapForBoms($boms);

        $boms = $boms
            ->filter(function (Bom $b) use ($pendingByItem) {
                return $b->items->contains(function ($i) use ($pendingByItem, $b) {
                    $pending = $pendingByItem["{$i->bom_id}.{$i->stock_item_code}"] ?? 0;

                    return $i->returnRequestMaxQty($pending, $b->stage) > 0;
                });
            })
            ->values();

        $codes = $boms->flatMap(fn (Bom $b) => $b->items->pluck('stock_item_code'))->unique()->filter()->values()->all();
        $barcodes = collect(StockItem::mapByOperationalCodes($codes, 'barcode'));

        return response()->json([
            'context' => $postDelivery ? 'post_delivery' : 'wip',
            'boms' => $boms->map(function (Bom $b) use ($pendingByItem, $barcodes, $postDelivery) {
                return [
                    'id' => $b->id,
                    'bom_no' => $b->bom_no,
                    'patient_name' => $b->patient_name,
                    'order_ref' => $b->order_ref,
                    'work_order_no' => $b->caseRecord?->work_order_no,
                    'stage' => $b->stage,
                    'post_delivery' => $postDelivery,
                    'items' => $b->items
                        ->filter(function ($i) use ($pendingByItem, $b) {
                            $pending = $pendingByItem["{$i->bom_id}.{$i->stock_item_code}"] ?? 0;

                            return $i->returnRequestMaxQty($pending, $b->stage) > 0;
                        })
                        ->map(function ($i) use ($pendingByItem, $barcodes, $b) {
                            $pending = $pendingByItem["{$i->bom_id}.{$i->stock_item_code}"] ?? 0;

                            return [
                                'stock_item_code' => $i->stock_item_code,
                                'name' => $i->name,
                                'returnable_qty' => $i->returnRequestMaxQty($pending, $b->stage),
                                'issued_qty' => $i->returnableQty(),
                                'barcode' => $barcodes[$i->stock_item_code] ?? null,
                            ];
                        })->values(),
                ];
            }),
        ]);
    }

    /**
     * إنشاء إذن ارتجاع.
     */
    public function store(StoreReturnNoteRequest $request): JsonResponse
    {
        $bom = Bom::findOrFail($request->validated('bom_id'));

        /** @var User $user */
        $user = Auth::user();

        $note = $this->returnNoteService->create(
            $bom,
            $request->validated('lines'),
            $request->validated('reason'),
            $user,
        );

        return response()->json([
            'message' => 'تم إرسال طلب الارتجاع للمخزن — بانتظار الاستلام.',
            'note' => $this->formatNote($note),
        ], 201);
    }

    /**
     * إتمام الارتجاع بالباركود.
     */
    public function complete(CompleteReturnNoteRequest $request, ReturnNote $returnNote): JsonResponse
    {
        $result = $this->returnNoteService->complete(
            $returnNote,
            $request->validated('scanned_lines'),
        );

        $note = $result['note'];
        $stockUpdates = $result['stock_updates'];
        $totalValue = round(collect($stockUpdates)->sum('line_value'), 2);

        return response()->json([
            'message' => $totalValue > 0
                ? 'تم تأكيد استلام المواد المرتجعة — أُعيدت للمخزون بقيمة '.number_format($totalValue, 2).' ج.م.'
                : 'تم تأكيد استلام المواد المرتجعة.',
            'note' => $this->formatNote($note),
            'stock_updates' => $stockUpdates,
            'total_value' => $totalValue,
        ]);
    }

    private function formatNote(ReturnNote $note, ?Collection $barcodes = null): array
    {
        $lines = $note->relationLoaded('lines') ? $note->lines : collect();

        if ($barcodes === null && $lines->isNotEmpty()) {
            $barcodes = collect(StockItem::mapByOperationalCodes(
                $lines->pluck('stock_item_code')->unique()->filter()->values()->all(),
                'barcode'
            ));
        }

        return $note->only([
            'id', 'return_no', 'bom_id', 'case_id', 'order_ref',
            'work_order_no', 'patient_name', 'status', 'created_by',
            'authorized_at', 'completed_at', 'created_at',
        ]) + [
            'case_no' => $note->relationLoaded('caseRecord') && $note->caseRecord
                ? $note->caseRecord->case_no
                : null,
            'created_by_name' => $note->relationLoaded('createdByUser') && $note->createdByUser
                ? $note->createdByUser->name
                : ($note->created_by ?: null),
            'lines' => $lines->map(fn ($line) => [
                'id' => $line->id,
                'stock_item_code' => $line->stock_item_code,
                'name' => $line->name,
                'qty_requested' => $line->qty_requested,
                'qty_returned' => $line->qty_returned,
                'reason' => $line->reason,
                'barcode' => $barcodes[$line->stock_item_code] ?? null,
            ])->values()->all(),
            'bom' => $note->relationLoaded('bom') && $note->bom
                ? $note->bom->only(['id', 'bom_no'])
                : null,
        ];
    }

    /** @param  Collection<int, ReturnNote>  $notes */
    private function barcodesForNotes(Collection $notes): Collection
    {
        $codes = $notes
            ->flatMap(fn (ReturnNote $note) => $note->relationLoaded('lines')
                ? $note->lines->pluck('stock_item_code')
                : collect())
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($codes === []) {
            return collect();
        }

        return collect(StockItem::mapByOperationalCodes($codes, 'barcode'));
    }
}
