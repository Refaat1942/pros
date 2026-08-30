<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Services\DocumentTemplateService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DocumentTemplateSettingsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_super_admin_can_update_issue_voucher_template(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->putJson(route('admin.documents-hub.update', 'issue_voucher'), [
                'doc_title' => 'إذن صرف مخصص — {no}',
                'dept_label' => 'مخزن تجريبي',
                'signature_4' => 'أمين المخزن',
                'show_logo' => true,
                'compact_layout' => true,
            ])
            ->assertOk()
            ->assertJsonPath('values.doc_title', 'إذن صرف مخصص — {no}');

        $tpl = app(DocumentTemplateService::class)->for('issue_voucher');
        $this->assertSame('إذن صرف مخصص — {no}', $tpl['doc_title']);
        $this->assertSame('مخزن تجريبي', $tpl['dept_label']);
    }

    public function test_documents_hub_index_ok_without_custom_documents_table(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->get(route('admin.documents-hub'))
            ->assertOk()
            ->assertSee('مركز الوثائق', false)
            ->assertSee('عرض سعر', false);
    }

    public function test_preview_quote_uses_real_print_template(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->get(route('admin.documents-hub.preview', 'quote'))
            ->assertOk()
            ->assertSee('مريض تجريبي — معاينة القالب', false);
    }

    public function test_scoped_template_overrides_global_for_department(): void
    {
        Setting::updateOrCreate(
            ['key' => DocumentTemplateService::SETTING_KEY],
            ['value' => json_encode([
                'quote' => [
                    'doc_title' => 'عرض سعر عام',
                    '_scopes' => [
                        'cashier:*' => ['doc_title' => 'عرض سعر — الخزنة'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE)],
        );
        \Illuminate\Support\Facades\Cache::forget('settings.document_templates');

        $service = app(DocumentTemplateService::class);

        $this->assertSame('عرض سعر عام', $service->for('quote')['doc_title']);
        $this->assertSame('عرض سعر — الخزنة', $service->for('quote', 'cashier', null)['doc_title']);
        $this->assertSame('عرض سعر — الخزنة', $service->for('quote', 'cashier', 'cashier')['doc_title']);
    }

    public function test_scoped_template_update_persists_under_scope_key(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->putJson(route('admin.documents-hub.update', 'quote'), [
                'doc_title' => 'عرض خاص للاستقبال',
                'scope_department' => 'reception',
                'scope_stage' => '',
            ])
            ->assertOk()
            ->assertJsonPath('values.doc_title', 'عرض خاص للاستقبال');

        $global = app(DocumentTemplateService::class)->for('quote');
        $scoped = app(DocumentTemplateService::class)->for('quote', 'reception', null);

        $this->assertNotSame('عرض خاص للاستقبال', $global['doc_title']);
        $this->assertSame('عرض خاص للاستقبال', $scoped['doc_title']);
    }

    public function test_preview_issue_voucher_uses_custom_title(): void
    {
        Setting::updateOrCreate(
            ['key' => DocumentTemplateService::SETTING_KEY],
            ['value' => json_encode([
                'issue_voucher' => ['doc_title' => 'صرف مواد — {no}'],
            ], JSON_UNESCAPED_UNICODE)],
        );
        \Illuminate\Support\Facades\Cache::forget('settings.document_templates');

        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->get(route('admin.documents-hub.preview', 'issue_voucher'))
            ->assertOk()
            ->assertSee('صرف مواد — DEMO-001', false);
    }

    public function test_preview_quote_and_work_order_return_ok(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->get(route('admin.documents-hub.preview', 'quote'))
            ->assertOk()
            ->assertSee('عرض سعر', false);

        $this->actingAs($super)
            ->get(route('admin.documents-hub.preview', 'work_order'))
            ->assertOk()
            ->assertSee('إذن شغل', false);
    }

    public function test_super_admin_can_create_custom_document_with_preview(): void
    {
        $super = $this->userWithRole('super_admin');

        $response = $this->actingAs($super)
            ->postJson(route('admin.documents-hub.custom.store'), [
                'title' => 'خطاب موافقة',
                'group_label' => 'وثائق مخصصة',
                'description' => 'نموذج تجريبي',
                'body_html' => '<p>محتوى تجريبي للوثيقة</p>',
            ]);

        $response->assertCreated();
        $key = $response->json('document.key');
        $this->assertStringStartsWith('custom_', $key);

        $this->actingAs($super)
            ->get(route('admin.documents-hub.preview', $key))
            ->assertOk()
            ->assertSee('محتوى تجريبي للوثيقة', false);
    }
}
