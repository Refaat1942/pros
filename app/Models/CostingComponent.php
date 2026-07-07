<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند مكوّن ضمن نمط تكاليف — تسمية + نسبة مئوية من إجمالي المواد.
 */
class CostingComponent extends Model
{
    protected $fillable = [
        'costing_mode_id',
        'label',
        'rate',
        'sort',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'sort' => 'integer',
    ];

    public function mode(): BelongsTo
    {
        return $this->belongsTo(CostingMode::class, 'costing_mode_id');
    }
}
