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

        if ($planId === null) {
            $plan = dbFetchOne("SELECT * FROM platform_plans WHERE status='active' ORDER BY price ASC,id ASC LIMIT 1");
        } else {
            $plan = dbFetchOne("SELECT * FROM platform_plans WHERE id=? AND status='active' LIMIT 1", 'i', (int)$planId);
        }
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
        $due = date('Y-m-d', strtotime('+' . max(0,(int)$sub['grace_days']) . ' days', strtotime($issue)));
        $number = flexihubPlatformInvoiceNumber((int)$sub['tenant_id']);
        $stmt = $conn->prepare("INSERT INTO platform_invoices (tenant_id,subscription_id,invoice_number,period_start,period_end,issue_date,due_date,subtotal,total_amount,status) VALUES (?,?,?,?,?,?,?,?,?,'unpaid')");
        if (!$stmt) return false;
        $amount=(float)$sub['price'];
        $stmt->bind_param('iissssssd',(int)$sub['tenant_id'],(int)$subscriptionId,$number,$periodStart,$periodEnd,$issue,$due,$amount,$amount);
        $ok=$stmt->execute(); $id=$ok?$conn->insert_id:false; $stmt->close();
        if ($id) {
            $conn->query("UPDATE tenant_platform_subscriptions SET last_invoice_id=".(int)$id.", updated_at=NOW() WHERE id=".(int)$subscriptionId);
        }
        return $id ?: false;
    }
}

if (!function_exists('flexihubProcessPlatformBilling')) {
    function flexihubProcessPlatformBilling() {
        global $conn;
        if (!flexihubPlatformBillingEnabled()) return ['enabled'=>false,'created'=>0,'past_due'=>0,'suspended'=>0,'errors'=>0];

        $created=0;$pastDue=0;$suspended=0;$errors=0;
        $tenants=dbFetchAll("SELECT id FROM tenants WHERE LOWER(COALESCE(status,'')) NOT IN ('deleted','archived')");
        foreach($tenants as $tenant){
            $tenantId=(int)$tenant['id'];
            try {
                $subId=flexihubEnsurePlatformSubscription($tenantId);
                if(!$subId){$errors++;continue;}
                $sub=dbFetchOne("SELECT s.*,p.grace_days FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id WHERE s.id=? LIMIT 1",'i',$subId);
                if(!$sub) {$errors++;continue;}

                if($sub['status']==='trial' && !empty($sub['trial_ends_at']) && strtotime($sub['trial_ends_at'])<=time()){
                    $start=date('Y-m-d');
                    $end=flexihubPlatformAddCycle($start, dbFetchOne("SELECT billing_cycle FROM platform_plans WHERE id=? LIMIT 1",'i',(int)$sub['plan_id'])['billing_cycle']);
                    $conn->query("UPDATE tenant_platform_subscriptions SET status='active',current_period_start='".$conn->real_escape_string($start)."',current_period_end='".$conn->real_escape_string($end)."',updated_at=NOW() WHERE id=".$subId);
                    $sub['status']='active'; $sub['current_period_start']=$start; $sub['current_period_end']=$end;
                }

                $invoice=flexihubGeneratePlatformInvoice($subId);
                if($invoice && !dbFetchOne("SELECT id FROM platform_invoices WHERE id=? AND created_at < NOW() LIMIT 1",'i',$invoice)) $created++;

                $open=dbFetchOne("SELECT id,due_date,status,total_amount,paid_amount FROM platform_invoices WHERE subscription_id=? AND status IN ('unpaid','partial','overdue') ORDER BY due_date DESC,id DESC LIMIT 1",'i',$subId);
                if($open){
                    $balance=max(0,(float)$open['total_amount']-(float)$open['paid_amount']);
                    if($balance<=0.0001){
                        $conn->query("UPDATE platform_invoices SET status='paid',updated_at=NOW() WHERE id=".(int)$open['id']);
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
        return ['enabled'=>true,'created'=>$created,'past_due'=>$pastDue,'suspended'=>$suspended,'errors'=>$errors];
    }
}
