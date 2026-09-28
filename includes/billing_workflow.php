<?php
/**
 * Flexihub billing workflow helpers.
 * These helpers adapt to the existing legacy billing schema while enforcing tenant scope.
 */

if (!function_exists('flexihubTableColumns')) {
    function flexihubTableColumns($table)
    {
        global $conn;
        $columns = [];
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if (!$safe || !($conn instanceof mysqli)) return $columns;
        $result = $conn->query("SHOW COLUMNS FROM `{$safe}`");
        if ($result) {
            while ($row = $result->fetch_assoc()) $columns[] = $row['Field'];
        }
        return $columns;
    }
}

if (!function_exists('flexihubWorkflowBindType')) {
    function flexihubWorkflowBindType($table, $field)
    {
        global $conn;
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$field);
        if (!$safeTable || !$safeField) return 's';
        $stmt = $conn->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE ?");
        if (!$stmt) return 's';
        $stmt->bind_param('s', $safeField);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $type = strtolower($row['Type'] ?? '');
        if (preg_match('/int|bigint|smallint|tinyint|mediumint/', $type)) return 'i';
        if (preg_match('/decimal|float|double|real/', $type)) return 'd';
        return 's';
    }
}

if (!function_exists('flexihubWorkflowInsert')) {
    function flexihubWorkflowInsert($table, array $data)
    {
        global $conn;
        $columns = flexihubTableColumns($table);
        if (!$columns || !$data) return false;
        $insert = [];
        foreach ($data as $field => $value) {
            if (in_array($field, $columns, true)) $insert[$field] = $value;
        }
        if (!$insert) return false;

        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $fields = array_keys($insert);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $types = '';
        $values = [];
        foreach ($fields as $field) {
            $types .= flexihubWorkflowBindType($safeTable, $field);
            $values[] = $insert[$field];
        }

        $stmt = $conn->prepare("INSERT INTO `{$safeTable}` (" . implode(',', $fields) . ") VALUES ({$placeholders})");
        if (!$stmt) return false;
        $bind = [$types];
        foreach ($values as $key => $value) $bind[] = &$values[$key];
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $ok = $stmt->execute();
        $id = $ok ? $conn->insert_id : false;
        $stmt->close();
        return $ok ? $id : false;
    }
}

if (!function_exists('flexihubInvoiceTotal')) {
    function flexihubInvoiceTotal(array $invoice)
    {
        foreach (['total_amount','amount','total','grand_total'] as $field) {
            if (isset($invoice[$field]) && is_numeric($invoice[$field])) return (float)$invoice[$field];
        }
        return 0.0;
    }
}

if (!function_exists('flexihubInvoicePaid')) {
    function flexihubInvoicePaid($invoiceId, $tenantId)
    {
        global $conn;
        $cols = flexihubTableColumns('payments');
        if (!in_array('amount', $cols, true)) return 0.0;
        $sql = "SELECT COALESCE(SUM(amount),0) AS paid FROM payments WHERE invoice_id=? AND tenant_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0.0;
        $stmt->bind_param('ii', $invoiceId, $tenantId);
        $stmt->execute();
        $paid = (float)($stmt->get_result()->fetch_assoc()['paid'] ?? 0);
        $stmt->close();
        return $paid;
    }
}

if (!function_exists('flexihubRefreshInvoiceStatus')) {
    function flexihubRefreshInvoiceStatus($invoiceId, $tenantId)
    {
        global $conn;
        $cols = flexihubTableColumns('invoices');
        $stmt = $conn->prepare("SELECT * FROM invoices WHERE id=? AND tenant_id=? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('ii', $invoiceId, $tenantId);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$invoice) return false;

        $total = flexihubInvoiceTotal($invoice);
        $paid = flexihubInvoicePaid($invoiceId, $tenantId);
        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.00001 >= $total ? 'paid' : 'partial');

        $sets = [];
        $values = [];
        $types = '';
        if (in_array('paid_amount', $cols, true)) { $sets[]='paid_amount=?'; $values[]=$paid; $types.='d'; }
        if (in_array('balance', $cols, true)) { $sets[]='balance=?'; $values[]=max(0,$total-$paid); $types.='d'; }
        if (in_array('status', $cols, true)) { $sets[]='status=?'; $values[]=$status; $types.='s'; }
        if (!$sets) return true;

        $sql = "UPDATE invoices SET ".implode(',', $sets)." WHERE id=? AND tenant_id=?";
        $values[]=$invoiceId; $values[]=$tenantId; $types.='ii';
        $stmt=$conn->prepare($sql);
        if (!$stmt) return false;
        $bind=[$types];
        foreach($values as $k=>$v)$bind[]=&$values[$k];
        call_user_func_array([$stmt,'bind_param'],$bind);
        $ok=$stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('flexihubCreatePaymentArtifacts')) {
    function flexihubCreatePaymentArtifacts($paymentId, array $payment, array $invoice, $tenantId)
    {
        global $conn;
        $receiptCols = flexihubTableColumns('receipts');
        $receiptId = false;

        if (in_array('payment_id', $receiptCols, true)) {
            $stmt=$conn->prepare("SELECT id FROM receipts WHERE payment_id=? AND tenant_id=? LIMIT 1");
            if($stmt){$stmt->bind_param('ii',$paymentId,$tenantId);$stmt->execute();$exists=$stmt->get_result()->fetch_assoc();$stmt->close();if($exists)$receiptId=(int)$exists['id'];}
        }

        if (!$receiptId && $receiptCols) {
            $receiptData=[
                'tenant_id'=>$tenantId,
                'payment_id'=>$paymentId,
                'receipt_number'=>'RCT-'.date('YmdHis').'-'.random_int(100,999),
                'invoice_id'=>$payment['invoice_id']??$invoice['id']??null,
                'customer_id'=>$invoice['customer_id']??null,
                'amount'=>$payment['amount']??0,
                'payment_method'=>$payment['payment_method']??'',
                'receipt_date'=>$payment['payment_date']??date('Y-m-d'),
                'reference'=>$payment['reference']??''
            ];
            $receiptId=flexihubWorkflowInsert('receipts',$receiptData);
        }

        $txCols=flexihubTableColumns('account_transactions');
        if ($txCols) {
            $duplicate=false;
            if(in_array('payment_id',$txCols,true)){
                $stmt=$conn->prepare("SELECT id FROM account_transactions WHERE payment_id=? AND tenant_id=? LIMIT 1");
                if($stmt){$stmt->bind_param('ii',$paymentId,$tenantId);$stmt->execute();$duplicate=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();}
            }
            if(!$duplicate){
                flexihubWorkflowInsert('account_transactions',[
                    'tenant_id'=>$tenantId,'payment_id'=>$paymentId,'invoice_id'=>$payment['invoice_id']??null,
                    'customer_id'=>$invoice['customer_id']??null,'transaction_type'=>'payment','type'=>'payment',
                    'amount'=>$payment['amount']??0,'description'=>'Customer payment',
                    'reference'=>$payment['reference']??('PAY-'.$paymentId),
                    'transaction_date'=>$payment['payment_date']??date('Y-m-d')
                ]);
            }
        }
        return $receiptId;
    }
}

if (!function_exists('flexihubRenewInternetAccount')) {
    function flexihubRenewInternetAccount($accountId, $tenantId)
    {
        global $conn;
        if (!$accountId) return false;
        $acctCols=flexihubTableColumns('internet_accounts');
        if (!$acctCols) return false;
        $sql="SELECT ia.*, ip.billing_days, ip.billing_cycle FROM internet_accounts ia LEFT JOIN internet_plans ip ON ip.id=ia.plan_id AND ip.tenant_id=ia.tenant_id WHERE ia.id=? AND ia.tenant_id=? LIMIT 1";
        $stmt=$conn->prepare($sql);
        if(!$stmt)return false;
        $stmt->bind_param('ii',$accountId,$tenantId);$stmt->execute();$account=$stmt->get_result()->fetch_assoc();$stmt->close();
        if(!$account)return false;

        $days=(int)($account['billing_days']??0);
        if($days<1){
            $days=['daily'=>1,'weekly'=>7,'monthly'=>30,'quarterly'=>90,'yearly'=>365][strtolower((string)($account['billing_cycle']??'monthly'))]??30;
        }
        $base=$account['expiry_date']??'';
        $baseTime=$base?strtotime($base):false;
        if(!$baseTime || $baseTime<strtotime(date('Y-m-d'))) $baseTime=strtotime(date('Y-m-d'));
        $expiry=date('Y-m-d',strtotime("+{$days} days",$baseTime));

        $set=[];$vals=[];$types='';
        if(in_array('expiry_date',$acctCols,true)){$set[]='expiry_date=?';$vals[]=$expiry;$types.='s';}
        if(in_array('activation_date',$acctCols,true)&&empty($account['activation_date'])){$set[]='activation_date=?';$vals[]=date('Y-m-d');$types.='s';}
        if(in_array('status',$acctCols,true)){$set[]='status=?';$vals[]='active';$types.='s';}
        if(!$set)return false;
        $vals[]=$accountId;$vals[]=$tenantId;$types.='ii';
        $stmt=$conn->prepare("UPDATE internet_accounts SET ".implode(',',$set)." WHERE id=? AND tenant_id=?");
        if(!$stmt)return false;
        $bind=[$types];foreach($vals as $k=>$v)$bind[]=&$vals[$k];call_user_func_array([$stmt,'bind_param'],$bind);
        $ok=$stmt->execute();$stmt->close();return $ok;
    }
}
