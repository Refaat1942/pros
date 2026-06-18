  <!-- Pricing Detail Modal -->
  <div class="modal-overlay" id="pricingModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="pricingModalTitle">🧾 تفاصيل طلب التسعير</h3>
        <button type="button" class="modal-close" id="closePricingModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="pricing-detail-meta" id="pricingModalMeta"></div>
        <div class="pricing-detail-steps-wrap">
          <h4>📊 مسار التسعير</h4>
          <div id="pricingModalSteps"></div>
        </div>
        <div class="pricing-detail-items">
          <h4>📦 الأصناف المطلوبة</h4>
          <div class="stock-table-wrap">
            <table class="stock-table">
              <thead>
                <tr>
                  <th>الصنف</th>
                  <th>الكود</th>
                  <th>الفئة</th>
                  <th class="col-qty">الكمية</th>
                  <th class="col-qty">المتاح</th>
                  <th class="col-status">التوفر</th>
                </tr>
              </thead>
              <tbody id="pricingModalItems"></tbody>
            </table>
          </div>
        </div>
        <div class="pricing-detail-note">
          📋 الطلب يظهر للإدارة للاعتماد — بعد الموافقة يُرسل تلقائياً للاستقبال.
        </div>
        <div style="margin-top:20px;display:flex;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnClosePricingModal">إغلاق</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>