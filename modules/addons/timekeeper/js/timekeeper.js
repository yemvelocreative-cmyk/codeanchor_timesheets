(function (window, document) {
  'use strict';

  function $(id) {
    return document.getElementById(id);
  }

  function hasOption(select, value) {
    if (!select) return false;
    for (let i = 0; i < select.options.length; i++) {
      if (select.options[i].value === value) return true;
    }
    return false;
  }

  function sortSelect(select) {
    if (!select) return;
    const opts = Array.from(select.options);
    opts.sort((a, b) => a.text.toLowerCase().localeCompare(b.text.toLowerCase()));
    opts.forEach(opt => select.appendChild(opt));
  }

  function moveSelected(fromId, toId, opts) {
    const options = Object.assign({ sort: true, dedupe: true }, opts);
    const from = $(fromId);
    const to = $(toId);
    if (!from || !to) return;

    const selected = Array.from(from.selectedOptions);
    if (selected.length === 0) return;

    selected.forEach(option => {
      if (options.dedupe && hasOption(to, option.value)) return;
      to.appendChild(option);
    });

    if (options.sort) sortSelect(to);
  }

  function moveAll(fromId, toId, opts) {
    const from = $(fromId);
    if (!from) return;
    for (let i = 0; i < from.options.length; i++) {
      from.options[i].selected = true;
    }
    moveSelected(fromId, toId, opts);
  }

  function serializeValues(selectId, hiddenInputId) {
    const select = $(selectId);
    const hidden = $(hiddenInputId);
    if (!select || !hidden) return;
    const values = Array.from(select.options).map(o => o.value);
    hidden.value = values.join(',');
  }

  window.Timekeeper = {
    moveSelected,
    moveAll,
    sortSelect,
    serializeValues
  };
})(window, document);
