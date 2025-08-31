// modules/addons/timekeeper/js/dashboard.js
(function () {
  'use strict';

  function hydrateProgressBars() {
    var bars = document.querySelectorAll('.dashboard-root .progress-bar[data-width]');
    bars.forEach(function (bar) {
      var pct = parseFloat(bar.getAttribute('data-width'));
      if (isNaN(pct)) pct = 0;
      if (pct < 0) pct = 0;
      if (pct > 100) pct = 100;
      bar.style.width = pct + '%';
    });
  }

  function applyDatePreset(range) {
    var today = new Date();
    var from = document.querySelector('.dash-filters input[name="from"]');
    var to   = document.querySelector('.dash-filters input[name="to"]');

    function fmt(d){ return d.toISOString().slice(0,10); }

    if (range === 'today') {
      var d = fmt(today);
      from.value = d; to.value = d;
    } else if (range === 'week') {
      var day = today.getDay(); // 0=Sun
      var diffToMon = (day === 0 ? -6 : 1 - day);
      var monday = new Date(today); monday.setDate(today.getDate() + diffToMon);
      var sunday = new Date(monday); sunday.setDate(monday.getDate() + 6);
      from.value = fmt(monday); to.value = fmt(sunday);
    } else if (range === 'month') {
      var y = today.getFullYear(), m = today.getMonth();
      var start = new Date(y, m, 1);
      var end   = new Date(y, m + 1, 0);
      from.value = fmt(start); to.value = fmt(end);
    } else if (range === 'lastmonth') {
      var y = today.getFullYear(), m = today.getMonth() - 1;
      var start = new Date(y, m, 1);
      var end   = new Date(y, m + 1, 0);
      from.value = fmt(start); to.value = fmt(end);
    }
  }

  function initPresets() {
    document.querySelectorAll('.js-preset').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyDatePreset(btn.getAttribute('data-range'));
      });
    });
  }

  // Minimal sparkline renderer (no external libs)
  function drawSparkline(canvas) {
    var ctx = canvas.getContext('2d');
    var labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
    var series = JSON.parse(canvas.getAttribute('data-series') || '[]'); // [ [y...], [y...] ]

    if (!labels.length || !series.length) return;

    var w = canvas.width, h = canvas.height;
    ctx.clearRect(0,0,w,h);

    // Compute bounds
    var all = [];
    series.forEach(function (s){ all = all.concat(s); });
    var min = Math.min.apply(null, all);
    var max = Math.max.apply(null, all);
    if (min === max) { min = 0; } // flatline support

    function x(i){ return (labels.length === 1) ? 0 : (i/(labels.length-1))* (w-16) + 8; }
    function y(v){ 
      if (max === min) return h/2;
      var t = (v - min) / (max - min);
      return h - 12 - t * (h - 24);
    }

    // draw axes baseline
    ctx.lineWidth = 1;
    ctx.strokeStyle = 'rgba(0,0,0,0.12)';
    ctx.beginPath(); ctx.moveTo(8, h-12); ctx.lineTo(w-8, h-12); ctx.stroke();

    // draw each series
    series.forEach(function (s, idx) {
      ctx.beginPath();
      ctx.lineWidth = 2;
      // default colors (let browser choose) — we do not set specific colors per instructions unless asked
      for (var i=0;i<s.length;i++) {
        var px = x(i), py = y(s[i]);
        if (i===0) ctx.moveTo(px,py); else ctx.lineTo(px,py);
      }
      ctx.stroke();
    });
  }

  function initSparklines() {
    document.querySelectorAll('.dashboard-root canvas.tk-spark').forEach(drawSparkline);
  }

  function ready(fn){
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    hydrateProgressBars();
    initPresets();
    initSparklines();
  });
})();
