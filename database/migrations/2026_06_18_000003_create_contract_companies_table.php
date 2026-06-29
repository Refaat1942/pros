<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جهات التعاقد — clinic_contract_companies في admin-dashboard.js
     */
    public function up(): void
    {
        Schema::create('contract_companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->unique(); // CO-001
            $table->string('name'); // اسم جهة التعاقد (تأمين، صحي، عسكري...)
            $table->boolean('is_military')->default(false);
            $table->boolean('is_contracted')->default(true); // true = هيئة متعاقدة، false = غير متعاقدة
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_companies');
    }
};
