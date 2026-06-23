@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@php
    use App\Support\CaseDisplayStatus;
    $requests = $spec_pricing_requests ?? collect();
    $inPricing = $requests->filter(fn ($r) => in_array($r->caseRecord?->stage_key, [\App\Models\CaseRecord::STAGE_COST_CALC, \App\Models\CaseRecord::STAGE_QUOTE, \App\Models\CaseRecord::STAGE_OPERATIONS], true))->count();
    $inProduction = $requests->filter(fn ($r) => in_array($r->caseRecord?->stage_key, [\App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::STAGE_READY_DELIVERY], true))->count();
@endphp

<div id="analytics-pricing">
    @include('partials.dashboard-analytics-empty', ['stats' => $spec_pricing_stats ?? []])
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <div>
            <h3 class="font-bold text-slate-800">💰 طلبات مرسلة للتسعير</h3>
            <p class="text-xs text-slate-500 mt-1">🔒 لا تظهر أي مبالغ — الحالة فقط</p>
        </div>
        <span class="text-xs font-bold bg-amber-500 text-white px-3 py-1 rounded-full">{{ $requests->count() }}</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-5 border-b border-slate-100">
        <div class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs text-slate-500">إجمالي الطلبات</p>
            <p class="text-2xl font-bold text-slate-800">{{ $requests->count() }}</p>
        </div>
        <div class="rounded-xl bg-amber-50 p-4">
            <p class="text-xs text-amber-700">في التسعير / الاعتماد</p>
            <p class="text-2xl font-bold text-amber-700">{{ $inPricing }}</p>
        </div>
        <div class="rounded-xl bg-cyan-50 p-4">
            <p class="text-xs text-cyan-700">في الإنتاج</p>
            <p class="text-2xl font-bold text-cyan-700">{{ $inProduction }}</p>
        </div>
    </div>

    <div class="p-4 border-b border-slate-100">
        <input type="search" id="pricingSearch" placeholder="بحث برقم الطلب أو اسم المريض..."
               class="w-full max-w-md rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
    </div>

    <div class="overflow-x-auto">
        <table data-paginate="10" class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3 text-right font-bold">#</th>
                    <th class="px-4 py-3 text-right font-bold">رقم الطلب</th>
                    <th class="px-4 py-3 text-right font-bold">المريض</th>
                    <th class="px-4 py-3 text-right font-bold">التاريخ</th>
                    <th class="px-4 py-3 text-right font-bold">البنود</th>
                    <th class="px-4 py-3 text-right font-bold">الحالة</th>
                </tr>
            </thead>
            <tbody id="pricingTable" class="divide-y divide-slate-100">
                @forelse ($requests as $pr)
                    @php
                        $display = CaseDisplayStatus::forPricingRequest($pr);
                        $specBadge = \App\Enums\CaseStage::specBadgeFor($pr->caseRecord?->stage_key);
                    @endphp
                    <tr data-search="{{ $pr->request_no }} {{ $pr->patient_name }}">
                        <td class="px-4 py-3">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-mono text-xs font-bold">{{ $pr->request_no }}</td>
                        <td class="px-4 py-3">{{ $pr->patient_name }}</td>
                        <td class="px-4 py-3">{{ $pr->request_date?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-center">{{ $pr->items_count }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-lg text-xs font-bold {{ $specBadge['class'] }}">
                                {{ $display->label }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">لا توجد طلبات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
  var search = document.getElementById('pricingSearch');
  var table = document.getElementById('pricingTable');
  if (!search || !table) return;
  search.addEventListener('input', function() {
    var q = search.value.trim().toLowerCase();
    table.querySelectorAll('tr[data-search]').forEach(function(row) {
      row.style.display = !q || row.dataset.search.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
    });
  });
})();
</script>
