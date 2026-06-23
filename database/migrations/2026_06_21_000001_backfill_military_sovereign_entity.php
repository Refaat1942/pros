<?php

use App\Models\CaseRecord;
use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $entity = Patient::MILITARY_SOVEREIGN_ENTITY;

        DB::table('patients')
            ->where('patient_type', Patient::TYPE_MILITARY)
            ->where(function ($q) {
                $q->whereNull('sovereign_entity')->orWhere('sovereign_entity', '');
            })
            ->update(['sovereign_entity' => $entity]);

        DB::table('cases')
            ->where('patient_type', Patient::TYPE_MILITARY)
            ->where(function ($q) {
                $q->whereNull('sovereign_entity')->orWhere('sovereign_entity', '');
            })
            ->update(['sovereign_entity' => $entity]);
    }

    public function down(): void
    {
        // لا تراجع — البيانات كانت ناقصة أصلاً
    }
};
