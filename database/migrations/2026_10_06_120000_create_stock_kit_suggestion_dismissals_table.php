<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اقتراحات التجميع التلقائي التي رفضها المستخدم («لا») — حتى لا تُقترح مرة أخرى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_kit_suggestion_dismissals', function (Blueprint $table) {
            $table->id();
            // kits = أطقم الإدارة، adjustments = مجموعات مكتب المعدلات
            $table->string('scope', 20)->default('kits');
            $table->string('signature', 64);
            $table->json('item_codes');
            $table->unsignedInteger('case_count')->default(0);
            $table->foreignId('dismissed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope', 'signature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_kit_suggestion_dismissals');
    }
};
