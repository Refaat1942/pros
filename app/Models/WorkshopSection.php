<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopSection extends Model
{
    protected $fillable = [
        'name',
        'code',
        'sort',
        'active',
        'description',
    ];

    protected $casts = [
        'sort' => 'integer',
        'active' => 'boolean',
    ];

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workshop_section_user');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class, 'workshop_section_id');
    }
}
