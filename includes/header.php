<?php

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

$companyName = getCompanyName($conn);

$userName = getLoggedInUserName($conn);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($pageTitle) ?> | <?= e($companyName) ?>
    </title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

</head>

<body>

<div class="app-container">

    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Application -->
    <div class="main-wrapper">

        <!-- Headbar -->
        <header class="headbar">

            <div class="headbar-left">

                <button class="sidebar-toggle"
                        id="sidebarToggle">
                    ☰
                </button>

                <div class="page-title">

                    <h1>
                        <?= e($pageTitle) ?>
                    </h1>

                </div>

            </div>


            <div class="headbar-right">

                <div class="notification-icon">
                    🔔
                </div>

                <div class="user-profile">

                    <div class="user-avatar">
                        <?= strtoupper(substr($userName ?: 'U', 0, 1)) ?>
                    </div>

                    <div class="user-info">

                        <strong>
                            <?= e($userName ?: 'User') ?>
                        </strong>

                        <small>
                            <?= e($_SESSION['user_role'] ?? 'User') ?>
                        </small>

                    </div>

                </div>

            </div>

        </header>


        <!-- Page Content -->

        <main class="page-content">