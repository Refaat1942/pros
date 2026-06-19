<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('quote_total');
            $table->decimal('invoice_total', 15, 2)->nullable()->after('invoice_no');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('last_visit_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['invoice_no', 'invoice_total']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
