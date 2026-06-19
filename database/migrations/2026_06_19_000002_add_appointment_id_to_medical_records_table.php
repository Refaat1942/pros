<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط التقرير الطبي بموعد الاستقبال — لتتبع الموعد عند الاعتماد.
     */
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('appointments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
        });
    }
};
