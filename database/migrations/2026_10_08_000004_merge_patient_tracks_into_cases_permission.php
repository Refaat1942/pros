<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «مسار المرضى» أصبح تبويباً داخل «متابعة المرضى» — كل دور كان يرى مسار المرضى يرى الصفحة المدمجة.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tracksId = DB::table('permissions')->where('slug', 'admin.patient-tracks.view')->value('id');
        $casesId = DB::table('permissions')->where('slug', 'admin.cases.view')->value('id');

        if (! $tracksId || ! $casesId) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $tracksId)->pluck('role_id');
        $alreadyHave = DB::table('role_permission')->where('permission_id', $casesId)->pluck('role_id')->all();

        foreach ($roleIds->diff($alreadyHave) as $roleId) {
            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $casesId]);
        }
    }

    public function down(): void
    {
        // منح الصلاحية لا يُسحب تلقائياً.
    }
};
