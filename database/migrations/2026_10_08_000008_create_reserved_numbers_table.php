<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجل واحد لكل الأرقام ذات الستة أرقام (رقم المريض، رقم الطلب، رقم طلب التسعير).
 *
 * كان كل نوع يتحقق من التكرار في جدوله فقط، فقد يكون رقم مريض هو نفسه رقم طلب
 * لمريض آخر — والبحث بالرقم يُظهر الاثنين. الفهرس الفريد هنا يمنع أي رقم من الظهور
 * مرتين بأي نوع، حتى عند التسجيل المتزامن من أكثر من جهاز.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_numbers', function (Blueprint $table) {
            $table->string('number', 32)->primary();
            $table->string('kind', 32);
            $table->timestamp('created_at')->nullable();
        });

        // الأرقام الموجودة تُحجز — الأقدم أولاً، فلو تكرر رقم بين نوعين يبقى لصاحبه الأول.
        $sources = [
            ['patients', 'patient_code', 'patient'],
            ['cases', 'order_ref', 'order_ref'],
            ['tech_order_specs', 'order_ref', 'order_ref'],
            ['pricing_requests', 'request_no', 'pricing_request'],
        ];

        foreach ($sources as [$table, $column, $kind]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy('id')
                ->select(['id', $column])
                ->chunkById(500, function ($rows) use ($column, $kind) {
                    DB::table('reserved_numbers')->insertOrIgnore(
                        $rows->map(fn ($row) => [
                            'number' => (string) $row->{$column},
                            'kind' => $kind,
                            'created_at' => now(),
                        ])->unique('number')->values()->all()
                    );
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_numbers');
    }
};
