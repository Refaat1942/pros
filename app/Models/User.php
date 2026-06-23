<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'email',
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
        'email_verified_at' => 'datetime',
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

    public function isAdmin(): bool
    {
        return $this->role?->slug === Role::SLUG_ADMIN;
    }

    /**
     * هل يملك المستخدم الصلاحية التفصيلية (عبر دوره)؟
     */
    public function hasPermission(string $slug): bool
    {
        return (bool) $this->role?->loadMissing('permissions')->hasPermission($slug);
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
