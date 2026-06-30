@php
    $returnRowActions = $return_row_actions ?? [];
@endphp
@if ($returnRowActions !== [])
    <script>
        window.__REPORTS_RETURN_ITEMS = @json($returnRowActions, JSON_UNESCAPED_UNICODE);
    </script>

    <div id="reportsReturnItemsModal" class="admin-return-modal-overlay" style="display:none;">
        <div class="admin-return-modal" onclick="event.stopPropagation()">
            <div class="admin-return-modal-header">
                <div>
                    <h3 id="reportsReturnItemsTitle">↩️ أصناف الارتجاع</h3>
                    <p id="reportsReturnItemsSubtitle" class="modal-subtitle"></p>
                </div>
                <button type="button" class="modal-close" id="btnCloseReportsReturnItems" aria-label="إغلاق">&times;</button>
            </div>
            <div id="reportsReturnItemsBody" class="admin-return-modal-body"></div>
            <div class="admin-return-modal-footer">
                <button type="button" class="btn-action primary" id="btnReportsReturnItemsClose">إغلاق</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var items = window.__REPORTS_RETURN_ITEMS || [];
            var modal = document.getElementById('reportsReturnItemsModal');
            var title = document.getElementById('reportsReturnItemsTitle');
            var subtitle = document.getElementById('reportsReturnItemsSubtitle');
            var body = document.getElementById('reportsReturnItemsBody');

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function closeModal() {
                if (modal) modal.style.display = 'none';
            }

            window.openReportsReturnItems = function (index) {
                var note = items[index];
                if (!note || !modal) return;

                if (title) title.textContent = '↩️ ' + (note.return_no || 'طلب ارتجاع');
                if (subtitle) {
                    subtitle.textContent = [
                        note.patient_name || '—',
                        note.work_order_no ? 'أمر ' + note.work_order_no : '',
                        note.warehouse_received_at ? 'استلام المخزن: ' + note.warehouse_received_at : '',
                    ].filter(Boolean).join(' · ');
                }

                var lines = Array.isArray(note.lines) ? note.lines : [];
                if (body) {
                    body.innerHTML = lines.length
                        ? '<div class="admin-return-lines-table-wrap"><table class="admin-return-lines-table">' +
                          '<thead><tr><th>كود الصنف</th><th>اسم الصنف</th><th>الكمية المرجعة</th><th>السبب</th></tr></thead><tbody>' +
                          lines.map(function (ln) {
                              return '<tr>' +
                                  '<td><code>' + esc(ln.code) + '</code></td>' +
                                  '<td><strong>' + esc(ln.name) + '</strong></td>' +
                                  '<td><strong style="color:#059669;">' + esc(ln.qty_returned || 0) + '</strong></td>' +
                                  '<td>' + esc(ln.reason || '—') + '</td></tr>';
                          }).join('') + '</tbody></table></div>'
                        : '<p style="color:var(--text-muted);">لم يُستلم أي صنف من المخزن بعد.</p>';
                }

                modal.style.display = 'flex';
            };

            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }
            ['btnCloseReportsReturnItems', 'btnReportsReturnItemsClose'].forEach(function (id) {
                var btn = document.getElementById(id);
                if (btn) btn.addEventListener('click', closeModal);
            });
        })();
    </script>
@endif
