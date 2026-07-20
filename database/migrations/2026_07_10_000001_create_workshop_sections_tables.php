<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->unsignedSmallInteger('sort')->default(10);
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('workshop_section_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_section_id')->constrained('workshop_sections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['workshop_section_id', 'user_id']);
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('workshop_section_id')->nullable()->after('work_order_no')
                ->constrained('workshop_sections')->nullOnDelete();
            $table->foreignId('assigned_technician_id')->nullable()->after('workshop_section_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('workshop_assigned_at')->nullable()->after('assigned_technician_id');
            $table->unsignedTinyInteger('workshop_progress_pct')->default(0)->after('workshop_assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workshop_section_id');
            $table->dropConstrainedForeignId('assigned_technician_id');
            $table->dropColumn(['workshop_assigned_at', 'workshop_progress_pct']);
        });

        Schema::dropIfExists('workshop_section_user');
        Schema::dropIfExists('workshop_sections');
    }
};
