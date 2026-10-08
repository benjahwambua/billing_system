<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if (isTenantUser()) requireTenant();
global $conn;
$cols=[];$q=$conn->query("SHOW COLUMNS FROM pppoe_accounts");while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=getCurrentTenantId();$where=[];$params=[];$types='';
if($has('tenant_id')&&$tid){$where[]='pa.tenant_id=?';$params[]=$tid;$types.='i';}
$search=trim($_GET['search']??'');
if($search!==''&&$has('username')){$where[]='pa.username LIKE ?';$params[]='%'.$search.'%';$types.='s';}
$sql="SELECT pa.*,ia.account_number,c.first_name,c.last_name FROM pppoe_accounts pa LEFT JOIN internet_accounts ia ON ia.id=pa.internet_account_id LEFT JOIN customers c ON c.id=ia.customer_id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY pa.id DESC LIMIT 200";
$s=$conn->prepare($sql);if($params)$s->bind_param($types,...$params);$s->execute();$rows=$s->get_result();
?>
<?php require '../includes/header.php'; ?>
<div class="page-content pppoe-command-center">
  <div class="page-header"><div><span class="pppoe-kicker">INTERNET SERVICES</span><h1>PPPoE Command Center</h1><p>Manage subscriber credentials, service status and account readiness.</p></div><a class="btn btn-primary" href="add.php">+ Add PPPoE Account</a></div>
  <div class="pppoe-metrics">
    <?php
      $metricWhere=$has('tenant_id')&&$tid?' WHERE tenant_id='.(int)$tid:'';
      $total=(int)($conn->query("SELECT COUNT(*) n FROM pppoe_accounts".$metricWhere)->fetch_assoc()['n']??0);
      $active=(int)($conn->query("SELECT COUNT(*) n FROM pppoe_accounts".$metricWhere.($metricWhere?' AND ':' WHERE ')."LOWER(status)='active'")->fetch_assoc()['n']??0);
      $suspended=(int)($conn->query("SELECT COUNT(*) n FROM pppoe_accounts".$metricWhere.($metricWhere?' AND ':' WHERE ')."LOWER(status)='suspended'")->fetch_assoc()['n']??0);
      $inactive=$total-$active-$suspended;
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
    <div class="card pppoe-panel pppoe-readiness"><h2>Provisioning Readiness</h2><p>Account records are currently managed here. Router provisioning should be verified against the configured PPPoE server before an account is treated as live.</p><div class="readiness-row"><span>PPPoE Servers</span><strong><?=count($servers)?></strong></div><div class="readiness-row"><span>Active Accounts</span><strong><?=$active?></strong></div><div class="readiness-row"><span>Next step</span><strong>Router provisioning</strong></div><a class="btn btn-primary" href="../pppoe_servers/index.php">Manage PPPoE Servers</a></div>
  </div>
</div>
<style>
.pppoe-command-center{color:#f8fafc}.pppoe-kicker{font-size:11px;letter-spacing:.16em;color:#60a5fa;font-weight:700}.pppoe-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:0 0 18px}.pppoe-metric,.pppoe-panel{background:linear-gradient(145deg,rgba(16,24,39,.97),rgba(7,12,20,.96));border:1px solid rgba(96,165,250,.12);border-radius:14px;box-shadow:0 14px 35px rgba(0,0,0,.2)}.pppoe-metric{padding:18px}.pppoe-metric span{display:block;color:#94a3b8;font-size:12px}.pppoe-metric strong{display:block;font-size:27px;margin-top:7px}.pppoe-workspace{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:18px}.pppoe-panel{padding:20px}.pppoe-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:15px}.pppoe-panel h2{margin:0;color:#f8fafc}.pppoe-panel p{color:#94a3b8;margin:5px 0 0}.pppoe-search{display:flex;gap:10px;margin-bottom:16px}.pppoe-search input{flex:1;background:#0b111b!important;color:#fff!important;border:1px solid rgba(148,163,184,.18)!important;border-radius:8px}.pppoe-status{display:inline-flex;padding:5px 9px;border-radius:999px;background:#172235;color:#cbd5e1;font-size:12px}.pppoe-status.active{color:#93c5fd;background:rgba(37,99,235,.14)}.pppoe-status.suspended{color:#fbbf24;background:rgba(245,158,11,.12)}.empty{text-align:center;padding:32px!important;color:#94a3b8}.readiness-row{display:flex;justify-content:space-between;padding:13px 0;border-bottom:1px solid rgba(148,163,184,.12);color:#94a3b8}.readiness-row strong{color:#e2e8f0}.pppoe-readiness .btn{margin-top:18px;width:100%}@media(max-width:900px){.pppoe-metrics{grid-template-columns:repeat(2,1fr)}.pppoe-workspace{grid-template-columns:1fr}}@media(max-width:600px){.pppoe-metrics{grid-template-columns:1fr}.pppoe-panel-head{flex-direction:column}.pppoe-search{flex-direction:column}}
</style>
<?php require '../includes/footer.php'; ?>