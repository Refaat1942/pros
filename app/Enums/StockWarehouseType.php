<?php

namespace App\Enums;

use App\Models\Bom;

/**
 * أنواع المخازن التشغيلية — تُطابق مراحل BOM (raw → wip → finished).
 *
 * - مخزن خام: أرصدة بطاقة الأصناف + قوائم بانتظار الصرف
 * - مخزن إنتاج: مواد مُصرفة للورشة (تحت التنفيذ)
 * - مخزن تسليم: منتج تام بانتظار تسليم المريض
 */
enum StockWarehouseType: string
{
    case Raw        = 'raw';
    case Production = 'wip';
    case Delivery   = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Raw        => 'مخزن خام',
            self::Production => 'مخزن إنتاج',
            self::Delivery   => 'مخزن تسليم',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Raw        => 'خام',
            self::Production => 'إنتاج',
            self::Delivery   => 'تسليم',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Raw        => 'البضاعة على الرف — قبل الصرف بالباركود',
            self::Production => 'المواد في الورشة — تحت التنفيذ',
            self::Delivery   => 'المنتجات الجاهزة — بانتظار تسليم المريض',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Raw        => '📦',
            self::Production => '🏭',
            self::Delivery   => '✅',
        };
    }

    public function bomStage(): string
    {
        return $this->value;
    }

    public static function fromBomStage(?string $stage): ?self
    {
        return match ($stage) {
            Bom::STAGE_RAW, self::Raw->value => self::Raw,
            Bom::STAGE_WIP, self::Production->value => self::Production,
            Bom::STAGE_FINISHED, self::Delivery->value => self::Delivery,
            default => null,
        };
    }

    public static function labelForBomStage(?string $stage): string
    {
        return self::fromBomStage($stage)?->label() ?? ($stage ?? '—');
    }

    public static function shortLabelForBomStage(?string $stage): string
    {
        return self::fromBomStage($stage)?->shortLabel() ?? ($stage ?? '—');
    }

    /** @return list<array{key: string, label: string, short: string, icon: string, description: string}> */
    public static function catalog(): array
    {
        return array_map(
            fn (self $w) => [
                'key'         => $w->bomStage(),
                'label'       => $w->label(),
                'short'       => $w->shortLabel(),
                'icon'        => $w->icon(),
                'description' => $w->description(),
            ],
            self::cases()
        );
    }
}
