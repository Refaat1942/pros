<?php

namespace App\Http\Controllers\Bom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bom\CompleteReturnNoteRequest;
use App\Http\Requests\Bom\StoreReturnNoteRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\ReturnNote;
use App\Models\StockItem;
use App\Services\ReturnNoteService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReturnNoteController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly ReturnNoteService $returnNoteService)
    {
    }

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
            'data'  => collect($notes)->map(fn ($n) => $this->formatNote($n, $barcodes))->values(),
            'total' => $notes->count(),
        ]);
    }

    /**
     * BOMs المتاحة لإنشاء إذن ارتجاع (wip فقط + بنود قابلة للارتجاع).
     */
    public function create(Request $request): JsonResponse
    {
        $boms = Bom::with(['items', 'caseRecord:id,work_order_no'])
            ->where('stage', Bom::STAGE_WIP)
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
                return $b->items->contains(function ($i) use ($pendingByItem) {
                    $pending = $pendingByItem["{$i->bom_id}.{$i->stock_item_code}"] ?? 0;

                    return $i->returnRequestMaxQty($pending) > 0;
                });
            })
            ->values();

        $barcodes = \App\Models\StockItem::whereIn(
            'code',
            $boms->flatMap(fn (Bom $b) => $b->items->pluck('stock_item_code'))->unique()->all()
        )->pluck('barcode', 'code');

        return response()->json([
            'boms' => $boms->map(function (Bom $b) use ($pendingByItem, $barcodes) {
                return [
                'id'            => $b->id,
                'bom_no'        => $b->bom_no,
                'patient_name'  => $b->patient_name,
                'order_ref'     => $b->order_ref,
                'work_order_no' => $b->caseRecord?->work_order_no,
                'items'         => $b->items
                    ->filter(function ($i) use ($pendingByItem) {
                        $pending = $pendingByItem["{$i->bom_id}.{$i->stock_item_code}"] ?? 0;

                        return $i->returnRequestMaxQty($pending) > 0;
                    })
                    ->map(function ($i) use ($pendingByItem, $barcodes) {
                        $pending = $pendingByItem["{$i->bom_id}.{$i->stock_item_code}"] ?? 0;

                        return [
                        'stock_item_code' => $i->stock_item_code,
                        'name'            => $i->name,
                        'returnable_qty'  => $i->returnRequestMaxQty($pending),
                        'issued_qty'      => $i->returnableQty(),
                        'barcode'         => $barcodes[$i->stock_item_code] ?? null,
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

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $note = $this->returnNoteService->create(
            $bom,
            $request->validated('lines'),
            $request->validated('reason'),
            $user,
        );

        return response()->json([
            'message' => 'تم إرسال طلب الارتجاع للمخزن — بانتظار الاستلام.',
            'note'    => $this->formatNote($note),
        ], 201);
    }

    /**
     * إتمام الارتجاع بالباركود.
     */
    public function complete(CompleteReturnNoteRequest $request, ReturnNote $returnNote): JsonResponse
    {
        $note = $this->returnNoteService->complete(
            $returnNote,
            $request->validated('scanned_lines'),
        );

        return response()->json([
            'message' => 'تم تأكيد استلام المواد المرتجعة.',
            'note'    => $this->formatNote($note),
        ]);
    }

    private function formatNote(ReturnNote $note, ?\Illuminate\Support\Collection $barcodes = null): array
    {
        $lines = $note->relationLoaded('lines') ? $note->lines : collect();

        if ($barcodes === null && $lines->isNotEmpty()) {
            $barcodes = StockItem::query()
                ->whereIn('code', $lines->pluck('stock_item_code')->unique()->all())
                ->pluck('barcode', 'code');
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
                'id'              => $line->id,
                'stock_item_code' => $line->stock_item_code,
                'name'            => $line->name,
                'qty_requested'   => $line->qty_requested,
                'qty_returned'    => $line->qty_returned,
                'reason'          => $line->reason,
                'barcode'         => $barcodes[$line->stock_item_code] ?? null,
            ])->values()->all(),
            'bom' => $note->relationLoaded('bom') && $note->bom
                ? $note->bom->only(['id', 'bom_no'])
                : null,
        ];
    }

    /** @param  \Illuminate\Support\Collection<int, ReturnNote>  $notes */
    private function barcodesForNotes(\Illuminate\Support\Collection $notes): \Illuminate\Support\Collection
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

        return StockItem::query()->whereIn('code', $codes)->pluck('barcode', 'code');
    }
}
