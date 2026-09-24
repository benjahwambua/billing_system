<?php

require_once '../includes/auth.php';

requireLogin();

$pageTitle = 'Communication';

require_once '../includes/header.php';

?>

<div class="dashboard-card">

    <h2>Communication</h2>

    <p style="margin-top: 10px; color: #6b7280;">
        Communication management module.
    </p>

</div>

<?php

require_once '../includes/footer.php';

?>