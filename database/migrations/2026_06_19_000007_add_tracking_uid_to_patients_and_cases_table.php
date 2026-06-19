<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cases', 'tracking_uid')) {
            try {
                Schema::table('cases', function (Blueprint $table) {
                    $table->dropUnique(['tracking_uid']);
                });
            } catch (\Throwable) {
                // partial run may have already dropped the index
            }
        }

        if (! Schema::hasColumn('patients', 'tracking_uid')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->string('tracking_uid', 64)->nullable()->unique()->after('patient_qr');
            });
        }

        if (! Schema::hasColumn('cases', 'tracking_uid')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->string('tracking_uid', 64)->nullable()->index()->after('order_ref');
            });
        }

        DB::table('patients')->whereNull('tracking_uid')->orderBy('id')->each(function ($patient) {
            DB::table('patients')->where('id', $patient->id)->update([
                'tracking_uid' => $this->uniqueTrackingUid(),
            ]);
        });

        DB::table('cases')->whereNull('tracking_uid')->orderBy('id')->each(function ($case) {
            $patientUid = DB::table('patients')->where('id', $case->patient_id)->value('tracking_uid');
            if ($patientUid) {
                DB::table('cases')->where('id', $case->id)->update(['tracking_uid' => $patientUid]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('tracking_uid');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('tracking_uid');
        });
    }

    private function uniqueTrackingUid(): string
    {
        do {
            $uid = 'case-' . Str::lower(Str::random(8));
        } while (
            DB::table('patients')->where('tracking_uid', $uid)->exists()
            || DB::table('cases')->where('tracking_uid', $uid)->exists()
        );

        return $uid;
    }
};
