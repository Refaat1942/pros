<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_stage_policies', function (Blueprint $table) {
            $table->id();
            $table->string('pathway', 20);
            $table->string('stage_key', 64);
            $table->boolean('required')->default(true);
            $table->boolean('auto_skip')->default(false);
            $table->json('skip_roles')->nullable();
            $table->unsignedSmallInteger('sort')->default(1);
            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['pathway', 'stage_key']);
            $table->index(['pathway', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stage_policies');
    }
};
