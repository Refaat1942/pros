<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** حالة «صرف سريع» (عكاز/كرسي…): بعد الدفع تتصرف وتتسلم من غير قسم الإنتاج. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cases', 'quick_dispense_at')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->timestamp('quick_dispense_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cases', 'quick_dispense_at')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropColumn('quick_dispense_at');
            });
        }
    }
};
