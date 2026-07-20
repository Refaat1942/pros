/**
 * Shared popup for technical spec notes (ملاحظات التوصيف الفني).
 */
(function () {
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function hasNotes(notes) {
    return String(notes || '').trim().length > 0;
  }

  window.TechNotesModal = {
    hasNotes: hasNotes,

    buttonHtml: function (notes, caseNo) {
      if (!hasNotes(notes)) return '';
      var payload = esc(JSON.stringify({ notes: String(notes), case_no: caseNo || '' }));
      return '<button type="button" class="btn-action btn-view-tech-notes" style="margin-left:4px;" ' +
        'data-tech-notes-payload="' + payload + '">📝 عرض الملاحظة</button>';
    },

    writtenItemsButtonHtml: function (text, caseNo) {
      if (!hasNotes(text)) return '';
      var payload = esc(JSON.stringify({ notes: String(text), case_no: caseNo || '', title: 'الوصف الحر' }));
      return '<button type="button" class="btn-action btn-view-tech-notes" style="margin-left:4px;" ' +
        'data-tech-notes-payload="' + payload + '">📄 الوصف الحر</button>';
    },

    open: function (notes, caseLabel, modalTitle) {
      var modal = document.getElementById('techNotesModal');
      var body = document.getElementById('techNotesBody');
      var title = document.getElementById('techNotesTitle');
      if (!modal || !body) return;
      if (title) {
        if (modalTitle) {
          title.textContent = modalTitle + (caseLabel ? ' — ' + caseLabel : '');
        } else {
          title.textContent = caseLabel
            ? '📝 ملاحظات التوصيف — ' + caseLabel
            : '📝 ملاحظات التوصيف';
        }
      }
      body.textContent = notes || '';
      modal.classList.add('visible');
    },

    close: function () {
      var modal = document.getElementById('techNotesModal');
      if (modal) modal.classList.remove('visible');
    },

    bind: function () {
      var self = this;
      document.querySelectorAll('.btn-view-tech-notes').forEach(function (btn) {
        if (btn.dataset.techNotesBound === '1') return;
        btn.dataset.techNotesBound = '1';
        btn.addEventListener('click', function () {
          var raw = btn.getAttribute('data-tech-notes-payload') || '{}';
          try {
            var data = JSON.parse(raw);
            self.open(data.notes || '', data.case_no || '', data.title || '');
          } catch (e) {
            self.open('', '');
          }
        });
      });
    },

    init: function () {
      var self = this;
      var modal = document.getElementById('techNotesModal');
      if (!modal) return;
      var closeBtn = document.getElementById('closeTechNotesModal');
      if (closeBtn) closeBtn.addEventListener('click', function () { self.close(); });
      modal.addEventListener('click', function (e) { if (e.target === modal) self.close(); });
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    window.TechNotesModal.init();
  });
})();
