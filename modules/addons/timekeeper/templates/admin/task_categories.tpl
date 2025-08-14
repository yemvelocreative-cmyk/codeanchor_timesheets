<h2>Task Categories</h2>
<!--MESSAGE-->
<form method="post" class="mb-4">
    <input type="hidden" name="action" value="add">
    <div class="row" style="width: 50%;">
        <div class="col-md-6">
            <input type="text" name="name" class="form-control" placeholder="Task Category Name" required oninvalid="this.setCustomValidity('Required field. Please provide a task category name.')" oninput="this.setCustomValidity('')">
        </div>
        <div class="col-md-3">
            <select name="department_id" class="form-control" required oninvalid="this.setCustomValidity('Please select a department to assign this task category.')" oninput="this.setCustomValidity('')">
                <!--DEPARTMENT_OPTIONS-->
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Add</button>
        </div>
    </div>
</form>

<hr>

<h3>Existing Task Categories</h3>

<!--TASK_CATEGORY_ROWS-->
