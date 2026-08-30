<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_workshop_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('workshop_section_id')->constrained('workshop_sections')->cascadeOnDelete();
            $table->foreignId('assigned_technician_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(
                ['case_id', 'workshop_section_id', 'assigned_technician_id'],
                'case_workshop_assignments_unique'
            );
        });

        Schema::table('supply_request_lines', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('stock_movement_id');
        });

        if (Schema::hasTable('cases') && Schema::hasTable('workshop_sections')) {
            $rows = \Illuminate\Support\Facades\DB::table('cases')
                ->whereNotNull('workshop_section_id')
                ->whereNotNull('assigned_technician_id')
                ->select(['id', 'workshop_section_id', 'assigned_technician_id'])
                ->get();

            foreach ($rows as $row) {
                \Illuminate\Support\Facades\DB::table('case_workshop_assignments')->insertOrIgnore([
                    'case_id' => $row->id,
                    'workshop_section_id' => $row->workshop_section_id,
                    'assigned_technician_id' => $row->assigned_technician_id,
                    'sort' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('supply_request_lines', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });

        Schema::dropIfExists('case_workshop_assignments');
    }
};
