<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مصطلحات مهنية لأسماء الأدوار الافتراضية — يُغيَّر الاسم فقط لو لسه بالقيمة الافتراضية القديمة
 * (أي اسم عدّله العميل يفضل زي ما هو). أسماء المستخدمين لا تُلمس.
 */
return new class extends Migration
{
    private const RENAMES = [
        ['slug' => 'super_admin', 'from' => 'سوبر أدمن', 'to' => 'مدير النظام'],
        ['slug' => 'cashier', 'from' => 'الخزنة', 'to' => 'الخزينة'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }
        foreach (self::RENAMES as $r) {
            DB::table('roles')->where('slug', $r['slug'])->where('label_ar', $r['from'])->update(['label_ar' => $r['to']]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }
        foreach (self::RENAMES as $r) {
            DB::table('roles')->where('slug', $r['slug'])->where('label_ar', $r['to'])->update(['label_ar' => $r['from']]);
        }
    }
};
