// Timekeeper — Departments
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Focus the "Add department" input on load
    var addInput = document.querySelector('form.mb-4 input[name="name"]');
    if (addInput) addInput.focus();

    // Helper: add custom validity messages (replaces inline oninvalid/oninput)
    function installCustomValidity(input, message) {
      if (!input) return;
      input.addEventListener('invalid', function () {
        if (!input.validity.valid) input.setCustomValidity(message);
      });
      input.addEventListener('input', function () {
        input.setCustomValidity('');
      });
    }

    // Forms: trim, required guard, double-submit guard
    var forms = document.querySelectorAll('form.mb-4, .dept-rows form');
    forms.forEach(function (f) {
      var submitting = false;
      var name = f.querySelector('input[name="name"]');

      installCustomValidity(name, 'Required field. Please provide a department name.');

      f.addEventListener('submit', function (e) {
        if (name) name.value = (name.value || '').trim();
        if (name && !name.value) { e.preventDefault(); name.focus(); return; }
        if (submitting) { e.preventDefault(); return; }
        submitting = true;
        f.querySelectorAll('button[type="submit"]').forEach(function (btn) { btn.disabled = true; });
      });
    });

    // Safety-net confirm for Delete links (delegated; no inline onclick)
    document.body.addEventListener('click', function (e) {
      var link = e.target.closest('a.btn.btn-danger');
      if (!link) return;
      // only apply to department delete links (look for ?delete=ID)
      var href = link.getAttribute('href') || '';
      if (!/(\?|&)delete=\d+/.test(href)) return;
      if (!confirm('Are you sure you want to delete this department?')) {
        e.preventDefault();
      }
    });
  });
})();
