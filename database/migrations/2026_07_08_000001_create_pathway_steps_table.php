<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pathway_steps', function (Blueprint $table) {
            $table->id();
            $table->string('pathway', 20);
            $table->string('key', 64);
            $table->string('label');
            $table->unsignedSmallInteger('sort')->default(1);
            $table->json('stage_keys');
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['pathway', 'key']);
            $table->index(['pathway', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pathway_steps');
    }
};
