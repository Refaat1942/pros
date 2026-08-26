@include('shared.pages.catalog-item-entry', [
    'catalog_api_base' => url('/technical/catalog'),
    'catalog_success_redirect' => route('technical.add-catalog-item'),
])
