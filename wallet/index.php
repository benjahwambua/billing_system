<?php
require_once '../includes/auth.php';
requireActiveUser();
$tenantId=requireTenantContext();
requireModulePermission('wallet','view');
global $conn;
$wallet=null;$rows=[];
$stmt=$conn->prepare("SELECT id,balance,currency,updated_at FROM tenant_wallets WHERE tenant_id=? LIMIT 1");
if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$wallet=$stmt->get_result()->fetch_assoc();$stmt->close();}
$stmt=$conn->prepare("SELECT id,transaction_type,direction,amount,balance_before,balance_after,reference,description,source,payment_method,external_reference,gateway_transaction_id,created_at FROM wallet_transactions WHERE tenant_id=? ORDER BY id DESC LIMIT 100");
if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$r=$stmt->get_result();while($x=$r->fetch_assoc())$rows[]=$x;$stmt->close();}
$pageTitle='Tenant Wallet';require_once '../includes/header.php';?>
<div class="dashboard-card">
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap"><div><h2>Tenant Wallet</h2><p>Flexihub wallet balance is separate from ISP customer collections.</p></div><a href="transactions.php">View Wallet Transactions</a></div>
<div class="stat-card"><h3><?=e(formatMoney($wallet['balance']??0,$wallet['currency']??'KES'))?></h3><p>Available Balance</p></div>
<div class="table-responsive"><table><thead><tr><th>Date</th><th>Direction</th><th>Type</th><th>Source</th><th>Method</th><th>Reference</th><th>Amount</th><th>Balance After</th></tr></thead><tbody>
<?php foreach($rows as $x):?><tr><td><?=e($x['created_at']??'')?></td><td><?=e(ucfirst($x['direction']??''))?></td><td><?=e($x['transaction_type']??'')?></td><td><?=e($x['source']??'')?></td><td><?=e($x['payment_method']??'')?></td><td><?=e($x['external_reference']??$x['reference']??'')?></td><td><?=e(formatMoney($x['amount']??0,$wallet['currency']??'KES'))?></td><td><?=e(formatMoney($x['balance_after']??0,$wallet['currency']??'KES'))?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="8">No wallet transactions found.</td></tr><?php endif;?></tbody></table></div>
</div><?php require_once '../includes/footer.php';?>