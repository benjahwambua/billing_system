<?php
require_once __DIR__ . '/mikrotik_api.php';
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


if (!function_exists('flexihubQueueServiceActivation')) {
    function flexihubQueueServiceActivation($accountId, $tenantId, $paymentId = null, $subscriptionId = null, $action = 'activate')
    {
        global $conn;
        $accountId=(int)$accountId; $tenantId=(int)$tenantId;
        if(!$accountId || !$tenantId) return false;
        if(!flexihubTableColumns('service_activation_queue')) return false;
        $check=$conn->prepare("SELECT id FROM service_activation_queue WHERE tenant_id=? AND account_id=? AND action=? AND status IN ('pending','processing') LIMIT 1");
        if($check){$check->bind_param('iis',$tenantId,$accountId,$action);$check->execute();$existing=$check->get_result()->fetch_assoc();$check->close();if($existing)return (int)$existing['id'];}
        return flexihubWorkflowInsert('service_activation_queue',[
            'tenant_id'=>$tenantId,'service_type'=>'internet','account_id'=>$accountId,
            'payment_id'=>$paymentId,'subscription_id'=>$subscriptionId,'action'=>$action,
            'status'=>'pending','attempts'=>0,'available_at'=>date('Y-m-d H:i:s'),
            'payload'=>json_encode(['source'=>'payment'],JSON_UNESCAPED_SLASHES)
        ]);
    }
}

if (!function_exists('flexihubProcessServiceActivationQueue')) {
    function flexihubProcessServiceActivationQueue($tenantId, $limit=25)
    {
        global $conn; $tenantId=(int)$tenantId; $limit=max(1,min(100,(int)$limit));
        $stats=['processed'=>0,'completed'=>0,'failed'=>0]; if(!$tenantId)return $stats;
        $stmt=$conn->prepare("SELECT * FROM service_activation_queue WHERE tenant_id=? AND status='pending' AND (available_at IS NULL OR available_at<=NOW()) ORDER BY id ASC LIMIT {$limit}");
        if(!$stmt)return $stats;
        $stmt->bind_param('i',$tenantId);$stmt->execute();$res=$stmt->get_result();$items=[];
        while($row=$res->fetch_assoc())$items[]=$row;$stmt->close();
        foreach($items as $item){
            $id=(int)$item['id'];$stats['processed']++;
            $claim=$conn->prepare("UPDATE service_activation_queue SET status='processing',attempts=attempts+1 WHERE id=? AND tenant_id=? AND status='pending'");
            if(!$claim){$stats['failed']++;continue;}
            $claim->bind_param('ii',$id,$tenantId);$claim->execute();$claimed=$claim->affected_rows>0;$claim->close();if(!$claimed)continue;
            $ok=false;$error='';
            try{
                $accountId=(int)($item['account_id']??0);if(!$accountId)throw new Exception('Internet account is missing.');
                $q=$conn->prepare("SELECT id,status FROM internet_accounts WHERE id=? AND tenant_id=? LIMIT 1");
                if(!$q)throw new Exception('Unable to load internet account.');
                $q->bind_param('ii',$accountId,$tenantId);$q->execute();$account=$q->get_result()->fetch_assoc();$q->close();
                if(!$account)throw new Exception('Internet account not found.');
                $action=(string)($item['action']??'activate');
                $newStatus=$action==='suspend'?'suspended':($action==='expire'?'expired':'active');
                // Never advertise an activation as active before the network authorization succeeds.
                // For suspend/expire the requested state can be persisted immediately; activation
                // remains pending until RouterOS synchronization completes.
                $dbStatus=$action==='activate'?'pending_activation':$newStatus;
                $u=$conn->prepare("UPDATE internet_accounts SET status=? WHERE id=? AND tenant_id=?");
                if(!$u)throw new Exception('Unable to update internet account.');
                $u->bind_param('sii',$dbStatus,$accountId,$tenantId);$ok=$u->execute();$u->close();
                if(!$ok)throw new Exception('Unable to update internet account status.');
                if($conn->query("SHOW TABLES LIKE 'pppoe_accounts'")->num_rows){
                    $p=$conn->prepare("SELECT pa.*, ps.router_id FROM pppoe_accounts pa LEFT JOIN pppoe_servers ps ON ps.id=pa.pppoe_server_id AND ps.tenant_id=pa.tenant_id WHERE pa.internet_account_id=? AND pa.tenant_id=? LIMIT 1");
                    $pppoe=null;
                    if($p){$p->bind_param('ii',$accountId,$tenantId);$p->execute();$pppoe=$p->get_result()->fetch_assoc();$p->close();}
                    if($pppoe){
                        // Keep PPPoE state pending during activation until RouterOS confirms it.
                        $pppoeDbStatus=$action==='activate'?'pending_activation':$newStatus;
                        $psql=$conn->prepare("UPDATE pppoe_accounts SET status=? WHERE id=? AND tenant_id=?");
                        if($psql){$psql->bind_param('sii',$pppoeDbStatus,$pppoe['id'],$tenantId);$psql->execute();$psql->close();}
                        $routerId=(int)($pppoe['router_id']??0); $username=trim((string)($pppoe['username']??''));
                        if($routerId && $username && $conn->query("SHOW TABLES LIKE 'router_service_authorizations'")->num_rows){
                            $router=null;
                            $rq=$conn->prepare("SELECT * FROM mikrotik_routers WHERE id=? AND tenant_id=? LIMIT 1");
                            if($rq){$rq->bind_param('ii',$routerId,$tenantId);$rq->execute();$router=$rq->get_result()->fetch_assoc();$rq->close();}
                            if(!$router) throw new Exception('Assigned MikroTik router was not found.');
                            $routerCols=flexihubTableColumns('mikrotik_routers');
                            $host=''; foreach(['host','ip_address','ip'] as $hc) if(in_array($hc,$routerCols,true) && !empty($router[$hc])){$host=$router[$hc];break;}
                            $ruser=''; foreach(['username','user'] as $uc) if(in_array($uc,$routerCols,true) && isset($router[$uc])){$ruser=$router[$uc];break;}
                            $rpass=''; foreach(['password','api_password'] as $pc) if(in_array($pc,$routerCols,true) && isset($router[$pc])){$rpass=$router[$pc];break;}
                            $rport=8728; foreach(['api_port','port'] as $pc) if(in_array($pc,$routerCols,true) && !empty($router[$pc])){$rport=(int)$router[$pc];break;}
                            if($host==='' || $ruser==='') throw new Exception('Router connection details are incomplete.');
                            $ros=new FlexihubRouterOS($host,$ruser,$rpass,$rport,8);
                            $secret=$ros->findPppSecret($username);
                            if(!$secret) throw new Exception('PPPoE username was not found on the assigned MikroTik router.');
                            $disable=($newStatus!=='active');
                            $ros->setPppSecretDisabled($secret['.id'],$disable);
                            if($disable) $ros->disconnectPppActive($username);
                            $ros->close();
                            $authId=flexihubWorkflowInsert('router_service_authorizations',[
                                'tenant_id'=>$tenantId,'pppoe_account_id'=>$pppoe['id'],'router_id'=>$routerId,
                                'service_type'=>'pppoe','external_username'=>$username,'desired_status'=>$newStatus,
                                'applied_status'=>$newStatus,'last_synced_at'=>date('Y-m-d H:i:s'),'last_error'=>null
                            ]);
                            if(!$authId){
                                $upd=$conn->prepare("UPDATE router_service_authorizations SET desired_status=?,applied_status=?,last_synced_at=NOW(),last_error=NULL WHERE tenant_id=? AND pppoe_account_id=?");
                                if($upd){$upd->bind_param('ssii',$newStatus,$newStatus,$tenantId,$pppoe['id']);$upd->execute();$upd->close();}
                            }
                            flexihubWorkflowInsert('router_sync_logs',[
                                'tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'pppoe',
                                'external_username'=>$username,'action'=>$action,'status'=>'success',
                                'message'=>'RouterOS PPPoE authorization synchronized.'
                            ]);
                            if($action==='activate'){
                                $activate=$conn->prepare("UPDATE internet_accounts SET status='active' WHERE id=? AND tenant_id=?");
                                if(!$activate) throw new Exception('Unable to finalize internet account activation.');
                                $activate->bind_param('ii',$accountId,$tenantId);
                                if(!$activate->execute()) throw new Exception('Unable to finalize internet account activation.');
                                $activate->close();
                                $pppoeActivate=$conn->prepare("UPDATE pppoe_accounts SET status='active' WHERE id=? AND tenant_id=?");
                                if(!$pppoeActivate) throw new Exception('Unable to finalize PPPoE activation.');
                                $pppoeActivate->bind_param('ii',$pppoe['id'],$tenantId);
                                if(!$pppoeActivate->execute()) throw new Exception('Unable to finalize PPPoE activation.');
                                $pppoeActivate->close();
                            }
                        } elseif($routerId || $username) {
                            throw new Exception('PPPoE account is missing its router assignment or username.');
                        }
                    }
                }
                if($ok&&$action==='activate'){
                    // Non-PPPoE accounts have no RouterOS authorization step. Finalize them here.
                    if($action==='activate'){
                        $checkP=$conn->prepare("SELECT id FROM pppoe_accounts WHERE internet_account_id=? AND tenant_id=? LIMIT 1");
                        $hasP=false;
                        if($checkP){$checkP->bind_param('ii',$accountId,$tenantId);$checkP->execute();$hasP=(bool)$checkP->get_result()->fetch_assoc();$checkP->close();}
                        if(!$hasP){
                            $activate=$conn->prepare("UPDATE internet_accounts SET status='active' WHERE id=? AND tenant_id=?");
                            if($activate){$activate->bind_param('ii',$accountId,$tenantId);$ok=$activate->execute();$activate->close();}
                        }
                    }
                    flexihubWorkflowInsert('service_events',[
                        'tenant_id'=>$tenantId,'account_id'=>$accountId,'subscription_id'=>$item['subscription_id']??null,
                        'event_type'=>'service_activated','old_status'=>$account['status']??null,'new_status'=>'active',
                        'source'=>'queue','reference'=>'QUEUE-'.$id,
                        'details'=>json_encode(['payment_id'=>$item['payment_id']??null],JSON_UNESCAPED_SLASHES)
                    ]);
                }
            }catch(Throwable $e){$error=$e->getMessage();}
            if($ok){
                $done=$conn->prepare("UPDATE service_activation_queue SET status='completed',processed_at=NOW(),last_error=NULL WHERE id=? AND tenant_id=?");
                if($done){$done->bind_param('ii',$id,$tenantId);$done->execute();$done->close();}$stats['completed']++;
            }else{
                $retry=$conn->prepare("UPDATE service_activation_queue SET status=IF(attempts>=5,'failed','pending'),available_at=DATE_ADD(NOW(),INTERVAL LEAST(attempts*5,60) MINUTE),last_error=? WHERE id=? AND tenant_id=?");
                if($retry){$retry->bind_param('sii',$error,$id,$tenantId);$retry->execute();$retry->close();}$stats['failed']++;
            }
        }
        return $stats;
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


if (!function_exists('flexihubCreateServiceSubscription')) {
    function flexihubCreateServiceSubscription($accountId, $tenantId, $invoiceId = null, $paymentId = null)
    {
        global $conn;
        $accountId=(int)$accountId; $tenantId=(int)$tenantId;
        if(!$accountId || !$tenantId) return false;
        $cols=flexihubTableColumns('service_subscriptions');
        if(!$cols) return false;

        $sql="SELECT ia.id,ia.customer_id,ia.plan_id,ia.activation_date,ia.expiry_date,ip.billing_days,ip.billing_cycle
              FROM internet_accounts ia
              LEFT JOIN internet_plans ip ON ip.id=ia.plan_id AND ip.tenant_id=ia.tenant_id
              WHERE ia.id=? AND ia.tenant_id=? LIMIT 1";
        $stmt=$conn->prepare($sql); if(!$stmt) return false;
        $stmt->bind_param('ii',$accountId,$tenantId); $stmt->execute();
        $account=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if(!$account) return false;
        if($paymentId && in_array('payment_id',$cols,true)){
            $check=$conn->prepare("SELECT id FROM service_subscriptions WHERE tenant_id=? AND payment_id=? LIMIT 1");
            if($check){$check->bind_param('ii',$tenantId,$paymentId);$check->execute();$existing=$check->get_result()->fetch_assoc();$check->close();if($existing)return (int)$existing['id'];}
        }

        $end=$account['expiry_date']??date('Y-m-d');
        $start=$account['activation_date']??date('Y-m-d');
        if(!$start || strtotime($start)===false) $start=date('Y-m-d');
        if(!$end || strtotime($end)===false) $end=$start;

        $data=[
            'tenant_id'=>$tenantId,'account_id'=>$accountId,'customer_id'=>(int)$account['customer_id'],
            'plan_id'=>(int)$account['plan_id'],'invoice_id'=>$invoiceId,'payment_id'=>$paymentId,
            'start_date'=>$start,'end_date'=>$end,'status'=>'active'
        ];
        return flexihubWorkflowInsert('service_subscriptions',$data);
    }
}
