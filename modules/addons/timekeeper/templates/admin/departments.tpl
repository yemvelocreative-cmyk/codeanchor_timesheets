
<h2>Departments</h2>
<p style="margin-top: 4px; font-size: 14px; color: #555;">
    Type the department name in the field below and click 'Add' to save it.
</p>
<!--MESSAGE-->

<!-- Add Department Form -->
<form method="post" class="mb-4">
    <input type="hidden" name="action" value="add">
    <div class="row" style="width: 50%;">
        <div class="col-md-6">
            <input type="text" name="name" class="form-control" placeholder="Department Name" required oninvalid="this.setCustomValidity('Required field. Please provide a department name.')" oninput="this.setCustomValidity('')">
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Add</button>
        </div>
    </div>
</form>

<hr>

<h3>Existing Departments</h3>

<!--DEPARTMENT_ROWS-->
