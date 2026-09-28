<?php
require_once '../includes/auth.php';
requireLogin();
$tenantId=requireTenant();
$pageTitle='Create Customer Internet Account';

function acctCols(){global $conn;$a=[];$r=$conn->query("SHOW COLUMNS FROM internet_accounts");if($r)while($x=$r->fetch_assoc())$a[]=$x['Field'];return $a;}
$cols=acctCols(); if(!$cols)die('Internet accounts table is not available.');
$customers=[];$s=$conn->prepare("SELECT id,customer_number,first_name,last_name FROM customers WHERE tenant_id=? ORDER BY first_name,last_name");if($s){$s->bind_param('i',$tenantId);$s->execute();$r=$s->get_result();while($x=$r->fetch_assoc())$customers[]=$x;$s->close();}
$plans=[];$s=$conn->prepare("SELECT * FROM internet_plans WHERE tenant_id=? AND (status='active' OR status IS NULL) ORDER BY name");if($s){$s->bind_param('i',$tenantId);$s->execute();$r=$s->get_result();while($x=$r->fetch_assoc())$plans[]=$x;$s->close();}
$values=['customer_id'=>'','plan_id'=>'','username'=>'','status'=>'active','activation_date'=>date('Y-m-d'),'expiry_date'=>'','billing_date'=>''];
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf(); foreach($values as $f=>$d)$values[$f]=trim((string)($_POST[$f]??$d));
 if((int)$values['customer_id']<=0)$errors[]='Select a customer.';
 if((int)$values['plan_id']<=0)$errors[]='Select an internet plan.';
 $customerOk=false;$plan=null;
 foreach($customers as $c)if((int)$c['id']===(int)$values['customer_id'])$customerOk=true;
 foreach($plans as $p)if((int)$p['id']===(int)$values['plan_id'])$plan=$p;
 if(!$customerOk)$errors[]='Selected customer is invalid.';
 if(!$plan)$errors[]='Selected plan is invalid.';
 if(!$errors){
  if(in_array('account_number',$cols,true))$values['account_number']=generateInternetAccountNumber();
  $start=$values['activation_date']?:date('Y-m-d'); $expiry=calculateAccountExpiry($start,$plan);
  if(in_array('expiry_date',$cols,true))$values['expiry_date']=$expiry;
  if(in_array('billing_date',$cols,true))$values['billing_date']=$expiry;
  $allowed=['account_number','customer_id','plan_id','username','status','activation_date','expiry_date','billing_date'];
  $ins=[];foreach($allowed as $f)if(in_array($f,$cols,true))$ins[$f]=$values[$f]??'';
  if(in_array('tenant_id',$cols,true))$ins['tenant_id']=$tenantId;
  $fields=array_keys($ins);$sql='INSERT INTO internet_accounts ('.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')';$types='';$bv=[];
  foreach($fields as $f){$types.=$f==='tenant_id'||$f==='customer_id'||$f==='plan_id'?'i':'s';$bv[]=$ins[$f];}
  $s=$conn->prepare($sql);if($s){$b=[$types];foreach($bv as $k=>$v)$b[]=&$bv[$k];call_user_func_array([$s,'bind_param'],$b);if($s->execute()){ $s->close();redirect('index.php');}$errors[]='Unable to create account: '.$s->error;$s->close();}else$errors[]='Unable to prepare account.';
 }
}
require_once '../includes/header.php';?>
<div class="dashboard-card"><?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?>
<form method="post"><?=csrfField()?><div class="form-grid">
<div class="form-group"><label>Customer *</label><select name="customer_id" required><option value="">Select customer</option><?php foreach($customers as $c):?><option value="<?=$c['id']?>" <?=$values['customer_id']==$c['id']?'selected':''?>><?=e($c['customer_number'].' — '.trim($c['first_name'].' '.$c['last_name']))?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Internet Plan *</label><select name="plan_id" required><option value="">Select plan</option><?php foreach($plans as $p):?><option value="<?=$p['id']?>" <?=$values['plan_id']==$p['id']?'selected':''?>><?=e($p['name'].' — '.formatMoney($p['price']??0))?></option><?php endforeach;?></select></div>
<?php if(in_array('username',$cols,true)):?><div class="form-group"><label>Username</label><input name="username" value="<?=e($values['username'])?>"></div><?php endif;?>
<?php if(in_array('activation_date',$cols,true)):?><div class="form-group"><label>Activation Date</label><input type="date" name="activation_date" value="<?=e($values['activation_date'])?>"></div><?php endif;?>
<?php if(in_array('status',$cols,true)):?><div class="form-group"><label>Status</label><select name="status"><?php foreach(['active','pending','suspended','inactive'] as $x):?><option value="<?=$x?>" <?=$values['status']===$x?'selected':''?>><?=ucfirst($x)?></option><?php endforeach;?></select></div><?php endif;?>
</div><div style="margin-top:20px;"><button class="btn btn-primary">Create Account</button> <a href="index.php" class="btn btn-light">Cancel</a></div></form></div><?php require_once '../includes/footer.php';?>