/**
 * Shared client-side validation — Egyptian locale rules for all 7 dashboards.
 * Usage on inputs: data-v-rules="required,egyptian-mobile"
 * Conditional: data-v-when="patient_type=military" on required fields
 * Forms: data-validate-form on <form>
 */
(function (global) {
  'use strict';

  var MESSAGES = {
    required: 'هذا الحقل مطلوب.',
    email: 'أدخل بريداً إلكترونياً صالحاً.',
    'egyptian-mobile': 'رقم الهاتف يجب أن يكون 11 رقماً (010 / 011 / 012 / 015).',
    'egyptian-national-id': 'الرقم القومي يجب أن يكون 14 رقماً ويبدأ بـ 2 أو 3.',
    min: 'الحد الأدنى {n} أحرف.',
    max: 'الحد الأقصى {n} حرفاً.',
    minValue: 'القيمة يجب ألا تقل عن {n}.',
    maxValue: 'القيمة يجب ألا تزيد عن {n}.',
    integer: 'أدخل رقماً صحيحاً.',
    money: 'أدخل مبلغاً صحيحاً أكبر من صفر.',
    qr: 'رمز QR غير صالح.',
    barcode: 'الباركود غير صالح.',
    password: 'كلمة المرور يجب ألا تقل عن 6 أحرف.',
    passwordConfirm: 'تأكيد كلمة المرور غير متطابق.',
    date: 'أدخل تاريخاً صالحاً.',
    dateFuture: 'التاريخ لا يمكن أن يكون في المستقبل.',
    search: 'نص البحث طويل جداً.',
    rankCode: 'كود الرتبة: حروف إنجليزية وأرقام فقط.',
    select: 'اختر قيمة من القائمة.'
  };

  function digitsOnly(v) {
    return String(v || '').replace(/\D+/g, '');
  }

  function shouldDigitsOnly(el) {
    if (!el) return false;
    if (el.getAttribute('data-v-digits-only') === '1' || el.getAttribute('data-v-digits-only') === 'true') {
      return true;
    }
    var rules = parseRules(el);
    return rules.some(function (rule) {
      return rule === 'egyptian-mobile' || rule === 'egyptian-national-id' || rule === 'integer';
    });
  }

  function sanitizeDigitsValue(el) {
    if (!el) return;
    var max = el.maxLength > 0 ? el.maxLength : null;
    var cleaned = digitsOnly(el.value);
    if (max) cleaned = cleaned.slice(0, max);
    if (cleaned !== el.value) el.value = cleaned;
  }

  function bindDigitsOnly(el) {
    if (!el || el.dataset.vDigitsBound) return;
    el.dataset.vDigitsBound = '1';

    el.addEventListener('keydown', function (e) {
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      var allowed = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
      if (allowed.indexOf(e.key) !== -1) return;
      if (e.key.length === 1 && !/\d/.test(e.key)) e.preventDefault();
    });

    el.addEventListener('paste', function (e) {
      e.preventDefault();
      var pasted = (e.clipboardData || window.clipboardData).getData('text') || '';
      var max = el.maxLength > 0 ? el.maxLength : 32;
      el.value = digitsOnly(String(el.value) + pasted).slice(0, max);
      el.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  function trim(v) {
    return String(v || '').trim();
  }

  var validators = {
    required: function (value) {
      return trim(value) !== '';
    },
    email: function (value) {
      if (!trim(value)) return true;
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trim(value));
    },
    'egyptian-mobile': function (value) {
      if (!trim(value)) return true;
      var d = digitsOnly(value);
      return /^01[0125]\d{8}$/.test(d);
    },
    'egyptian-national-id': function (value) {
      if (!trim(value)) return true;
      var d = digitsOnly(value);
      return /^[23]\d{13}$/.test(d);
    },
    qr: function (value) {
      if (!trim(value)) return true;
      return /^[A-Za-z0-9\-_]{3,100}$/.test(trim(value));
    },
    barcode: function (value) {
      if (!trim(value)) return true;
      return /^[A-Za-z0-9\-_]{1,100}$/.test(trim(value));
    },
    integer: function (value) {
      if (value === '' || value === null || value === undefined) return true;
      return /^-?\d+$/.test(String(value));
    },
    money: function (value) {
      if (value === '' || value === null || value === undefined) return true;
      var n = parseFloat(value);
      return !isNaN(n) && n >= 0.01;
    },
    password: function (value) {
      if (!trim(value)) return true;
      return trim(value).length >= 6;
    },
    rankCode: function (value) {
      if (!trim(value)) return true;
      return /^[A-Za-z0-9_\-]{2,30}$/.test(trim(value));
    },
    search: function (value) {
      return trim(value).length <= 100;
    },
    date: function (value) {
      if (!trim(value)) return true;
      return !isNaN(Date.parse(value));
    },
    dateFuture: function (value) {
      if (!trim(value)) return true;
      var d = new Date(value);
      if (isNaN(d.getTime())) return false;
      var today = new Date();
      today.setHours(23, 59, 59, 999);
      return d <= today;
    },
    select: function (value) {
      return trim(value) !== '';
    }
  };

  function parseRules(el) {
    var raw = el.getAttribute('data-v-rules') || '';
    if (!raw && el.hasAttribute('required')) raw = 'required';
    return raw.split(',').map(function (r) { return r.trim(); }).filter(Boolean);
  }

  function ruleApplies(el, rule, form) {
    if (rule.indexOf('min:') === 0 || rule.indexOf('max:') === 0 ||
        rule.indexOf('minValue:') === 0 || rule.indexOf('maxValue:') === 0) {
      return true;
    }
    if (rule === 'required' || rule === 'select') {
      var when = el.getAttribute('data-v-when');
      if (when) {
        var parts = when.split('=');
        var field = form && form.querySelector('[name="' + parts[0] + '"]');
        if (!field || field.value !== parts[1]) return false;
      }
    }
    return true;
  }

  function runRule(el, rule, value, form) {
    if (!ruleApplies(el, rule, form)) return null;

    if (rule.indexOf('min:') === 0) {
      var minLen = parseInt(rule.split(':')[1], 10);
      if (trim(value) && trim(value).length < minLen) return MESSAGES.min.replace('{n}', minLen);
      return null;
    }
    if (rule.indexOf('max:') === 0) {
      var maxLen = parseInt(rule.split(':')[1], 10);
      if (trim(value).length > maxLen) return MESSAGES.max.replace('{n}', maxLen);
      return null;
    }
    if (rule.indexOf('minValue:') === 0) {
      var minV = parseFloat(rule.split(':')[1]);
      if (value !== '' && parseFloat(value) < minV) return MESSAGES.minValue.replace('{n}', minV);
      return null;
    }
    if (rule.indexOf('maxValue:') === 0) {
      var maxV = parseFloat(rule.split(':')[1]);
      if (value !== '' && parseFloat(value) > maxV) return MESSAGES.maxValue.replace('{n}', maxV);
      return null;
    }

    var fn = validators[rule];
    if (!fn) return null;
    if (!fn(value, el, form)) return MESSAGES[rule] || 'قيمة غير صالحة.';
    return null;
  }

  function getValue(el) {
    if (el.type === 'checkbox') return el.checked ? '1' : '';
    return el.value;
  }

  function markInvalid(el, message) {
    el.classList.add('v-invalid');
    el.setAttribute('aria-invalid', 'true');
    var wrap = el.closest('.form-group') || el.parentElement;
    if (!wrap) return;
    var msg = wrap.querySelector('.v-error-msg');
    if (!msg) {
      msg = document.createElement('div');
      msg.className = 'v-error-msg';
      msg.setAttribute('role', 'alert');
      wrap.appendChild(msg);
    }
    msg.textContent = message;
  }

  function clearInvalid(el) {
    el.classList.remove('v-invalid');
    el.removeAttribute('aria-invalid');
    var wrap = el.closest('.form-group') || el.parentElement;
    if (!wrap) return;
    wrap.querySelectorAll('.v-error-msg').forEach(function (msg) {
      msg.remove();
    });
  }

  function validateField(el, form) {
    if (!el || el.disabled || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') {
      return null;
    }
    var rules = parseRules(el);
    if (!rules.length) return null;
    var value = getValue(el);
    for (var i = 0; i < rules.length; i++) {
      var err = runRule(el, rules[i], value, form || el.form);
      if (err) {
        markInvalid(el, err);
        return err;
      }
    }
    clearInvalid(el);
    return null;
  }

  function validatePasswordConfirm(form) {
    var pw = form.querySelector('[name="password"]');
    var pwc = form.querySelector('[name="password_confirmation"]');
    if (!pw || !pwc) return true;
    if (!trim(pw.value) && !trim(pwc.value)) {
      clearInvalid(pwc);
      return true;
    }
    if (pw.value !== pwc.value) {
      markInvalid(pwc, MESSAGES.passwordConfirm);
      pwc.focus();
      return false;
    }
    clearInvalid(pwc);
    return true;
  }

  function validateForm(form) {
    if (!form) return true;
    var fields = form.querySelectorAll('input, select, textarea');
    var firstError = null;
    var valid = true;
    fields.forEach(function (el) {
      var err = validateField(el, form);
      if (err && !firstError) firstError = el;
      if (err) valid = false;
    });
    if (valid && !validatePasswordConfirm(form)) valid = false;
    if (firstError) firstError.focus();
    return valid;
  }

  function clearFormSummaryErrors(form) {
    if (!form) return;
    form.querySelectorAll(':scope > .v-error-msg').forEach(function (el) {
      el.remove();
    });
  }

  function handleFieldEvent(el, form) {
    if (!el || el.disabled || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') {
      return;
    }
    if (shouldDigitsOnly(el)) sanitizeDigitsValue(el);
    if (parseRules(el).length) {
      validateField(el, form || el.form);
      if (!el.classList.contains('v-invalid')) {
        clearFormSummaryErrors(form || el.form);
      }
    }
  }

  function bindField(el) {
    if (!el || el.dataset.vBound) return;
    el.dataset.vBound = '1';
    if (shouldDigitsOnly(el)) bindDigitsOnly(el);
  }

  function bindForm(form) {
    if (!form) return;

    if (!form.dataset.vFormBound) {
      form.dataset.vFormBound = '1';
      form.setAttribute('novalidate', 'novalidate');

      form.addEventListener('input', function (e) {
        handleFieldEvent(e.target, form);
      }, true);

      form.addEventListener('change', function (e) {
        handleFieldEvent(e.target, form);
      }, true);

      form.addEventListener('blur', function (e) {
        handleFieldEvent(e.target, form);
      }, true);

      form.addEventListener('submit', function (e) {
        if (!validateForm(form)) e.preventDefault();
      });

      form.querySelectorAll('[data-v-when]').forEach(function (dep) {
        var when = dep.getAttribute('data-v-when');
        if (!when) return;
        var fieldName = when.split('=')[0];
        var trigger = form.querySelector('[name="' + fieldName + '"]');
        if (trigger) {
          trigger.addEventListener('change', function () {
            validateField(dep, form);
          });
        }
      });
    }

    form.querySelectorAll('input, select, textarea').forEach(bindField);

    form.querySelectorAll('[data-v-rules]').forEach(function (el) {
      if (el.classList.contains('v-invalid') || (el.closest('.form-group') && el.closest('.form-group').querySelector('.v-error-msg'))) {
        validateField(el, form);
      }
    });
  }

  function bindSearchInputs() {
    document.querySelectorAll('input[type="search"], input[id$="Search"], input[placeholder*="بحث"]').forEach(function (el) {
      if (!el.getAttribute('data-v-rules')) {
        el.setAttribute('data-v-rules', 'search');
        el.setAttribute('maxlength', '100');
        bindField(el);
      }
    });
  }

  function bindDashboard() {
    document.querySelectorAll('form[data-validate-form]').forEach(bindForm);
    document.querySelectorAll('[data-v-rules]').forEach(function (el) {
      if (!el.form || !el.form.hasAttribute('data-validate-form')) bindField(el);
    });
    bindSearchInputs();
  }

  function validateValues(rulesMap) {
    var errors = [];
    Object.keys(rulesMap).forEach(function (key) {
      var spec = rulesMap[key];
      var fake = document.createElement('input');
      fake.setAttribute('data-v-rules', spec.rules || '');
      if (spec.when) fake.setAttribute('data-v-when', spec.when);
      var err = runRule(fake, 'required', spec.value, spec.form || null);
      if (spec.rules) {
        spec.rules.split(',').forEach(function (rule) {
          var e = runRule(fake, rule.trim(), spec.value, spec.form || null);
          if (e) errors.push({ field: key, message: e });
        });
      }
    });
    return errors;
  }

  global.DashboardValidation = {
    validateField: validateField,
    validateForm: validateForm,
    clearInvalid: clearInvalid,
    isFieldValid: function (el, form) {
      return validateField(el, form) === null;
    },
    bindForm: bindForm,
    bindField: bindField,
    bindDashboard: bindDashboard,
    digitsOnly: digitsOnly,
    validators: validators,
    messages: MESSAGES
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      if (!document.getElementById('dashboard-validation-style')) {
        var style = document.createElement('style');
        style.id = 'dashboard-validation-style';
        style.textContent = '.v-invalid{border-color:#dc2626!important;box-shadow:0 0 0 2px rgba(220,38,38,.12)!important}.v-error-msg{color:#dc2626;font-size:12px;margin-top:4px;font-weight:600}';
        document.head.appendChild(style);
      }
      bindDashboard();
    });
  } else {
    bindDashboard();
  }
})(typeof window !== 'undefined' ? window : this);
