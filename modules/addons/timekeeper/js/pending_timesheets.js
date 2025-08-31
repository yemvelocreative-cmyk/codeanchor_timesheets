// Timekeeper — Pending Timesheets
(function () {
  'use strict';

  // --- helpers ---
  function qn(form, name) { return form ? form.querySelector('[name="' + name + '"]') : null; }

  // Toggle a time input & optional header by checkbox
  // ENHANCED: also auto-fill the time field from time_spent when checked,
  // and keep it in sync when time_spent changes (unless user edits manually).
  function toggleTimeField(form, chkName, inputName, headerEl) {
    const chk   = qn(form, chkName);
    const inp   = qn(form, inputName);
    const spent = qn(form, 'time_spent'); // visible in Add, hidden in Edit

    if (!chk || !inp) return;

    // user-edited detection
    function markManual() { inp.dataset.autofill = '0'; }
    inp.addEventListener('input', markManual);

    function getSpent() {
      // prefer value in this form; fallback to global (Add form might be a separate form)
      if (spent && spent.value) return spent.value;
      // last resort: global name (avoid if possible)
      const g = document.querySelector('form#pt-add-form [name="time_spent"]');
      return g ? g.value : '';
    }

    function showField() {
      inp.classList.remove('col-hidden'); inp.classList.add('col-show');
      if (headerEl) { headerEl.classList.remove('col-hidden'); headerEl.classList.add('col-show'); }
    }
    function hideField() {
      inp.classList.remove('col-show'); inp.classList.add('col-hidden');
      if (headerEl) { headerEl.classList.remove('col-show'); headerEl.classList.add('col-hidden'); }
      inp.value = '';
      inp.dataset.autofill = '1'; // if re-enabled, we’ll re-autofill
    }

    function maybeAutofill() {
      if (!chk.checked) return;
      const ts = getSpent();
      // Autofill if empty OR previously autofilled
      if (inp.value.trim() === '' || inp.dataset.autofill !== '0') {
        inp.value = ts || '';
        inp.dataset.autofill = '1';
      }
    }

    function apply() {
      const show = !!chk.checked;
      if (show) { showField(); maybeAutofill(); }
      else { hideField(); }
    }

    chk.addEventListener('change', apply);

    // Keep in sync if time_spent changes
    const spentEl = spent;
    if (spentEl) {
      // if bindTimeCalc sets value programmatically, listen to change & input
      function syncFromSpent() { maybeAutofill(); }
      spentEl.addEventListener('change', syncFromSpent);
      spentEl.addEventListener('input',  syncFromSpent);
    }

    // initial state
    // If already checked on load, ensure field is visible and populated
    apply();
  }

  // Calculate time_spent for a form with start/end/time_spent fields
  // ENHANCED: dispatch a 'change' event when time_spent updates so dependent logic can react.
  function bindTimeCalc(scope) {
    const start = scope.querySelector('[name="start_time"]');
    const end   = scope.querySelector('[name="end_time"]');
    const spent = scope.querySelector('[name="time_spent"]');
    if (!start || !end || !spent) return;

    function calc() {
      const s = start.value, e = end.value;
      if (!s || !e) { spent.value = ''; spent.dispatchEvent(new Event('change')); return; }
      const [sh, sm] = s.split(':').map(Number);
      const [eh, em] = e.split(':').map(Number);
      const diff = (eh * 60 + em) - (sh * 60 + sm);
      if (diff <= 0) { alert('End time must be later than start time.'); end.value = ''; spent.value = ''; spent.dispatchEvent(new Event('change')); return; }
      const val = (Math.round((diff / 60) * 100) / 100).toFixed(2);
      spent.value = val;
      spent.dispatchEvent(new Event('change')); // notify any listeners
    }
    start.addEventListener('change', calc);
    end.addEventListener('change', calc);
    // run once if both are filled on load
    if (start.value && end.value) calc();
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
      if (form) {
        bindTimeCalc(form);
        // NEW: live toggle + auto-fill for Billable/SLA in EDIT rows
        toggleTimeField(form, 'billable', 'billable_time');
        toggleTimeField(form, 'sla', 'sla_time');
      }
    });
  }

  // Approve form: (kept for compatibility; no-op when using form="approve-form")
  function bindApproveInjection() {
    var approveForm = document.getElementById('approve-form');
    if (!approveForm) return;
    approveForm.addEventListener('submit', function () {
      // If any verify checkboxes are outside the form and lack form attribute (legacy),
      // inject hidden inputs so values submit reliably.
      document.querySelectorAll('input[type="checkbox"][name^="verify_unbilled_"]').forEach(function (chk) {
        if (chk.form === approveForm || approveForm.contains(chk)) return;
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = chk.name;
        hidden.value = chk.checked ? '1' : '0';
        approveForm.appendChild(hidden);
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

    // Add-row bindings (use the actual Add form to avoid cross-form collisions)
    var addForm = document.getElementById('pt-add-form');
    var addDept = document.getElementById('pending-add-department');
    var addTask = document.getElementById('pending-add-task-category');

    if (addDept && addTask) bindDeptTaskFilter(addDept, addTask);
    if (addForm) {
      bindTimeCalc(addForm);
      // NEW: live toggle + auto-fill for Billable/SLA in ADD row
      toggleTimeField(addForm, 'billable', 'billable_time');
      toggleTimeField(addForm, 'sla', 'sla_time');
    }
  });
})();
