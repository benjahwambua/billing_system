<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('customers','edit');

$id=(int)($_GET['id']??0);
$customer=getCustomer($id);
if(!$customer){http_response_code(404);exit('Customer not found.');}

$errors=[];
$values=[
 'first_name'=>(string)($customer['first_name']??''),
 'last_name'=>(string)($customer['last_name']??''),
 'phone'=>(string)($customer['phone']??''),
 'email'=>(string)($customer['email']??''),
 'address'=>(string)($customer['address']??''),
 'status'=>(string)($customer['status']??'active')
];

if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 foreach($values as $k=>$v)$values[$k]=trim((string)($_POST[$k]??$v));
 if($values['first_name']===''&&$values['last_name']==='')$errors[]='Enter the customer first name or last name.';
 if($values['phone']!==''&&!isValidPhone($values['phone']))$errors[]='Enter a valid phone number.';
 if($values['email']!==''&&!isValidEmail($values['email']))$errors[]='Enter a valid email address.';
 if(!in_array($values['status'],['active','inactive','suspended'],true))$values['status']='active';

 if(!$errors){
  $columns=[];$r=$conn->query("SHOW COLUMNS FROM customers");if($r)while($x=$r->fetch_assoc())$columns[$x['Field']]=true;
  $set=[];$params=[];$types='';
  foreach($values as $field=>$value)if(isset($columns[$field])){$set[]="$field=?";$params[]=$value;$types.='s';}
  if(!$set)$errors[]='No editable customer fields are available.';
  else{
   $params[]=$id;$types.='i';
   $sql="UPDATE customers SET ".implode(',',$set)." WHERE id=? AND tenant_id=? LIMIT 1";
   $params[]=(int)getCurrentTenantId();$types.='i';
   $stmt=$conn->prepare($sql);
   if($stmt){
    $stmt->bind_param($types,...$params);
    if($stmt->execute()){
     $new=getCustomer($id);
     logAudit('UPDATE','customers','Customer details updated.','customer',$id,$customer,$new);
     $stmt->close();setFlash('success','Customer updated successfully.');redirect('view.php?id='.$id);
    }
    $errors[]='Unable to update customer: '.$stmt->error;$stmt->close();
   }else $errors[]='Unable to prepare customer update: '.$conn->error;
  }
 }
}
$pageTitle='Edit Customer';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Edit Customer</h1><p><?=e($customer['customer_number']??'')?></p></div><a class="btn btn-secondary" href="view.php?id=<?=$id?>">Back</a></div>
<div class="card" style="padding:22px">
<?php if($errors):?><div class="alert alert-danger"><?php foreach($errors as $err):?><div><?=e($err)?></div><?php endforeach;?></div><?php endif;?>
<form method="post" class="form-grid"><?=csrfField()?>
<div><label>First Name</label><input name="first_name" value="<?=e($values['first_name'])?>" required></div>
<div><label>Last Name</label><input name="last_name" value="<?=e($values['last_name'])?>" required></div>
<div><label>Phone</label><input name="phone" value="<?=e($values['phone'])?>"></div>
<div><label>Email</label><input type="email" name="email" value="<?=e($values['email'])?>"></div>
<div class="full"><label>Address / Location</label><input name="address" value="<?=e($values['address'])?>"></div>
<div><label>Status</label><select name="status"><option value="active" <?=$values['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$values['status']==='inactive'?'selected':''?>>Inactive</option><option value="suspended" <?=$values['status']==='suspended'?'selected':''?>>Suspended</option></select></div>
<div class="full"><button class="btn btn-primary">Save Changes</button></div>
</form></div></div>
<style>
.main-content{padding:24px}.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.page-header h1{margin:0 0 5px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.form-grid .full{grid-column:1/-1}label{display:block;font-size:13px;font-weight:600;margin-bottom:7px}input,select{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px}.btn{display:inline-block;padding:9px 14px;border-radius:6px;border:1px solid transparent;text-decoration:none;font-size:13px;font-weight:600;cursor:pointer}.btn-primary{background:#111827;color:#fff}.btn-secondary{background:#f3f4f6;color:#111827}.alert{padding:12px 14px;border-radius:7px;margin-bottom:18px;background:#fee2e2;color:#991b1b}@media(max-width:700px){.main-content{padding:15px}.form-grid{grid-template-columns:1fr}}
</style>
<?php require_once __DIR__.'/../includes/footer.php'; ?>