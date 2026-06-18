<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ملف المريض — EMR + بطاقة QR
 */
class Patient extends Model
{
    public const TYPE_CIVILIAN = 'civilian';
    public const TYPE_MILITARY = 'military';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'patient_code',
        'patient_qr',
        'name',
        'phone',
        'national_id',
        'patient_type',
        'rank',
        'sovereign_entity',
        'contract_company_id',
        'company_name',
        'registered_at',
        'last_visit_at',
        'status',
    ];

    protected $casts = [
        'registered_at' => 'date',
        'last_visit_at' => 'date',
    ];

    public function contractCompany(): BelongsTo
    {
        return $this->belongsTo(ContractCompany::class);
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
}
