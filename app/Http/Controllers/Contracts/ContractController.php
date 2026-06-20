<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Models\ApprovalContract;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * أرشيف العقود والاتفاقيات المالية (مدني فقط).
 *
 * الاستقبال: عرض + تحميل فقط (read-only).
 * الإدارة:   تحكم كامل — تعديل + حذف.
 */
class ContractController extends Controller
{
    /**
     * قائمة العقود — يُستدعى من كلا الـ dashboards.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApprovalContract::query()
            ->with(['caseRecord:id,case_no,stage_key'])
            ->orderByDesc('approval_date')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('patient_name', 'like', "%{$s}%")
                  ->orWhere('company_name', 'like', "%{$s}%")
                  ->orWhere('contract_no', 'like', "%{$s}%")
                  ->orWhere('work_order_no', 'like', "%{$s}%");
            });
        }

        if ($request->filled('company')) {
            $query->where('company_name', 'like', '%' . $request->input('company') . '%');
        }

        $contracts = $query->limit(500)->get();

        return response()->json([
            'data'  => $contracts->map(fn ($c) => $this->formatContract($c))->values(),
            'total' => $contracts->count(),
        ]);
    }

    /**
     * تفاصيل عقد واحد.
     */
    public function show(ApprovalContract $contract): JsonResponse
    {
        $contract->load('caseRecord:id,case_no,stage_key,patient_type');

        return response()->json($this->formatContract($contract));
    }

    /**
     * تحميل ملف خطاب الموافقة.
     */
    public function download(ApprovalContract $contract): BinaryFileResponse
    {
        abort_unless($contract->letter_path && Storage::disk('public')->exists($contract->letter_path), 404);

        $storagePath = storage_path('app/public/' . $contract->letter_path);
        $downloadName = 'approval_letter_' . $contract->contract_no . '.' . pathinfo($contract->letter_path, PATHINFO_EXTENSION);

        return response()->download($storagePath, $downloadName);
    }

    /**
     * تعديل بيانات العقد — إدارة فقط.
     */
    public function update(Request $request, ApprovalContract $contract): JsonResponse
    {
        $data = $request->validate([
            'approved_amount' => ['sometimes', 'numeric', 'min:0'],
            'letter_ref'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'letter_date'     => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_name'    => ['sometimes', 'string', 'max:255'],
        ]);

        $before = $contract->only(['approved_amount', 'letter_ref', 'company_name']);
        $contract->update($data);

        AuditService::log(
            action:      'update',
            description: "تعديل عقد {$contract->contract_no}",
            tag:         'contracts',
            before:      $before,
            after:       $contract->fresh()->only(['approved_amount', 'letter_ref', 'company_name']),
        );

        return response()->json([
            'message'  => 'تم تحديث العقد بنجاح.',
            'contract' => $this->formatContract($contract->fresh()),
        ]);
    }

    /**
     * حذف عقد — إدارة فقط.
     */
    public function destroy(ApprovalContract $contract): JsonResponse
    {
        AuditService::log(
            action:      'delete',
            description: "حذف عقد {$contract->contract_no}",
            tag:         'contracts',
            before:      $contract->only(['contract_no', 'patient_name', 'approved_amount']),
        );

        if ($contract->letter_path && Storage::disk('public')->exists($contract->letter_path)) {
            Storage::disk('public')->delete($contract->letter_path);
        }

        $contract->delete();

        return response()->json(['message' => 'تم حذف العقد.']);
    }

    private function formatContract(ApprovalContract $c): array
    {
        return [
            'id'              => $c->id,
            'contract_no'     => $c->contract_no,
            'case_id'         => $c->case_id,
            'case_no'         => $c->caseRecord?->case_no,
            'patient_name'    => $c->patient_name,
            'company_name'    => $c->company_name,
            'approved_amount' => (float) $c->approved_amount,
            'approval_date'   => $c->approval_date ? (string) $c->approval_date : null,
            'work_order_no'   => $c->work_order_no,
            'letter_ref'      => $c->letter_ref,
            'letter_date'     => $c->letter_date,
            'has_letter'      => (bool) $c->letter_path,
            'download_url'    => $c->letter_path ? route('contracts.download', $c) : null,
            'created_at'      => $c->created_at?->toDateString(),
        ];
    }
}
