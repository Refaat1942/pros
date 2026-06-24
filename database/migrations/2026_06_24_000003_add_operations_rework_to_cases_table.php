<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->text('rework_reason')->nullable()->after('credit_note_amount');
            $table->string('rework_target', 32)->nullable()->after('rework_reason');
            $table->timestamp('rework_returned_at')->nullable()->after('rework_target');
            $table->string('rework_returned_by')->nullable()->after('rework_returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn([
                'rework_reason',
                'rework_target',
                'rework_returned_at',
                'rework_returned_by',
            ]);
        });
    }
};
