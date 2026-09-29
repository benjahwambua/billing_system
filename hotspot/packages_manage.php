<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireModulePermission('hotspot','create');
    requireCsrf();

    $name=trim((string)($_POST['name']??''));
    $duration=max(1,(int)($_POST['duration_minutes']??0));
    $price=round((float)($_POST['price']??0),2);

    if($name==='') $error='Enter a hotspot plan name.';
    elseif($price<0) $error='Price cannot be negative.';
    else{
        $stmt=$conn->prepare("INSERT INTO hotspot_packages (tenant_id,name,duration_minutes,price,status) VALUES (?,?,?,?,'active')");
        if($stmt){
            $stmt->bind_param('isid',$tenantId,$name,$duration,$price);
            if($stmt->execute()){
                logAudit('hotspot_package_created','hotspot','Hotspot package created','hotspot_package',$stmt->insert_id,null,['name'=>$name,'duration_minutes'=>$duration,'price'=>$price]);
                setFlash('success','Hotspot plan created successfully.');
                redirect('packages.php');
            }
            $error=$stmt->error?:'Unable to create hotspot plan.';
            $stmt->close();
        }else $error='Unable to prepare hotspot plan.';
    }
}

$pageTitle='Create Hotspot Plan';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;">
        <div><h2>Create Hotspot Plan</h2><p>Set the duration and price customers will purchase through the portal.</p></div>
        <a class="btn" href="packages.php">Back to Plans</a>
    </div>
    <?php if($error): ?><div class="alert alert-danger" style="margin-top:18px;"><?=e($error)?></div><?php endif; ?>
    <form method="post" style="margin-top:20px;">
        <?=csrfField()?>
        <div class="form-grid">
            <label>Plan Name *<input type="text" name="name" maxlength="150" required></label>
            <label>Duration (minutes) *<input type="number" name="duration_minutes" min="1" required></label>
            <label>Price *<input type="number" name="price" min="0" step="0.01" required></label>
        </div>
        <button class="btn btn-primary" type="submit">Create Plan</button>
    </form>
</div>
<?php require_once '../includes/footer.php';?>