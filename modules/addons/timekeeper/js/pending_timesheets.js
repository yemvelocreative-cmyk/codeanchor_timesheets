// Timekeeper — Pending Timesheets
(function () {
  'use strict';

  // --- helpers ---
  function qn(form, name) { return form ? form.querySelector('[name="' + name + '"]') : null; }

  // Generic: Initialize or refresh Select2 on a <select> (used for client & ticket)
  function initSelect2(selectEl, placeholderText) {
    if (!selectEl) return;
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return; // guard if jQuery/Select2 missing
    var $sel = jQuery(selectEl);
    if ($sel.data('select2')) {
      $sel.select2('destroy');
    }
    $sel.select2({
      placeholder: placeholderText || 'Select…',
      width: '100%',
      allowClear: true,
      dropdownAutoWidth: true,
      minimumResultsForSearch: 0 // always show the search box
    });
  }

  // Ticket-specific Select2 initializer (kept for clarity; calls generic)
  function initTicketSelect2(selectEl) {
    initSelect2(selectEl, 'Select a ticket…');
  }

  // Populate ticket <select> for the chosen client (labels = #TID only)
function populateTicketSelect(selectEl, tickets, preselected) {
  if (!selectEl) return;
  const current = selectEl.value;
  const valToSet = (preselected !== undefined && preselected !== null) ? String(preselected) : current;

  // Clear
  selectEl.innerHTML = '';
  const optEmpty = document.createElement('option');
  optEmpty.value = '';
  optEmpty.textContent = 'Select…';
  selectEl.appendChild(optEmpty);

  if (Array.isArray(tickets)) {
    tickets.forEach(t => {
      // Be tolerant to various backend field names:
      const rawTid =
        t.tid || t.TID || t.ticket_tid || t.ticketTID || t.ticketid || t.ticket_number || t.id || '';
      const tid = String(rawTid).trim();
      if (!tid) return;

      const opt = document.createElement('option');
      opt.value = tid;             // submit plain TID
      opt.textContent = '#' + tid; // display as #TID only
      if (valToSet && tid === valToSet) opt.selected = true;
      selectEl.appendChild(opt);
    });
  }

  // (Re)apply Select2 after DOM options are set
  initTicketSelect2(selectEl);
}

// Bind client -> ticket linkage for a given form (robust to late-loaded map & Select2)
function bindTicketPicker(clientSelect, ticketSelect) {
  if (!clientSelect || !ticketSelect) return;
  const preselected = ticketSelect.getAttribute('data-preselected') || '';

  function ticketsFor(val) {
    const k = String(val || '');
    const data = (window.TK_TICKETS_BY_CLIENT || {}); // <-- re-read live map each time
    const asNum = parseInt(k, 10);
    return data[k] || (Number.isFinite(asNum) ? data[asNum] : null) || [];
  }

  function apply() {
    const items = ticketsFor(clientSelect.value);
    populateTicketSelect(ticketSelect, items, preselected);
  }

  // Native change (works with/without Select2)
  clientSelect.addEventListener('change', apply);

  // If Select2 is present, listen to its events as well
  if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
    jQuery(clientSelect).on('select2:select select2:clear', apply);
  }

  // Initial fill + microtask re-apply (covers late-defined TK_TICKETS_BY_CLIENT)
  apply();
  setTimeout(apply, 0);
}

// (Optional, but nice): rerun binding for any rows dynamically added later
window.addEventListener('tk:tickets-ready', function () {
  const addClient  = document.getElementById('pending-add-client');
  const addTicket  = document.getElementById('pending-add-ticket');
  if (addClient && addTicket) { bindTicketPicker(addClient, addTicket); }
  document.querySelectorAll('.tk-row-edit').forEach(function (form) {
    const c = form.querySelector('.pending-edit-client') || form.querySelector('select[name="client_id"]');
    const t = form.querySelector('.tk-ticket-select');
    if (c && t) bindTicketPicker(c, t);
  });
});

  // Toggle a time input & optional header by checkbox
  function toggleTimeField(form, chkName, inputName, headerEl) {
    const chk   = qn(form, chkName);
    const inp   = qn(form, inputName);
    const spent = qn(form, 'time_spent'); // visible in Add, hidden in Edit
    if (!chk || !inp) return;

    function markManual() { inp.dataset.autofill = '0'; }
    inp.addEventListener('input', markManual);

    function getSpent() {
      if (spent && spent.value) return spent.value;
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
      inp.dataset.autofill = '1';
    }

    function maybeAutofill() {
      if (!chk.checked) return;
      const ts = getSpent();
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

    const spentEl = spent;
    if (spentEl) {
      function syncFromSpent() { maybeAutofill(); }
      spentEl.addEventListener('change', syncFromSpent);
      spentEl.addEventListener('input',  syncFromSpent);
    }

    apply();
  }

  // Calculate time_spent
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
      spent.dispatchEvent(new Event('change'));
    }
    start.addEventListener('change', calc);
    end.addEventListener('change', calc);
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

  // Bind edit rows
  function bindEditRows() {
    document.querySelectorAll('.tk-row-edit').forEach(function (form) {
      // Client select (searchable via Select2)
      // Prefer an explicit class if present; otherwise fall back to the client select in the row
      let clientSel = form.querySelector('.pending-edit-client');
      if (!clientSel) clientSel = form.querySelector('select[name="client_id"]');
      if (clientSel) initSelect2(clientSel, 'Select client…');

      // Client -> Ticket linkage per edit row (includes Select2 init for ticket)
      const ticketSel = form.querySelector('.tk-ticket-select');
      if (clientSel && ticketSel) bindTicketPicker(clientSel, ticketSel);

      // Dept -> Task filter, time calc, and billable/SLA toggles
      const deptSel = form.querySelector('.pending-edit-department');
      const taskSel = form.querySelector('.pending-edit-task-category');
      if (deptSel && taskSel) bindDeptTaskFilter(deptSel, taskSel);
      bindTimeCalc(form);
      toggleTimeField(form, 'billable', 'billable_time');
      toggleTimeField(form, 'sla', 'sla_time');
    });
  }

  // Approve form injection (legacy-safe)
  function bindApproveInjection() {
    var approveForm = document.getElementById('approve-form');
    if (!approveForm) return;
    approveForm.addEventListener('submit', function () {
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

  // Optional confirms
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

    // Add-row bindings
    var addForm  = document.getElementById('pt-add-form');
    if (addForm) {
      bindTimeCalc(addForm);
      toggleTimeField(addForm, 'billable', 'billable_time');
      toggleTimeField(addForm, 'sla', 'sla_time');

      // Dept -> Task filter
      var addDept = document.getElementById('pending-add-department');
      var addTask = document.getElementById('pending-add-task-category');
      if (addDept && addTask) bindDeptTaskFilter(addDept, addTask);

      // Client select (searchable via Select2) on Add row
      var addClient = document.getElementById('pending-add-client');
      if (addClient) initSelect2(addClient, 'Select client…');

      // Client -> Ticket linkage on Add (includes Select2 init for ticket)
      var addTicket = document.getElementById('pending-add-ticket');
      if (addClient && addTicket) {
        bindTicketPicker(addClient, addTicket);
      }
    }
  });
})();

// Compact toolbar reject panel toggling
var openBtn = document.getElementById('open-reject');
var panel   = document.getElementById('reject-panel');
var cancel  = document.getElementById('cancel-reject');
if (openBtn && panel) {
  openBtn.addEventListener('click', function(){ panel.classList.add('is-open'); });
}
if (cancel && panel) {
  cancel.addEventListener('click', function(){ panel.classList.remove('is-open'); });
}
