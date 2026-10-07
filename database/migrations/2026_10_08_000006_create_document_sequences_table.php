<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عدّاد لكل نوع ترقيم (حالة، عرض سعر، أمر شغل…) — صف واحد يُقفل عند الإصدار،
 * فجهازان في نفس اللحظة يأخذان رقمين متتاليين بدل أن يتعارضا على نفس الرقم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
