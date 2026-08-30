@include('shared.pages.catalog-item-entry', [
    'catalog_api_base' => url('/admin/catalog'),
    'catalog_success_redirect' => route('admin.add-catalog-item'),
])
