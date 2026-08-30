<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

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
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function for(string $key): array
    {
        $def = $this->definition($key);
        $stored = $this->storedRaw();

        return $this->mergeDefinition($key, $def, $stored[$key] ?? []);
    }

    /** @return array{group: string, title: string, description: string, view: ?string, print_route: ?string, fields: array, defaults: array} */
    public function definition(string $key): array
    {
        $def = config("document_templates.definitions.{$key}");

        if (! is_array($def)) {
            abort(404, 'نوع الوثيقة غير معرّف.');
        }

        return $def;
    }

    public function exists(string $key): bool
    {
        return is_array(config("document_templates.definitions.{$key}"));
    }

    /** @param  array<string, mixed>  $payload */
    public function update(string $key, array $payload): array
    {
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

    private function castField(string $type, mixed $value, mixed $default): mixed
    {
        if ($type === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($type === 'textarea' || $type === 'text') {
            $text = trim((string) $value);

            return $text === '' ? (is_string($default) ? $default : '') : mb_substr($text, 0, 2000);
        }

        return $value;
    }

    private static function cacheKey(): string
    {
        return 'settings.'.self::SETTING_KEY;
    }
}
