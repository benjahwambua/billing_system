<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/payment_gateway.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('payments','edit');

global $conn;
$tenantId=(int)getCurrentTenantId();
$error='';$message='';$gateway=null;

$q=$conn->prepare("SELECT * FROM payment_gateways WHERE tenant_id=? AND provider='mpesa' ORDER BY is_default DESC,id DESC LIMIT 1");
if($q){$q->bind_param('i',$tenantId);$q->execute();$gateway=$q->get_result()->fetch_assoc();$q->close();}

if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 try{
  $environment=in_array($_POST['environment']??'', ['sandbox','production'],true)?$_POST['environment']:'sandbox';
  $shortcodeType=in_array($_POST['shortcode_type']??'', ['paybill','till'],true)?$_POST['shortcode_type']:'paybill';
  $shortcode=trim((string)($_POST['shortcode']??''));
  $consumerKey=trim((string)($_POST['consumer_key']??''));
  $consumerSecret=trim((string)($_POST['consumer_secret']??''));
  $passkey=trim((string)($_POST['passkey']??''));
  $status=isset($_POST['status'])?'active':'inactive';
  if($shortcode==='')throw new InvalidArgumentException('Business shortcode or Till number is required.');
  if(!$gateway && ($consumerKey===''||$consumerSecret===''||$passkey===''))throw new InvalidArgumentException('Consumer Key, Consumer Secret and STK Passkey are required for the first configuration.');

  if($gateway){
   $callbackToken=(string)($gateway['callback_token']??'');
   if($callbackToken==='')$callbackToken=bin2hex(random_bytes(32));
   $sets=['environment=?','shortcode_type=?','shortcode=?','status=?','callback_token=?'];$vals=[$environment,$shortcodeType,$shortcode,$status,$callbackToken];$types='sssss';
   if($consumerKey!==''){$sets[]='consumer_key_encrypted=?';$vals[]=flexihubGatewayEncrypt($consumerKey);$types.='s';}
   if($consumerSecret!==''){$sets[]='consumer_secret_encrypted=?';$vals[]=flexihubGatewayEncrypt($consumerSecret);$types.='s';}
   if($passkey!==''){$sets[]='passkey_encrypted=?';$vals[]=flexihubGatewayEncrypt($passkey);$types.='s';}
   $callbackUrl=baseUrl().'/payments/mpesa_callback.php?gateway='.(int)$gateway['id'].'&token='.rawurlencode($callbackToken);
   $sets[]='callback_url=?';$vals[]=$callbackUrl;$types.='s';$vals[]=(int)$gateway['id'];$vals[]=$tenantId;$types.='ii';
   $stmt=$conn->prepare("UPDATE payment_gateways SET ".implode(',',$sets)." WHERE id=? AND tenant_id=?");
   if(!$stmt)throw new RuntimeException('Unable to update M-Pesa gateway.');
   $bind=[$types];foreach($vals as $k=>$v)$bind[]=&$vals[$k];call_user_func_array([$stmt,'bind_param'],$bind);$stmt->execute();$stmt->close();
  }else{
   $token=bin2hex(random_bytes(32));
   $stmt=$conn->prepare("INSERT INTO payment_gateways (tenant_id,provider,name,environment,shortcode_type,shortcode,consumer_key_encrypted,consumer_secret_encrypted,passkey_encrypted,callback_token,status,is_default) VALUES (?,'mpesa','M-Pesa',?,?,?,?,?,?,?,1)");
   if(!$stmt)throw new RuntimeException('Unable to create M-Pesa gateway.');
   $a=flexihubGatewayEncrypt($consumerKey);$b=flexihubGatewayEncrypt($consumerSecret);$c=flexihubGatewayEncrypt($passkey);
   $stmt->bind_param('issssssss',$tenantId,$environment,$shortcodeType,$shortcode,$a,$b,$c,$token,$status);$stmt->execute();$id=(int)$conn->insert_id;$stmt->close();
   $url=baseUrl().'/payments/mpesa_callback.php?gateway='.$id.'&token='.rawurlencode($token);
   $stmt=$conn->prepare("UPDATE payment_gateways SET callback_url=? WHERE id=? AND tenant_id=?");$stmt->bind_param('sii',$url,$id,$tenantId);$stmt->execute();$stmt->close();
  }
  $q=$conn->prepare("SELECT * FROM payment_gateways WHERE tenant_id=? AND provider='mpesa' ORDER BY is_default DESC,id DESC LIMIT 1");$q->bind_param('i',$tenantId);$q->execute();$gateway=$q->get_result()->fetch_assoc();$q->close();
  $message='M-Pesa gateway configuration saved.';
 }catch(Throwable $e){$error=$e->getMessage();}
}

$pageTitle='M-Pesa Gateway';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
 <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
  <div><h2>M-Pesa Gateway</h2><p>Each ISP tenant uses its own M-Pesa collection credentials.</p></div>
  <span class="status-badge"><?=e($gateway['status']??'not configured')?></span>
 </div>
 <?php if($message):?><div class="alert alert-success" style="margin-top:18px;"><?=e($message)?></div><?php endif;?>
 <?php if($error):?><div class="alert alert-danger" style="margin-top:18px;"><?=e($error)?></div><?php endif;?>
 <form method="post" style="margin-top:22px;">
  <?=csrfField()?>
  <div class="form-grid">
   <div class="form-group"><label>Environment</label><select name="environment"><option value="sandbox" <?=($gateway['environment']??'sandbox')==='sandbox'?'selected':''?>>Sandbox</option><option value="production" <?=($gateway['environment']??'')==='production'?'selected':''?>>Production</option></select></div>
   <div class="form-group"><label>Shortcode Type</label><select name="shortcode_type"><option value="paybill" <?=($gateway['shortcode_type']??'paybill')==='paybill'?'selected':''?>>PayBill</option><option value="till" <?=($gateway['shortcode_type']??'')==='till'?'selected':''?>>Till</option></select></div>
   <div class="form-group"><label>Business Shortcode / Till</label><input name="shortcode" value="<?=e($gateway['shortcode']??'')?>" required></div>
   <div class="form-group"><label>Status</label><label style="display:flex;gap:8px;align-items:center;margin-top:10px;"><input type="checkbox" name="status" value="active" <?=($gateway['status']??'')==='active'?'checked':''?>> Active</label></div>
   <div class="form-group"><label>Consumer Key</label><input type="password" name="consumer_key" autocomplete="new-password" placeholder="<?=$gateway?'Leave blank to keep current':''?>"></div>
   <div class="form-group"><label>Consumer Secret</label><input type="password" name="consumer_secret" autocomplete="new-password" placeholder="<?=$gateway?'Leave blank to keep current':''?>"></div>
   <div class="form-group"><label>STK Passkey</label><input type="password" name="passkey" autocomplete="new-password" placeholder="<?=$gateway?'Leave blank to keep current':''?>"></div>
  </div>
  <?php if($gateway&&$gateway['callback_url']):?><div class="dashboard-card" style="margin-top:18px;background:#f8f9fa;"><strong>Callback URL</strong><div style="margin-top:8px;word-break:break-all;"><?=e($gateway['callback_url'])?></div><small>Use HTTPS and a publicly reachable hostname in production.</small></div><?php endif;?>
  <button class="btn btn-primary" type="submit" style="margin-top:20px;">Save M-Pesa Configuration</button>
 </form>
</div>
<?php require_once '../includes/footer.php'; ?>