<?php

use App\Services\PermissionCatalogService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** إضافة صلاحيات تحصيل المديونيات إلى الكتالوج دون إسنادها تلقائياً للأدوار. */
    public function up(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
    }

    public function down(): void
    {
        // لا حذف — الكتالوج يُدار من config/permissions.php
    }
};
