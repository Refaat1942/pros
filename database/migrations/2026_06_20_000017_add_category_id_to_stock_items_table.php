<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('spec')
                ->constrained('stock_categories')
                ->nullOnDelete();
        });

        $names = DB::table('stock_items')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        foreach ($names as $name) {
            $id = DB::table('stock_categories')->where('name', $name)->value('id');

            if (! $id) {
                $id = DB::table('stock_categories')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('stock_items')
                ->where('category', $name)
                ->update(['category_id' => $id]);
        }

        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('category')->nullable()->after('spec');
            $table->index('category');
        });

        $items = DB::table('stock_items')
            ->leftJoin('stock_categories', 'stock_items.category_id', '=', 'stock_categories.id')
            ->select('stock_items.id', 'stock_categories.name')
            ->get();

        foreach ($items as $row) {
            if ($row->name) {
                DB::table('stock_items')->where('id', $row->id)->update(['category' => $row->name]);
            }
        }

        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
