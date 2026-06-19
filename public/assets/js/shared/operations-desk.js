/**
 * OperationsDesk — Static UI stub (no localStorage / no seed data)
 */
var OperationsDesk = (function () {
  function empty() { return []; }
  function nullFn() { return null; }

  return {
    getQueue: empty,
    getInProduction: empty,
    getReady: empty,
    ensureWorkOrder: nullFn,
    getSummary: function () { return { queue: 0, production: 0, ready: 0 }; }
  };
})();
