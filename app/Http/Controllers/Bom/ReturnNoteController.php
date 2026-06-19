<?php

namespace App\Http\Controllers\Bom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bom\CompleteReturnNoteRequest;
use App\Http\Requests\Bom\StoreReturnNoteRequest;
use App\Models\Bom;
use App\Models\ReturnNote;
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
        $notes = $this->fetchForDashboard(
            ReturnNote::with(['bom:id,bom_no', 'lines'])
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('return_no', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%")
                      ->orWhere('patient_name', 'like', "%{$s}%");
                }))
                ->orderByDesc('created_at')
        );

        return response()->json([
            'data'  => collect($notes)->map(fn ($n) => $this->formatNote($n))->values(),
            'total' => $notes->count(),
        ]);
    }

    /**
     * BOMs المتاحة لإنشاء إذن ارتجاع (wip / finished).
     */
    public function create(Request $request): JsonResponse
    {
        $boms = Bom::with('items')
            ->whereIn('stage', [Bom::STAGE_WIP, Bom::STAGE_FINISHED])
            ->when($request->search, fn ($q, $s) => $q->where('bom_no', 'like', "%{$s}%"))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'boms' => $boms->map(fn (Bom $b) => [
                'id'           => $b->id,
                'bom_no'       => $b->bom_no,
                'patient_name' => $b->patient_name,
                'order_ref'    => $b->order_ref,
                'items'        => $b->items->map(fn ($i) => [
                    'stock_item_code' => $i->stock_item_code,
                    'name'            => $i->name,
                    'returnable_qty'  => $i->returnableQty(),
                ]),
            ]),
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
            'message' => 'تم إنشاء إذن الارتجاع.',
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
            'message' => 'تم إتمام الارتجاع.',
            'note'    => $this->formatNote($note),
        ]);
    }

    private function formatNote(ReturnNote $note): array
    {
        return $note->only([
            'id', 'return_no', 'bom_id', 'case_id', 'order_ref',
            'work_order_no', 'patient_name', 'status',
            'authorized_at', 'completed_at',
        ]) + [
            'lines' => $note->relationLoaded('lines')
                ? $note->lines->map->only([
                    'id', 'stock_item_code', 'name',
                    'qty_requested', 'qty_returned', 'reason',
                ])
                : [],
            'bom' => $note->relationLoaded('bom') && $note->bom
                ? $note->bom->only(['id', 'bom_no'])
                : null,
        ];
    }
}
