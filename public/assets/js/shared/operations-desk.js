/**
 * OperationsDesk — مكتب التشغيل المركزي
 * يلتقي عنده المساران (مدني بعد الموافقة، عسكري مباشرة)، يولّد رقم أمر التشغيل،
 * ويُجهّز قوائم الصرف بالباركود للمخزن.
 */
var OperationsDesk = (function () {

  function caseHasBomStage(c, stage) {
    if (typeof BomInventory === 'undefined') return false;
    var bom = BomInventory.getByCaseId(c.id);
    return bom && bom.stage === stage;
  }

  // الحالات الواصلة لمكتب التشغيل وتنتظر أمر صرف المواد (BOM خام).
  function getQueue() {
    if (typeof CasesWorkflow === 'undefined') return [];
    return CasesWorkflow.getBucket('in_progress').filter(function (c) {
      return caseHasBomStage(c, 'raw');
    });
  }

  // الحالات قيد التصنيع بعد الصرف (BOM تحت التشغيل).
  function getInProduction() {
    if (typeof CasesWorkflow === 'undefined') return [];
    return CasesWorkflow.getBucket('in_progress').filter(function (c) {
      return caseHasBomStage(c, 'wip');
    });
  }

  // الحالات الجاهزة للتسليم والتركيب (BOM تام).
  function getReady() {
    if (typeof CasesWorkflow === 'undefined') return [];
    return CasesWorkflow.getBucket('in_progress').filter(function (c) {
      return caseHasBomStage(c, 'finished');
    });
  }

  // توليد/تثبيت رقم أمر التشغيل المركزي للحالة.
  function ensureWorkOrder(caseId) {
    if (typeof CasesWorkflow === 'undefined') return null;
    var c = CasesWorkflow.getById(caseId);
    if (!c) return null;
    if (c.workOrderNo) return c.workOrderNo;
    var wo = CasesWorkflow.generateWorkOrderNo(c);
    CasesWorkflow.setStage(c.id, c.stageKey, { workOrderNo: wo });
    return wo;
  }

  // محاكاة إرسال إشعار SMS للمريض والجهة عند جاهزية الطرف.
  function notifyReady(caseId) {
    if (typeof CasesWorkflow === 'undefined') return null;
    var c = CasesWorkflow.getById(caseId);
    if (!c) return null;
    var msg = '📩 SMS → ' + c.patient + ' و' + (c.company || 'الجهة') +
      ': الطرف الصناعي (أمر ' + (c.workOrderNo || '—') + ') جاهز للتسليم والتركيب.';
    try {
      var log = JSON.parse(localStorage.getItem('clinic_sms_log') || '[]');
      log.unshift({ caseId: c.id, patient: c.patient, msg: msg, at: new Date().toISOString() });
      localStorage.setItem('clinic_sms_log', JSON.stringify(log.slice(0, 50)));
    } catch (e) { /* ignore */ }
    return msg;
  }

  function getSummary() {
    return {
      queue: getQueue().length,
      production: getInProduction().length,
      ready: getReady().length
    };
  }

  return {
    getQueue: getQueue,
    getInProduction: getInProduction,
    getReady: getReady,
    ensureWorkOrder: ensureWorkOrder,
    notifyReady: notifyReady,
    getSummary: getSummary
  };
})();
