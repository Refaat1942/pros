<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\MilitaryDebt;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مديونيات الجهات العسكرية — قراءة + تحديث حالة التحصيل.
 *
 * كل سجل يُنشأ تلقائياً عند إغلاق حالة عسكرية (DeliveryService → postMilitary).
 * الأدمن المالي يغيّر الحالة إلى "تم التحصيل" فيُجمَّد السجل.
 */
class MilitaryDebtController extends Controller
{
    /**
     * قائمة مديونيات الجهات العسكرية.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MilitaryDebt::query()
            ->orderBy('status')                  // pending أولاً
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

        if ($request->filled('entity')) {
            $query->where('sovereign_entity', 'like', '%' . $request->input('entity') . '%');
        }

        $debts = $query->limit(1000)->get();

        return response()->json([
            'data'  => $debts->map(fn ($d) => $this->format($d))->values(),
            'total' => $debts->count(),
        ]);
    }

    /**
     * تحديث حالة التحصيل — يُجمَّد السجل عند الاعتماد.
     */
    public function updateStatus(Request $request, MilitaryDebt $militaryDebt): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:' . MilitaryDebt::STATUS_PENDING . ',' . MilitaryDebt::STATUS_COLLECTED],
        ]);

        // السجل المحصَّل محمي — لا يمكن الرجوع عنه
        if ($militaryDebt->isCollected()) {
            return response()->json([
                'message' => 'السجل مجمَّد — تم اعتماد التحصيل مسبقاً ولا يمكن التعديل.',
                'frozen'  => true,
            ], 422);
        }

        $newStatus = $request->input('status');
        $before    = $militaryDebt->only(['status', 'collected_at']);

        $militaryDebt->update([
            'status'       => $newStatus,
            'collected_at' => $newStatus === MilitaryDebt::STATUS_COLLECTED ? now() : null,
        ]);

        AuditService::log(
            action:      'update',
            description: "تحديث حالة مديونية عسكرية — WO: {$militaryDebt->work_order_no}",
            tag:         'financial',
            before:      $before,
            after:       $militaryDebt->fresh()->only(['status', 'collected_at']),
        );

        return response()->json([
            'message' => $newStatus === MilitaryDebt::STATUS_COLLECTED
                ? 'تم اعتماد التحصيل وإيداع الحساب — السجل مجمَّد.'
                : 'تم تغيير حالة المديونية.',
            'debt'    => $this->format($militaryDebt->fresh()),
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

    private function format(MilitaryDebt $d): array
    {
        return [
            'id'                  => $d->id,
            'case_id'             => $d->case_id,
            'work_order_no'       => $d->work_order_no,
            'patient_name'        => $d->patient_name,
            'patient_national_id' => $d->patient_national_id,
            'sovereign_entity'    => $d->sovereign_entity,
            'total_cost'          => (float) $d->total_cost,
            'delivered_at'        => $d->delivered_at ? (string) $d->delivered_at : null,
            'status'              => $d->status,
            'status_label'        => $d->status === MilitaryDebt::STATUS_COLLECTED
                ? 'تم التحصيل وإيداع الحساب'
                : 'بانتظار التحصيل',
            'collected_at'        => $d->collected_at?->format('Y-m-d H:i'),
            'is_frozen'           => $d->isCollected(),
        ];
    }
}
