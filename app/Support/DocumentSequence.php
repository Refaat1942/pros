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
            $table = DB::table('document_sequences');

            // زيادة ذرّية بأمر واحد بدل «اقفل ثم أضف»: على MySQL كان قفل صف غير موجود بعد
            // (أول رقم في يوم/سنة جديدة) يقفل فجوة في الفهرس فيتعارض جهازان (deadlock).
            if ($table->clone()->where('key', $key)->exists()) {
                $table->clone()->where('key', $key)->update([
                    'value' => DB::raw(DB::getQueryGrammar()->wrap('value').' + 1'),
                    'updated_at' => now(),
                ]);
            } else {
                $table->clone()->upsert([[
                    'key' => $key,
                    'value' => max(0, (int) $currentMax()) + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]], ['key'], [
                    'value' => DB::raw(DB::getQueryGrammar()->wrap('document_sequences.value').' + 1'),
                    'updated_at' => now(),
                ]);
            }

            return (int) $table->clone()->where('key', $key)->lockForUpdate()->value('value');
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
