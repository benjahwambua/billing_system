<?php
require_once '../includes/auth.php'; requireLogin(); requireHostContext();
$pageTitle='Tenant Wallets'; $rows=[]; $available=false;
$check=$conn->query("SHOW TABLES LIKE 'tenant_wallets'");
if($check && $check->num_rows){$available=true;$r=$conn->query("SELECT tw.*,t.name AS tenant_name,t.tenant_code FROM tenant_wallets tw LEFT JOIN tenants t ON t.id=tw.tenant_id ORDER BY tw.tenant_id");if($r)while($x=$r->fetch_assoc())$rows[]=$x;}
require_once '../includes/header.php'; require_once '../includes/sidebar.php';
?>
<div class="main-content"><div class="page-header"><div><h1>Tenant Wallets</h1><p>Monitor tenant wallet balances already used by the billing runtime.</p></div></div>
<?php if(!$available): ?><div class="card notice">The tenant wallet table is not available in this installation yet.</div><?php else: ?>
<div class="stats-grid"><div class="stat-card"><span>Wallets</span><strong><?=count($rows)?></strong></div><div class="stat-card"><span>Total Balance</span><strong><?=e(formatMoney(array_sum(array_map(fn($x)=>(float)($x['balance']??0),$rows))) )?></strong></div></div>
<div class="card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Tenant</th><th>Code</th><th>Balance</th><th>Currency</th><th>Updated</th></tr></thead><tbody><?php foreach($rows as $x): ?><tr><td><?=e($x['tenant_name']??'—')?></td><td><?=e($x['tenant_code']??'—')?></td><td><strong><?=e(formatMoney($x['balance']??0,$x['currency']??null))?></strong></td><td><?=e($x['currency']??'KES')?></td><td><?=e($x['updated_at']??'—')?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?></div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:20px}.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}.stat-card span{display:block;color:#6b7280;font-size:13px;margin-bottom:8px}.stat-card strong{font-size:25px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.notice{padding:20px}.table-responsive{overflow:auto}.data-table{width:100%;border-collapse:collapse;min-width:700px}.data-table th,.data-table td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left}.data-table th{background:#f9fafb;font-size:12px;color:#4b5563}</style>
<?php require_once '../includes/footer.php'; ?>