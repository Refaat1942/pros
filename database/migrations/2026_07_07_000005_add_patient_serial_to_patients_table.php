<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سيريال تسلسلي للمريض بصيغة PT-YYYY-NNNN — للعرض والطباعة.
 * يبقى patient_code العشوائي و patient_qr للمسح والتوافق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('patient_serial')->nullable()->unique()->after('patient_code');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('patient_serial');
        });
    }
};
