<h2>Departments</h2>
<p class="dept-intro">
  Create and manage departments. Active departments can be linked to task categories.
</p>

<!--MESSAGE-->

<!-- Add Department -->
<form method="post" class="mb-4">
  <input type="hidden" name="action" value="add">
  <div class="row">
    <div class="col-md-8 mb-2">
      <input
        type="text"
        name="name"
        class="form-control"
        placeholder="Department Name"
        required
      >
    </div>
    <div class="col-md-4 mb-2">
      <button type="submit" class="btn btn-primary w-100">Add</button>
    </div>
  </div>
</form>

<hr>

<!-- Active -->
<div class="tk-card">
  <div class="tk-card-header">
    <h4 class="tk-card-title">Active Departments</h4>
  </div>
  <div class="dept-rows">
    <!--DEPT_ROWS_ACTIVE-->
  </div>
</div>

<!-- Inactive (auto-hidden if empty) -->
<div class="tk-card" id="dept-inactive-card" style="display:none;">
  <div class="tk-card-header">
    <h4 class="tk-card-title">Inactive Departments</h4>
  </div>
  <div class="dept-rows">
    <!--DEPT_ROWS_INACTIVE-->
  </div>
</div>

<script>
  (function () {
    var card = document.getElementById('dept-inactive-card');
    if (!card) return;
    var rows = card.querySelector('.dept-rows');
    if (!rows || !rows.textContent.trim()) card.style.display = 'none';
  })();
</script>
