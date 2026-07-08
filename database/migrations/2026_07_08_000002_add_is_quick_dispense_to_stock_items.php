<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * علم «صنف صرف سريع» — الأصناف المعلَّمة تأخذ هامش ربح مباشر (40%) بلا مكوّنات.
     */
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->boolean('is_quick_dispense')->default(false)->after('store_class');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn('is_quick_dispense');
        });
    }
};
