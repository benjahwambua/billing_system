<?php

require_once '../includes/auth.php';

requireLogin();

$pageTitle = 'Settings';

require_once '../includes/header.php';

?>

<div class="dashboard-card">

    <h2>Settings</h2>

    <p style="margin-top: 10px; color: #6b7280;">
        System settings module.
    </p>

</div>

<?php

require_once '../includes/footer.php';

?>