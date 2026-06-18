<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تجارب التركيب والمعدلات — clinic_fitting_trials
 */
class FittingTrial extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_TRIAL1 = 'trial1';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'case_id',
        'trial1_date',
        'trial2_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'trial1_date' => 'date',
        'trial2_date' => 'date',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }
}
