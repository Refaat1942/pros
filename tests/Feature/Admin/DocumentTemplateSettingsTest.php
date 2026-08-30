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
