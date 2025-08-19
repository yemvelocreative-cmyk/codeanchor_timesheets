// Cron Settings — Dual Select logic (minimal)
(function () {
  'use strict';

  var available = document.getElementById('availableUsers');
  var assigned  = document.getElementById('assignedUsers');
  var addBtn    = document.getElementById('addUser');
  var remBtn    = document.getElementById('removeUser');
  var form      = (function (el) { while (el && el.tagName !== 'FORM') el = el.parentElement; return el; })(assigned);

  if (!available || !assigned || !addBtn || !remBtn) return;

  function sortOptions(select) {
    var arr = Array.prototype.slice.call(select.options);
    arr.sort(function (a, b) { return a.text.toLowerCase().localeCompare(b.text.toLowerCase()); });
    select.innerHTML = '';
    arr.forEach(function (o) { select.add(o); });
  }

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

  // Click controls
  addBtn.addEventListener('click', function () { moveSelected(available, assigned); });
  remBtn.addEventListener('click', function () { moveSelected(assigned, available); });

  // Double‑click to move quickly
  available.addEventListener('dblcli
