<?php
require_once '../includes/auth.php'; requireLogin();
$pageTitle='Communication'; require_once '../includes/header.php';
?>
<div class="dashboard-card"><h2>Communication Centre</h2><p style="color:#6b7280">Tenant-scoped customer communication.</p>
<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="sms.php">SMS</a><a class="btn" href="whatsapp.php">WhatsApp</a><a class="btn" href="email.php">Email</a><a class="btn" href="templates.php">Templates</a></div></div>
<?php require_once '../includes/footer.php'; ?>