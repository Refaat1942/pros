@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          recv: { DEFAULT: '#059669', dark: '#047857', light: '#ecfdf5' }
        }
      }
    }
  }
</script>
@endpush

@php
    $cases = $delivery_cases ?? collect();
@endphp

<div id="analytics-delivery">
    @include('partials.dashboard-analytics-empty', ['stats' => $delivery_stats ?? []])
</div>

<div class="space-y-6" id="deliveryRoot">
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
        <p class="text-sm text-emerald-900 leading-relaxed">
            الحالات الظاهرة هنا اكتمل تصنيعها (<strong>BOM تام</strong>) وهي <strong>جاهزة للتسليم</strong>.
            امسح <strong>QR بطاقة المريض</strong> لتأكيد التسليم وإغلاق الحالة نهائياً.
        </p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        {{-- قائمة جاهزة للتسليم --}}
        <div class="xl:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-slate-800">✅ جاهز للتسليم</h3>
                <span class="text-xs font-bold bg-recv text-white px-3 py-1 rounded-full" id="deliveryCount">{{ $cases->count() }}</span>
            </div>
            <div class="p-4 border-b border-slate-100">
                <input type="search" id="deliverySearch" placeholder="🔍 بحث بالمريض أو WO..."
                       class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-recv/40">
            </div>
            <ul class="max-h-[480px] overflow-y-auto divide-y divide-slate-100" id="deliveryList" data-paginate="10">
                @forelse ($cases as $case)
                    <li class="delivery-item cursor-pointer px-5 py-4 hover:bg-emerald-50 transition-colors"
                        data-case-id="{{ $case->id }}"
                        data-patient-qr="{{ $case->patient?->patient_qr }}"
                        data-search="{{ $case->patient?->name }} {{ $case->work_order_no }} {{ $case->case_no }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-slate-800">{{ $case->patient?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-500 mt-1">{{ $case->case_no }} · {{ $case->work_order_no ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $case->company_name ?? '—' }}</p>
                            </div>
                            <span class="text-[11px] font-bold px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700">BOM تام</span>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-slate-400 text-sm">لا توجد حالات جاهزة للتسليم.</li>
                @endforelse
            </ul>
        </div>

        {{-- مسح QR --}}
        <div class="xl:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-l from-emerald-600 to-teal-600 text-white">
                <h3 class="font-bold text-lg">📱 مسح QR — التسليم الختامي</h3>
                <p class="text-sm opacity-90 mt-1">يُغلق الملف الطبي ويُصدر مرجع الفاتورة (مدني)</p>
            </div>

            <div id="deliveryEmpty" class="p-10 text-center text-slate-400">
                <div class="text-5xl mb-3">📦</div>
                <p>اختر حالة من القائمة أو امسح QR مباشرة</p>
            </div>

            <div id="deliveryWorkspace" class="hidden p-6 space-y-5">
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                    <h4 class="font-bold text-slate-800 text-lg" id="delPatientName">—</h4>
                    <div class="grid grid-cols-2 gap-3 mt-3 text-sm text-slate-600">
                        <div>الحالة: <strong id="delCaseNo" class="text-slate-800">—</strong></div>
                        <div>WO: <strong id="delWorkOrder" class="font-mono text-emerald-700">—</strong></div>
                        <div>الجهة: <strong id="delCompany">—</strong></div>
                        <div>BOM: <strong id="delBomStage" class="text-emerald-700">—</strong></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">رمز QR لبطاقة المريض</label>
                    <div class="flex gap-2">
                        <input type="text" id="deliveryQrInput" placeholder="امسح أو أدخل QR..."
                               data-v-rules="required,qr" maxlength="100"
                               class="flex-1 rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-recv/50">
                        <button type="button" id="btnConfirmDelivery"
                                class="rounded-xl bg-recv text-white px-6 py-3 text-sm font-bold hover:bg-recv-dark shadow-sm disabled:opacity-40">
                            ✓ تأكيد التسليم
                        </button>
                    </div>
                </div>

                <div id="deliveryError" class="hidden rounded-xl border-2 border-red-400 bg-red-50 p-4 text-red-800 text-sm font-bold"></div>
            </div>
        </div>
    </div>
</div>

{{-- Success modal --}}
<div id="deliverySuccessModal" class="fixed inset-0 z-[200] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" id="deliverySuccessBackdrop"></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-emerald-200 overflow-hidden text-center p-8">
            <div class="text-5xl mb-4">🎉</div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">تم التسليم وإغلاق الحالة</h3>
            <p class="text-sm text-slate-600 mb-4" id="deliverySuccessText">—</p>
            <p class="text-xs font-mono text-emerald-700 bg-emerald-50 rounded-lg py-2 px-4" id="deliveryInvoiceRef"></p>
            <button type="button" id="btnCloseDeliverySuccess"
                    class="mt-6 rounded-xl bg-slate-800 text-white px-8 py-2.5 text-sm font-bold">حسناً</button>
        </div>
    </div>
</div>

<div id="deliveryToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[300] hidden rounded-xl px-6 py-3 text-sm font-bold shadow-lg"></div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
@endpush
