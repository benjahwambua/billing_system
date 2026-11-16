<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('resellers','view');
global $conn;
$tenantId=(int)getCurrentTenantId();$userId=(int)getCurrentUserId();
$errors=[];$notice='';
$schemaReady=flexihubTableHasColumn('resellers','tenant_id')&&flexihubTableHasColumn('reseller_commissions','tenant_id')&&flexihubTableHasColumn('customers','reseller_id');

if($schemaReady&&$_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();$action=trim((string)($_POST['action']??''));
 if($action==='update_reseller'){
  requireModulePermission('resellers','edit');
  $resellerId=(int)($_POST['reseller_id']??0);$contact=trim((string)($_POST['contact_person']??''));$phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));$rate=(float)($_POST['commission_rate']??0);$status=trim((string)($_POST['status']??'active'));
  if($resellerId<=0)$errors[]='Select a valid reseller.';
  if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';
  if(!is_finite($rate)||$rate<0||$rate>100)$errors[]='Commission rate must be between 0 and 100 percent.';
  if(!in_array($status,['active','inactive'],true))$errors[]='Select a valid reseller status.';
  if(!$errors){
   $stmt=$conn->prepare("UPDATE resellers SET contact_person=?,phone=?,email=?,commission_rate=?,status=? WHERE id=? AND tenant_id=?");
   if(!$stmt)$errors[]='Unable to prepare reseller update.';
   else{$stmt->bind_param('sssdsii',$contact,$phone,$email,$rate,$status,$resellerId,$tenantId);if($stmt->execute())$notice='Reseller details updated.';else $errors[]='Unable to update reseller.';$stmt->close();}
  }
 } elseif($action==='assign_customer'){
  requireModulePermission('resellers','edit');
  $resellerId=(int)($_POST['reseller_id']??0);$customerId=(int)($_POST['customer_id']??0);
  if($resellerId<=0||$customerId<=0)$errors[]='Select a reseller and customer.';
  if(!$errors){
   $check=$conn->prepare("SELECT id FROM resellers WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
   if(!$check)$errors[]='Unable to verify reseller.';
   else{$check->bind_param('ii',$resellerId,$tenantId);$check->execute();$ok=(bool)$check->get_result()->fetch_assoc();$check->close();if(!$ok)$errors[]='Selected reseller is not active in this organisation.';}
  }
  if(!$errors){
   $check=$conn->prepare("SELECT id FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
   if(!$check)$errors[]='Unable to verify customer.';
   else{$check->bind_param('ii',$customerId,$tenantId);$check->execute();$ok=(bool)$check->get_result()->fetch_assoc();$check->close();if(!$ok)$errors[]='Selected customer does not belong to this organisation.';}
  }
  if(!$errors){
   $stmt=$conn->prepare("UPDATE customers SET reseller_id=? WHERE id=? AND tenant_id=?");
   if(!$stmt)$errors[]='Unable to prepare customer reseller assignment.';
   else{$stmt->bind_param('iii',$resellerId,$customerId,$tenantId);if($stmt->execute())$notice='Customer assigned to reseller.';else $errors[]='Unable to assign customer to reseller.';$stmt->close();}
  }
 } elseif($action==='create'){
  requireModulePermission('resellers','create');
  $name=trim((string)($_POST['business_name']??''));$contact=trim((string)($_POST['contact_person']??''));$phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));$rate=(float)($_POST['commission_rate']??0);$notes=trim((string)($_POST['notes']??''));
  if($name===''||mb_strlen($name)>180)$errors[]='Enter a reseller/business name of up to 180 characters.';
  if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';
  if(!is_finite($rate)||$rate<0||$rate>100)$errors[]='Commission rate must be between 0 and 100 percent.';
  if(!$errors){
   $code='RS-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
   $stmt=$conn->prepare("INSERT INTO resellers (tenant_id,reseller_code,business_name,contact_person,phone,email,commission_rate,status,notes) VALUES (?,?,?,?,?,?,?,'active',?)");
   if(!$stmt)$errors[]='Unable to prepare reseller creation.';
   else{$stmt->bind_param('isssssds',$tenantId,$code,$name,$contact,$phone,$email,$rate,$notes);if($stmt->execute())$notice='Reseller created.';elseif($stmt->errno===1062)$errors[]='Reseller code already exists. Try again.';else $errors[]='Unable to create reseller.';$stmt->close();}
  }
 } elseif($action==='commission'){
  requireModulePermission('resellers','create');
  $resellerId=(int)($_POST['reseller_id']??0);$invoiceId=(int)($_POST['invoice_id']??0);$reference=trim((string)($_POST['reference']??''));$notes=trim((string)($_POST['commission_notes']??''));
  if($resellerId<=0||$invoiceId<=0)$errors[]='Select a reseller and invoice.';
  if(!$errors){
   $stmt=$conn->prepare("SELECT id,commission_rate FROM resellers WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
   if(!$stmt)$errors[]='Unable to verify reseller.';
   else{$stmt->bind_param('ii',$resellerId,$tenantId);$stmt->execute();$reseller=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$reseller)$errors[]='Selected reseller is not active in this organisation.';}
  }
  if(!$errors){
   $stmt=$conn->prepare("SELECT * FROM invoices WHERE id=? AND tenant_id=? LIMIT 1");
   if(!$stmt)$errors[]='Unable to verify invoice.';
   else{$stmt->bind_param('ii',$invoiceId,$tenantId);$stmt->execute();$invoice=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$invoice)$errors[]='Selected invoice does not belong to this organisation.';
    else {
     $owner=$conn->prepare("SELECT reseller_id FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
     if(!$owner)$errors[]='Unable to verify customer reseller attribution.';
     else{$owner->bind_param('ii',$invoice['customer_id'],$tenantId);$owner->execute();$customerOwner=$owner->get_result()->fetch_assoc();$owner->close();if((int)($customerOwner['reseller_id']??0)!==$resellerId)$errors[]='The invoice customer is not assigned to the selected reseller.';}
    }
   }
  }
  if(!$errors){
   $paid=(float)($invoice['actual_paid']??0);
   if($paid<=0)$errors[]='The invoice has no recorded payments. A commission can only be calculated from collected revenue.';
   else{
    $rate=(float)$reseller['commission_rate'];$amount=round($paid*$rate/100,2);$customerId=(int)$invoice['customer_id'];
    $stmt=$conn->prepare("INSERT INTO reseller_commissions (tenant_id,reseller_id,customer_id,invoice_id,sale_amount,commission_rate,commission_amount,status,reference,notes,created_by) VALUES (?,?,?,?,?,?,?,'pending',?,?,?)");
    if(!$stmt)$errors[]='Unable to prepare reseller commission.';
    else{$stmt->bind_param('iiiidddssi',$tenantId,$resellerId,$customerId,$invoiceId,$paid,$rate,$amount,$reference,$notes,$userId);if($stmt->execute())$notice='Commission calculated from collected invoice payments.';elseif($stmt->errno===1062)$errors[]='A commission for this reseller and invoice already exists.';else $errors[]='Unable to save reseller commission.';$stmt->close();}
   }
  }
 } elseif($action==='status'){
  requireModulePermission('resellers','edit');
  $commissionId=(int)($_POST['commission_id']??0);$status=trim((string)($_POST['status']??''));$payoutReference=trim((string)($_POST['payout_reference']??''));
  if(!in_array($status,['approved','paid','reversed'],true))$errors[]='Invalid commission status.';
  if($status==='paid'&&($payoutReference===''||mb_strlen($payoutReference)>120))$errors[]='Enter the payout reference before marking a commission as paid.';
  if(!$errors){
   $stmt=$conn->prepare("SELECT status FROM reseller_commissions WHERE id=? AND tenant_id=? LIMIT 1");
   if(!$stmt)$errors[]='Unable to verify commission.';
   else{$stmt->bind_param('ii',$commissionId,$tenantId);$stmt->execute();$current=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$current)$errors[]='Commission not found.';}
  }
  if(!$errors){
   $currentStatus=(string)$current['status'];
   $allowed=($currentStatus==='pending'&&in_array($status,['approved','reversed'],true))
      ||($currentStatus==='approved'&&in_array($status,['paid','reversed'],true));
   if(!$allowed)$errors[]='That commission status transition is not allowed.';
   else{
    $paidAt=$status==='paid'?date('Y-m-d H:i:s'):null;
    $approvedAt=$status==='approved'?date('Y-m-d H:i:s'):null;
    $stmt=$conn->prepare("UPDATE reseller_commissions SET status=?,approved_by=IF(?='approved',?,approved_by),approved_at=IF(?='approved',?,approved_at),paid_by=IF(?='paid',?,paid_by),paid_at=IF(?='paid',?,paid_at),paid_reference=IF(?='paid',?,paid_reference) WHERE id=? AND tenant_id=?");
    if(!$stmt)$errors[]='Unable to prepare commission status update.';
    else{$stmt->bind_param('ssisssissssii',$status,$status,$userId,$status,$approvedAt,$status,$userId,$status,$paidAt,$status,$payoutReference,$commissionId,$tenantId);if($stmt->execute())$notice='Commission status updated.';else $errors[]='Unable to update commission status.';$stmt->close();}
   }
  }
 } else $errors[]='Invalid reseller action.';
}

$resellers=[];$commissions=[];$invoices=[];$customers=[];
if($schemaReady){
 $q=$conn->prepare("SELECT r.*,COUNT(c.id) AS commission_count,COALESCE(SUM(CASE WHEN c.status<>'paid' AND c.status<>'reversed' THEN c.commission_amount ELSE 0 END),0) AS outstanding_commission FROM resellers r LEFT JOIN reseller_commissions c ON c.reseller_id=r.id AND c.tenant_id=r.tenant_id WHERE r.tenant_id=? GROUP BY r.id ORDER BY r.business_name");
 if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$resellers[]=$x;$q->close();}
 $q=$conn->prepare("SELECT c.*,r.business_name,r.reseller_code,cu.customer_number,TRIM(CONCAT_WS(' ',cu.first_name,cu.last_name)) AS customer_name FROM reseller_commissions c INNER JOIN resellers r ON r.id=c.reseller_id AND r.tenant_id=c.tenant_id INNER JOIN customers cu ON cu.id=c.customer_id AND cu.tenant_id=c.tenant_id WHERE c.tenant_id=? ORDER BY c.id DESC LIMIT 300");
 if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$commissions[]=$x;$q->close();}
 $q=$conn->prepare("SELECT i.* FROM invoices i INNER JOIN customers c ON c.id=i.customer_id AND c.tenant_id=i.tenant_id WHERE i.tenant_id=? AND c.reseller_id IS NOT NULL ORDER BY i.id DESC LIMIT 200");
 if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$invoices[]=$x;$q->close();}
 $q=$conn->prepare("SELECT id,customer_number,first_name,last_name FROM customers WHERE tenant_id=? ORDER BY first_name,last_name");
 if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$customers[]=$x;$q->close();}
}
$totalOutstanding=0;$pending=0;foreach($resellers as $r)$totalOutstanding+=(float)$r['outstanding_commission'];foreach($commissions as $c)if($c['status']==='pending')$pending++;
$pageTitle='Resellers & Commissions';require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<div class="main-content reseller-page">
 <div class="reseller-hero"><div><span>PARTNER CHANNEL</span><h1>Resellers & Commissions</h1><p>Manage sales partners and calculate commissions only against recorded customer collections.</p></div><span><?=count($resellers)?> partners</span></div>
 <?php if(!$schemaReady): ?><div class="reseller-alert">Reseller tables are not yet available. Apply the reviewed migration <code>database/041_reseller_commissions.sql</code> during the final database update.</div><?php endif; ?>
 <?php foreach($errors as $error): ?><div class="reseller-alert error"><?=e($error)?></div><?php endforeach; ?><?php if($notice): ?><div class="reseller-alert success"><?=e($notice)?></div><?php endif; ?>
 <?php if($schemaReady): ?>
 <div class="reseller-metrics"><div><small>Resellers</small><strong><?=count($resellers)?></strong></div><div><small>Pending Commissions</small><strong><?=$pending?></strong></div><div><small>Unpaid Commission Value</small><strong><?=e(formatMoney($totalOutstanding))?></strong></div><div><small>Commission Records</small><strong><?=count($commissions)?></strong></div></div>
 <?php if(userCan('resellers','create')): ?>
 <section class="reseller-panel"><h2>Add Reseller</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="create"><div class="reseller-form"><label>Business / Reseller Name *<input name="business_name" maxlength="180" required></label><label>Contact Person<input name="contact_person" maxlength="150"></label><label>Phone<input name="phone" maxlength="40"></label><label>Email<input type="email" name="email" maxlength="190"></label><label>Commission Rate (%)<input type="number" min="0" max="100" step="0.001" name="commission_rate" value="0"></label><label class="full">Notes<textarea name="notes" rows="2"></textarea></label></div><button class="btn btn-primary">Create Reseller</button></form></section>
 <section class="reseller-panel"><h2>Assign Customer to Reseller</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="assign_customer"><div class="reseller-form"><label>Reseller<select name="reseller_id" required><option value="">Select reseller</option><?php foreach($resellers as $r): if($r['status']==='active'): ?><option value="<?=(int)$r['id']?>"><?=e($r['reseller_code'].' — '.$r['business_name'])?></option><?php endif; endforeach; ?></select></label><label>Customer<select name="customer_id" required><option value="">Select customer</option><?php foreach($customers as $customer): ?><option value="<?=(int)$customer['id']?>"><?=e(($customer['customer_number']??'').' — '.trim(($customer['first_name']??'').' '.($customer['last_name']??'')))?></option><?php endforeach; ?></select></label></div><button class="btn btn-primary">Assign Customer</button></form></section>
 <section class="reseller-panel"><h2>Calculate Commission</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="commission"><div class="reseller-form"><label>Reseller<select name="reseller_id" required><option value="">Select reseller</option><?php foreach($resellers as $r): if($r['status']==='active'): ?><option value="<?=(int)$r['id']?>"><?=e($r['reseller_code'].' — '.$r['business_name'].' ('.$r['commission_rate'].'%)')?></option><?php endif; endforeach; ?></select></label><label>Invoice<select name="invoice_id" required><option value="">Select invoice</option><?php foreach($invoices as $invoice): ?><option value="<?=(int)$invoice['id']?>"><?=e(($invoice['invoice_number']??('Invoice #'.$invoice['id'])).' · '.formatMoney($invoice['actual_paid']??0).' collected')?></option><?php endforeach; ?></select></label><label>Reference<input name="reference" maxlength="120"></label><label class="full">Notes<textarea name="commission_notes" rows="2"></textarea></label></div><button class="btn btn-primary">Calculate Commission</button></form></section>
 <?php endif; ?>
 <section class="reseller-panel"><h2>Reseller Directory</h2><div class="reseller-table-wrap"><table><thead><tr><th>Partner</th><th>Contact</th><th>Rate</th><th>Commissions</th><th>Outstanding</th><th>Status</th><th>Manage</th></tr></thead><tbody><?php foreach($resellers as $r): ?><tr><td><strong><?=e($r['business_name'])?></strong><small><?=e($r['reseller_code'])?></small></td><td><?=e($r['contact_person']??'—')?><small><?=e($r['phone']??'')?></small></td><td><?=number_format((float)$r['commission_rate'],3)?>%</td><td><?=(int)$r['commission_count']?></td><td><?=e(formatMoney($r['outstanding_commission']))?></td><td><?=e(ucfirst($r['status']))?></td><td><?php if(userCan('resellers','edit')): ?><details><summary>Edit</summary><form method="post" class="reseller-edit"><?=csrfField()?><input type="hidden" name="action" value="update_reseller"><input type="hidden" name="reseller_id" value="<?=(int)$r['id']?>"><label>Contact<input name="contact_person" value="<?=e($r['contact_person']??'')?>"></label><label>Phone<input name="phone" value="<?=e($r['phone']??'')?>"></label><label>Email<input type="email" name="email" value="<?=e($r['email']??'')?>"></label><label>Commission %<input type="number" min="0" max="100" step="0.001" name="commission_rate" value="<?=e($r['commission_rate'])?>"></label><label>Status<select name="status"><option value="active" <?=$r['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$r['status']==='inactive'?'selected':''?>>Inactive</option></select></label><button class="btn btn-secondary">Save</button></form></details><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?><?php if(!$resellers): ?><tr><td colspan="7">No resellers registered yet.</td></tr><?php endif; ?></tbody></table></div></section>
 <section class="reseller-panel"><h2>Commission Ledger</h2><div class="reseller-table-wrap"><table><thead><tr><th>Date</th><th>Reseller</th><th>Customer / Invoice</th><th>Collected</th><th>Rate</th><th>Commission</th><th>Status / Action</th></tr></thead><tbody><?php foreach($commissions as $c): ?><tr><td><?=e($c['created_at'])?></td><td><?=e($c['business_name'])?></td><td><?=e(($c['customer_number']??'').' — '.($c['customer_name']??'')).' / Invoice #'.(int)$c['invoice_id']?></td><td><?=e(formatMoney($c['sale_amount']))?></td><td><?=number_format((float)$c['commission_rate'],3)?>%</td><td><?=e(formatMoney($c['commission_amount']))?></td><td><?=e(ucfirst($c['status']))?><?php if(!empty($c['paid_reference'])): ?><small>Paid ref: <?=e($c['paid_reference'])?></small><?php endif; ?><?php if(userCan('resellers','edit')&&$c['status']!=='paid'&&$c['status']!=='reversed'): ?><form method="post" class="commission-action"><?=csrfField()?><input type="hidden" name="action" value="status"><input type="hidden" name="commission_id" value="<?=(int)$c['id']?>"><select name="status"><?php if($c['status']==='pending'): ?><option value="approved">Approve</option><option value="reversed">Reverse</option><?php else: ?><option value="paid">Mark Paid</option><option value="reversed">Reverse</option><?php endif; ?></select><?php if($c['status']==='approved'): ?><input name="payout_reference" maxlength="120" placeholder="Payout reference (required)"><?php endif; ?><button class="btn btn-secondary">Apply</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$commissions): ?><tr><td colspan="7">No commissions calculated yet.</td></tr><?php endif; ?></tbody></table></div></section>
 <?php endif; ?>
</div>
<style>
.reseller-page{padding:24px;color:#e2e8f0}.reseller-hero{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px;margin-bottom:16px;border:1px solid rgba(59,130,246,.18);border-radius:16px;background:radial-gradient(circle at 90% 10%,rgba(37,99,235,.2),transparent 35%),linear-gradient(135deg,#101827,#0a101a)}.reseller-hero span:first-child{color:#60a5fa;font-size:11px;font-weight:800;letter-spacing:.15em}.reseller-hero h1{margin:7px 0;color:#f8fafc}.reseller-hero p{margin:0;color:#94a3b8}.reseller-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}.reseller-metrics>div,.reseller-panel{padding:18px;border:1px solid rgba(148,163,184,.14);border-radius:14px;background:linear-gradient(145deg,#101827,#0b111b)}.reseller-metrics small{display:block;color:#94a3b8}.reseller-metrics strong{display:block;color:#f8fafc;font-size:22px;margin-top:6px}.reseller-panel{margin-bottom:16px}.reseller-panel h2{margin:0 0 14px;color:#f8fafc;font-size:17px}.reseller-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px}.reseller-form .full{grid-column:1/-1}.reseller-form label{display:grid;gap:6px;color:#cbd5e1;font-size:12px;font-weight:700}.reseller-form input,.reseller-form select,.reseller-form textarea,.commission-action select{width:100%;box-sizing:border-box;padding:10px;border:1px solid rgba(148,163,184,.2);border-radius:8px;background:#0b111b;color:#f8fafc}.reseller-table-wrap{overflow:auto}.reseller-table-wrap table{width:100%;min-width:850px;border-collapse:collapse}.reseller-table-wrap th,.reseller-table-wrap td{padding:11px 12px;border-bottom:1px solid rgba(148,163,184,.13);text-align:left;color:#e2e8f0;vertical-align:top}.reseller-table-wrap th{background:#0b111b;color:#94a3b8}.reseller-table-wrap td small{display:block;color:#94a3b8;margin-top:4px}.commission-action{display:flex;gap:6px;margin-top:7px}.reseller-edit{display:grid;gap:7px;min-width:180px;padding:10px;background:#101827;border-radius:9px}.reseller-edit label{display:grid;gap:4px;color:#cbd5e1;font-size:11px}.reseller-edit input,.reseller-edit select{width:100%;box-sizing:border-box;padding:7px;border:1px solid rgba(148,163,184,.2);border-radius:7px;background:#0b111b;color:#f8fafc}.reseller-table-wrap summary{cursor:pointer;color:#60a5fa;font-weight:700}.commission-action select{max-width:120px}.reseller-page .btn{padding:9px 13px;border-radius:8px;border:0;font-weight:700}.reseller-page .btn-primary{background:#2563eb;color:#fff}.reseller-page .btn-secondary{background:#172235;color:#cbd5e1;border:1px solid rgba(148,163,184,.16)}.reseller-alert{padding:13px 15px;margin-bottom:14px;border-radius:9px;background:rgba(245,158,11,.13);color:#fcd34d}.reseller-alert.error{background:rgba(239,68,68,.13);color:#fca5a5}.reseller-alert.success{background:rgba(34,197,94,.13);color:#86efac}@media(max-width:850px){.reseller-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.reseller-page{padding:14px}.reseller-hero{align-items:flex-start;flex-direction:column}.reseller-form{grid-template-columns:1fr}.reseller-form .full{grid-column:auto}}
</style>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
