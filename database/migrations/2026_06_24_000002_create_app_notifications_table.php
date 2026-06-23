<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إشعارات داخل التطبيق — مستهدفة بالدور (لوحة) لتنتقل بين اللوحات.
     * تُعرض في جرس الإشعارات داخل كل لوحة (Polling + صوت) وتُرسل أيضاً عبر FCM.
     */
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('role_slug');                 // الدور/اللوحة المستهدفة
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('event')->nullable();         // حدث محرك التدفق المسبّب
            $table->string('title');
            $table->string('body', 1000);
            $table->json('data')->nullable();            // حمولة إضافية (case_no, url, ...)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['role_slug', 'read_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
