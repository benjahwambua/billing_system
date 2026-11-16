<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('inventory','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$userId=(int)getCurrentUserId();
$errors=[];
$notice='';
$schemaReady=flexihubTableHasColumn('inventory_items','tenant_id')
    && flexihubTableHasColumn('inventory_locations','tenant_id')
    && flexihubTableHasColumn('inventory_movements','tenant_id');

if($schemaReady && $_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    $action=trim((string)($_POST['action']??''));
    if($action==='location'){
        requireModulePermission('inventory','create');
        $name=trim((string)($_POST['location_name']??''));
        $type=trim((string)($_POST['location_type']??'store'));
        if($name===''||mb_strlen($name)>150)$errors[]='Enter a location name of up to 150 characters.';
        if(!in_array($type,['store','warehouse','van','site'],true))$errors[]='Select a valid location type.';
        if(!$errors){
            $stmt=$conn->prepare("INSERT INTO inventory_locations (tenant_id,name,location_type,status) VALUES (?,?,?,'active')");
            if(!$stmt)$errors[]='Unable to prepare location creation.';
            else{$stmt->bind_param('iss',$tenantId,$name,$type);if($stmt->execute())$notice='Inventory location created.';elseif($stmt->errno===1062)$errors[]='A location with this name already exists.';else $errors[]='Unable to create inventory location.';$stmt->close();}
        }
    } elseif($action==='item'){
        requireModulePermission('inventory','create');
        $sku=strtoupper(trim((string)($_POST['sku']??'')));
        $name=trim((string)($_POST['name']??''));
        $category=trim((string)($_POST['category']??''));
        $unit=trim((string)($_POST['unit']??'pcs'));
        $description=trim((string)($_POST['description']??''));
        $cost=(float)($_POST['unit_cost']??0);
        $reorder=(float)($_POST['reorder_level']??0);
        if($sku===''||mb_strlen($sku)>80)$errors[]='Enter a SKU of up to 80 characters.';
        if($name===''||mb_strlen($name)>180)$errors[]='Enter an item name of up to 180 characters.';
        if($unit===''||mb_strlen($unit)>30)$errors[]='Enter a valid unit of measure.';
        if($cost<0||!is_finite($cost)||$reorder<0||!is_finite($reorder))$errors[]='Cost and reorder level must be valid non-negative numbers.';
        if(mb_strlen($description)>5000)$errors[]='Item description is too long.';
        if(!$errors){
            $stmt=$conn->prepare("INSERT INTO inventory_items (tenant_id,sku,name,category,unit,description,unit_cost,reorder_level,status) VALUES (?,?,?,?,?,?,?,?,'active')");
            if(!$stmt)$errors[]='Unable to prepare item creation.';
            else{$stmt->bind_param('isssssdd',$tenantId,$sku,$name,$category,$unit,$description,$cost,$reorder);if($stmt->execute())$notice='Inventory item created.';elseif($stmt->errno===1062)$errors[]='That SKU already exists in this organisation.';else $errors[]='Unable to create inventory item.';$stmt->close();}
        }
    } elseif($action==='movement'){
        requireModulePermission('inventory','create');
        $itemId=(int)($_POST['item_id']??0);
        $locationId=(int)($_POST['location_id']??0);
        $type=trim((string)($_POST['movement_type']??'receipt'));
        $quantity=(float)($_POST['quantity']??0);
        $unitCost=(float)($_POST['unit_cost']??0);
        $reference=trim((string)($_POST['reference']??''));
        $notes=trim((string)($_POST['notes']??''));
        if($itemId<=0||$locationId<=0)$errors[]='Select an item and stock location.';
        if(!in_array($type,['receipt','issue','adjustment'],true))$errors[]='Select a valid stock movement type.';
        if(!is_finite($quantity)||$quantity==0)$errors[]='Enter a non-zero stock quantity.';
        if(!is_finite($unitCost)||$unitCost<0)$errors[]='Enter a valid non-negative unit cost.';
        if(!$errors){
            $conn->begin_transaction();
            try{
                $itemCheck=$conn->prepare("SELECT id FROM inventory_items WHERE id=? AND tenant_id=? AND status='active' FOR UPDATE");
                if(!$itemCheck)throw new RuntimeException('Unable to verify inventory item.');
                $itemCheck->bind_param('ii',$itemId,$tenantId);$itemCheck->execute();$itemExists=(bool)$itemCheck->get_result()->fetch_assoc();$itemCheck->close();
                if(!$itemExists)throw new RuntimeException('The selected item is not active in this organisation.');
                $locCheck=$conn->prepare("SELECT id FROM inventory_locations WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
                if(!$locCheck)throw new RuntimeException('Unable to verify inventory location.');
                $locCheck->bind_param('ii',$locationId,$tenantId);$locCheck->execute();$locExists=(bool)$locCheck->get_result()->fetch_assoc();$locCheck->close();
                if(!$locExists)throw new RuntimeException('The selected location is not active in this organisation.');
                $delta=$type==='receipt'?abs($quantity):($type==='issue'?-abs($quantity):$quantity);
                $stockStmt=$conn->prepare("SELECT COALESCE(SUM(quantity_delta),0) AS on_hand FROM inventory_movements WHERE tenant_id=? AND item_id=? AND location_id=?");
                if(!$stockStmt)throw new RuntimeException('Unable to calculate current stock.');
                $stockStmt->bind_param('iii',$tenantId,$itemId,$locationId);$stockStmt->execute();$onHand=(float)($stockStmt->get_result()->fetch_assoc()['on_hand']??0);$stockStmt->close();
                if($type==='issue' && abs($delta)>$onHand+0.000001)throw new RuntimeException('Insufficient stock at this location. Current stock: '.number_format($onHand,3).'.');
                if($type==='adjustment' && $onHand+$delta < -0.000001)throw new RuntimeException('Adjustment cannot reduce stock below zero.');
                $stmt=$conn->prepare("INSERT INTO inventory_movements (tenant_id,item_id,location_id,movement_type,quantity_delta,unit_cost,reference,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
                if(!$stmt)throw new RuntimeException('Unable to prepare stock movement.');
                $stmt->bind_param('iiisddssi',$tenantId,$itemId,$locationId,$type,$delta,$unitCost,$reference,$notes,$userId);
                if(!$stmt->execute())throw new RuntimeException('Unable to save stock movement.');
                $stmt->close();$conn->commit();$notice='Stock movement recorded.';
            }catch(Throwable $e){$conn->rollback();$errors[]=$e->getMessage();}
        }
    } else $errors[]='Invalid inventory action.';
}

$locations=[];$items=[];$movements=[];
if($schemaReady){
    $q=$conn->prepare("SELECT id,name,location_type FROM inventory_locations WHERE tenant_id=? AND status='active' ORDER BY name");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$locations[]=$x;$q->close();}
    $q=$conn->prepare("SELECT i.*,COALESCE(SUM(m.quantity_delta),0) AS on_hand
        FROM inventory_items i LEFT JOIN inventory_movements m ON m.item_id=i.id AND m.tenant_id=i.tenant_id
        WHERE i.tenant_id=? GROUP BY i.id ORDER BY i.name");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$items[]=$x;$q->close();}
    $q=$conn->prepare("SELECT m.*,i.sku,i.name item_name,l.name location_name
        FROM inventory_movements m INNER JOIN inventory_items i ON i.id=m.item_id AND i.tenant_id=m.tenant_id
        INNER JOIN inventory_locations l ON l.id=m.location_id AND l.tenant_id=m.tenant_id
        WHERE m.tenant_id=? ORDER BY m.id DESC LIMIT 25");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$movements[]=$x;$q->close();}
}
$lowStock=0;foreach($items as $item){$threshold=(float)$item['reorder_level'];$onHand=(float)$item['on_hand'];if($onHand<=0||($threshold>0&&$onHand<=$threshold))$lowStock++;}
$pageTitle='Inventory';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/sidebar.php';
?>
<div class="main-content inventory-page">
 <div class="inventory-hero"><div><span>STOCK CONTROL</span><h1>Inventory Management</h1><p>Track stock items, storage locations, receipts, issues and reorder thresholds.</p></div><span class="inventory-count"><?=count($items)?> items</span></div>
 <?php if(!$schemaReady): ?><div class="inventory-alert">Inventory tables are not yet available. The tenant-scoped inventory migration is <code>database/040_inventory.sql</code>; it is not applied automatically.</div><?php endif; ?>
 <?php foreach($errors as $error): ?><div class="inventory-alert error"><?=e($error)?></div><?php endforeach; ?>
 <?php if($notice): ?><div class="inventory-alert success"><?=e($notice)?></div><?php endif; ?>
 <?php if($schemaReady): ?>
 <div class="inventory-metrics"><div><small>Items</small><strong><?=count($items)?></strong></div><div><small>Locations</small><strong><?=count($locations)?></strong></div><div><small>Low / Reorder</small><strong><?=$lowStock?></strong></div><div><small>Recent movements</small><strong><?=count($movements)?></strong></div></div>
 <div class="inventory-grid">
 <?php if(userCan('inventory','create')): ?>
 <section class="inventory-panel"><h2>Add Inventory Item</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="item"><div class="inventory-form"><label>SKU *<input name="sku" maxlength="80" required></label><label>Item Name *<input name="name" maxlength="180" required></label><label>Category<input name="category" maxlength="100"></label><label>Unit<input name="unit" maxlength="30" value="pcs" required></label><label>Unit Cost (KES)<input type="number" min="0" step="0.01" name="unit_cost" value="0"></label><label>Reorder Level<input type="number" min="0" step="0.001" name="reorder_level" value="0"></label><label class="full">Description<textarea name="description" rows="2"></textarea></label></div><button class="btn btn-primary">Save Item</button></form></section>
 <section class="inventory-panel"><h2>Add Location</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="location"><div class="inventory-form"><label>Location Name *<input name="location_name" maxlength="150" required></label><label>Location Type<select name="location_type"><option value="store">Store</option><option value="warehouse">Warehouse</option><option value="van">Technician Van</option><option value="site">Field Site</option></select></label></div><button class="btn btn-primary">Save Location</button></form></section>
 <section class="inventory-panel"><h2>Record Stock Movement</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="movement"><div class="inventory-form"><label>Item *<select name="item_id" required><option value="">Select item</option><?php foreach($items as $item): ?><option value="<?=(int)$item['id']?>"><?=e($item['sku'].' — '.$item['name'])?></option><?php endforeach; ?></select></label><label>Location *<select name="location_id" required><option value="">Select location</option><?php foreach($locations as $location): ?><option value="<?=(int)$location['id']?>"><?=e($location['name'])?></option><?php endforeach; ?></select></label><label>Movement Type<select name="movement_type"><option value="receipt">Receipt / Stock In</option><option value="issue">Issue / Stock Out</option><option value="adjustment">Adjustment (+/-)</option></select></label><label>Quantity *<input type="number" step="0.001" name="quantity" required></label><label>Unit Cost (KES)<input type="number" min="0" step="0.01" name="unit_cost" value="0"></label><label>Reference<input name="reference" maxlength="120"></label><label class="full">Notes<textarea name="notes" rows="2"></textarea></label></div><button class="btn btn-primary">Post Movement</button></form></section>
 <?php endif; ?>
 </div>
 <section class="inventory-panel"><div class="inventory-heading"><div><h2>Stock on Hand</h2><p>Balances are calculated from the movement ledger.</p></div></div><div class="inventory-table-wrap"><table><thead><tr><th>SKU</th><th>Item</th><th>Category</th><th>Unit</th><th>On Hand</th><th>Reorder Level</th><th>Status</th></tr></thead><tbody><?php foreach($items as $item): $threshold=(float)$item['reorder_level'];$onHand=(float)$item['on_hand'];$low=$onHand<=0||($threshold>0&&$onHand<=$threshold);$stockLabel=$onHand<=0?'No Stock':($low?'Reorder':'In Stock'); ?><tr><td><?=e($item['sku'])?></td><td><strong><?=e($item['name'])?></strong></td><td><?=e($item['category']??'—')?></td><td><?=e($item['unit'])?></td><td><?=number_format((float)$item['on_hand'],3)?></td><td><?=number_format((float)$item['reorder_level'],3)?></td><td><span class="<?=$low?'low':'good'?>"><?=$stockLabel?></span></td></tr><?php endforeach; ?><?php if(!$items): ?><tr><td colspan="7">No inventory items have been created.</td></tr><?php endif; ?></tbody></table></div></section>
 <section class="inventory-panel"><h2>Recent Stock Movements</h2><div class="inventory-table-wrap"><table><thead><tr><th>Date</th><th>Item</th><th>Location</th><th>Type</th><th>Quantity Change</th><th>Reference</th></tr></thead><tbody><?php foreach($movements as $movement): ?><tr><td><?=e($movement['created_at'])?></td><td><?=e($movement['sku'].' — '.$movement['item_name'])?></td><td><?=e($movement['location_name'])?></td><td><?=e(ucfirst($movement['movement_type']))?></td><td><?=number_format((float)$movement['quantity_delta'],3)?></td><td><?=e($movement['reference']??'—')?></td></tr><?php endforeach; ?><?php if(!$movements): ?><tr><td colspan="6">No stock movements recorded yet.</td></tr><?php endif; ?></tbody></table></div></section>
 <?php endif; ?>
</div>
<style>
.inventory-page{padding:24px;color:#e2e8f0}.inventory-hero{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px;margin-bottom:16px;border:1px solid rgba(59,130,246,.18);border-radius:16px;background:radial-gradient(circle at 90% 10%,rgba(37,99,235,.2),transparent 35%),linear-gradient(135deg,#101827,#0a101a)}.inventory-hero span:first-child{color:#60a5fa;font-size:11px;font-weight:800;letter-spacing:.15em}.inventory-hero h1{margin:7px 0;color:#f8fafc}.inventory-hero p,.inventory-heading p{margin:0;color:#94a3b8}.inventory-count{padding:8px 12px;border-radius:20px;background:rgba(37,99,235,.15);color:#93c5fd!important;font-weight:700}.inventory-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}.inventory-metrics>div,.inventory-panel{padding:18px;border:1px solid rgba(148,163,184,.14);border-radius:14px;background:linear-gradient(145deg,#101827,#0b111b)}.inventory-metrics small{display:block;color:#94a3b8}.inventory-metrics strong{display:block;color:#f8fafc;font-size:23px;margin-top:6px}.inventory-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:16px}.inventory-panel{margin-bottom:16px}.inventory-panel h2{margin:0 0 14px;color:#f8fafc;font-size:17px}.inventory-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px}.inventory-form .full{grid-column:1/-1}.inventory-form label{display:grid;gap:6px;color:#cbd5e1;font-size:12px;font-weight:700}.inventory-form input,.inventory-form select,.inventory-form textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid rgba(148,163,184,.2);border-radius:8px;background:#0b111b;color:#f8fafc}.inventory-table-wrap{overflow:auto}.inventory-table-wrap table{width:100%;min-width:760px;border-collapse:collapse}.inventory-table-wrap th,.inventory-table-wrap td{padding:11px 12px;border-bottom:1px solid rgba(148,163,184,.13);text-align:left;color:#e2e8f0}.inventory-table-wrap th{background:#0b111b;color:#94a3b8}.inventory-table-wrap .low{padding:5px 8px;border-radius:20px;background:rgba(245,158,11,.13);color:#fcd34d}.inventory-table-wrap .good{padding:5px 8px;border-radius:20px;background:rgba(34,197,94,.13);color:#86efac}.inventory-alert{padding:13px 15px;margin-bottom:14px;border-radius:9px;background:rgba(245,158,11,.13);color:#fcd34d}.inventory-alert.error{background:rgba(239,68,68,.13);color:#fca5a5}.inventory-alert.success{background:rgba(34,197,94,.13);color:#86efac}.inventory-page .btn{padding:9px 13px;border-radius:8px;border:0;font-weight:700}.inventory-page .btn-primary{background:#2563eb;color:white}.inventory-heading p{font-size:12px}@media(max-width:900px){.inventory-grid{grid-template-columns:1fr}.inventory-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.inventory-page{padding:14px}.inventory-hero{align-items:flex-start;flex-direction:column}.inventory-form{grid-template-columns:1fr}.inventory-form .full{grid-column:auto}}
</style>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
