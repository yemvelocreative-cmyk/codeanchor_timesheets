// Settings → Cron (dual select controls)
(function () {
  'use strict';

  var available = document.getElementById('availableUsers');
  var assigned  = document.getElementById('assignedUsers');
  var addBtn    = document.getElementById('addUser');
  var remBtn    = document.getElementById('removeUser');

  if (!available || !assigned || !addBtn || !remBtn) return;

  function moveSelected(from, to) {
    var opts = Array.prototype.slice.call(from.selectedOptions || []);
    if (!opts.length) return;
    opts.forEach(function (o) {
      // avoid duplicates
      var exists = Array.prototype.some.call(to.options, function (t) { return t.value === o.value; });
      if (!exists) {
        to.add(o); // also removes from 'from'
      } else {
        from.remove(o.index);
      }
    });
    sortOptions(to);
  }

  function sortOptions(select) {
    var arr = Array.prototype.slice.call(select.options);
    arr.sort(function (a, b) {
      return a.text.toLowerCase().localeCompare(b.text.toLowerCase());
    });
    select.innerHTML = '';
    arr.forEach(function (o) { select.add(o); });
  }

  // Buttons
  addBtn.addEventListener('click', function () { moveSelected(available, assigned); });
  remBtn.addEventListener('click', function () { moveSelected(assigned, available); });

  // Keyboard support: Enter/Space to move between lists
  function keyMoveHandler(from, to) {
    return function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        moveSelected(from, to);
      }
    };
  }
  available.addEventListener('keydown', keyMoveHandler(available, assigned));
  assigned .addEventListener('keydown', keyMoveHandler(assigned, available));

  // On submit: ensure all assigned are selected so PHP receives full list
  var form = assigned;
  while (form && form.tagName !== 'FORM') form = form.parentElement;
  if (form) {
    form.addEventListener('submit', function () {
      for (var i = 0; i < assigned.options.length; i++) {
        assigned.options[i].selected = true;
      }
    });
  }
})();
