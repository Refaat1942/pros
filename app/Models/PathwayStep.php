<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * خطوة مرقّمة في مسار المريض (مدني / عسكري) — للعرض والمتابعة فقط.
 */
class PathwayStep extends Model
{
    public const PATHWAY_CIVILIAN = 'civilian';

    public const PATHWAY_MILITARY = 'military';

    protected $fillable = [
        'pathway',
        'key',
        'label',
        'sort',
        'stage_keys',
        'active',
        'description',
    ];

    protected $casts = [
        'sort' => 'integer',
        'stage_keys' => 'array',
        'active' => 'boolean',
    ];
}
