<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DocumentsHubController extends Controller
{
  public function index(): View
  {
    return view('admin.pages.documents-hub', [
      'documents' => $this->documentCatalog(),
    ]);
  }

  /** @return list<array{group: string, items: list<array{title: string, description: string, print_url: ?string, edit_url: ?string, sample_path: ?string}>}> */
  private function documentCatalog(): array
  {
    return [
      [
        'group' => 'المالية والاستقبال',
        'items' => [
          [
            'title' => 'عرض سعر',
            'description' => 'طباعة عرض السعر للمريض / الجهة',
            'print_url' => null,
            'edit_url' => url('/admin/branding-settings'),
            'sample_path' => 'quotes/print',
          ],
          [
            'title' => 'إيصال دفع',
            'description' => 'إيصال الخزنة بعد التحصيل',
            'print_url' => null,
            'edit_url' => url('/admin/branding-settings'),
            'sample_path' => 'prints/payment-receipt',
          ],
        ],
      ],
      [
        'group' => 'المخزن والتوريد',
        'items' => [
          [
            'title' => 'إذن صرف مواد',
            'description' => 'صرف BOM للورشة — من المخزن',
            'print_url' => null,
            'edit_url' => url('/admin/branding-settings'),
            'sample_path' => 'prints/issue-voucher',
          ],
          [
            'title' => 'طلبات التوريد المفتوحة',
            'description' => 'قائمة طلبات التوريد مع تاريخ الطلب والاستلام',
            'print_url' => route('admin.supply.requests.print'),
            'edit_url' => null,
            'sample_path' => 'prints/supply-request-list',
          ],
          [
            'title' => 'ملصقات الباركود',
            'description' => 'إعدادات مقاس الملصق وكثافة الباركود',
            'print_url' => route('admin.catalog.labels.bulk'),
            'edit_url' => route('admin.catalog'),
            'sample_path' => 'admin/print/barcode-labels',
          ],
        ],
      ],
      [
        'group' => 'الإنتاج والتصنيع',
        'items' => [
          [
            'title' => 'إذن شغل',
            'description' => 'أمر التشغيل لقسم الإنتاج',
            'print_url' => null,
            'edit_url' => url('/admin/branding-settings'),
            'sample_path' => 'prints/work-order',
          ],
          [
            'title' => 'تقرير التوصيف',
            'description' => 'طباعة التوصيف الفني',
            'print_url' => null,
            'edit_url' => url('/admin/branding-settings'),
            'sample_path' => 'spec/print',
          ],
        ],
      ],
      [
        'group' => 'إعدادات عامة للوثائق',
        'items' => [
          [
            'title' => 'ترويسة المؤسسة والشعار',
            'description' => 'يظهر في كل الوثائق المطبوعة',
            'print_url' => null,
            'edit_url' => url('/admin/branding-settings'),
            'sample_path' => null,
          ],
          [
            'title' => 'حقول النماذج',
            'description' => 'سياسات الحقول في شاشات الإدخال',
            'print_url' => null,
            'edit_url' => url('/admin/form-field-settings'),
            'sample_path' => null,
          ],
        ],
      ],
    ];
  }
}
