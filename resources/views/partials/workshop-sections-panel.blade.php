@php
    $technicians = $workshop_technicians ?? [];
    $sections = $workshop_sections ?? [];
    $technicianCount = count($technicians);
    $sectionCount = count($sections);
@endphp

<div class="space-y-4" id="workshopSectionsPage">
    {{-- تبويبات --}}
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-1">
        <button type="button" data-ws-tab="sections"
                class="ws-tab-btn active rounded-t-xl px-5 py-2.5 text-sm font-bold border border-b-0 border-slate-200 bg-white text-violet-900 -mb-px">
            🏭 الأقسام <span class="text-xs font-normal text-slate-500">({{ $sectionCount }})</span>
        </button>
        <button type="button" data-ws-tab="technicians"
                class="ws-tab-btn rounded-t-xl px-5 py-2.5 text-sm font-bold border border-transparent text-slate-600 hover:text-violet-800 hover:bg-slate-50">
            👷 الفنيون <span class="text-xs font-normal text-slate-500">({{ $technicianCount }})</span>
        </button>
    </div>

    {{-- ═══ تبويب الأقسام ═══ --}}
    <div id="wsTabSections" class="ws-tab-panel space-y-4">
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm leading-relaxed text-violet-950">
            <p class="font-extrabold mb-1">🏭 إدارة أقسام الإنتاج</p>
            <p>أنشئ الأقسام وعدّلها أو احذفها. ربط الفنيين بالأقسام يتم من تبويب <strong>«الفنيون»</strong>.</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
                <div>
                    <h3 class="font-bold text-slate-800 text-lg">أقسام الإنتاج</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $sectionCount }} قسم</p>
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
                            <th class="px-4 py-3 text-right font-bold">الوصف</th>
                            <th class="px-4 py-3 text-right font-bold">الحالة</th>
                            <th class="px-4 py-3 text-right font-bold whitespace-nowrap">إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="workshopSectionsTable" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ تبويب الفنيون ═══ --}}
    <div id="wsTabTechnicians" class="ws-tab-panel hidden space-y-4">
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm leading-relaxed text-violet-950">
            <p class="font-extrabold mb-1">👷 إدارة فنيي الإنتاج</p>
            <p>أضف فنيين جدد أو عدّل بياناتهم واربطهم بالأقسام المناسبة.</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
                <div>
                    <h3 class="font-bold text-slate-800 text-lg">فنيو الإنتاج</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $technicianCount }} فني</p>
                </div>
                <button type="button" id="btnAddWorkshopTechnician"
                        class="rounded-xl bg-emerald-600 text-white px-5 py-2.5 text-sm font-bold hover:bg-emerald-700 shadow-sm">
                    ➕ إضافة فني
                </button>
            </div>

            <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center">
                <input type="text" id="workshopTechnicianSearch" placeholder="🔍 بحث بالاسم أو اسم المستخدم..."
                       class="flex-1 min-w-[240px] rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400/40">
                <span class="text-sm font-bold text-slate-500" id="workshopTechnicianCount">{{ $technicianCount }} فني</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-paginate="10">
                    <thead class="bg-slate-100 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-right font-bold">الاسم</th>
                            <th class="px-4 py-3 text-right font-bold">اسم المستخدم</th>
                            <th class="px-4 py-3 text-right font-bold">الأقسام</th>
                            <th class="px-4 py-3 text-right font-bold">الحالة</th>
                            <th class="px-4 py-3 text-right font-bold whitespace-nowrap">إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="workshopTechniciansTable" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- مودال القسم --}}
<div class="catalog-modal-overlay" id="workshopSectionModal" role="dialog" aria-modal="true" aria-labelledby="workshopSectionModalTitle">
    <div class="catalog-modal" style="max-width:520px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="workshopSectionModalTitle">➕ قسم إنتاج جديد</h3>
                <p class="text-xs text-slate-500 mt-1" id="workshopSectionModalHint">أدخل بيانات القسم.</p>
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
            <label class="form-check-row" for="workshopSectionActive">
                <input type="checkbox" id="workshopSectionActive" checked>
                <span>القسم نشط</span>
            </label>
            <div id="workshopSectionError" style="display:none;color:#dc2626;margin-top:12px;font-size:13px;padding:10px;background:#fee2e2;border-radius:8px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelWorkshopSectionModal">إلغاء</button>
            <button type="button" class="btn-action success" id="saveWorkshopSectionBtn">💾 حفظ القسم</button>
        </div>
    </div>
</div>

{{-- مودال الفني --}}
<div class="catalog-modal-overlay" id="workshopTechnicianModal" role="dialog" aria-modal="true" aria-labelledby="workshopTechnicianModalTitle">
    <div class="catalog-modal" style="max-width:560px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="workshopTechnicianModalTitle">➕ فني جديد</h3>
                <p class="text-xs text-slate-500 mt-1" id="workshopTechnicianModalHint">أدخل بيانات الفني واربطه بالأقسام.</p>
            </div>
            <button type="button" class="catalog-modal-close" id="closeWorkshopTechnicianModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="workshopTechnicianId">
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopTechnicianName">الاسم <span style="color:#dc2626">*</span></label>
                <input type="text" id="workshopTechnicianName" class="form-control" maxlength="255" placeholder="مثال: أحمد محمد">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopTechnicianUsername">اسم المستخدم <span style="color:#dc2626">*</span></label>
                <input type="text" id="workshopTechnicianUsername" class="form-control" maxlength="50" placeholder="ahmed_m" dir="ltr">
                <small style="display:block;margin-top:4px;color:#64748b;font-size:12px;">حروف إنجليزية وأرقام و _ و - فقط</small>
            </div>
            <div class="form-group" style="margin-bottom:14px;" id="workshopTechnicianPasswordGroup">
                <label for="workshopTechnicianPassword">كلمة المرور <span style="color:#dc2626" id="workshopTechnicianPasswordRequired">*</span></label>
                <input type="password" id="workshopTechnicianPassword" class="form-control" minlength="6" placeholder="6 أحرف على الأقل">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label for="workshopTechnicianSections">🏭 ربط بالأقسام</label>
                <select id="workshopTechnicianSections" class="form-control" multiple size="6"></select>
                <small style="display:block;margin-top:6px;color:#64748b;font-size:12px;">
                    اضغط <strong>Ctrl</strong> (أو <strong>Cmd</strong> على Mac) لاختيار أكثر من قسم.
                    @if ($sectionCount === 0)
                        <br>لا توجد أقسام — أنشئ قسماً أولاً من تبويب «الأقسام».
                    @endif
                </small>
            </div>
            <label class="form-check-row" for="workshopTechnicianActive">
                <input type="checkbox" id="workshopTechnicianActive" checked>
                <span>الفني نشط</span>
            </label>
            <div id="workshopTechnicianError" style="display:none;color:#dc2626;margin-top:12px;font-size:13px;padding:10px;background:#fee2e2;border-radius:8px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelWorkshopTechnicianModal">إلغاء</button>
            <button type="button" class="btn-action success" id="saveWorkshopTechnicianBtn">💾 حفظ الفني</button>
        </div>
    </div>
</div>

<script>
window.__WORKSHOP_SECTIONS = @json($sections);
window.__WORKSHOP_TECHNICIANS = @json($technicians);
window.__WORKSHOP_SECTIONS_API = @json($workshop_sections_api ?? '/admin/workshop-sections');
window.__WORKSHOP_TECHNICIANS_API = @json($workshop_technicians_api ?? '/admin/workshop-technicians');
</script>
