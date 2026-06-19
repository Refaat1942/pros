  <div class="modal-overlay" id="fittingModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="fittingModalTitle">📏 تسجيل تجربة تركيب</h3>
        <button type="button" class="modal-close" id="closeFittingModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>تاريخ التجربة الأولى</label>
          <input type="date" class="form-control" id="fittingTrial1"
                 data-v-rules="date,dateFuture" max="{{ now()->toDateString() }}">
        </div>
        <div class="form-group">
          <label>تاريخ التجربة الثانية</label>
          <input type="date" class="form-control" id="fittingTrial2"
                 data-v-rules="date,dateFuture" max="{{ now()->toDateString() }}">
        </div>
        <div class="form-group">
          <label>ملاحظات المقاسات والتعديلات</label>
          <textarea class="form-control" id="fittingNotes" rows="4"
                    data-v-rules="max:2000" maxlength="2000"></textarea>
        </div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelFitting">إلغاء</button>
          <button type="button" class="btn-action success" id="btnSaveFitting">حفظ</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>