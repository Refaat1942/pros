<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-4: تحويل حذف السجلات المالية/القانونية من CASCADE إلى RESTRICT.
 *
 * الجداول payments / credit_notes / military_debts كانت تُحذف تلقائياً عند حذف
 * الحالة/المريض (cascadeOnDelete)، ما يُتلف تاريخاً مالياً وقانونياً. نحوّلها إلى
 * RESTRICT حتى تمنع قاعدة البيانات حذف حالة تحمل سجلاً مالياً — بالإضافة إلى حارس
 * التطبيق PatientDeletionGuard الذي يعمل في كل المحركات.
 *
 * التوافق:
 *   - PostgreSQL (VPS/LAN): يُعاد إنشاء قيد المفتاح الأجنبي بـ ON DELETE RESTRICT.
 *   - SQLite (اختبارات): لا يدعم تغيير قيود FK عبر ALTER — يُتخطّى بأمان، والحماية
 *     تأتي من PatientDeletionGuard على مستوى التطبيق.
 *   - MySQL: مدعوم أيضاً عبر DROP/ADD FOREIGN KEY.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{column: string, references: string, on: string}>
     */
    private array $foreignKeys = [
        'payments' => ['column' => 'case_id', 'references' => 'id', 'on' => 'cases'],
        'credit_notes' => ['column' => 'case_id', 'references' => 'id', 'on' => 'cases'],
        'military_debts' => ['column' => 'case_id', 'references' => 'id', 'on' => 'cases'],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite لا يسمح بتعديل قيود FK بعد الإنشاء — الحماية عبر PatientDeletionGuard.
            return;
        }

        foreach ($this->foreignKeys as $table => $fk) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->recreateForeignKey($table, $fk['column'], $fk['references'], $fk['on'], 'restrict');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        foreach ($this->foreignKeys as $table => $fk) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->recreateForeignKey($table, $fk['column'], $fk['references'], $fk['on'], 'cascade');
        }
    }

    private function recreateForeignKey(
        string $table,
        string $column,
        string $references,
        string $on,
        string $onDelete,
    ): void {
        Schema::table($table, function ($blueprint) use ($column) {
            // اسم القيد الافتراضي في Laravel: {table}_{column}_foreign
            $blueprint->dropForeign([$column]);
        });

        Schema::table($table, function ($blueprint) use ($column, $references, $on, $onDelete) {
            $blueprint->foreign($column)
                ->references($references)
                ->on($on)
                ->onDelete($onDelete);
        });
    }
};
