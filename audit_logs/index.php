<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();

if (!isHostUser()) {
    http_response_code(403);
    exit('Host access required.');
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1>Audit Logs</h1>
        <p>Review platform activity and security audit records.</p>
    </div>
    <div class="card">
        <p>This platform workspace is enabled and protected for host administrators. Connect its records to the corresponding platform workflow as data is introduced.</p>
    </div>
</main>
