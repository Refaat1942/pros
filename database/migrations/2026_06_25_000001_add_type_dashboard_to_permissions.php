<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'type')) {
                $table->string('type', 16)->default('action')->after('group');
            }
            if (! Schema::hasColumn('permissions', 'dashboard')) {
                $table->string('dashboard', 32)->nullable()->after('type');
            }
            if (! Schema::hasColumn('permissions', 'page')) {
                $table->string('page', 64)->nullable()->after('dashboard');
            }
        });

        if (class_exists(\App\Services\PermissionCatalogService::class)) {
            app(\App\Services\PermissionCatalogService::class)->seedRoleDefaults();
        }
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['type', 'dashboard', 'page']);
        });
    }
};
