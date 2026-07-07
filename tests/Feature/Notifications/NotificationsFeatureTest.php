<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkflowEvent;
use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\UserDevice;
use App\Services\BomService;
use App\Services\StockPriceService;
use App\Services\WorkflowService;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * إشعارات بين اللوحات (Firebase/FCM + جرس داخلي).
 *
 * Firebase معطّل في بيئة الاختبار (firebase.enabled=false) فلا إرسال شبكي،
 * لكن الإشعارات الداخلية تُحفظ وتُختبر، وبيانات الجهاز تُسجَّل عند الدخول.
 */
class NotificationsFeatureTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    public function test_reception_transfer_to_clinic_notifies_doctor(): void
    {
        $company = $this->civilianCompany();
        $recep = $this->userWithRole('reception');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض إشعار الطبيب');

        $this->assertSame(0, AppNotification::forRole(Role::SLUG_DOCTOR)->count());

        $appointment = $this->transferPatientToClinicHttp($recep, $patient);

        $notification = AppNotification::forRole(Role::SLUG_DOCTOR)
            ->where('event', 'patient_transferred_to_clinic')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('مريض إشعار الطبيب', $notification->body);
        $this->assertStringContainsString('قائمة الانتظار', $notification->title);
        $this->assertSame((string) $appointment->id, $notification->data['appointment_id'] ?? null);
    }

    public function test_spec_submit_notifies_adjustments_dashboard(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        app(WorkflowService::class)->advance($case, WorkflowEvent::SpecSaved->value);

        $notification = AppNotification::forRole(Role::SLUG_ADJUSTMENTS)->first();

        $this->assertNotNull($notification);
        $this->assertSame($case->id, $notification->case_id);
        $this->assertSame(WorkflowEvent::SpecSaved->value, $notification->event);
        $this->assertStringContainsString($patient->name, $notification->body);
        $this->assertStringContainsString('المعدلات', $notification->title);
    }

    public function test_operations_approval_notifies_warehouse(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        app(WorkflowService::class)->advance($case, WorkflowEvent::OperationsApproved->value);

        $this->assertSame(
            1,
            AppNotification::forRole(Role::SLUG_TECHNICAL)
                ->where('event', WorkflowEvent::OperationsApproved->value)
                ->count(),
        );
    }

    public function test_workshop_finish_notifies_warehouse(): void
    {
        $item = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch(
            $item,
            10,
            200.0,
            $this->makeSupplier(),
            'INV-NOTIF',
            now(),
        );

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('workshop');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-NOTIF-01']);
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->assertSame(
            0,
            AppNotification::forRole(Role::SLUG_RECEPTION)
                ->where('event', WorkflowEvent::BomFinished->value)
                ->count(),
        );

        $this->postJson("/workshop/workshop/{$case->id}/finish-quality")->assertOk();

        // تسليم المرضى في الاستقبال — الإشعار يذهب لموظف الاستقبال.
        $notification = AppNotification::forRole(Role::SLUG_RECEPTION)
            ->where('event', WorkflowEvent::BomFinished->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($case->id, $notification->case_id);
        $this->assertStringContainsString($patient->name, $notification->body);
        $this->assertStringContainsString('الاستقبال', $notification->title);
        $this->assertSame(
            0,
            AppNotification::forRole(Role::SLUG_TECHNICAL)
                ->where('event', WorkflowEvent::BomFinished->value)
                ->count(),
        );
    }

    public function test_login_registers_device_id_and_type(): void
    {
        $this->userWithRole('reception');

        $this->post('/reception/login', [
            'username' => 'reception',
            'password' => 'password',
            'device_id' => 'fcm-token-abc-123',
            'device_type' => 'web',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_devices', [
            'device_id' => 'fcm-token-abc-123',
            'device_type' => 'web',
        ]);
    }

    public function test_login_without_device_still_succeeds(): void
    {
        $this->userWithRole('doctor');

        $this->post('/doctor/login', [
            'username' => 'doctor',
            'password' => 'password',
        ])->assertRedirect(route('doctor.dashboard'));

        $this->assertSame(0, UserDevice::count());
    }

    public function test_device_register_endpoint_upserts_token(): void
    {
        $user = $this->userWithRole('technical');

        $this->actingAs($user)
            ->postJson('/devices', ['device_id' => 'tok-1', 'device_type' => 'web'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // نفس التوكن مرة أخرى → تحديث لا تكرار.
        $this->actingAs($user)
            ->postJson('/devices', ['device_id' => 'tok-1', 'device_type' => 'android']);

        $this->assertSame(1, UserDevice::where('device_id', 'tok-1')->count());
        $this->assertSame('android', UserDevice::where('device_id', 'tok-1')->value('device_type'));
    }

    public function test_notifications_page_renders_with_pagination(): void
    {
        $user = $this->userWithRole('adjustments');

        for ($i = 1; $i <= 12; $i++) {
            AppNotification::create([
                'role_slug' => Role::SLUG_ADJUSTMENTS,
                'title' => "إشعار {$i}",
                'body' => "نص الإشعار رقم {$i}",
            ]);
        }

        $this->actingAs($user)
            ->get('/adjustments/notifications')
            ->assertOk()
            ->assertSee('سجل الإشعارات')
            ->assertSee('notif-feed', false)
            ->assertSee('dash-pagination', false)
            ->assertSee('إشعار 1')
            ->assertSee('تعليم الكل كمقروء');
    }

    public function test_notifications_page_unread_filter_empty_after_auto_mark(): void
    {
        $user = $this->userWithRole('spec');

        AppNotification::create([
            'role_slug' => Role::SLUG_SPEC,
            'title' => 'مقروء',
            'body' => 'قديم',
            'read_at' => now(),
        ]);
        AppNotification::create([
            'role_slug' => Role::SLUG_SPEC,
            'title' => 'جديد',
            'body' => 'غير مقروء',
        ]);

        $this->actingAs($user)
            ->get('/spec/notifications?filter=unread')
            ->assertOk()
            ->assertSee('لا توجد إشعارات غير مقروءة')
            ->assertDontSee('notif-pill-new', false);

        $this->assertSame(0, AppNotification::forRole(Role::SLUG_SPEC)->unread()->count());
    }

    public function test_mark_all_read_clears_unread_for_role(): void
    {
        $user = $this->userWithRole('operations');

        AppNotification::create(['role_slug' => Role::SLUG_OPERATIONS, 'title' => 'أ', 'body' => 'ب']);
        AppNotification::create(['role_slug' => Role::SLUG_OPERATIONS, 'title' => 'ج', 'body' => 'د']);

        $this->actingAs($user)
            ->post('/notifications/read-all')
            ->assertRedirect();

        $this->assertSame(0, AppNotification::forRole(Role::SLUG_OPERATIONS)->unread()->count());
    }

    public function test_notifications_page_marks_all_as_read_on_visit(): void
    {
        $user = $this->userWithRole('technical');

        AppNotification::create(['role_slug' => Role::SLUG_TECHNICAL, 'title' => 'صرف', 'body' => 'أمر جديد']);
        AppNotification::create(['role_slug' => Role::SLUG_TECHNICAL, 'title' => 'تسليم', 'body' => 'طرف جاهز']);
        AppNotification::create(['role_slug' => Role::SLUG_DOCTOR, 'title' => 'طبيب', 'body' => 'لا يُمس']);

        $this->actingAs($user)
            ->get('/technical/notifications')
            ->assertOk()
            ->assertSee('سجل الإشعارات');

        $this->assertSame(0, AppNotification::forRole(Role::SLUG_TECHNICAL)->unread()->count());
        $this->assertSame(1, AppNotification::forRole(Role::SLUG_DOCTOR)->unread()->count());
    }

    public function test_reception_notified_when_last_patient_exam_is_locked(): void
    {
        $company = $this->civilianCompany();
        $recep = $this->userWithRole('reception');
        $doctor = $this->userWithRole('doctor');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض آخر كشف');
        $this->transferPatientToClinicHttp($recep, $patient);

        $appointmentId = Appointment::where('patient_id', $patient->id)->value('id');

        $this->actingAs($doctor)->postJson('/doctor/diagnosis', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis' => 'تشخيص اختبار الإشعار',
            'lock' => true,
        ])->assertCreated();

        $notification = AppNotification::forRole(Role::SLUG_RECEPTION)
            ->where('event', 'doctor_clinic_queue_empty')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('متاحة', $notification->title);
        $this->assertStringContainsString('انتهت قائمة انتظار الطبيب', $notification->body);
    }

    public function test_reception_not_notified_when_other_patients_still_waiting(): void
    {
        $company = $this->civilianCompany();
        $recep = $this->userWithRole('reception');
        $doctor = $this->userWithRole('doctor');

        $first = $this->registerCivilianPatientHttp($recep, $company, 'مريض أول');
        $second = $this->registerCivilianPatientHttp($recep, $company, 'مريض ثاني');
        $this->transferPatientToClinicHttp($recep, $first);
        $this->transferPatientToClinicHttp($recep, $second);

        $firstAppointmentId = Appointment::where('patient_id', $first->id)->value('id');

        $this->actingAs($doctor)->postJson('/doctor/diagnosis', [
            'patient_id' => $first->id,
            'appointment_id' => $firstAppointmentId,
            'diagnosis' => 'تشخيص المريض الأول',
            'lock' => true,
        ])->assertCreated();

        $this->assertSame(
            0,
            AppNotification::forRole(Role::SLUG_RECEPTION)
                ->where('event', 'doctor_clinic_queue_empty')
                ->count(),
        );
        $this->assertSame(1, $this->queues()->doctorWaitingCount());
    }

    public function test_reception_notified_when_last_patient_skips_exam(): void
    {
        $company = $this->civilianCompany();
        $recep = $this->userWithRole('reception');
        $doctor = $this->userWithRole('doctor');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض تخطي');
        $appointment = $this->transferPatientToClinicHttp($recep, $patient);

        $this->actingAs($doctor)
            ->postJson("/doctor/diagnosis/{$appointment->id}/skip")
            ->assertOk();

        $this->assertSame(
            1,
            AppNotification::forRole(Role::SLUG_RECEPTION)
                ->where('event', 'doctor_clinic_queue_empty')
                ->count(),
        );
    }
}
