<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','edit');

global $conn;$tenantId=(int)getCurrentTenantId();$error='';$message='';$settings=null;
$q=$conn->prepare("SELECT * FROM hotspot_portal_settings WHERE tenant_id=? LIMIT 1");if($q){$q->bind_param('i',$tenantId);$q->execute();$settings=$q->get_result()->fetch_assoc();$q->close();}
if(!$settings){
 $token=bin2hex(random_bytes(32));$q=$conn->prepare("INSERT INTO hotspot_portal_settings (tenant_id,public_token,portal_name,status) VALUES (?,?,?, 'active')");$name='Hotspot Portal';$q->bind_param('iss',$tenantId,$token,$name);$q->execute();$q->close();$settings=['public_token'=>$token,'portal_name'=>$name,'status'=>'active'];
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 try{
  $name=trim((string)($_POST['portal_name']??'Hotspot Portal'));$status=isset($_POST['status'])?'active':'inactive';
  if($name==='')$name='Hotspot Portal';
  if(isset($_POST['regenerate'])){$token=bin2hex(random_bytes(32));$stmt=$conn->prepare("UPDATE hotspot_portal_settings SET public_token=?,portal_name=?,status=? WHERE tenant_id=?");$stmt->bind_param('sssi',$token,$name,$status,$tenantId);}
  else{$token=(string)$settings['public_token'];$stmt=$conn->prepare("UPDATE hotspot_portal_settings SET portal_name=?,status=? WHERE tenant_id=?");$stmt->bind_param('ssi',$name,$status,$tenantId);}
  $stmt->execute();$stmt->close();$message=isset($_POST['regenerate'])?'Portal token regenerated.':'Portal settings saved.';
  $q=$conn->prepare("SELECT * FROM hotspot_portal_settings WHERE tenant_id=? LIMIT 1");$q->bind_param('i',$tenantId);$q->execute();$settings=$q->get_result()->fetch_assoc();$q->close();
 }catch(Throwable $e){$error=$e->getMessage();}
}
$portalUrl=baseUrl().'/hotspot/public.php?token='.rawurlencode($settings['public_token']);
$pageTitle='Hotspot Portal Settings';require_once '../includes/header.php';
?>
<div class="dashboard-card">
 <h2>Hotspot Portal Settings</h2><p>Give customers a tenant-specific public URL without exposing the internal tenant ID.</p>
 <?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?>
 <?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
 <form method="post" style="margin-top:20px;"><?=csrfField()?>
  <div class="form-grid"><div class="form-group"><label>Portal Name</label><input name="portal_name" value="<?=e($settings['portal_name']??'Hotspot Portal')?>" required></div><div class="form-group"><label>Status</label><label style="display:flex;gap:8px;align-items:center;margin-top:10px;"><input type="checkbox" name="status" value="active" <?=($settings['status']??'')==='active'?'checked':''?>> Active</label></div></div>
  <button class="btn btn-primary" name="save" value="1">Save Portal</button><button class="btn" name="regenerate" value="1" style="margin-left:8px;">Regenerate Public Token</button>
 </form>
 <div class="dashboard-card" style="margin-top:20px;background:#f8f9fa;"><strong>Public Portal URL</strong><div style="margin-top:8px;word-break:break-all;"><?=e($portalUrl)?></div></div>
</div>
<?php require_once '../includes/footer.php'; ?>