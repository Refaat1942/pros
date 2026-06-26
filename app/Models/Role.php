<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دور المستخدم — admin, doctor, technical, reception, store
 */
class Role extends Model
{
    public const SLUG_ADMIN       = 'admin';
    public const SLUG_RECEPTION   = 'reception';
    public const SLUG_DOCTOR      = 'doctor';
    public const SLUG_SPEC        = 'spec';
    public const SLUG_ADJUSTMENTS = 'adjustments';
    public const SLUG_COSTING     = 'costing';
    public const SLUG_OPERATIONS  = 'operations';
    public const SLUG_WORKSHOP    = 'workshop';
    public const SLUG_TECHNICAL   = 'technical';

    /** جميع الـ slugs الصحيحة المطابقة لبادئات المسارات */
    public const ALL_SLUGS = [
        self::SLUG_ADMIN,
        self::SLUG_RECEPTION,
        self::SLUG_DOCTOR,
        self::SLUG_SPEC,
        self::SLUG_ADJUSTMENTS,
        self::SLUG_COSTING,
        self::SLUG_OPERATIONS,
        self::SLUG_WORKSHOP,
        self::SLUG_TECHNICAL,
    ];

    protected $fillable = [
        'slug',
        'label_ar',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function isAdmin(): bool
    {
        return $this->slug === self::SLUG_ADMIN;
    }

    /**
     * هل يملك هذا الدور الصلاحية؟ الأدمن (السوبر أدمن) يملك كل شيء.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->permissions->contains('slug', $slug);
    }
}
