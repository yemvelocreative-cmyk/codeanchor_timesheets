// Timekeeper — Timesheet
(function () {
  'use strict';

  // Quick selector by name within a form
  function qn(form, name) { return form.querySelector('[name="' + name + '"]'); }

  // Show/hide billable/sla time inputs + headers
  function toggleTimeField(form, checkboxName, inputName, headerId) {
    const chk = qn(form, checkboxName);
    const inp = qn(form, inputName);
    const header = document.getElementById(headerId);
    if (!chk || !inp) return;

    function apply() {
      const show = chk.checked === true;
      inp.classList.toggle('col-hidden', !show);
      inp.classList.toggle('col-show', show);
      if (!show) inp.value = '';
      if (header) {
        header.classList.toggle('col-hidden', !show);
        header.classList.toggle('col-show', show);
      }
    }
    chk.addEventListener('change', apply);
    apply();
  }

  // Calculate time_spent within a form from start_time/end_time (both HH:MM)
  function bindTimeCalc(form) {
    const start = qn(form, 'start_time');
    const end = qn(form, 'end_time');
    const spent = qn(form, 'time_spent');
    if (!start || !end || !spent) return;

    function calc() {
      const s = start.value, e = end.value;
      if (!s || !e) { spent.value = ''; return; }
      const [sh, sm] = s.split(':').map(Number);
      const [eh, em] = e.split(':').map(Number);
      let diff = (eh * 60 + em) - (sh * 60 + sm);
      if (diff <= 0) {
        alert('End time must be later than start time.');
        end.value = '';
        spent.value = '';
        return;
      }
      spent.value = (Math.round((diff / 60) * 100) / 100).toFixed(2);
    }
    start.addEventListener('change', calc);
    end.addEventListener('change', calc);
  }

  // Filter task_category by selected department (single form)
  function bindDeptTaskFilter(deptSel, taskSel) {
    if (!deptSel || !taskSel) return;
    function apply() {
      const dept = deptSel.value;
      Array.from(taskSel.options).forEach(opt => {
        const dep = opt.getAttribute('data-dept');
        const show = (!dep || dep === dept || opt.value === '');
        opt.style.display = show ? 'block' : 'none';
      });
      // Reset if current selection is hidden
      if (taskSel.selectedOptions.length && taskSel.selectedOptions[0].style.display === 'none') {
        taskSel.value = '';
      }
    }
    deptSel.addEventListener('change', apply);
    apply();
  }

  // Confirm deletes
  function bindDeleteConfirms() {
    document.querySelectorAll('form.ts-delete-form').forEach(form => {
      form.addEventListener('submit', function (e) {
        if (!confirm('Delete this entry?')) e.preventDefault();
      });
    });
  }

  // Initialize Select2 on #client_id, if available
  function initSelect2() {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
    jQuery('#client_id').select2({
      width: '200px',
      placeholder: 'Select Client',
      matcher: function (params, data) {
        if (jQuery.trim(params.term) === '') return data;
        const term = params.term.toLowerCase();
        const text = (data.text || '').toLowerCase();
        return text.indexOf(term) > -1 ? data : null;
      }
    });
  }

  // ------- Boot -------
  document.addEventListener('DOMContentLoaded', function () {
    // Add form behavior
    const addForm = document.getElementById('addTaskForm');
    if (addForm) {
      toggleTimeField(addForm, 'billable', 'billable_time', 'billableTimeHeader');
      toggleTimeField(addForm, 'sla', 'sla_time', 'slaTimeHeader');
      bindTimeCalc(addForm);
      bindDeptTaskFilter(
        document.getElementById('department_id'),
        document.getElementById('task_category_id')
      );
    }

    // Edit row behavior (per-row)
    document.querySelectorAll('form.ts-item').forEach(form => {
      bindTimeCalc(form);
      const deptSel = form.querySelector('.edit-department');
      const taskSel = form.querySelector('.edit-task-category');
      if (deptSel && taskSel) bindDeptTaskFilter(deptSel, taskSel);
    });

    bindDeleteConfirms();
    initSelect2();
  });

  (function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
      const header = document.querySelector('.timekeeper-root .ts-subheader');
      if (!header) return;

      const labels = Array.from(header.children).map(el => el.textContent.trim());

      // Apply data-col to every non-header row so CSS can show inline labels on mobile
      document.querySelectorAll('.timekeeper-root .ts-row:not(.ts-subheader)').forEach(row => {
        Array.from(row.children).forEach((cell, i) => {
          // Only set if not already present
          if (!cell.hasAttribute('data-col') && labels[i]) {
            cell.setAttribute('data-col', labels[i]);
          }
        });
      });
    });
  })();
})();
