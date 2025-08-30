// Timekeeper — Pending Timesheets
(function () {
  'use strict';

  // --- helpers ---
  function qn(form, name) { return form ? form.querySelector('[name="' + name + '"]') : null; }

  // Toggle a time input & optional header by checkbox
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

  // Calculate time_spent for a form with start/end/time_spent fields
  function bindTimeCalc(scope) {
    const start = scope.querySelector('[name="start_time"]');
    const end   = scope.querySelector('[name="end_time"]');
    const spent = scope.querySelector('[name="time_spent"]');
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

  // Bind edit rows (matches your template classes)
  function bindEditRows() {
    document.querySelectorAll('.pending-edit-department').forEach(function (deptSel) {
      const form = deptSel.closest('form');
      const taskSel = form ? form.querySelector('.pending-edit-task-category') : null;
      if (taskSel) bindDeptTaskFilter(deptSel, taskSel);
      if (form) bindTimeCalc(form);
    });
  }

  // Approve form: copy verify_unbilled_* checkboxes into the form on submit
  // Approve form: only inject verify_unbilled_* if those checkboxes are OUTSIDE the form
  function bindApproveInjection() {
    var approveForm = document.getElementById('approve-form');
    if (!approveForm) return;

    approveForm.addEventListener('submit', function () {
      // Find any verify checkboxes that are NOT descendants of #approve-form
      document.querySelectorAll('input[type="checkbox"][name^="verify_unbilled_"]').forEach(function (chk) {
        if (!approveForm.contains(chk)) {
          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = chk.name;
          hidden.value = chk.checked ? '1' : '0';
          approveForm.appendChild(hidden);
        }
      });
    });
  }

  // Optional confirms (only fire if you add these classes in the template later)
  function bindConfirms() {
    document.querySelectorAll('form.pt-approve-form').forEach(f => {
      f.addEventListener('submit', function (e) {
        if (!confirm('Approve this timesheet?')) e.preventDefault();
      });
    });
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
    document.querySelectorAll('form.pt-resubmit-form').forEach(f => {
      f.addEventListener('submit', function (e) {
        if (!confirm('Re-submit this timesheet for approval?')) e.preventDefault();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindEditRows();
    bindApproveInjection();
    bindConfirms();

    // Add-row bindings (use your current IDs from the template)
    var addDept = document.getElementById('pending-add-department');
    var addTask = document.getElementById('pending-add-task-category');
    var addRowScope = document; // inputs have unique IDs, so scope can be document

    if (addDept && addTask) bindDeptTaskFilter(addDept, addTask);
    // Time calc for the add row uses the named fields within the same "row" (IDs exist so this works)
    bindTimeCalc(addRowScope);

    // Optional: show/hide billable/sla inputs in Add row if you want dynamic visibility
    var billHdr = document.getElementById('pt-billable-header'); // add these spans if desired
    var slaHdr  = document.getElementById('pt-sla-header');
    var addForm = document.querySelector('form[action*="timekeeperpage=pending_timesheets"]'); // first form on the add row
    if (addForm) {
      toggleTimeField(addForm, 'billable', 'billable_time', billHdr);
      toggleTimeField(addForm, 'sla', 'sla_time', slaHdr);
    }
  });
})();
