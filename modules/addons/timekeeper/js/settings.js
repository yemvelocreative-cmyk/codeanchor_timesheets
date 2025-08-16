(function () {
  'use strict';

  const ids = {
    available: 'availableUsers',     // <select multiple>
    assigned: 'assignedUsers',       // <select multiple>
    hidden:   'assignedUsersInput',  // <input type="hidden" name="assigned_users">
    saveBtn:  'saveSettingsButton',
    add:      'btnAdd',              // >
    addAll:   'btnAddAll',           // >>
    remove:   'btnRemove',           // <
    removeAll:'btnRemoveAll'         // <<
  };

  function on(elId, evt, fn) {
    const el = document.getElementById(elId);
    if (el) el.addEventListener(evt, fn);
  }

  on(ids.add,      'click', () => Timekeeper.moveSelected(ids.available, ids.assigned));
  on(ids.addAll,   'click', () => Timekeeper.moveAll(ids.available, ids.assigned));
  on(ids.remove,   'click', () => Timekeeper.moveSelected(ids.assigned, ids.available));
  on(ids.removeAll,'click', () => Timekeeper.moveAll(ids.assigned, ids.available));

  const avail  = document.getElementById(ids.available);
  const assign = document.getElementById(ids.assigned);
  if (avail)  avail.addEventListener('dblclick', () => Timekeeper.moveSelected(ids.available, ids.assigned));
  if (assign) assign.addEventListener('dblclick', () => Timekeeper.moveSelected(ids.assigned, ids.available));

  if (assign) assign.addEventListener('change', () => Timekeeper.sortSelect(assign));

  on(ids.saveBtn, 'click', () => {
    Timekeeper.serializeValues(ids.assigned, ids.hidden);
  });

  if (avail)  Timekeeper.sortSelect(avail);
  if (assign) Timekeeper.sortSelect(assign);
})();
