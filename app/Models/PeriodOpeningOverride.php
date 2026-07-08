<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رصيد افتتاحي يدوي لمجال مالي محدد ضمن فترة محاسبية.
 */
class PeriodOpeningOverride extends Model
{
    public const DOMAIN_CASH = 'cash';

    public const DOMAIN_CIVILIAN = 'civilian';

    public const DOMAIN_MILITARY = 'military';

    public const DOMAIN_INVENTORY = 'inventory';

    protected $fillable = [
        'accounting_period_id',
        'domain',
        'opening_amount',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
    ];

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }
}
