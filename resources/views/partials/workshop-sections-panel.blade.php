@php
    $employeesUrl = ($sections_employees_url ?? null) ?: route('admin.employees');
    $showAdminEmployeeLink = $show_admin_employee_link ?? true;
    $technicians = $workshop_technicians ?? [];
    $sections = $workshop_sections ?? [];
    $technicianCount = count($technicians);
    $sectionCount = count($sections);
@endphp

<div class="space-y-4" id="workshopSectionsPage">
    {{-- دليل سريع --}}
    <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm leading-relaxed text-violet-950">
        <p class="font-extrabold mb-2">📌 كيف تدير الأقسام والفنيين؟</p>
        <ol class="list-decimal list-inside space-y-1.5 mr-1">
            <li><strong>الأقسام</strong> — الجدول أدناه. اضغط الزر الأخضر <strong>«➕ إضافة قسم»</strong> لإنشاء قسم جديد.</li>
            <li><strong>ربط الفنيين</strong> — اضغط <strong>«✏️ تعديل / ربط فنيين»</strong> على أي صف واختر الفنيين من القائمة ثم احفظ.</li>
            @if ($showAdminEmployeeLink)
                <li><strong>إضافة فني جديد للنظام</strong> — من لوحة الإدارة → <a href="{{ $employeesUrl }}" class="font-bold underline text-violet-800">الموظفون</a> → دور <strong>«قسم الإنتاج»</strong>، ثم ارجع هنا واربطه بالقسم.</li>
            @else
                <li><strong>إضافة فني جديد</strong> — تواصل مع الإدارة لتسجيل موظف بدور «قسم الإنتاج»، ثم اربطه من زر التعديل.</li>
            @endif
        </ol>
    </div>

    @if ($technicianCount === 0)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            <strong>⚠️ لا يوجد فنيون مسجّلون بدور «قسم الإنتاج».</strong>
            <p class="mt-2">
                @if ($showAdminEmployeeLink)
                    أضف موظفاً من <a href="{{ $employeesUrl }}" class="font-bold underline">الموظفون</a> أولاً، ثم ارجع لربطهم بالأقسام.
                @else
                    اطلب من الإدارة إضافة موظفين بدور «قسم الإنتاج» قبل ربطهم بالأقسام.
                @endif
            </p>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
            <div>
                <h3 class="font-bold text-slate-800 text-lg">🏭 أقسام الإنتاج</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ $sectionCount }} قسم · {{ $technicianCount }} فني متاح للربط</p>
            </div>
            <button type="button" id="btnAddWorkshopSection"
                    class="rounded-xl bg-emerald-600 text-white px-5 py-2.5 text-sm font-bold hover:bg-emerald-700 shadow-sm">
                ➕ إضافة قسم
            </button>
        </div>

        <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center">
            <input type="text" id="workshopSectionSearch" placeholder="🔍 بحث باسم القسم أو الكود..."
                   class="flex-1 min-w-[240px] rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400/40">
            <span class="text-sm font-bold text-slate-500" id="workshopSectionCount">{{ $sectionCount }} قسم</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" data-paginate="10">
                <thead class="bg-slate-100 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-bold">القسم</th>
                        <th class="px-4 py-3 text-right font-bold">الكود</th>
                        <th class="px-4 py-3 text-right font-bold">الفنيون المربوطون</th>
                        <th class="px-4 py-3 text-right font-bold">الحالة</th>
                        <th class="px-4 py-3 text-right font-bold whitespace-nowrap">إجراء</th>
                    </tr>
                </thead>
                <tbody id="workshopSectionsTable" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="workshopSectionModal" role="dialog" aria-modal="true" aria-labelledby="workshopSectionModalTitle">
    <div class="catalog-modal" style="max-width:560px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="workshopSectionModalTitle">➕ قسم إنتاج جديد</h3>
                <p class="text-xs text-slate-500 mt-1" id="workshopSectionModalHint">أدخل بيانات القسم واختر الفنيين المسؤولين عنه.</p>
            </div>
            <button type="button" class="catalog-modal-close" id="closeWorkshopSectionModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="workshopSectionId">
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionName">اسم القسم <span style="color:#dc2626">*</span></label>
                <input type="text" id="workshopSectionName" class="form-control" maxlength="100" placeholder="مثال: تركيب المفاصل">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionCode">الكود (اختياري)</label>
                <input type="text" id="workshopSectionCode" class="form-control" maxlength="50" placeholder="JOINTS" dir="ltr">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionDescription">الوصف</label>
                <textarea id="workshopSectionDescription" class="form-control" rows="2" placeholder="وصف مختصر..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopSectionTechnicians">👷 ربط الفنيين بالقسم</label>
                <select id="workshopSectionTechnicians" class="form-control" multiple size="6"></select>
                <small style="display:block;margin-top:6px;color:#64748b;font-size:12px;line-height:1.6;">
                    اضغط <strong>Ctrl</strong> (أو <strong>Cmd</strong> على Mac) لاختيار أكثر من فني.
                    @if ($technicianCount === 0)
                        @if ($showAdminEmployeeLink)
                            <br>القائمة فارغة — <a href="{{ $employeesUrl }}">أضف موظفاً بدور «قسم الإنتاج»</a> من الإدارة.
                        @else
                            <br>لا يوجد فنيون — تواصل مع الإدارة لإضافتهم.
                        @endif
                    @endif
                </small>
            </div>
            <label class="form-check-row" for="workshopSectionActive">
                <input type="checkbox" id="workshopSectionActive" checked>
                <span>القسم نشط</span>
            </label>
            <div id="workshopSectionError" style="display:none;color:#dc2626;margin-top:12px;font-size:13px;padding:10px;background:#fee2e2;border-radius:8px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelWorkshopSectionModal">إلغاء</button>
            <button type="button" class="btn-action success" id="saveWorkshopSectionBtn">💾 حفظ القسم والفنيين</button>
        </div>
    </div>
</div>

<script>
window.__WORKSHOP_SECTIONS = @json($sections);
window.__WORKSHOP_TECHNICIANS = @json($technicians);
window.__WORKSHOP_SECTIONS_API = @json($workshop_sections_api ?? '/admin/workshop-sections');
</script>
