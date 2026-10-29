<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot', 'create');
require_once '../includes/hotspot_package_policy.php';

global $conn;
$tenantId = (int)getCurrentTenantId();
$error = '';
$routers = [];

$q = $conn->prepare("SELECT id, COALESCE(name, router_name) name FROM mikrotik_routers WHERE tenant_id=? AND status='active' ORDER BY name");
if ($q) {
    $q->bind_param('i', $tenantId);
    $q->execute();
    $z = $q->get_result();
    while ($r = $z->fetch_assoc()) {
        $routers[] = $r;
    }
    $q->close();
}

$v = [
    'name' => '',
    'duration_minutes' => '60',
    'price' => '',
    'router_id' => '',
    'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($v as $k => $d) {
        $v[$k] = trim((string)($_POST[$k] ?? $d));
    }

    $v['status'] = strtolower($v['status']);
    $name = $v['name'];
    $mins = (int)$v['duration_minutes'];
    $price = (float)$v['price'];
    $rid = (int)$v['router_id'];
    $routerValid = false;

    if ($rid > 0) {
        $rq = $conn->prepare("SELECT id FROM mikrotik_routers WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
        if ($rq) {
            $rq->bind_param('ii', $rid, $tenantId);
            $rq->execute();
            $routerValid = (bool)$rq->get_result()->fetch_assoc();
            $rq->close();
        }
    }

    if ($name === '') {
        $error = 'Plan name is required.';
    } elseif (!flexihubIsValidHotspotPackageStatus($v['status'])) {
        $error = 'Select a valid hotspot plan status.';
    } elseif ($mins < 1) {
        $error = 'Duration must be at least 1 minute.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } elseif (!$rid || !$routerValid) {
        $error = 'Select an active MikroTik router belonging to your organisation.';
    } else {
        $q = $conn->prepare("INSERT INTO hotspot_packages(tenant_id,name,duration_minutes,price,status,router_id) VALUES(?,?,?,?,?,?)");
        if ($q) {
            $q->bind_param('iidssi', $tenantId, $name, $mins, $price, $v['status'], $rid);
            if ($q->execute()) {
                redirect('packages.php');
            }
            $error = $q->error;
            $q->close();
        } else {
            $error = 'Unable to create hotspot plan.';
        }
    }
}

$pageTitle = 'Add Hotspot Plan';
require_once '../includes/header.php';
?>

<div class="page-content hotspot-package-form-page">
    <div class="page-header">
        <div>
            <div class="page-kicker">Hotspot management</div>
            <h1>Add Hotspot Plan</h1>
            <p>Create a customer package by setting its name, duration, price and active MikroTik router.</p>
        </div>
        <a class="btn btn-secondary" href="packages.php">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to plans
        </a>
    </div>

    <section class="card hotspot-package-form-card" aria-labelledby="hotspot-package-form-title">
        <div class="hotspot-form-intro">
            <div class="hotspot-form-icon" aria-hidden="true"><i class="fa-solid fa-wifi"></i></div>
            <div>
                <h2 id="hotspot-package-form-title">Package details</h2>
                <p>Fields marked with <span class="required-indicator">*</span> are required.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$routers): ?>
            <div class="alert alert-warning" role="status">
                <strong>No active routers found.</strong>
                Add or activate a MikroTik router for this organisation before creating a hotspot plan.
            </div>
        <?php endif; ?>

        <form method="post" class="hotspot-package-form">
            <?= csrfField() ?>

            <div class="form-grid">
                <div class="form-field">
                    <label for="package-name">Plan name <span class="required-indicator">*</span></label>
                    <input id="package-name" type="text" name="name" value="<?= e($v['name']) ?>" placeholder="e.g. 1 Hour, Daily, Weekly" autocomplete="off" maxlength="150" required>
                    <small class="form-help">Use a clear name customers will recognise.</small>
                </div>

                <div class="form-field">
                    <label for="package-duration">Duration <span class="required-indicator">*</span></label>
                    <div class="hotspot-input-with-unit">
                        <input id="package-duration" type="number" min="1" step="1" name="duration_minutes" value="<?= e($v['duration_minutes']) ?>" required>
                        <span>minutes</span>
                    </div>
                    <small class="form-help">Enter the total access time included in this package.</small>
                </div>

                <div class="form-field">
                    <label for="package-price">Price (KES) <span class="required-indicator">*</span></label>
                    <div class="hotspot-input-with-prefix">
                        <span>KES</span>
                        <input id="package-price" type="number" min="0" step="0.01" name="price" value="<?= e($v['price']) ?>" placeholder="0.00" required>
                    </div>
                    <small class="form-help">Enter the amount a customer pays for this package.</small>
                </div>

                <div class="form-field">
                    <label for="package-router">MikroTik router <span class="required-indicator">*</span></label>
                    <select id="package-router" name="router_id" required>
                        <option value="">Choose an active router</option>
                        <?php foreach ($routers as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= (string)$v['router_id'] === (string)$r['id'] ? 'selected' : '' ?>>
                                <?= e($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">The router that will handle this hotspot package.</small>
                </div>

                <div class="form-field hotspot-status-field">
                    <label for="package-status">Plan status</label>
                    <select id="package-status" name="status">
                        <option value="active" <?= $v['status'] === 'active' ? 'selected' : '' ?>>Active — available for use</option>
                        <option value="inactive" <?= $v['status'] === 'inactive' ? 'selected' : '' ?>>Inactive — not available</option>
                    </select>
                    <small class="form-help">You can change the status later.</small>
                </div>
            </div>

            <div class="form-actions hotspot-form-actions">
                <a class="btn btn-secondary" href="packages.php">Cancel</a>
                <button class="btn btn-primary" type="submit" <?= !$routers ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    Save Hotspot Plan
                </button>
            </div>
        </form>
    </section>
</div>

<style>
.hotspot-package-form-page{--hpf-border:rgba(148,163,184,.16);--hpf-muted:#91a2b9}
.hotspot-package-form-page .page-header{align-items:center}
.hotspot-package-form-page .page-kicker{margin-bottom:7px;color:#60a5fa;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.hotspot-package-form-page .page-header h1{margin:0 0 7px;font-size:clamp(24px,2.4vw,32px);font-weight:800;letter-spacing:-.04em}
.hotspot-package-form-page .page-header p{max-width:650px;margin:0;color:var(--hpf-muted);font-size:13px;line-height:1.7}
.hotspot-package-form-card{max-width:900px;margin:0 auto 24px;padding:clamp(20px,3vw,32px)!important;border:1px solid var(--hpf-border)!important;background:linear-gradient(145deg,rgba(18,29,46,.98),rgba(10,17,29,.98))!important}
.hotspot-form-intro{display:flex;align-items:center;gap:14px;padding-bottom:22px;margin-bottom:24px;border-bottom:1px solid var(--hpf-border)}
.hotspot-form-icon{display:flex;flex:0 0 48px;width:48px;height:48px;align-items:center;justify-content:center;border:1px solid rgba(59,130,246,.3);border-radius:14px;background:rgba(37,99,235,.13);color:#60a5fa;font-size:19px}
.hotspot-form-intro h2{margin:0 0 5px;color:#f1f5f9;font-size:17px;font-weight:750;letter-spacing:-.02em}
.hotspot-form-intro p{margin:0;color:var(--hpf-muted);font-size:12px}
.hotspot-package-form .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:23px 22px!important}
.hotspot-package-form .form-field{display:flex;flex-direction:column;min-width:0;gap:8px}
.hotspot-package-form .form-field>label{margin:0;color:#dbeafe;font-size:12px;font-weight:750;letter-spacing:.015em}
.hotspot-package-form .required-indicator{color:#f87171}
.hotspot-package-form .form-help{display:block;margin:0;color:var(--hpf-muted)!important;font-size:11px!important;line-height:1.5}
.hotspot-package-form .hotspot-input-with-unit,.hotspot-package-form .hotspot-input-with-prefix{display:flex;align-items:center;min-width:0;overflow:hidden;border:1px solid rgba(148,163,184,.27);border-radius:10px;background:#080f1c;transition:border-color .16s,box-shadow .16s}
.hotspot-package-form .hotspot-input-with-unit:focus-within,.hotspot-package-form .hotspot-input-with-prefix:focus-within{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(59,130,246,.18)}
.hotspot-package-form .hotspot-input-with-unit input,.hotspot-package-form .hotspot-input-with-prefix input{min-width:0;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important}
.hotspot-package-form .hotspot-input-with-unit>span,.hotspot-package-form .hotspot-input-with-prefix>span{flex:0 0 auto;padding:0 13px;color:#94a3b8;font-size:12px;font-weight:700;white-space:nowrap}
.hotspot-package-form .hotspot-input-with-unit>span{border-left:1px solid var(--hpf-border)}
.hotspot-package-form .hotspot-input-with-prefix>span{border-right:1px solid var(--hpf-border)}
.hotspot-package-form .hotspot-status-field{grid-column:1/-1;max-width:calc(50% - 11px)}
.hotspot-package-form .form-actions{justify-content:flex-end;margin-top:28px;padding-top:20px;border-top:1px solid var(--hpf-border)}
.hotspot-package-form .form-actions .btn{min-height:44px;padding:11px 18px}
.hotspot-package-form .alert{display:flex;align-items:flex-start;gap:10px;margin:0 0 22px}
.hotspot-package-form .alert i{margin-top:3px}
@media(max-width:680px){
 .hotspot-package-form-card{padding:18px!important}
 .hotspot-package-form .form-grid{grid-template-columns:minmax(0,1fr)!important;gap:19px!important}
 .hotspot-package-form .hotspot-status-field{grid-column:auto;max-width:none}
 .hotspot-package-form-page .page-header{align-items:flex-start}
 .hotspot-package-form-page .page-header>.btn{align-self:flex-start}
 .hotspot-package-form .form-actions{justify-content:stretch}
 .hotspot-package-form .form-actions .btn{flex:1 1 140px}
}
</style>

<?php require_once '../includes/footer.php'; ?>
