<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * لكل مريض رقم قومي واحد — فهرس فريد يمنع ملفين لنفس الشخص (القيم الفارغة مسموحة).
 *
 * لو وُجدت أرقام مكررة سابقاً لا يُفشل النشر: تُسجَّل في اللوج لتُدمج يدوياً،
 * والتحقق في التسجيل والتصحيح يمنع أي تكرار جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('patients')->where('national_id', '')->update(['national_id' => null]);

        $duplicates = DB::table('patients')
            ->select('national_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('national_id')
            ->groupBy('national_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('total', 'national_id');

        if ($duplicates->isNotEmpty()) {
            Log::warning('patients.national_id unique index skipped — duplicate national IDs must be merged first', [
                'duplicates' => $duplicates->all(),
            ]);

            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->unique('national_id', 'patients_national_id_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('patients', 'patients_national_id_unique')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_national_id_unique');
        });
    }
};
