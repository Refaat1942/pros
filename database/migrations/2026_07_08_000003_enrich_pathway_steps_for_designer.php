<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pathway_steps', function (Blueprint $table) {
            $table->string('owner_department', 32)->nullable()->after('description');
            $table->text('action_summary')->nullable()->after('owner_department');
            $table->string('on_complete', 255)->nullable()->after('action_summary');
            $table->boolean('required')->default(true)->after('on_complete');
            $table->boolean('auto_skip')->default(false)->after('required');
            $table->json('skip_roles')->nullable()->after('auto_skip');
            $table->json('handlers')->nullable()->after('skip_roles');
        });
    }

    public function down(): void
    {
        Schema::table('pathway_steps', function (Blueprint $table) {
            $table->dropColumn([
                'owner_department',
                'action_summary',
                'on_complete',
                'required',
                'auto_skip',
                'skip_roles',
                'handlers',
            ]);
        });
    }
};
