<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * لقطة التكاليف المجمّدة: النمط المختار + المكوّنات + الربح + سعر البيع (الذي يصبح عرض السعر).
     */
    public function up(): void
    {
        Schema::table('pricing_requests', function (Blueprint $table) {
            $table->string('costing_mode')->nullable()->after('internal_total');
            $table->decimal('components_total', 12, 2)->default(0)->after('costing_mode');
            $table->decimal('total_cost', 12, 2)->default(0)->after('components_total');
            $table->decimal('profit_rate', 8, 2)->default(0)->after('total_cost');
            $table->decimal('selling_price', 12, 2)->default(0)->after('profit_rate');
            $table->text('components_snapshot')->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_requests', function (Blueprint $table) {
            $table->dropColumn([
                'costing_mode', 'components_total', 'total_cost',
                'profit_rate', 'selling_price', 'components_snapshot',
            ]);
        });
    }
};
