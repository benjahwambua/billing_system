<?php
require_once __DIR__.'/../includes/auth.php';
global $conn;
$tenantId=(int)($_GET['tenant_id']??$_POST['tenant_id']??0);
$token=trim((string)($_GET['token']??$_POST['token']??''));
$error='';$validInvite=null;
if($tenantId>0&&preg_match('/^[a-f0-9]{64}$/',$token)){
 $hash=hash('sha256',$token);
 $stmt=$conn->prepare("SELECT id,customer_id,email FROM customer_portal_users WHERE tenant_id=? AND invite_token_hash=? AND status='invited' AND invite_expires_at>NOW() LIMIT 1");
 if($stmt){$stmt->bind_param('is',$tenantId,$hash);$stmt->execute();$validInvite=$stmt->get_result()->fetch_assoc();$stmt->close();}
}
if(!$validInvite)$error='This invitation is invalid, expired or has already been used.';
if($validInvite&&$_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 $password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
 if(strlen($password)<10)$error='Choose a password with at least 10 characters.';
 elseif($password!==$confirm)$error='The passwords do not match.';
 else{
  $passwordHash=password_hash($password,PASSWORD_DEFAULT);
  $stmt=$conn->prepare("UPDATE customer_portal_users SET password_hash=?,status='active',invite_token_hash=NULL,invite_expires_at=NULL WHERE id=? AND tenant_id=? AND invite_token_hash=? AND status='invited' AND invite_expires_at>NOW()");
  if($stmt){$stmt->bind_param('siis',$passwordHash,$validInvite['id'],$tenantId,$hash);if($stmt->execute()&&$stmt->affected_rows>0){$stmt->close();header('Location: login.php?tenant_id='.$tenantId.'&activated=1');exit;}$stmt->close();$error='Unable to activate this invitation. Please request a new one.';}
  else $error='Unable to activate customer portal access.';
 }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Activate Customer Portal</title></head><body><main class="portal-card"><div class="brand">FLEXIHUB <span>Customer Portal</span></div><h1>Activate your access</h1><?php if($error): ?><div class="error"><?=e($error)?></div><?php endif; ?><?php if($validInvite): ?><p>Set a password for <?=e($validInvite['email'])?>.</p><form method="post"><?=csrfField()?><input type="hidden" name="tenant_id" value="<?=$tenantId?>"><input type="hidden" name="token" value="<?=e($token)?>"><label>Password<input type="password" name="password" minlength="10" required autocomplete="new-password"></label><label>Confirm Password<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label><button type="submit">Activate Portal</button></form><?php else: ?><a href="login.php?tenant_id=<?=$tenantId?>">Return to login</a><?php endif; ?></main><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:18px;background:linear-gradient(145deg,#070d17,#101827 55%,#0b111b);font-family:Arial,sans-serif;color:#e2e8f0}.portal-card{width:min(100%,440px);padding:30px;border:1px solid rgba(96,165,250,.18);border-radius:18px;background:linear-gradient(145deg,#101827,#0b111b);box-shadow:0 24px 65px rgba(0,0,0,.35)}.brand{color:#60a5fa;font-weight:900;letter-spacing:.12em}.brand span{display:block;color:#94a3b8;font-size:11px;margin-top:4px}.portal-card h1{color:#f8fafc}.portal-card p{color:#94a3b8}.portal-card form{display:grid;gap:14px;margin-top:20px}.portal-card label{display:grid;gap:7px;color:#cbd5e1;font-size:13px;font-weight:700}.portal-card input{width:100%;padding:12px;border:1px solid rgba(148,163,184,.22);border-radius:9px;background:#0b111b;color:#f8fafc}.portal-card button{padding:12px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-weight:800}.error{padding:12px;border-radius:9px;background:rgba(239,68,68,.13);color:#fca5a5}</style></body></html>
