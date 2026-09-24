<?php

require_once '../includes/auth.php';

requireLogin();

$pageTitle = 'Staffs';

require_once '../includes/header.php';


/*
|--------------------------------------------------------------------------
| Staff Statistics
|--------------------------------------------------------------------------
*/

$totalStaff = 0;
$activeStaff = 0;
$inactiveStaff = 0;


$result = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Active') AS active,
        SUM(status = 'Inactive') AS inactive
    FROM staffs
");

if ($result) {

    $stats = $result->fetch_assoc();

    $totalStaff = (int)($stats['total'] ?? 0);
    $activeStaff = (int)($stats['active'] ?? 0);
    $inactiveStaff = (int)($stats['inactive'] ?? 0);

}

?>


<!-- Page Header -->

<div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
">

    <div>

        <h2>
            Staff Management
        </h2>

        <p style="
            color:#6b7280;
            margin-top:5px;
        ">
            Manage employees and system users.
        </p>

    </div>


    <a href="add.php"
       style="
           background:#2563eb;
           color:white;
           padding:11px 16px;
           border-radius:7px;
           font-weight:bold;
       ">

        + Add Staff

    </a>

</div>


<!-- Statistics -->

<div class="dashboard-grid">


    <div class="dashboard-card">

        <h3>
            TOTAL STAFF
        </h3>

        <div class="value">
            <?= $totalStaff ?>
        </div>

    </div>


    <div class="dashboard-card">

        <h3>
            ACTIVE STAFF
        </h3>

        <div class="value">
            <?= $activeStaff ?>
        </div>

    </div>


    <div class="dashboard-card">

        <h3>
            INACTIVE STAFF
        </h3>

        <div class="value">
            <?= $inactiveStaff ?>
        </div>

    </div>


    <div class="dashboard-card">

        <h3>
            SYSTEM USERS
        </h3>

        <div class="value">

            <?php

            $result = $conn->query("
                SELECT COUNT(*) AS total
                FROM users
                WHERE status = 'Active'
            ");

            echo $result
                ? $result->fetch_assoc()['total']
                : 0;

            ?>

        </div>

    </div>

</div>


<!-- Staff List -->

<div class="dashboard-card">

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:20px;
    ">

        <h2>
            Staff List
        </h2>

        <a href="list.php"
           style="
               color:#2563eb;
               font-size:14px;
               font-weight:bold;
           ">

            View All

        </a>

    </div>


    <?php

    $staffResult = $conn->query("
        SELECT
            id,
            staff_code,
            first_name,
            last_name,
            department,
            position,
            phone,
            status
        FROM staffs
        ORDER BY id DESC
        LIMIT 10
    ");

    ?>


    <?php if ($staffResult && $staffResult->num_rows > 0): ?>

        <div style="overflow-x:auto;">

            <table style="
                width:100%;
                border-collapse:collapse;
            ">

                <thead>

                    <tr style="
                        border-bottom:1px solid #e5e7eb;
                        text-align:left;
                    ">

                        <th style="padding:12px;">
                            Staff Code
                        </th>

                        <th style="padding:12px;">
                            Name
                        </th>

                        <th style="padding:12px;">
                            Department
                        </th>

                        <th style="padding:12px;">
                            Position
                        </th>

                        <th style="padding:12px;">
                            Status
                        </th>

                        <th style="padding:12px;">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php while ($staff = $staffResult->fetch_assoc()): ?>

                    <tr style="
                        border-bottom:1px solid #f0f0f0;
                    ">

                        <td style="padding:12px;">
                            <?= e($staff['staff_code']) ?>
                        </td>

                        <td style="padding:12px;">

                            <?= e(
                                trim(
                                    $staff['first_name'] . ' ' .
                                    $staff['last_name']
                                )
                            ) ?>

                        </td>

                        <td style="padding:12px;">
                            <?= e($staff['department'] ?: '-') ?>
                        </td>

                        <td style="padding:12px;">
                            <?= e($staff['position'] ?: '-') ?>
                        </td>

                        <td style="padding:12px;">

                            <?php if ($staff['status'] === 'Active'): ?>

                                <span style="
                                    background:#dcfce7;
                                    color:#166534;
                                    padding:5px 9px;
                                    border-radius:20px;
                                    font-size:12px;
                                ">
                                    Active
                                </span>

                            <?php else: ?>

                                <span style="
                                    background:#fee2e2;
                                    color:#991b1b;
                                    padding:5px 9px;
                                    border-radius:20px;
                                    font-size:12px;
                                ">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>

                        <td style="padding:12px;">

                            <a href="view.php?id=<?= $staff['id'] ?>"
                               style="
                                   color:#2563eb;
                                   font-weight:bold;
                               ">

                                View

                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <p style="
            color:#6b7280;
            padding:20px 0;
        ">

            No staff members have been added yet.

        </p>

    <?php endif; ?>

</div>


<?php

require_once '../includes/footer.php';

?>