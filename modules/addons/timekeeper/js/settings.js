(function () {
  'use strict';

  const TK = (window.Timekeeper = window.Timekeeper || {});

  // ----- Core helpers -----
  function hasOptionWithValue(selectEl, value) {
    if (!selectEl) return false;
    for (let i = 0; i < selectEl.options.length; i++) {
      if (selectEl.options[i].value === value) return true;
    }
    return false;
  }

  TK.sortSelect = function (selectEl) {
    if (!selectEl) return;
    const opts = Array.from(selectEl.options);
    opts.sort((a, b) =>
      a.text.localeCompare(b.text, navigator.language || undefined, { sensitivity: 'base' })
    );
    const frag = document.createDocumentFragment();
    opts.forEach(o => frag.appendChild(o));
    selectEl.appendChild(frag);
  };

  TK.moveSelected = function (fromId, toId) {
    const from = document.getElementById(fromId);
    const to = document.getElementById(toId);
    if (!from || !to) return;

    const frag = document.createDocumentFragment();
    const moved = [];

    Array.from(from.selectedOptions).forEach(opt => {
      if (!hasOptionWithValue(to, opt.value)) {
        moved.push(opt);
        frag.appendChild(opt);
      }
    });

    if (moved.length) {
      to.appendChild(frag);
      TK.sortSelect(to);
      moved.forEach(o => { o.selected = false; });
      to.focus();
    }
  };

  TK.moveAll = function (fromId, toId) {
    const from = document.getElementById(fromId);
    const to = document.getElementById(toId);
    if (!from || !to) return;

    const frag = document.createDocumentFragment();
    const moved = [];

    Array.from(from.options).forEach(opt => {
      if (!hasOptionWithValue(to, opt.value)) {
        moved.push(opt);
        frag.appendChild(opt);
      }
    });

    if (moved.length) {
      to.appendChild(frag);
      TK.sortSelect(to);
      moved.forEach(o => { o.selected = false; });
      to.focus();
    }
  };

  // Select all MULTIPLE <select>s that have a name attribute (assigned boxes) within a form
  TK.selectAllFormMultis = function (form) {
    form.querySelectorAll('select[multiple][name]').forEach(sel => {
      for (let i = 0; i < sel.options.length; i++) {
        sel.options[i].selected = true;
      }
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    // Utility to attach dual-select behaviors safely (no-op if elements missing)
    function bindDualSelect(cfg) {
      const { availId, assignedId, addBtnId, removeBtnId } = cfg;
      const avail = document.getElementById(availId);
      const assigned = document.getElementById(assignedId);
      const addBtn = document.getElementById(addBtnId);
      const removeBtn = document.getElementById(removeBtnId);

      if (!avail || !assigned || !addBtn || !removeBtn) return;

      addBtn.addEventListener('click', () => { TK.moveSelected(availId, assignedId); });
      removeBtn.addEventListener('click', () => { TK.moveSelected(assignedId, availId); });

      // Nice-to-have UX: double-click to move
      avail.addEventListener('dblclick', () => { TK.moveSelected(availId, assignedId); });
      assigned.addEventListener('dblclick', () => { TK.moveSelected(assignedId, availId); });

      TK.sortSelect(avail);
      TK.sortSelect(assigned);

      // On submit of the closest form, ensure assigned options are selected
      const form = addBtn.closest('form');
      if (form) {
        form.addEventListener('submit', function () {
          TK.selectAllFormMultis(form);
        });
      }
    }

    // Users (cron tab)
    bindDualSelect({
      availId: 'availableUsers',
      assignedId: 'assignedUsers',
      addBtnId: 'addUser',
      removeBtnId: 'removeUser'
    });

    // Roles - View permissions (approvals tab)
    bindDualSelect({
      availId: 'availableRoles',
      assignedId: 'assignedRoles',
      addBtnId: 'addRole',
      removeBtnId: 'removeRole'
    });

    // Roles - Approve permissions (approvals tab)
    bindDualSelect({
      availId: 'availableRolesApprove',
      assignedId: 'assignedRolesApprove',
      addBtnId: 'addRoleApprove',
      removeBtnId: 'removeRoleApprove'
    });

    // Double-submit guard + ensure assigned selects are submitted
    document.querySelectorAll('form[data-tk]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        TK.selectAllFormMultis(form);
        if (form.dataset.submitting === '1') {
          e.preventDefault();
          return false;
        }
        form.dataset.submitting = '1';
      });
    });

    // Reset the guard if user navigates back (bfcache)
    window.addEventListener('pageshow', function (evt) {
      if (evt.persisted) {
        document.querySelectorAll('form[data-tk]').forEach(f => { delete f.dataset.submitting; });
      }
    });

    // Optional: explicit save buttons by id
    ['saveSettingsButton'].forEach(id => {
      const btn = document.getElementById(id);
      if (btn) btn.addEventListener('click', function () {
        const form = btn.closest('form');
        if (form) TK.selectAllFormMultis(form);
      });
    });
  });
})();

/* ============================
   Role-Centric Toggle Lists JS
   ============================ */
(function () {
  const root = document.querySelector('.timekeeper-root .timekeeper-hide-tabs-settings');
  if (!root) return;

  const roleCards = root.querySelectorAll('.tk-role-card');
  if (!roleCards.length) return;

  function updateRoleCount(card) {
    const checks = card.querySelectorAll('.js-role-tab');
    const total = checks.length;
    const hiddenCount = Array.from(checks).filter(c => c.checked).length;

    const badge = card.querySelector('.tk-role-count');
    if (badge) badge.textContent = `Hidden: ${hiddenCount} / ${total}`;

    const toggleAll = card.querySelector('.js-role-toggle-all');
    if (toggleAll) {
      toggleAll.indeterminate = hiddenCount > 0 && hiddenCount < total;
      toggleAll.checked = total > 0 && hiddenCount === total;
    }
  }

  // Init counts
  roleCards.forEach(card => updateRoleCount(card));

  // Toggle-all + individual toggles
  root.addEventListener('change', (e) => {
    const t = e.target;

    if (t.classList.contains('js-role-toggle-all')) {
      const card = t.closest('.tk-role-card');
      if (!card) return;
      card.querySelectorAll('.js-role-tab').forEach(c => { c.checked = t.checked; });
      updateRoleCount(card);
      return;
    }

    if (t.classList.contains('js-role-tab')) {
      const card = t.closest('.tk-role-card');
      if (card) updateRoleCount(card);
    }
  });
})();

/* ============================
   Approvals – Dual Cards
   ============================ */
(function () {
  const root = document.querySelector('.timekeeper-root .timekeeper-approvals-settings');
  if (!root) return;

  function updateCount(scope) {
    const card = root.querySelector(`.tk-approvals-card[data-scope="${scope}"]`);
    if (!card) return;
    const checks = card.querySelectorAll(`.js-approvals-${scope}`);
    const selected = Array.from(checks).filter(c => c.checked).length;
    const counter = card.querySelector(`.js-approvals-${scope}-count`);
    if (counter) counter.textContent = `Selected: ${selected}`;

    // reflect tri-state on toggle-all
    const allToggle = card.querySelector(`.js-approvals-${scope}-toggleall`);
    if (allToggle) {
      allToggle.indeterminate = selected > 0 && selected < checks.length;
      allToggle.checked = checks.length > 0 && selected === checks.length;
    }
  }

  // Init both cards
  ['viewall', 'approve'].forEach(scope => updateCount(scope));

  // Toggle-all per card
  root.addEventListener('change', (e) => {
    const t = e.target;

    if (t.classList.contains('js-approvals-viewall-toggleall')) {
      const card = t.closest('.tk-approvals-card');
      card.querySelectorAll('.js-approvals-viewall').forEach(cb => cb.checked = t.checked);
      updateCount('viewall');
    }

    if (t.classList.contains('js-approvals-approve-toggleall')) {
      const card = t.closest('.tk-approvals-card');
      card.querySelectorAll('.js-approvals-approve').forEach(cb => cb.checked = t.checked);
      updateCount('approve');
    }

    if (t.classList.contains('js-approvals-viewall')) updateCount('viewall');
    if (t.classList.contains('js-approvals-approve')) updateCount('approve');
  });
})();
