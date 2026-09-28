<?php
require_once '../includes/auth.php'; requireActiveUser(); requireTenantContext(); requireModulePermission('hotspot','create');
global $conn; $tenantId=(int)getCurrentTenantId(); $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){ requireCsrf(); $name=trim($_POST['name']??''); $duration=max(1,(int)($_POST['duration_minutes']??60)); $price=max(0,(float)($_POST['price']??0)); $status=$_POST['status']??'active';
if($name===''){$error='Plan name is required.';}else{$q=$conn->prepare("INSERT INTO hotspot_packages(tenant_id,name,duration_minutes,price,status) VALUES(?,?,?,?,?)"); if($q){$q->bind_param('isids',$tenantId,$name,$duration,$price,$status); if($q->execute()){redirect('packages.php');} $error=$q->error?:'Unable to save plan.';$q->close();}else{$error=$conn->error;}}}
$pageTitle='Add Hotspot Plan'; require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Add Hotspot Plan</h2><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form method="post"><?=csrfField()?><div class="form-group"><label>Plan Name</label><input name="name" required value="<?=e($_POST['name']??'')?>"></div>
<div class="form-group"><label>Duration (minutes)</label><input type="number" name="duration_minutes" min="1" value="<?=e($_POST['duration_minutes']??60)?>" required></div>
<div class="form-group"><label>Price (KES)</label><input type="number" step="0.01" min="0" name="price" value="<?=e($_POST['price']??0)?>" required></div>
<div class="form-group"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<button type="submit">Save Plan</button> <a href="packages.php">Cancel</a></form></div><?php require_once '../includes/footer.php';?>