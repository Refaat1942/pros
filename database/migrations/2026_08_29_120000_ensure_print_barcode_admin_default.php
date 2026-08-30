<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * يضمن صلاحية طباعة الباركود لدور admin بعد إعادة مزامنة الصلاحيات (d1f35f6).
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->first();
        $permission = Permission::query()->where('slug', 'print-barcode')->first();

        if ($adminRole === null || $permission === null) {
            return;
        }

        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function down(): void
    {
        // لا rollback — إعداد تشغيلي
    }
};
