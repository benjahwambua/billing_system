<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if(isTenantUser()) requireTenant();
global $conn;
$cols=[];$q=$conn->query("SHOW COLUMNS FROM pppoe_accounts");while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=getCurrentTenantId();$errors=[];
$accounts=[];$sql="SELECT ia.id,ia.account_number,c.first_name,c.last_name FROM internet_accounts ia LEFT JOIN customers c ON c.id=ia.customer_id".($tid?" WHERE ia.tenant_id=".(int)$tid:"")." ORDER BY ia.id DESC";
$r=$conn->query($sql);if($r)while($x=$r->fetch_assoc())$accounts[]=$x;
if($_SERVER['REQUEST_METHOD']==='POST'){requireCsrf();$ia=(int)($_POST['internet_account_id']??0);$user=trim($_POST['username']??'');$secret=$_POST['password']??'';$status=$_POST['status']??'active';
if($has('internet_account_id')&&!$ia)$errors[]='Internet account is required.';
if($has('username')&&!$user)$errors[]='Username is required.';
if($has('password')&&!$secret)$errors[]='Password is required.';
if(!$errors){$map=['internet_account_id'=>$ia,'username'=>$user,'password'=>$secret,'status'=>$status];$f=[];$v=[];$t='';
foreach($map as $c=>$x)if($has($c)){$f[]=$c;$v[]=$x;$t.=is_int($x)?'i':'s';}
if($has('tenant_id')){$f[]='tenant_id';$v[]=$tid;$t.='i';}
$st=$conn->prepare("INSERT INTO pppoe_accounts (".implode(',',$f).") VALUES (".implode(',',array_fill(0,count($f),'?')).")");
if($st){$st->bind_param($t,...$v);if($st->execute())redirect('index.php');$errors[]=$st->error;}}}
?>
<?php require '../includes/header.php';?><div class="page-content"><div class="page-header"><div><h1>Add PPPoE Account</h1><p>Create PPPoE credentials for an internet account.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?>
<div class="card" style="max-width:760px;padding:22px"><form method="post"><?=csrfField()?>
<label>Internet Account<select name="internet_account_id" required><option value="">Select account</option><?php foreach($accounts as $a):?><option value="<?=$a['id']?>"><?=e($a['account_number'].' — '.trim(($a['first_name']??'').' '.($a['last_name']??'')))?></option><?php endforeach;?></select></label>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px"><?php if($has('username')):?><label>Username<input name="username" required></label><?php endif;?><?php if($has('password')):?><label>Credential<input type="password" name="password" required autocomplete="new-password"></label><?php endif;?><?php if($has('status')):?><label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></label><?php endif;?></div>
<button class="btn btn-primary" style="margin-top:18px">Save PPPoE Account</button></form></div></div><?php require '../includes/footer.php';?>