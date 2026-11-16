<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','edit');
require_once '../includes/billing_workflow.php';
require_once '../includes/hotspot_package_policy.php';

global $conn;
$tenantId=(int)getCurrentTenantId();
$id=(int)($_GET['id']??$_POST['id']??0);
if($tenantId<=0 || $id<=0){http_response_code(400);exit('Invalid hotspot plan.');}

$packageColumns=flexihubTableColumns('hotspot_packages');
$routerColumns=flexihubTableColumns('mikrotik_routers');
$routerNameColumn=in_array('name',$routerColumns,true)?'name':(in_array('router_name',$routerColumns,true)?'router_name':null);
$routerLinkAvailable=in_array('router_id',$packageColumns,true);
$routers=[];
if($routerNameColumn){
    $q=$conn->prepare("SELECT id, `{$routerNameColumn}` AS name FROM mikrotik_routers WHERE tenant_id=? AND status='active' ORDER BY `{$routerNameColumn}`");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$routers[]=$r;$q->close();}
}

$q=$conn->prepare("SELECT * FROM hotspot_packages WHERE id=? AND tenant_id=? LIMIT 1");
if(!$q){http_response_code(500);exit('Unable to load hotspot plan.');}
$q->bind_param('ii',$id,$tenantId);
$q->execute();
$plan=$q->get_result()->fetch_assoc();
$q->close();
if(!$plan){http_response_code(404);exit('Hotspot plan not found.');}

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    $name=trim((string)($_POST['name']??''));
    $minutes=(int)($_POST['duration_minutes']??0);
    $price=(float)($_POST['price']??-1);
    $status=strtolower(trim((string)($_POST['status']??'active')));
    $routerId=(int)($_POST['router_id']??0);
    $routerValid=false;
    if($routerId>0){
        $check=$conn->prepare("SELECT id FROM mikrotik_routers WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
        if($check){$check->bind_param('ii',$routerId,$tenantId);$check->execute();$routerValid=(bool)$check->get_result()->fetch_assoc();$check->close();}
    }
    if($name==='')$error='Plan name is required.';
    elseif($minutes<1)$error='Duration must be at least one minute.';
    elseif(!is_finite($price)||$price<0)$error='Enter a valid non-negative price.';
    elseif(!flexihubIsValidHotspotPackageStatus($status))$error='Select a valid plan status.';
    elseif($routerLinkAvailable && (!$routerValid || $routerId<=0))$error='Select an active router belonging to your organisation.';
    else{
        if($routerLinkAvailable){
            $u=$conn->prepare("UPDATE hotspot_packages SET name=?,duration_minutes=?,price=?,status=?,router_id=? WHERE id=? AND tenant_id=?");
            if($u){$u->bind_param('sidsiii',$name,$minutes,$price,$status,$routerId,$id,$tenantId);}
        }else{
            $u=$conn->prepare("UPDATE hotspot_packages SET name=?,duration_minutes=?,price=?,status=? WHERE id=? AND tenant_id=?");
            if($u){$u->bind_param('sidsii',$name,$minutes,$price,$status,$id,$tenantId);}
        }
        if(empty($u))$error='Unable to prepare hotspot plan update.';
        elseif(!$u->execute())$error='Unable to save hotspot plan changes.';
        else redirect('packages.php');
        if(!empty($u))$u->close();
    }
    $plan=array_merge($plan,['name'=>$name,'duration_minutes'=>$minutes,'price'=>$price,'status'=>$status,'router_id'=>$routerId]);
}

$pageTitle='Edit Hotspot Plan';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content hotspot-plan-edit">
 <div class="page-header"><div><h1>Edit Hotspot Plan</h1><p>Update package duration, price, status and router assignment.</p></div><a class="btn btn-secondary" href="packages.php">Back to plans</a></div>
 <section class="plan-card">
 <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
 <?php if(!$routerLinkAvailable): ?><div class="alert alert-warning">Router association is unavailable in this schema. Apply the reviewed hotspot router migration before using this plan for activation.</div><?php endif; ?>
 <form method="post"><?=csrfField()?><input type="hidden" name="id" value="<?=$id?>">
 <div class="plan-grid">
 <label>Plan Name<input name="name" maxlength="150" required value="<?=e($plan['name']??'')?>"></label>
 <label>Duration (minutes)<input type="number" min="1" name="duration_minutes" required value="<?=e($plan['duration_minutes']??60)?>"></label>
 <label>Price (KES)<input type="number" min="0" step="0.01" name="price" required value="<?=e($plan['price']??0)?>"></label>
 <label>Status<select name="status"><option value="active" <?=strtolower((string)($plan['status']??''))==='active'?'selected':''?>>Active</option><option value="inactive" <?=strtolower((string)($plan['status']??''))==='inactive'?'selected':''?>>Inactive</option></select></label>
 <?php if($routerLinkAvailable): ?><label class="full">MikroTik Router<select name="router_id" required><option value="">Select router</option><?php foreach($routers as $router): ?><option value="<?=(int)$router['id']?>" <?=(int)($plan['router_id']??0)===(int)$router['id']?'selected':''?>><?=e($router['name'])?></option><?php endforeach; ?></select></label><?php endif; ?>
 </div><button class="btn btn-primary" type="submit">Save Changes</button>
 </form></section>
</div>
<style>
.hotspot-plan-edit{padding:26px;color:#e2e8f0}.hotspot-plan-edit .page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:20px}.hotspot-plan-edit h1{margin:0 0 5px;color:#f8fafc}.hotspot-plan-edit p{margin:0;color:#94a3b8}.plan-card{max-width:900px;padding:22px;border:1px solid rgba(148,163,184,.16);border-radius:15px;background:linear-gradient(145deg,#101827,#0b111b)}.plan-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:18px}.plan-grid .full{grid-column:1/-1}.plan-grid label{display:grid;gap:7px;color:#cbd5e1;font-size:12px;font-weight:700}.plan-grid input,.plan-grid select{width:100%;box-sizing:border-box;padding:11px;border:1px solid rgba(148,163,184,.2);border-radius:8px;background:#0b111b;color:#f8fafc}.hotspot-plan-edit .btn{padding:9px 14px;border-radius:8px;text-decoration:none;border:1px solid rgba(148,163,184,.16);font-weight:700}.hotspot-plan-edit .btn-primary{background:#2563eb;color:white}.hotspot-plan-edit .btn-secondary{background:#172235;color:#cbd5e1}.hotspot-plan-edit .alert{padding:12px;margin-bottom:14px;border-radius:8px}.hotspot-plan-edit .alert-danger{background:rgba(239,68,68,.13);color:#fca5a5}.hotspot-plan-edit .alert-warning{background:rgba(245,158,11,.13);color:#fcd34d}@media(max-width:700px){.hotspot-plan-edit{padding:15px}.plan-grid{grid-template-columns:1fr}.plan-grid .full{grid-column:auto}.hotspot-plan-edit .page-header{align-items:flex-start;flex-direction:column}}
</style>
<?php require_once '../includes/footer.php'; ?>
