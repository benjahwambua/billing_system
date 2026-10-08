<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('settings','view');
global $conn;
$tenantId=(int)getCurrentTenantId();
$error='';$success='';$row=null;
$q=$conn->prepare("SELECT * FROM tenant_theme_settings WHERE tenant_id=? LIMIT 1");
if($q){$q->bind_param('i',$tenantId);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();}

$v=[
 'theme_name'=>$row['theme_name']??'Flexihub Blue',
 'primary_color'=>$row['primary_color']??'#2563eb',
 'accent_color'=>$row['accent_color']??'#3b82f6',
 'dark_mode'=>(int)($row['dark_mode']??1),
 'logo_url'=>$row['logo_url']??''
];

if($_SERVER['REQUEST_METHOD']==='POST'){
 requireModulePermission('settings','edit');
 requireCsrf();
 foreach($v as $k=>$default)$v[$k]=$_POST[$k]??$default;
 $v['theme_name']=trim((string)$v['theme_name']);
 $v['logo_url']=trim((string)$v['logo_url']);
 $v['primary_color']=trim((string)$v['primary_color']);
 $v['accent_color']=trim((string)$v['accent_color']);
 $v['dark_mode']=!empty($_POST['dark_mode'])?1:0;
 if($v['theme_name']==='')$error='Theme name is required.';
 elseif(!preg_match('/^#[0-9A-Fa-f]{6}$/',$v['primary_color']) || !preg_match('/^#[0-9A-Fa-f]{6}$/',$v['accent_color']))$error='Colors must use six-digit hexadecimal values.';
 elseif($v['logo_url']!=='' && !filter_var($v['logo_url'],FILTER_VALIDATE_URL))$error='Logo URL must be a valid URL.';
 else{
   $q=$conn->prepare("INSERT INTO tenant_theme_settings (tenant_id,theme_name,primary_color,accent_color,dark_mode,logo_url)
      VALUES (?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE theme_name=VALUES(theme_name),primary_color=VALUES(primary_color),accent_color=VALUES(accent_color),dark_mode=VALUES(dark_mode),logo_url=VALUES(logo_url)");
   if(!$q)$error='Unable to prepare theme settings.';
   else{
     $q->bind_param('isssis',$tenantId,$v['theme_name'],$v['primary_color'],$v['accent_color'],$v['dark_mode'],$v['logo_url']);
     if($q->execute())$success='Theme and branding settings saved.';
     else $error='Unable to save theme settings.';
     $q->close();
   }
 }
}
$pageTitle='Theme & Branding';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
<div class="page-header"><div><h2>Theme &amp; Branding</h2><p>Configure tenant-specific visual identity for the Flexihub workspace.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="card" style="padding:20px">
<form method="post"><?=csrfField()?>
<div class="form-grid">
<label>Theme Name<input name="theme_name" maxlength="100" value="<?=e($v['theme_name'])?>" required></label>
<label>Logo URL<input type="url" name="logo_url" maxlength="500" value="<?=e($v['logo_url'])?>"></label>
<label>Primary Color<input type="text" name="primary_color" pattern="#[0-9A-Fa-f]{6}" value="<?=e($v['primary_color'])?>" required></label>
<label>Accent Color<input type="text" name="accent_color" pattern="#[0-9A-Fa-f]{6}" value="<?=e($v['accent_color'])?>" required></label>
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="dark_mode" value="1" <?=$v['dark_mode']?'checked':''?>> Dark mode</label>
</div>
<button class="btn btn-primary">Save Branding</button>
</form>
</div>
</div>
<?php require_once '../includes/footer.php';?>