<?php

namespace App\Services;

use App\Enums\StockCategoryFieldType;
use App\Enums\StockUom;
use App\Models\StockCategory;
use App\Models\StockCategoryField;
use App\Models\StockItem;
use App\Models\StockItemAttributeValue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockCategorySchemaService
{
    /** @return array<string, mixed> */
    public function formatCategory(StockCategory $category): array
    {
        $category->loadMissing('fields');

        return [
            'id'     => $category->id,
            'name'   => $category->name,
            'fields' => $category->fields->map(fn (StockCategoryField $f) => $this->formatField($f))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function formatField(StockCategoryField $field): array
    {
        return [
            'id'         => $field->id,
            'field_key'  => $field->field_key,
            'label'      => $field->label,
            'type'       => $field->type,
            'options'    => $field->options ?? [],
            'config'     => $field->config ?? [],
            'required'   => (bool) $field->required,
            'sort_order' => (int) $field->sort_order,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function syncFields(StockCategory $category, array $fields): void
    {
        $keepIds = [];
        $usedKeys = [];

        foreach (array_values($fields) as $index => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $type  = (string) ($row['type'] ?? StockCategoryFieldType::Text->value);

            if ($label === '') {
                continue;
            }

            if (! in_array($type, StockCategoryFieldType::values(), true)) {
                continue;
            }

            $fieldKey = trim((string) ($row['field_key'] ?? ''));
            if ($fieldKey === '') {
                $fieldKey = Str::slug($label, '_');
            }
            $fieldKey = Str::limit($fieldKey, 64, '');

            $suffix = 1;
            $baseKey = $fieldKey;
            while (in_array($fieldKey, $usedKeys, true)) {
                $fieldKey = Str::limit($baseKey . '_' . $suffix, 64, '');
                $suffix++;
            }
            $usedKeys[] = $fieldKey;

            $payload = [
                'field_key'  => $fieldKey,
                'label'      => $label,
                'type'       => $type,
                'options'    => $this->normalizeOptions($row['options'] ?? []),
                'config'     => $this->normalizeConfig($type, $row['config'] ?? []),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : (($index + 1) * 10),
                'required'   => (bool) ($row['required'] ?? false),
            ];

            $fieldId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
            if ($fieldId && $existing = $category->fields()->whereKey($fieldId)->first()) {
                $existing->update($payload);
                $keepIds[] = $existing->id;
                continue;
            }

            $created = $category->fields()->create($payload);
            $keepIds[] = $created->id;
        }

        if ($keepIds) {
            $category->fields()->whereNotIn('id', $keepIds)->delete();
        } else {
            $category->fields()->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncItemAttributes(StockItem $item, ?int $categoryId, array $attributes): void
    {
        if (! $categoryId) {
            $item->attributeValues()->delete();

            return;
        }

        $fields = StockCategoryField::query()
            ->where('category_id', $categoryId)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('field_key');

        $validated = $this->validateAttributes($fields, $attributes);

        $keepIds = [];
        foreach ($fields as $fieldKey => $field) {
            if (! array_key_exists($fieldKey, $validated)) {
                continue;
            }

            $stored = $this->encodeValue($field, $validated[$fieldKey]);
            $record = StockItemAttributeValue::query()->updateOrCreate(
                [
                    'stock_item_id'     => $item->id,
                    'category_field_id' => $field->id,
                ],
                ['value' => $stored],
            );
            $keepIds[] = $record->id;

            if ($fieldKey === 'uom' && is_string($validated[$fieldKey]) && $validated[$fieldKey] !== '') {
                $item->update(['uom' => $this->mapUom($validated[$fieldKey])]);
            }
        }

        $item->attributeValues()
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, StockCategoryField>  $fields
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function validateAttributes($fields, array $attributes): array
    {
        $out = [];
        $errors = [];

        foreach ($fields as $fieldKey => $field) {
            $raw = $attributes[$fieldKey] ?? null;
            $type = StockCategoryFieldType::tryFrom($field->type) ?? StockCategoryFieldType::Text;

            if ($this->isEmptyValue($type, $raw)) {
                if ($field->required) {
                    $errors[$fieldKey] = "«{$field->label}» مطلوب.";
                }
                continue;
            }

            try {
                $out[$fieldKey] = $this->castValue($field, $type, $raw);
            } catch (\InvalidArgumentException $e) {
                $errors[$fieldKey] = $e->getMessage();
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** @return list<array{value: string, label: string}> */
    private function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $out = [];
        foreach ($options as $option) {
            if (is_string($option)) {
                $option = trim($option);
                if ($option !== '') {
                    $out[] = ['value' => $option, 'label' => $option];
                }
                continue;
            }
            if (! is_array($option)) {
                continue;
            }
            $value = trim((string) ($option['value'] ?? $option['label'] ?? ''));
            $label = trim((string) ($option['label'] ?? $value));
            if ($value !== '') {
                $out[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function normalizeConfig(string $type, mixed $config): array
    {
        if (! is_array($config)) {
            return [];
        }

        $out = [];
        foreach (['min', 'max', 'step', 'placeholder', 'max_length'] as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '') {
                $out[$key] = $config[$key];
            }
        }

        if (in_array($type, [StockCategoryFieldType::Number->value, StockCategoryFieldType::Range->value], true)) {
            $out['min'] = isset($out['min']) ? (float) $out['min'] : 0;
            $out['max'] = isset($out['max']) ? (float) $out['max'] : 100;
            $out['step'] = isset($out['step']) ? (float) $out['step'] : 1;
        }

        return $out;
    }

    private function isEmptyValue(StockCategoryFieldType $type, mixed $raw): bool
    {
        if ($type === StockCategoryFieldType::Checkbox) {
            return ! is_array($raw) || count(array_filter($raw, fn ($v) => $v !== null && $v !== '')) === 0;
        }

        return $raw === null || $raw === '' || (is_array($raw) && $raw === []);
    }

    private function castValue(StockCategoryField $field, StockCategoryFieldType $type, mixed $raw): mixed
    {
        return match ($type) {
            StockCategoryFieldType::Number => $this->castNumber($field, $raw),
            StockCategoryFieldType::Range  => $this->castNumber($field, $raw),
            StockCategoryFieldType::Color  => $this->castColor($raw),
            StockCategoryFieldType::Checkbox => $this->castCheckbox($field, $raw),
            StockCategoryFieldType::List, StockCategoryFieldType::Radio => $this->castChoice($field, $raw),
            default => trim((string) $raw),
        };
    }

    private function castNumber(StockCategoryField $field, mixed $raw): float
    {
        if (! is_numeric($raw)) {
            throw new \InvalidArgumentException("«{$field->label}» يجب أن يكون رقماً.");
        }

        $value = (float) $raw;
        $config = $field->config ?? [];
        $min = isset($config['min']) ? (float) $config['min'] : null;
        $max = isset($config['max']) ? (float) $config['max'] : null;

        if ($min !== null && $value < $min) {
            throw new \InvalidArgumentException("«{$field->label}» أقل من الحد الأدنى ({$min}).");
        }
        if ($max !== null && $value > $max) {
            throw new \InvalidArgumentException("«{$field->label}» أكبر من الحد الأقصى ({$max}).");
        }

        return $value;
    }

    private function castColor(mixed $raw): string
    {
        $value = trim((string) $raw);
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            throw new \InvalidArgumentException('صيغة اللون غير صالحة.');
        }

        return strtoupper($value);
    }

    /** @return list<string> */
    private function castCheckbox(StockCategoryField $field, mixed $raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $allowed = collect($field->options ?? [])->pluck('value')->all();
        $picked = [];

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if ($allowed && ! in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException("قيمة غير مسموحة في «{$field->label}».");
            }
            $picked[] = $value;
        }

        return array_values(array_unique($picked));
    }

    private function castChoice(StockCategoryField $field, mixed $raw): string
    {
        $value = trim((string) $raw);
        $allowed = collect($field->options ?? [])->pluck('value')->all();

        if ($allowed && ! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("اختيار غير صالح في «{$field->label}».");
        }

        return $value;
    }

    private function encodeValue(StockCategoryField $field, mixed $value): string
    {
        $type = StockCategoryFieldType::tryFrom($field->type) ?? StockCategoryFieldType::Text;

        if ($type === StockCategoryFieldType::Checkbox) {
            return json_encode(array_values((array) $value), JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /** @return list<array<string, mixed>> */
    public function formatItemAttributes(StockItem $item): array
    {
        $item->loadMissing(['attributeValues.field']);

        return $item->attributeValues
            ->sortBy(fn (StockItemAttributeValue $v) => $v->field?->sort_order ?? 0)
            ->map(function (StockItemAttributeValue $value) {
                $field = $value->field;
                if (! $field) {
                    return null;
                }

                $decoded = $this->decodeStoredValue($field, $value->value);

                return [
                    'field_key'      => $field->field_key,
                    'label'          => $field->label,
                    'type'           => $field->type,
                    'value'          => $decoded,
                    'display_value'  => $this->displayValue($field, $decoded),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** نص مختصر لعرض المعيار (حقول القسم الديناميكية) في التكاليف والعروض. */
    public function formatCriteriaSummary(StockItem $item): string
    {
        $parts = collect($this->formatItemAttributes($item))
            ->map(fn (array $row) => trim($row['label'] . ': ' . ($row['display_value'] ?? '—')))
            ->filter(fn (string $line) => ! str_ends_with($line, ': —'))
            ->values();

        return $parts->isNotEmpty() ? $parts->implode(' · ') : '—';
    }

    public function decodeStoredValue(StockCategoryField $field, ?string $stored): mixed
    {
        $type = StockCategoryFieldType::tryFrom($field->type) ?? StockCategoryFieldType::Text;

        if ($type === StockCategoryFieldType::Checkbox) {
            $decoded = json_decode((string) $stored, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (in_array($type, [StockCategoryFieldType::Number, StockCategoryFieldType::Range], true)) {
            return is_numeric($stored) ? (float) $stored : $stored;
        }

        return $stored;
    }

    public function displayValue(StockCategoryField $field, mixed $value): string
    {
        $type = StockCategoryFieldType::tryFrom($field->type) ?? StockCategoryFieldType::Text;

        if ($type === StockCategoryFieldType::Checkbox) {
            $values = array_values((array) $value);
            if (! $values) {
                return '—';
            }
            $labels = collect($field->options ?? [])->pluck('label', 'value');

            return collect($values)->map(fn ($v) => $labels[$v] ?? $v)->implode('، ');
        }

        if (in_array($type, [StockCategoryFieldType::List, StockCategoryFieldType::Radio], true)) {
            $labels = collect($field->options ?? [])->pluck('label', 'value');

            return (string) ($labels[(string) $value] ?? $value ?? '—');
        }

        if ($type === StockCategoryFieldType::Color) {
            return (string) $value;
        }

        return (string) ($value ?? '—');
    }

    private function mapUom(string $value): string
    {
        $map = [
            'piece'  => StockUom::Piece->value,
            'قطعة'   => StockUom::Piece->value,
            'meter'  => StockUom::Meter->value,
            'متر'    => StockUom::Meter->value,
            'kg'     => StockUom::Kilo->value,
            'kilo'   => StockUom::Kilo->value,
            'كilo'   => StockUom::Kilo->value,
            'كيلو'   => StockUom::Kilo->value,
            'gram'   => StockUom::Gram->value,
            'جرام'   => StockUom::Gram->value,
            'liter'  => StockUom::Liter->value,
            'لتر'    => StockUom::Liter->value,
        ];

        return $map[$value] ?? $value;
    }
}
