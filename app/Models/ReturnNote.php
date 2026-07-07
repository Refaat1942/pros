<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * إذن ارتجاع داخلي — clinic_inventory_returns
 */
class ReturnNote extends Model
{
    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'return_no',
        'bom_id',
        'case_id',
        'order_ref',
        'work_order_no',
        'patient_name',
        'status',
        'created_by',
        'created_by_user_id',
        'authorized_at',
        'completed_at',
        'audit_trail',
    ];

    protected $casts = [
        'authorized_at' => 'datetime',
        'completed_at' => 'datetime',
        'audit_trail' => 'array',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReturnNoteLine::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
