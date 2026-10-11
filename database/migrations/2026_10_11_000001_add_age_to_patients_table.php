<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سن المريض يُدخله الاستقبال — يُستخدم عند غياب الرقم القومي (السن من الرقم القومي له الأولوية).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patients', 'age')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('national_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'age')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('age');
            });
        }
    }
};
