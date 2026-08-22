<div class="panel">
    <div class="panel-header">
        <h3>📍 تتبع أوامر الشغل — قسم الإنتاج</h3>
        <button type="button" class="btn-action" id="btnRefreshWorkshopTracking">↻ تحديث</button>
    </div>
    <p class="text-muted" style="padding:0 16px 8px;">
        طابور <strong>تخصيص الإنتاج</strong> (قبل صرف المخزن) و<strong>تحت التشغيل</strong> (بعد الصرف) — متوافق مع مسار اعتماد التخصيص ثم الصرف.
    </p>
    <div class="data-toolbar">
        <select id="trackingQueueFilter" class="form-control" style="max-width:240px;">
            <option value="assignment">👷 طابور التخصيص — قبل الصرف</option>
            <option value="wip">🏭 تحت التشغيل — بعد الصرف</option>
        </select>
        <select id="trackingSectionFilter" class="form-control" style="max-width:220px;">
            <option value="">كل الأقسام</option>
            @foreach ($workshop_sections ?? [] as $section)
                <option value="{{ $section['id'] ?? $section->id ?? '' }}">{{ $section['name'] ?? $section->name ?? '' }}</option>
            @endforeach
        </select>
        <span class="toolbar-count" id="workshopTrackingSummary">—</span>
    </div>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>الحالة</th>
                    <th>المريض</th>
                    <th>WO</th>
                    <th>القسم</th>
                    <th>الفني</th>
                    <th id="trackingPhaseCol">حالة التخصيص</th>
                    <th>% إنجاز</th>
                    <th>آخر تحديث</th>
                </tr>
            </thead>
            <tbody id="workshopTrackingTable"></tbody>
        </table>
    </div>
</div>
