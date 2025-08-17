<h2>Task Categories</h2>
<p style="margin-top:4px;font-size:14px;color:#555;">
  Create and manage task categories, each linked to a department.
</p>

<!--MESSAGE-->

<!-- Add Task Category -->
<form method="post" class="mb-4">
  <input type="hidden" name="action" value="add">
  <div class="row" style="width:50%;max-width:760px;">
    <div class="col-md-5 mb-2">
      <input type="text" name="name" class="form-control" placeholder="Task Category Name" required
             oninvalid="this.setCustomValidity('Required field. Please provide a task category name.')"
             oninput="this.setCustomValidity('')">
    </div>
    <div class="col-md-4 mb-2">
      <select name="department_id" class="form-control" required
              oninvalid="this.setCustomValidity('Please select a department.')"
              oninput="this.setCustomValidity('')">
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
