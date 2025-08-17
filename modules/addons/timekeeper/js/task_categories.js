// Timekeeper — Task Categories
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Focus the add-name input
    var addName = document.querySelector('form.mb-4 input[name="name"]');
    if (addName) addName.focus();

    // Trim + prevent double submit on all forms (add + edit)
    var forms = document.querySelectorAll('form.mb-4, .border.p-3.mb-3 form');
    forms.forEach(function (f) {
      var submitting = false;
      f.addEventListener('submit', function (e) {
        var name = f.querySelector('input[name="name"]');
        if (name) name.value = (name.value || '').trim();
        if (name && !name.value) { e.preventDefault(); name.focus(); return; }
        if (submitting) { e.preventDefault(); return; }
        submitting = true;
        f.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
      });
    });

    // Safety-net confirm for Delete links
    document.body.addEventListener('click', function (e) {
      var el = e.target;
      if (el.matches('a.btn.btn-danger')) {
        if (!confirm('Are you sure you want to delete this task category?')) {
          e.preventDefault();
        }
      }
    });
  });
})();
