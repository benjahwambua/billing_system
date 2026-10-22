<?php

require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('operations', 'view');

$pageTitle = 'Operations';

require_once '../includes/header.php';

?>

<div class="dashboard-card">

    <h2>Operations</h2>

    <p style="margin-top: 10px; color: #6b7280;">
        Operations management module.
    </p>

</div>

<?php

require_once '../includes/footer.php';

?>