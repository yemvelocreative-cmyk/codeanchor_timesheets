(function () {
  'use strict';

  // Double-submit guard (kept)
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-tk]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
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
  });
})();

/* ============================
   Role-Centric Toggle Lists JS (Hide Tabs)
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

  roleCards.forEach(card => updateRoleCount(card));

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
  function run() {
    const root = document.querySelector('.timekeeper-approvals-settings');
    if (!root) return;

    function update(scope) {
      const card = root.querySelector(`.tk-approvals-card[data-scope="${scope}"]`);
      if (!card) return;

      const checks = card.querySelectorAll(`.js-approvals-${scope}`);
      const selected = Array.from(checks).filter(c => c.checked).length;

      const counter = card.querySelector(`.js-approvals-${scope}-count`);
      if (counter) counter.textContent = `Selected: ${selected}`;

      const allToggle = card.querySelector(`.js-approvals-${scope}-toggleall`);
      if (allToggle) {
        allToggle.indeterminate = selected > 0 && selected < checks.length;
        allToggle.checked = checks.length > 0 && selected === checks.length;
      }
    }

    ['viewall', 'approve'].forEach(update);

    root.addEventListener('change', (e) => {
      const t = e.target;

      if (t.classList.contains('js-approvals-viewall-toggleall')) {
        const card = t.closest('.tk-approvals-card[data-scope="viewall"]');
        card?.querySelectorAll('.js-approvals-viewall').forEach(cb => cb.checked = t.checked);
        update('viewall');
        return;
      }

      if (t.classList.contains('js-approvals-approve-toggleall')) {
        const card = t.closest('.tk-approvals-card[data-scope="approve"]');
        card?.querySelectorAll('.js-approvals-approve').forEach(cb => cb.checked = t.checked);
        update('approve');
        return;
      }

      if (t.classList.contains('js-approvals-viewall')) { update('viewall'); }
      if (t.classList.contains('js-approvals-approve')) { update('approve'); }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
