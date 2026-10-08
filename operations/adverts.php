<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('operations','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$error='';$success='';$rows=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireModulePermission('operations','create');
    requireCsrf();
    $title=trim((string)($_POST['title']??''));
    $message=trim((string)($_POST['message']??''));
    $imageUrl=trim((string)($_POST['image_url']??''));
    $target=trim((string)($_POST['target']??'all'));
    $status=trim((string)($_POST['status']??'draft'));
    $starts=trim((string)($_POST['starts_at']??''));
    $ends=trim((string)($_POST['ends_at']??''));
    $allowedTargets=['all','pppoe','hotspot','active','expired'];
    $allowedStatuses=['draft','scheduled','active','paused','expired'];

    if($title==='')$error='Advert title is required.';
    elseif($message==='')$error='Advert message is required.';
    elseif(!in_array($target,$allowedTargets,true))$error='Invalid target audience.';
    elseif(!in_array($status,$allowedStatuses,true))$error='Invalid advert status.';
    elseif($imageUrl!=='' && !filter_var($imageUrl,FILTER_VALIDATE_URL))$error='Image URL must be a valid URL.';
    elseif($starts!=='' && $ends!=='' && strtotime($ends)<strtotime($starts))$error='End time cannot be before start time.';
    else{
        $starts=$starts!==''?date('Y-m-d H:i:s',strtotime($starts)):null;
        $ends=$ends!==''?date('Y-m-d H:i:s',strtotime($ends)):null;
        $q=$conn->prepare("INSERT INTO adverts
            (tenant_id,title,message,image_url,target,status,starts_at,ends_at)
            VALUES (?,?,?,?,?,?,?,?)");
        if(!$q)$error='Unable to prepare the advert.';
        else{
            $q->bind_param('isssssss',$tenantId,$title,$message,$imageUrl,$target,$status,$starts,$ends);
            if($q->execute())$success='Advert created successfully.';
            else $error='Unable to create the advert.';
            $q->close();
        }
    }
}

$q=$conn->prepare("SELECT * FROM adverts WHERE tenant_id=? ORDER BY id DESC LIMIT 200");
if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$rows[]=$r;$q->close();}

$active=0;$scheduled=0;$drafts=0;
foreach($rows as $r){
    if(($r['status']??'')==='active')$active++;
    if(($r['status']??'')==='scheduled')$scheduled++;
    if(($r['status']??'')==='draft')$drafts++;
}

$pageTitle='Adverts & Announcements';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
<div class="page-header"><div><h2>Adverts &amp; Announcements</h2><p>Create and manage customer-facing campaigns, notices and promotional messages.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0">
<div class="card" style="padding:16px"><small>Active</small><h3><?=e((string)$active)?></h3></div>
<div class="card" style="padding:16px"><small>Scheduled</small><h3><?=e((string)$scheduled)?></h3></div>
<div class="card" style="padding:16px"><small>Drafts</small><h3><?=e((string)$drafts)?></h3></div>
</div>

<div class="card" style="padding:20px;margin-bottom:20px">
<h3>Create Advert</h3>
<form method="post"><?=csrfField()?>
<div class="form-grid">
<label>Title *<input name="title" maxlength="200" required></label>
<label>Target<select name="target"><option value="all">All Customers</option><option value="pppoe">PPPoE</option><option value="hotspot">Hotspot</option><option value="active">Active Customers</option><option value="expired">Expired Customers</option></select></label>
<label>Status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option></select></label>
<label>Image URL<input type="url" name="image_url" maxlength="500"></label>
<label>Starts At<input type="datetime-local" name="starts_at"></label>
<label>Ends At<input type="datetime-local" name="ends_at"></label>
<label style="grid-column:1/-1">Message *<textarea name="message" rows="4" required></textarea></label>
</div>
<button class="btn btn-primary">Create Advert</button>
</form>
</div>

<div>
<?php foreach($rows as $x):?>
<div class="card" style="padding:16px;margin:10px 0">
<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><strong><?=e($x['title'])?></strong><span><?=e($x['status'])?></span></div>
<p><?=nl2br(e($x['message']))?></p>
<small>Target: <?=e($x['target'])?> · <?=e($x['starts_at']??'Immediate')?><?=!empty($x['ends_at'])?' → '.e($x['ends_at']):''?></small>
</div>
<?php endforeach;?>
<?php if(!$rows):?><p>No adverts or announcements yet.</p><?php endif;?>
</div>
</div>
<?php require_once '../includes/footer.php';?>