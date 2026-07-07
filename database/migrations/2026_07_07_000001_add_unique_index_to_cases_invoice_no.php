<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهرس فريد على رقم الفاتورة يمنع ترقيماً مكرراً عند تسليم حالتين متزامنتين.
 * القيم null مسموح تكرارها (الحالات غير المُسلَّمة) في MySQL و SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->unique('invoice_no', 'cases_invoice_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique('cases_invoice_no_unique');
        });
    }
};
