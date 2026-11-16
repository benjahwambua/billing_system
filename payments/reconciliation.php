<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('payments','view');
require_once '../includes/functions.php';

global $conn;
$tenantId=(int)getCurrentTenantId();
if($tenantId<=0){http_response_code(403);exit('A valid tenant context is required.');}
$gatewayRows=[];$callbackRows=[];$warnings=[];
$gatewayCheck=$conn->query("SHOW TABLES LIKE 'payment_gateway_transactions'");
if($gatewayCheck && $gatewayCheck->num_rows>0){
 $stmt=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE tenant_id=? AND status IN ('confirmed','failed') ORDER BY id DESC LIMIT 200");
 if($stmt){$stmt->bind_param('i',$tenantId);if($stmt->execute()){$result=$stmt->get_result();while($row=$result->fetch_assoc())$gatewayRows[]=$row;}else $warnings[]='Gateway transactions could not be loaded.';$stmt->close();}
 else $warnings[]='Gateway transaction storage could not be queried.';
}else $warnings[]='Gateway transaction storage is not installed.';
$callbackCheck=$conn->query("SHOW TABLES LIKE 'payment_callbacks'");
if($callbackCheck && $callbackCheck->num_rows>0){
 $stmt=$conn->prepare("SELECT * FROM payment_callbacks WHERE tenant_id=? AND processing_status IN ('received','failed') ORDER BY id DESC LIMIT 200");
 if($stmt){$stmt->bind_param('i',$tenantId);if($stmt->execute()){$result=$stmt->get_result();while($row=$result->fetch_assoc())$callbackRows[]=$row;}else $warnings[]='Callback events could not be loaded.';$stmt->close();}
 else $warnings[]='Callback event storage could not be queried.';
}else $warnings[]='Callback event storage is not installed.';
$pageTitle='Payment Reconciliation';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
 <div class="page-header"><div><h1>Payment Reconciliation</h1><p>Review confirmed gateway payments awaiting finalization, failed transactions and callback events that need investigation.</p></div><a class="btn btn-secondary" href="index.php">Back to Payments</a></div>
 <?php foreach($warnings as $warning):?><div class="alert alert-warning"><?=e($warning)?></div><?php endforeach;?>
 <div class="stats-grid" style="margin-bottom:20px">
  <div class="card" style="padding:16px"><div class="stat-label">Gateway items to review</div><div class="stat-value"><?=count($gatewayRows)?></div></div>
  <div class="card" style="padding:16px"><div class="stat-label">Callback events to review</div><div class="stat-value"><?=count($callbackRows)?></div></div>
 </div>
 <div class="card" style="padding:20px;margin-bottom:20px">
  <h2>Gateway transactions requiring review</h2>
  <p>Confirmed transactions have a successful provider response but have not completed the billing workflow. Failed transactions require investigation; do not assume they represent settled funds.</p>
  <div class="table-responsive"><table><thead><tr><th>Transaction</th><th>Flow</th><th>Reference</th><th>Amount</th><th>Receipt</th><th>Status</th><th>Reason</th><th>Created</th></tr></thead><tbody>
  <?php foreach($gatewayRows as $row):?><tr>
   <td>#<?=e((string)($row['id']??''))?></td><td><?=e((string)($row['flow']??''))?></td>
   <td><?=e((string)($row['account_reference']??$row['checkout_request_id']??''))?></td>
   <td><?=e(formatMoney($row['amount']??0))?></td><td><?=e((string)($row['provider_receipt']??''))?></td>
   <td><span class="status-badge <?=($row['status']??'')==='confirmed'?'warning':'danger'?>"><?=e((string)($row['status']??''))?></span></td>
   <td><?=e((string)($row['failure_reason']??$row['result_description']??''))?></td><td><?=e((string)($row['created_at']??''))?></td>
  </tr><?php endforeach;?>
  <?php if(!$gatewayRows):?><tr><td colspan="8">No confirmed or failed gateway transactions require review.</td></tr><?php endif;?>
  </tbody></table></div>
 </div>
 <div class="card" style="padding:20px">
  <h2>Callback events requiring review</h2>
  <p>Received events may not yet have completed processing. Failed events retain the recorded error for investigation.</p>
  <div class="table-responsive"><table><thead><tr><th>Event</th><th>Provider reference</th><th>Amount</th><th>Phone</th><th>Status</th><th>Error</th><th>Received</th></tr></thead><tbody>
  <?php foreach($callbackRows as $row):?><tr>
   <td>#<?=e((string)($row['id']??''))?></td><td><?=e((string)($row['external_transaction_id']??$row['transaction_reference']??''))?></td>
   <td><?=e(isset($row['amount'])?formatMoney($row['amount']):'—')?></td><td><?=e((string)($row['phone_number']??''))?></td>
   <td><?=e((string)($row['processing_status']??''))?></td><td><?=e((string)($row['error_message']??''))?></td><td><?=e((string)($row['created_at']??''))?></td>
  </tr><?php endforeach;?>
  <?php if(!$callbackRows):?><tr><td colspan="7">No unprocessed or failed callback events require review.</td></tr><?php endif;?>
  </tbody></table></div>
 </div>
 <div class="alert alert-info" style="margin-top:18px">This screen is intentionally review-only. It does not retry callbacks, create payments, alter invoice balances or claim M-Pesa receipts. Those actions require a verified recovery workflow to prevent duplicate collections and allocations.</div>
</div>
<?php require_once '../includes/footer.php';?>
