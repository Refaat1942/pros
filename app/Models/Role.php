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
    public const SLUG_SUPER_ADMIN = 'super_admin';

    public const SLUG_ADMIN = 'admin';

    public const SLUG_RECEPTION = 'reception';

    public const SLUG_DOCTOR = 'doctor';

    public const SLUG_SPEC = 'spec';

    public const SLUG_ADJUSTMENTS = 'adjustments';

    public const SLUG_COSTING = 'costing';

    public const SLUG_OPERATIONS = 'operations';

    public const SLUG_CASHIER = 'cashier';

    public const SLUG_WORKSHOP = 'workshop';

    public const SLUG_TECHNICAL = 'technical';

    /** جميع الـ slugs الصحيحة المطابقة لبادئات المسارات */
    public const ALL_SLUGS = [
        self::SLUG_SUPER_ADMIN,
        self::SLUG_ADMIN,
        self::SLUG_RECEPTION,
        self::SLUG_DOCTOR,
        self::SLUG_SPEC,
        self::SLUG_ADJUSTMENTS,
        self::SLUG_COSTING,
        self::SLUG_OPERATIONS,
        self::SLUG_CASHIER,
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

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SLUG_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->slug === self::SLUG_ADMIN;
    }

    /** أدوار لوحة الإدارة — سوبر أدمن (كامل) أو أدمن محدود. */
    public function isAdminPanelRole(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin();
    }

    /**
     * هل يملك هذا الدور الصلاحية؟ (تُدار من مصفوفة الصلاحيات لكل دور).
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }
}
