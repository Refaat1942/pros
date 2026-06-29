<?php

namespace App\Models;

use App\Support\ClinicTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * موعد المريض — reception-dashboard appointments[]
 */
class Appointment extends Model
{
    public const VISIT_EXAM = 'exam';
    public const VISIT_FOLLOWUP = 'followup';
    public const VISIT_FITTING = 'fitting';
    public const VISIT_DELIVERY = 'delivery';
    public const VISIT_REVIEW = 'review';

    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_CLINIC = 'in_clinic';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'patient_id',
        'appointment_date',
        'appointment_time',
        'visit_type_id',
        'visit_type',
        'patient_name',
        'phone',
        'company_name',
        'patient_type',
        'status',
        'status_label',
        'transferred_to_clinic',
        'transferred_to_clinic_at',
    ];

    protected $casts = [
        'appointment_date'          => 'date',
        'transferred_to_clinic'     => 'boolean',
        'transferred_to_clinic_at'  => 'datetime',
    ];

    /**
     * وقت تحويل المريض من الاستقبال — مع fallback للسجلات القديمة.
     */
    public function transferredAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->transferred_to_clinic_at) {
            return $this->transferred_to_clinic_at;
        }

        return $this->transferred_to_clinic ? $this->updated_at : null;
    }

    /** وقت التحويل للعرض — توقيت المركز (Africa/Cairo). */
    public function transferredAtFormatted(): string
    {
        return ClinicTime::format($this->transferredAt());
    }

    /**
     * مدة الانتظار من إنشاء ملف المريض حتى تحويله للعيادة (استقبال).
     */
    public function receptionWaitLabel(): string
    {
        $patient = $this->relationLoaded('patient') ? $this->patient : null;
        $end     = $this->transferredAt();

        if (! $patient?->created_at || ! $end) {
            return '—';
        }

        return self::formatWaitDuration($patient->created_at, $end);
    }

    /**
     * مدة الانتظار في العيادة — من التحويل حتى الآن (قائمة الطبيب).
     */
    public function clinicWaitLabel(?\Carbon\CarbonInterface $until = null): string
    {
        $start = $this->transferredAt();

        if (! $start) {
            return '—';
        }

        return self::formatWaitDuration($start, $until ?? now());
    }

    /** لحظة تسجيل الموعد في الاستقبال — وقت إنشاء الموعد وليس ملف المريض الأول. */
    public function registrationMoment(): ?\Carbon\CarbonInterface
    {
        if ($this->created_at) {
            return $this->created_at;
        }

        $patient = $this->relationLoaded('patient') ? $this->patient : null;

        return $patient?->created_at;
    }

    /** تاريخ ووقت الإضافة — توقيت المركز. */
    public function registeredAtFormatted(): string
    {
        return ClinicTime::format($this->registrationMoment(), 'd/m/Y H:i');
    }

    /**
     * مدة انتظار المريض في الاستقبال — من التسجيل حتى التحويل أو الآن.
     */
    public function receptionDeskWaitLabel(?\Carbon\CarbonInterface $until = null): string
    {
        $start = $this->registrationMoment();

        if (! $start) {
            return '—';
        }

        $end = ($this->transferred_to_clinic && $this->transferredAt())
            ? $this->transferredAt()
            : ($until ?? now());

        return self::formatWaitDuration($start, $end);
    }

    public static function formatWaitDuration(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): string
    {
        if ($to->lessThan($from)) {
            return '—';
        }

        $totalSeconds = (int) $from->diffInSeconds($to);

        if ($totalSeconds < 60) {
            return 'أقل من دقيقة';
        }

        $totalMinutes = intdiv($totalSeconds, 60);
        $days         = intdiv($totalMinutes, 60 * 24);
        $remMinutes   = $totalMinutes % (60 * 24);
        $hours        = intdiv($remMinutes, 60);
        $minutes      = $remMinutes % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' '.($days === 1 ? 'يوم' : 'أيام');
        }

        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'ساعة' : 'ساعات');
        }

        if ($minutes > 0) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'دقيقة' : 'دقائق');
        }

        if ($parts === []) {
            return self::arabicDigits('1 دقيقة');
        }

        return self::arabicDigits(implode(' ', array_slice($parts, 0, 2)));
    }

    /** تحويل الأرقام اللاتينية إلى أرقام عربية للعرض في الواجهة. */
    private static function arabicDigits(string $text): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            $text,
        );
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitTypeRecord(): BelongsTo
    {
        return $this->belongsTo(VisitType::class, 'visit_type_id');
    }

    public function isMilitary(): bool
    {
        return $this->patient_type === Patient::TYPE_MILITARY;
    }

    /** اسم نوع الزيارة للعرض — من جدول visit_types وليس المعرّف. */
    public function displayVisitType(): string
    {
        if ($this->relationLoaded('visitTypeRecord') && $this->visitTypeRecord) {
            return $this->visitTypeRecord->name;
        }

        if ($this->visit_type_id) {
            return VisitType::query()->whereKey($this->visit_type_id)->value('name') ?? '—';
        }

        if ($this->visit_type !== null && $this->visit_type !== '' && ctype_digit((string) $this->visit_type)) {
            return VisitType::query()->whereKey((int) $this->visit_type)->value('name') ?? '—';
        }

        return match ($this->visit_type) {
            self::VISIT_EXAM      => 'كشف',
            self::VISIT_FOLLOWUP  => 'متابعة',
            self::VISIT_FITTING   => 'تجربة',
            self::VISIT_DELIVERY  => 'تسليم',
            self::VISIT_REVIEW    => 'مراجعة',
            default               => $this->visit_type ?: '—',
        };
    }

    /** الجهة المعروضة في قائمة الانتظار والتقارير. */
    public function displayEntity(): string
    {
        return $this->entityPresentation()['label'];
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    public function entityPresentation(): array
    {
        if ($this->relationLoaded('patient') && $this->patient) {
            return $this->patient->entityPresentation();
        }

        return \App\Support\PatientEntityPresenter::fromParts(
            $this->patient_type,
            null,
            $this->company_name,
            null,
        );
    }
}
