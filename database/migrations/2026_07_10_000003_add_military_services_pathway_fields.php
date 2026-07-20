<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('military_beneficiary_category', 30)->nullable()->after('military_weapon');
        });

        Schema::create('services_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services_approvals');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('military_beneficiary_category');
        });
    }
};
