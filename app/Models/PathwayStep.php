<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * خطوة في مصمم مسار المريض — عرض + تدفق + القسم المسؤول.
 */
class PathwayStep extends Model
{
    public const PATHWAY_CIVILIAN = 'civilian';

    public const PATHWAY_MILITARY = 'military';

    /** مدني مرتبط بجهة تعاقد (تأمين / شركة) — مسار عرض السعر وخطاب الموافقة. */
    public const PATHWAY_ENTITY = 'entity';

    /** مسار عسكري يحتاج تصديق إدارة الخدمات (ضباط / مدنيين / عائلات). */
    public const PATHWAY_MILITARY_SERVICES = 'military_services';

    protected $fillable = [
        'pathway',
        'key',
        'label',
        'sort',
        'stage_keys',
        'active',
        'description',
        'owner_department',
        'action_summary',
        'on_complete',
        'next_step_key',
        'required',
        'auto_skip',
        'skip_roles',
        'handlers',
    ];

    protected $casts = [
        'sort' => 'integer',
        'stage_keys' => 'array',
        'active' => 'boolean',
        'required' => 'boolean',
        'auto_skip' => 'boolean',
        'skip_roles' => 'array',
        'handlers' => 'array',
    ];
}
