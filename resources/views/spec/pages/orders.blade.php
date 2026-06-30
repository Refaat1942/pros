@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          spec: { DEFAULT: '#d97706', dark: '#b45309', light: '#fef3c7' }
        }
      }
    }
  }
</script>
@endpush

@php
    $cases = $spec_cases ?? collect();
    $dateFrom = $spec_orders_date_from ?? request()->query('from');
    $dateTo = $spec_orders_date_to ?? request()->query('to');
    $ordersSearch = $spec_orders_search ?? request()->query('search');
    $exportUrl = route('spec.orders.export', array_filter([
        'from'   => $dateFrom,
        'to'     => $dateTo,
        'search' => $ordersSearch ?: null,
    ]));
@endphp

<div id="analytics-orders">
    @include('partials.dashboard-analytics-empty', ['stats' => $spec_stats ?? [], 'hide_charts' => true])
</div>

<form id="specOrdersFilter" action="{{ route('spec.orders') }}" method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <label class="flex flex-col gap-1 text-sm font-semibold text-slate-700">
        <span>من</span>
        <input type="date" id="ordersDateFrom" name="from" value="{{ $dateFrom }}"
               class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-spec/40">
    </label>
    <label class="flex flex-col gap-1 text-sm font-semibold text-slate-700">
        <span>إلى</span>
        <input type="date" id="ordersDateTo" name="to" value="{{ $dateTo }}"
               class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-spec/40">
    </label>
    <label class="flex flex-col gap-1 text-sm font-semibold text-slate-700 flex-1 min-w-[200px]">
        <span>بحث</span>
        <input type="search" id="ordersFilterSearch" name="search" value="{{ $ordersSearch }}"
               placeholder="اسم المريض أو رقم الحالة..."
               class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-spec/40">
    </label>
    <button type="submit" class="rounded-xl bg-spec hover:bg-spec-dark text-white text-sm font-bold px-5 py-2.5 transition-colors">
        تطبيق الفلتر
    </button>
    @if ($dateFrom || $dateTo || $ordersSearch)
        <a href="{{ route('spec.orders') }}" class="rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold px-5 py-2.5 hover:bg-slate-50">
            مسح الفلتر
        </a>
    @endif
    <div class="flex flex-wrap gap-2 mr-auto">
        <a href="{{ $exportUrl }}" class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm font-bold px-4 py-2.5 hover:bg-emerald-100" download>
            📊 Excel
        </a>
        <button type="button" id="btnExportOrdersPdf"
                class="rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold px-4 py-2.5 hover:bg-slate-50">
            📄 PDF
        </button>
    </div>
</form>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-6" id="specOrdersRoot"
     data-cases-count="{{ $cases->count() }}">
    {{-- قائمة الحالات --}}
    <div class="xl:col-span-4 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-bold text-slate-800 text-base">📥 طلبات التوصيف الفني</h3>
            <span class="text-xs font-bold bg-spec text-white px-3 py-1 rounded-full" id="ordersCount">{{ $cases->count() }}</span>
        </div>
        <div class="p-4 border-b border-slate-100">
            <input type="search" id="ordersSearch" placeholder="🔍 بحث بالمريض أو رقم الحالة..."
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-spec/40">
        </div>
        <ul class="max-h-[520px] overflow-y-auto divide-y divide-slate-100" id="ordersList" data-paginate="10">
            @forelse ($cases as $case)
                <li class="order-item cursor-pointer px-5 py-4 hover:bg-amber-50 transition-colors"
                    data-case-id="{{ $case->id }}"
                    data-transfer-date="{{ $case->created_at?->toDateString() }}"
                    data-search="{{ $case->patient?->name }} {{ $case->case_no }} {{ $case->order_ref }} {{ $case->displayEntity() }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-bold text-slate-800">{{ $case->patient?->name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $case->case_no }} · {{ $case->order_ref }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $case->displayEntity() }}</p>
                        </div>
                        <span class="text-[11px] font-semibold px-2 py-1 rounded-lg {{ $case->patient_type === 'military' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}">
                            {{ $case->patient_type === 'military' ? '🪖 عسكري' : '🌐 مدني' }}
                        </span>
                    </div>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-slate-400 text-sm">لا توجد حالات بانتظار التوصيف الفني.</li>
            @endforelse
        </ul>
    </div>

    {{-- نموذج التوصيف --}}
    <div class="xl:col-span-8 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
            <h3 class="font-bold text-slate-800 text-base">📐 التوصيف الفني — أكواد وكميات</h3>
            <p class="text-xs text-slate-500 mt-1">🔒 عمى مالي ومخزني — لا تظهر الأسعار ولا أرصدة المخزون</p>
        </div>

        <div id="emptyState" class="p-10 text-center text-slate-400">
            <div class="text-4xl mb-3">📋</div>
            <p>اختر حالة من القائمة لبدء التوصيف الفني</p>
        </div>

        <div id="specWorkspace" class="hidden p-5 space-y-5">
            <div class="rounded-xl bg-gradient-to-l from-amber-500 to-orange-500 text-white p-4">
                <h4 class="font-bold text-lg" id="bannerName">—</h4>
                <div class="flex flex-wrap gap-4 text-sm mt-2 opacity-95">
                    <span>الحالة: <strong id="bannerCaseNo">—</strong></span>
                    <span>الطلب: <strong id="bannerOrderRef">—</strong></span>
                    <span>الطبيب: <strong id="bannerDoctor">—</strong></span>
                    <span>الجهة: <strong id="bannerCompany">—</strong></span>
                </div>
            </div>

            <div id="medicalSummary" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <p class="font-bold text-slate-700 mb-2">🩺 ملخص الكشف الطبي</p>
                <p class="text-slate-600"><span class="font-semibold">التشخيص:</span> <span id="medDiagnosis">—</span></p>
                <p class="text-slate-600 mt-1"><span class="font-semibold">الروشتة:</span> <span id="medPrescription">—</span></p>
            </div>

            <div id="specReworkBanner" class="hidden rounded-xl border border-red-200 bg-red-50 p-4 text-sm">
                <p class="font-bold text-red-800" id="specReworkTitle">↩️ إرجاع من مكتب التشغيل</p>
                <p class="text-red-600/80 text-xs mt-1" id="specReworkMeta"></p>
                <p class="text-red-900 mt-2 whitespace-pre-wrap leading-relaxed" id="specReworkReason"></p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h5 class="font-bold text-slate-800">📦 بنود التوصيف</h5>
                <button type="button" id="btnAddCatalogItem"
                        class="inline-flex items-center gap-2 rounded-xl bg-spec hover:bg-spec-dark text-white text-sm font-bold px-4 py-2 transition-colors">
                    ➕ إضافة صنف
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table data-no-paginate class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-right font-bold">الكود</th>
                            <th class="px-4 py-3 text-right font-bold">الصنف</th>
                            <th class="px-4 py-3 text-right font-bold">الوحدة</th>
                            <th class="px-4 py-3 text-right font-bold w-28">الكمية</th>
                            <th class="px-4 py-3 text-center font-bold w-20">حذف</th>
                        </tr>
                    </thead>
                    <tbody id="specItemsBody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">ملاحظات فنية (اختياري)</label>
                <textarea id="techNotes" rows="3" placeholder="ملاحظات التوصيف..."
                          data-v-rules="max:5000" maxlength="5000"
                          class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-spec/40"></textarea>
            </div>

            <div id="specFormError" class="hidden rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"></div>

            <div class="flex flex-wrap gap-3 pt-2 items-center">
                <button type="button" id="btnSubmitSpec"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-emerald-600 text-white font-bold px-6 py-3 transition-colors">
                    💾 حفظ
                </button>
                <p id="specSubmittedBanner" class="hidden text-sm font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                    <span id="specSubmittedBannerText">✅ تم الاعتماد</span><span id="specSubmittedRequestNo" class="font-mono"></span>
                </p>
            </div>
        </div>
    </div>
</div>

{{-- Modal: اختيار صنف --}}
<div id="catalogModal" class="hidden fixed inset-0 z-[200] bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">🔍 اختيار صنف من الكاتلوج</h3>
            <button type="button" id="closeCatalogModal" class="text-2xl text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <div class="p-4 border-b border-slate-100">
            <input type="search" id="catalogSearch" placeholder="بحث بالكود أو الاسم..."
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-spec/40">
        </div>
        <div class="overflow-y-auto flex-1 p-2" id="catalogList"></div>
    </div>
</div>

<script>
window.__SPEC_ORDERS_EXPORT = @json($spec_orders_export ?? []);
</script>
