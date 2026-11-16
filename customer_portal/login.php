<?php
require_once __DIR__.'/../includes/auth.php';
global $conn;
$error='';
if(!empty($_SESSION['customer_portal_authenticated'])){header('Location: index.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 $tenantId=(int)($_POST['tenant_id']??0);$email=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');
 if($tenantId<=0||!filter_var($email,FILTER_VALIDATE_EMAIL)||$password==='')$error='Enter your ISP ID, email and password.';
 else{
  $stmt=$conn->prepare("SELECT p.id,p.tenant_id,p.customer_id,p.email,p.password_hash,p.status FROM customer_portal_users p INNER JOIN tenants t ON t.id=p.tenant_id WHERE p.tenant_id=? AND LOWER(p.email)=LOWER(?) AND p.status='active' AND LOWER(t.status) IN ('active','trial') LIMIT 1");
  if($stmt){$stmt->bind_param('is',$tenantId,$email);$stmt->execute();$account=$stmt->get_result()->fetch_assoc();$stmt->close();}
  else $account=null;
  if(!$account||empty($account['password_hash'])||!password_verify($password,(string)$account['password_hash'])){
   $error='The portal credentials could not be verified.';
  }else{
   $customerCheck=$conn->prepare("SELECT id FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
   if(!$customerCheck){$error='Unable to verify the customer account.';}
   else{$customerCheck->bind_param('ii',$account['customer_id'],$tenantId);$customerCheck->execute();$valid=(bool)$customerCheck->get_result()->fetch_assoc();$customerCheck->close();
    if(!$valid)$error='The portal credentials could not be verified.';
    else{
     session_regenerate_id(true);
     unset($_SESSION['user_id'],$_SESSION['user_scope'],$_SESSION['tenant_id'],$_SESSION['roles'],$_SESSION['role'],$_SESSION['user_role'],$_SESSION['tenant_name'],$_SESSION['session_token']);
     $_SESSION['customer_portal_authenticated']=true;
     $_SESSION['customer_portal_user_id']=(int)$account['id'];
     $_SESSION['customer_portal_tenant_id']=$tenantId;
     $_SESSION['customer_portal_customer_id']=(int)$account['customer_id'];
     $up=$conn->prepare("UPDATE customer_portal_users SET last_login_at=NOW() WHERE id=? AND tenant_id=?");
     if($up){$up->bind_param('ii',$account['id'],$tenantId);$up->execute();$up->close();}
     header('Location: index.php');exit;
    }
   }
  }
 }
}
$tenantPrefill=(int)($_GET['tenant_id']??$_POST['tenant_id']??0);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customer Portal Login</title></head><body><main class="portal-card"><div class="brand">FLEXIHUB <span>Customer Portal</span></div><h1>Welcome back</h1><p>Sign in to view your internet services, invoices and payment history.</p><?php if($error): ?><div class="error"><?=e($error)?></div><?php endif; ?><form method="post"><?=csrfField()?><label>ISP ID<input type="number" min="1" name="tenant_id" required value="<?=$tenantPrefill?>"></label><label>Email<input type="email" name="email" required value="<?=e($_POST['email']??'')?>"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button type="submit">Sign In</button></form><small>Need portal access? Ask your ISP to send you an invitation.</small></main><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:18px;background:radial-gradient(circle at 85% 10%,rgba(37,99,235,.2),transparent 32%),linear-gradient(145deg,#070d17,#101827 55%,#0b111b);font-family:Arial,sans-serif;color:#e2e8f0}.portal-card{width:min(100%,440px);padding:30px;border:1px solid rgba(96,165,250,.18);border-radius:18px;background:linear-gradient(145deg,#101827,#0b111b);box-shadow:0 24px 65px rgba(0,0,0,.35)}.brand{color:#60a5fa;font-weight:900;letter-spacing:.12em}.brand span{display:block;color:#94a3b8;font-size:11px;letter-spacing:.04em;margin-top:4px}.portal-card h1{color:#f8fafc;margin:25px 0 5px}.portal-card p,.portal-card small{color:#94a3b8;line-height:1.6}.portal-card form{display:grid;gap:14px;margin:20px 0}.portal-card label{display:grid;gap:7px;color:#cbd5e1;font-size:13px;font-weight:700}.portal-card input{width:100%;padding:12px;border:1px solid rgba(148,163,184,.22);border-radius:9px;background:#0b111b;color:#f8fafc}.portal-card button{padding:12px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.error{padding:12px;border-radius:9px;background:rgba(239,68,68,.13);color:#fca5a5}</style></body></html>
