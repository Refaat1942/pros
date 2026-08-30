<?php

use App\Models\Role;
use App\Services\CatalogListVisibilityService;
use App\Services\PermissionCatalogService;
use Illuminate\Database\Migrations\Migration;

/**
 * ضبط الصلاحيات والمسميات وقوائم الأصناف — توحيد offline/online.
 */
return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            Role::SLUG_RECEPTION => 'الاستقبال',
            Role::SLUG_DOCTOR => 'الطبيب',
            Role::SLUG_SPEC => 'التوصيف',
            Role::SLUG_ADJUSTMENTS => 'المعدلات',
            Role::SLUG_COSTING => 'الاعتماد',
            Role::SLUG_OPERATIONS => 'مكتب التشغيل',
            Role::SLUG_CASHIER => 'الخزنة',
            Role::SLUG_WORKSHOP => 'قسم الإنتاج',
            Role::SLUG_TECHNICAL => 'المخزن',
        ];

        foreach ($labels as $slug => $label) {
            Role::query()->where('slug', $slug)->update(['label_ar' => $label]);
        }

        app(PermissionCatalogService::class)->syncToDatabase();
        app(PermissionCatalogService::class)->seedRoleDefaults(fullSync: true);
        app(CatalogListVisibilityService::class)->seedConfigDefaults();
    }

    public function down(): void
    {
        // لا rollback — إعدادات تشغيلية
    }
};
