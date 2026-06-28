{{-- Popup — معاينة خطاب الموافقة المرفوع --}}
<div class="modal-overlay" id="contractLetterModal"
     style="display:none;position:fixed;inset:0;z-index:1100;background:rgba(15,23,42,.65);
            backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:820px;max-height:92vh;
                box-shadow:0 24px 80px rgba(0,0,0,.25);display:flex;flex-direction:column;overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;
                    border-bottom:1px solid #e2e8f0;background:#f8fafc;flex-shrink:0;">
            <h3 style="font-size:15px;font-weight:700;margin:0;" id="contractLetterTitle">📄 خطاب الموافقة</h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <a id="contractLetterDownload" href="#" target="_blank" download
                   class="btn btn-secondary" style="padding:6px 14px;font-size:12px;">📎 تحميل</a>
                <button type="button" id="btnCloseContractLetter"
                        style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1;">&times;</button>
            </div>
        </div>
        <div style="flex:1;overflow:auto;padding:16px;background:#f1f5f9;min-height:320px;"
             id="contractLetterBody">
            <p style="text-align:center;color:#64748b;padding:40px;">جاري التحميل...</p>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    function $(id) { return document.getElementById(id); }

    window.openContractLetterView = function (url, contractNo, ext) {
        var modal   = $('contractLetterModal');
        var title   = $('contractLetterTitle');
        var body    = $('contractLetterBody');
        var dl      = $('contractLetterDownload');
        if (!modal || !body) return;

        if (title) title.textContent = '📄 خطاب الموافقة — ' + (contractNo || '');
        if (dl) { dl.href = url; dl.style.display = url ? '' : 'none'; }

        ext = (ext || '').toLowerCase();
        if (ext === 'pdf') {
            body.innerHTML = '<iframe src="' + url + '" style="width:100%;height:70vh;border:none;border-radius:8px;background:#fff;" title="خطاب الموافقة"></iframe>';
        } else {
            body.innerHTML = '<img src="' + url + '" alt="خطاب الموافقة" style="max-width:100%;height:auto;display:block;margin:0 auto;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.12);">';
        }

        modal.style.display = 'flex';
    };

    window.closeContractLetterView = function () {
        var modal = $('contractLetterModal');
        var body  = $('contractLetterBody');
        if (modal) modal.style.display = 'none';
        if (body) body.innerHTML = '';
    };

    $('btnCloseContractLetter') && $('btnCloseContractLetter').addEventListener('click', closeContractLetterView);
    $('contractLetterModal') && $('contractLetterModal').addEventListener('click', function (e) {
        if (e.target === $('contractLetterModal')) closeContractLetterView();
    });
})();
</script>
@endpush
@endonce
