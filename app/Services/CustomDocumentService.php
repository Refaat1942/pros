<?php

namespace App\Services;

use App\Models\CustomDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomDocumentService
{
  /** @return list<array{key: string, label: string, type: string, help?: string}> */
    public static function fieldDefinitions(): array
    {
        return [
            ['key' => 'doc_title', 'label' => 'عنوان الوثيقة', 'type' => 'text'],
            ['key' => 'dept_label', 'label' => 'اسم القسم في الترويسة', 'type' => 'text'],
            ['key' => 'subtitle', 'label' => 'سطر توضيحي تحت العنوان', 'type' => 'text'],
            ['key' => 'footer_note', 'label' => 'ملاحظة أسفل الوثيقة', 'type' => 'textarea'],
            ['key' => 'signature_1', 'label' => 'توقيع 1', 'type' => 'text'],
            ['key' => 'signature_2', 'label' => 'توقيع 2', 'type' => 'text'],
            ['key' => 'show_logo', 'label' => 'إظهار الشعار', 'type' => 'bool'],
            ['key' => 'show_seal', 'label' => 'إظهار الختم', 'type' => 'bool'],
            ['key' => 'compact_layout', 'label' => 'تنسيق مضغوط (ورقة واحدة)', 'type' => 'bool'],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultTemplateValues(string $title): array
    {
        return [
            'doc_title' => $title,
            'dept_label' => 'مركز الأطراف الصناعية',
            'subtitle' => '',
            'footer_note' => '',
            'signature_1' => 'مسؤول',
            'signature_2' => 'يعتمد',
            'show_logo' => true,
            'show_seal' => true,
            'compact_layout' => true,
            'font_scale' => 'compact',
        ];
    }

    /**
     * @param  array{title: string, group_label: string, description?: string, body_html?: string}  $data
     */
    public function create(array $data, User $actor, ?UploadedFile $reference = null): CustomDocument
    {
        return DB::transaction(function () use ($data, $actor, $reference) {
            $title = trim($data['title']);
            $key = $this->uniqueKey($title);

            $doc = CustomDocument::create([
                'key' => $key,
                'group_label' => trim($data['group_label']),
                'title' => $title,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'body_html' => trim((string) ($data['body_html'] ?? '')) ?: null,
                'template_values' => self::defaultTemplateValues($title),
                'created_by_user_id' => $actor->id,
            ]);

            if ($reference) {
                $this->storeReference($doc, $reference);
            }

            AuditService::log(
                action: 'create',
                description: "إنشاء وثيقة مخصصة: {$doc->title}",
                tag: 'admin',
                after: $doc->only(['id', 'key', 'title']),
            );

            return $doc->fresh();
        });
    }

    public function updateContent(CustomDocument $doc, array $payload): CustomDocument
    {
        $before = $doc->only(['title', 'description', 'body_html', 'template_values']);

        $template = array_merge($doc->template_values ?? [], $payload['template'] ?? []);
        $doc->fill([
            'title' => trim((string) ($payload['title'] ?? $doc->title)),
            'group_label' => trim((string) ($payload['group_label'] ?? $doc->group_label)),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'body_html' => trim((string) ($payload['body_html'] ?? '')) ?: null,
            'template_values' => $template,
        ])->save();

        AuditService::log(
            action: 'update',
            description: "تعديل وثيقة مخصصة: {$doc->title}",
            tag: 'admin',
            before: $before,
            after: $doc->only(['title', 'description', 'body_html', 'template_values']),
        );

        return $doc->fresh();
    }

    public function storeReference(CustomDocument $doc, UploadedFile $file): CustomDocument
    {
        if ($doc->reference_path) {
            $this->deleteReferenceFile($doc->reference_path);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = $doc->key.'-'.now()->format('YmdHis').'.'.$ext;
        $path = $file->storeAs('document-templates', $filename, 'public');

        $doc->update(['reference_path' => 'storage/'.$path]);

        return $doc->fresh();
    }

    public function delete(CustomDocument $doc): void
    {
        DB::transaction(function () use ($doc) {
            if ($doc->reference_path) {
                $this->deleteReferenceFile($doc->reference_path);
            }

            AuditService::log(
                action: 'delete',
                description: "حذف وثيقة مخصصة: {$doc->title}",
                tag: 'admin',
                before: $doc->only(['id', 'key', 'title']),
            );

            $doc->delete();
        });
    }

    public function findByKey(string $key): ?CustomDocument
    {
        return CustomDocument::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();
    }

    /** @return list<CustomDocument> */
    public function activeList(): array
    {
        return CustomDocument::query()
            ->where('is_active', true)
            ->orderBy('group_label')
            ->orderBy('title')
            ->get()
            ->all();
    }

    private function uniqueKey(string $title): string
    {
        $base = 'custom_'.Str::slug($title, '_');
        if ($base === 'custom_') {
            $base = 'custom_doc';
        }

        $key = Str::limit($base, 48, '');
        $suffix = 0;

        while (CustomDocument::query()->where('key', $key)->exists()
            || is_array(config("document_templates.definitions.{$key}"))) {
            $suffix++;
            $key = Str::limit($base, 44, '').'_'.$suffix;
        }

        return $key;
    }

    private function deleteReferenceFile(string $path): void
    {
        if (str_starts_with($path, 'storage/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }
    }
}
