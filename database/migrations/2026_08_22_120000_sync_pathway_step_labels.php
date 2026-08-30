<?php

use App\Services\PathwayConfigService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(PathwayConfigService::class)->syncStepLabelsFromDefaults();
    }

    public function down(): void
    {
        // لا تراجع — التسميات السابقة قد تكون قديمة.
    }
};
