<?php

namespace Tests\Feature\Admin;

use App\Services\DocumentTemplateService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * الفاتورة في مركز الوثائق + توقيعات إضافية قابلة للإضافة في أي وثيقة.
 */
class DocumentSignaturesAndInvoiceTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_documents_hub_lists_the_invoice(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.documents-hub'))
            ->assertOk()
            ->assertSee('فاتورة', false)
            ->assertSee('/admin/documents-hub/invoice/edit', false);
    }

    public function test_every_document_has_the_extra_signatures_field_and_preview_opens(): void
    {
        $super = $this->userWithRole('super_admin');
        $service = app(DocumentTemplateService::class);

        foreach (array_keys($service->catalog()) as $key) {
            $keys = array_column($service->definition($key)['fields'], 'key');
            $this->assertContains(DocumentTemplateService::SIGNATURES_KEY, $keys, "{$key} has extra signatures");

            // صفحة الخطأ تُرجع 200 أيضاً — نتأكد أن القالب نفسه اشتغل.
            $this->actingAs($super)
                ->get(route('admin.documents-hub.preview', $key))
                ->assertOk()
                ->assertDontSee('تعذّر المعاينة', false);

            $this->actingAs($super)
                ->get("/admin/documents-hub/{$key}/edit")
                ->assertOk()
                ->assertSee('➕ إضافة توقيع', false);
        }
    }

    public function test_saved_extra_signatures_print_on_each_document(): void
    {
        $super = $this->userWithRole('super_admin');

        foreach (['quote', 'invoice', 'payment_receipt', 'issue_voucher', 'work_order', 'spec_print', 'supply_request_list'] as $key) {
            $this->actingAs($super)
                ->putJson(route('admin.documents-hub.update', $key), [
                    DocumentTemplateService::SIGNATURES_KEY => [
                        ['title' => 'مدير المركز — '.$key, 'name' => 'لواء / أحمد'],
                        ['title' => 'المراجع المالي', 'name' => ''],
                        ['title' => '', 'name' => ''],
                    ],
                ])
                ->assertOk()
                ->assertJsonCount(2, 'values.'.DocumentTemplateService::SIGNATURES_KEY);

            $this->actingAs($super)
                ->get(route('admin.documents-hub.preview', $key))
                ->assertOk()
                ->assertSee('مدير المركز — '.$key, false)
                ->assertSee('لواء / أحمد', false)
                ->assertSee('المراجع المالي', false);
        }
    }

    public function test_extra_signatures_are_limited_and_validated(): void
    {
        $super = $this->userWithRole('super_admin');
        $rows = array_fill(0, DocumentTemplateService::MAX_EXTRA_SIGNATURES + 1, ['title' => 'توقيع', 'name' => '']);

        $this->actingAs($super)
            ->putJson(route('admin.documents-hub.update', 'quote'), [DocumentTemplateService::SIGNATURES_KEY => $rows])
            ->assertUnprocessable();
    }

    public function test_invoice_template_is_separate_from_quote(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->putJson(route('admin.documents-hub.update', 'invoice'), ['doc_title' => 'فاتورة ضريبية مبسطة'])
            ->assertOk();

        $this->actingAs($super)
            ->get(route('admin.documents-hub.preview', 'invoice'))
            ->assertOk()
            ->assertSee('فاتورة ضريبية مبسطة', false)
            ->assertSee('رقم الفاتورة', false);

        $this->actingAs($super)
            ->get(route('admin.documents-hub.preview', 'quote'))
            ->assertOk()
            ->assertDontSee('فاتورة ضريبية مبسطة', false);
    }
}
