<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\DeliveryService;
use App\Support\ManufacturingDeskCaseFormatter;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturingStageController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly BomService $bomService,
        private readonly DeliveryService $deliveryService,
    ) {
    }

    /**
     * طابور التسليم — حالات جاهزة بعد إتمام التصنيع من الورشة.
     */
    public function index(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::operationsDeliveryQueue()
                ->with([
                    'patient:id,patient_code,name',
                    'bom:id,case_id,bom_no,stage',
                    'bom.items:id,bom_id,stock_item_code,name,qty,source',
                ])
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%")
                      ->orWhere('work_order_no', 'like', "%{$s}%");
                }))
                ->orderByDesc('updated_at')
        );

        $collection = collect($cases);
        $summary    = ManufacturingDeskCaseFormatter::deliverySummary($collection);

        return response()->json([
            'data'    => $collection->map(fn ($c) => ManufacturingDeskCaseFormatter::format($c, 'operations.work-order.print'))->values(),
            'total'   => $collection->count(),
            'summary' => $summary,
        ]);
    }

    /**
     * تسليم الطرف وإغلاق الحالة — من مكتب التشغيل.
     */
    public function deliver(CaseRecord $case): JsonResponse
    {
        $case->load('patient');

        if (! $case->patient?->patient_qr) {
            abort(422, 'بطاقة المريض غير متاحة — لا يمكن إتمام التسليم.');
        }

        try {
            $case = $this->deliveryService->close($case, $case->patient->patient_qr);
        } catch (\App\Exceptions\DeliveryNotReadyException $e) {
            return response()->json(['message' => $e->getMessage(), 'blocked' => true], 422);
        } catch (\App\Exceptions\InvalidPatientQrException $e) {
            return response()->json([
                'message'  => $e->getMessage(),
                'blocked'  => true,
                'security' => true,
            ], 422);
        }

        return response()->json([
            'message'    => 'تم تسليم الطرف وإغلاق الطلب بنجاح.',
            'case'       => ManufacturingDeskCaseFormatter::format($case->load(['patient:id,patient_code,name', 'bom']), 'operations.work-order.print'),
            'invoice_no' => $case->invoice_no,
            'closed'     => true,
        ]);
    }

    /**
     * إذن شغل — للطباعة من مكتب التشغيل عند التسليم.
     */
    public function printWorkOrder(CaseRecord $case): \Illuminate\View\View
    {
        abort_unless($case->work_order_no, 404, 'لا يوجد أمر تشغيل لهذه الحالة.');

        $case->load(['patient', 'bom.items']);

        abort_unless($case->bom, 404, 'لا توجد BOM مرتبطة بهذه الحالة.');

        return view('prints.work-order', [
            'case'      => $case,
            'autoPrint' => true,
        ]);
    }
}
