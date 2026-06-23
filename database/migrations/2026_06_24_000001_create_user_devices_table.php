<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أجهزة المستخدمين — تُسجَّل عند تسجيل الدخول (device_id + device_type)
     * وتُستخدم لإرسال إشعارات FCM المستهدفة لكل لوحة/دور.
     */
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id', 512);          // FCM registration token
            $table->string('device_type')->nullable(); // android | ios | web
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('device_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
