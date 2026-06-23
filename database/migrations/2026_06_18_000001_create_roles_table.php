<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أدوار المستخدمين — مستخرجة من employees[] في admin-dashboard.js
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // admin, doctor, technical, reception, store
            $table->string('label_ar'); // التسمية العربية للدور
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });

        // ── مصفوفة الصلاحيات التفصيلية (Granular Permissions) ──────────────────
        // صلاحيات على مستوى الميزة/الزر (رؤية التكاليف، الاعتماد، الطباعة...).
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // view-costs, approve-pricing, print-barcode...
            $table->string('label_ar'); // التسمية العربية
            $table->string('group')->default('general'); // تجميع منطقي للعرض
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });

        Schema::dropIfExists('roles');
    }
};
