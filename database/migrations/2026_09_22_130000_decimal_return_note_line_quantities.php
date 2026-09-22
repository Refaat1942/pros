<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE return_note_lines MODIFY qty_requested DECIMAL(15,4) NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE return_note_lines MODIFY qty_returned DECIMAL(15,4) NOT NULL DEFAULT 0');
        } else {
            Schema::table('return_note_lines', function (Blueprint $table) {
                $table->decimal('qty_requested', 15, 4)->default(1)->change();
                $table->decimal('qty_returned', 15, 4)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE return_note_lines MODIFY qty_requested INT UNSIGNED NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE return_note_lines MODIFY qty_returned INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};
