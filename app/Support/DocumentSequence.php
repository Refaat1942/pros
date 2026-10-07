<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * الرقم التالي لنوع ترقيم — آمن عند الطلبات المتزامنة من أكثر من جهاز.
 *
 * كان كل مولّد يقرأ «آخر رقم» بقفل ثم يضيف 1؛ على PostgreSQL القفل لا يمنع جهازاً ثانياً
 * من قراءة نفس آخر رقم، فيأخذ الاثنان نفس الرقم ويفشل أحدهما بخطأ «رقم مكرر»
 * (في التجربة: 13 من 16 حالة فُتحت في نفس اللحظة فشلت). الآن صف العدّاد نفسه يُقفل
 * حتى انتهاء العملية، فالجهاز الثاني ينتظر لحظة ويأخذ الرقم التالي.
 */
final class DocumentSequence
{
    /**
     * @param  string  $key  مثال: CASE-2026
     * @param  callable(): int  $currentMax  أعلى رقم مستخدَم فعلاً — يُقرأ مرة واحدة عند أول استخدام للمفتاح
     */
    public static function next(string $key, callable $currentMax): int
    {
        return DB::transaction(function () use ($key, $currentMax) {
            $row = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('document_sequences')->insertOrIgnore([
                    'key' => $key,
                    'value' => max(0, (int) $currentMax()),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();
            }

            $next = (int) $row->value + 1;

            DB::table('document_sequences')->where('key', $key)->update([
                'value' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });
    }

    /**
     * أعلى رقم في عمود بصيغة {prefix}{رقم} — لتهيئة العدّاد من البيانات الموجودة.
     *
     * @param  class-string<Model>  $model
     */
    public static function maxSuffix(string $model, string $column, string $prefix): int
    {
        return (int) $model::query()
            ->where($column, 'like', $prefix.'%')
            ->pluck($column)
            ->map(fn ($value) => (int) preg_replace('/\D/', '', substr((string) $value, strlen($prefix))))
            ->max();
    }
}
