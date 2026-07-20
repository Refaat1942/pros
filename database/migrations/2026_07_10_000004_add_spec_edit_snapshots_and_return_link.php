<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spec_edit_requests', function (Blueprint $table) {
            $table->json('before_snapshot')->nullable()->after('proposed_tech_notes');
            $table->json('after_snapshot')->nullable()->after('before_snapshot');
        });

        Schema::table('return_notes', function (Blueprint $table) {
            $table->foreignId('spec_edit_request_id')->nullable()->after('case_id')
                ->constrained('spec_edit_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spec_edit_request_id');
        });

        Schema::table('spec_edit_requests', function (Blueprint $table) {
            $table->dropColumn(['before_snapshot', 'after_snapshot']);
        });
    }
};
