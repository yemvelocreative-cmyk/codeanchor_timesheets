// Timekeeper — Timesheet (clean)
(function () {
  'use strict';

  function qn(form, name) {
    return form.querySelector('[name="' + name + '"]');
  }

  // --- AJAX helper
  function fetchJSON(url) {
    return fetch(url, { credentials: 'same-origin' }).then(r => r.ok ? r.json() : []);
  }

  // Populate a <select> with ticket options [{id, text}], optionally set initial value
  function populateTicketSelect(selectEl, clientId, initialValue) {
  if (!selectEl || !clientId) return;
  const url = 'addonmodules.php?module=timekeeper&timekeeperpage=timesheet&ajax=tickets&client_id=' + encodeURIComponent(clientId);

  // If Select2 is already bound, temporarily destroy it so it re-reads options cleanly
  const hadSelect2 = !!(window.jQuery && jQuery.fn && jQuery.fn.select2 && jQuery(selectEl).data('select2'));
  if (hadSelect2) {
    jQuery(selectEl).select2('destroy');
  }

  fetch(url, { credentials: 'same-origin' })
    .then(r => r.ok ? r.json() : [])
    .then(items => {
      // clear options
      while (selectEl.options.length) selectEl.remove(0);
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Select a ticket…';
      selectEl.appendChild(placeholder);

      (items || []).forEach(it => {
        const opt = document.createElement('option');
        opt.value = String(it.id);
        opt.textContent = it.text || ('Ticket ID ' + it.id);
        selectEl.appendChild(opt);
      });

      // set initial selection if provided
      if (initialValue) {
        selectEl.value = String(initialValue);
      }

      // Re-init Select2 (or init if not yet)
      if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
        jQuery(selectEl).select2({
          width: '260px',
          placeholder: 'Select a ticket…',
          matcher: function (params, data) {
            if (jQuery.trim(params.term) === '') return data;
            const term = params.term.toLowerCase();
            const text = (data.text || '').toLowerCase();
            return text.indexOf(term) > -1 ? data : null;
          }
        }).trigger('change.select2');
      }
    })
    .catch(() => { /* ignore */ });
}

  // Show/hide billable/sla time inputs
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

  function bindDeleteConfirms() {
    document.querySelectorAll('form.ts-delete-form').forEach(form => {
      form.addEventListener('submit', function (e) {
        if (!confirm('Delete this entry?')) e.preventDefault();
      });
    });
  }

  // Initialize Select2 on elements by selector
  function initSelect2For(selector, widthPx) {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
    jQuery(selector).select2({
      width: widthPx ? (widthPx + 'px') : 'resolve',
      placeholder: 'Select…',
      matcher: function (params, data) {
        if (jQuery.trim(params.term) === '') return data;
        const term = params.term.toLowerCase();
        const text = (data.text || '').toLowerCase();
        return text.indexOf(term) > -1 ? data : null;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Add form behavior
    const addForm = document.getElementById('addTaskForm');
    if (addForm) {
      toggleTimeField(addForm, 'billable', 'billable_time');
      toggleTimeField(addForm, 'sla', 'sla_time');
      bindTimeCalc(addForm);
      bindDeptTaskFilter(
        document.getElementById('department_id'),
        document.getElementById('task_category_id')
      );

      // Tickets: load when client changes (Add form)
      const clientSel = document.getElementById('client_id');
      const ticketSel = document.getElementById('ticket_id');
      if (clientSel && ticketSel) {
        // Initial (if editing an existing row in the add form context)
        const initialTicket = parseInt(ticketSel.getAttribute('data-initial') || '0', 10) || null;
        if (clientSel.value) {
          populateTicketSelect(ticketSel, clientSel.value, initialTicket);
        }
        clientSel.addEventListener('change', function () {
          if (clientSel.value) {
            populateTicketSelect(ticketSel, clientSel.value, null);
          } else {
            // Clear
            while (ticketSel.options.length) ticketSel.remove(0);
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Select a ticket…';
            ticketSel.appendChild(opt);
          }
        });
      }
    }

    // Inline edit behavior (per-row)
    document.querySelectorAll('form.tk-row-edit').forEach(form => {
      bindTimeCalc(form);

      const deptSel = form.querySelector('.edit-department');
      const taskSel = form.querySelector('.edit-task-category');
      if (deptSel && taskSel) bindDeptTaskFilter(deptSel, taskSel);

      const spent = qn(form, 'time_spent');

      // Billable
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
            billInp.value = spent.value;
          }
        }
        billChk.addEventListener('change', setBill);
        setBill();
      }

      // SLA
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
            slaInp.value = spent.value;
          }
        }
        slaChk.addEventListener('change', setSla);
        setSla();
      }

      // Tickets: load for edit row
      const rowClient = form.querySelector('.js-row-client');
      const rowTicket = form.querySelector('.js-ticket-select');
      if (rowClient && rowTicket) {
        const initial = parseInt(rowTicket.getAttribute('data-initial') || '0', 10) || null;

        function loadRowTickets() {
          if (rowClient.value) {
            populateTicketSelect(rowTicket, rowClient.value, initial);
          }
        }
        loadRowTickets();

        // If client changes in edit mode, reload ticket list
        rowClient.addEventListener('change', function () {
          populateTicketSelect(rowTicket, rowClient.value, null);
        });
      }
    });

    bindDeleteConfirms();

    // Select2 init
    initSelect2For('#client_id', 200);
    initSelect2For('#ticket_id', 260);
    initSelect2For('form.tk-row-edit .tk-row-select', 180);
    initSelect2For('form.tk-row-edit .js-ticket-select', 240);
  });
})();
