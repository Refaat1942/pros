<div class="panel">
    <div class="panel-header">
        <h3>🪖 تصديقات إدارة الخدمات</h3>
        <button type="button" class="btn-action" id="btnRefreshServicesApprovals">↻ تحديث</button>
    </div>
    <p class="text-muted" style="padding:0 16px 8px;">
        ضباط / مدنيين / عائلات — بدون تكلفة على المريض — قبل إصدار أمر الشغل.
    </p>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>الحالة</th>
                    <th>المريض</th>
                    <th>التصنيف</th>
                    <th>التاريخ</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="servicesApprovalsTable"></tbody>
        </table>
    </div>
</div>

<script>
window.__BENEFICIARY_LABELS = @json($beneficiary_labels ?? []);
</script>
