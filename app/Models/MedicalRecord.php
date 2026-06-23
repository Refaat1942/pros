<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * التقرير الطبي المعتمد — doctor-dashboard medicalRecords[]
 */
class MedicalRecord extends Model
{
    public const STATUS_DRAFT    = 'مسودة';
    public const STATUS_APPROVED = 'معتمد';

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'case_id',
        'patient_name',
        'national_id',
        'company_name',
        'patient_type',
        'diagnosis',
        'prescription',
        'doctor_name',
        'doctor_user_id',
        'record_date',
        'status',
        'locked',
    ];

    protected $casts = [
        'record_date' => 'date',
        'locked' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MedicalRecordItem::class);
    }

    public function isMilitary(): bool
    {
        return $this->patient_type === Patient::TYPE_MILITARY;
    }

    /** الجهة المعروضة — للعسكري القوات المسلحة حتى لو company_name فارغ. */
    public function displayEntity(): string
    {
        if ($this->isMilitary()) {
            return Patient::MILITARY_SOVEREIGN_ENTITY;
        }

        return $this->company_name ?? '—';
    }
}
