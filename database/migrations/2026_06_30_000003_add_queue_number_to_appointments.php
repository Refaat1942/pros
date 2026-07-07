<?php

use App\Support\ClinicTime;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedSmallInteger('queue_number')->nullable()->after('patient_id');
            $table->date('clinic_day')->nullable()->after('queue_number');
            $table->index(['clinic_day', 'queue_number']);
        });

        $counters = [];

        DB::table('appointments')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lazy()
            ->each(function ($row) use (&$counters) {
                $created = Carbon::parse($row->created_at)->timezone(ClinicTime::zone());
                $clinicDay = $created->hour < 1
                    ? $created->copy()->subDay()->toDateString()
                    : $created->toDateString();

                $counters[$clinicDay] = ($counters[$clinicDay] ?? 0) + 1;

                DB::table('appointments')->where('id', $row->id)->update([
                    'clinic_day' => $clinicDay,
                    'queue_number' => $counters[$clinicDay],
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['clinic_day', 'queue_number']);
            $table->dropColumn(['queue_number', 'clinic_day']);
        });
    }
};
