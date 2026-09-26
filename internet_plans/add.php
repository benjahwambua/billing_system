<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin(); $tenantId=requireTenant(); requirePermission('plans.create');
$errors=[]; $d=['name'=>'','download_speed'=>'','upload_speed'=>'','unit'=>'Mbps','price'=>'','billing_cycle'=>'monthly','billing_days'=>'30','mikrotik_profile'=>'','data_limit'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf(); foreach($d as $k=>$v)$d[$k]=trim($_POST[$k]??$v);
 if($d['name']==='')$errors[]='Plan name is required.';
 if(!is_numeric($d['price']) || (float)$d['price']<0)$errors[]='Enter a valid price.';
 if($d['download_speed']==='' || $d['upload_speed']==='')$errors[]='Download and upload speeds are required.';
 if(!$errors){
  $planCode='PLN-'.date('ymdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
  $stmt=$conn->prepare("INSERT INTO internet_plans (tenant_id,plan_code,name,download_speed,upload_speed,unit,price,billing_cycle,billing_days,mikrotik_profile,data_limit,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'active',NOW(),NOW())");
  if(!$stmt)$errors[]='Unable to prepare plan: '.$conn->error;
  else{
   $price=(float)$d['price']; $days=$d['billing_days']===''?null:(int)$d['billing_days'];
   $stmt->bind_param('isssssdsi ss',$tenantId,$planCode,$d['name'],$d['download_speed'],$d['upload_speed'],$d['unit'],$price,$d['billing_cycle'],$days,$d['mikrotik_profile'],$d['data_limit']);
   if($stmt->execute()){ $id=$stmt->insert_id;$stmt->close();logAudit('CREATE','INTERNET_PLAN','Created plan '.$planCode,'internet_plan',$id);$_SESSION['flash_success']='Internet plan created successfully.';redirect('view.php?id='.$id); }
   $errors[]='Unable to create plan: '.$stmt->error;$stmt->close();
  }
 }
}
$pageTitle='Add Internet Plan';require_once __DIR__.'/../includes/header.php'; ?>
<div class="card"><h2>Add Internet Plan</h2>
<?php if($errors): ?><div class="alert alert-danger"><ul><?php foreach($errors as $x): ?><li><?=e($x)?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post"><?=csrfField()?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
<div><label>Plan Name *</label><input name="name" value="<?=e($d['name'])?>" required></div>
<div><label>Price (KES) *</label><input type="number" step="0.01" min="0" name="price" value="<?=e($d['price'])?>" required></div>
<div><label>Download Speed *</label><input name="download_speed" value="<?=e($d['download_speed'])?>" placeholder="10" required></div>
<div><label>Upload Speed *</label><input name="upload_speed" value="<?=e($d['upload_speed'])?>" placeholder="5" required></div>
<div><label>Unit</label><select name="unit"><option>Mbps</option><option>Gbps</option><option>Kbps</option></select></div>
<div><label>Billing Cycle</label><select name="billing_cycle"><option>hourly</option><option>daily</option><option>weekly</option><option>monthly</option><option>quarterly</option><option>yearly</option><option>one_time</option></select></div>
<div><label>Billing Days</label><input type="number" min="1" name="billing_days" value="<?=e($d['billing_days'])?>"></div>
<div><label>MikroTik Profile</label><input name="mikrotik_profile" value="<?=e($d['mikrotik_profile'])?>"></div>
<div style="grid-column:1/-1"><label>Data Limit</label><input name="data_limit" value="<?=e($d['data_limit'])?>" placeholder="e.g. 50GB or Unlimited"></div>
</div><div style="margin-top:20px"><button class="btn btn-primary">Create Plan</button> <a class="btn btn-secondary" href="index.php">Cancel</a></div>
</form></div><?php require_once __DIR__.'/../includes/footer.php'; ?>