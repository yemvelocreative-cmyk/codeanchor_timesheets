// modules/addons/timekeeper/js/dashboard.js
// Timekeeper Dashboard
(function () {
  'use strict';

  // Hydrate progress bars from data-width attributes (no inline CSS in markup)
  function hydrateProgressBars() {
    var bars = document.querySelectorAll('.dashboard-root .progress-bar[data-width]');
    bars.forEach(function (bar) {
      var pct = parseFloat(bar.getAttribute('data-width'));
      if (isNaN(pct)) pct = 0;
      if (pct < 0) pct = 0;
      if (pct > 100) pct = 100;
      // apply width via JS (allowed), avoids inline CSS in PHP/HTML
      bar.style.width = pct + '%';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hydrateProgressBars);
  } else {
    hydrateProgressBars();
  }

  // Placeholder example for future interactions:
  // document.querySelectorAll('.dashboard-root .some-button')
  //   .forEach(btn => btn.addEventListener('click', () => { /* ... */ }));
})();
