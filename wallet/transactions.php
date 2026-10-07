<?php
require_once '../includes/auth.php';
requireActiveUser();
$tenantId=requireTenantContext();
requireModulePermission('wallet','view');
global $conn;
$rows=[];$stmt=$conn->prepare("SELECT id,transaction_type,direction,amount,balance_before,balance_after,reference,description,source,payment_method,external_reference,gateway_transaction_id,idempotency_key,created_at FROM wallet_transactions WHERE tenant_id=? ORDER BY id DESC LIMIT 500");
if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$r=$stmt->get_result();while($x=$r->fetch_assoc())$rows[]=$x;$stmt->close();}
$wallet=null;$stmt=$conn->prepare("SELECT balance,currency FROM tenant_wallets WHERE tenant_id=? LIMIT 1");if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$wallet=$stmt->get_result()->fetch_assoc();$stmt->close();}
$pageTitle='Wallet Transactions';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Wallet Transactions</h2><p>Tenant wallet ledger only. Customer collections remain in the billing ledger.</p>
<div style="font-size:24px;font-weight:700;margin:12px 0"><?=e(formatMoney($wallet['balance']??0,$wallet['currency']??'KES'))?></div>
<div class="table-responsive"><table><thead><tr><th>Date</th><th>Direction</th><th>Type</th><th>Amount</th><th>Before</th><th>After</th><th>Source</th><th>Method</th><th>Reference</th><th>Gateway ID</th><th>Description</th></tr></thead><tbody>
<?php foreach($rows as $x):?><tr><td><?=e($x['created_at']??'')?></td><td><?=e(ucfirst($x['direction']??''))?></td><td><?=e($x['transaction_type']??'')?></td><td><?=e(formatMoney($x['amount']??0,$wallet['currency']??'KES'))?></td><td><?=e(formatMoney($x['balance_before']??0,$wallet['currency']??'KES'))?></td><td><?=e(formatMoney($x['balance_after']??0,$wallet['currency']??'KES'))?></td><td><?=e($x['source']??'')?></td><td><?=e($x['payment_method']??'')?></td><td><?=e($x['external_reference']??$x['reference']??'')?></td><td><?=e($x['gateway_transaction_id']??'')?></td><td><?=e($x['description']??'')?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="11">No wallet transactions found.</td></tr><?php endif;?></tbody></table></div>
</div><?php require_once '../includes/footer.php';?>