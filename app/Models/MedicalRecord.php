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
    protected $fillable = [
        'patient_id',
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
}
