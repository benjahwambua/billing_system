<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/payment_gateway.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','create');

global $conn;
$tenantId=(int)getCurrentTenantId();
$error='';

$gateway=null;
$stmt=$conn->prepare("SELECT * FROM payment_gateways WHERE tenant_id=? AND provider='mpesa' AND status='active' ORDER BY is_default DESC,id DESC LIMIT 1");
if($stmt){
    $stmt->bind_param('i',$tenantId);
    $stmt->execute();
    $gateway=$stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$packages=[];
$stmt=$conn->prepare("SELECT id,name,duration_minutes,price FROM hotspot_packages WHERE tenant_id=? AND status='active' ORDER BY name");
if($stmt){
    $stmt->bind_param('i',$tenantId);
    $stmt->execute();
    $r=$stmt->get_result();
    while($row=$r->fetch_assoc()) $packages[]=$row;
    $stmt->close();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();

    $packageId=(int)($_POST['package_id']??0);
    $phone=trim((string)($_POST['phone_number']??''));
    $deviceMac=trim((string)($_POST['device_mac']??''));

    $selected=null;
    foreach($packages as $p){
        if((int)$p['id']===$packageId){$selected=$p;break;}
    }

    try{
        if(!$gateway) throw new RuntimeException('No active M-Pesa gateway is configured for this ISP.');
        if(!$selected) throw new InvalidArgumentException('Select a valid hotspot plan.');
        if($phone==='') throw new InvalidArgumentException('Enter the customer phone number.');
        if($deviceMac!=='' && !preg_match('/^[0-9A-Fa-f:.-]{11,50}$/',$deviceMac)){
            throw new InvalidArgumentException('Enter a valid device MAC address.');
        }

        $amount=round((float)$selected['price'],2);
        if($amount<=0) throw new InvalidArgumentException('The selected hotspot plan has an invalid price.');

        $reference=generateReference('HS');
        $saleId=flexihubWorkflowInsert('hotspot_sales',[
            'tenant_id'=>$tenantId,
            'package_id'=>(int)$selected['id'],
            'amount'=>$amount,
            'phone_number'=>$phone,
            'device_mac'=>$deviceMac,
            'payment_method'=>'mpesa',
            'reference'=>$reference,
            'status'=>'payment_pending'
        ]);
        if(!$saleId) throw new RuntimeException('Unable to create the hotspot sale.');

        $callbackUrl=baseUrl().'/payments/mpesa_callback.php?gateway='.(int)$gateway['id'];
        $idempotencyKey='hotspot-sale-'.$saleId;

        try{
            $tx=flexihubCreateMpesaStkTransaction(
                $tenantId,
                (int)$gateway['id'],
                $amount,
                $phone,
                'hotspot',
                $reference,
                'Hotspot '.$selected['name'],
                $callbackUrl,
                $idempotencyKey,
                null,
                $saleId
            );
        }catch(Throwable $gatewayError){
            $message=substr($gatewayError->getMessage(),0,500);
            $u=$conn->prepare("UPDATE hotspot_sales SET status='payment_failed', reference=? WHERE id=? AND tenant_id=?");
            if($u){$u->bind_param('sii',$message,$saleId,$tenantId);$u->execute();$u->close();}
            throw $gatewayError;
        }

        $gatewayStatus=(string)($tx['status']??'failed');
        $gatewayId=(int)($tx['id']??0);
        $saleStatus=$gatewayStatus==='pending'?'payment_pending':'payment_failed';

        $u=$conn->prepare("UPDATE hotspot_sales SET gateway_transaction_id=?,status=? WHERE id=? AND tenant_id=?");
        if(!$u) throw new RuntimeException('Unable to link the M-Pesa transaction to the hotspot sale.');
        $u->bind_param('isii',$gatewayId,$saleStatus,$saleId,$tenantId);
        $u->execute();
        $u->close();

        if($saleStatus==='payment_failed'){
            throw new RuntimeException($tx['failure_reason'] ?? $tx['result_description'] ?? 'M-Pesa STK Push could not be initiated.');
        }

        logAudit('hotspot_mpesa_stk_initiated','hotspot','Hotspot M-Pesa STK Push initiated','hotspot_sale',$saleId,null,[
            'package_id'=>(int)$selected['id'],
            'amount'=>$amount,
            'phone_number'=>$phone,
            'gateway_transaction_id'=>$gatewayId
        ]);
        setFlash('success','STK Push sent. Complete the M-Pesa prompt on the customer phone.');
        redirect('sales.php');
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$pageTitle='Hotspot M-Pesa Payment';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;">
        <div><h2>Hotspot M-Pesa Payment</h2><p>Initiate an STK Push for a time-based hotspot package.</p></div>
        <a class="btn" href="sales.php">Back to Sales</a>
    </div>

    <?php if($error): ?><div class="alert alert-danger" style="margin-top:18px;"><?=e($error)?></div><?php endif; ?>

    <?php if(!$gateway): ?>
        <div class="alert alert-warning" style="margin-top:18px;">Configure and activate the tenant M-Pesa gateway before taking online hotspot payments.</div>
        <?php if(userCan('payments','edit')): ?><a class="btn btn-primary" href="../settings/mpesa.php">Configure M-Pesa</a><?php endif; ?>
    <?php elseif(!$packages): ?>
        <div class="alert alert-warning" style="margin-top:18px;">Create at least one active hotspot plan first.</div>
        <a class="btn btn-primary" href="packages_manage.php">Create Hotspot Plan</a>
    <?php else: ?>
        <form method="post" style="margin-top:20px;">
            <?=csrfField()?>
            <div class="form-grid">
                <label>Hotspot Plan *
                    <select name="package_id" required>
                        <option value="">Select plan</option>
                        <?php foreach($packages as $p): ?>
                            <option value="<?=e($p['id'])?>"><?=e($p['name'])?> — <?=e($p['duration_minutes'])?> min — <?=e(formatMoney($p['price']))?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Customer Phone *
                    <input type="text" name="phone_number" placeholder="07XXXXXXXX or 2547XXXXXXXX" required>
                </label>
                <label>Device MAC (optional)
                    <input type="text" name="device_mac" placeholder="AA:BB:CC:DD:EE:FF">
                </label>
            </div>
            <button class="btn btn-primary" type="submit">Send STK Push</button>
        </form>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>