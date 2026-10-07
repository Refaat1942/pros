<?php

use App\Services\PermissionCatalogService;
use Illuminate\Database\Migrations\Migration;

/**
 * صفحات أُضيفت بعد آخر مزامنة (طلب التوريد، استلام الوارد، إضافة صنف، مركز الوثائق، وحدات القياس)
 * لم يكن لها صف صلاحية — تُضاف وتُمنح لأدوارها الافتراضية دون المساس بما عدّله المدير.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionCatalogService::class)->syncNewPermissions();
    }

    public function down(): void
    {
        // لا سحب — المصفوفة تُدار من شاشة الصلاحيات.
    }
};
