@php
    use App\Services\SpecEditRequestService;

    $specs = collect($submitted_specs ?? []);
    $stats = $spec_edit_stats ?? ['total' => 0, 'editable' => 0, 'pending' => 0];
    $editService = app(SpecEditRequestService::class);
@endphp

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" id="specPreviewRoot">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-bold text-slate-800">👁️ معاينة التوصيفات المُرسَلة</h3>
            <p class="text-xs text-slate-500 mt-1">يمكن طلب تعديل التوصيف أثناء وجود الحالة في المعدلات — يتطلب موافقة الإدارة</p>
        </div>
        <div class="flex gap-2 text-xs">
            <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700">{{ $stats['total'] ?? 0 }} مُرسَل</span>
            <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700">{{ $stats['editable'] ?? 0 }} قابل للتعديل</span>
            <span class="px-2 py-1 rounded-lg bg-amber-50 text-amber-700">{{ $stats['pending'] ?? 0 }} طلب معلّق</span>
        </div>
    </div>
    <div class="divide-y divide-slate-100">
        @forelse ($specs as $spec)
            @php
                $canEdit = $editService->canRequestEdit($spec);
                $pending = $spec->pendingEditRequest;
                $stage = $spec->caseRecord?->stage_key;
            @endphp
            <details class="group px-5 py-4" data-spec-id="{{ $spec->id }}">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                    <div>
                        <p class="font-bold text-slate-800">{{ $spec->patient_name }}</p>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $spec->order_ref }}
                            · {{ $spec->caseRecord?->case_no }}
                            · {{ $spec->updated_at?->format('d/m/Y H:i') ?? $spec->submitted_at?->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        @if ($pending)
                            <span class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-1 rounded-lg">⏳ طلب تعديل معلّق</span>
                        @elseif ($canEdit)
                            <span class="text-xs font-semibold text-violet-700 bg-violet-50 px-2 py-1 rounded-lg">✏️ قابل للتعديل</span>
                        @else
                            <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-lg">مُرسَل</span>
                        @endif
                        @if ($canEdit)
                            <button type="button"
                                class="spec-edit-open-btn text-xs font-bold px-3 py-1.5 rounded-lg bg-violet-600 text-white hover:bg-violet-700"
                                data-spec-id="{{ $spec->id }}">
                                ✏️ طلب تعديل
                            </button>
                        @endif
                        <a href="{{ route('spec.spec.print', $spec) }}?embed=1"
                           target="_blank"
                           rel="noopener"
                           class="text-xs font-bold px-3 py-1.5 rounded-lg bg-slate-700 text-white hover:bg-slate-800 no-underline inline-block"
                           onclick="event.stopPropagation();">
                            🖨️ طباعة
                        </a>
                    </div>
                </summary>
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-100">
                    <table data-paginate="10" class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-right">الكود</th>
                                <th class="px-3 py-2 text-right">الصنف</th>
                                <th class="px-3 py-2 text-right">الكمية</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($spec->items as $item)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $item->stock_item_code }}</td>
                                    <td class="px-3 py-2">{{ $item->name }}</td>
                                    <td class="px-3 py-2">{{ $item->qty }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($spec->tech_notes)
                    <p class="mt-3 text-sm text-slate-600"><strong>ملاحظات:</strong> {{ $spec->tech_notes }}</p>
                @endif
                @if ($stage && $stage !== 'adjustments')
                    <p class="mt-2 text-xs text-slate-500">المرحلة الحالية: {{ $stage }} — التعديل غير متاح بعد تجاوز المعدلات.</p>
                @endif
            </details>
        @empty
            <p class="px-5 py-10 text-center text-slate-400">لا توجد توصيفات مُرسَلة بعد.</p>
        @endforelse
    </div>
</div>

<div id="specEditModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h4 class="font-bold text-slate-800" id="specEditModalTitle">✏️ طلب تعديل التوصيف</h4>
                <p class="text-xs text-slate-500 mt-1" id="specEditModalMeta"></p>
            </div>
            <button type="button" id="specEditModalClose" class="text-slate-400 hover:text-slate-700 text-xl font-bold">×</button>
        </div>
        <div class="p-5 overflow-y-auto flex-1 space-y-4">
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
                التعديل لا يُطبَّق مباشرة — يُرسل للإدارة للموافقة أو الرفض مع إشعار لك بالنتيجة.
            </p>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">ملاحظات فنية</label>
                <textarea id="specEditNotes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold text-slate-600">البنود المقترحة</label>
                    <button type="button" id="specEditAddItem" class="text-xs font-bold text-violet-700 hover:text-violet-900">+ إضافة صنف</button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-right">الكود</th>
                                <th class="px-3 py-2 text-right">الصنف</th>
                                <th class="px-3 py-2 text-right">الكمية</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody id="specEditItemsBody"></tbody>
                    </table>
                </div>
            </div>
            <p id="specEditError" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3"></p>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" id="specEditCancel" class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-600">إلغاء</button>
            <button type="button" id="specEditSubmit" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-bold hover:bg-violet-700">📤 إرسال للإدارة</button>
        </div>
    </div>
</div>

<div id="specEditCatalogModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h5 class="font-bold text-slate-800">اختر صنفاً</h5>
            <button type="button" id="specEditCatalogClose" class="text-slate-400 text-xl">×</button>
        </div>
        <div class="p-4">
            <input type="search" id="specEditCatalogSearch" placeholder="بحث..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm mb-3">
        </div>
        <div id="specEditCatalogList" class="overflow-y-auto px-4 pb-4 flex-1"></div>
    </div>
</div>
