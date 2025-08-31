// /modules/addons/timekeeper/js/approved_timesheets.js
(function () {
  'use strict';

  function bindUnapproveConfirm(root) {
    (root || document).querySelectorAll('form.tk-unapprove-form').forEach(function (f) {
      // Prevent duplicate bindings if the script runs twice
      if (f.dataset.tkBound === '1') return;
      f.dataset.tkBound = '1';

      f.addEventListener('submit', function (e) {
        // If we've already confirmed once, let it go through
        if (f.dataset.tkConfirmed === '1') return;

        e.preventDefault(); // stop the normal submit flow for now
        var ok = window.confirm('Unapprove this timesheet? It will move back to Pending.');
        if (!ok) return;

        // Mark as confirmed and submit natively to bypass other submit handlers
        f.dataset.tkConfirmed = '1';
        HTMLFormElement.prototype.submit.call(f);
      }, { capture: true }); // capture ensures we intercept before other listeners
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindUnapproveConfirm(document);
  });

  // If anything dynamically injects rows later, you can call this:
  window.tkBindUnapproveConfirm = bindUnapproveConfirm;
})();