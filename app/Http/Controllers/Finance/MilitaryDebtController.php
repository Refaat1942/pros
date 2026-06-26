<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Models\MilitaryDebt;
use App\Services\AuditService;
use App\Services\MilitaryDebtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * مديونيات الجهات العسكرية — قراءة + تحصيل جزئي/كامل.
 */
class MilitaryDebtController extends Controller
{
    public function __construct(
        private readonly MilitaryDebtService $militaryDebtService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = MilitaryDebt::query()
            ->orderBy('status')
            ->orderByDesc('delivered_at')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('patient_name', 'like', "%{$s}%")
                  ->orWhere('sovereign_entity', 'like', "%{$s}%")
                  ->orWhere('work_order_no', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('balance') && $request->input('balance') === 'outstanding') {
            $query->whereColumn('total_cost', '>', 'collected');
        }

        if ($request->filled('balance') && $request->input('balance') === 'settled') {
            $query->whereColumn('total_cost', '<=', 'collected');
        }

        if ($request->filled('entity')) {
            $query->where('sovereign_entity', 'like', '%' . $request->input('entity') . '%');
        }

        $debts = $query->limit(1000)->get();

        return response()->json([
            'data'  => $debts->map(fn ($d) => $this->militaryDebtService->formatDebt($d))->values(),
            'stats' => $this->militaryDebtService->stats($debts),
            'total' => $debts->count(),
        ]);
    }

    public function recordPayment(RecordPaymentRequest $request, MilitaryDebt $militaryDebt): JsonResponse
    {
        if ($militaryDebt->isCollected()) {
            return response()->json([
                'message' => 'السجل مجمَّد — تم اعتماد التحصيل مسبقاً ولا يمكن التعديل.',
                'frozen'  => true,
            ], 422);
        }

        $remaining = $this->militaryDebtService->remaining($militaryDebt);
        $amount    = round((float) $request->validated('amount'), 2);

        if ($remaining <= 0) {
            return response()->json(['message' => 'لا يوجد متبقٍ للتحصيل على هذا السجل.'], 422);
        }

        if ($amount > $remaining) {
            return response()->json([
                'message' => 'المبلغ المُدخل أكبر من المتبقي للتحصيل (' . number_format($remaining, 2) . ' ج.م).',
            ], 422);
        }

        try {
            $debt = $this->militaryDebtService->recordPayment($militaryDebt, $amount);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        $formatted = $this->militaryDebtService->formatDebt($debt);

        return response()->json([
            'message' => $debt->isCollected()
                ? 'تم التحصيل بالكامل — السجل مجمَّد.'
                : 'تم تسجيل جزء من التحصيل — يمكنك إكمال الباقي لاحقاً.',
            'debt'    => $formatted,
        ]);
    }

    /**
     * @deprecated استخدم recordPayment — يُبقى للتوافق مع العملاء القدامى.
     */
    public function updateStatus(Request $request, MilitaryDebt $militaryDebt): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:' . MilitaryDebt::STATUS_PENDING . ',' . MilitaryDebt::STATUS_COLLECTED],
        ]);

        if ($militaryDebt->isCollected()) {
            return response()->json([
                'message' => 'السجل مجمَّد — تم اعتماد التحصيل مسبقاً ولا يمكن التعديل.',
                'frozen'  => true,
            ], 422);
        }

        $newStatus = $request->input('status');
        if ($newStatus === MilitaryDebt::STATUS_COLLECTED) {
            $remaining = $this->militaryDebtService->remaining($militaryDebt);
            if ($remaining > 0) {
                try {
                    $militaryDebt = $this->militaryDebtService->recordPayment($militaryDebt, $remaining);
                } catch (\InvalidArgumentException $e) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }

                return response()->json([
                    'message' => 'تم اعتماد التحصيل وإيداع المبلغ — السجل مجمَّد.',
                    'debt'    => $this->militaryDebtService->formatDebt($militaryDebt),
                ]);
            }
        }

        $before = $militaryDebt->only(['status', 'collected_at']);
        $militaryDebt->update([
            'status'       => $newStatus,
            'collected_at' => null,
        ]);

        AuditService::log(
            action:      'update',
            description: "تحديث حالة مديونية عسكرية — WO: {$militaryDebt->work_order_no}",
            tag:         'financial',
            before:      $before,
            after:       $militaryDebt->fresh()->only(['status', 'collected_at']),
        );

        return response()->json([
            'message' => 'تم تغيير حالة المديونية.',
            'debt'    => $this->militaryDebtService->formatDebt($militaryDebt->fresh()),
        ]);
    }

    public function destroy(MilitaryDebt $militaryDebt): JsonResponse
    {
        if ($militaryDebt->isCollected()) {
            return response()->json([
                'message' => 'لا يمكن حذف سجل محصّل ومجمّد.',
            ], 422);
        }

        $before = $militaryDebt->only(['work_order_no', 'patient_name', 'total_cost', 'status']);
        $militaryDebt->delete();

        AuditService::log(
            action:      'delete',
            description: "حذف مديونية عسكرية — WO: {$before['work_order_no']}",
            tag:         'financial',
            before:      $before,
        );

        return response()->json(['message' => 'تم حذف السجل بنجاح.']);
    }
}
