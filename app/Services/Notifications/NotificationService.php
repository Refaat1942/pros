<?php

namespace App\Services\Notifications;

use App\Enums\WorkflowEvent;
use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\UserDevice;

/**
 * مركز الإشعارات بين اللوحات — يترجم أحداث محرك التدفق إلى إشعارات
 * مستهدفة للدور/اللوحة التالية، برسالة عربية واضحة، ثم:
 *   1) يحفظها داخلياً (تظهر في جرس كل لوحة عبر Polling + صوت).
 *   2) يرسلها Push عبر FCM لأجهزة مستخدمي ذلك الدور.
 */
class NotificationService
{
    public function __construct(private readonly FirebaseService $firebase)
    {
    }

    /**
     * خريطة الحدث → [الدور المستهدف, العنوان, قالب الرسالة].
     * {patient} و{case} يُستبدلان باسم المريض ورقم الحالة.
     *
     * @var array<string, array{role: string, title: string, body: string}>
     */
    private const MAP = [
        WorkflowEvent::ExamApproved->value => [
            'role'  => Role::SLUG_SPEC,
            'title' => '🔧 حالة جديدة للتوصيف الفني',
            'body'  => 'المريض {patient} (حالة {case}) جاهز للتوصيف الفني بعد اعتماد الكشف.',
        ],
        WorkflowEvent::ExamSkipped->value => [
            'role'  => Role::SLUG_SPEC,
            'title' => '🔧 حالة جديدة للتوصيف الفني',
            'body'  => 'المريض {patient} (حالة {case}) محوّل مباشرةً للتوصيف (تم تخطّي الكشف).',
        ],
        WorkflowEvent::SpecSaved->value => [
            'role'  => Role::SLUG_ADJUSTMENTS,
            'title' => '📏 حالة بانتظار المعدلات',
            'body'  => 'المريض {patient} (حالة {case}) وصل لمكتب المعدلات لمراجعة وإضافة البنود.',
        ],
        WorkflowEvent::AdjustmentsCompleted->value => [
            'role'  => Role::SLUG_COSTING,
            'title' => '🧮 حالة بانتظار التكاليف',
            'body'  => 'المريض {patient} (حالة {case}) جاهز لمراجعة التكلفة وتأكيد عرض السعر.',
        ],
        WorkflowEvent::QuoteIssued->value => [
            'role'  => Role::SLUG_OPERATIONS,
            'title' => '🎯 حالة بانتظار مكتب التشغيل',
            'body'  => 'المريض {patient} (حالة {case}) بانتظار اعتماد مكتب التشغيل وإصدار أمر الصرف.',
        ],
        WorkflowEvent::OperationsApproved->value => [
            'role'  => Role::SLUG_TECHNICAL,
            'title' => '📦 أمر صرف جديد للمخزن',
            'body'  => 'المريض {patient} (حالة {case}) معتمد — جاهز للصرف بالباركود من المخزن.',
        ],
        WorkflowEvent::ReturnedToAdjustments->value => [
            'role'  => Role::SLUG_ADJUSTMENTS,
            'title' => '↩️ حالة أُعيدت للمعدلات',
            'body'  => 'المريض {patient} (حالة {case}) أُعيد من مكتب التشغيل لمراجعة المعدلات.',
        ],
        WorkflowEvent::ReturnedToTechnical->value => [
            'role'  => Role::SLUG_SPEC,
            'title' => '↩️ حالة أُعيدت للتوصيف',
            'body'  => 'المريض {patient} (حالة {case}) أُعيد لإعادة التوصيف الفني.',
        ],
        WorkflowEvent::BomDispensed->value => [
            'role'  => Role::SLUG_TECHNICAL,
            'title' => '🏭 بدء التصنيع في الورشة',
            'body'  => 'تم صرف مواد المريض {patient} (حالة {case}) — دخلت الورشة للتصنيع.',
        ],
        WorkflowEvent::BomFinished->value => [
            'role'  => Role::SLUG_RECEPTION,
            'title' => '✅ طرف جاهز للتسليم',
            'body'  => 'المريض {patient} (حالة {case}) جاهز للتسليم.',
        ],
        WorkflowEvent::Delivered->value => [
            'role'  => Role::SLUG_ADMIN,
            'title' => '📁 تم تسليم وإغلاق حالة',
            'body'  => 'تم تسليم الطرف للمريض {patient} (حالة {case}) وإغلاق الحالة.',
        ],
    ];

    /**
     * يُطلق إشعار انتقال المرحلة للدور المستهدف.
     */
    public function notifyTransition(CaseRecord $case, string $event): ?AppNotification
    {
        $rule = self::MAP[$event] ?? null;

        if ($rule === null) {
            return null;
        }

        $case->loadMissing('patient:id,name,patient_code');
        $patient = $case->patient?->name ?? 'غير معروف';
        $caseNo  = $case->case_no ?? ('#' . $case->id);

        $body = strtr($rule['body'], ['{patient}' => $patient, '{case}' => $caseNo]);

        return $this->push(
            roleSlug: $rule['role'],
            title:    $rule['title'],
            body:     $body,
            case:     $case,
            event:    $event,
        );
    }

    /**
     * يحفظ الإشعار داخلياً ويرسله Push لأجهزة الدور المستهدف.
     */
    public function push(string $roleSlug, string $title, string $body, ?CaseRecord $case = null, ?string $event = null, array $data = []): AppNotification
    {
        $notification = AppNotification::create([
            'role_slug' => $roleSlug,
            'case_id'   => $case?->id,
            'event'     => $event,
            'title'     => $title,
            'body'      => $body,
            'data'      => $data ?: null,
        ]);

        $tokens = $this->tokensForRole($roleSlug);

        if ($tokens !== []) {
            $this->firebase->sendToTokens($tokens, $title, $body, array_merge([
                'notification_id' => (string) $notification->id,
                'role'            => $roleSlug,
                'case_id'         => (string) ($case?->id ?? ''),
                'case_no'         => (string) ($case?->case_no ?? ''),
            ], array_map('strval', $data)));
        }

        return $notification;
    }

    /**
     * استقبال → عيادة: إشعار الطبيب بمريض جديد في قائمة الانتظار.
     */
    public function notifyDoctorClinicTransfer(Appointment $appointment): AppNotification
    {
        $appointment->loadMissing('patient:id,name,patient_type');

        $patientName = $appointment->patient_name
            ?? $appointment->patient?->name
            ?? 'مريض';
        $entity = $appointment->displayEntity();
        $pathway = $appointment->isMilitary() ? 'عسكري' : 'مدني';

        return $this->push(
            roleSlug: Role::SLUG_DOCTOR,
            title:    '🩺 مريض جديد في قائمة الانتظار',
            body:     "تم تحويل المريض {$patientName} ({$entity} — {$pathway}) من الاستقبال — جاهز للكشف.",
            case:     null,
            event:    'patient_transferred_to_clinic',
            data:     [
                'appointment_id' => (string) $appointment->id,
                'patient_id'     => (string) ($appointment->patient_id ?? ''),
                'patient_name'   => $patientName,
                'url'            => '/doctor/queue',
            ],
        );
    }

    /**
     * أجهزة (FCM tokens) كل مستخدمي دور معيّن.
     *
     * @return list<string>
     */
    private function tokensForRole(string $roleSlug): array
    {
        return UserDevice::query()
            ->whereHas('user.role', fn ($q) => $q->where('slug', $roleSlug))
            ->pluck('device_id')
            ->all();
    }
}
