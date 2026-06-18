<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دور المستخدم — admin, doctor, technical, reception, store
 */
class Role extends Model
{
    public const SLUG_ADMIN = 'admin';
    public const SLUG_DOCTOR = 'doctor';
    public const SLUG_TECHNICAL = 'technical';
    public const SLUG_RECEPTION = 'reception';
    public const SLUG_STORE = 'store';

    protected $fillable = [
        'slug',
        'label_ar',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
