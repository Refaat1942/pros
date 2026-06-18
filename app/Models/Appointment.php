<?php

namespace App\Models;

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
        'visit_type',
        'patient_name',
        'phone',
        'company_name',
        'patient_type',
        'status',
        'status_label',
        'transferred_to_clinic',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'transferred_to_clinic' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
