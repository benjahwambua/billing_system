<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('operations','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$userId=(int)getCurrentUserId();
$errors=[];
$notice='';
$allowedTypes=['installation','repair','maintenance','survey','relocation','disconnection'];
$allowedPriorities=['low','normal','high','urgent'];
$allowedStatuses=['open','scheduled','assigned','in_progress','waiting_parts','completed','cancelled'];

$customers=[];
$q=$conn->prepare("SELECT id,customer_number,first_name,last_name FROM customers WHERE tenant_id=? ORDER BY first_name,last_name");
if($q){$q->bind_param('i',$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$customers[]=$x;$q->close();}
$staff=[];
$q=$conn->prepare("SELECT id,staff_code,first_name,last_name,position FROM staffs WHERE tenant_id=? AND LOWER(status)='active' ORDER BY first_name,last_name");
if($q){$q->bind_param('i',$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$staff[]=$x;$q->close();}

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    $action=trim((string)($_POST['action']??''));
    if($action==='create'){
        requireModulePermission('operations','create');
        $customerId=(int)($_POST['customer_id']??0);
        $accountId=(int)($_POST['internet_account_id']??0);
        $type=trim((string)($_POST['job_type']??'installation'));
        $title=trim((string)($_POST['title']??''));
        $description=trim((string)($_POST['description']??''));
        $priority=trim((string)($_POST['priority']??'normal'));
        $scheduled=trim((string)($_POST['scheduled_at']??''));
        $address=trim((string)($_POST['service_address']??''));
        $assigned=(int)($_POST['assigned_staff_id']??0);
        if($customerId<=0)$errors[]='Select a customer.';
        if(!in_array($type,$allowedTypes,true))$errors[]='Select a valid job type.';
        if($title===''||mb_strlen($title)>200)$errors[]='Enter a job title of up to 200 characters.';
        if(!in_array($priority,$allowedPriorities,true))$errors[]='Select a valid priority.';
        if(mb_strlen($description)>10000||mb_strlen($address)>500)$errors[]='Job description or service address is too long.';
        if($scheduled!=='' && strtotime($scheduled)===false)$errors[]='Enter a valid scheduled date and time.';
        if(!$errors){
            $check=$conn->prepare("SELECT id FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
            if(!$check)$errors[]='Unable to verify customer ownership.';
            else{$check->bind_param('ii',$customerId,$tenantId);$check->execute();$ok=(bool)$check->get_result()->fetch_assoc();$check->close();if(!$ok)$errors[]='Selected customer does not belong to this organisation.';}
        }
        if(!$errors&&$accountId>0){
            $check=$conn->prepare("SELECT id FROM internet_accounts WHERE id=? AND customer_id=? AND tenant_id=? LIMIT 1");
            if(!$check)$errors[]='Unable to verify the linked service account.';
            else{$check->bind_param('iii',$accountId,$customerId,$tenantId);$check->execute();$ok=(bool)$check->get_result()->fetch_assoc();$check->close();if(!$ok)$errors[]='Selected internet account does not belong to the selected customer.';}
        }
        if(!$errors&&$assigned>0){
            $check=$conn->prepare("SELECT id FROM staffs WHERE id=? AND tenant_id=? AND LOWER(status)='active' LIMIT 1");
            if(!$check)$errors[]='Unable to verify technician assignment.';
            else{$check->bind_param('ii',$assigned,$tenantId);$check->execute();$ok=(bool)$check->get_result()->fetch_assoc();$check->close();if(!$ok)$errors[]='Select an active technician from this organisation.';}
        }
        if(!$errors){
            $jobNumber='FJ-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));
            $status=$assigned>0?'assigned':($scheduled!==''?'scheduled':'open');
            $scheduledAt=$scheduled!==''?date('Y-m-d H:i:s',strtotime($scheduled)):null;
            $stmt=$conn->prepare("INSERT INTO field_jobs (tenant_id,job_number,customer_id,internet_account_id,job_type,title,description,priority,status,scheduled_at,assigned_staff_id,service_address,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if(!$stmt)$errors[]='Unable to prepare field job creation.';
            else{
                $accountValue=$accountId>0?$accountId:null;
                $assignedValue=$assigned>0?$assigned:null;
                $stmt->bind_param('isiissssssisi',$tenantId,$jobNumber,$customerId,$accountValue,$type,$title,$description,$priority,$status,$scheduledAt,$assignedValue,$address,$userId);
                if($stmt->execute()){
                    $jobId=(int)$stmt->insert_id;$stmt->close();
                    $log=$conn->prepare("INSERT INTO field_job_updates (tenant_id,job_id,created_by,assigned_staff_id,update_type,status_to,note) VALUES (?,?,?,?,'created',?,?)");
                    if($log){$log->bind_param('iiiiss',$tenantId,$jobId,$userId,$assigned,$status,$description);$log->execute();$log->close();}
                    header('Location: field_jobs.php?created=1');exit;
                }
                $errors[]='Unable to save field job.';$stmt->close();
            }
        }
    } elseif($action==='update'){
        requireModulePermission('operations','edit');
        $jobId=(int)($_POST['job_id']??0);
        $status=trim((string)($_POST['status']??'open'));
        $assigned=(int)($_POST['assigned_staff_id']??0);
        $scheduled=trim((string)($_POST['scheduled_at']??''));
        $note=trim((string)($_POST['note']??''));
        $signoff=trim((string)($_POST['customer_signoff_name']??''));
        if($jobId<=0||!in_array($status,$allowedStatuses,true))$errors[]='Select a valid job and status.';
        if(mb_strlen($note)>5000||mb_strlen($signoff)>150)$errors[]='Update note or customer sign-off is too long.';
        if($scheduled!==''&&strtotime($scheduled)===false)$errors[]='Enter a valid scheduled date and time.';
        if(!$errors&&$assigned>0){
            $check=$conn->prepare("SELECT id FROM staffs WHERE id=? AND tenant_id=? AND LOWER(status)='active' LIMIT 1");
            if(!$check)$errors[]='Unable to verify technician assignment.';
            else{$check->bind_param('ii',$assigned,$tenantId);$check->execute();$ok=(bool)$check->get_result()->fetch_assoc();$check->close();if(!$ok)$errors[]='Select an active technician from this organisation.';}
        }
        if(!$errors){
            $check=$conn->prepare("SELECT status FROM field_jobs WHERE id=? AND tenant_id=? LIMIT 1");
            if(!$check)$errors[]='Unable to verify field job ownership.';
            else{$check->bind_param('ii',$jobId,$tenantId);$check->execute();$job=$check->get_result()->fetch_assoc();$check->close();if(!$job)$errors[]='Field job not found for this organisation.';}
        }
        if(!$errors){
            $from=(string)$job['status'];$scheduledAt=$scheduled!==''?date('Y-m-d H:i:s',strtotime($scheduled)):null;
            $completedAt=$status==='completed'?date('Y-m-d H:i:s'):null;
            $signoffAt=$signoff!==''?date('Y-m-d H:i:s'):null;
            $stmt=$conn->prepare("UPDATE field_jobs SET status=?,assigned_staff_id=?,scheduled_at=?,resolution_notes=?,customer_signoff_name=NULLIF(?,''),customer_signoff_at=?,completed_at=? WHERE id=? AND tenant_id=?");
            if(!$stmt)$errors[]='Unable to prepare field job update.';
            else{
                $assignedValue=$assigned>0?$assigned:null;
                $stmt->bind_param('sisssssii',$status,$assignedValue,$scheduledAt,$note,$signoff,$signoffAt,$completedAt,$jobId,$tenantId);
                if(!$stmt->execute())$errors[]='Unable to update field job.';
                $stmt->close();
                if(!$errors){
                    $log=$conn->prepare("INSERT INTO field_job_updates (tenant_id,job_id,created_by,assigned_staff_id,update_type,status_from,status_to,note) VALUES (?,?,?,?,'status',?,?,?)");
                    if($log){$log->bind_param('iiiisss',$tenantId,$jobId,$userId,$assigned,$from,$status,$note);$log->execute();$log->close();}
                    header('Location: field_jobs.php?updated=1');exit;
                }
            }
        }
    }
}
if(isset($_GET['created']))$notice='Field job created.';
elseif(isset($_GET['updated']))$notice='Field job updated.';

$statusFilter=trim((string)($_GET['status']??''));
$rows=[];
$sql="SELECT j.*,c.customer_number,TRIM(CONCAT_WS(' ',c.first_name,c.last_name)) AS customer_name,ia.account_number,ia.username,st.staff_code,TRIM(CONCAT_WS(' ',st.first_name,st.last_name)) AS technician_name
      FROM field_jobs j
      INNER JOIN customers c ON c.id=j.customer_id AND c.tenant_id=j.tenant_id
      LEFT JOIN internet_accounts ia ON ia.id=j.internet_account_id AND ia.tenant_id=j.tenant_id
      LEFT JOIN staffs st ON st.id=j.assigned_staff_id AND st.tenant_id=j.tenant_id
      WHERE j.tenant_id=?";
$params=[$tenantId];$types='i';
if(in_array($statusFilter,$allowedStatuses,true)){$sql.=" AND j.status=?";$params[]=$statusFilter;$types.='s';}
$sql.=" ORDER BY FIELD(j.priority,'urgent','high','normal','low'),j.scheduled_at ASC,j.id DESC LIMIT 300";
$stmt=$conn->prepare($sql);
if($stmt){$stmt->bind_param($types,...$params);if($stmt->execute()){$r=$stmt->get_result();while($x=$r->fetch_assoc())$rows[]=$x;}$stmt->close();}

$counts=['open'=>0,'scheduled'=>0,'assigned'=>0,'in_progress'=>0,'waiting_parts'=>0,'completed'=>0,'cancelled'=>0];
foreach($rows as $row){$key=(string)$row['status'];if(isset($counts[$key]))$counts[$key]++;}
$pageTitle='Field Operations';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/sidebar.php';
?>
<div class="main-content field-ops">
 <div class="field-hero"><div><span>FIELD OPERATIONS</span><h1>Jobs & Technician Dispatch</h1><p>Schedule installations and repairs, assign technicians, track progress and record customer sign-off.</p></div><a class="btn btn-secondary" href="../staffs/list.php">Technician Directory</a></div>
 <?php if($errors): ?><div class="field-alert error"><?php foreach($errors as $error): ?><div><?=e($error)?></div><?php endforeach; ?></div><?php endif; ?>
 <?php if($notice): ?><div class="field-alert success"><?=e($notice)?></div><?php endif; ?>
 <div class="field-metrics"><?php foreach(['open'=>'Open','scheduled'=>'Scheduled','assigned'=>'Assigned','in_progress'=>'In Progress','waiting_parts'=>'Waiting Parts','completed'=>'Completed'] as $key=>$label): ?><div><small><?=e($label)?></small><strong><?=number_format($counts[$key])?></strong></div><?php endforeach; ?></div>
 <?php if(userCan('operations','create')): ?>
 <section class="field-panel"><h2>Schedule a Job</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="create"><div class="field-grid">
 <label>Customer *<select name="customer_id" required><option value="">Select customer</option><?php foreach($customers as $c): ?><option value="<?=(int)$c['id']?>"><?=e(($c['customer_number']??'').' — '.trim(($c['first_name']??'').' '.($c['last_name']??'')))?></option><?php endforeach; ?></select></label>
 <label>Internet Account ID (optional)<input type="number" min="1" name="internet_account_id" placeholder="Linked service account ID"></label>
 <label>Job Type<select name="job_type"><?php foreach($allowedTypes as $type): ?><option value="<?=$type?>"><?=ucfirst($type)?></option><?php endforeach; ?></select></label>
 <label>Title *<input name="title" maxlength="200" required placeholder="e.g. Install fibre connection"></label>
 <label>Priority<select name="priority"><?php foreach($allowedPriorities as $priority): ?><option value="<?=$priority?>" <?=$priority==='normal'?'selected':''?>><?=ucfirst($priority)?></option><?php endforeach; ?></select></label>
 <label>Assign Technician<select name="assigned_staff_id"><option value="0">Unassigned</option><?php foreach($staff as $person): ?><option value="<?=(int)$person['id']?>"><?=e(trim($person['first_name'].' '.$person['last_name']).' · '.$person['staff_code'])?></option><?php endforeach; ?></select></label>
 <label>Scheduled At<input type="datetime-local" name="scheduled_at"></label>
 <label class="full">Service Address<input name="service_address" maxlength="500"></label>
 <label class="full">Description<textarea name="description" rows="3"></textarea></label>
 </div><button class="btn btn-primary">Create Job</button></form></section>
 <?php endif; ?>
 <section class="field-panel"><div class="field-panel-heading"><div><h2>Dispatch Board</h2><p><?=count($rows)?> job(s) shown · filtered to this organisation</p></div><form method="get"><select name="status" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach($allowedStatuses as $status): ?><option value="<?=$status?>" <?=$statusFilter===$status?'selected':''?>><?=ucwords(str_replace('_',' ',$status))?></option><?php endforeach; ?></select></form></div>
 <div class="field-table-wrap"><table><thead><tr><th>Job</th><th>Customer</th><th>Type / Priority</th><th>Technician</th><th>Schedule</th><th>Status</th><th>Update</th></tr></thead><tbody>
 <?php foreach($rows as $job): ?><tr><td><strong><?=e($job['job_number'])?></strong><small><?=e($job['title'])?></small></td><td><?=e(($job['customer_number']??'').' — '.($job['customer_name']??''))?><small><?=e($job['account_number']??$job['username']??'')?></small></td><td><?=e(ucfirst($job['job_type']))?><small><?=e(ucfirst($job['priority']))?></small></td><td><?=e($job['technician_name']??'Unassigned')?></td><td><?=e($job['scheduled_at']??'—')?></td><td><span class="job-status"><?=e(ucwords(str_replace('_',' ',$job['status'])))?></span></td><td><?php if(userCan('operations','edit')): ?><details><summary>Update</summary><form method="post" class="job-update-form"><?=csrfField()?><input type="hidden" name="action" value="update"><input type="hidden" name="job_id" value="<?=(int)$job['id']?>"><label>Status<select name="status"><?php foreach($allowedStatuses as $status): ?><option value="<?=$status?>" <?=$job['status']===$status?'selected':''?>><?=ucwords(str_replace('_',' ',$status))?></option><?php endforeach; ?></select></label><label>Technician<select name="assigned_staff_id"><option value="0">Unassigned</option><?php foreach($staff as $person): ?><option value="<?=(int)$person['id']?>" <?=(int)$job['assigned_staff_id']===(int)$person['id']?'selected':''?>><?=e(trim($person['first_name'].' '.$person['last_name']))?></option><?php endforeach; ?></select></label><label>Scheduled At<input type="datetime-local" name="scheduled_at" value="<?=!empty($job['scheduled_at'])?e(date('Y-m-d\TH:i',strtotime($job['scheduled_at']))):''?>"></label><label>Update / Resolution Note<textarea name="note" rows="2"><?=e($job['resolution_notes']??'')?></textarea></label><label>Customer Sign-off Name<input name="customer_signoff_name" maxlength="150" value="<?=e($job['customer_signoff_name']??'')?>"></label><button class="btn btn-primary">Save Update</button></form></details><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
 <?php if(!$rows): ?><tr><td colspan="7">No field jobs found for this organisation.</td></tr><?php endif; ?>
 </tbody></table></div></section>
</div>
<style>
.field-ops{padding:24px;color:#e2e8f0}.field-hero{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:24px;margin-bottom:16px;border:1px solid rgba(59,130,246,.18);border-radius:16px;background:radial-gradient(circle at 90% 10%,rgba(37,99,235,.2),transparent 35%),linear-gradient(135deg,#101827,#0a101a)}.field-hero span{color:#60a5fa;font-size:11px;font-weight:800;letter-spacing:.15em}.field-hero h1{margin:7px 0;color:#f8fafc}.field-hero p,.field-panel-heading p{margin:0;color:#94a3b8}.field-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:16px}.field-metrics>div,.field-panel{padding:18px;border:1px solid rgba(148,163,184,.14);border-radius:14px;background:linear-gradient(145deg,#101827,#0b111b)}.field-metrics small{display:block;color:#94a3b8}.field-metrics strong{display:block;color:#f8fafc;font-size:23px;margin-top:6px}.field-panel{margin-bottom:16px}.field-panel h2{margin:0 0 14px;color:#f8fafc;font-size:17px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-bottom:16px}.field-grid .full{grid-column:1/-1}.field-grid label,.job-update-form label{display:grid;gap:6px;color:#cbd5e1;font-size:12px;font-weight:700}.field-grid input,.field-grid select,.field-grid textarea,.job-update-form input,.job-update-form select,.job-update-form textarea,.field-panel-heading select{width:100%;box-sizing:border-box;padding:10px;border:1px solid rgba(148,163,184,.2);border-radius:8px;background:#0b111b;color:#f8fafc}.field-panel-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}.field-table-wrap{overflow:auto}.field-table-wrap table{width:100%;border-collapse:collapse;min-width:900px}.field-table-wrap th,.field-table-wrap td{padding:11px 12px;text-align:left;border-bottom:1px solid rgba(148,163,184,.13);color:#e2e8f0;vertical-align:top}.field-table-wrap th{background:#0b111b;color:#94a3b8}.field-table-wrap td small{display:block;color:#94a3b8;margin-top:4px}.job-status{display:inline-block;padding:5px 8px;border-radius:20px;background:rgba(37,99,235,.15);color:#93c5fd;font-size:11px}.field-table-wrap summary{cursor:pointer;color:#60a5fa;font-weight:700}.job-update-form{display:grid;gap:9px;min-width:220px;padding:10px;background:#101827;border-radius:10px}.field-ops .btn{padding:9px 13px;border:0;border-radius:8px;text-decoration:none;font-weight:700}.field-ops .btn-primary{background:#2563eb;color:#fff}.field-ops .btn-secondary{background:#172235;color:#cbd5e1;border:1px solid rgba(148,163,184,.16)}.field-alert{padding:12px 14px;margin-bottom:14px;border-radius:9px}.field-alert.error{background:rgba(239,68,68,.13);color:#fca5a5}.field-alert.success{background:rgba(34,197,94,.13);color:#86efac}@media(max-width:1000px){.field-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:650px){.field-ops{padding:14px}.field-hero{align-items:flex-start;flex-direction:column}.field-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.field-grid{grid-template-columns:1fr}.field-grid .full{grid-column:auto}.field-panel-heading{align-items:flex-start;flex-direction:column}}
</style>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
