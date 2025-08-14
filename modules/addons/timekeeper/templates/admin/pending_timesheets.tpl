<h2>Pending Timesheets</h2>



<!-- Timesheet approved message -->

<?php if (isset($_GET['approved']) && $_GET['approved'] == 1): ?>

    <div style="background: #e1f3e1; padding: 10px; border: 1px solid #8ad08a; color: #256029; margin-bottom: 16px;">

        Timesheet approved successfully.

    </div>

<?php endif; ?>



<!-- Timesheet Rejected message -->

<?php if (isset($_GET['rejected']) && $_GET['rejected'] == 1): ?>

    <div style="background: #ffe1e1; padding: 10px; border: 1px solid #d08a8a; color: #8c1a1a; margin-bottom: 16px;">

        Timesheet rejected.

    </div>

<?php endif; ?>



<!-- Timesheet re-submit message -->

<?php if (isset($_GET['resubmitted']) && $_GET['resubmitted'] == 1): ?>

    <div style="background: #e1f3e1; padding: 10px; border: 1px solid #8ad08a; color: #256029; margin-bottom: 16px;">

        Timesheet re-submitted for approval.

    </div>

<?php endif; ?>



<?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>

    <div style="background: #e1f3e1; padding: 10px; border: 1px solid #8ad08a; color: #256029; margin-bottom: 16px;">

        New line added successfully.

    </div>

<?php endif; ?>



<?php if (isset($_GET['add_error']) && $_GET['add_error'] == 1): ?>

    <div style="background: #ffe1e1; padding: 10px; border: 1px solid #d08a8a; color: #8c1a1a; margin-bottom: 16px;">

        The End Time must be later than the Start Time.

    </div>

<?php endif; ?>



<?php if (empty($pendingTimesheets)): ?>

    <div style="margin-top: 20px; background-color: #e2f0d9; padding: 10px; border: 1px solid #a2d28f;">

        No pending timesheets found.

    </div>
<?php else: ?>
    <div style="display: flex; font-weight: bold; gap: 8px; border-bottom: 2px solid #ccc; padding: 10px 0;">
        <div style="width: 200px;">Admin</div>
        <div style="width: 150px;">Date</div>
        <div style="width: 100px;">Status</div>
        <div style="width: 100px;">Actions</div>
    </div>
    <?php foreach ($pendingTimesheets as $ts): ?>
        <div style="display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #eee;">
            <div style="width: 200px;"><?= htmlspecialchars($adminMap[$ts->admin_id] ?? 'Unknown') ?></div>
            <div style="width: 150px;"><?= htmlspecialchars($ts->timesheet_date) ?></div>
            <div style="width: 100px;"><?= ucfirst($ts->status) ?></div>
            <div style="width: 100px;">
                <a href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= $ts->admin_id ?>&date=<?= $ts->timesheet_date ?>" class="btn btn-sm btn-primary">View Timesheet</a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($editMode): ?>
    <div style="margin-top: 30px;">
        <?php if ($editingEntryId): ?>
            <h4>Editing Timesheet: <?= $editAdminName ?> — <?= $editTimesheetDate ?></h4>
        <?php else: ?>
            <h4>Viewing Timesheet: <?= $editAdminName ?> — <?= $editTimesheetDate ?></h4>
        <?php endif; ?>
        <!-- Display Admin Reject Note -->
        <?php if ($editMode && isset($timesheet) && $timesheet->status === 'rejected' && !empty($timesheet->admin_rejection_note)): ?>
            <div style="background: #ffe1e1; padding: 10px; margin: 8px 0 18px 0; border: 1px solid #d08a8a; color: #8c1a1a;">
                <strong>Reason for rejection:</strong><br>
                <?= nl2br(htmlspecialchars($timesheet->admin_rejection_note)) ?>
                <?php if (!empty($timesheet->rejected_at) || !empty($timesheet->rejected_by)): ?>
                    <br><span style="font-size:90%;color:#b00;">
                    <?php if (!empty($timesheet->rejected_at)): ?>
                        <strong>Rejected on:</strong> <?= htmlspecialchars($timesheet->rejected_at) ?>
                    <?php endif; ?>
                    <?php if (!empty($timesheet->rejected_by) && isset($adminMap[$timesheet->rejected_by])): ?>
                        &nbsp;<strong>by</strong> <?= htmlspecialchars($adminMap[$timesheet->rejected_by]) ?>
                    <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ===================== ADD NEW LINE (Admin) ===================== -->
        <div style="background:#f7f7f9; border:1px solid #ddd; padding:12px; border-radius:6px; margin:10px 0 18px;">
            <div style="font-weight:bold; margin-bottom:8px;">Add New Line</div>
            <form method="post" onsubmit="return validateAddRow();">
                <input type="hidden" name="add_new_entry" value="1">
                <input type="hidden" name="admin_id" value="<?= htmlspecialchars($editAdminId) ?>">
                <input type="hidden" name="timesheet_date" value="<?= htmlspecialchars($editTimesheetDate) ?>">
                <!-- Header (matches listing widths/order, incl. Time Spent) -->
                <div style="display: flex; font-weight: bold; gap: 8px; border-bottom: 2px solid #ccc; padding: 10px 0; margin-top: 0;">
                    <div style="width: 200px;">Client</div>
                    <div style="width: 180px;">Department</div>
                    <div style="width: 180px;">Task Category</div>
                    <div style="width: 90px;">Ticket ID</div>
                    <div style="width: 250px;">Description</div>
                    <div style="width: 90px;">Start</div>
                    <div style="width: 90px;">End</div>
                    <div style="width: 80px;">Time Spent</div>
                    <div style="width: 50px;">Billable</div>
                    <div style="width: 90px;">Billable Time</div>
                    <div style="width: 50px;">SLA</div>
                    <div style="width: 90px;">SLA Time</div>
                    <div style="width: 70px;"></div>
                </div>
                <div style="display: flex; gap: 8px; padding: 6px 0; align-items: center; border-bottom: 1px solid #eee;">
                    <!-- Client -->
                    <select name="client_id" style="width: 200px;">
                        <option value="">Select…</option>
                        <?php foreach ($clientMap as $id => $label): ?>
                            <option value="<?= (int)$id ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Department -->
                    <select name="department_id" id="pending-add-department" style="width: 180px;">
                        <option value="">Select…</option>
                        <?php foreach ($departmentMap as $id => $label): ?>
                            <option value="<?= (int)$id ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Task Category (filtered by department) -->
                    <select name="task_category_id" id="pending-add-task-category" style="width: 180px;">
                        <option value="">Select…</option>
                        <?php foreach ($taskCategories as $cat): ?>
                            <option value="<?= (int)$cat->id ?>" data-dept="<?= (int)$cat->department_id ?>"><?= htmlspecialchars($cat->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Ticket ID -->
                    <input type="text" name="ticket_id" style="width: 90px;">
                    <!-- Description -->
                    <input type="text" name="description" style="width: 250px;">
                    <!-- Start / End -->
                    <input type="time" name="start_time" id="pending-add-start" style="width: 90px;">
                    <input type="time" name="end_time" id="pending-add-end" style="width: 90px;">
                    <!-- Time Spent (auto, read-only, hours.decimal) -->
                    <input type="text" name="time_spent" id="pending-add-timespent" style="width: 80px;" readonly>
                    <!-- Billable / Billable Time -->
                    <input type="checkbox" name="billable" value="1" style="width: 50px;">
                    <input type="text" name="billable_time" style="width: 90px;">
                    <!-- SLA / SLA Time -->
                    <input type="checkbox" name="sla" value="1" style="width: 50px;">
                    <input type="text" name="sla_time" style="width: 90px;">
                    <!-- Add -->
                    <button type="submit" class="btn btn-sm btn-success" style="width: 70px;">Add</button>
                </div>
            </form>
        </div>
        <!-- =================== END ADD NEW LINE (Admin) =================== -->

        <?php if (empty($editTimesheetEntries)): ?>
            <div style="background: #fff3cd; padding: 10px; border: 1px solid #ffeeba;">
                No entries found for this timesheet.
            </div>
        <?php else: ?>
            <!-- Calculate Totals -->
            <?php
            $totalTime = 0;
            $totalBillableTime = 0;
            $totalSlaTime = 0;
            foreach ($editTimesheetEntries as $entry) {
                $totalTime += (float)$entry->time_spent;
                $totalBillableTime += (float)$entry->billable_time;
                $totalSlaTime += (float)$entry->sla_time;
            }
            ?>
            <!-- Label Headings Row -->
            <div style="display: flex; font-weight: bold; gap: 8px; border-bottom: 2px solid #ccc; padding: 10px 0; margin-top: 8px;">
                <div style="width: 200px;">Client</div>
                <div style="width: 180px;">Department</div>
                <div style="width: 180px;">Task Category</div>
                <div style="width: 90px;">Ticket ID</div>
                <div style="width: 250px;">Description</div>
                <div style="width: 90px;">Start</div>
                <div style="width: 90px;">End</div>
                <div style="width: 80px;">Time Spent</div>
                <div style="width: 50px;">Billable</div>
                <div style="width: 90px;">Billable Time</div>
                <div style="width: 50px;">SLA</div>
                <div style="width: 90px;">SLA Time</div>
                <div style="width: 70px;"></div>
            </div>
            <?php foreach ($editTimesheetEntries as $entry): ?>
                <?php $isEditing = ($editingEntryId == $entry->id); ?>
                <form method="post" style="display: flex; gap: 8px; padding: 6px 0; border-bottom: 1px solid #eee; align-items: center;">
                    <input type="hidden" name="save_id" value="<?= $entry->id ?>">
                    <input type="hidden" name="admin_id" value="<?= $editAdminId ?>">
                    <input type="hidden" name="timesheet_date" value="<?= $editTimesheetDate ?>">
                    <?php if ($isEditing): ?>
                        <select name="client_id" style="width: 200px;">
                            <?php foreach ($clientMap as $id => $label): ?>
                                <option value="<?= $id ?>" <?= $entry->client_id == $id ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="department_id" class="pending-edit-department" style="width: 180px;">
                            <?php foreach ($departmentMap as $id => $label): ?>
                                <option value="<?= $id ?>" <?= $entry->department_id == $id ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="task_category_id" class="pending-edit-task-category" style="width: 180px;">
                            <?php foreach ($taskCategories as $cat): ?>
                                <option value="<?= $cat->id ?>" data-dept="<?= $cat->department_id ?>" <?= $entry->task_category_id == $cat->id ? 'selected' : '' ?>><?= htmlspecialchars($cat->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="ticket_id" value="<?= htmlspecialchars($entry->ticket_id) ?>" style="width: 90px;">
                        <input type="text" name="description" value="<?= htmlspecialchars($entry->description) ?>" style="width: 250px;">
                        <input type="time" name="start_time" value="<?= $entry->start_time ?>" style="width: 90px;">
                        <input type="time" name="end_time" value="<?= $entry->end_time ?>" style="width: 90px;">
                        <input type="text" name="time_spent" value="<?= number_format($entry->time_spent, 2) ?>" style="width: 80px;" readonly>
                        <input type="checkbox" name="billable" value="1" <?= $entry->billable ? 'checked' : '' ?> style="width: 50px;" class="pending-billable-checkbox">
                        <input type="text" name="billable_time" value="<?= number_format($entry->billable_time, 2) ?>" style="width: 90px;">
                        <input type="checkbox" name="sla" value="1" <?= $entry->sla ? 'checked' : '' ?> style="width: 50px;" class="pending-sla-checkbox">
                        <input type="text" name="sla_time" value="<?= number_format($entry->sla_time, 2) ?>" style="width: 90px;">
                        <button type="submit" class="btn btn-sm btn-success" style="width: 70px;">Save</button>
                        <a href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= $editAdminId ?>&date=<?= $editTimesheetDate ?>" class="btn btn-sm btn-secondary" style="width: 70px;">Cancel</a>
                    <?php else: ?>
                        <div style="width: 200px;"><?= $clientMap[$entry->client_id] ?? 'N/A' ?></div>
                        <div style="width: 180px;"><?= $departmentMap[$entry->department_id] ?? 'N/A' ?></div>
                        <div style="width: 180px;"><?= $taskMap[$entry->task_category_id] ?? 'N/A' ?></div>
                        <div style="width: 90px;"><?= htmlspecialchars($entry->ticket_id) ?></div>
                        <div style="width: 250px;"><?= htmlspecialchars($entry->description) ?></div>
                        <div style="width: 90px;"><?= $entry->start_time ?></div>
                        <div style="width: 90px;"><?= $entry->end_time ?></div>
                        <div style="width: 80px;"><?= number_format($entry->time_spent, 2) ?> hrs</div>
                        <div style="width: 50px;"><?= $entry->billable ? 'Yes' : 'No' ?></div>
                        <div style="width: 90px;"><?= number_format($entry->billable_time, 2) ?> hrs</div>
                        <div style="width: 50px;"><?= $entry->sla ? 'Yes' : 'No' ?></div>
                        <div style="width: 90px;"><?= number_format($entry->sla_time, 2) ?> hrs</div>
                        <div style="width: 70px;">
                            <a href="addonmodules.php?module=timekeeper&timekeeperpage=pending_timesheets&admin_id=<?= $editAdminId ?>&date=<?= $editTimesheetDate ?>&edit_id=<?= $entry->id ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        </div>
                    <?php endif; ?>
                </form>
                <?php
                    $needsVerify = (
                        isset($unbilledTimeValidateMin) && $unbilledTimeValidateMin !== '' &&
                        floatval($entry->time_spent) >= $unbilledTimeValidateMin
                        && !$entry->billable
                        && !$entry->sla
                    );
                ?>
                <?php if ($canApprove && $needsVerify): ?>
                    <label style="display:block; color:#b00; margin-top:2px;">
                        <input type="checkbox" name="verify_unbilled_<?= $entry->id ?>" value="1" required>
                        Verify this unbilled/SLA-exempt time spent entry (<?= number_format($entry->time_spent,2) ?>h)
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Totals Row -->
            <div style="display: flex; gap: 8px; padding: 10px 0; border-top: 2px solid #ccc; font-weight: bold; background: #f5f5f5;">
				<div style="width: 200px;"></div>
                <div style="width: 180px;"></div>
                <div style="width: 180px;"></div>
                <div style="width: 90px;"></div>
                <div style="width: 250px; text-align: right;">Totals:</div>
                <div style="width: 90px;"><?= number_format($totalTime, 2) ?> hrs</div>
                <div style="width: 90px;"></div>
                <div style="width: 80px;"></div>
                <div style="width: 50px;"></div>
                <div style="width: 90px;"><?= number_format($totalBillableTime, 2) ?> hrs</div>
                <div style="width: 50px;"></div>
                <div style="width: 90px;"><?= number_format($totalSlaTime, 2) ?> hrs</div>
                <div style="width: 70px;"></div>
            </div>

            <!-- Approval/Reject Buttons -->
            <?php if ($canApprove && $editMode && isset($timesheet)): ?>
                <form method="post" style="margin-top: 12px; display: inline-block;">
                    <input type="hidden" name="approve_timesheet_id" value="<?= htmlspecialchars($timesheet->id) ?>">
                    <button type="submit" class="btn btn-success"
                        onclick="return confirm('Are you sure you want to approve this timesheet?');">
                        Approve Timesheet
                    </button>
                </form>
                <form method="post" style="margin-top: 12px; display: inline-block;" onsubmit="return confirmRejectNote(event);">
                    <input type="hidden" name="reject_timesheet_id" value="<?= htmlspecialchars($timesheet->id) ?>">
                    <textarea name="admin_rejection_note" placeholder="Rejection Note (required)" style="display:block; margin-bottom:6px; width:220px; height:40px;"></textarea>
                    <button type="submit" class="btn btn-danger">
                        Reject Timesheet
                    </button>
                </form>
                <script>
                function confirmRejectNote() {
                    var note = document.querySelector('textarea[name="admin_rejection_note"]');
                    if (note && note.value.trim() === '') {
                        alert('Please enter a reason for rejection.');
                        return false;
                    }
                    return confirm('Are you sure you want to reject this timesheet?');
                }
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Resubmit Button for rejected timesheet (not approvers) -->
        <?php
        // Show Resubmit if: viewing a rejected timesheet AND the logged-in admin is the owner of the timesheet
        if (isset($timesheet) && $timesheet->status === 'rejected' && $editAdminId == $_SESSION['adminid']):
        ?>
            <form method="post" style="margin-top: 24px;">
                <input type="hidden" name="resubmit_timesheet_id" value="<?= htmlspecialchars($timesheet->id) ?>">
                <button type="submit" class="btn btn-primary" style="min-width: 120px;">Re-Submit</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- PENDING TIMESHEET JS FIXES -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filter task categories by department for each edit row
    document.querySelectorAll('.pending-edit-department').forEach(function(deptSelect) {
        const form = deptSelect.closest('form');
        const taskSelect = form.querySelector('.pending-edit-task-category');
        function filterEditTasksByDepartment() {
            const selectedDept = deptSelect.value;
            Array.from(taskSelect.options).forEach(option => {
                const deptId = option.getAttribute('data-dept');
                option.style.display = (!deptId || deptId === selectedDept || option.value === "") ? 'block' : 'none';
            });
            if (
                taskSelect.value &&
                taskSelect.selectedOptions.length &&
				taskSelect.selectedOptions[0].style.display === 'none'
            ) {
                taskSelect.value = '';
            }
        }
        deptSelect.addEventListener('change', filterEditTasksByDepartment);
        filterEditTasksByDepartment();
    });

    // Filter for the Add New Line row
    var addDept = document.getElementById('pending-add-department');
    var addTask = document.getElementById('pending-add-task-category');
    function filterAddTasks() {
        if (!addDept || !addTask) return;
        var deptVal = addDept.value;
        Array.from(addTask.options).forEach(function(opt) {
            var d = opt.getAttribute('data-dept');
            opt.style.display = (!d || !deptVal || d === deptVal || opt.value === "") ? 'block' : 'none';
        });
        if (addTask.selectedIndex > 0 && addTask.options[addTask.selectedIndex].style.display === 'none') {
            addTask.value = '';
        }
    }
    if (addDept) {
        addDept.addEventListener('change', filterAddTasks);
        filterAddTasks();
    }

	// Auto-calc Time Spent (hours.decimal) on Start/End change for Add row
    var st = document.getElementById('pending-add-start');
    var et = document.getElementById('pending-add-end');
    var ts = document.getElementById('pending-add-timespent');

    function calcAddTimeSpent() {
        if (!st || !et || !ts || !st.value || !et.value) { if(ts) ts.value=''; return; }
        var s = st.value.split(':'), e = et.value.split(':');
        if (s.length < 2 || e.length < 2) { ts.value = ''; return; }
        var sMin = parseInt(s[0],10)*60 + parseInt(s[1],10);
        var eMin = parseInt(e[0],10)*60 + parseInt(e[1],10);
        ts.value = (eMin > sMin) ? ((eMin - sMin) / 60).toFixed(2) : '';
    }
    if (st) st.addEventListener('change', calcAddTimeSpent);
    if (et) et.addEventListener('change', calcAddTimeSpent);
});

// Client-side validation for Add row
function validateAddRow() {
    var st = document.getElementById('pending-add-start');
    var et = document.getElementById('pending-add-end');
    if (st && et && st.value && et.value) {
        var s = st.value.split(':'), e = et.value.split(':');
        var sMin = parseInt(s[0],10)*60 + parseInt(s[1],10);
        var eMin = parseInt(e[0],10)*60 + parseInt(e[1],10);
        if (!(eMin > sMin)) {
            alert('End Time must be later than Start Time.');
            return false;
        }
    }
    return true;
}
</script>
