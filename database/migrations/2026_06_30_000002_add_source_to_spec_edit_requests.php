<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spec_edit_requests', function (Blueprint $table) {
            $table->string('source', 20)->default('spec')->after('case_id');
            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('spec_edit_requests', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropColumn('source');
        });
    }
};
