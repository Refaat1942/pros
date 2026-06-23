  {{-- إرجاع للتعديل من مكتب التشغيل --}}
  <div class="modal-overlay" id="reworkModal">
    <div class="modal" style="max-width:480px;">
      <div class="modal-header">
        <h3>↩️ إرجاع للتعديل</h3>
        <button type="button" class="modal-close" id="closeReworkModal">&times;</button>
      </div>
      <div class="modal-body">
        <p style="margin:0 0 14px;font-size:13px;color:var(--text-muted);" id="reworkCaseLabel"></p>
        <div class="form-group">
          <label>إعادة الحالة إلى</label>
          <select class="form-control" id="reworkTarget">
            <option value="adjustments">المعدلات الفنية</option>
            <option value="technical">التوصيف الفني</option>
          </select>
        </div>
        <div class="form-group">
          <label>سبب الإرجاع (اختياري)</label>
          <textarea class="form-control" id="reworkReason" rows="3" placeholder="ملاحظات للفريق..."></textarea>
        </div>
        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelRework">إلغاء</button>
          <button type="button" class="btn-action" id="btnSubmitRework" style="background:#fee2e2;color:#b91c1c;">↩️ تأكيد الإرجاع</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast" aria-live="polite"></div>
