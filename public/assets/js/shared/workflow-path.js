(function () {
  function $(id) { return document.getElementById(id); }

  function openWorkflowPathModal() {
    var modal = $('workflowPathModal');
    if (!modal) return;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeWorkflowPathModal() {
    var modal = $('workflowPathModal');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
  }

  window.openWorkflowPathModal = openWorkflowPathModal;
  window.closeWorkflowPathModal = closeWorkflowPathModal;

  document.addEventListener('click', function (e) {
    var openBtn = e.target.closest('[data-workflow-path-open]');
    if (openBtn) {
      e.preventDefault();
      openWorkflowPathModal();
      return;
    }
    if (e.target.id === 'btnCloseWorkflowPath' || e.target.id === 'workflowPathModal') {
      closeWorkflowPathModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeWorkflowPathModal();
  });
})();
