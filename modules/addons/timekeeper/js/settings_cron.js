// Cron Settings — Checkbox model (no dual-select)
(function () {
  'use strict';

  var scope = document.querySelector('.tk-card[data-scope="cron-users"]');
  if (!scope) return;

  var toggler = scope.querySelector('.js-cronusers-toggleall');
  var chips   = Array.prototype.slice.call(scope.querySelectorAll('.js-cronuser'));
  var countEl = scope.querySelector('.js-cronusers-count');

  function updateCount() {
    var selected = chips.filter(function (c) { return c.checked; }).length;
    if (countEl) countEl.textContent = 'Selected: ' + selected;
    if (toggler) toggler.checked = (selected === chips.length && chips.length > 0);
    if (toggler) toggler.indeterminate = (selected > 0 && selected < chips.length);
  }

  if (toggler) {
    toggler.addEventListener('change', function () {
      var checked = !!toggler.checked;
      chips.forEach(function (c) { c.checked = checked; });
      updateCount();
    });
  }

  chips.forEach(function (c) {
    c.addEventListener('change', updateCount);
  });

  // initial state
  updateCount();
})();
