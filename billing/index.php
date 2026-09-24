<?php

require_once '../includes/auth.php';

requireLogin();

$pageTitle = 'Billing';

require_once '../includes/header.php';

?>

<div class="dashboard-card">

    <h2>Billing</h2>

    <p style="margin-top: 10px; color: #6b7280;">
        Billing management module.
    </p>

</div>

<?php

require_once '../includes/footer.php';

?>