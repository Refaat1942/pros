<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_kits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 32)->default('assembly');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_kit_id')->constrained('stock_kits')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['stock_kit_id', 'stock_item_id']);
        });

        if (! Schema::hasColumn('tech_order_spec_items', 'group_label')) {
            Schema::table('tech_order_spec_items', function (Blueprint $table) {
                $table->string('group_label')->nullable()->after('qty');
            });
        }

        if (! Schema::hasColumn('bom_items', 'group_label')) {
            Schema::table('bom_items', function (Blueprint $table) {
                $table->string('group_label')->nullable()->after('qty');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('bom_items', 'group_label')) {
                $table->dropColumn('group_label');
            }
        });

        Schema::table('tech_order_spec_items', function (Blueprint $table) {
            if (Schema::hasColumn('tech_order_spec_items', 'group_label')) {
                $table->dropColumn('group_label');
            }
        });

        Schema::dropIfExists('stock_kit_items');
        Schema::dropIfExists('stock_kits');
    }
};
