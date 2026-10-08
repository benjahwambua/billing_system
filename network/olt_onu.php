<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('network','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$message=''; $error='';

if (empty($_SESSION['olt_onu_csrf'])) {
    $_SESSION['olt_onu_csrf']=bin2hex(random_bytes(32));
}
$csrf=$_SESSION['olt_onu_csrf'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($csrf,(string)($_POST['csrf']??''))) {
        $error='Security validation failed. Please refresh and try again.';
    } elseif (!userCan('network','create')) {
        $error='You do not have permission to add network equipment.';
    } else {
        $type=$_POST['type']??'';
        if ($type==='olt') {
            $name=trim((string)($_POST['name']??''));
            $vendor=trim((string)($_POST['vendor']??''));
            $model=trim((string)($_POST['model']??''));
            $host=trim((string)($_POST['host']??''));
            $protocol=in_array($_POST['protocol']??'snmp',['snmp','api','ssh'],true)?$_POST['protocol']:'snmp';
            $port=max(1,min(65535,(int)($_POST['api_port']??161)));
            $siteId=(int)($_POST['site_id']??0);
            if ($name==='') $error='OLT name is required.';
            else {
                $q=$conn->prepare("INSERT INTO olt_devices (tenant_id,name,vendor,model,host,api_port,protocol,status,site_id) VALUES (?,?,?,?,?,?,?,'active',NULLIF(?,0))");
                if($q){$q->bind_param('issssisi',$tenantId,$name,$vendor,$model,$host,$port,$protocol,$siteId);$q->execute();$q->close();$message='OLT registered successfully.';}
                else $error='Unable to prepare OLT registration.';
            }
        } elseif ($type==='onu') {
            $oltId=(int)($_POST['olt_id']??0);
            $serial=trim((string)($_POST['serial_number']??''));
            $name=trim((string)($_POST['onu_name']??''));
            $pon=trim((string)($_POST['pon_port']??''));
            $customerId=(int)($_POST['customer_id']??0);
            $serviceAccountId=(int)($_POST['service_account_id']??0);
            $mac=trim((string)($_POST['mac_address']??''));
            $vendor=trim((string)($_POST['onu_vendor']??''));
            $model=trim((string)($_POST['onu_model']??''));
            if($serial==='') $error='ONU serial number is required.';
            else {
                $q=$conn->prepare("INSERT INTO onu_devices (tenant_id,olt_id,serial_number,name,pon_port,customer_id,service_account_id,mac_address,vendor,model,status) VALUES (?,?,?,?,?,?,?,?,?,?,'unknown')");
                if($q){$q->bind_param('iisssiisss',$tenantId,$oltId,$serial,$name,$pon,$customerId,$serviceAccountId,$mac,$vendor,$model);$q->execute();$q->close();$message='ONU registered successfully.';}
                else $error='Unable to prepare ONU registration.';
            }
        }
    }
}

$olts=[];$onus=[];$sites=[];
$q=$conn->prepare("SELECT id,name,vendor,model,host,api_port,protocol,status,last_seen_at,polling_enabled FROM olt_devices WHERE tenant_id=? ORDER BY id DESC");
if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$olts[]=$r;$q->close();}
$q=$conn->prepare("SELECT o.*,l.name olt_name FROM onu_devices o LEFT JOIN olt_devices l ON l.id=o.olt_id AND l.tenant_id=o.tenant_id WHERE o.tenant_id=? ORDER BY o.id DESC LIMIT 300");
if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$onus[]=$r;$q->close();}
$q=$conn->prepare("SELECT id,name FROM network_sites WHERE tenant_id=? ORDER BY name");
if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$sites[]=$r;$q->close();}

$pageTitle='OLT & ONU Management';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
  <div class="page-header"><div><h2>OLT &amp; ONU Management</h2><p>Register fiber access equipment, map ONUs to PON ports and monitor live transport reachability. Vendor-specific optical polling can be added through adapters.</p></div></div>
  <?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

  <?php if(userCan('network','create')): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:28px">
    <form method="post" class="dashboard-card" style="margin:0">
      <h3>Register OLT</h3><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="type" value="olt">
      <label>Name</label><input name="name" required>
      <label>Vendor</label><input name="vendor" placeholder="Huawei / ZTE / FiberHome">
      <label>Model</label><input name="model">
      <label>Host / IP</label><input name="host" placeholder="10.0.0.10">
      <label>Protocol</label><select name="protocol"><option value="snmp">SNMP</option><option value="api">API</option><option value="ssh">SSH</option></select>
      <label>Port</label><input type="number" name="api_port" value="161" min="1" max="65535">
      <label>Network Site</label><select name="site_id"><option value="0">Unassigned</option><?php foreach($sites as $s):?><option value="<?=e($s['id'])?>"><?=e($s['name'])?></option><?php endforeach;?></select>
      <button type="submit">Register OLT</button>
    </form>

    <form method="post" class="dashboard-card" style="margin:0">
      <h3>Register ONU</h3><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="type" value="onu">
      <label>OLT</label><select name="olt_id"><option value="0">Unassigned</option><?php foreach($olts as $o):?><option value="<?=e($o['id'])?>"><?=e($o['name'])?></option><?php endforeach;?></select>
      <label>Serial Number</label><input name="serial_number" required>
      <label>Name</label><input name="onu_name">
      <label>PON Port</label><input name="pon_port" placeholder="0/1/1">
      <label>Customer ID</label><input type="number" name="customer_id" min="0">
      <label>Service Account ID</label><input type="number" name="service_account_id" min="0">
      <label>MAC Address</label><input name="mac_address">
      <label>Vendor / Model</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="onu_vendor" placeholder="Vendor"><input name="onu_model" placeholder="Model"></div>
      <button type="submit">Register ONU</button>
    </form>
  </div>
  <?php endif; ?>

  <h3>OLT Inventory</h3>
  <div class="table-responsive"><table><thead><tr><th>OLT</th><th>Vendor / Model</th><th>Host</th><th>Protocol</th><th>Status</th><th>Last Seen</th><th>Last Poll</th><th>Poll Error</th></tr></thead><tbody>
  <?php foreach($olts as $x):?><tr><td><?=e($x['name'])?></td><td><?=e(trim(($x['vendor']??'').' '.($x['model']??''))?:'—')?></td><td><?=e($x['host']??'—')?>:<?=e($x['api_port'])?></td><td><?=e(strtoupper($x['protocol']??'SNMP'))?></td><td><?=e($x['status'])?></td><td><?=e($x['last_seen_at']??'Not seen')?></td><td><?=e($x['last_poll_at']??'Not polled')?></td><td><?=e($x['last_poll_error']??'—')?></td></tr><?php endforeach;?>
  <?php if(!$olts):?><tr><td colspan="8">No OLTs configured.</td></tr><?php endif;?></tbody></table></div>

  <h3 style="margin-top:28px">ONU Inventory</h3>
  <div class="table-responsive"><table><thead><tr><th>Serial</th><th>OLT</th><th>PON</th><th>Customer</th><th>MAC</th><th>Status</th><th>Optical RX/TX</th></tr></thead><tbody>
  <?php foreach($onus as $x):?><tr><td><?=e($x['serial_number']??'—')?></td><td><?=e($x['olt_name']??'Unassigned')?></td><td><?=e($x['pon_port']??'—')?></td><td><?=e($x['customer_id']??'—')?></td><td><?=e($x['mac_address']??'—')?></td><td><?=e($x['status'])?></td><td><?=e(($x['optical_rx_dbm']??$x['signal_rx']??'—').' / '.($x['optical_tx_dbm']??$x['signal_tx']??'—'))?></td></tr><?php endforeach;?>
  <?php if(!$onus):?><tr><td colspan="7">No ONUs discovered or registered.</td></tr><?php endif;?></tbody></table></div>
</div>
<?php require_once '../includes/footer.php'; ?>
