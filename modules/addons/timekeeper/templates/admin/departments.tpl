<?php
/** @var string $message */
/** @var string $modulelink */
/** @var \Illuminate\Support\Collection|array $departments */
?>
<h2>Departments</h2>
<p style="margin-top: 4px; font-size: 14px; color: #555;">
    Type the department name in the field below and click “Add” to save it.
</p>

<?php if (!empty($message)) { echo $message; } ?>

<!-- Add Department -->
<form method="post" class="mb-4">
    <input type="hidden" name="action" value="add">
    <div class="row" style="width: 50%;">
        <div class="col-md-6">
            <input
                type="text"
                name="name"
                class="form-control"
                placeholder="Department Name"
                required
                oninvalid="this.setCustomValidity('Required field. Please provide a department name.')"
                oninput="this.setCustomValidity('')"
            >
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Add</button>
        </div>
    </div>
</form>

<hr>

<h3>Existing Departments</h3>

<?php if (empty($departments) || count($departments) === 0): ?>
  <div class="alert alert-info">No departments found.</div>
<?php else: ?>
  <?php foreach ($departments as $dept): ?>
    <form method="post" class="border p-3 mb-3 rounded bg-light" style="padding-bottom: 5px;">
      <div class="row align-items-center" style="width: 50%;">
        <div class="col-md-6 mb-2">
          <input
            type="text"
            name="name"
            value="<?= htmlspecialchars($dept->name, ENT_QUOTES, 'UTF-8') ?>"
            class="form-control"
            required
          >
        </div>
        <div class="col-md-6 d-flex gap-2 flex-wrap">
          <input type="hidden" name="id" value="<?= (int)$dept->id ?>">
          <input type="hidden" name="action" value="edit">
          <button type="submit" class="btn btn-success">Save</button>
          <a
            href="<?= htmlspecialchars($modulelink, ENT_QUOTES, 'UTF-8') ?>&delete=<?= (int)$dept->id ?>"
            class="btn btn-danger"
            style="margin-left:5px;"
            onclick="return confirm('Are you sure you want to delete this department?');"
          >Delete</a>
        </div>
      </div>
    </form>
  <?php endforeach; ?>
<?php endif; ?>
