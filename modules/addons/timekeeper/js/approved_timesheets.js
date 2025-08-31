(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.tk-unapprove-form').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        if (!confirm('Unapprove this timesheet? It will move back to Pending.')) {
          e.preventDefault();
        }
      });
    });
  });
})();
