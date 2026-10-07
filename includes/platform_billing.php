<?php
require_once __DIR__ . '/functions.php';

if (!function_exists('flexihubPlatformBillingEnabled')) {
    function flexihubPlatformBillingEnabled() {
        return filter_var(getPlatformSetting('saas_billing_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('flexihubPlatformAddCycle')) {
    function flexihubPlatformAddCycle($date, $cycle) {
        $date = new DateTime($date ?: date('Y-m-d'));
        if ($cycle === 'yearly') $date->modify('+1 year');
        elseif ($cycle === 'quarterly') $date->modify('+3 months');
        else $date->modify('+1 month');
        return $date->format('Y-m-d');
    }
}

if (!function_exists('flexihubPlatformInvoiceNumber')) {
    function flexihubPlatformInvoiceNumber($tenantId) {
        return 'FLEX-' . date('Ym') . '-' . str_pad((string)$tenantId, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

if (!function_exists('flexihubEnsurePlatformSubscription')) {
    function flexihubEnsurePlatformSubscription($tenantId, $planId = null) {
        global $conn;
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) return false;

        $existing = dbFetchOne("SELECT * FROM tenant_platform_subscriptions WHERE tenant_id=? LIMIT 1", 'i', $tenantId);
        if ($existing) return (int)$existing['id'];

        if ($planId === null) return false;
        $plan = dbFetchOne("SELECT * FROM platform_plans WHERE id=? AND status='active' LIMIT 1", 'i', (int)$planId);
        if (!$plan) return false;

        $tenant = dbFetchOne("SELECT status FROM tenants WHERE id=? LIMIT 1", 'i', $tenantId);
        if (!$tenant) return false;

        $now = date('Y-m-d H:i:s');
        $trialDays = (int)$plan['trial_days'];
        $trialEnds = $trialDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$trialDays} days")) : null;
        $status = $trialDays > 0 ? 'trial' : 'active';
        $start = date('Y-m-d');
        $periodEnd = $trialDays > 0 ? null : flexihubPlatformAddCycle($start, $plan['billing_cycle']);

        $stmt = $conn->prepare("INSERT INTO tenant_platform_subscriptions (tenant_id,plan_id,status,started_at,trial_ends_at,current_period_start,current_period_end,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)");
        if (!$stmt) return false;
        $stmt->bind_param('iisssssss', $tenantId, $plan['id'], $status, $now, $trialEnds, $start, $periodEnd, $now, $now);
        $ok = $stmt->execute();
        $id = $ok ? $conn->insert_id : false;
        $stmt->close();
        return $id ?: false;
    }
}

if (!function_exists('flexihubGeneratePlatformInvoice')) {
    function flexihubGeneratePlatformInvoice($subscriptionId) {
        global $conn;
        $sub = dbFetchOne("SELECT s.*,p.name plan_name,p.price,p.billing_cycle,p.grace_days FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id WHERE s.id=? LIMIT 1", 'i', (int)$subscriptionId);
        if (!$sub || !in_array($sub['status'], ['active','past_due'], true)) return false;

        $periodStart = $sub['current_period_start'] ?: date('Y-m-d');
        $periodEnd = $sub['current_period_end'] ?: flexihubPlatformAddCycle($periodStart, $sub['billing_cycle']);
        $existing = dbFetchOne("SELECT id FROM platform_invoices WHERE subscription_id=? AND period_start=? AND period_end=? LIMIT 1", 'iss', (int)$subscriptionId, $periodStart, $periodEnd);
        if ($existing) return (int)$existing['id'];

        $issue = date('Y-m-d');
        // Grace is applied by the billing processor from the invoice due date.
        // Keep the invoice due date at issue date so grace is never double-counted.
        $due = $issue;
        $number = flexihubPlatformInvoiceNumber((int)$sub['tenant_id']);
        $stmt = $conn->prepare("INSERT INTO platform_invoices (tenant_id,subscription_id,invoice_number,period_start,period_end,issue_date,due_date,subtotal,total_amount,status) VALUES (?,?,?,?,?,?,?,?,?,'unpaid')");
        if (!$stmt) return false;
        $amount=(float)$sub['price'];
        $tenantId=(int)$sub['tenant_id']; $subscriptionIdValue=(int)$subscriptionId; $stmt->bind_param('iisssssdd',$tenantId,$subscriptionIdValue,$number,$periodStart,$periodEnd,$issue,$due,$amount,$amount);
        $ok=$stmt->execute(); $id=$ok?$conn->insert_id:false; $stmt->close();
        if ($id) {
            $conn->query("UPDATE tenant_platform_subscriptions SET last_invoice_id=".(int)$id.", updated_at=NOW() WHERE id=".(int)$subscriptionId);
        }
        return $id ?: false;
    }
}

if (!function_exists('flexihubRecordPlatformPayment')) {
    function flexihubRecordPlatformPayment($tenantId, $invoiceId, $amount, $method='manual', $reference=null, $provider=null, $externalId=null) {
        global $conn;
        $tenantId=(int)$tenantId; $invoiceId=(int)$invoiceId; $amount=(float)$amount;
        if($tenantId<=0 || $invoiceId<=0 || $amount<=0) return ['ok'=>false,'message'=>'Invalid payment details.'];
        $conn->begin_transaction();
        try {
            $invoice=dbFetchOne("SELECT * FROM platform_invoices WHERE id=? AND tenant_id=? FOR UPDATE",'ii',$invoiceId,$tenantId);
            if(!$invoice) throw new Exception('SaaS invoice not found for this tenant.');
            if(in_array((string)$invoice['status'], ['cancelled','draft'], true)) {
                throw new Exception('This SaaS invoice is not payable in its current status.');
            }
            $balance=max(0,(float)$invoice['total_amount']-(float)$invoice['paid_amount']);
            if($balance<=0.0001) throw new Exception('This SaaS invoice is already fully paid.');
            if($amount>$balance+0.0001) throw new Exception('Payment exceeds the outstanding SaaS invoice balance.');

            if($provider && $externalId){
                $duplicate=dbFetchOne("SELECT id FROM platform_payments WHERE provider=? AND external_transaction_id=? LIMIT 1",'ss',$provider,$externalId);
                if($duplicate) throw new Exception('This provider transaction has already been recorded.');
            }

            $stmt=$conn->prepare("INSERT INTO platform_payments (tenant_id,invoice_id,amount,payment_method,reference,provider,external_transaction_id,status) VALUES (?,?,?,?,?,?,?,'completed')");
            if(!$stmt) throw new Exception('Unable to prepare SaaS payment.');
            $stmt->bind_param('iidssss',$tenantId,$invoiceId,$amount,$method,$reference,$provider,$externalId);
            if(!$stmt->execute()) { $stmt->close(); throw new Exception('Unable to record SaaS payment.'); }
            $paymentId=$conn->insert_id; $stmt->close();

            $newPaid=(float)$invoice['paid_amount']+$amount;
            $newStatus=$newPaid+0.0001 >= (float)$invoice['total_amount'] ? 'paid' : 'partial';
            $stmt=$conn->prepare("UPDATE platform_invoices SET paid_amount=?,status=?,updated_at=NOW() WHERE id=?");
            if(!$stmt) throw new Exception('Unable to update SaaS invoice.');
            $stmt->bind_param('dsi',$newPaid,$newStatus,$invoiceId);
            if(!$stmt->execute()) { $stmt->close(); throw new Exception('Unable to update SaaS invoice.'); }
            $stmt->close();

            if($newStatus==='paid'){
            // SaaS payment restores the subscription only; tenant internet
            // service provisioning is handled by the tenant's own billing/runtime.

                $sub=dbFetchOne("SELECT s.*,p.billing_cycle FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id WHERE s.id=? FOR UPDATE",'i',(int)$invoice['subscription_id']);
                if($sub){
                    $nextStart=$invoice['period_end'];
                    $nextEnd=flexihubPlatformAddCycle($nextStart,$sub['billing_cycle']);
                    $stmt=$conn->prepare("UPDATE tenant_platform_subscriptions SET status='active',current_period_start=?,current_period_end=?,grace_ends_at=NULL,updated_at=NOW() WHERE id=?");
                    if($stmt){$stmt->bind_param('ssi',$nextStart,$nextEnd,$sub['id']);$stmt->execute();$stmt->close();}
                    $conn->query("UPDATE tenants SET status='active' WHERE id=".$tenantId." AND status IN ('past_due','suspended','trial')");
                }
            }

            $conn->commit();
            return ['ok'=>true,'payment_id'=>(int)$paymentId,'invoice_id'=>$invoiceId,'status'=>$newStatus];
        } catch(Throwable $e) {
            $conn->rollback();
            return ['ok'=>false,'message'=>$e->getMessage()];
        }
    }
}

if (!function_exists('flexihubProcessPlatformBilling')) {
    function flexihubProcessPlatformBilling() {
        global $conn;
        if (!flexihubPlatformBillingEnabled()) return ['enabled'=>false,'created'=>0,'past_due'=>0,'suspended'=>0,'unassigned'=>0,'expired_payments'=>0,'errors'=>0];

        $created=0;$pastDue=0;$suspended=0;$unassigned=0;$expiredPayments=0;$errors=0;
        // Expire abandoned platform M-Pesa requests so they can be retried cleanly.
        $expiredPayments=(int)$conn->query("UPDATE platform_payments SET status='failed',failure_reason='M-Pesa payment request expired' WHERE provider='mpesa' AND status='pending' AND expires_at IS NOT NULL AND expires_at<=NOW()")->affected_rows;
        $tenants=dbFetchAll("SELECT id FROM tenants WHERE LOWER(COALESCE(status,'')) NOT IN ('deleted','archived')");
        foreach($tenants as $tenant){
            $tenantId=(int)$tenant['id'];
            try {
                $subId=flexihubEnsurePlatformSubscription($tenantId);
                if(!$subId){
                    $hasSub=dbFetchOne("SELECT id FROM tenant_platform_subscriptions WHERE tenant_id=? LIMIT 1",'i',$tenantId);
                    if(!$hasSub){$unassigned++;continue;}
                    $errors++;continue;
                }
                $sub=dbFetchOne("SELECT s.*,p.grace_days,p.billing_cycle FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id WHERE s.id=? LIMIT 1",'i',$subId);
                if(!$sub) {$errors++;continue;}

                if($sub['status']==='trial' && !empty($sub['trial_ends_at']) && strtotime($sub['trial_ends_at'])<=time()){
                    $start=date('Y-m-d');
                    $end=flexihubPlatformAddCycle($start, $sub['billing_cycle']);
                    $conn->query("UPDATE tenant_platform_subscriptions SET status='active',current_period_start='".$conn->real_escape_string($start)."',current_period_end='".$conn->real_escape_string($end)."',updated_at=NOW() WHERE id=".$subId);
                    $sub['status']='active'; $sub['current_period_start']=$start; $sub['current_period_end']=$end;
                }

                $beforeInvoice=dbFetchOne("SELECT id FROM platform_invoices WHERE subscription_id=? AND period_start=? AND period_end=? LIMIT 1",'iss',$subId,$sub['current_period_start'],$sub['current_period_end']);
                $invoice=flexihubGeneratePlatformInvoice($subId);
                if($invoice && !$beforeInvoice) $created++;

                $open=dbFetchOne("SELECT id,due_date,status,total_amount,paid_amount FROM platform_invoices WHERE subscription_id=? AND status IN ('unpaid','partial','overdue') ORDER BY due_date DESC,id DESC LIMIT 1",'i',$subId);
                if($open){
                    $balance=max(0,(float)$open['total_amount']-(float)$open['paid_amount']);
                    if($balance<=0.0001){
                        $conn->query("UPDATE platform_invoices SET status='paid',updated_at=NOW() WHERE id=".(int)$open['id']);
                        // Zero-value SaaS plans must still roll their billing period forward.
                        if((float)$open['total_amount']<=0.0001){
                            $period=dbFetchOne("SELECT period_end FROM platform_invoices WHERE id=? LIMIT 1",'i',(int)$open['id']);
                            if($period && !empty($period['period_end'])){
                                $nextEnd=flexihubPlatformAddCycle($period['period_end'],$sub['billing_cycle']);
                                $conn->query("UPDATE tenant_platform_subscriptions SET status='active',current_period_start='".$conn->real_escape_string($period['period_end'])."',current_period_end='".$conn->real_escape_string($nextEnd)."',grace_ends_at=NULL,updated_at=NOW() WHERE id=".(int)$subId);
                            }
                        }
                    } elseif(strtotime($open['due_date'])<strtotime(date('Y-m-d'))){
                        $conn->query("UPDATE platform_invoices SET status='overdue',updated_at=NOW() WHERE id=".(int)$open['id']);
                        $pastDue++;
                        $graceEnd=date('Y-m-d H:i:s',strtotime('+'.max(0,(int)$sub['grace_days']).' days',strtotime($open['due_date'])));
                        if(time()>strtotime($graceEnd)){
                            $conn->query("UPDATE tenant_platform_subscriptions SET status='suspended',grace_ends_at='".$conn->real_escape_string($graceEnd)."',updated_at=NOW() WHERE id=".(int)$subId);
                            $conn->query("UPDATE tenants SET status='suspended' WHERE id=".$tenantId);
                            $suspended++;
                        } else {
                            $conn->query("UPDATE tenant_platform_subscriptions SET status='past_due',grace_ends_at='".$conn->real_escape_string($graceEnd)."',updated_at=NOW() WHERE id=".(int)$subId);
                            $conn->query("UPDATE tenants SET status='past_due' WHERE id=".$tenantId);
                        }
                    }
                }
            } catch(Throwable $e) {$errors++;}
        }
        return ['enabled'=>true,'created'=>$created,'past_due'=>$pastDue,'suspended'=>$suspended,'unassigned'=>$unassigned,'expired_payments'=>$expiredPayments,'errors'=>$errors];
    }
}
