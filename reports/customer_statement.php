<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
require_once '../includes/billing_workflow.php';

$tenantId=(int)getCurrentTenantId();$customerId=(int)($_GET['customer_id']??0);
$customer=null;$entries=[];$running=0;$totals=['invoiced'=>0,'paid'=>0,'balance'=>0];
if($customerId>0){
    $q=$conn->prepare("SELECT * FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
    if($q){$q->bind_param('ii',$customerId,$tenantId);$q->execute();$customer=$q->get_result()->fetch_assoc();$q->close();}
    if($customer){
        $q=$conn->prepare("SELECT i.id,i.invoice_number,i.invoice_date,i.due_date,
                                  COALESCE(i.total_amount,i.total,i.amount,0) total_amount,
                                  COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id AND p.tenant_id=i.tenant_id),0) paid_amount
                           FROM invoices i WHERE i.customer_id=? AND i.tenant_id=? ORDER BY i.invoice_date ASC,i.id ASC");
        if($q){$q->bind_param('ii',$customerId,$tenantId);$q->execute();$res=$q->get_result();
            while($i=$res->fetch_assoc()){
                $total=(float)$i['total_amount'];$paid=(float)$i['paid_amount'];$balance=max(0,$total-$paid);
                $entries[]=['date'=>$i['invoice_date']??date('Y-m-d'),'type'=>'Invoice','reference'=>$i['invoice_number']??$i['id'],'debit'=>$total,'credit'=>0,'balance_delta'=>$total];
                if($paid>0)$entries[]=['date'=>$i['invoice_date']??date('Y-m-d'),'type'=>'Payment','reference'=>'Payments for '.$i['invoice_number'],'debit'=>0,'credit'=>$paid,'balance_delta'=>-$paid];
                $totals['invoiced']+=$total;$totals['paid']+=$paid;
            }
            $q->close();
        }
        usort($entries,function($a,$b){return strcmp($a['date'],$b['date']);});
        foreach($entries as &$e){$running+=$e['balance_delta'];$e['running']=$running;}unset($e);
        $totals['balance']=max(0,$totals['invoiced']-$totals['paid']);
    }
}
$customers=[];$q=$conn->prepare("SELECT id,customer_number,first_name,last_name FROM customers WHERE tenant_id=? ORDER BY first_name,last_name");
if($q){$q->bind_param('i',$tenantId);$q->execute();$res=$q->get_result();while($r=$res->fetch_assoc())$customers[]=$r;$q->close();}
$pageTitle='Customer Statement';require_once '../includes/header.php';require_once '../includes/sidebar.php';
?>
<div class="main-content"><div class="page-header"><div><h1>Customer Statement</h1><p>Invoice and payment ledger for one customer.</p></div></div>
<div class="dashboard-card"><form method="get" class="form-grid"><label>Customer
<select name="customer_id" required><option value="">Select customer</option><?php foreach($customers as $c):?><option value="<?=$c['id']?>" <?=$customerId===$c['id']?'selected':''?>><?=e($c['customer_number'].' — '.trim($c['first_name'].' '.$c['last_name']))?></option><?php endforeach;?></select></label>
<div style="display:flex;align-items:end"><button class="btn btn-primary">View Statement</button></div></form></div>
<?php if($customer):$name=trim(($customer['first_name']??'').' '.($customer['last_name']??''));?>
<div class="summary" style="margin-top:20px"><div class="card identity"><small>CUSTOMER</small><h2><?=e($name?:$customer['customer_number'])?></h2><p><?=e($customer['customer_number']??'')?> · <?=e($customer['phone']??'')?></p></div>
<div class="stat"><small>INVOICED</small><strong><?=e(formatMoney($totals['invoiced']))?></strong></div>
<div class="stat"><small>PAID</small><strong><?=e(formatMoney($totals['paid']))?></strong></div>
<div class="stat danger"><small>BALANCE</small><strong><?=e(formatMoney($totals['balance']))?></strong></div></div>
<div class="dashboard-card" style="margin-top:20px"><div class="table-responsive"><table><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead><tbody>
<?php foreach($entries as $e):?><tr><td><?=e($e['date'])?></td><td><?=e($e['type'])?></td><td><?=e($e['reference'])?></td><td><?=e($e['debit']?formatMoney($e['debit']):'—')?></td><td><?=e($e['credit']?formatMoney($e['credit']):'—')?></td><td><b><?=e(formatMoney(max(0,$e['running'])))?></b></td></tr><?php endforeach;?>
<?php if(!$entries):?><tr><td colspan="6" class="empty">No financial activity for this customer.</td></tr><?php endif;?>
</tbody></table></div></div>
<?php endif;?></div>
<?php require_once '../includes/footer.php'; ?>