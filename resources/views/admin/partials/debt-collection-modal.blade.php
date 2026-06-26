{{-- سجل دفعات التحصيل — مدني + عسكري --}}
<div id="debtCollectionModal"
     class="debt-collection-modal-overlay"
     style="display:none;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="debtCollectionModalTitle">
    <div class="debt-collection-modal" onclick="event.stopPropagation()">
        <div class="debt-collection-modal__header">
            <div>
                <h3 id="debtCollectionModalTitle">📋 تفاصيل التحصيل</h3>
                <p id="debtCollectionModalSubtitle" class="debt-collection-modal__subtitle"></p>
            </div>
            <button type="button" class="debt-collection-modal__close" id="btnCloseDebtCollection" aria-label="إغلاق">&times;</button>
        </div>
        <div class="debt-collection-modal__summary" id="debtCollectionModalSummary"></div>
        <div class="debt-collection-modal__body" id="debtCollectionModalBody"></div>
        <div class="debt-collection-modal__footer catalog-modal-footer">
            <button type="button" class="btn-action primary" id="btnDebtCollectionModalClose">إغلاق</button>
        </div>
    </div>
</div>
