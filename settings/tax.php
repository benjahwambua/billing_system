<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('settings','view');
require_once '../includes/functions.php';

global $conn;
$tenantId=(int)getCurrentTenantId();
if($tenantId<=0){http_response_code(403);exit('A valid tenant context is required.');}
$errors=[];$success='';
$ready=false;$setting=null;
$check=$conn->query("SHOW TABLES LIKE 'tenant_tax_settings'");
$lineCheck=$conn->query("SHOW TABLES LIKE 'invoice_tax_lines'");
if($check && $lineCheck && $check->num_rows>0 && $lineCheck->num_rows>0){
 $ready=true;
 $stmt=$conn->prepare("SELECT * FROM tenant_tax_settings WHERE tenant_id=? LIMIT 1");
 if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$setting=$stmt->get_result()->fetch_assoc();$stmt->close();}
}
$value=['tax_name'=>(string)($setting['tax_name']??'VAT'),'rate_percent'=>(string)($setting['rate_percent']??'16.0000'),'is_active'=>(int)($setting['is_active']??0)];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!userCan('settings','edit')){http_response_code(403);exit('You do not have permission to change tax settings.');}
 requireCsrf();
 $value['tax_name']=trim((string)($_POST['tax_name']??'VAT'));
 $value['rate_percent']=trim((string)($_POST['rate_percent']??''));
 $value['is_active']=isset($_POST['is_active'])?1:0;
 if(!$ready)$errors[]='Tax configuration and invoice snapshot storage are not both installed. Review and apply migration 045_tenant_tax_invoices.sql first.';
 if($value['tax_name']===''||strlen($value['tax_name'])>80)$errors[]='Enter a tax label of up to 80 characters.';
 if(!is_numeric($value['rate_percent'])||(float)$value['rate_percent']<0||(float)$value['rate_percent']>100)$errors[]='Tax rate must be between 0 and 100 percent.';
 if(!$errors){
  $rate=(float)$value['rate_percent'];
  $stmt=$conn->prepare("INSERT INTO tenant_tax_settings (tenant_id,tax_name,rate_percent,is_active) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE tax_name=VALUES(tax_name),rate_percent=VALUES(rate_percent),is_active=VALUES(is_active)");
  if($stmt){$stmt->bind_param('isdi',$tenantId,$value['tax_name'],$rate,$value['is_active']);if($stmt->execute())$success='Tax settings saved.';else $errors[]='Unable to save tax settings.';$stmt->close();}
  else $errors[]='Unable to prepare tax settings.';
 }
}
$pageTitle='Tax Settings';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
 <div class="page-header"><div><h1>Tax Settings</h1><p>Configure the tax rate applied to new customer invoices. Existing invoices retain their original tax snapshot.</p></div></div>
 <div class="card" style="padding:20px;max-width:760px">
 <?php if(!$ready):?><div class="alert alert-warning">Tax storage is not installed. Review migration <strong>045_tenant_tax_invoices.sql</strong> before applying it to the existing database.</div><?php endif;?>
 <?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?>
 <?php if($success!==''):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
 <form method="post"><?=csrfField()?>
  <div class="form-grid">
   <label>Tax label<input name="tax_name" maxlength="80" value="<?=e($value['tax_name'])?>" required></label>
   <label>Rate (%)<input type="number" name="rate_percent" min="0" max="100" step="0.0001" value="<?=e($value['rate_percent'])?>" required></label>
  </div>
  <label style="display:flex;align-items:center;gap:10px;margin:18px 0"><input type="checkbox" name="is_active" value="1" <?=$value['is_active']?'checked':''?> <?=$ready?'':'disabled'?>> Apply this tax to newly created invoices</label>
  <button class="btn btn-primary" <?=$ready?'':'disabled'?>>Save Tax Settings</button>
 </form>
 <p style="color:#94a3b8;font-size:13px;margin-top:16px">Tax is calculated on the invoice subtotal and added to the amount due (tax-exclusive). The applied label, rate, taxable amount and tax amount are stored against each invoice so later setting changes do not rewrite historical invoices.</p>
 </div>
</div>
<?php require_once '../includes/footer.php';?>
