// Timekeeper — Task Categories
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Focus the add-name input
    var addName = document.querySelector('form.mb-4 input[name="name"]');
    if (addName) addName.focus();

    // Custom validity helpers (replaces inline oninvalid/oninput)
    function installCustomValidity(input, message) {
      if (!input) return;
      input.addEventListener('invalid', function () {
        if (!input.validity.valid) input.setCustomValidity(message);
      });
      input.addEventListener('input', function () {
        input.setCustomValidity('');
      });
      if (input.tagName === 'SELECT') {
        input.addEventListener('change', function () {
          input.setCustomValidity('');
        });
      }
    }

    installCustomValidity(
      document.querySelector('form.mb-4 input[name="name"]'),
      'Required field. Please provide a task category name.'
    );
    installCustomValidity(
      document.querySelector('form.mb-4 select[name="department_id"]'),
      'Please select a department.'
    );

    // Trim + prevent double submit on all forms (add + edit)
    var forms = document.querySelectorAll('form.mb-4, .border.p-3.mb-3 form');
    forms.forEach(function (f) {
      var submitting = false;

      // ensure edit rows also clear custom validity if needed
      var nameInput = f.querySelector('input[name="name"]');
      var deptSelect = f.querySelector('select[name="department_id"]');
      installCustomValidity(nameInput, 'Required field. Please provide a task category name.');
      installCustomValidity(deptSelect, 'Please select a department.');

      f.addEventListener('submit', function (e) {
        if (nameInput) nameInput.value = (nameInput.value || '').trim();
        if (nameInput && !nameInput.value) { e.preventDefault(); nameInput.focus(); return; }
        if (submitting) { e.preventDefault(); return; }
        submitting = true;
        f.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
      });
    });

    // Safety-net confirm for Delete links (no inline onclick anymore)
    document.body.addEventListener('click', function (e) {
      var el = e.target.closest('a.btn.btn-danger');
      if (!el) return;
      if (!/(\?|&)delete=\d+/.test(el.getAttribute('href') || '')) return;
      if (!confirm('Are you sure you want to delete this task category?')) {
        e.preventDefault();
      }
    });
  });
})();
