<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * صلاحية تفصيلية — عرض صفحة أو إجراء داخل لوحة تحكم.
 *
 * صلاحيات العرض:  {dashboard}.{page}.view
 * صلاحيات الإجراء: slug مُعرَّف في config/permissions.php
 */
class Permission extends Model
{
    public const TYPE_VIEW   = 'view';
    public const TYPE_ACTION = 'action';

    protected $fillable = [
        'slug',
        'label_ar',
        'group',
        'type',
        'dashboard',
        'page',
    ];

    /** @var array<string, array{label_ar: string, dashboard: string, type: string, page?: string}>|null */
    private static ?array $catalogCache = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * السجل الكامل: صفحات كل لوحة + الإجراءات المُعرَّفة.
     *
     * @return array<string, array{label_ar: string, dashboard: string, type: string, page?: string}>
     */
    public static function catalog(): array
    {
        if (self::$catalogCache !== null) {
            return self::$catalogCache;
        }

        $entries = [];

        foreach (config('dashboards', []) as $dashKey => $dash) {
            if ($dashKey === 'home' || empty($dash['pages'])) {
                continue;
            }

            foreach ($dash['pages'] as $pageKey => $page) {
                $slug = self::viewSlug($dashKey, $pageKey);
                $entries[$slug] = [
                    'label_ar'  => $page['label'] ?? $page['title'] ?? $pageKey,
                    'dashboard' => $dashKey,
                    'type'      => self::TYPE_VIEW,
                    'page'      => $pageKey,
                ];
            }
        }

        foreach (config('permissions.actions', []) as $slug => $meta) {
            $entries[$slug] = [
                'label_ar'  => $meta['label_ar'],
                'dashboard' => $meta['dashboard'],
                'type'      => self::TYPE_ACTION,
            ];
        }

        return self::$catalogCache = $entries;
    }

    public static function viewSlug(string $dashboard, string $page): string
    {
        return "{$dashboard}.{$page}.view";
    }

    public static function isViewSlug(string $slug): bool
    {
        return str_ends_with($slug, '.view');
    }
}
