<?php

namespace App\Services;

use App\Models\CustomDocument;
use App\Models\CustomDocument;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DocumentTemplateService
{
    public const SETTING_KEY = 'document_templates';

    /** @return array<string, array<string, mixed>> */
    public function catalog(): array
    {
        $definitions = config('document_templates.definitions', []);
        $stored = $this->storedRaw();
        $out = [];

        foreach ($definitions as $key => $def) {
            $merged = $this->mergeDefinition($key, $def, $stored[$key] ?? []);
            $out[$key] = [
                'key' => $key,
                'group' => $def['group'],
                'title' => $def['title'],
                'description' => $def['description'],
                'print_route' => $def['print_route'] ?? null,
                'fields' => $def['fields'],
                'values' => $merged,
                'is_custom' => false,
            ];
        }

        foreach ($this->safeCustomDocuments() as $custom) {
            $def = $this->customDefinition($custom);
            $merged = $this->mergeCustomTemplate($custom);
            $out[$custom->key] = [
                'key' => $custom->key,
                'group' => $custom->group_label,
                'title' => $custom->title,
                'description' => $custom->description ?? '',
                'print_route' => null,
                'fields' => $def['fields'],
                'values' => $merged,
                'is_custom' => true,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function for(string $key): array
    {
        $custom = $this->findCustomByKey($key);
        if ($custom) {
            return $this->mergeCustomTemplate($custom);
        }

        $def = $this->definition($key);
        $stored = $this->storedRaw();

        return $this->mergeDefinition($key, $def, $stored[$key] ?? []);
    }

    /**
     * @return array{group: string, title: string, description: string, view: ?string, print_route: ?string, fields: array, defaults: array}
     */
    public function definition(string $key): array
    {
        $custom = $this->findCustomByKey($key);
        if ($custom) {
            return $this->customDefinition($custom);
        }

        $def = config("document_templates.definitions.{$key}");

        if (! is_array($def)) {
            abort(404, 'نوع الوثيقة غير معرّف.');
        }

        return $def;
    }

    public function exists(string $key): bool
    {
        if (is_array(config("document_templates.definitions.{$key}"))) {
            return true;
        }

        return $this->findCustomByKey($key) instanceof CustomDocument;
    }

    public function isCustom(string $key): bool
    {
        return str_starts_with($key, 'custom_')
            && $this->findCustomByKey($key) instanceof CustomDocument;
    }

    /** @param  array<string, mixed>  $payload */
    public function update(string $key, array $payload): array
    {
        $custom = $this->findCustomByKey($key);
        if ($custom) {
            return $this->updateCustomTemplate($custom, $payload);
        }

        $def = $this->definition($key);
        $allowed = collect($def['fields'])->pluck('key')->all();
        $defaults = $def['defaults'];

        $clean = [];
        foreach ($allowed as $fieldKey) {
            if (! array_key_exists($fieldKey, $payload)) {
                continue;
            }
            $type = collect($def['fields'])->firstWhere('key', $fieldKey)['type'] ?? 'text';
            $clean[$fieldKey] = $this->castField($type, $payload[$fieldKey], $defaults[$fieldKey] ?? null);
        }

        if (array_key_exists('font_scale', $defaults) && isset($payload['font_scale'])) {
            $scale = (string) $payload['font_scale'];
            $clean['font_scale'] = in_array($scale, ['compact', 'normal'], true) ? $scale : 'compact';
        }

        $stored = $this->storedRaw();
        $stored[$key] = array_merge($stored[$key] ?? [], $clean);

        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($stored, JSON_UNESCAPED_UNICODE)],
        );

        Cache::forget(self::cacheKey());

        return $this->for($key);
    }

    /**
     * استبدال placeholders في النص — مثلاً {no} → رقم الوثيقة.
     */
    public function renderText(string $template, array $vars = []): string
    {
        $out = $template;
        foreach ($vars as $name => $value) {
            $out = str_replace('{'.$name.'}', (string) $value, $out);
        }

        return $out;
    }

    /** @return list<array{group: string, items: list<array<string, mixed>>}> */
    public function hubGroups(): array
    {
        $groups = [];
        foreach ($this->catalog() as $key => $entry) {
            $group = $entry['group'];
            if (! isset($groups[$group])) {
                $groups[$group] = ['group' => $group, 'items' => []];
            }

            $printUrl = null;
            if ($entry['print_route']) {
                try {
                    $printUrl = route($entry['print_route']);
                } catch (\Throwable) {
                    $printUrl = null;
                }
            }

            $groups[$group]['items'][] = [
                'key' => $key,
                'title' => $entry['title'],
                'description' => $entry['description'],
                'print_url' => $printUrl,
                'edit_url' => route('admin.documents-hub.edit', $key),
                'preview_url' => route('admin.documents-hub.preview', $key),
                'is_custom' => (bool) ($entry['is_custom'] ?? false),
            ];
        }

        return array_values($groups);
    }

    /** @return array<string, array<string, mixed>> */
    private function storedRaw(): array
    {
        return Cache::rememberForever(self::cacheKey(), function () {
            $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
            if (! is_string($raw) || $raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        });
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function mergeDefinition(string $key, array $def, array $stored): array
    {
        $defaults = $def['defaults'] ?? [];
        $merged = array_merge($defaults, $stored);
        $merged['document_key'] = $key;

        return $merged;
    }

    /** @return array<string, mixed> */
    private function mergeCustomTemplate(CustomDocument $custom): array
    {
        $defaults = CustomDocumentService::defaultTemplateValues($custom->title);
        $merged = array_merge($defaults, $custom->template_values ?? []);
        $merged['document_key'] = $custom->key;
        $merged['body_html'] = $custom->body_html;

        return $merged;
    }

    /**
     * @return array{group: string, title: string, description: string, view: null, print_route: null, fields: array, defaults: array}
     */
    private function customDefinition(CustomDocument $custom): array
    {
        $fields = CustomDocumentService::fieldDefinitions();
        $fields[] = [
            'key' => 'body_html',
            'label' => 'محتوى الوثيقة (HTML)',
            'type' => 'html',
            'help' => 'يمكنك نسخ التنسيق من النموذج المرفوع وتعديله هنا.',
        ];

        return [
            'group' => $custom->group_label,
            'title' => $custom->title,
            'description' => $custom->description ?? '',
            'view' => null,
            'print_route' => null,
            'fields' => $fields,
            'defaults' => CustomDocumentService::defaultTemplateValues($custom->title),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function updateCustomTemplate(CustomDocument $custom, array $payload): array
    {
        $allowed = collect(CustomDocumentService::fieldDefinitions())->pluck('key')->all();
        $defaults = CustomDocumentService::defaultTemplateValues($custom->title);
        $template = $custom->template_values ?? [];

        foreach ($allowed as $fieldKey) {
            if (! array_key_exists($fieldKey, $payload)) {
                continue;
            }
            $type = collect(CustomDocumentService::fieldDefinitions())->firstWhere('key', $fieldKey)['type'] ?? 'text';
            $template[$fieldKey] = $this->castField($type, $payload[$fieldKey], $defaults[$fieldKey] ?? null);
        }

        if (isset($payload['font_scale'])) {
            $scale = (string) $payload['font_scale'];
            $template['font_scale'] = in_array($scale, ['compact', 'normal'], true) ? $scale : 'compact';
        }

        if (array_key_exists('body_html', $payload)) {
            $custom->body_html = trim((string) $payload['body_html']) ?: null;
        }

        $custom->template_values = $template;
        $custom->save();

        return $this->mergeCustomTemplate($custom->fresh());
    }

    private function castField(string $type, mixed $value, mixed $default): mixed
    {
        if ($type === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($type === 'textarea' || $type === 'text' || $type === 'html') {
            $text = trim((string) $value);
            $limit = $type === 'html' ? 50000 : 2000;

            return $text === '' ? (is_string($default) ? $default : '') : mb_substr($text, 0, $limit);
        }

        return $value;
    }

    private static function cacheKey(): string
    {
        return 'settings.'.self::SETTING_KEY;
    }

    /** @return list<CustomDocument> */
    private function safeCustomDocuments(): array
    {
        if (! $this->customDocumentsTableReady()) {
            return [];
        }

        try {
            return app(CustomDocumentService::class)->activeList();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function findCustomByKey(string $key): ?CustomDocument
    {
        if (! $this->customDocumentsTableReady()) {
            return null;
        }

        try {
            return app(CustomDocumentService::class)->findByKey($key);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function customDocumentsTableReady(): bool
    {
        try {
            return Schema::hasTable('custom_documents');
        } catch (\Throwable) {
            return false;
        }
    }
}
