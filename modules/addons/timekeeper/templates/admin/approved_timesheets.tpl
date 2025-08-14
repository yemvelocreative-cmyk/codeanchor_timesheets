<h2>Approved Timesheets</h2>
<?php if (empty($approvedTimesheets)): ?>
    <div style="margin-top: 20px; background-color: #e2f0d9; padding: 10px; border: 1px solid #a2d28f;">
        No approved timesheets found.
    </div>
<?php else: ?>
    <div style="display: flex; font-weight: bold; gap: 8px; border-bottom: 2px solid #ccc; padding: 10px 0;">
        <div style="width: 200px;">Admin</div>
        <div style="width: 150px;">Date</div>
        <div style="width: 100px;">Status</div>
        <div style="width: 100px;">Actions</div>
    </div>
    <?php foreach ($approvedTimesheets as $ts): ?>
        <div style="display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #eee;">
            <div style="width: 200px;"><?= htmlspecialchars($adminMap[$ts->admin_id] ?? 'Unknown') ?></div>
            <div style="width: 150px;"><?= htmlspecialchars($ts->timesheet_date) ?></div>
            <div style="width: 100px;">Approved</div>
            <div style="width: 100px; display: flex; gap: 4px; align-items: center;">
                <a href="addonmodules.php?module=timekeeper&timekeeperpage=approved_timesheets&admin_id=<?= $ts->admin_id ?>&date=<?= $ts->timesheet_date ?>"
                   class="btn btn-sm btn-primary"
                   style="display: inline-block; vertical-align: middle; margin-right: 2px;">View Timesheet</a>
                <?php if ($canApprove): ?>
                    <form method="post"
                          action="addonmodules.php?module=timekeeper&timekeeperpage=approved_timesheets"
                          style="display: inline-block; vertical-align: middle; margin: 0; padding: 0;">
                        <input type="hidden" name="unapprove_id" value="<?= $ts->id ?>">
                        <button type="submit"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Are you sure you want to unapprove this timesheet?');"
                                style="display: inline-block; vertical-align: middle; margin: 0;">Unapprove</button>
                    </form>
                <?php endif; ?>
            </div>

        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php if (isset($_GET['unapproved']) && $_GET['unapproved'] == 1): ?>
    <div style="background: #fff3cd; padding: 10px; border: 1px solid #ffeeba; color: #856404; margin-bottom: 16px;">
        Timesheet successfully unapproved.
    </div>
<?php endif; ?>

<?php if ($editMode): ?>
    <div style="margin-top: 30px;">
        <h4>Viewing Approved Timesheet: <?= $editAdminName ?> — <?= $editTimesheetDate ?></h4>

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
            </div>

            <?php foreach ($editTimesheetEntries as $entry): ?>
                <div style="display: flex; gap: 8px; padding: 6px 0; border-bottom: 1px solid #eee; align-items: center;">
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
                </div>
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
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
