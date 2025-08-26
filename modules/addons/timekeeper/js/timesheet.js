// Timekeeper — Timesheet (clean)
(function () {
  'use strict';

  // Quick selector by name within a form
  function qn(form, name) {
    return form.querySelector('[name="' + name + '"]');
  }

  // Show/hide billable/sla time inputs + (optional) headers using col-hidden/col-show
  // headerId is optional; pass if you have a matching header element to toggle.
  function toggleTimeField(form, checkboxName, inputName, headerId) {
    const chk = qn(form, checkboxName);
    const inp = qn(form, inputName);
    const header = headerId ? document.getElementById(headerId) : null;
    if (!chk || !inp) return;

    function apply() {
      const show = !!chk.checked;
      inp.classList.toggle('col-hidden', !show);
      inp.classList.toggle('col-show', show);

      if (!show) {
        inp.value = '';
      } else if (!inp.value) {
        // Autofill from time_spent if available
        const spent = qn(form, 'time_spent');
        if (spent && spent.value) inp.value = spent.value;
      }

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

      // Reset if current selection is now hidden
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
      toggleTimeField(addForm, 'billable', 'billable_time'); // headerId optional, omitted
      toggleTimeField(addForm, 'sla', 'sla_time');           // headerId optional, omitted
      bindTimeCalc(addForm);
      bindDeptTaskFilter(
        document.getElementById('department_id'),
        document.getElementById('task_category_id')
      );
    }

    // Inline edit behavior (per-row)
    document.querySelectorAll('form.tk-row-edit').forEach(form => {
      bindTimeCalc(form);

      const deptSel = form.querySelector('.edit-department');
      const taskSel = form.querySelector('.edit-task-category');
      if (deptSel && taskSel) bindDeptTaskFilter(deptSel, taskSel);

      const spent = qn(form, 'time_spent'); // hidden (edit) or readonly (add)

      // Billable toggle + autofill
      const billChk = form.querySelector('input[name="billable"]');
      const billInp = form.querySelector('input[name="billable_time"]');
      if (billChk && billInp) {
        function setBill() {
          const show = !!billChk.checked;
          billInp.classList.toggle('col-hidden', !show);
          billInp.classList.toggle('col-show', show);
          if (!show) {
            billInp.value = '';
          } else if (!billInp.value && spent && spent.value) {
            billInp.value = spent.value; // autofill from time_spent
          }
        }
        billChk.addEventListener('change', setBill);
        setBill();
      }

      // SLA toggle + autofill
      const slaChk = form.querySelector('input[name="sla"]');
      const slaInp = form.querySelector('input[name="sla_time"]');
      if (slaChk && slaInp) {
        function setSla() {
          const show = !!slaChk.checked;
          slaInp.classList.toggle('col-hidden', !show);
          slaInp.classList.toggle('col-show', show);
          if (!show) {
            slaInp.value = '';
          } else if (!slaInp.value && spent && spent.value) {
            slaInp.value = spent.value; // autofill from time_spent
          }
        }
        slaChk.addEventListener('change', setSla);
        setSla();
      }
    });

    bindDeleteConfirms();
    initSelect2();
  });
})();
