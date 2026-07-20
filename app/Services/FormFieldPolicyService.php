<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * إلزامية حقول النماذج — قابلة للتعديل من مصمم المسار (لوحة الإدارة).
 */
class FormFieldPolicyService
{
    public const SETTING_KEY = 'form_field_required';

    /** @var array<string, array<string, array{label_ar: string, default: bool}>> */
    private const CATALOG = [
        'reception' => [
            'phone' => ['label_ar' => 'رقم الهاتف', 'default' => false],
            'national_id' => ['label_ar' => 'الرقم القومي', 'default' => false],
            'contract_company_id' => ['label_ar' => 'جهة التعاقد (مدني جهات)', 'default' => true],
            'military_number' => ['label_ar' => 'الرقم العسكري', 'default' => true],
            'seniority_number' => ['label_ar' => 'رقم الأقدمية', 'default' => false],
            'military_weapon' => ['label_ar' => 'السلاح / الفرع', 'default' => true],
        ],
        'spec' => [
            'written_items' => ['label_ar' => 'بنود الوصف الحر', 'default' => false],
            'tech_notes' => ['label_ar' => 'ملاحظات التوصيف', 'default' => false],
        ],
        'appointment' => [
            'phone' => ['label_ar' => 'هاتف الموعد', 'default' => true],
        ],
    ];

    public function isRequired(string $feature, string $field): bool
    {
        $policies = $this->all();

        return (bool) ($policies[$feature][$field] ?? self::CATALOG[$feature][$field]['default'] ?? false);
    }

    /** @return array<string, array<string, bool>> */
    public function all(): array
    {
        return Cache::rememberForever('settings.form_field_required', function () {
            $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
            $stored = $raw ? json_decode($raw, true) : [];
            if (! is_array($stored)) {
                $stored = [];
            }

            $merged = [];
            foreach (self::CATALOG as $feature => $fields) {
                foreach ($fields as $field => $meta) {
                    $merged[$feature][$field] = (bool) ($stored[$feature][$field] ?? $meta['default']);
                }
            }

            return $merged;
        });
    }

    /** @return array<string, array<string, array{label_ar: string, required: bool}>> */
    public function catalogForAdmin(): array
    {
        $policies = $this->all();
        $out = [];

        foreach (self::CATALOG as $feature => $fields) {
            foreach ($fields as $field => $meta) {
                $out[$feature][] = [
                    'field' => $field,
                    'label_ar' => $meta['label_ar'],
                    'required' => (bool) ($policies[$feature][$field] ?? $meta['default']),
                ];
            }
        }

        return $out;
    }

    /** @param  array<string, array<string, bool|int|string>>  $input */
    public function update(array $input): void
    {
        $normalized = [];

        foreach (self::CATALOG as $feature => $fields) {
            foreach (array_keys($fields) as $field) {
                $normalized[$feature][$field] = filter_var(
                    $input[$feature][$field] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
            }
        }

        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($normalized, JSON_UNESCAPED_UNICODE)],
        );

        Cache::forget('settings.form_field_required');
    }
}
