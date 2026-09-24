<?php

require_once '../includes/auth.php';

requireLogin();

$pageTitle = 'Add Staff';

$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $dateJoined = $_POST['date_joined'] ?? '';


    if ($firstName === '') {

        $error = 'First name is required.';

    } else {

        $staffCode = generateStaffCode($conn);

        $stmt = $conn->prepare("
            INSERT INTO staffs
            (
                staff_code,
                first_name,
                last_name,
                phone,
                email,
                department,
                position,
                date_joined,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");

        $stmt->bind_param(
            "ssssssss",
            $staffCode,
            $firstName,
            $lastName,
            $phone,
            $email,
            $department,
            $position,
            $dateJoined
        );


        if ($stmt->execute()) {

            $staffId = $conn->insert_id;


            recordAuditLog(
                $conn,
                currentUserId(),
                'Create',
                'Staffs',
                $staffId,
                "Created staff member {$staffCode}"
            );


            header(
                "Location: view.php?id=" .
                $staffId .
                "&created=1"
            );

            exit;

        } else {

            $error = 'Unable to create staff record.';

        }

    }

}

require_once '../includes/header.php';

?>


<div style="
    max-width:900px;
    margin:auto;
">


    <div style="margin-bottom:20px;">

        <h2>
            Add Staff
        </h2>

        <p style="
            color:#6b7280;
            margin-top:5px;
        ">
            Create a new staff record.
        </p>

    </div>


    <?php if ($error): ?>

        <div style="
            background:#fee2e2;
            color:#991b1b;
            padding:12px;
            border-radius:7px;
            margin-bottom:20px;
        ">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <div class="dashboard-card">


        <form method="POST">


            <div style="
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:20px;
            ">


                <div class="form-group">

                    <label>
                        First Name *
                    </label>

                    <input
                        type="text"
                        name="first_name"
                        required
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #d1d5db;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Last Name
                    </label>

                    <input
                        type="text"
                        name="last_name"
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #d1d5db;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Phone
                    </label>

                    <input
                        type="text"
                        name="phone"
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #d1d5db;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #d1d5db;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Department
                    </label>

                    <input
                        type="text"
                        name="department"
                        placeholder="e.g. Finance, Operations, IT"
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #d1d5db;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Position
                    </label>

                    <input
                        type="text"
                        name="position"
                        placeholder="e.g. Accountant"
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #d1d5db;
                            border-radius:7px;
                        "
                    >

                </div>


                <div class="form-group">

                    <label>
                        Date Joined
                    </label>

                    <input
                        type="date"
                        name="date_joined"
                        value="<?= date('Y-m-d') ?>"
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #d1d5db;
                            border-radius:7px;
                        "
                    >

                </div>


            </div>


            <div style="
                margin-top:25px;
                display:flex;
                gap:10px;
            ">

                <button
                    type="submit"
                    style="
                        background:#2563eb;
                        color:#fff;
                        border:none;
                        padding:12px 20px;
                        border-radius:7px;
                        cursor:pointer;
                        font-weight:bold;
                    "
                >
                    Save Staff
                </button>


                <a
                    href="list.php"
                    style="
                        background:#e5e7eb;
                        padding:12px 20px;
                        border-radius:7px;
                    "
                >
                    Cancel
                </a>

            </div>


        </form>

    </div>

</div>


<?php

require_once '../includes/footer.php';

?>