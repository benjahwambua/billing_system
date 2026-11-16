<?php
require_once __DIR__.'/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('customers','edit');
global $conn;
$tenantId=(int)getCurrentTenantId();$customerId=(int)($_GET['id']??$_POST['id']??0);
if($tenantId<=0||$customerId<=0){http_response_code(400);exit('Invalid customer.');}
if(!flexihubTableHasColumn('customer_portal_users','tenant_id')){http_response_code(503);exit('Customer portal schema is not installed.');}
$stmt=$conn->prepare("SELECT id,customer_number,first_name,last_name,email FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
if(!$stmt){http_response_code(503);exit('Unable to load customer.');}
$stmt->bind_param('ii',$customerId,$tenantId);$stmt->execute();$customer=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$customer){http_response_code(404);exit('Customer not found.');}
$error='';$inviteUrl='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 $email=trim((string)($_POST['email']??($customer['email']??'')));
 if($email===''||!filter_var($email,FILTER_VALIDATE_EMAIL))$error='A valid customer email address is required before portal access can be invited.';
 else{
  $token=bin2hex(random_bytes(32));$tokenHash=hash('sha256',$token);$expires=date('Y-m-d H:i:s',time()+172800);
  $q=$conn->prepare("INSERT INTO customer_portal_users (tenant_id,customer_id,email,status,invite_token_hash,invite_expires_at) VALUES (?,?,?,'invited',?,?) ON DUPLICATE KEY UPDATE email=VALUES(email),status='invited',invite_token_hash=VALUES(invite_token_hash),invite_expires_at=VALUES(invite_expires_at)");
  if(!$q)$error='Unable to prepare customer portal invitation.';
  else{$q->bind_param('iisss',$tenantId,$customerId,$email,$tokenHash,$expires);if($q->execute()){$scriptPath=str_replace('\\\\','/',(string)($_SERVER['SCRIPT_NAME']??'/customers/portal_invite.php'));
     $appPath=rtrim(dirname(dirname($scriptPath)),'/');
     $scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?'https':'http';
     $host=(string)($_SERVER['HTTP_HOST']??'localhost');
     $inviteUrl=$scheme.'://'.$host.$appPath.'/customer_portal/activate.php?tenant_id='.$tenantId.'&token='.rawurlencode($token);$customer['email']=$email;}else $error='Unable to create customer portal invitation.';$q->close();}
 }
}
$pageTitle='Customer Portal Invitation';require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<div class="main-content portal-invite"><div class="invite-card"><a href="view.php?id=<?=$customerId?>">← Customer Account</a><h1>Customer Portal Access</h1><p>Create a secure invitation for <?=e(trim(($customer['first_name']??'').' '.($customer['last_name']??'')))?> (<?=e($customer['customer_number']??'')?>).</p>
<?php if($error): ?><div class="invite-alert"><?=e($error)?></div><?php endif; ?>
<?php if($inviteUrl): ?><div class="invite-success"><strong>Invitation created.</strong><p>Share this one-time activation link with the customer. It expires in 48 hours and can only be used once.</p><input readonly value="<?=e($inviteUrl)?>" id="inviteLink"><button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('inviteLink').value)">Copy Invitation Link</button></div><?php else: ?><form method="post"><?=csrfField()?><input type="hidden" name="id" value="<?=$customerId?>"><label>Customer Email<input type="email" name="email" required value="<?=e($customer['email']??'')?>"></label><button class="btn btn-primary">Generate Invitation</button></form><?php endif; ?>
</div></div>
<style>.portal-invite{padding:26px;color:#e2e8f0}.invite-card{max-width:720px;padding:24px;border:1px solid rgba(148,163,184,.16);border-radius:16px;background:linear-gradient(145deg,#101827,#0b111b)}.invite-card>a{color:#60a5fa}.invite-card h1{color:#f8fafc}.invite-card p{color:#94a3b8}.invite-card label{display:grid;gap:7px;color:#cbd5e1;font-weight:700}.invite-card input{width:100%;box-sizing:border-box;padding:12px;border:1px solid rgba(148,163,184,.2);border-radius:9px;background:#0b111b;color:#f8fafc;margin:10px 0}.invite-card .btn{padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:700}.invite-alert{padding:12px;background:rgba(239,68,68,.13);color:#fca5a5;border-radius:8px}.invite-success{padding:15px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);border-radius:10px;color:#86efac}</style>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
