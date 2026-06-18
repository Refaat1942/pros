<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تجارب التركيب والمعدلات — clinic_fitting_trials في technical-dashboard.js
     */
    public function up(): void
    {
        Schema::create('fitting_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->date('trial1_date')->nullable(); // التجربة الأولى
            $table->date('trial2_date')->nullable(); // التجربة الثانية
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending | trial1 | completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fitting_trials');
    }
};
