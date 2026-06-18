<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل الرقابة الحصين — auditLogs[] في admin-dashboard.js (Append-Only)
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name'); // هوية المستخدم وقت العملية
            $table->string('action'); // إنشاء | تحديث | صرف مخزن | عرض...
            $table->text('description'); // وصف العملية
            $table->string('tag')->nullable(); // patients | medical | inventory | finance...
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->json('payload_before')->nullable(); // لقطة قبل التعديل
            $table->json('payload_after')->nullable(); // لقطة بعد التعديل
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index('logged_at');
            $table->index('tag');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
