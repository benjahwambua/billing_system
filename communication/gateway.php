<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('communication','view');
global $conn;
$tenantId=(int)getCurrentTenantId();$error='';$success='';$rows=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    $action=(string)($_POST['action']??'');
    if($action==='save'){
        requireModulePermission('communication','edit');
        $id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??''));$provider=trim((string)($_POST['provider']??'generic'));
        $sender=trim((string)($_POST['sender_id']??''));$endpoint=trim((string)($_POST['endpoint_url']??''));
        $method=strtoupper(trim((string)($_POST['api_method']??'POST')));$auth=trim((string)($_POST['auth_type']??'bearer'));
        $template=trim((string)($_POST['request_template']??''));$balance=trim((string)($_POST['balance_endpoint']??''));
        $status=trim((string)($_POST['status']??'inactive'));$credentials=trim((string)($_POST['credentials']??''));
        if($name===''||$endpoint==='')$error='Gateway name and endpoint are required.';
        elseif(!in_array($method,['POST','GET','PUT'],true)||!in_array($auth,['none','bearer','api_key','basic'],true)||!in_array($status,['active','inactive'],true))$error='Invalid gateway configuration.';
        else{
            require_once '../includes/sms_gateway.php';
            $encrypted=$credentials!==''?flexihubSmsEncryptCredentials($credentials):null;
            if($credentials!==''&&$encrypted===false)$error='SMS credentials could not be encrypted. Set FLEXIHUB_SMS_KEY in the server environment.';
            else{
                if($id>0){
                    $sql="UPDATE sms_gateways SET name=?,provider=?,sender_id=?,endpoint_url=?,api_method=?,auth_type=?,request_template=?,balance_endpoint=?,status=?".($encrypted!==null?',credentials_encrypted=?':'')." WHERE id=? AND tenant_id=?";
                    $q=$conn->prepare($sql);
                    if($q){
                        if($encrypted!==null)$q->bind_param('ssssssssssii',$name,$provider,$sender,$endpoint,$method,$auth,$template,$balance,$status,$encrypted,$id,$tenantId);
                        else $q->bind_param('sssssssssii',$name,$provider,$sender,$endpoint,$method,$auth,$template,$balance,$status,$id,$tenantId);
                        $q->execute();$success=$q->affected_rows>=0?'Gateway updated successfully.':'Unable to update gateway.';$q->close();
                    }
                }else{
                    $q=$conn->prepare("INSERT INTO sms_gateways (tenant_id,name,provider,sender_id,endpoint_url,api_method,auth_type,request_template,balance_endpoint,status,credentials_encrypted) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    if($q){$q->bind_param('issssssssss',$tenantId,$name,$provider,$sender,$endpoint,$method,$auth,$template,$balance,$status,$encrypted);$success=$q->execute()?'Gateway saved successfully.':'Unable to save gateway.';$q->close();}
                }
            }
        }
    }elseif($action==='send_test'){
        requireModulePermission('communication','edit');
        require_once '../includes/sms_gateway.php';
        $gatewayId=(int)($_POST['gateway_id']??0);$recipient=trim((string)($_POST['recipient']??''));$message=trim((string)($_POST['message']??''));
        if(!$gatewayId||$recipient===''||$message==='')$error='Gateway, recipient and message are required.';
        else{$result=flexihubSmsSendMessage($tenantId,$gatewayId,$recipient,$message);if($result['success'])$success='Test SMS submitted successfully.';else $error=$result['message'];}
    }
}
$q=$conn->prepare("SELECT * FROM sms_gateways WHERE tenant_id=? ORDER BY id DESC");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$rows[]=$r;$q->close();}
$pageTitle='SMS Gateways';require_once '../includes/header.php';
?>
<div class="dashboard-card"><div class="page-header"><div><h2>SMS Gateway</h2><p>Configure providers, securely store credentials, and submit test messages.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="card" style="padding:20px;margin-bottom:20px"><h3>Gateway Configuration</h3><form method="post"><?=csrfField()?><input type="hidden" name="action" value="save"><div class="form-grid">
<label>Name *<input name="name" required></label><label>Provider<input name="provider" value="generic"></label><label>Sender ID<input name="sender_id"></label><label>Endpoint URL *<input type="url" name="endpoint_url" required></label><label>Method<select name="api_method"><option>POST</option><option>GET</option><option>PUT</option></select></label><label>Authentication<select name="auth_type"><option value="bearer">Bearer</option><option value="api_key">API Key</option><option value="basic">Basic</option><option value="none">None</option></select></label><label>Status<select name="status"><option value="inactive">Inactive</option><option value="active">Active</option></select></label><label>Balance Endpoint<input type="url" name="balance_endpoint"></label><label style="grid-column:1/-1">Request Template (JSON; placeholders: {{recipient}}, {{message}}, {{sender_id}})<textarea name="request_template" rows="3" placeholder='{"to":"{{recipient}}","message":"{{message}}","from":"{{sender_id}}"}'></textarea></label><label style="grid-column:1/-1">Credentials JSON <small>Encrypted at rest; e.g. {"token":"..."} or {"username":"...","password":"..."}</small><textarea name="credentials" rows="2"></textarea></label>
</div><button class="btn btn-primary">Save Gateway</button></form></div>
<div class="card" style="padding:20px;margin-bottom:20px"><h3>Send Test SMS</h3><form method="post"><?=csrfField()?><input type="hidden" name="action" value="send_test"><div class="form-grid"><label>Gateway<select name="gateway_id" required><?php foreach($rows as $x):?><option value="<?=$x['id']?>"><?=e($x['name'])?></option><?php endforeach;?></select></label><label>Recipient<input name="recipient" required></label><label style="grid-column:1/-1">Message<textarea name="message" rows="2" required></textarea></label></div><button class="btn btn-secondary">Send Test</button></form></div>
<div class="table-responsive"><table><thead><tr><th>Name</th><th>Provider</th><th>Sender</th><th>Status</th><th>Balance</th><th>Verified</th></tr></thead><tbody><?php foreach($rows as $x):?><tr><td><?=e($x['name'])?></td><td><?=e($x['provider'])?></td><td><?=e($x['sender_id']??'—')?></td><td><?=e($x['status'])?></td><td><?=e($x['balance']!==null?formatMoney($x['balance']):'—')?></td><td><?=e($x['last_verified_at']??'—')?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="6">No SMS gateways configured.</td></tr><?php endif;?></tbody></table></div></div>
<?php require_once '../includes/footer.php';?>