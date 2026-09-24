<?php

require_once '../includes/auth.php';

requireLogin();

$pageTitle = 'All Staff';

require_once '../includes/header.php';


$search = trim($_GET['search'] ?? '');


if ($search !== '') {

    $searchTerm = "%{$search}%";

    $stmt = $conn->prepare("
        SELECT *
        FROM staffs
        WHERE
            staff_code LIKE ?
            OR first_name LIKE ?
            OR last_name LIKE ?
            OR phone LIKE ?
            OR email LIKE ?
            OR department LIKE ?
            OR position LIKE ?
        ORDER BY id DESC
    ");

    $stmt->bind_param(
        "sssssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

    $stmt->execute();

    $staffResult = $stmt->get_result();

} else {

    $staffResult = $conn->query("
        SELECT *
        FROM staffs
        ORDER BY id DESC
    ");

}

?>


<div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
">

    <div>

        <h2>
            All Staff
        </h2>

        <p style="
            color:#6b7280;
            margin-top:5px;
        ">
            Staff directory and employee records.
        </p>

    </div>


    <a href="add.php"
       style="
           background:#2563eb;
           color:#ffffff;
           padding:11px 16px;
           border-radius:7px;
           font-weight:bold;
       ">

        + Add Staff

    </a>

</div>


<div class="dashboard-card">

    <!-- Search -->

    <form method="GET"
          style="
              display:flex;
              gap:10px;
              margin-bottom:20px;
          ">

        <input
            type="text"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search staff..."
            style="
                flex:1;
                padding:11px;
                border:1px solid #d1d5db;
                border-radius:7px;
                outline:none;
            "
        >

        <button
            type="submit"
            style="
                background:#2563eb;
                color:white;
                border:none;
                padding:11px 18px;
                border-radius:7px;
                cursor:pointer;
            "
        >
            Search
        </button>


        <?php if ($search !== ''): ?>

            <a href="list.php"
               style="
                   background:#e5e7eb;
                   padding:11px 18px;
                   border-radius:7px;
               ">

                Clear

            </a>

        <?php endif; ?>

    </form>


    <!-- Staff Table -->

    <div style="overflow-x:auto;">

        <table style="
            width:100%;
            border-collapse:collapse;
        ">

            <thead>

                <tr style="
                    text-align:left;
                    border-bottom:1px solid #e5e7eb;
                ">

                    <th style="padding:12px;">
                        Code
                    </th>

                    <th style="padding:12px;">
                        Name
                    </th>

                    <th style="padding:12px;">
                        Phone
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

            <?php if ($staffResult && $staffResult->num_rows > 0): ?>

                <?php while ($staff = $staffResult->fetch_assoc()): ?>

                    <tr style="
                        border-bottom:1px solid #f0f0f0;
                    ">

                        <td style="padding:12px;">
                            <?= e($staff['staff_code']) ?>
                        </td>

                        <td style="padding:12px;">

                            <strong>

                                <?= e(
                                    trim(
                                        $staff['first_name'] . ' ' .
                                        $staff['last_name']
                                    )
                                ) ?>

                            </strong>

                        </td>

                        <td style="padding:12px;">
                            <?= e($staff['phone'] ?: '-') ?>
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
                                    color:#166534;
                                    background:#dcfce7;
                                    padding:5px 9px;
                                    border-radius:20px;
                                    font-size:12px;
                                ">
                                    Active
                                </span>

                            <?php else: ?>

                                <span style="
                                    color:#991b1b;
                                    background:#fee2e2;
                                    padding:5px 9px;
                                    border-radius:20px;
                                    font-size:12px;
                                ">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>

                        <td style="padding:12px;">

                            <a
                                href="view.php?id=<?= $staff['id'] ?>"
                                style="
                                    color:#2563eb;
                                    font-weight:bold;
                                "
                            >
                                View
                            </a>

                            &nbsp;|&nbsp;

                            <a
                                href="edit.php?id=<?= $staff['id'] ?>"
                                style="
                                    color:#374151;
                                "
                            >
                                Edit
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="7"
                        style="
                            padding:30px;
                            text-align:center;
                            color:#6b7280;
                        "
                    >

                        No staff records found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php

require_once '../includes/footer.php';

?>