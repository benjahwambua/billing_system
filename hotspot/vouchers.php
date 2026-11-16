<?php
require_once '../includes/auth.php';
requireActiveUser();requireTenantContext();requireModulePermission('hotspot','view');
global $conn;$tenantId=(int)getCurrentTenantId();$errors=[];$notice='';
$schemaReady=flexihubTableHasColumn('hotspot_vouchers','tenant_id')&&flexihubTableHasColumn('hotspot_access_codes','tenant_id');
$packages=[];
if($schemaReady){$q=$conn->prepare("SELECT id,name,price,duration_minutes FROM hotspot_packages WHERE tenant_id=? AND status='active' ORDER BY name");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$packages[]=$x;$q->close();}}
if($schemaReady&&$_SERVER['REQUEST_METHOD']==='POST'){
 requireModulePermission('hotspot','create');requireCsrf();
 $packageId=(int)($_POST['package_id']??0);$quantity=(int)($_POST['quantity']??0);$expiry=trim((string)($_POST['expires_at']??''));$batch='VB-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
 if($quantity<1||$quantity>500)$errors[]='Generate between 1 and 500 vouchers per batch.';
 if($packageId<=0)$errors[]='Select an active hotspot package.';
 $package=null;
 if(!$errors){$q=$conn->prepare("SELECT id,name,price FROM hotspot_packages WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");if(!$q)$errors[]='Unable to verify hotspot package.';else{$q->bind_param('ii',$packageId,$tenantId);$q->execute();$package=$q->get_result()->fetch_assoc();$q->close();if(!$package)$errors[]='Selected package is not active in this organisation.';}}
 $expiresAt=null;
 if($expiry!==''){if(strtotime($expiry)===false)$errors[]='Enter a valid voucher expiry date.';else{$expiresAt=date('Y-m-d H:i:s',strtotime($expiry));if(strtotime($expiresAt)<=time())$errors[]='Voucher expiry must be in the future.';}}
 if(!$errors){
  $conn->begin_transaction();
  try{
   $insert=$conn->prepare("INSERT INTO hotspot_vouchers (tenant_id,code,package_id,batch_reference,price,status,expires_at) VALUES (?,?,?,?,?,'unused',?)");
   $accessInsert=$conn->prepare("INSERT INTO hotspot_access_codes (tenant_id,code,package_id,status,expires_at) VALUES (?,?,?,'unused',?)");
   if(!$insert||!$accessInsert)throw new RuntimeException('Unable to prepare voucher and captive-portal code generation.');
   $created=0;
   for($n=0;$n<$quantity;$n++){
    $saved=false;
    for($attempt=0;$attempt<5&&!$saved;$attempt++){
     $code=strtoupper(substr(bin2hex(random_bytes(6)),0,10));
     $voucherPrice=(float)$package['price'];
     $insert->bind_param('isisds',$tenantId,$code,$packageId,$batch,$voucherPrice,$expiresAt);
     if(!$insert->execute()){
      if($insert->errno===1062)continue;
      throw new RuntimeException('Unable to generate voucher batch.');
     }
     $accessInsert->bind_param('isis',$tenantId,$code,$packageId,$expiresAt);
     if(!$accessInsert->execute()){
      if($accessInsert->errno===1062)throw new RuntimeException('Voucher code collision detected. Retry the batch.');
      throw new RuntimeException('Unable to register voucher for captive-portal sales.');
     }
     $saved=true;$created++;
    }
    if(!$saved)throw new RuntimeException('Could not generate a unique voucher code. Retry the batch.');
   }
   $insert->close();$accessInsert->close();$conn->commit();$notice=number_format($created).' vouchers generated in batch '.$batch.' and added to the captive-portal access-code inventory.';
  }catch(Throwable $e){$conn->rollback();if(isset($insert)&&$insert instanceof mysqli_stmt)$insert->close();if(isset($accessInsert)&&$accessInsert instanceof mysqli_stmt)$accessInsert->close();$errors[]=$e->getMessage();}
 }
}
$rows=[];
if($schemaReady){
 $q=$conn->prepare("SELECT v.*,p.name AS package_name FROM hotspot_vouchers v LEFT JOIN hotspot_packages p ON p.id=v.package_id AND p.tenant_id=v.tenant_id WHERE v.tenant_id=? ORDER BY v.id DESC LIMIT 500");
 if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$rows[]=$x;$q->close();}
}
$pageTitle='Hotspot Vouchers';require_once '../includes/header.php';require_once '../includes/sidebar.php';
?>
<div class="main-content hotspot-vouchers">
 <div class="voucher-hero"><div><span>HOTSPOT INVENTORY</span><h1>Voucher Management</h1><p>Generate tenant-specific voucher batches and monitor their issue and expiry status.</p></div><span><?=count($rows)?> vouchers</span></div>
 <?php if(!$schemaReady): ?><div class="voucher-alert">Voucher storage is not installed. Apply the existing hotspot voucher foundation migration before using this page.</div><?php endif; ?>
 <?php foreach($errors as $error): ?><div class="voucher-alert error"><?=e($error)?></div><?php endforeach; ?><?php if($notice): ?><div class="voucher-alert success"><?=e($notice)?></div><?php endif; ?>
 <?php if($schemaReady&&userCan('hotspot','create')): ?><section class="voucher-panel"><h2>Generate Voucher Batch</h2><form method="post"><?=csrfField()?><div class="voucher-form"><label>Hotspot Package<select name="package_id" required><option value="">Select package</option><?php foreach($packages as $package): ?><option value="<?=(int)$package['id']?>"><?=e($package['name'].' · '.formatMoney($package['price']).' · '.(int)$package['duration_minutes'].' min')?></option><?php endforeach; ?></select></label><label>Quantity<input type="number" name="quantity" min="1" max="500" value="20" required></label><label>Expires At (optional)<input type="datetime-local" name="expires_at"></label></div><button class="btn btn-primary">Generate Vouchers</button></form></section><?php endif; ?>
 <section class="voucher-panel"><h2>Voucher Ledger</h2><div class="voucher-table-wrap"><table><thead><tr><th>Code</th><th>Batch</th><th>Package</th><th>Price</th><th>Status</th><th>Sold</th><th>Activated</th><th>Expires</th></tr></thead><tbody><?php foreach($rows as $x): ?><tr><td><strong><?=e($x['code'])?></strong></td><td><?=e($x['batch_reference']??'—')?></td><td><?=e($x['package_name']??'—')?></td><td><?=e(formatMoney($x['price']))?></td><td><?=e(ucfirst($x['status']))?></td><td><?=e($x['sold_at']??'—')?></td><td><?=e($x['activated_at']??'—')?></td><td><?=e($x['expires_at']??'—')?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="8">No vouchers generated yet.</td></tr><?php endif; ?></tbody></table></div></section>
</div>
<style>.hotspot-vouchers{padding:24px;color:#e2e8f0}.voucher-hero{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px;margin-bottom:16px;border:1px solid rgba(59,130,246,.18);border-radius:16px;background:radial-gradient(circle at 90% 10%,rgba(37,99,235,.2),transparent 35%),linear-gradient(135deg,#101827,#0a101a)}.voucher-hero span:first-child{color:#60a5fa;font-size:11px;font-weight:800;letter-spacing:.15em}.voucher-hero h1{margin:7px 0;color:#f8fafc}.voucher-hero p{margin:0;color:#94a3b8}.voucher-panel{padding:18px;margin-bottom:16px;border:1px solid rgba(148,163,184,.14);border-radius:14px;background:linear-gradient(145deg,#101827,#0b111b)}.voucher-panel h2{margin:0 0 14px;color:#f8fafc;font-size:17px}.voucher-form{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;margin-bottom:14px}.voucher-form label{display:grid;gap:6px;color:#cbd5e1;font-size:12px;font-weight:700}.voucher-form input,.voucher-form select{width:100%;box-sizing:border-box;padding:10px;border:1px solid rgba(148,163,184,.2);border-radius:8px;background:#0b111b;color:#f8fafc}.voucher-table-wrap{overflow:auto}.voucher-table-wrap table{width:100%;min-width:900px;border-collapse:collapse}.voucher-table-wrap th,.voucher-table-wrap td{padding:11px 12px;text-align:left;border-bottom:1px solid rgba(148,163,184,.13);color:#e2e8f0}.voucher-table-wrap th{background:#0b111b;color:#94a3b8}.voucher-alert{padding:13px 15px;margin-bottom:14px;border-radius:9px;background:rgba(245,158,11,.13);color:#fcd34d}.voucher-alert.error{background:rgba(239,68,68,.13);color:#fca5a5}.voucher-alert.success{background:rgba(34,197,94,.13);color:#86efac}.hotspot-vouchers .btn{padding:9px 13px;border:0;border-radius:8px;background:#2563eb;color:white;font-weight:700}@media(max-width:800px){.voucher-form{grid-template-columns:1fr}.hotspot-vouchers{padding:14px}.voucher-hero{align-items:flex-start;flex-direction:column}}
</style>
<?php require_once '../includes/footer.php'; ?>
