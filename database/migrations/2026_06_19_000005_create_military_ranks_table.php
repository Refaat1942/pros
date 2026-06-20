<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('military_ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // نقيب / رائد / عقيد ...
            $table->string('rank_code', 30)->nullable()->unique();       // CAPT / MAJ / COL ...
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
           
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('military_ranks');
    }
};
