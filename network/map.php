<?php
require_once '../includes/auth.php'; requireActiveUser(); requireTenantContext(); requireModulePermission('network','view');
global $conn; $tenantId=(int)getCurrentTenantId(); $message=''; $error='';
if(empty($_SESSION['network_map_csrf'])) $_SESSION['network_map_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['network_map_csrf'];

function mapEnsureNode(mysqli $conn,int $tenantId,string $type,int $sourceId,string $label,string $status='active'): int {
    $q=$conn->prepare("SELECT id FROM network_map_nodes WHERE tenant_id=? AND node_type=? AND node_id=? LIMIT 1");
    $q->bind_param('isi',$tenantId,$type,$sourceId); $q->execute(); $row=$q->get_result()->fetch_assoc(); $q->close();
    if($row){$id=(int)$row['id'];$u=$conn->prepare("UPDATE network_map_nodes SET label=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?");$u->bind_param('ssii',$label,$status,$id,$tenantId);$u->execute();$u->close();return $id;}
    $i=$conn->prepare("INSERT INTO network_map_nodes(tenant_id,node_type,node_id,label,status) VALUES(?,?,?,?,?)");$i->bind_param('isiss',$tenantId,$type,$sourceId,$label,$status);$i->execute();$id=(int)$i->insert_id;$i->close();return $id;
}
function mapEnsureLink(mysqli $conn,int $tenantId,int $source,int $target,string $type,string $status='active'): void {
    if($source===$target)return;
    $q=$conn->prepare("SELECT id FROM network_map_links WHERE tenant_id=? AND source_node_id=? AND target_node_id=? AND link_type=? LIMIT 1");$q->bind_param('iiis',$tenantId,$source,$target,$type);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
    if($row){$u=$conn->prepare("UPDATE network_map_links SET status=? WHERE id=? AND tenant_id=?");$u->bind_param('sii',$status,$row['id'],$tenantId);$u->execute();$u->close();return;}
    $i=$conn->prepare("INSERT INTO network_map_links(tenant_id,source_node_id,target_node_id,link_type,status) VALUES(?,?,?,?,?)");$i->bind_param('iiiss',$tenantId,$source,$target,$type,$status);$i->execute();$i->close();
}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='sync'){
    requireCsrf();
    if(!userCan('network','edit')) $error='You do not have permission to synchronize network topology.';
    else try{
        $conn->begin_transaction(); $counts=['sites'=>0,'routers'=>0,'olts'=>0,'onus'=>0];
        $siteNodes=[];
        $q=$conn->prepare("SELECT id,name,status FROM network_sites WHERE tenant_id=? ORDER BY id");$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();
        while($row=$z->fetch_assoc()){$siteNodes[(int)$row['id']]=mapEnsureNode($conn,$tenantId,'site',(int)$row['id'],$row['name'],$row['status']??'active');$counts['sites']++;}$q->close();

        $routerNodes=[];$routerSite=[];$cols=[];$c=$conn->query("SHOW COLUMNS FROM mikrotik_routers");while($c&&($row=$c->fetch_assoc()))$cols[]=$row['Field'];
        $nameCol=in_array('name',$cols,true)?'name':(in_array('router_name',$cols,true)?'router_name':null);$statusCol=in_array('status',$cols,true)?'status':null;$siteCol=in_array('site_id',$cols,true)?'site_id':null;
        $sql="SELECT id".($nameCol?", ".$nameCol." AS display_name":"").($statusCol?", ".$statusCol." AS router_status":"").($siteCol?", ".$siteCol." AS site_id":"")." FROM mikrotik_routers";
        if(in_array('tenant_id',$cols,true))$sql.=" WHERE tenant_id=?";
        $q=$conn->prepare($sql);if(in_array('tenant_id',$cols,true))$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();
        while($row=$z->fetch_assoc()){$routerNodes[(int)$row['id']]=mapEnsureNode($conn,$tenantId,'router',(int)$row['id'],$row['display_name']??('Router #'.$row['id']),$row['router_status']??'active');if(isset($row['site_id']))$routerSite[(int)$row['id']]=(int)$row['site_id'];$counts['routers']++;}$q->close();
        foreach($routerSite as $rid=>$sid)if(isset($siteNodes[$sid]))mapEnsureLink($conn,$tenantId,$siteNodes[$sid],$routerNodes[$rid],'ethernet');

        $oltNodes=[];$q=$conn->prepare("SELECT id,name,status,site_id FROM olt_devices WHERE tenant_id=? ORDER BY id");$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();
        while($row=$z->fetch_assoc()){$oltNodes[(int)$row['id']]=mapEnsureNode($conn,$tenantId,'olt',(int)$row['id'],$row['name'],$row['status']??'active');if(!empty($row['site_id'])&&isset($siteNodes[(int)$row['site_id']]))mapEnsureLink($conn,$tenantId,$siteNodes[(int)$row['site_id']],$oltNodes[(int)$row['id']],'fiber');$counts['olts']++;}$q->close();

        $q=$conn->prepare("SELECT id,name,status,olt_id FROM onu_devices WHERE tenant_id=? ORDER BY id");$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();
        while($row=$z->fetch_assoc()){$onuId=mapEnsureNode($conn,$tenantId,'onu',(int)$row['id'],$row['name']?:('ONU #'.$row['id']),$row['status']??'unknown');if(!empty($row['olt_id'])&&isset($oltNodes[(int)$row['olt_id']]))mapEnsureLink($conn,$tenantId,$oltNodes[(int)$row['olt_id']],$onuId,'pon');$counts['onus']++;}$q->close();
        $conn->commit();$q=$conn->prepare("SELECT COUNT(*) total FROM network_map_links WHERE tenant_id=?");$q->bind_param('i',$tenantId);$q->execute();$linksTotal=(int)$q->get_result()->fetch_assoc()['total'];$q->close();
        $message='Topology synchronized: '.$counts['sites'].' sites, '.$counts['routers'].' routers, '.$counts['olts'].' OLTs, '.$counts['onus'].' ONUs and '.$linksTotal.' links.';
    }catch(Throwable $e){$conn->rollback();$error='Topology synchronization failed: '.$e->getMessage();}
}
$nodes=[];$links=[];
$q=$conn->prepare("SELECT * FROM network_map_nodes WHERE tenant_id=? ORDER BY node_type,label");$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$nodes[]=$r;$q->close();
$q=$conn->prepare("SELECT l.*,s.label source_label,t.label target_label FROM network_map_links l LEFT JOIN network_map_nodes s ON s.id=l.source_node_id AND s.tenant_id=l.tenant_id LEFT JOIN network_map_nodes t ON t.id=l.target_node_id AND t.tenant_id=l.tenant_id WHERE l.tenant_id=? ORDER BY l.id");$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$links[]=$r;$q->close();
$nodeCounts=[];foreach($nodes as $n)$nodeCounts[$n['node_type']]=($nodeCounts[$n['node_type']]??0)+1;
$pageTitle='Network Map';require_once '../includes/header.php';?>
<div class="dashboard-card"><div class="page-header"><div><h2>Network Map</h2><p>Tenant-scoped topology inventory for sites, routers, OLTs and ONUs.</p></div><?php if(userCan('network','edit')): ?><form method="post" style="margin:0"><?=csrfField()?><input type="hidden" name="action" value="sync"><button class="btn btn-primary" type="submit">Synchronize Topology</button></form><?php endif; ?></div>
<?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px"><?php foreach(['site'=>'Sites','router'=>'Routers','olt'=>'OLTs','onu'=>'ONUs'] as $type=>$label): ?><div class="card" style="padding:16px"><small><?=e($label)?></small><h3 style="margin:6px 0"><?=e((string)($nodeCounts[$type]??0))?></h3></div><?php endforeach; ?></div>
<div class="table-responsive"><table><thead><tr><th>Node</th><th>Type</th><th>Status</th></tr></thead><tbody><?php foreach($nodes as $n): ?><tr><td><?=e($n['label'])?></td><td><?=e(strtoupper($n['node_type']))?></td><td><?=e($n['status'])?></td></tr><?php endforeach; ?><?php if(!$nodes): ?><tr><td colspan="3">No topology nodes configured yet.</td></tr><?php endif; ?></tbody></table></div>
<div style="margin-top:24px" class="table-responsive"><table><thead><tr><th>Source</th><th>Target</th><th>Link</th><th>Bandwidth</th><th>Status</th></tr></thead><tbody><?php foreach($links as $l): ?><tr><td><?=e($l['source_label']??$l['source_node_id'])?></td><td><?=e($l['target_label']??$l['target_node_id'])?></td><td><?=e($l['link_type'])?></td><td><?=e($l['bandwidth_mbps']??'—')?> Mbps</td><td><?=e($l['status'])?></td></tr><?php endforeach; ?><?php if(!$links): ?><tr><td colspan="5">No topology links configured yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>