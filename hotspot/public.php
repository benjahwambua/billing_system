<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/payment_gateway.php';

header('Cache-Control: no-store');
$token=trim((string)($_GET['token']??$_POST['token']??''));
if($token===''){http_response_code(404);exit('Hotspot portal not found.');}

global $conn;$portal=null;
$q=$conn->prepare("SELECT * FROM hotspot_portal_settings WHERE public_token=? AND status='active' LIMIT 1");
if($q){$q->bind_param('s',$token);$q->execute();$portal=$q->get_result()->fetch_assoc();$q->close();}
if(!$portal){http_response_code(404);exit('Hotspot portal not found or inactive.');}
$tenantId=(int)$portal['tenant_id'];$error='';$notice='';$reference='';

$gateway=null;$q=$conn->prepare("SELECT * FROM payment_gateways WHERE tenant_id=? AND provider='mpesa' AND status='active' ORDER BY is_default DESC,id DESC LIMIT 1");if($q){$q->bind_param('i',$tenantId);$q->execute();$gateway=$q->get_result()->fetch_assoc();$q->close();}
$packages=[];$q=$conn->prepare("SELECT id,name,duration_minutes,price FROM hotspot_packages WHERE tenant_id=? AND status='active' ORDER BY price,name");if($q){$q->bind_param('i',$tenantId);$q->execute();$rs=$q->get_result();while($row=$rs->fetch_assoc())$packages[]=$row;$q->close();}

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!$gateway)throw new RuntimeException('M-Pesa payments are not currently available.');
  $packageId=(int)($_POST['package_id']??0);$phone=flexihubMpesaNormalizePhone($_POST['phone_number']??'');$mac=trim((string)($_POST['device_mac']??''));
  if($mac!==''&&!preg_match('/^[0-9A-Fa-f:.-]{11,64}$/',$mac))throw new InvalidArgumentException('Invalid device MAC address.');
  $package=null;foreach($packages as $p)if((int)$p['id']===$packageId){$package=$p;break;}
  if(!$package)throw new InvalidArgumentException('Select a valid hotspot package.');
  $amount=round((float)$package['price'],2);if($amount<=0)throw new InvalidArgumentException('Selected package has an invalid price.');
  $reference='HS'.date('ymdHis').strtoupper(substr(bin2hex(random_bytes(4)),0,8));
  $saleId=flexihubWorkflowInsert('hotspot_sales',['tenant_id'=>$tenantId,'access_code_id'=>0,'package_id'=>$packageId,'amount'=>$amount,'phone_number'=>$phone,'device_mac'=>$mac,'payment_method'=>'mpesa','reference'=>$reference,'status'=>'payment_pending']);
  if(!$saleId)throw new RuntimeException('Unable to create payment request.');
  $callbackUrl=(string)($gateway['callback_url']??'');
  if($callbackUrl==='')throw new RuntimeException('M-Pesa callback URL is not configured.');
  $idempotency='portal-'.$saleId;
  $tx=flexihubCreateMpesaStkTransaction($tenantId,(int)$gateway['id'],$amount,$phone,'hotspot',$reference,'Hotspot '.$package['name'],$callbackUrl,$idempotency,$saleId);
  $txId=(int)($tx['id']??0);$status=(string)($tx['status']??'failed');
  $u=$conn->prepare("UPDATE hotspot_sales SET gateway_transaction_id=?,status=? WHERE id=? AND tenant_id=?");if($u){$u->bind_param('isii',$txId,$status==='pending'?'payment_pending':'payment_failed',$saleId,$tenantId);$u->execute();$u->close();}
  if($status!=='pending')throw new RuntimeException($tx['failure_reason']??$tx['result_description']??'Unable to start M-Pesa payment.');
  $notice='STK Push sent. Complete the payment prompt, then check your access status below.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
$pageTitle=$portal['portal_name']?:'Hotspot Portal';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($pageTitle)?></title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#f4f7fb;color:#172033}.wrap{max-width:560px;margin:40px auto;padding:20px}.card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 8px 30px rgba(0,0,0,.08)}h1{margin-top:0}.plan{border:1px solid #ddd;border-radius:10px;padding:12px;margin:8px 0}.plan label{display:flex;justify-content:space-between;gap:12px}.field{margin:14px 0}.field label{display:block;font-weight:600;margin-bottom:6px}.field input,.field select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ccd3df;border-radius:8px}.btn{border:0;border-radius:8px;padding:12px 18px;cursor:pointer;background:#172033;color:#fff}.alert{padding:12px;border-radius:8px;margin:12px 0}.ok{background:#e9f8ef}.err{background:#fdecec}.status{margin-top:24px;border-top:1px solid #eee;padding-top:20px}</style></head><body><div class="wrap"><div class="card">
<h1><?=e($pageTitle)?></h1><p>Select a package and pay securely through M-Pesa.</p>
<?php if($notice):?><div class="alert ok"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="alert err"><?=e($error)?></div><?php endif;?>
<?php if(!$gateway):?><div class="alert err">Online payments are currently unavailable.</div><?php elseif(!$packages):?><div class="alert err">No hotspot packages are available.</div><?php else:?><form method="post"><input type="hidden" name="token" value="<?=e($token)?>"><div class="field"><label>Package</label><select name="package_id" required><option value="">Select package</option><?php foreach($packages as $p):?><option value="<?=e($p['id'])?>"><?=e($p['name'])?> — <?=e($p['duration_minutes'])?> min — KES <?=e(number_format((float)$p['price'],2))?></option><?php endforeach;?></select></div><div class="field"><label>M-Pesa Phone</label><input name="phone_number" placeholder="07XXXXXXXX" required></div><div class="field"><label>Device MAC (optional)</label><input name="device_mac" placeholder="AA:BB:CC:DD:EE:FF"></div><button class="btn" type="submit">Pay with M-Pesa</button></form><?php endif;?>
<div class="status"><h3>Already paid?</h3><form method="get"><input type="hidden" name="token" value="<?=e($token)?>"><div class="field"><label>Payment Reference</label><input name="reference" value="<?=e($_GET['reference']??$reference)?>" placeholder="HS..." required></div><button class="btn" type="submit">Check Access</button></form>
<?php if(isset($_GET['reference'])&&$_GET['reference']!==''): $ref=trim((string)$_GET['reference']);$s=$conn->prepare("SELECT s.*,p.name package_name FROM hotspot_sales s JOIN hotspot_packages p ON p.id=s.package_id AND p.tenant_id=s.tenant_id WHERE s.tenant_id=? AND s.reference=? LIMIT 1");if($s){$s->bind_param('is',$tenantId,$ref);$s->execute();$sale=$s->get_result()->fetch_assoc();$s->close();}else{$sale=null;}?>
<?php if($sale):?><div class="alert <?=in_array($sale['status'],['access_active','completed'],true)?'ok':'err'?>">Package: <?=e($sale['package_name'])?><br>Status: <?=e($sale['status'])?><?php if(in_array($sale['status'],['access_active','completed'],true)&&!empty($sale['hotspot_username'])): $pw=flexihubGatewayDecrypt($sale['hotspot_password_encrypted']??null);?><br><strong>Username:</strong> <?=e($sale['hotspot_username'])?><br><strong>Password:</strong> <?=e($pw??'')?><br><strong>Expires:</strong> <?=e($sale['expires_at'])?><?php endif;?></div><?php elseif($sale===null):?><div class="alert err">Payment reference not found.</div><?php endif;endif;?></div>
</div></div></body></html>