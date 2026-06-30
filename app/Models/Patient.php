<?php

namespace App\Models;

use App\Support\PatientEntityPresenter;
use App\Support\ClinicTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// MilitaryRank is resolved at runtime — imported for IDE hints

/**
 * ملف المريض — EMR + بطاقة QR
 */
class Patient extends Model
{
    public const TYPE_CIVILIAN = 'civilian';
    public const TYPE_MILITARY = 'military';

    /** الجهة السيادية الافتراضية لكل مريض عسكري — لا يُدخلها الاستقبال. */
    public const MILITARY_SOVEREIGN_ENTITY = 'القوات المسلحة';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'patient_code',
        'patient_qr',
        'tracking_uid',
        'name',
        'phone',
        'national_id',
        'patient_type',
        'military_rank_id',
        'rank',               // نص مشتق من military_rank.name — للعرض السريع
        'sovereign_entity',
        'contract_company_id',
        'company_name',
        'registered_at',
        'last_visit_at',
        'archived_at',
        'status',
    ];

    protected $casts = [
        'registered_at' => 'date',
        'last_visit_at' => 'date',
        'archived_at' => 'datetime',
    ];

    public function contractCompany(): BelongsTo
    {
        return $this->belongsTo(ContractCompany::class);
    }

    public function militaryRank(): BelongsTo
    {
        return $this->belongsTo(MilitaryRank::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function isMilitary(): bool
    {
        return $this->patient_type === self::TYPE_MILITARY;
    }

    /** مدني بدون جهة تعاقد — فوترة نقدية على حساب المريض. */
    public function isCashCivilian(): bool
    {
        return ! $this->isMilitary() && ! $this->contract_company_id;
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    public function entityPresentation(): array
    {
        return PatientEntityPresenter::forPatient($this);
    }

    /** الجهة المعروضة في الواجهات — للعسكري دائماً القوات المسلحة، للمدني النقدي أو اسم الجهة. */
    public function displayEntity(): string
    {
        return $this->entityPresentation()['label'];
    }

    /** رقم الدور ليوم العمل الحالي (يبدأ من 1 الساعة 01:00). */
    public function clinicDayQueueNumber(?string $clinicDay = null): ?int
    {
        $clinicDay ??= ClinicTime::clinicDayDateString();

        return $this->appointments()
            ->whereDate('clinic_day', $clinicDay)
            ->orderByDesc('id')
            ->value('queue_number');
    }

    protected static function booted(): void
    {
        static::saving(function (Patient $patient) {
            if ($patient->patient_type !== self::TYPE_MILITARY) {
                return;
            }

            $patient->sovereign_entity = $patient->sovereign_entity ?: self::MILITARY_SOVEREIGN_ENTITY;
            $patient->contract_company_id = null;
            $patient->company_name = null;
        });
    }
}
