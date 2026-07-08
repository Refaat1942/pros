<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أرصدة افتتاحية يدوية لكل مجال (نقدية/مدني/عسكري/مخزون) — تُستخدم للفترة
     * الأولى فقط عندما لا يوجد تاريخ حركات سابق يُشتق منه الرصيد الافتتاحي.
     */
    public function up(): void
    {
        Schema::create('period_opening_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->string('domain', 32); // cash | civilian | military | inventory
            $table->decimal('opening_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['accounting_period_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_opening_overrides');
    }
};
