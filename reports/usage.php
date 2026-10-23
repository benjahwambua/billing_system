<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$rows=[];
$loadError=false;
$table='active_sessions';
$columns=[];

// Avoid rendering arbitrary session columns: a future schema may add credentials,
// tokens or other sensitive connection metadata.
$columnResult=$conn->query("SHOW COLUMNS FROM active_sessions");
if(!$columnResult){
    $loadError=true;
    error_log('Flexihub usage report: unable to inspect active-session columns.');
}else{
    while($column=$columnResult->fetch_assoc()){
        $field=(string)$column['Field'];
        if(!preg_match('/^[A-Za-z0-9_]+$/',$field)) continue;
        if(preg_match('/(password|secret|token|credential|private|key|auth|raw|payload)/i',$field)) continue;
        $columns[]=$field;
    }
    $columnResult->close();
    if(!in_array('tenant_id',$columns,true) || !in_array('id',$columns,true)){
        $loadError=true;
    }else{
        $quoted=array_map(static function($field){return '`'.$field.'`';},$columns);
        $sql='SELECT '.implode(', ',$quoted).' FROM active_sessions WHERE tenant_id=? ORDER BY id DESC LIMIT 200';
        $stmt=$conn->prepare($sql);
        if(!$stmt){$loadError=true;error_log('Flexihub usage report: query preparation failed.');}
        else{
            $stmt->bind_param('i',$tenantId);
            if(!$stmt->execute()){$loadError=true;error_log('Flexihub usage report: query execution failed.');}
            else{
                $result=$stmt->get_result();
                if(!$result)$loadError=true;
                else while($row=$result->fetch_assoc())$rows[]=$row;
            }
            $stmt->close();
        }
    }
}
$pageTitle='Usage';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
<h2><?=e($pageTitle)?></h2>
<p>Tenant-scoped active-session usage. Authentication secrets and token fields are excluded.</p>
<?php if($loadError):?>
<p class="alert alert-warning">Usage data could not be loaded. Please try again later.</p>
<?php elseif(!$rows):?>
<p>No records available yet.</p>
<?php else:?>
<div class="table-responsive"><table><thead><tr><?php foreach(array_keys($rows[0]) as $key):?><th><?=e(ucwords(str_replace('_',' ',$key)))?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><?php foreach($row as $value):?><td><?=e($value)?></td><?php endforeach;?></tr><?php endforeach;?>
</tbody></table></div>
<?php endif;?>
</div>
<?php require_once '../includes/footer.php';?>
