<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سياسة مرحلة في مسار العمل — اختيارية / إلزامية / تخطي تلقائي.
 */
class WorkflowStagePolicy extends Model
{
    public const PATHWAY_CIVILIAN = 'civilian';

    public const PATHWAY_MILITARY = 'military';

    protected $fillable = [
        'pathway',
        'stage_key',
        'required',
        'auto_skip',
        'skip_roles',
        'sort',
        'label',
        'description',
    ];

    protected $casts = [
        'required' => 'boolean',
        'auto_skip' => 'boolean',
        'skip_roles' => 'array',
        'sort' => 'integer',
    ];
}
