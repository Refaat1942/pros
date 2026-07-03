<div class="record-modal-overlay" id="doctorExamModal" role="dialog" aria-modal="true" aria-labelledby="doctorExamModalTitle" hidden>
    <div class="record-modal" style="max-width:720px;" onclick="event.stopPropagation()">
        <div class="record-modal-header">
            <div>
                <h3 id="doctorExamModalTitle">📝 التشخيص الطبي</h3>
                <div class="modal-meta" id="doctorExamModalMeta">—</div>
            </div>
            <button type="button" class="record-modal-close" id="doctorExamModalClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="record-modal-body">
            <div class="silent-clinic-note" id="doctorExamSilentNote" style="display:none;margin-bottom:14px;">
                🪖 <span>مريض عسكري — <strong>عيادة صامتة</strong>: يُسجَّل الكشف ويتخطّى النظام عرض السعر والتحصيل.</span>
            </div>
            <form id="doctorExamForm" data-validate-form>
                <input type="hidden" name="lock" value="1">
                <input type="hidden" name="patient_id" id="doctorExamPatientId" value="">
                <input type="hidden" name="appointment_id" id="doctorExamAppointmentId" value="">
                <input type="hidden" name="medical_record_id" id="doctorExamRecordId" value="">

                <div class="form-group">
                    <label>التشخيص الدقيق <span class="required">*</span></label>
                    <textarea class="form-control" name="diagnosis" id="doctorExamDiagnosis"
                              data-v-rules="required,min:3,max:5000" maxlength="5000"
                              placeholder="أدخل التشخيص الطبي التفصيلي..." required></textarea>
                </div>

                <div class="form-group">
                    <label>الروشتة الطبية</label>
                    <textarea class="form-control" name="prescription" id="doctorExamPrescription"
                              data-v-rules="max:5000" maxlength="5000"
                              placeholder="الأدوية والإرشادات الطبية (اختياري)..."></textarea>
                </div>
            </form>
            <p id="doctorExamError" style="display:none;margin:12px 0 0;font-size:13px;color:#b91c1c;"></p>
        </div>
        <div class="record-modal-footer doctor-exam-modal-footer">
            <button type="button" class="btn-action primary" id="doctorExamSave">💾 حفظ وتحويل للتوصيف</button>
            @can('skip-diagnosis')
                <button type="button" class="btn-action skip-outline" id="doctorExamSkip" style="display:none;">⏭️ تخطّي الكشف</button>
            @endcan
            <button type="button" class="btn-close-modal" id="doctorExamCancel">إلغاء</button>
        </div>
    </div>
</div>
