<?php

namespace Tests\Feature\Integrity;

use App\Models\CaseRecord;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * H-7: توليد رقم أمر الشغل يتخطّى أي رقم مستخدَم بالفعل بدل الفشل بخطأ فريد.
 */
class WorkOrderUniquenessTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    public function test_generate_skips_existing_number_instead_of_failing(): void
    {
        $service = app(WorkOrderService::class);
        $year = now()->year;

        // الحالة الأولى تولّد WO-YYYY-0001.
        $case1 = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_OPERATIONS);
        $wo1 = $service->generate($case1);
        $this->assertSame(sprintf('WO-%s-0001', $year), $wo1);

        // حالة ثانية «تحجز» الرقم التالي (0002) يدوياً — محاكاة سباق.
        $case2 = $this->caseAtStage($this->civilianPatient($this->civilianCompany()), CaseRecord::STAGE_OPERATIONS);
        CaseRecord::where('id', $case2->id)->update(['work_order_no' => sprintf('WO-%s-0002', $year)]);

        // حالة ثالثة تولّد — يجب أن تتخطّى 0002 المحجوز وتنتج 0003 دون خطأ فريد.
        $case3 = $this->caseAtStage($this->militaryPatient($this->militaryCompany()), CaseRecord::STAGE_OPERATIONS);
        $case3->update(['work_order_no' => null]);
        $wo3 = $service->generate($case3->fresh());

        $this->assertNotSame(sprintf('WO-%s-0002', $year), $wo3);
        $this->assertSame(1, CaseRecord::where('work_order_no', $wo3)->count());
    }

    public function test_generate_is_idempotent_when_already_set(): void
    {
        $patient = $this->cashPatient();
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update(['work_order_no' => 'WO-EXISTING-0001']);

        $wo = app(WorkOrderService::class)->generate($case->fresh());

        $this->assertSame('WO-EXISTING-0001', $wo);
    }
}
