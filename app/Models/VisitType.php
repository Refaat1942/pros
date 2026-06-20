<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نوع الزيارة — يديره الأدمن، يختار منه الاستقبال عند تسجيل المريض.
 */
class VisitType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
