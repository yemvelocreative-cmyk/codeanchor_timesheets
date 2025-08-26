<h2>Task Categories</h2>
<p class="tc-intro">
  Create and manage task categories, each linked to a department.
</p>

<!--MESSAGE-->

<!-- Add Task Category -->
<form method="post" class="mb-4">
  <input type="hidden" name="action" value="add">
  <div class="row">
    <div class="col-md-5 mb-2">
      <input
        type="text"
        name="name"
        class="form-control"
        placeholder="Task Category Name"
        required
      >
    </div>
    <div class="col-md-4 mb-2">
      <select
        name="department_id"
        class="form-control"
        required
      >
        <!--DEPARTMENT_OPTIONS-->
      </select>
    </div>
    <div class="col-md-3 mb-2">
      <button type="submit" class="btn btn-primary w-100">Add</button>
    </div>
  </div>
</form>

<hr>

<h3>Existing Task Categories</h3>

<!--TASK_CATEGORY_ROWS-->
