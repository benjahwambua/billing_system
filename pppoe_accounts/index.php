<?php
require_once '../includes/auth.php';
requireActiveUser();
require_once '../includes/functions.php';
if (isTenantUser()) requireTenant();
global $conn;
$tableCols = static function ($table) use ($conn) {
    $out = [];
    $result = $conn->query("SHOW COLUMNS FROM `" . str_replace("`", "", $table) . "`");
    if ($result) {
        while ($column = $result->fetch_assoc()) {
            $out[] = $column['Field'];
        }
        $result->free();
    }
    return $out;
};
$cols = $tableCols('pppoe_accounts');
$internetCols = $tableCols('internet_accounts');
$customerCols = $tableCols('customers');
$has = static fn($column) => in_array($column, $cols, true);
$tid = (int)getCurrentTenantId();
if (!$cols) {
    http_response_code(503);
    exit('PPPoE accounts are unavailable because the database table could not be read.');
}
if (isTenantUser() && (!$has('tenant_id') || $tid <= 0 || !in_array('tenant_id', $internetCols, true) || !in_array('tenant_id', $customerCols, true))) {
    http_response_code(503);
    exit('PPPoE account tenant isolation is unavailable. Please contact the administrator.');
}
$where = [];
$params = [];
$types = '';
if ($has('tenant_id') && $tid > 0) {
    $where[] = 'pa.tenant_id = ?';
    $params[] = $tid;
    $types .= 'i';
}
$search = trim($_GET['search'] ?? '');
if ($search !== '' && $has('username')) {
    $where[] = 'pa.username LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

$select = ['pa.*', 'NULL AS account_number', "'' AS first_name", "'' AS last_name"];
$joins = '';
if ($has('internet_account_id') && in_array('id', $internetCols, true)) {
    $accountJoin = 'ia.id = pa.internet_account_id';
    if (in_array('tenant_id', $cols, true) && in_array('tenant_id', $internetCols, true)) {
        $accountJoin .= ' AND ia.tenant_id = pa.tenant_id';
    }
    $joins .= ' LEFT JOIN internet_accounts ia ON ' . $accountJoin;
    if (in_array('account_number', $internetCols, true)) {
        $select[1] = 'ia.account_number';
    }
    if (in_array('customer_id', $internetCols, true) && in_array('id', $customerCols, true)) {
        $customerJoin = 'c.id = ia.customer_id';
        if (in_array('tenant_id', $internetCols, true) && in_array('tenant_id', $customerCols, true)) {
            $customerJoin .= ' AND c.tenant_id = ia.tenant_id';
        }
        $joins .= ' LEFT JOIN customers c ON ' . $customerJoin;
        if (in_array('first_name', $customerCols, true)) {
            $select[2] = 'c.first_name';
        }
        if (in_array('last_name', $customerCols, true)) {
            $select[3] = 'c.last_name';
        }
    }
}
$sql = 'SELECT ' . implode(', ', $select) . ' FROM pppoe_accounts pa' . $joins
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY pa.id DESC LIMIT 200';
$s = $conn->prepare($sql);
if (!$s) {
    error_log('PPPoE account list query preparation failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load PPPoE accounts. Please check the database schema and contact the administrator.');
}
if ($params) {
    $s->bind_param($types, ...$params);
}
if (!$s->execute()) {
    error_log('PPPoE account list query execution failed: ' . $s->error);
    http_response_code(500);
    exit('Unable to load PPPoE accounts. Please contact the administrator.');
}
$rows = $s->get_result();
$serverCount=0;
if($tid>0){$serverStmt=$conn->prepare('SELECT COUNT(*) AS n FROM pppoe_servers WHERE tenant_id=?');if($serverStmt){$serverStmt->bind_param('i',$tid);$serverStmt->execute();$serverCount=(int)($serverStmt->get_result()->fetch_assoc()['n']??0);$serverStmt->close();}}
else{$serverResult=$conn->query('SELECT COUNT(*) AS n FROM pppoe_servers');if($serverResult)$serverCount=(int)($serverResult->fetch_assoc()['n']??0);}
?>
<?php require '../includes/header.php'; ?>
<div class="page-content pppoe-command-center">
  <div class="page-header"><div><span class="pppoe-kicker">INTERNET SERVICES</span><h1>PPPoE Command Center</h1><p>Manage subscriber credentials, service status and account readiness.</p></div><a class="btn btn-primary" href="add.php">+ Add PPPoE Account</a></div>
  <div class="pppoe-metrics">
    <?php
      // Keep dashboard metrics compatible with databases where optional columns differ.
      $countAccounts = static function ($status = null) use ($conn, $has, $tid) {
          $conditions = [];
          if ($has('tenant_id') && $tid > 0) $conditions[] = 'tenant_id = ' . (int)$tid;
          if ($status !== null && $has('status')) $conditions[] = "LOWER(status) = '" . $conn->real_escape_string($status) . "'";
          $query = 'SELECT COUNT(*) AS n FROM pppoe_accounts' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '');
          $result = $conn->query($query);
          if (!$result) {
              error_log('PPPoE dashboard metric query failed: ' . $conn->error);
              return 0;
          }
          $count = (int)($result->fetch_assoc()['n'] ?? 0);
          $result->free();
          return $count;
      };
      $total = $countAccounts();
      $active = $has('status') ? $countAccounts('active') : 0;
      $suspended = $has('status') ? $countAccounts('suspended') : 0;
      $inactive = max(0, $total - $active - $suspended);
    ?>
    <div class="pppoe-metric"><span>Total Accounts</span><strong><?=$total?></strong></div>
    <div class="pppoe-metric"><span>Active</span><strong><?=$active?></strong></div>
    <div class="pppoe-metric"><span>Suspended</span><strong><?=$suspended?></strong></div>
    <div class="pppoe-metric"><span>Inactive</span><strong><?=max(0,$inactive)?></strong></div>
  </div>
  <div class="pppoe-workspace">
    <div class="card pppoe-panel">
      <div class="pppoe-panel-head"><div><h2>Subscriber Accounts</h2><p>Search and manage PPPoE credentials.</p></div><a class="btn btn-secondary" href="../internet_accounts/index.php">Internet Accounts</a></div>
      <form method="get" class="pppoe-search"><input name="search" value="<?=e($search)?>" placeholder="Search PPPoE username"><button class="btn btn-secondary">Search</button><?php if($search!==''):?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif;?></form>
      <div style="overflow:auto"><table class="table"><thead><tr><th>Username</th><th>Customer</th><th>Account</th><th>Status</th><th>Action</th></tr></thead><tbody>
      <?php if(!$rows->num_rows): ?><tr><td colspan="5" class="empty">No PPPoE accounts found.</td></tr><?php else: while($r=$rows->fetch_assoc()): ?><tr><td><strong><?=e($r['username']??'')?></strong></td><td><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')))?></td><td><?=e($r['account_number']??'')?></td><td><span class="pppoe-status <?=e(strtolower($r['status']??'active'))?>"><?=e(ucfirst($r['status']??'active'))?></span></td><td><a class="btn btn-secondary" href="edit.php?id=<?=$r['id']?>">Manage</a></td></tr><?php endwhile; endif;?></tbody></table></div>
    </div>
    <div class="card pppoe-panel pppoe-readiness"><h2>Provisioning Readiness</h2><p>Account records are currently managed here. Router provisioning should be verified against the configured PPPoE server before an account is treated as live.</p><div class="readiness-row"><span>PPPoE Servers</span><strong><?=$serverCount?></strong></div><div class="readiness-row"><span>Active Accounts</span><strong><?=$active?></strong></div><div class="readiness-row"><span>Next step</span><strong>Router provisioning</strong></div><a class="btn btn-primary" href="../pppoe_servers/index.php">Manage PPPoE Servers</a></div>
  </div>
</div>
<style>
.pppoe-command-center{color:#f8fafc}.pppoe-kicker{font-size:11px;letter-spacing:.16em;color:#60a5fa;font-weight:700}.pppoe-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:0 0 18px}.pppoe-metric,.pppoe-panel{background:linear-gradient(145deg,rgba(16,24,39,.97),rgba(7,12,20,.96));border:1px solid rgba(96,165,250,.12);border-radius:14px;box-shadow:0 14px 35px rgba(0,0,0,.2)}.pppoe-metric{padding:18px}.pppoe-metric span{display:block;color:#94a3b8;font-size:12px}.pppoe-metric strong{display:block;font-size:27px;margin-top:7px}.pppoe-workspace{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:18px}.pppoe-panel{padding:20px}.pppoe-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:15px}.pppoe-panel h2{margin:0;color:#f8fafc}.pppoe-panel p{color:#94a3b8;margin:5px 0 0}.pppoe-search{display:flex;gap:10px;margin-bottom:16px}.pppoe-search input{flex:1;background:#0b111b!important;color:#fff!important;border:1px solid rgba(148,163,184,.18)!important;border-radius:8px}.pppoe-status{display:inline-flex;padding:5px 9px;border-radius:999px;background:#172235;color:#cbd5e1;font-size:12px}.pppoe-status.active{color:#93c5fd;background:rgba(37,99,235,.14)}.pppoe-status.suspended{color:#fbbf24;background:rgba(245,158,11,.12)}.empty{text-align:center;padding:32px!important;color:#94a3b8}.readiness-row{display:flex;justify-content:space-between;padding:13px 0;border-bottom:1px solid rgba(148,163,184,.12);color:#94a3b8}.readiness-row strong{color:#e2e8f0}.pppoe-readiness .btn{margin-top:18px;width:100%}@media(max-width:900px){.pppoe-metrics{grid-template-columns:repeat(2,1fr)}.pppoe-workspace{grid-template-columns:1fr}}@media(max-width:600px){.pppoe-metrics{grid-template-columns:1fr}.pppoe-panel-head{flex-direction:column}.pppoe-search{flex-direction:column}}
</style>
<?php require '../includes/footer.php'; ?>