<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * مستخدم النظام — موظفو المركز (استقبال، طبيب، فني، مخزن، إدارة)
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'username',
        'password',
        'role_id',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** سوبر أدمن — صلاحية كاملة على النظام ومصفوفة الصلاحيات. */
    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === Role::SLUG_SUPER_ADMIN;
    }

    /** أدمن محدود أو سوبر أدمن — أي حساب يرى لوحة الإدارة. */
    public function isAdmin(): bool
    {
        return $this->role?->isAdminPanelRole() ?? false;
    }

    /** لوحة التوجيه بعد تسجيل الدخول (super_admin → admin). */
    public function dashboardSlug(): string
    {
        if ($this->isSuperAdmin()) {
            return Role::SLUG_ADMIN;
        }

        return $this->role?->slug ?? 'home';
    }

    /**
     * هل يملك المستخدم الصلاحية التفصيلية (عبر دوره)؟
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return (bool) $this->role?->loadMissing('permissions')->hasPermission($slug);
    }

    /**
     * هل يمكن للمستخدم فتح صفحة ضمن لوحة تحكم؟
     */
    public function canViewDashboardPage(string $dashboard, string $page): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $slug = Permission::viewSlug($dashboard, $page);

        return $this->role?->loadMissing('permissions')->permissions->contains('slug', $slug) ?? false;
    }

    /**
     * هل يمكن للمستخدم الدخول إلى لوحة تحكم (أي صفحة فيها على الأقل)؟
     */
    public function canAccessDashboard(string $dashboard): bool
    {
        if ($dashboard === 'home' || ! config("dashboards.{$dashboard}")) {
            return false;
        }

        if ($this->isSuperAdmin() && $dashboard === Role::SLUG_ADMIN) {
            return true;
        }

        if ($this->role?->slug === $dashboard) {
            return true;
        }

        foreach (array_keys(config("dashboards.{$dashboard}.pages", [])) as $page) {
            if ($this->canViewDashboardPage($dashboard, $page)) {
                return true;
            }
        }

        return false;
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'doctor_user_id');
    }

    public function approvedPricingRequests(): HasMany
    {
        return $this->hasMany(PricingRequest::class, 'approved_by_user_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'performed_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }
}
