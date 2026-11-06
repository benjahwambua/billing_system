<?php
require_once '../includes/auth.php';requireActiveUser();requireTenantContext();requireModulePermission('staff','view');
global $conn;$tenantId=(int)getCurrentTenantId();
$assignmentMessage='';
if($_SERVER['REQUEST_METHOD']==='POST' && userCan('staff','edit')){
    requireCsrf();
    $uid=(int)($_POST['user_id']??0);
    $rid=(int)($_POST['role_id']??0);
    if($uid>0 && $rid>0){
        $userCheck=$conn->prepare("SELECT id FROM users WHERE id=? AND tenant_id=? AND user_scope='tenant' LIMIT 1");
        $roleCheck=$conn->prepare("SELECT id FROM access_roles WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
        if($userCheck && $roleCheck){
            $userCheck->bind_param('ii',$uid,$tenantId);
            $roleCheck->bind_param('ii',$rid,$tenantId);
            $userCheck->execute();
            $roleCheck->execute();
            $validUser=$userCheck->get_result()->fetch_assoc();
            $validRole=$roleCheck->get_result()->fetch_assoc();
            $userCheck->close();
            $roleCheck->close();
            if($validUser && $validRole){
                $q=$conn->prepare("INSERT IGNORE INTO access_user_roles(tenant_id,user_id,role_id) VALUES(?,?,?)");
                if($q){
                    $q->bind_param('iii',$tenantId,$uid,$rid);
                    $assignmentMessage=$q->execute()?'Role assignment saved.':'Unable to save the role assignment.';
                    $q->close();
                } else {
                    $assignmentMessage='Unable to prepare the role assignment.';
                }
            } else {
                $assignmentMessage='The selected user or role does not belong to this organisation, or the role is inactive.';
            }
        } else {
            if($userCheck)$userCheck->close();
            if($roleCheck)$roleCheck->close();
            $assignmentMessage='Unable to verify the selected user and role.';
        }
    } else {
        $assignmentMessage='Select a valid user and role.';
    }
}
$users=[];$q=$conn->prepare("SELECT id,username,role,status FROM users WHERE tenant_id=? ORDER BY username");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$users[]=$x;$q->close();}
$roles=[];$q=$conn->prepare("SELECT id,name FROM access_roles WHERE tenant_id=? AND status='active' ORDER BY name");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$roles[]=$x;$q->close();}
$pageTitle='Users & Access';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Users & Access</h2><p>Assign tenant roles to users. Existing login accounts remain unchanged.</p><?php if($assignmentMessage!==''):?><p role="status"><?=e($assignmentMessage)?></p><?php endif;?>
<?php foreach($users as $u):?><div style="padding:12px 0;border-bottom:1px solid #eee"><strong><?=e($u['username'])?></strong> — <?=e($u['role']??'')?><form method="post" style="display:inline;float:right"><?=csrfField()?><input type="hidden" name="user_id" value="<?=e($u['id'])?>"><select name="role_id" required><option value="">Assign role</option><?php foreach($roles as $r):?><option value="<?=e($r['id'])?>"><?=e($r['name'])?></option><?php endforeach;?></select><button type="submit">Assign</button></form><div style="clear:both"></div></div><?php endforeach;?>
</div><?php require_once '../includes/footer.php';?>