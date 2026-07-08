<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * فترة محاسبية — تُستخدم لتقارير رصيد أول/آخر المدة والربحية.
 */
class AccountingPeriod extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'label',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function openingOverrides(): HasMany
    {
        return $this->hasMany(PeriodOpeningOverride::class);
    }

    /** @return array<string, float> */
    public function openingOverrideMap(): array
    {
        return $this->openingOverrides
            ->mapWithKeys(fn (PeriodOpeningOverride $o) => [$o->domain => (float) $o->opening_amount])
            ->all();
    }
}
