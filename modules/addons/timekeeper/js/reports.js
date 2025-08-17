// Timekeeper — Reports
(function () {
  'use strict';

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  // ----- Date helpers (ISO week: Monday = start) -----
  function fmtYMD(dt) {
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const d = String(dt.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }
  function startOfMonth(dt) { return new Date(dt.getFullYear(), dt.getMonth(), 1); }
  function endOfMonth(dt) { return new Date(dt.getFullYear(), dt.getMonth() + 1, 0); }
  function startOfLastMonth(dt) { return new Date(dt.getFullYear(), dt.getMonth() - 1, 1); }
  function endOfLastMonth(dt) { return new Date(dt.getFullYear(), dt.getMonth(), 0); }
  function startOfWeek(dt) {
    // JS Sunday=0; make Monday the first day
    const day = dt.getDay();
    const diff = (day === 0 ? 6 : day - 1);
    const s = new Date(dt);
    s.setDate(dt.getDate() - diff);
    s.setHours(0, 0, 0, 0);
    return s;
  }
  function endOfWeek(dt) {
    const s = startOfWeek(dt);
    const e = new Date(s);
    e.setDate(s.getDate() + 6);
    return e;
  }

  // ----- Bind "quick range" buttons (data-range="this_month|last_month|this_week") -----
  function bindDateQuickies(scope) {
    qsa('[data-range]', scope).forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const form = btn.closest('form') || qs('form.timekeeper-report-filters', scope) || qs('form');
        if (!form) return;
        const from = form.querySelector('input[name="from"]');
        const to = form.querySelector('input[name="to"]');
        if (!from || !to) return;

        const now = new Date();
        let s = null, eDate = null;
        switch (btn.getAttribute('data-range')) {
          case 'this_month':
            s = startOfMonth(now); eDate = endOfMonth(now); break;
          case 'last_month':
            s = startOfLastMonth(now); eDate = endOfLastMonth(now); break;
          case 'this_week':
            s = startOfWeek(now); eDate = endOfWeek(now); break;
        }
        if (s && eDate) {
          from.value = fmtYMD(s);
          to.value = fmtYMD(eDate);
        }
        // Auto-submit if requested
        if (btn.hasAttribute('data-submit') || form.hasAttribute('data-autosubmit')) {
          form.submit();
        }
      });
    });
  }

  // ----- Department → Task Category filtering (expects options with data-dept="ID") -----
  function bindDeptTaskFilter(scope) {
    qsa('.timekeeper-report-filters', scope).forEach(function (form) {
      const dept = form.querySelector('#department_id, select[name="department_id"]');
      const task = form.querySelector('#task_category_id, select[name="task_category_id"]');
      if (!dept || !task) return;

      function apply() {
        const selectedDept = dept.value;
        Array.from(task.options).forEach(function (opt) {
          const depId = opt.getAttribute('data-dept');
          const show = (!depId || depId === selectedDept || opt.value === '');
          opt.style.display = show ? 'block' : 'none';
        });
        if (task.selectedOptions.length && task.selectedOptions[0].style.display === 'none') {
          task.value = '';
        }
      }

      dept.addEventListener('change', apply);
      apply();
    });
  }

  // ----- Export buttons (data-export="csv" | "xlsx" | etc.) -----
  function bindExportButtons(scope) {
    qsa('[data-export]', scope).forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const form = btn.closest('form') || qs('form.timekeeper-report-filters', scope) || qs('form');
        if (!form) return;

        function hidden(name, val) {
          let i = form.querySelector('input[name="' + name + '"]');
          if (!i) {
            i = document.createElement('input');
            i.type = 'hidden';
            i.name = name;
            form.appendChild(i);
          }
          i.value = val;
        }

        hidden('export', '1');
        hidden('format', btn.getAttribute('data-export') || 'csv');
        form.submit();
      });
    });
  }

  // ----- Optional Select2 init (only if jQuery+Select2 is present) -----
  function hydrateSelect2() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) return;
    jQuery(function ($) {
      $('select[data-select2], select.tk-select2').each(function () {
        $(this).select2({ width: 'resolve' });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const scope = document.querySelector('.timekeeper-root') || document;
    bindDateQuickies(scope);
    bindDeptTaskFilter(scope);
    bindExportButtons(scope);
    hydrateSelect2();
  });
})();

// Timekeeper Reports — shared JS
(function () {
  'use strict';

  // Only run on the audit page
  function onReady(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  onReady(function () {
    var auditRoot = document.getElementById('ts-audit');
    if (!auditRoot) return;

    // Enhance client dropdown with Select2 if available; otherwise add a simple filter input
    var hasSelect2 = (window.jQuery && jQuery.fn && typeof jQuery.fn.select2 === 'function');
    var clientSelect = document.querySelector('#ts-audit .js-client-select');

    if (hasSelect2 && clientSelect) {
      var $ = window.jQuery;
      function customMatcher(params, data) {
        if ($.trim(params.term) === '') return data;
        if (typeof data.text === 'undefined') return null;
        var term = params.term.toLowerCase();
        var text = (data.text || '').toLowerCase();
        return text.indexOf(term) > -1 ? data : null;
      }
      $(clientSelect)
        .select2({
          width: 'style', // matches CSS width
          placeholder: 'All Clients',
          allowClear: true,
          matcher: customMatcher
        })
        .css('min-width', '260px');
      return;
    }

    // Fallback filter input if Select2 isn't present
    if (clientSelect) {
      var wrapper = document.createElement('div');
      wrapper.style.display = 'flex';
      wrapper.style.flexDirection = 'column';
      wrapper.style.gap = '6px';
      clientSelect.parentNode.insertBefore(wrapper, clientSelect);
      wrapper.appendChild(clientSelect);

      var filter = document.createElement('input');
      filter.type = 'text';
      filter.placeholder = 'Type to filter clients...';
      filter.style.padding = '6px';
      filter.style.minWidth = '260px';
      wrapper.insertBefore(filter, clientSelect);

      filter.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        var opts = clientSelect.options;
        var keepSelected = false;
        for (var i = 0; i < opts.length; i++) {
          var text = (opts[i].text || '').toLowerCase();
          var match = (q === '') || (text.indexOf(q) > -1) || (opts[i].value === '0'); // keep "All Clients"
          opts[i].style.display = match ? '' : 'none';
          if (match && opts[i].selected) keepSelected = true;
        }
        if (!keepSelected) clientSelect.value = '0';
      });
    }
  });
})();
