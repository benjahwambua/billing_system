<?php
require_once __DIR__.'/../includes/auth.php';
global $conn;
if(empty($_SESSION['customer_portal_authenticated'])||empty($_SESSION['customer_portal_tenant_id'])||empty($_SESSION['customer_portal_customer_id'])){header('Location: login.php');exit;}
$tenantId=(int)$_SESSION['customer_portal_tenant_id'];$customerId=(int)$_SESSION['customer_portal_customer_id'];$portalUserId=(int)$_SESSION['customer_portal_user_id'];
$errors=[];$notice='';$ticketId=(int)($_GET['id']??$_POST['ticket_id']??0);
$categories=['technical','billing','installation','account','other'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();$action=trim((string)($_POST['action']??''));$message=trim((string)($_POST['message']??''));
 if($message===''||mb_strlen($message)>5000)$errors[]='Enter a message of up to 5,000 characters.';
 if($action==='create'){
  $subject=trim((string)($_POST['subject']??''));$category=trim((string)($_POST['category']??'technical'));
  if($subject===''||mb_strlen($subject)>180)$errors[]='Enter a ticket subject of up to 180 characters.';
  if(!in_array($category,$categories,true))$errors[]='Select a valid support category.';
  if(!$errors){
   $number='CUS-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));
   $conn->begin_transaction();
   try{
    $q=$conn->prepare("INSERT INTO customer_support_tickets (tenant_id,customer_id,portal_user_id,ticket_number,subject,category,priority,status,last_message_at) VALUES (?,?,?,?,?,?, 'normal','open',NOW())");
    if(!$q)throw new RuntimeException('Unable to prepare support ticket.');
    $q->bind_param('iiisss',$tenantId,$customerId,$portalUserId,$number,$subject,$category);
    if(!$q->execute())throw new RuntimeException('Unable to create support ticket.');
    $newId=(int)$q->insert_id;$q->close();
    $msg=$conn->prepare("INSERT INTO customer_support_messages (tenant_id,ticket_id,sender_type,sender_id,message) VALUES (?,?,'customer',?,?)");
    if(!$msg)throw new RuntimeException('Unable to save your message.');
    $msg->bind_param('iiis',$tenantId,$newId,$portalUserId,$message);
    if(!$msg->execute())throw new RuntimeException('Unable to save your message.');
    $msg->close();$conn->commit();header('Location: tickets.php?id='.$newId.'&created=1');exit;
   }catch(Throwable $e){$conn->rollback();$errors[]=$e->getMessage();}
  }
 }elseif($action==='reply'){
  $check=$conn->prepare("SELECT id,status FROM customer_support_tickets WHERE id=? AND tenant_id=? AND customer_id=? LIMIT 1");
  if(!$check)$errors[]='Unable to verify support ticket.';
  else{$check->bind_param('iii',$ticketId,$tenantId,$customerId);$check->execute();$ticket=$check->get_result()->fetch_assoc();$check->close();if(!$ticket)$errors[]='Support ticket not found.';elseif(in_array($ticket['status'],['closed','resolved'],true))$errors[]='This ticket is closed. Open a new ticket if you need more help.';}
  if(!$errors){
   $conn->begin_transaction();
   try{
    $msg=$conn->prepare("INSERT INTO customer_support_messages (tenant_id,ticket_id,sender_type,sender_id,message) VALUES (?,?,'customer',?,?)");
    if(!$msg)throw new RuntimeException('Unable to save your reply.');
    $msg->bind_param('iiis',$tenantId,$ticketId,$portalUserId,$message);
    if(!$msg->execute())throw new RuntimeException('Unable to save your reply.');
    $msg->close();
    $status=$ticket['status']==='waiting_customer'?'in_progress':$ticket['status'];
    $up=$conn->prepare("UPDATE customer_support_tickets SET status=?,last_message_at=NOW() WHERE id=? AND tenant_id=? AND customer_id=?");
    if(!$up)throw new RuntimeException('Unable to update ticket activity.');
    $up->bind_param('siii',$status,$ticketId,$tenantId,$customerId);
    if(!$up->execute())throw new RuntimeException('Unable to update ticket activity.');
    $up->close();$conn->commit();header('Location: tickets.php?id='.$ticketId.'&replied=1');exit;
   }catch(Throwable $e){$conn->rollback();$errors[]=$e->getMessage();}
  }
 }else $errors[]='Invalid support action.';
}
if(isset($_GET['created']))$notice='Your support request has been created.';
elseif(isset($_GET['replied']))$notice='Your reply has been sent.';
$tickets=[];$ticket=null;$messages=[];
$q=$conn->prepare("SELECT * FROM customer_support_tickets WHERE tenant_id=? AND customer_id=? ORDER BY COALESCE(last_message_at,created_at) DESC LIMIT 100");
if($q){$q->bind_param('ii',$tenantId,$customerId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$tickets[]=$x;$q->close();}
if($ticketId>0){
 $q=$conn->prepare("SELECT * FROM customer_support_tickets WHERE id=? AND tenant_id=? AND customer_id=? LIMIT 1");
 if($q){$q->bind_param('iii',$ticketId,$tenantId,$customerId);$q->execute();$ticket=$q->get_result()->fetch_assoc();$q->close();}
 if($ticket){$q=$conn->prepare("SELECT * FROM customer_support_messages WHERE ticket_id=? AND tenant_id=? ORDER BY id ASC");if($q){$q->bind_param('ii',$ticketId,$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$messages[]=$x;$q->close();}}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Support Centre</title></head><body><header><div><strong>FLEXIHUB</strong><span>Customer Support</span></div><nav><a href="index.php">My Account</a><a href="logout.php">Sign Out</a></nav></header><main><div class="page-head"><div><h1>Support Centre</h1><p>Submit a request and follow replies from your ISP support team.</p></div><a class="btn" href="tickets.php">New Request</a></div>
<?php foreach($errors as $e): ?><div class="alert error"><?=e($e)?></div><?php endforeach; ?><?php if($notice): ?><div class="alert success"><?=e($notice)?></div><?php endif; ?>
<div class="layout"><section class="panel"><h2><?= $ticket ? 'Ticket '.e($ticket['ticket_number']) : 'My Requests' ?></h2><?php if($ticket): ?><p><strong><?=e($ticket['subject'])?></strong> · <?=e(ucwords(str_replace('_',' ',$ticket['status'])))?></p><div class="thread"><?php foreach($messages as $m): ?><article class="<?=$m['sender_type']==='customer'?'mine':'theirs'?>"><small><?=$m['sender_type']==='customer'?'You':'Support Team'?> · <?=e($m['created_at'])?></small><p><?=nl2br(e($m['message']))?></p></article><?php endforeach; ?></div><?php if(!in_array($ticket['status'],['closed','resolved'],true)): ?><form method="post" class="reply-form"><?=csrfField()?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?=$ticketId?>"><textarea name="message" rows="3" maxlength="5000" required placeholder="Write a reply..."></textarea><button class="btn">Send Reply</button></form><?php endif; ?><?php else: ?><form method="post" class="new-ticket"><?=csrfField()?><input type="hidden" name="action" value="create"><label>Subject<input name="subject" maxlength="180" required></label><label>Category<select name="category"><?php foreach($categories as $category): ?><option value="<?=$category?>"><?=ucfirst($category)?></option><?php endforeach; ?></select></label><label>Message<textarea name="message" rows="4" maxlength="5000" required></textarea></label><button class="btn">Submit Request</button></form><?php endif; ?></section><aside class="panel"><h2>My Tickets</h2><?php foreach($tickets as $t): ?><a class="ticket-link" href="tickets.php?id=<?=(int)$t['id']?>"><strong><?=e($t['ticket_number'])?></strong><span><?=e($t['subject'])?></span><small><?=e(ucwords(str_replace('_',' ',$t['status'])))?> · <?=e($t['created_at'])?></small></a><?php endforeach; ?><?php if(!$tickets): ?><p>No support requests yet.</p><?php endif; ?></aside></div></main><style>*{box-sizing:border-box}body{margin:0;background:#080e18;color:#e2e8f0;font-family:Arial,sans-serif}header{display:flex;justify-content:space-between;align-items:center;padding:15px 5%;background:#0d1625;border-bottom:1px solid rgba(148,163,184,.14)}header strong{display:block;color:#60a5fa;letter-spacing:.12em}header span{color:#94a3b8;font-size:11px}header nav{display:flex;gap:14px}header a{color:#93c5fd;text-decoration:none}main{max-width:1200px;margin:24px auto;padding:0 18px}.page-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.page-head h1{margin:0;color:#f8fafc}.page-head p{color:#94a3b8}.layout{display:grid;grid-template-columns:1.5fr 1fr;gap:14px}.panel{padding:18px;border:1px solid rgba(148,163,184,.14);border-radius:14px;background:linear-gradient(145deg,#101827,#0b111b)}.panel h2{margin-top:0;color:#f8fafc;font-size:17px}.panel label{display:grid;gap:6px;color:#cbd5e1;font-weight:700;font-size:12px;margin-bottom:12px}.panel input,.panel select,.panel textarea{width:100%;box-sizing:border-box;padding:11px;border:1px solid rgba(148,163,184,.2);border-radius:8px;background:#0b111b;color:#f8fafc}.btn{display:inline-block;padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:white;font-weight:700;text-decoration:none;cursor:pointer}.ticket-link{display:block;padding:12px 0;border-bottom:1px solid rgba(148,163,184,.12);color:#e2e8f0;text-decoration:none}.ticket-link span,.ticket-link small{display:block;margin-top:4px}.ticket-link small,.thread small{color:#94a3b8}.thread{display:grid;gap:10px;margin:16px 0}.thread article{max-width:88%;padding:12px;border-radius:10px;background:#172235}.thread article.mine{margin-left:auto;background:rgba(37,99,235,.2)}.thread p{margin:7px 0 0;white-space:pre-wrap;line-height:1.5}.reply-form{display:grid;gap:10px}.alert{padding:12px;border-radius:8px;margin-bottom:12px}.alert.error{background:rgba(239,68,68,.13);color:#fca5a5}.alert.success{background:rgba(34,197,94,.13);color:#86efac}@media(max-width:750px){.layout{grid-template-columns:1fr}.page-head{align-items:flex-start;flex-direction:column}}
</style></body></html>
