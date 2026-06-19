<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RejectCreditNoteRequest;
use App\Http\Requests\Finance\StoreCreditNoteRequest;
use App\Models\CaseRecord;
use App\Models\CreditNote;
use App\Services\CreditNoteService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreditNoteController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly CreditNoteService $creditNoteService)
    {
    }

    /**
     * قائمة إشعارات الدائن — مرشَّحة بالحالة.
     */
    public function index(Request $request): JsonResponse
    {
        $notes = $this->fetchForDashboard(
            CreditNote::query()
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('credit_note_no', 'like', "%{$s}%")
                      ->orWhere('patient_name', 'like', "%{$s}%")
                      ->orWhere('company_name', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%");
                }))
                ->orderByDesc('created_at')
        );

        return response()->json([
            'data'  => collect($notes)->map(fn ($n) => $this->formatNote($n))->values(),
            'total' => $notes->count(),
        ]);
    }

    /**
     * إنشاء إشعار دائن — مدني بعد التسليم فقط.
     */
    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        $case = CaseRecord::findOrFail($request->validated('case_id'));

        $note = $this->creditNoteService->create(
            $case,
            $request->validated('type'),
            (float) $request->validated('amount'),
            $request->validated('reason'),
        );

        return response()->json([
            'message'     => 'تم إنشاء إشعار الدائن.',
            'credit_note' => $this->formatNote($note),
        ], 201);
    }

    /**
     * اعتماد إشعار دائن — تخفيض المستحق.
     */
    public function approve(CreditNote $creditNote): JsonResponse
    {
        /** @var \App\Models\User $approver */
        $approver = Auth::user();

        $note = $this->creditNoteService->apply($creditNote, $approver);

        return response()->json([
            'message'     => 'تم اعتماد إشعار الدائن.',
            'credit_note' => $this->formatNote($note),
        ]);
    }

    /**
     * رفض إشعار دائن معلّق.
     */
    public function reject(RejectCreditNoteRequest $request, CreditNote $creditNote): JsonResponse
    {
        /** @var \App\Models\User $approver */
        $approver = Auth::user();

        $note = $this->creditNoteService->reject(
            $creditNote,
            $approver,
            $request->validated('reason'),
        );

        return response()->json([
            'message'     => 'تم رفض إشعار الدائن.',
            'credit_note' => $this->formatNote($note),
        ]);
    }

    private function formatNote(CreditNote $note): array
    {
        return $note->only([
            'id', 'credit_note_no', 'case_id', 'order_ref',
            'patient_name', 'company_name', 'type', 'amount',
            'original_total', 'reason', 'status', 'approved_at', 'approved_by',
        ]);
    }
}
