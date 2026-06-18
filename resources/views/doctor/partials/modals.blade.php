  <div class="toast" id="toast"></div>

  <div class="record-modal-overlay" id="recordDetailModal" role="dialog" aria-modal="true" aria-labelledby="recordModalTitle">
    <div class="record-modal" onclick="event.stopPropagation()">
      <div class="record-modal-header">
        <div>
          <h3 id="recordModalTitle">—</h3>
          <div class="modal-meta" id="recordModalMeta">—</div>
        </div>
        <button type="button" class="record-modal-close" id="recordModalClose" aria-label="إغلاق">&times;</button>
      </div>
      <div class="record-modal-body" id="recordModalBody"></div>
      <div class="record-modal-footer">
        <button type="button" class="btn-close-modal" id="recordModalCloseBtn">إغلاق</button>
      </div>
    </div>
  </div>