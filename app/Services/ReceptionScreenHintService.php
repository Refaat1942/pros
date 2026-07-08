<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;

/**
 * تلميحات/إشعارات سياقية لكل شاشة في لوحة الاستقبال.
 */
class ReceptionScreenHintService
{
    /**
     * @return array{page: string, title: string, message: string, count: ?int, link: ?string, link_label: ?string}
     */
    public function hint(string $page): array
    {
        return match ($page) {
            'appointments' => $this->appointments(),
            'quote' => $this->quotes(),
            'delivery' => $this->deliveries(),
            'patients' => $this->patients(),
            'statistics' => $this->statistics(),
            'selfservice' => $this->selfservice(),
            default => [
                'page' => $page,
                'title' => 'مرحباً',
                'message' => 'استخدم القائمة الجانبية للتنقل بين مهام الاستقبال.',
                'count' => null,
                'link' => null,
                'link_label' => null,
            ],
        };
    }

    /** @return array{page: string, title: string, message: string, count: ?int, link: ?string, link_label: ?string} */
    private function appointments(): array
    {
        return [
            'page' => 'appointments',
            'title' => '📅 جدولة المواعيد',
            'message' => 'سجّل حضور المرضى، أنشئ ملفاً جديداً، أو حوّل للطبيب. المريض العسكري يدخل مساراً منفصلاً بدون عرض سعر.',
            'count' => null,
            'link' => null,
            'link_label' => null,
        ];
    }

    /** @return array{page: string, title: string, message: string, count: ?int, link: ?string, link_label: ?string} */
    private function quotes(): array
    {
        $awaitingOcr = Quote::query()
            ->where('status', Quote::STATUS_ISSUED)
            ->whereHas('caseRecord', fn ($q) => $q->where('patient_type', Patient::TYPE_CIVILIAN))
            ->count();

        return [
            'page' => 'quote',
            'title' => '💰 عروض الأسعار',
            'message' => $awaitingOcr > 0
                ? "في {$awaitingOcr} عرض/عروض بانتظار خطاب الموافقة — ارفع الخطاب وامسحه، ثم تُحوَّل الحالة لمكتب التشغيل لإصدار أمر الشغل."
                : 'لا توجد عروض بانتظار خطاب موافقة حالياً. اطبع العرض للمريض عند الحاجة.',
            'count' => $awaitingOcr > 0 ? $awaitingOcr : null,
            'link' => route('reception.quote'),
            'link_label' => 'فتح عروض الأسعار',
        ];
    }

    /** @return array{page: string, title: string, message: string, count: ?int, link: ?string, link_label: ?string} */
    private function deliveries(): array
    {
        $ready = CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_READY_DELIVERY)
            ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_FINISHED))
            ->count();

        return [
            'page' => 'delivery',
            'title' => '📦 التسليمات',
            'message' => $ready > 0
                ? "في {$ready} حالة/حالات جاهزة للتسليم — امسح QR بطاقة المريض لإغلاق الحالة."
                : 'لا توجد تسليمات معلّقة — ستظهر هنا عند جاهزية الطرف من الورشة.',
            'count' => $ready > 0 ? $ready : null,
            'link' => route('reception.delivery'),
            'link_label' => 'فتح التسليمات',
        ];
    }

    /** @return array{page: string, title: string, message: string, count: ?int, link: ?string, link_label: ?string} */
    private function patients(): array
    {
        return [
            'page' => 'patients',
            'title' => '👤 سجل المرضى',
            'message' => 'ابحث عن ملف المريض، اطبع بطاقة QR، أو راجع مسار الحالة. خطاب الموافقة المرفوع يظهر في تفاصيل الحالة.',
            'count' => null,
            'link' => null,
            'link_label' => null,
        ];
    }

    /** @return array{page: string, title: string, message: string, count: ?int, link: ?string, link_label: ?string} */
    private function statistics(): array
    {
        return [
            'page' => 'statistics',
            'title' => '📊 إحصائيات الاستقبال',
            'message' => 'مؤشرات الزيارات والحالات — للمتابعة اليومية فقط.',
            'count' => null,
            'link' => null,
            'link_label' => null,
        ];
    }

    /** @return array{page: string, title: string, message: string, count: ?int, link: ?string, link_label: ?string} */
    private function selfservice(): array
    {
        return [
            'page' => 'selfservice',
            'title' => '📱 متابعة حالة الطلب',
            'message' => 'شاشة للمريض — امسح QR أو أدخل الرقم لمتابعة مرحلة الطلب.',
            'count' => null,
            'link' => null,
            'link_label' => null,
        ];
    }
}
