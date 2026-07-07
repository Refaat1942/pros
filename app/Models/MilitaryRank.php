<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * رتبة عسكرية — يديرها الأدمن، يختار منها الاستقبال عند تسجيل مريض عسكري.
 */
class MilitaryRank extends Model
{
    protected $fillable = [
        'name',
        'rank_code',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
