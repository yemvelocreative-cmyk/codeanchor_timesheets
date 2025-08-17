// Timekeeper — Pending Timesheets
(function () {
  'use strict';

  // Simple helper to query by name in a form
  function qn(form, name) { return form.querySelector('[name="' + name + '"]'); }

  // Toggle a time input & header by checkbox
  function toggleTimeField(form, chkName, inputName, headerEl) {
    const chk = qn(form, chkName);
    const inp = qn(form, inputName);
    if (!chk || !inp) return;
    function apply() {
      const show = !!chk.checked;
      inp.classList.toggle('col-hidden', !show);
      inp.classList.toggle('col-show', show);
      if (!show) inp.value = '';
      if (headerEl) {
        headerEl.classList.toggle('col-hidden', !show);
        headerEl.classList.toggle('col-show', show);
      }
    }
    chk.addEventListener('change', apply);
    apply();
  }

  // Calculate time_spent for edit/add rows (basic HH:MM validation)
  function bindTimeCalc(form) {
    const start = qn(form, 'start_time');
    const end   = qn(form, 'end_time');
    const spent = qn(form, 'time_spent');
    if (!start || !end || !spent) return;

    function calc() {
      const s = start.value, e = end.value;
      if (!s || !e) { spent.value = ''; return; }
      const [sh, sm] = s.split(':').map(Number);
      const [eh, em] = e.split(':').map(Number);
      const diff = (eh * 60 + em) - (sh * 60 + sm);
      if (diff <= 0) { alert('End time must be later than start time.'); end.value = ''; spent.value = ''; return; }
      spent.value = (Math.round((diff / 60) * 100) / 100).toFixed(2);
    }
    start.addEventListener('change', calc);
    end.addEventListener('change', calc);
  }

  // Filter task categories by department (works for add + edit forms)
  function bindDeptTaskFilter(deptSel, taskSel) {
    if (!deptSel || !taskSel) return;
    function apply() {
      const dept = deptSel.value;
      Array.from(taskSel.options).forEach(opt => {
        const dep = opt.getAttribute('data-dept');
        const show = (!dep || dep === dept || opt.value === '');
        opt.style.display = show ? 'block' : 'none';
      });
      if (taskSel.selectedOptions.length && taskSel.selectedOptions[0].style.display === 'none') {
        taskSel.value = '';
      }
    }
    deptSel.addEventListener('change', apply);
    apply();
  }

  // Confirmations
  function bindConfirms() {
    // Approve
    document.querySelectorAll('form.pt-approve-form').forEach(f => {
      f.addEventListener('submit', function (e) {
        if (!confirm('Approve this timesheet?')) e.preventDefault();
      });
    });
    // Reject
    document.querySelectorAll('form.pt-reject-form').forEach(f => {
      f.addEventListener('submit', function (e) {
        const note = qn(f, 'admin_rejection_note');
        if (!confirm('Reject this timesheet?')) { e.preventDefault(); return; }
        if (note && note.value.trim() === '') {
          e.preventDefault();
          alert('Please add a rejection note.');
        }
      });
    });
    // Resubmit
    document.querySelectorAll('form.pt-resubmit-form').forEach(f => {
      f.addEventListener('submit', function (e) {
        if (!confirm('Re-submit this timesheet for approval?')) e.preventDefault();
      });
    });
  }

  // For edit rows: bind dept -> task filter
  function bindEditRows() {
    document.querySelectorAll('.pt-entry-row').forEach(row => {
      const deptSel = row.querySelector('.edit-department');
      const taskSel = row.querySelector('.edit-task-category');
      if (deptSel && taskSel) bindDeptTaskFilter(deptSel, taskSel);
      bindTimeCalc(row);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindConfirms();
    bindEditRows();

    // Add-form helpers (if detail view has an add form)
    const addForm = document.getElementById('pt-add-form');
    if (addForm) {
      const dept = addForm.querySelector('#department_id');
      const task = addForm.querySelector('#task_category_id');
      if (dept && task) bindDeptTaskFilter(dept, task);
      bindTimeCalc(addForm);

      // Show/hide billable/sla inputs & headers in the add form
      const billHdr = document.getElementById('pt-billable-header');
      const slaHdr  = document.getElementById('pt-sla-header');
      toggleTimeField(addForm, 'billable', 'billable_time', billHdr);
      toggleTimeField(addForm, 'sla', 'sla_time', slaHdr);
    }
  });
})();
