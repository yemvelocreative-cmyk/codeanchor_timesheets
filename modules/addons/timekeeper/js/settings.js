(function () {
  'use strict';

  const TK = (window.Timekeeper = window.Timekeeper || {});

  // ----- Core helpers -----
  function hasOptionWithValue(selectEl, value) {
    if (!selectEl) return false;
    // Faster than Array.from(...).some(...)
    for (let i = 0; i < selectEl.options.length; i++) {
      if (selectEl.options[i].value === value) return true;
    }
    return false;
  }

  TK.moveSelected = function (fromId, toId) {
    const from = document.getElementById(fromId);
    const to = document.getElementById(toId);
    if (!from || !to) return;

    const frag = document.createDocumentFragment();
    const moved = [];

    Array.from(from.selectedOptions).forEach(opt => {
      // Avoid duplicates if markup gets out of sync
      if (!hasOptionWithValue(to, opt.value)) {
        moved.push(opt);
        frag.appendChild(opt);
      }
    });

    if (moved.length) {
      to.appendChild(frag);
      TK.sortSelect(to);
      // Optional: deselect after moving
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

    // Copy into array first; the live collection changes as we move
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

  TK.sortSelect = function (selectEl) {
    if (!selectEl) return;
    const opts = Array.from(selectEl.options);
    opts.sort((a, b) => a.text.localeCompare(b.text, navigator.language || undefined, { sensitivity: 'base' }));
    const frag = document.createDocumentFragment();
    opts.forEach(o => frag.appendChild(o));
    selectEl.appendChild(frag);
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
    // Utility to attach dual-select behaviors
    function bindDualSelect(cfg) {
      const { availId, assignedId, addBtnId, removeBtnId } = cfg;
      const avail = document.getElementById(availId);
      const assigned = document.getElementById(assignedId);
      const addBtn = document.getElementById(addBtnId);
      const removeBtn = document.getElementById(removeBtnId);

      if (addBtn) addBtn.addEventListener('click', () => { TK.moveSelected(availId, assignedId); });
      if (removeBtn) removeBtn.addEventListener('click', () => { TK.moveSelected(assignedId, availId); });
      if (avail)   avail.addEventListener('dblclick', () => { TK.moveSelected(availId, assignedId); });
      if (assigned)assigned.addEventListener('dblclick', () => { TK.moveSelected(assignedId, availId); });

      if (avail)   TK.sortSelect(avail);
      if (assigned)TK.sortSelect(assigned);
    }

    // Users (cron tab)
    bindDualSelect({
      availId: 'availableUsers',
      assignedId: 'assignedUsers',
      addBtnId: 'addUser',
      removeBtnId: 'removeUser'
    });

    // Roles - View permissions
    bindDualSelect({
      availId: 'availableRoles',
      assignedId: 'assignedRoles',
      addBtnId: 'addRole',
      removeBtnId: 'removeRole'
    });

    // Roles - Approve permissions
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

    // Reset the guard if user navigates back to the page (bfcache)
    window.addEventListener('pageshow', function (evt) {
      if (evt.persisted) {
        document.querySelectorAll('form[data-tk]').forEach(f => { delete f.dataset.submitting; });
      }
    });

    // Also handle explicit save buttons if present
    ['saveSettingsButton'].forEach(id => {
      const btn = document.getElementById(id);
      if (btn) btn.addEventListener('click', function () {
        const form = btn.closest('form');
        if (form) TK.selectAllFormMultis(form);
      });
    });
  });
})();
