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

    public static function formatWaitDuration(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): string
    {
        if ($to->lessThan($from)) {
            return '—';
        }

        $diff  = $from->diff($to);
        $parts = [];

        if ($diff->d > 0) {
            $parts[] = $diff->d.' '.($diff->d === 1 ? 'يوم' : 'أيام');
        }

        if ($diff->h > 0) {
            $parts[] = $diff->h.' '.($diff->h === 1 ? 'ساعة' : 'ساعات');
        }

        if ($diff->i > 0) {
            $parts[] = $diff->i.' '.($diff->i === 1 ? 'دقيقة' : 'دقائق');
        }

        if ($parts === []) {
            return 'أقل من دقيقة';
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
        if ($this->isMilitary()) {
            return $this->relationLoaded('patient') && $this->patient
                ? $this->patient->displayEntity()
                : Patient::MILITARY_SOVEREIGN_ENTITY;
        }

        return $this->company_name
            ?? ($this->relationLoaded('patient') ? $this->patient?->displayEntity() : null)
            ?? '—';
    }
}
