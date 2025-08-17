// Timekeeper — Departments
(function () {
  'use strict';

  // Focus the "Add department" input on load
  document.addEventListener('DOMContentLoaded', function () {
    var addInput = document.querySelector('form.mb-4 input[name="name"]');
    if (addInput) addInput.focus();

    // Prevent double-submit + trim values on all dept forms (add + edit)
    var forms = document.querySelectorAll('form.mb-4, .border.p-3.mb-3 form');
    forms.forEach(function (f) {
      var submitting = false;
      f.addEventListener('submit', function (e) {
        // Trim the name field
        var name = f.querySelector('input[name="name"]');
        if (name) name.value = (name.value || '').trim();

        // Basic required check (server still validates)
        if (name && !name.value) {
          e.preventDefault();
          name.focus();
          return;
        }

        // Block double submit
        if (submitting) {
          e.preventDefault();
          return;
        }
        submitting = true;

        // Disable buttons for a beat
        f.querySelectorAll('button[type="submit"]').forEach(function (btn) {
          btn.disabled = true;
        });
      });
    });

    // If a Delete link somehow misses inline confirm, add a safety net
    document.body.addEventListener('click', function (e) {
      var el = e.target;
      if (el.matches('a.btn.btn-danger')) {
        if (!confirm('Are you sure you want to delete this department?')) {
          e.preventDefault();
        }
      }
    });
  });
})();
