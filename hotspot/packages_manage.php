<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$error='';
$success='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    requireModulePermission('hotspot','create');
    requireCsrf();

    $name=trim((string)($_POST['name']??''));
    $duration=max(1,(int)($_POST['duration_minutes']??0));
    $price=round((float)($_POST['price']??0),2);

    if ($name==='') $error='Enter a hotspot plan name.';
    elseif ($price<0) $error='Price cannot be negative.';
    else {
        $stmt=$conn->prepare("INSERT INTO hotspot_packages (tenant_id,name,duration_minutes,price,status) VALUES (?,?,?,?,'active')");
        if ($stmt) {
            $stmt->bind_param('isid',$tenantId,$name,$duration,$price);
            if ($stmt->execute()) {
                logAudit('hotspot_package_created','hotspot','Hotspot package created','hotspot_package',$stmt->insert_id,null,[
                    'name'=>$name,'duration_minutes'=>$duration,'price'=>$price
                ]);
                setFlash('success','Hotspot plan created successfully.');
                redirect('packages.php');
            }
            $error=$stmt->error ?: 'Unable to create hotspot plan.';
            $stmt->close();
        } else {
            $error='Unable to prepare hotspot plan.';
        }
    }
}

$rows=[];
$stmt=$conn->prepare("SELECT id,name,duration_minutes,price,status,created_at FROM hotspot_packages WHERE tenant_id=? ORDER BY id DESC LIMIT 200");
if ($stmt) {
    $stmt->bind_param('i',$tenantId);
    $stmt->execute();
    $r=$stmt->get_result();
    while($x=$r->fetch_assoc()) $rows[]=$x;
    $stmt->close();
}

$pageTitle='Hotspot Plans';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;">
        <div><h2>Hotspot Plans</h2><p>Configure the time-based packages sold through the captive portal.</p></div>
    </div>

    <?php if($error): ?><div class="alert alert-danger" style="margin-top:16px;"><?=e($error)?></div><?php endif; ?>

    <?php if(userCan('hotspot','create')): ?>
    <div class="card" style="padding:20px;margin-top:18px;">
        <h3>Create Hotspot Plan</h3>
        <form method="post">
            <?=csrfField()?>
            <div class="form-grid">
                <label>Plan Name *
                    <input type="text" name="name" maxlength="150" required>
                </label>
                <label>Duration (minutes) *
                    <input type="number" name="duration_minutes" min="1" required>
                </label>
                <label>Price *
                    <input type="number" name="price" min="0" step="0.01" required>
                </label>
            </div>
            <button class="btn btn-primary" type="submit">Create Plan</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if(!$rows): ?>
        <p style="margin-top:20px;">No hotspot plans configured yet.</p>
    <?php else: ?>
        <div class="table-responsive" style="margin-top:20px;">
            <table>
                <thead><tr><th>Name</th><th>Duration</th><th>Price</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach($rows as $x): ?>
                    <tr>
                        <td><?=e($x['name'])?></td>
                        <td><?=e($x['duration_minutes'])?> min</td>
                        <td><?=e(formatMoney($x['price']))?></td>
                        <td><?=e(ucfirst($x['status']))?></td>
                        <td><?=e($x['created_at'])?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php';?>