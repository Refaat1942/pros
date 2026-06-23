<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة هيكلة خط الإنتاج: المعدلات قبل التكاليف + التكلفة الداخلية (WAC).
 *
 *  - cases.internal_cost          : التكلفة الحقيقية (WAC) — للأدمن فقط.
 *  - pricing_requests.internal_total : إجمالي WAC الداخلي مقابل computed_total (أعلى سعر شراء).
 *  - bom_items.source             : 'spec' (الفني — للقراءة) | 'adjustment' (مستشار المعدلات).
 *  - إعادة تسمية المراحل القديمة إلى التسلسل الجديد.
 *  - حذف تجارب التركيب (fitting_trials) — أُعيد توظيف "المعدلات".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (! Schema::hasColumn('cases', 'internal_cost')) {
                $table->decimal('internal_cost', 15, 2)->default(0)->after('total_cost');
            }

            // ── محرك الربحية العسكرية (خلفي — يظهر للسوبر أدمن فقط) ───────────
            if (! Schema::hasColumn('cases', 'military_selling_price')) {
                $table->decimal('military_selling_price', 15, 2)->default(0)->after('internal_cost');
            }
            if (! Schema::hasColumn('cases', 'military_markup_pct')) {
                $table->decimal('military_markup_pct', 8, 2)->default(0)->after('military_selling_price');
            }
        });

        Schema::table('pricing_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('pricing_requests', 'internal_total')) {
                $table->decimal('internal_total', 15, 2)->default(0)->after('computed_total');
            }
        });

        Schema::table('bom_items', function (Blueprint $table) {
            if (! Schema::hasColumn('bom_items', 'source')) {
                $table->string('source')->default('spec')->after('name');
            }
        });

        // إعادة تسمية المراحل القديمة إلى التسلسل الجديد.
        DB::table('cases')->where('stage_key', 'waiting_return')->update(['stage_key' => 'operations']);
        DB::table('cases')->where('stage_key', 'admin_approval')->update(['stage_key' => 'cost_calc']);

        Schema::dropIfExists('fitting_trials');
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            foreach (['internal_cost', 'military_selling_price', 'military_markup_pct'] as $column) {
                if (Schema::hasColumn('cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('pricing_requests', function (Blueprint $table) {
            if (Schema::hasColumn('pricing_requests', 'internal_total')) {
                $table->dropColumn('internal_total');
            }
        });

        Schema::table('bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('bom_items', 'source')) {
                $table->dropColumn('source');
            }
        });

        Schema::create('fitting_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->date('trial1_date')->nullable();
            $table->date('trial2_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
};
