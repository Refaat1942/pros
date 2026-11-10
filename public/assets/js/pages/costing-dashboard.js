/**
 * Costing Dashboard — لوحة مستقلة وبسيطة (read-only + تأكيد).
 */
(function () {
  if (document.body.dataset.dashboard !== 'costing') return;
  if (document.body.dataset.activePage !== 'costing') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/costing/queue/list';
  var SHOW_URL = function (id) { return '/costing/queue/' + id; };
  var CONFIRM_URL = function (id) { return '/costing/queue/' + id + '/confirm'; };

  var activeCaseId = null;
  var refreshInFlight = false;
  var canSeeInternal = false;
  var canSeeRates = false;
  var currentInternalTotal = 0;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function fmt(n) {
    return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtCatalogPrice(n) {
    return Number(n || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function apiMessage(err, fallback) {
    var data = err && err.response && err.response.data;
    if (data && data.message) return data.message;
    return fallback || 'حدث خطأ غير متوقع.';
  }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', prefix: isError ? '' : '✅ ', isError: isError });
      return;
    }
    var el = $('toast');
    if (!el) { if (isError) window.alert(msg); return; }
    el.textContent = msg;
    el.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(function () { el.classList.remove('show'); }, 4500);
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var search = [c.case_no, c.order_ref, c.patient && c.patient.name].join(' ');

    return '<tr class="costing-row" data-search="' + esc(search) + '">' +
      '<td><strong>' + esc(c.case_no) + '</strong></td>' +
      '<td>' + esc(c.patient && c.patient.name) + '</td>' +
      '<td>' + (isMil ? '🪖 عسكري' : '🌐 مدني') + '</td>' +
      '<td>' + (c.computed_total != null ? fmt(c.computed_total) + ' ج.م' : '—') + '</td>' +
      '<td class="col-actions">' +
        '<button type="button" class="btn-action primary btn-open-costing" data-case-id="' + c.id + '">مراجعة</button>' +
      '</td></tr>';
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-open-costing').forEach(function (btn) {
      btn.addEventListener('click', function () { openModal(btn.getAttribute('data-case-id')); });
    });
  }

  function filterSearch() {
    var q = ($('costingSearch') && $('costingSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.costing-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function refreshList() {
    if (!window.axios || refreshInFlight) return;
    refreshInFlight = true;
    var btn = $('btnRefreshCosting');
    if (btn) { btn.disabled = true; btn.textContent = '↻ …'; }

    axios.get(LIST_URL)
      .then(function (res) {
        var cases = res.data.data || [];
        var tbody = $('costingTable');
        if (!tbody) return;
        if ($('costingBadge')) $('costingBadge').textContent = cases.length;
        if (!cases.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="empty-cell">لا توجد حالات بانتظار الاعتماد.</td></tr>';
        } else {
          tbody.innerHTML = cases.map(renderRow).join('');
          bindTableEvents();
        }
        if (window.TablePagination) TablePagination.refreshById('costingTable');
        filterSearch();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر التحميل'), true); })
      .finally(function () {
        refreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث'; }
      });
  }

  function renderBreakdown(costing) {
    if (!costing) return;

    // غير الأدمن: يظهر سعر البيع فقط — تُخفى كل التفاصيل الداخلية.
    var internal = $('costingInternalRows');
    if (internal) internal.style.display = canSeeRates ? 'contents' : 'none';
    if ($('costingBreakdownTitle')) {
      $('costingBreakdownTitle').textContent = canSeeRates ? '📊 تفصيل التكلفة وسعر البيع' : '💰 سعر البيع';
    }
    if ($('costingSellingPrice')) $('costingSellingPrice').textContent = fmt(costing.selling_price) + ' ج.م';

    if (!canSeeRates) return;

    if ($('costingMaterialsTotal')) $('costingMaterialsTotal').textContent = fmt(costing.materials_total) + ' ج.م';
    currentMaterials = parseFloat(costing.materials_total) || 0;

    var linesEl = $('costingComponentLines');
    if (linesEl) {
      if (costing.components && costing.components.length) {
        linesEl.style.display = '';
        linesEl.innerHTML = costing.components.map(function (row) {
          return '<div class="costing-overhead-row">' +
            '<span>' + esc(row.label) + ' (' + esc(row.rate) + '%)</span>' +
            '<strong>' + fmt(row.amount) + ' ج.م</strong></div>';
        }).join('');
      } else {
        linesEl.style.display = 'none';
        linesEl.innerHTML = '';
      }
    }

    var compTotalRow = $('costingComponentsTotalRow');
    if (compTotalRow) {
      if (costing.components && costing.components.length) {
        compTotalRow.style.display = '';
        if ($('costingComponentsTotal')) $('costingComponentsTotal').textContent = fmt(costing.components_total) + ' ج.م';
      } else {
        compTotalRow.style.display = 'none';
      }
    }

    // سعر بيع الطرف الصناعي (95%) — يظهر فقط عند وجود مواد طرف.
    var hasBase = (parseFloat(costing.base_materials) || 0) > 0;
    var baseRow = $('costingBaseSellingRow');
    if (baseRow) {
      baseRow.style.display = hasBase ? '' : 'none';
      if (hasBase) {
        if ($('costingBaseSellingLabel')) {
          $('costingBaseSellingLabel').textContent = '🏭 مواد التصنيع ' + fmt(costing.base_materials) + ' + تكلفة التشغيل ' + fmt(costing.components_total || 0) +
            ' — ربح ' + (costing.base_profit_rate || 0) + '% ← سعر البيع';
        }
        if ($('costingBaseSelling')) $('costingBaseSelling').textContent = fmt(costing.base_selling) + ' ج.م';
      }
    }

    // بند الصرف السريع (40%) — يظهر فقط عند وجود أصناف صرف سريع.
    var hasQuick = (parseFloat(costing.quick_materials) || 0) > 0;
    var quickRow = $('costingQuickRow');
    if (quickRow) {
      quickRow.style.display = hasQuick ? '' : 'none';
      if (hasQuick) {
        if ($('costingQuickLabel')) {
          $('costingQuickLabel').textContent = '🩼 منتجات الصرف السريع ' + fmt(costing.quick_materials) +
            ' — ربح ' + (costing.quick_profit_rate || 0) + '% (بدون تكلفة تشغيل) ← سعر البيع';
        }
        if ($('costingQuickSelling')) $('costingQuickSelling').textContent = fmt(costing.quick_selling) + ' ج.م';
      }
    }

    if ($('costingTotalCost')) $('costingTotalCost').textContent = fmt(costing.total_cost) + ' ج.م';

    if ($('costingWacRow')) $('costingWacRow').style.display = canSeeInternal ? '' : 'none';
    if ($('costingWacTotal') && canSeeInternal) $('costingWacTotal').textContent = fmt(currentInternalTotal) + ' ج.م';

    if ($('costingProfitLabel')) $('costingProfitLabel').textContent = 'إجمالي هامش الربح (' + (costing.profit_rate || 0) + '%)';
    if ($('costingProfitAmount')) $('costingProfitAmount').textContent = fmt(costing.profit_amount) + ' ج.م';
    if ($('costingSellingPrice')) $('costingSellingPrice').textContent = fmt(costing.selling_price) + ' ج.م';
  }

  function openModal(caseId) {
    activeCaseId = caseId;
    axios.get(SHOW_URL(caseId))
      .then(function (res) {
        var c = res.data.case || {};
        var pricing = res.data.pricing || {};
        var costing = res.data.costing || null;
        setupCashDiscount(c, c.discount_types);
        canSeeInternal = !!res.data.can_see_internal;
        canSeeRates = !!res.data.can_see_rates;
        currentInternalTotal = pricing.internal_total || 0;

        if ($('costingModalTitle')) {
          $('costingModalTitle').textContent = '✅ ' + (c.case_no || '') + ' — ' + (c.patient && c.patient.name || '');
        }

        var banner = $('costingPatientBanner');
        if (banner) {
          banner.classList.remove('hidden');
          if ($('costingPatientName')) {
            $('costingPatientName').textContent = (c.patient && c.patient.name) || '—';
          }
          if ($('costingPatientMeta')) {
            $('costingPatientMeta').textContent = [
              c.case_no || '—',
              c.order_ref || '',
              c.pathway_label || '',
              c.display_entity || '',
              c.patient && c.patient.patient_code ? ('كود: ' + c.patient.patient_code) : '',
            ].filter(Boolean).join(' · ');
          }

          var medical = res.data.medical_record || {};
          var doctorNameEl = $('costingDoctorName');
          var doctorMsgEl = $('costingDoctorMessage');
          var doctorRecEl = $('costingDoctorRecommendations');
          if (doctorNameEl) {
            if (medical.doctor_name) {
              doctorNameEl.textContent = 'د. ' + medical.doctor_name;
              doctorNameEl.classList.remove('hidden');
            } else {
              doctorNameEl.textContent = '';
              doctorNameEl.classList.add('hidden');
            }
          }
          if (doctorMsgEl) {
            var doctorText = medical.doctor_message || [medical.diagnosis, medical.prescription].filter(function (x) {
              return x && String(x).trim();
            }).join('\n\n');
            if (doctorText && String(doctorText).trim()) {
              doctorMsgEl.textContent = doctorText;
              doctorMsgEl.classList.remove('hidden');
            } else {
              doctorMsgEl.textContent = '';
              doctorMsgEl.classList.add('hidden');
            }
          }
          if (doctorRecEl) {
            var recs = medical.recommendations || [];
            if (recs.length) {
              doctorRecEl.innerHTML = recs.map(function (r) {
                return '<li><span>' + esc(r.name || '—') + (r.code ? ' <code>' + esc(r.code) + '</code>' : '') + '</span><strong>× ' + esc(r.qty || 1) + '</strong></li>';
              }).join('');
              doctorRecEl.classList.remove('hidden');
            } else {
              doctorRecEl.innerHTML = '';
              doctorRecEl.classList.add('hidden');
            }
          }

          var spec = res.data.spec;
          var specList = $('costingSpecItems');
          if (specList) {
            var specItems = (spec && spec.items) || [];
            var quickWritten = (spec && spec.quick_dispense_items) || [];
            var specHtml = specItems.map(function (it) {
              return '<li><span><code>' + esc(it.stock_item_code) + '</code> ' + esc(it.name) + '</span><strong>× ' + esc(it.qty) + '</strong></li>';
            }).join('') + quickWritten.map(function (it) {
              return '<li><span>🩼 ' + esc(it.name) + ' <small>(صرف سريع — مكتوب في التوصيف)</small></span><strong>× ' + esc(it.qty) + '</strong></li>';
            }).join('');
            specList.innerHTML = specHtml || '<li class="text-slate-400">لا توجد بنود توصيف</li>';
          }
          var notesEl = $('costingSpecNotes');
          if (notesEl) {
            var notes = [(spec && spec.tech_notes) || c.tech_notes || '', (spec && spec.written_items) || ''].filter(function (x) { return x && String(x).trim(); }).join('\n\n');
            if (notes.trim()) {
              notesEl.textContent = notes;
              notesEl.classList.remove('hidden');
            } else {
              notesEl.textContent = '';
              notesEl.classList.add('hidden');
            }
          }
        }

        if ($('costingMeta')) {
          $('costingMeta').textContent = 'طلب: ' + (pricing.request_no || '—');
        }

        var body = $('costingItemsBody');
        if (body) {
          // مواد التصنيع (عليها تكلفة تشغيل) الأول، وبعدها منتجات الصرف السريع — كل نوع بنسبة ربح مختلفة.
          var pItems = pricing.items || [];
          var made = pItems.filter(function (it) { return !it.is_quick_dispense; });
          var quickOnes = pItems.filter(function (it) { return !!it.is_quick_dispense; });
          var both = made.length && quickOnes.length;
          var prevQuick = null;
          body.innerHTML = made.concat(quickOnes).map(function (it) {
            var quick = !!it.is_quick_dispense;
            var head = '';
            if (both && quick !== prevQuick) {
              head = '<tr><td colspan="6" style="background:' + (quick ? '#ecfdf5' : '#f8fafc') + ';font-weight:700;padding:8px 10px;">' +
                (quick ? '🩼 منتجات الصرف السريع — ربح مباشر بدون تكلفة تشغيل' : '🏭 مواد التصنيع — تُحمَّل بتكلفة التشغيل') + '</td></tr>';
            }
            prevQuick = quick;
            var badge = quick
              ? ' <span class="badge" style="background:#ecfdf5;color:#047857;font-size:11px;">🩼 صرف سريع</span>'
              : ' <span class="badge" style="background:#f1f5f9;color:#475569;font-size:11px;">🏭 تصنيع</span>';
            return head + '<tr>' +
              '<td><code>' + esc(it.stock_item_code) + '</code></td>' +
              '<td>' + esc(it.name) + badge + '</td>' +
              '<td>' + esc(it.qty) + '</td>' +
              '<td class="costing-criteria-cell">' + esc(it.criteria || '—') + '</td>' +
              '<td class="catalog-price-cell" style="text-align:center;">' + fmtCatalogPrice(it.price) + '</td>' +
              '<td><strong>' + fmt(it.line_total) + '</strong></td></tr>';
          }).join('');
        }

        currentMaterials = 0;
        renderBreakdown(costing);
        renderNetRows();

        var modal = $('costingModal');
        if (modal) modal.classList.add('visible');
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر فتح التفاصيل'), true); });
  }

  // ── خصم العميل المدني الكاش ─────────────────────────────────────────
  var discountGross = 0;

  function fmt2(n) {
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function discountAmount() {
    if (!currentDiscountType()) return 0;
    var v = parseFloat(($('cashDiscountValue') || {}).value || '0') || 0;
    if (v <= 0) return 0;
    var isPct = (($('cashDiscountType') || {}).value || 'percent') === 'percent';
    return Math.round((isPct ? discountGross * v / 100 : v) * 100) / 100;
  }

  // صفوف حسبة اللائحة: الخصم ← المبلغ بعد الخصم ← نسبة الربح = (الصافي − المواد) ÷ المواد.
  var currentMaterials = 0;

  function renderNetRows() {
    var amt = discountAmount();
    var valid = amt > 0 && amt < discountGross;
    var net = valid ? discountGross - amt : discountGross;
    var t = ($('cashDiscountType') || {}).value;
    var v = parseFloat(($('cashDiscountValue') || {}).value || '0') || 0;
    if ($('costingDiscountRow')) $('costingDiscountRow').style.display = valid ? '' : 'none';
    if ($('costingNetRow')) $('costingNetRow').style.display = valid ? '' : 'none';
    if ($('costingDiscountLabel')) $('costingDiscountLabel').textContent = 'خصم إضافي' + (t === 'percent' && v > 0 ? ' (' + v + '%)' : '');
    if ($('costingDiscountAmount')) $('costingDiscountAmount').textContent = '− ' + fmt2(amt) + ' ج.م';
    if ($('costingNetAmount')) $('costingNetAmount').textContent = fmt2(net) + ' ج.م';
    var profitRow = $('costingMaterialsProfitRow');
    if (profitRow) {
      var show = canSeeRates && currentMaterials > 0 && discountGross > 0;
      profitRow.style.display = show ? '' : 'none';
      if (show && $('costingMaterialsProfit')) {
        $('costingMaterialsProfit').textContent = ((net - currentMaterials) / currentMaterials * 100).toFixed(2) + '%';
      }
    }
  }

  function updateDiscountPreview() {
    renderNetRows();
    var p = $('cashDiscountPreview');
    if (!p) return;
    var amt = discountAmount();
    p.textContent = amt > 0
      ? 'الإجمالي ' + fmt2(discountGross) + ' − خصم ' + fmt2(amt) + ' = ' + fmt2(discountGross - amt) + ' ج.م'
      : '';
    p.style.color = amt >= discountGross && amt > 0 ? '#b91c1c' : '#0f766e';
  }

  // أنواع الخصم المعتمدة من «سياسة الخصومات» — كل نوع بطرقه المسموحة وحدّه الأقصى.
  var discountTypes = [];

  function currentDiscountType() {
    var key = ($('cashDiscountCategory') || {}).value;
    return discountTypes.filter(function (t) { return t.key === key; })[0] || null;
  }

  function syncDiscountTypeOptions() {
    var t = currentDiscountType();
    var sel = $('cashDiscountType');
    if (!sel) return;
    if (!t) {
      // «بدون خصم»: مفيش قيمة ولا حسبة خصم.
      if ($('cashDiscountValue')) { $('cashDiscountValue').value = ''; $('cashDiscountValue').readOnly = true; $('cashDiscountValue').dataset.fixed = ''; }
      if ($('cashDiscountLimit')) $('cashDiscountLimit').textContent = '';
      updateDiscountPreview();
      return;
    }
    Array.prototype.forEach.call(sel.options, function (o) {
      o.disabled = (o.value === 'percent' && !t.allow_percent) || (o.value === 'amount' && !t.allow_amount);
    });
    if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) {
      sel.value = t.allow_percent ? 'percent' : 'amount';
    }
    // نوع بنسبة ثابتة (خصم 15% / 25%): القيمة بتتملى لوحدها ومتتغيرش.
    var valueInput = $('cashDiscountValue');
    var fixed = t.fixed_percent != null && t.fixed_percent !== '';
    if (valueInput) {
      valueInput.readOnly = fixed;
      if (fixed) { sel.value = 'percent'; valueInput.value = t.fixed_percent; }
      else if (valueInput.dataset.fixed === '1') { valueInput.value = ''; }
      valueInput.dataset.fixed = fixed ? '1' : '';
    }
    if ($('cashDiscountLimit')) {
      $('cashDiscountLimit').textContent = fixed
        ? ('نسبة ثابتة: ' + t.fixed_percent + '% من سعر البيع')
        : (t.max_percent ? ('الحد الأقصى: ' + t.max_percent + '% من سعر البيع') : '');
    }
    updateDiscountPreview();
  }

  function setupCashDiscount(c, types) {
    var box = $('cashDiscountBox');
    if (!box) return;
    discountTypes = types || [];
    var cat = $('cashDiscountCategory');
    if (cat) {
      cat.innerHTML = '<option value="">— بدون خصم —</option>' +
        discountTypes.map(function (t) { return '<option value="' + esc(t.key) + '">' + esc(t.label) + '</option>'; }).join('');
    }
    box.hidden = !c.can_grant_cash_discount || !discountTypes.length;
    discountGross = Number(c.selling_price || c.computed_total || 0);
    if ($('cashDiscountValue')) $('cashDiscountValue').value = '';
    if ($('cashDiscountReason')) $('cashDiscountReason').value = '';
    if ($('cashDiscountType')) $('cashDiscountType').value = 'percent';
    syncDiscountTypeOptions();
  }

  function discountPayload() {
    var box = $('cashDiscountBox');
    if (!box || box.hidden) return {};
    var v = parseFloat(($('cashDiscountValue') || {}).value || '0') || 0;
    if (v <= 0 || !currentDiscountType()) return {};
    return {
      discount_type: ($('cashDiscountType') || {}).value || 'percent',
      discount_value: v,
      discount_reason: (($('cashDiscountReason') || {}).value || '').trim(),
      discount_category: ($('cashDiscountCategory') || {}).value || null,
    };
  }

  function closeModal() {
    activeCaseId = null;
    var modal = $('costingModal');
    if (modal) modal.classList.remove('visible');
  }

  function confirmCosting() {
    if (!activeCaseId) return;

    var payload = discountPayload();
    var t = currentDiscountType();
    if (payload.discount_value && t && t.max_percent && discountAmount() > discountGross * t.max_percent / 100 + 0.009) {
      toast('الخصم يتجاوز الحد الأقصى لنوع «' + t.label + '» (' + t.max_percent + '%).', true);
      return;
    }

    var btn = $('btnConfirmCosting');
    if (btn) btn.disabled = true;

    axios.post(CONFIRM_URL(activeCaseId), payload)
      .then(function (res) {
        toast(res.data.message || 'تم التأكيد');
        closeModal();
        refreshList();
      })
      .catch(function (err) { toast(apiMessage(err, 'تعذّر التأكيد'), true); })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    refreshList();
    if ($('costingSearch')) $('costingSearch').addEventListener('input', filterSearch);
    if ($('btnRefreshCosting')) $('btnRefreshCosting').addEventListener('click', refreshList);
    if ($('closeCostingModal')) $('closeCostingModal').addEventListener('click', closeModal);
    if ($('btnCancelCosting')) $('btnCancelCosting').addEventListener('click', closeModal);
    if ($('btnConfirmCosting')) $('btnConfirmCosting').addEventListener('click', confirmCosting);
    if ($('cashDiscountCategory')) $('cashDiscountCategory').addEventListener('change', syncDiscountTypeOptions);
    ['cashDiscountValue', 'cashDiscountType'].forEach(function (id) {
      if ($(id)) $(id).addEventListener('input', updateDiscountPreview);
      if ($(id)) $(id).addEventListener('change', updateDiscountPreview);
    });
    var modal = $('costingModal');
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  });
})();
