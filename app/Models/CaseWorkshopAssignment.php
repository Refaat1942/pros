<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseWorkshopAssignment extends Model
{
    protected $fillable = [
        'case_id',
        'workshop_section_id',
        'assigned_technician_id',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function workshopSection(): BelongsTo
    {
        return $this->belongsTo(WorkshopSection::class, 'workshop_section_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }
}
