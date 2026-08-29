@push('styles')
@include('partials.dashboard-tailwind')
@endpush

@push('tailwind-theme')
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          workshop: { DEFAULT: '#7c3aed', dark: '#6d28d9', light: '#f5f3ff' }
        }
      }
    }
  }
</script>
@endpush

@php
    $cases = $workshop_cases ?? collect();
    $assignmentCases = $workshop_assignment_cases ?? collect();
@endphp

<div id="analytics-workshop">
    @include('partials.dashboard-analytics-empty', ['stats' => $workshop_stats ?? [], 'hide_charts' => true])
</div>

<div class="space-y-6" id="workshopDeskRoot" data-cases-count="{{ $cases->count() }}">
    <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden" id="workshopAssignmentQueuePanel">
        <div class="px-5 py-4 border-b border-amber-100 flex flex-wrap items-center justify-between gap-3 bg-amber-50">
            <div>
                <h3 class="font-bold text-amber-900 text-base">📋 طابور تخصيص الإنتاج — قبل صرف المخزن</h3>
                <p class="text-xs text-amber-800 mt-1">أوامر الشغل بعد اعتماد التشغيل — خصّص القسم والفني ثم اعتمد التخصيص ليتاح للمخزن الصرف.</p>
            </div>
            <button type="button" id="btnRefreshAssignmentQueue"
                    class="rounded-xl bg-amber-600 text-white px-4 py-2 text-sm font-bold hover:bg-amber-700 transition-colors">
                ↻ تحديث الطابور
            </button>
        </div>
        <div class="overflow-x-auto">
            <table id="workshopAssignmentTable" data-paginate="8" class="w-full text-sm">
                <thead class="bg-amber-50/80 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">أمر التشغيل</th>
                        <th class="px-4 py-3 text-right font-bold">المريض</th>
                        <th class="px-4 py-3 text-right font-bold">المسار</th>
                        <th class="px-4 py-3 text-right font-bold">القسم / الفني</th>
                        <th class="px-4 py-3 text-right font-bold">حالة التخصيص</th>
                        <th class="px-4 py-3 text-right font-bold">إجراء</th>
                    </tr>
                </thead>
                <tbody id="workshopAssignmentTableBody" class="divide-y divide-slate-100">
                    @forelse ($assignmentCases as $case)
                        @php
                            $isMil = $case->isMilitary();
                            $voucherUrl = route('workshop.work-order.print', $case);
                            $awaitingApprove = $case->workshop_section_id && $case->assigned_technician_id && ! $case->isWorkshopAssignmentApproved();
                        @endphp
                        <tr class="assignment-row hover:bg-slate-50" data-case-id="{{ $case->id }}"
                            data-search="{{ $case->work_order_no }} {{ $case->case_no }} {{ $case->patient?->name }}">
                            <td class="px-4 py-3 font-mono font-bold text-amber-800">{{ $case->work_order_no ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $case->patient?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $case->case_no }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold px-2 py-1 rounded-lg {{ $isMil ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $isMil ? '🪖 عسكري' : '🌐 مدني' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="font-semibold text-slate-700">{{ $case->workshopSection?->name ?? '—' }}</div>
                                <div class="text-slate-400 mt-0.5">{{ $case->assignedTechnician?->name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($case->isWorkshopAssignmentApproved())
                                    <span class="text-xs font-bold px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700">✓ معتمد — جاهز للصرف</span>
                                @elseif ($awaitingApprove)
                                    <span class="text-xs font-bold px-2 py-1 rounded-lg bg-amber-100 text-amber-800">بانتظار الاعتماد</span>
                                @else
                                    <span class="text-xs font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">غير مخصّص</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ $voucherUrl }}" target="_blank" rel="noopener"
                                   class="text-xs font-bold rounded-lg border border-violet-700 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block mb-1">🖨️ إذن شغل</a>
                                @if ($awaitingApprove)
                                    <button type="button" class="btn-approve-assignment text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700 inline-block mb-1" data-case-id="{{ $case->id }}">✓ اعتماد</button>
                                @endif
                                <button type="button" class="btn-select-workshop-case text-xs font-bold rounded-lg border border-violet-300 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block"
                                        data-case-id="{{ $case->id }}" data-work-order="{{ $case->work_order_no ?? '' }}">👤 تخصيص</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر بانتظار التخصيص — تظهر بعد اعتماد مكتب التشغيل.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-violet-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-violet-100 bg-violet-50">
            <h3 class="font-bold text-violet-900 text-base">👤 تخصيص الفني وقسم الإنتاج</h3>
            <p class="text-xs text-violet-700 mt-1">اختر أمر الشغل من الطابور ثم حدّد القسم والفني. «حفظ التخصيص» للتجربة فقط — «حفظ واعتماد التخصيص» يُرسل للمخزن.</p>
        </div>
        <div class="p-4 flex flex-wrap gap-4 items-end">
            <div class="min-w-[200px]">
                <label class="block text-xs font-bold text-slate-600 mb-1">أمر الشغل المحدد</label>
                <input type="text" id="workshopSelectedOrder" readonly placeholder="— اختر من الجدول —"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-mono">
            </div>
            <div class="min-w-[200px]">
                <label class="block text-xs font-bold text-slate-600 mb-1">قسم الإنتاج</label>
                <select id="workshopAssignSection" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">— بدون —</option>
                </select>
            </div>
            <div class="min-w-[200px]">
                <label class="block text-xs font-bold text-slate-600 mb-1">الفني</label>
                <select id="workshopAssignTechnician" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">— بدون —</option>
                </select>
            </div>
            <button type="button" id="btnSaveWorkshopAssignment"
                    class="rounded-xl bg-violet-600 text-white px-5 py-2.5 text-sm font-bold hover:bg-violet-700 transition-colors">
                حفظ التخصيص
            </button>
            <button type="button" id="btnApproveWorkshopAssignment"
                    class="rounded-xl bg-emerald-600 text-white px-5 py-2.5 text-sm font-bold hover:bg-emerald-700 transition-colors">
                ✓ حفظ واعتماد التخصيص
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-indigo-200 shadow-sm overflow-hidden" id="workshopTechnicianBoard">
        <div class="px-5 py-4 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-3 bg-indigo-50">
            <div>
                <h3 class="font-bold text-indigo-900 text-base">👷 تتبع الفنيين — أوامر وإنجاز</h3>
                <p class="text-xs text-indigo-700 mt-1">كل فني معه إيه، ونسبة الإنجاز لكل أمر — تتحدّث تلقائياً عند التخصيص أو تحديث الإنجاز.</p>
            </div>
            <button type="button" id="btnRefreshTechBoard"
                    class="rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-bold hover:bg-indigo-700 transition-colors">
                ↻ تحديث التتبع
            </button>
        </div>
        <div class="p-4">
            <div id="workshopTechBoardCards" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <p class="text-sm text-slate-400 col-span-full text-center py-8">جاري تحميل تتبع الفنيين...</p>
            </div>
            <div id="workshopUnassignedPanel" class="mt-4 hidden">
                <h4 class="text-sm font-bold text-amber-800 mb-2">⏳ أوامر بدون فني</h4>
                <div id="workshopUnassignedList" class="space-y-2"></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
            <h3 class="font-bold text-slate-800">🏭 طابور قسم الإنتاج</h3>
            <button type="button" id="btnRefreshWorkshop"
                    class="rounded-xl bg-workshop text-white px-4 py-2 text-sm font-bold hover:bg-workshop-dark transition-colors">
                ↻ تحديث
            </button>
        </div>

        <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center">
            <input type="search" id="workshopSearch" placeholder="🔍 بحث WO / مريض / حالة..."
                   class="flex-1 min-w-[280px] rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-workshop/40">
            <div class="flex flex-wrap gap-2" id="workshopFilters">
                <button type="button" class="workshop-filter active rounded-full px-4 py-1.5 text-xs font-bold bg-slate-800 text-white" data-filter="all">الكل</button>
                <button type="button" class="workshop-filter rounded-full px-4 py-1.5 text-xs font-bold bg-indigo-100 text-indigo-700" data-filter="military">🪖 عسكري</button>
                <button type="button" class="workshop-filter rounded-full px-4 py-1.5 text-xs font-bold bg-emerald-100 text-emerald-700" data-filter="civilian">🌐 مدني</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="workshopDeskTable" data-paginate="10" class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">أمر التشغيل</th>
                        <th class="px-4 py-3 text-right font-bold">المريض</th>
                        <th class="px-4 py-3 text-right font-bold">المسار</th>
                        <th class="px-4 py-3 text-right font-bold">مرحلة التصنيع</th>
                        <th class="px-4 py-3 text-right font-bold">القسم / الفني</th>
                        <th class="px-4 py-3 text-right font-bold">عدد الأصناف</th>
                        <th class="px-4 py-3 text-right font-bold">إجراء</th>
                    </tr>
                </thead>
                <tbody id="workshopTableBody" class="divide-y divide-slate-100">
                    @forelse ($cases as $case)
                        @php
                            $itemsCount = $case->bom?->items?->isNotEmpty()
                                ? \App\Support\BomItemAggregator::uniqueCodeCount($case->bom->items)
                                : 0;
                            $isMil = $case->isMilitary();
                            $mfgLabel = \App\Enums\ManufacturingStage::workshopDeskLabelFor($case->manufacturing_stage);
                        @endphp
                        <tr class="workshop-row hover:bg-slate-50" data-case-id="{{ $case->id }}"
                            data-search="{{ $case->work_order_no }} {{ $case->case_no }} {{ $case->patient?->name }}"
                            data-path="{{ $isMil ? 'military' : 'civilian' }}"
                            data-filter-hidden="0">
                            <td class="px-4 py-3 font-mono font-bold text-workshop">{{ $case->work_order_no ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $case->patient?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $case->case_no }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold px-2 py-1 rounded-lg {{ $isMil ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $isMil ? '🪖 عسكري' : '🌐 مدني' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold px-2 py-1 rounded-lg bg-cyan-100 text-cyan-800">{{ $mfgLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="font-semibold text-slate-700">{{ $case->workshopSection?->name ?? '—' }}</div>
                                <div class="text-slate-400 mt-0.5">{{ $case->assignedTechnician?->name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($itemsCount > 0)
                                    <button type="button"
                                            class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50"
                                            data-case-id="{{ $case->id }}"
                                            data-patient="{{ $case->patient?->name ?? '—' }}"
                                            data-case-no="{{ $case->case_no }}"
                                            data-work-order="{{ $case->work_order_no ?? '—' }}"
                                            data-items='@json(\App\Support\BomItemAggregator::byStockCode($case->bom->items))'>
                                        عرض
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('workshop.work-order.print', $case) }}" target="_blank" rel="noopener"
                                   class="text-xs font-bold rounded-lg border border-violet-700 text-violet-800 px-3 py-1.5 hover:bg-violet-50 inline-block mb-1">
                                    🖨️ طباعة إذن شغل
                                </a>
                                <button type="button" class="btn-complete-manufacturing text-xs font-bold rounded-lg bg-emerald-600 text-white px-3 py-1.5 hover:bg-emerald-700"
                                        data-case-id="{{ $case->id }}">
                                    ✓ تم التصنيع
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">لا توجد أوامر في قسم الإنتاج حالياً — تظهر بعد صرف المواد من المخزن.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="workshopBomItemsModal" class="hidden fixed inset-0 z-[200] bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 md:p-8">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-slate-800">📦 بنود أمر التشغيل</h3>
                <p class="text-sm text-slate-500 mt-1" id="workshopBomItemsSubtitle">—</p>
            </div>
            <button type="button" id="closeWorkshopBomItemsModal" class="text-3xl leading-none text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <div class="overflow-y-auto flex-1 p-5 md:p-6">
            <table class="w-full text-base">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">الكود</th>
                        <th class="px-4 py-3 text-right font-bold">الصنف</th>
                        <th class="px-4 py-3 text-right font-bold w-28">الكمية</th>
                    </tr>
                </thead>
                <tbody id="workshopBomItemsBody" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.__WORKSHOP_ASSIGNMENT_QUEUE = @json($workshop_assignment_payload ?? []);
window.__WORKSHOP_ASSIGNMENT_SECTIONS = @json($workshop_assignment_sections ?? []);
</script>
@endpush
