<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أنماط التكاليف (طرف صناعي / صرف سريع) ومكوّناتها القابلة للتحكم من الإدارة.
     */
    public function up(): void
    {
        Schema::create('costing_modes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();      // prosthetic_limb | quick_dispense
            $table->string('label');
            $table->decimal('profit_rate', 8, 2)->default(0);
            $table->boolean('has_components')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('costing_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_mode_id')->constrained('costing_modes')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('rate', 8, 2)->default(0);   // نسبة مئوية من إجمالي المواد
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_components');
        Schema::dropIfExists('costing_modes');
    }
};
