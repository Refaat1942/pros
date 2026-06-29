<?php

use App\Models\VisitType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
        });

        $preferred = [
            'كشف أولي',
            'متابعة طبية',
            'تجربة تركيب',
            'تعديل وصيانة',
            'تسليم الطرف',
            'مراجعة ما بعد التسليم',
        ];

        $order = 0;
        foreach ($preferred as $name) {
            $type = VisitType::query()->where('name', $name)->first();
            if ($type) {
                $order += 10;
                $type->update(['sort_order' => $order]);
            }
        }

        VisitType::query()
            ->where('sort_order', 0)
            ->orderBy('id')
            ->get()
            ->each(function (VisitType $type) use (&$order) {
                $order += 10;
                $type->update(['sort_order' => $order]);
            });
    }

    public function down(): void
    {
        Schema::table('visit_types', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
