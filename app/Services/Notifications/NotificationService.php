<?php

namespace App\Services\Notifications;

use App\Enums\SpecEditRequestSource;
use App\Enums\WorkflowEvent;
use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\SpecEditRequest;
use App\Models\UserDevice;

/**
 * مركز الإشعارات بين اللوحات — يترجم أحداث محرك التدفق إلى إشعارات
 * مستهدفة للدور/اللوحة التالية، برسالة عربية واضحة، ثم:
 *   1) يحفظها داخلياً (تظهر في جرس كل لوحة عبر Polling + صوت).
 *   2) يرسلها Push عبر FCM لأجهزة مستخدمي ذلك الدور.
 */
class NotificationService
{
    public function __construct(private readonly FirebaseService $firebase) {}

    /**
     * خريطة الحدث → [الدور المستهدف, العنوان, قالب الرسالة].
     * {patient} و{case} يُستبدلان باسم المريض ورقم الحالة.
     *
     * @var array<string, array{role: string, title: string, body: string}>
     */
    private const MAP = [
        WorkflowEvent::ExamApproved->value => [
            'role' => Role::SLUG_SPEC,
            'title' => '🔧 حالة جديدة للتوصيف الفني',
            'body' => 'المريض {patient} (حالة {case}) جاهز للتوصيف الفني بعد اعتماد الكشف.',
        ],
        WorkflowEvent::ExamSkipped->value => [
            'role' => Role::SLUG_SPEC,
            'title' => '🔧 حالة جديدة للتوصيف الفني',
            'body' => 'المريض {patient} (حالة {case}) محوّل مباشرةً للتوصيف (تم تخطّي الكشف).',
        ],
        WorkflowEvent::SpecSaved->value => [
            'role' => Role::SLUG_ADJUSTMENTS,
            'title' => '📏 حالة بانتظار المعدلات',
            'body' => 'المريض {patient} (حالة {case}) وصل لمكتب المعدلات لمراجعة وإضافة البنود.',
        ],
        WorkflowEvent::AdjustmentsCompleted->value => [
            'role' => Role::SLUG_COSTING,
            'title' => '🧮 حالة بانتظار التكاليف',
            'body' => 'المريض {patient} (حالة {case}) جاهز لمراجعة التكلفة وتأكيد عرض السعر.',
        ],
        WorkflowEvent::QuoteIssued->value => [
            'role' => Role::SLUG_OPERATIONS,
            'title' => '🎯 حالة بانتظار مكتب التشغيل',
            'body' => 'المريض {patient} (حالة {case}) بانتظار اعتماد مكتب التشغيل وإصدار أمر الصرف.',
        ],
        WorkflowEvent::SentToCashier->value => [
            'role' => Role::SLUG_CASHIER,
            'title' => '💵 مريض بانتظار الدفع في الخزنة',
            'body' => 'المريض {patient} (حالة {case}) صدر له عرض سعر نقدي — بانتظار تحصيل المبلغ في الخزنة.',
        ],
        WorkflowEvent::CashierPaid->value => [
            'role' => Role::SLUG_OPERATIONS,
            'title' => '💰 حالة مدفوعة بانتظار اعتماد التشغيل',
            'body' => 'المريض {patient} (حالة {case}) سدد المبلغ في الخزنة — بانتظار اعتماد مكتب التشغيل لإصدار أمر الشغل.',
        ],
        WorkflowEvent::OperationsApproved->value => [
            'role' => Role::SLUG_TECHNICAL,
            'title' => '📦 أمر صرف جديد للمخزن',
            'body' => 'المريض {patient} (حالة {case}) معتمد — جاهز للصرف بالباركود من المخزن.',
        ],
        WorkflowEvent::ReturnedToAdjustments->value => [
            'role' => Role::SLUG_ADJUSTMENTS,
            'title' => '↩️ حالة أُعيدت للمعدلات',
            'body' => 'المريض {patient} (حالة {case}) أُعيد من مكتب التشغيل لمراجعة المعدلات.',
        ],
        WorkflowEvent::ReturnedToTechnical->value => [
            'role' => Role::SLUG_SPEC,
            'title' => '↩️ حالة أُعيدت للتوصيف',
            'body' => 'المريض {patient} (حالة {case}) أُعيد لإعادة التوصيف الفني.',
        ],
        WorkflowEvent::BomDispensed->value => [
            'role' => Role::SLUG_WORKSHOP,
            'title' => '🏭 أمر جديد في ورشة التصنيع',
            'body' => 'تم صرف مواد المريض {patient} (حالة {case}) — الطلب جاهز للتصنيع في الورشة.',
        ],
        WorkflowEvent::BomFinished->value => [
            'role' => Role::SLUG_TECHNICAL,
            'title' => '✅ طرف جاهز للتسليم — المخزن',
            'body' => 'المريض {patient} (حالة {case}) أُتمِم تصنيعه في الورشة — جاهز للتسليم وإغلاق الطلب من المخزن.',
        ],
        WorkflowEvent::Delivered->value => [
            'role' => Role::SLUG_ADMIN,
            'title' => '📁 تم تسليم وإغلاق حالة',
            'body' => 'تم تسليم الطرف للمريض {patient} (حالة {case}) وإغلاق الحالة.',
        ],
    ];

    public function notifyEditRequestSubmitted(SpecEditRequest $request): AppNotification
    {
        $request->loadMissing('caseRecord.patient:id,name', 'requestedBy:id,name');
        $case = $request->caseRecord;
        $patient = $case?->patient?->name ?? 'غير معروف';
        $caseNo = $case?->case_no ?? ('#'.$request->case_id);
        $by = $request->requestedBy?->name ?? '—';

        $isAdjustments = $request->source === SpecEditRequestSource::Adjustments;

        return $this->push(
            roleSlug: Role::SLUG_ADMIN,
            title: $isAdjustments ? '✏️ طلب تعديل بنود المعدلات' : '✏️ طلب تعديل التوصيف',
            body: $isAdjustments
                ? "طلب {$by} تعديل بنود المعدلات للمريض {$patient} (حالة {$caseNo}) — بانتظار موافقة الإدارة."
                : "طلب {$by} تعديل توصيف المريض {$patient} (حالة {$caseNo}) — بانتظار موافقة الإدارة.",
            case: $case,
            event: 'spec_edit_requested',
            data: [
                'spec_edit_request_id' => (string) $request->id,
                'source' => $request->source->value,
                'url' => '/admin/spec-edit-requests',
            ],
        );
    }

    public function notifyEditRequestApproved(SpecEditRequest $request): AppNotification
    {
        $request->loadMissing('caseRecord.patient:id,name', 'requestedBy.role:id,slug');
        $case = $request->caseRecord;
        $patient = $case?->patient?->name ?? 'غير معروف';
        $caseNo = $case?->case_no ?? ('#'.$request->case_id);

        $isAdjustments = $request->source === SpecEditRequestSource::Adjustments;
        $departmentRole = $isAdjustments ? Role::SLUG_ADJUSTMENTS : Role::SLUG_SPEC;

        return $this->pushEditRequestOutcome(
            request: $request,
            departmentRole: $departmentRole,
            title: $isAdjustments ? '✅ تم اعتماد تعديل بنود المعدلات' : '✅ تم اعتماد تعديل التوصيف',
            body: $isAdjustments
                ? "وافقت الإدارة على تعديل بنود المعدلات للمريض {$patient} (حالة {$caseNo}) — التعديل مُطبَّق."
                : "وافقت الإدارة على تعديل توصيف المريض {$patient} (حالة {$caseNo}) — التعديل مُطبَّق.",
            event: 'spec_edit_approved',
            data: [
                'spec_edit_request_id' => (string) $request->id,
                'source' => $request->source->value,
                'url' => $isAdjustments ? '/adjustments/adjustments' : '/spec/spec',
            ],
        );
    }

    public function notifyEditRequestRejected(SpecEditRequest $request): AppNotification
    {
        $request->loadMissing('caseRecord.patient:id,name', 'requestedBy.role:id,slug');
        $case = $request->caseRecord;
        $patient = $case?->patient?->name ?? 'غير معروف';
        $caseNo = $case?->case_no ?? ('#'.$request->case_id);
        $reason = $request->rejectionReasonLabel();
        $notes = trim((string) $request->rejection_notes);
        $reasonPart = '';

        if ($reason || $notes !== '') {
            $reasonPart = ' السبب: '.($notes !== '' ? $notes : $reason);
        }

        $isAdjustments = $request->source === SpecEditRequestSource::Adjustments;
        $departmentRole = $isAdjustments ? Role::SLUG_ADJUSTMENTS : Role::SLUG_SPEC;

        return $this->pushEditRequestOutcome(
            request: $request,
            departmentRole: $departmentRole,
            title: $isAdjustments ? '❌ رُفض طلب تعديل المعدلات' : '❌ رُفض طلب تعديل التوصيف',
            body: "رفضت الإدارة طلب تعديل {$patient} (حالة {$caseNo}).{$reasonPart}",
            event: 'spec_edit_rejected',
            data: [
                'spec_edit_request_id' => (string) $request->id,
                'source' => $request->source->value,
                'rejection_reason' => $reason,
                'url' => $isAdjustments ? '/adjustments/adjustments' : '/spec/spec',
            ],
        );
    }

    public function notifySpecEditRequested(SpecEditRequest $request): AppNotification
    {
        return $this->notifyEditRequestSubmitted($request);
    }

    public function notifySpecEditApproved(SpecEditRequest $request): AppNotification
    {
        return $this->notifyEditRequestApproved($request);
    }

    public function notifySpecEditRejected(SpecEditRequest $request): AppNotification
    {
        return $this->notifyEditRequestRejected($request);
    }

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
        $caseNo = $case->case_no ?? ('#'.$case->id);

        $body = strtr($rule['body'], ['{patient}' => $patient, '{case}' => $caseNo]);

        return $this->push(
            roleSlug: $rule['role'],
            title: $rule['title'],
            body: $body,
            case: $case,
            event: $event,
        );
    }

    /**
     * يحفظ الإشعار داخلياً ويرسله Push لأجهزة الدور المستهدف.
     */
    public function push(string $roleSlug, string $title, string $body, ?CaseRecord $case = null, ?string $event = null, array $data = []): AppNotification
    {
        $notification = AppNotification::create([
            'role_slug' => $roleSlug,
            'case_id' => $case?->id,
            'event' => $event,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);

        $tokens = $this->tokensForRole($roleSlug);

        if ($tokens !== []) {
            $this->firebase->sendToTokens($tokens, $title, $body, array_merge([
                'notification_id' => (string) $notification->id,
                'role' => $roleSlug,
                'case_id' => (string) ($case?->id ?? ''),
                'case_no' => (string) ($case?->case_no ?? ''),
            ], array_map('strval', $data)));
        }

        return $notification;
    }

    /**
     * إشعار نتيجة طلب التعديل — للوحة القسم + دور مقدّم الطلب إن اختلف (مثلاً أدمن يعمل من لوحة التوصيف).
     */
    private function pushEditRequestOutcome(
        SpecEditRequest $request,
        string $departmentRole,
        string $title,
        string $body,
        string $event,
        array $data,
    ): AppNotification {
        $case = $request->caseRecord;

        $notification = $this->push(
            roleSlug: $departmentRole,
            title: $title,
            body: $body,
            case: $case,
            event: $event,
            data: $data,
        );

        $requesterRole = $request->requestedBy?->role?->slug;
        if ($requesterRole && $requesterRole !== $departmentRole) {
            $this->push(
                roleSlug: $requesterRole,
                title: $title,
                body: $body,
                case: $case,
                event: $event,
                data: $data,
            );
        }

        return $notification;
    }

    /**
     * عيادة → استقبال: انتهت قائمة انتظار الطبيب — يمكن استقبال مرضى جدد.
     */
    public function notifyReceptionClinicQueueEmpty(string $queueDate): AppNotification
    {
        return $this->push(
            roleSlug: Role::SLUG_RECEPTION,
            title: '🟢 العيادة متاحة لاستقبال مرضى جدد',
            body: 'انتهت قائمة انتظار الطبيب — يمكن تحويل مرضى جدد للعيادة.',
            case: null,
            event: 'doctor_clinic_queue_empty',
            data: [
                'queue_date' => $queueDate,
                'url' => '/reception/appointments',
            ],
        );
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
            title: '🩺 مريض جديد في قائمة الانتظار',
            body: "تم تحويل المريض {$patientName} ({$entity} — {$pathway}) من الاستقبال — جاهز للكشف.",
            case: null,
            event: 'patient_transferred_to_clinic',
            data: [
                'appointment_id' => (string) $appointment->id,
                'patient_id' => (string) ($appointment->patient_id ?? ''),
                'patient_name' => $patientName,
                'url' => '/doctor/queue',
            ],
        );
    }

    /**
     * تعليم كل إشعارات دور/لوحة كمقروءة — عند فتح صفحة الإشعارات أو زر «تعليم الكل».
     */
    public function markAllReadForRole(?string $roleSlug): int
    {
        if ($roleSlug === null || $roleSlug === '') {
            return 0;
        }

        return AppNotification::forRole($roleSlug)
            ->unread()
            ->update(['read_at' => now()]);
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
