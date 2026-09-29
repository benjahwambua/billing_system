<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('invoices','view');
require_once '../includes/billing_workflow.php';

$tenantId=(int)getCurrentTenantId();
$rows=[];
$today=date('Y-m-d');

$stmt=$conn->prepare(
    "SELECT i.*, c.customer_number, c.first_name, c.last_name
     FROM invoices i
     LEFT JOIN customers c
       ON c.id=i.customer_id AND c.tenant_id=i.tenant_id
     WHERE i.tenant_id=?
       AND i.status NOT IN ('paid','cancelled')
     ORDER BY i.due_date ASC, i.id ASC
     LIMIT 500"
);
if($stmt){
    $stmt->bind_param('i',$tenantId);
    if($stmt->execute()){
        $result=$stmt->get_result();
        while($invoice=$result->fetch_assoc()){
            $total=flexihubInvoiceTotal($invoice);
            $paid=flexihubInvoicePaid((int)$invoice['id'],$tenantId);
            $balance=max(0,$total-$paid);
            if($balance<=0) continue;

            $invoice['calculated_total']=$total;
            $invoice['calculated_paid']=$paid;
            $invoice['calculated_balance']=$balance;
            $invoice['display_status']=!empty($invoice['due_date']) && $invoice['due_date']<$today
                ? 'overdue'
                : ($paid>0 ? 'partial' : 'unpaid');

            $rows[]=$invoice;
        }
    }
    $stmt->close();
}

$pageTitle='Outstanding';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div class="page-header">
        <div>
            <h2>Outstanding / Debtors</h2>
            <p>Open invoice balances calculated from the payment ledger.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Due Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $row):
                $customerName=trim(($row['first_name']??'').' '.($row['last_name']??''));
                ?>
                <tr>
                    <td><?=e($row['invoice_number']??$row['number']??$row['id'])?></td>
                    <td>
                        <?=e($row['customer_number']??$row['customer_id']??'')?>
                        <?php if($customerName): ?><br><small><?=e($customerName)?></small><?php endif; ?>
                    </td>
                    <td><?=e($row['due_date']??'')?></td>
                    <td><?=e(formatMoney($row['calculated_total']))?></td>
                    <td><?=e(formatMoney($row['calculated_paid']))?></td>
                    <td><?=e(formatMoney($row['calculated_balance']))?></td>
                    <td><?=e(ucfirst($row['display_status']))?></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="7">No outstanding invoices.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>