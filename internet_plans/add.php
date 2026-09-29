<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();requireTenantContext();requireModulePermission('internet_plans','create');
$tenantId=(int)getCurrentTenantId();$pageTitle='Add Internet Plan';$errors=[];
$values=['name'=>'','price'=>'','billing_cycle'=>'monthly','billing_days'=>'','download_speed'=>'','upload_speed'=>'','description'=>'','status'=>'active'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();foreach($values as $f=>$d)$values[$f]=trim((string)($_POST[$f]??$d));
 if($values['name']==='')$errors[]='Plan name is required.';
 if(!is_numeric($values['price'])||(float)$values['price']<0)$errors[]='Price must be a valid amount of 0 or more.';
 if($values['billing_days']!==''&&(!ctype_digit($values['billing_days'])||(int)$values['billing_days']<1))$errors[]='Billing days must be a positive whole number.';
 if(!in_array($values['billing_cycle'],['daily','weekly','monthly','quarterly','yearly'],true))$errors[]='Invalid billing cycle.';
 if(!in_array($values['status'],['active','inactive'],true))$values['status']='active';
 if(!$errors){
  $s=$conn->prepare("INSERT INTO internet_plans (tenant_id,name,price,billing_cycle,billing_days,download_speed,upload_speed,description,status) VALUES (?,?,?,?,?,?,?,?,?)");
  if($s){$s->bind_param('isdssssss',$tenantId,$values['name'],$values['price'],$values['billing_cycle'],$values['billing_days'],$values['download_speed'],$values['upload_speed'],$values['description'],$values['status']);
   if($s->execute()){$id=$s->insert_id;$s->close();logAudit('CREATE','internet_plans','Internet plan created.','internet_plan',$id,null,$values);redirect('index.php');}
   $errors[]='Unable to save the plan: '.$s->error;$s->close();
  }else$errors[]='Unable to prepare the plan: '.$conn->error;
 }
}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Add Internet Plan</h1><p>Define pricing, billing period and speed limits.</p></div><a href="index.php" class="btn btn-light">Back</a></div>
<div class="dashboard-card"><?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?><form method="post"><?=csrfField()?><div class="form-grid">
<div class="form-group"><label>Plan Name *</label><input name="name" value="<?=e($values['name'])?>" required></div><div class="form-group"><label>Price *</label><input type="number" step="0.01" min="0" name="price" value="<?=e($values['price'])?>" required></div>
<div class="form-group"><label>Billing Cycle</label><select name="billing_cycle"><?php foreach(['daily','weekly','monthly','quarterly','yearly'] as $x):?><option value="<?=$x?>" <?=$values['billing_cycle']===$x?'selected':''?>><?=ucfirst($x)?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Billing Days</label><input type="number" min="1" name="billing_days" value="<?=e($values['billing_days'])?>" placeholder="e.g. 30"></div>
<div class="form-group"><label>Download Speed</label><input name="download_speed" value="<?=e($values['download_speed'])?>" placeholder="e.g. 10 Mbps"></div><div class="form-group"><label>Upload Speed</label><input name="upload_speed" value="<?=e($values['upload_speed'])?>" placeholder="e.g. 5 Mbps"></div>
<div class="form-group"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div>
<div class="form-group"><label>Description</label><textarea name="description" rows="4"><?=e($values['description'])?></textarea></div><button class="btn btn-primary">Save Plan</button> <a href="index.php" class="btn btn-light">Cancel</a></form></div></div>
<?php require_once __DIR__.'/../includes/footer.php';?>