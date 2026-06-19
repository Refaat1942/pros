@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@php
    $specs = $submitted_specs ?? collect();
@endphp

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
        <h3 class="font-bold text-slate-800">👁️ معاينة التوصيفات المُرسَلة</h3>
        <p class="text-xs text-slate-500 mt-1">قراءة فقط — بدون صرف مخزني</p>
    </div>
    <div class="divide-y divide-slate-100">
        @forelse ($specs as $spec)
            <details class="group px-5 py-4">
                <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                    <div>
                        <p class="font-bold text-slate-800">{{ $spec->patient_name }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $spec->order_ref }} · {{ $spec->submitted_at }}</p>
                    </div>
                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-lg">مُرسَل</span>
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
            </details>
        @empty
            <p class="px-5 py-10 text-center text-slate-400">لا توجد توصيفات مُرسَلة بعد.</p>
        @endforelse
    </div>
</div>
