<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireActiveUser();
global $conn;
$isHost = isHostUser();
$tenantId = (int)getCurrentTenantId();
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$isHost) {
    requireTenantContext();
    requireModulePermission('support', 'view');
    if ($tenantId <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }
}
$errors = [];
$notice = '';
$selectedId = (int)($_GET['id'] ?? 0);
$allowedStatuses = ['open','in_progress','waiting_on_tenant','resolved','closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'create' && !$isHost) {
        requireModulePermission('support', 'create');
        $subject = trim((string)($_POST['subject'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'general'));
        $priority = trim((string)($_POST['priority'] ?? 'normal'));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($subject === '' || strlen($subject) > 200) $errors[] = 'Enter a subject of up to 200 characters.';
        if (!in_array($category, ['general','billing','connectivity','router','pppoe','hotspot','other'], true)) $errors[] = 'Select a valid category.';
        if (!in_array($priority, ['low','normal','high','urgent'], true)) $errors[] = 'Select a valid priority.';
        if ($message === '' || strlen($message) > 10000) $errors[] = 'Enter a message of up to 10,000 characters.';
        if (!$errors) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO support_tickets (tenant_id, created_by, subject, category, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
                if (!$stmt) throw new Exception('Unable to prepare ticket creation.');
                $stmt->bind_param('iisss', $tenantId, $userId, $subject, $category, $priority);
                if (!$stmt->execute()) throw new Exception('Unable to create ticket.');
                $ticketId = (int)$conn->insert_id;
                $stmt->close();
                $reply = $conn->prepare("INSERT INTO support_ticket_messages (ticket_id, tenant_id, sender_user_id, sender_type, message) VALUES (?, ?, ?, 'tenant', ?)");
                if (!$reply) throw new Exception('Unable to prepare the first message.');
                $reply->bind_param('iiis', $ticketId, $tenantId, $userId, $message);
                if (!$reply->execute()) throw new Exception('Unable to save the first message.');
                $reply->close();
                $conn->commit();
                header('Location: index.php?id=' . $ticketId . '&created=1'); exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Support ticket creation failed: ' . $e->getMessage());
                $errors[] = 'The ticket could not be saved. Please try again.';
            }
        }
    } elseif ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '' || strlen($message) > 10000) $errors[] = 'Enter a reply of up to 10,000 characters.';
        $ticket = null;
        if (!$errors && $ticketId > 0) {
            $check = $isHost
                ? $conn->prepare('SELECT id, tenant_id, status FROM support_tickets WHERE id=? LIMIT 1')
                : $conn->prepare('SELECT id, tenant_id, status FROM support_tickets WHERE id=? AND tenant_id=? LIMIT 1');
            if ($check) {
                if ($isHost) $check->bind_param('i', $ticketId); else $check->bind_param('ii', $ticketId, $tenantId);
                if ($check->execute()) $ticket = $check->get_result()->fetch_assoc();
                $check->close();
            }
            if (!$ticket) $errors[] = 'Ticket not found or access denied.';
            elseif (($ticket['status'] ?? '') === 'closed' && !$isHost) $errors[] = 'This ticket is closed and cannot receive replies.';
        }
        if (!$errors && $ticket) {
            $senderType = $isHost ? 'host' : 'tenant';
            $ticketTenantId = (int)$ticket['tenant_id'];
            $reply = $conn->prepare('INSERT INTO support_ticket_messages (ticket_id, tenant_id, sender_user_id, sender_type, message) VALUES (?, ?, ?, ?, ?)');
            if (!$reply) $errors[] = 'Unable to prepare the reply.';
            else {
                $reply->bind_param('iiiss', $ticketId, $ticketTenantId, $userId, $senderType, $message);
                if ($reply->execute()) {
                    $reply->close();
                    $touch = $conn->prepare('UPDATE support_tickets SET updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?');
                    if ($touch) { $touch->bind_param('ii', $ticketId, $ticketTenantId); $touch->execute(); $touch->close(); }
                    header('Location: index.php?id=' . $ticketId . '&replied=1'); exit;
                }
                $reply->close(); $errors[] = 'Unable to save the reply.';
            }
        }
    } elseif ($action === 'status' && $isHost) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        $assignee = (int)($_POST['assigned_to'] ?? 0);
        $resolution = trim((string)($_POST['resolution'] ?? ''));
        if (!in_array($status, $allowedStatuses, true)) $errors[] = 'Invalid ticket status.';
        if (strlen($resolution) > 5000) $errors[] = 'Resolution notes are too long.';
        if (!$errors) {
            $stmt = $conn->prepare('UPDATE support_tickets SET status=?, assigned_to=?, resolution=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
            if (!$stmt) $errors[] = 'Unable to prepare ticket update.';
            else {
                $stmt->bind_param('sisi', $status, $assignee, $resolution, $ticketId);
                if ($stmt->execute()) { $stmt->close(); header('Location: index.php?id=' . $ticketId . '&updated=1'); exit; }
                $stmt->close(); $errors[] = 'Unable to update the ticket.';
            }
        }
    }
}

if (isset($_GET['created'])) $notice = 'Support ticket created.';
elseif (isset($_GET['replied'])) $notice = 'Reply added.';
elseif (isset($_GET['updated'])) $notice = 'Ticket updated.';

$statusFilter = trim((string)($_GET['status'] ?? ''));
$filters = []; $params = []; $types = '';
if (!$isHost) { $filters[] = 'tenant_id=?'; $params[] = $tenantId; $types .= 'i'; }
if (in_array($statusFilter, $allowedStatuses, true)) { $filters[] = 'status=?'; $params[] = $statusFilter; $types .= 's'; }
$sql = 'SELECT * FROM support_tickets' . ($filters ? ' WHERE ' . implode(' AND ', $filters) : '') . ' ORDER BY updated_at DESC LIMIT 300';
$list = $conn->prepare($sql); $tickets = [];
if ($list) {
    if ($params) $list->bind_param($types, ...$params);
    if ($list->execute()) { $result = $list->get_result(); while ($row = $result->fetch_assoc()) $tickets[] = $row; }
    $list->close();
} else { error_log('Support ticket list query failed: ' . $conn->error); $errors[] = 'Unable to load tickets. Apply the support-ticket migration first.'; }

$selected = null; $messages = [];
if ($selectedId > 0) {
    $stmt = $isHost
        ? $conn->prepare('SELECT * FROM support_tickets WHERE id=? LIMIT 1')
        : $conn->prepare('SELECT * FROM support_tickets WHERE id=? AND tenant_id=? LIMIT 1');
    if ($stmt) {
        if ($isHost) $stmt->bind_param('i', $selectedId); else $stmt->bind_param('ii', $selectedId, $tenantId);
        if ($stmt->execute()) $selected = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if ($selected) {
        $stmt = $conn->prepare('SELECT * FROM support_ticket_messages WHERE ticket_id=? AND tenant_id=? ORDER BY id ASC');
        if ($stmt) {
            $ticketTenantId = (int)$selected['tenant_id']; $stmt->bind_param('ii', $selectedId, $ticketTenantId);
            if ($stmt->execute()) { $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) $messages[] = $row; }
            $stmt->close();
        }
    } elseif (!$errors) $errors[] = 'Ticket not found or access denied.';
}
$pageTitle = $isHost ? 'Platform Support' : 'Support Centre';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-content support-command-center">
  <div class="page-header"><div><span class="support-kicker">SERVICE DESK</span><h1><?= $isHost ? 'Platform Support' : 'Support Centre' ?></h1><p><?= $isHost ? 'Manage tenant requests, responses and resolution.' : 'Create a request and track its progress with Flexihub.' ?></p></div></div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
  <div class="support-metrics"><div class="support-metric"><span>Tickets shown</span><strong><?= count($tickets) ?></strong></div><div class="support-metric"><span>Open / in progress</span><strong><?= count(array_filter($tickets, fn($t) => in_array($t['status'], ['open','in_progress','waiting_on_tenant'], true))) ?></strong></div><div class="support-metric"><span>Resolved / closed</span><strong><?= count(array_filter($tickets, fn($t) => in_array($t['status'], ['resolved','closed'], true))) ?></strong></div></div>
  <div class="support-layout">
    <section class="card support-panel">
      <?php if (!$isHost): ?><h2>Open a support ticket</h2><form method="post" class="support-form"><?= csrfField() ?><input type="hidden" name="action" value="create"><label>Subject<input name="subject" maxlength="200" required></label><div class="support-form-grid"><label>Category<select name="category"><?php foreach (['general','billing','connectivity','router','pppoe','hotspot','other'] as $v): ?><option value="<?= e($v) ?>"><?= e(ucfirst($v)) ?></option><?php endforeach; ?></select></label><label>Priority<select name="priority"><?php foreach (['low','normal','high','urgent'] as $v): ?><option value="<?= e($v) ?>" <?= $v === 'normal' ? 'selected' : '' ?>><?= e(ucfirst($v)) ?></option><?php endforeach; ?></select></label></div><label>Describe the issue<textarea name="message" rows="5" maxlength="10000" required></textarea></label><button class="btn btn-primary">Submit ticket</button></form><?php endif; ?>
      <div class="support-list-head"><h2><?= $isHost ? 'Tenant tickets' : 'Your tickets' ?></h2><form method="get"><select name="status" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach ($allowedStatuses as $v): ?><option value="<?= e($v) ?>" <?= $statusFilter === $v ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$v))) ?></option><?php endforeach; ?></select></form></div>
      <div class="support-ticket-list"><?php if (!$tickets): ?><p class="support-empty">No support tickets found.</p><?php endif; ?><?php foreach ($tickets as $ticket): ?><a class="support-ticket <?= $selectedId === (int)$ticket['id'] ? 'selected' : '' ?>" href="index.php?id=<?= (int)$ticket['id'] ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>"><div><strong>#<?= (int)$ticket['id'] ?> · <?= e($ticket['subject']) ?></strong><small><?= e(ucfirst($ticket['category'])) ?> · <?= e(ucfirst($ticket['priority'])) ?> priority<?= $isHost ? ' · Tenant '.$ticket['tenant_id'] : '' ?></small></div><span class="support-status <?= e($ticket['status']) ?>"><?= e(ucwords(str_replace('_',' ',$ticket['status']))) ?></span></a><?php endforeach; ?></div>
    </section>
    <section class="card support-panel">
      <?php if (!$selected): ?><div class="support-empty-state"><h2>Ticket details</h2><p>Select a ticket to read messages and manage its status.</p></div><?php else: ?>
      <div class="support-detail-head"><div><span class="support-kicker">TICKET #<?= (int)$selected['id'] ?></span><h2><?= e($selected['subject']) ?></h2><p><?= e(ucfirst($selected['category'])) ?> · Opened <?= e($selected['created_at']) ?></p></div><span class="support-status <?= e($selected['status']) ?>"><?= e(ucwords(str_replace('_',' ',$selected['status']))) ?></span></div>
      <div class="support-conversation"><?php foreach ($messages as $message): ?><article class="support-message <?= e($message['sender_type']) ?>"><div><strong><?= $message['sender_type'] === 'host' ? 'Flexihub Support' : 'Tenant' ?></strong><small><?= e($message['created_at']) ?></small></div><p><?= nl2br(e($message['message'])) ?></p></article><?php endforeach; ?></div>
      <?php if ($isHost): ?><form method="post" class="support-form support-admin-form"><?= csrfField() ?><input type="hidden" name="action" value="status"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>"><h3>Manage ticket</h3><label>Status<select name="status"><?php foreach ($allowedStatuses as $v): ?><option value="<?= e($v) ?>" <?= $selected['status'] === $v ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$v))) ?></option><?php endforeach; ?></select></label><label>Assigned user ID<input type="number" name="assigned_to" min="0" value="<?= (int)($selected['assigned_to'] ?? 0) ?>"></label><label>Resolution notes<textarea name="resolution" rows="3" maxlength="5000"><?= e($selected['resolution'] ?? '') ?></textarea></label><button class="btn btn-secondary">Save ticket</button></form><?php endif; ?>
      <?php if ($selected['status'] !== 'closed' || $isHost): ?><form method="post" class="support-form support-reply-form"><?= csrfField() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>"><label><?= $isHost ? 'Reply to tenant' : 'Add a reply' ?><textarea name="message" rows="4" maxlength="10000" required></textarea></label><button class="btn btn-primary">Send reply</button></form><?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<style>
.support-command-center{color:#f8fafc}.support-kicker{font-size:11px;letter-spacing:.16em;color:#60a5fa;font-weight:700}.support-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.support-metric,.support-panel{background:linear-gradient(145deg,rgba(16,24,39,.97),rgba(7,12,20,.96));border:1px solid rgba(96,165,250,.14);border-radius:14px;box-shadow:0 14px 35px rgba(0,0,0,.18)}.support-metric{padding:17px}.support-metric span{display:block;color:#94a3b8;font-size:12px}.support-metric strong{display:block;font-size:25px;margin-top:7px}.support-layout{display:grid;grid-template-columns:minmax(280px,.9fr) minmax(320px,1.1fr);gap:16px}.support-panel{padding:20px;min-width:0}.support-panel h2{margin:0 0 14px;color:#f8fafc}.support-form{display:grid;gap:12px}.support-form label{display:grid;gap:6px;color:#cbd5e1;font-size:13px}.support-form input,.support-form select,.support-form textarea,.support-list-head select{width:100%;background:#0b111b!important;color:#f8fafc!important;border:1px solid rgba(148,163,184,.22)!important;border-radius:8px;padding:10px}.support-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.support-list-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:22px 0 10px}.support-list-head h2{margin:0}.support-list-head select{min-width:130px}.support-ticket-list{display:grid;gap:8px}.support-ticket{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px;border:1px solid rgba(148,163,184,.13);border-radius:10px;text-decoration:none;color:#e2e8f0}.support-ticket.selected,.support-ticket:hover{border-color:#3b82f6;background:rgba(37,99,235,.1)}.support-ticket strong,.support-ticket small{display:block}.support-ticket small,.support-detail-head p,.support-message small{color:#94a3b8;font-size:12px;margin-top:5px}.support-status{white-space:nowrap;border-radius:999px;padding:5px 8px;background:rgba(59,130,246,.13);color:#93c5fd;font-size:11px}.support-status.closed,.support-status.resolved{color:#86efac;background:rgba(34,197,94,.12)}.support-status.waiting_on_tenant{color:#fcd34d;background:rgba(245,158,11,.12)}.support-conversation{display:grid;gap:10px;margin:18px 0}.support-message{padding:13px;border:1px solid rgba(148,163,184,.14);border-radius:10px;background:rgba(15,23,42,.65)}.support-message.host{border-color:rgba(59,130,246,.3)}.support-message div{display:flex;justify-content:space-between;gap:10px}.support-message p{overflow-wrap:anywhere;margin:10px 0 0;color:#e2e8f0}.support-admin-form{padding:15px;margin:18px 0;border:1px solid rgba(148,163,184,.15);border-radius:10px}.support-admin-form h3{margin:0}.support-reply-form{border-top:1px solid rgba(148,163,184,.14);padding-top:16px}.support-empty,.support-empty-state{color:#94a3b8;padding:18px;text-align:center}.support-empty-state h2{margin-top:15px}@media(max-width:950px){.support-layout{grid-template-columns:1fr}}@media(max-width:600px){.support-metrics{grid-template-columns:1fr}.support-form-grid{grid-template-columns:1fr}.support-list-head{align-items:stretch;flex-direction:column}.support-ticket{align-items:flex-start;flex-direction:column}}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
