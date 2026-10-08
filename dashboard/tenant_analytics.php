<?php
/*
|--------------------------------------------------------------------------
| TENANT DASHBOARD ANALYTICS
|--------------------------------------------------------------------------
| SmartISP-inspired operational analytics using existing Flexihub data.
| This file is loaded only from the tenant dashboard.
|--------------------------------------------------------------------------
*/

if (!isset($tenantId) || !$tenantId || !isset($conn) || !($conn instanceof mysqli)) {
    return;
}

$analyticsTodayRevenue = dashboardAmount(
    $conn,
    "SELECT COALESCE(SUM(amount), 0)
     FROM payments
     WHERE tenant_id = ?
     AND DATE(payment_date) = CURDATE()",
    [$tenantId]
);

$analyticsSevenDayRevenue = dashboardAmount(
    $conn,
    "SELECT COALESCE(SUM(amount), 0)
     FROM payments
     WHERE tenant_id = ?
     AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     AND payment_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)",
    [$tenantId]
);

$analyticsHourlyRevenue = dashboardRows(
    $conn,
    "SELECT HOUR(payment_date) AS hour_bucket,
            COALESCE(SUM(amount), 0) AS amount,
            COUNT(*) AS payment_count
     FROM payments
     WHERE tenant_id = ?
     AND DATE(payment_date) = CURDATE()
     GROUP BY HOUR(payment_date)
     ORDER BY hour_bucket ASC",
    [$tenantId]
);

$analyticsPaymentDays = dashboardRows(
    $conn,
    "SELECT DATE(payment_date) AS payment_day,
            COALESCE(SUM(amount), 0) AS amount,
            COUNT(*) AS payments_count
     FROM payments
     WHERE tenant_id = ?
     AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     AND payment_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
     GROUP BY DATE(payment_date)
     ORDER BY payment_day ASC",
    [$tenantId]
);

$analyticsSessionBreakdown = dashboardRows(
    $conn,
    "SELECT session_type,
            COUNT(*) AS total
     FROM active_sessions
     WHERE tenant_id = ?
     AND status = 'online'
     GROUP BY session_type
     ORDER BY total DESC",
    [$tenantId]
);

$analyticsServiceLifecycle = dashboardRows(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM internet_accounts
     WHERE tenant_id = ?
     GROUP BY status
     ORDER BY total DESC",
    [$tenantId]
);

$analyticsMaxHourly = 0;
foreach ($analyticsHourlyRevenue as $row) {
    $analyticsMaxHourly = max($analyticsMaxHourly, (float)($row['amount'] ?? 0));
}

$analyticsMaxDaily = 0;
foreach ($analyticsPaymentDays as $row) {
    $analyticsMaxDaily = max($analyticsMaxDaily, (float)($row['amount'] ?? 0));
}

$analyticsSessionTotal = 0;
foreach ($analyticsSessionBreakdown as $row) {
    $analyticsSessionTotal += (int)($row['total'] ?? 0);
}
?>

<section class="fh-analytics-section">

    <div class="fh-analytics-heading">
        <div>
            <h2>Operational Analytics</h2>
            <p>Live tenant-level billing and connectivity indicators.</p>
        </div>
        <a href="../reports/index.php">Open Reports</a>
    </div>

    <div class="fh-analytics-summary">

        <div class="fh-analytics-card">
            <span>Today's Collections</span>
            <strong><?= dashboardMoney($analyticsTodayRevenue) ?></strong>
            <small>Payments received today</small>
        </div>

        <div class="fh-analytics-card">
            <span>7-Day Collections</span>
            <strong><?= dashboardMoney($analyticsSevenDayRevenue) ?></strong>
            <small>Rolling seven-day revenue</small>
        </div>

        <div class="fh-analytics-card">
            <span>PPPoE Online</span>
            <strong><?= dashboardNumber(max(0, $onlineUsers - $activeHotspotSessions)) ?></strong>
            <small>Online users excluding hotspot</small>
        </div>

        <div class="fh-analytics-card">
            <span>Hotspot Online</span>
            <strong><?= dashboardNumber($activeHotspotSessions) ?></strong>
            <small>Active hotspot sessions</small>
        </div>

    </div>

    <div class="fh-analytics-grid">

        <div class="fh-analytics-panel fh-analytics-wide">

            <div class="fh-panel-title">
                <div>
                    <h3>Sales by Hour</h3>
                    <span>Today's recorded payments</span>
                </div>
            </div>

            <?php if (!$analyticsHourlyRevenue): ?>

                <div class="fh-empty">No payment activity recorded today.</div>

            <?php else: ?>

                <div class="fh-hour-chart">

                    <?php foreach ($analyticsHourlyRevenue as $row): ?>
                        <?php
                        $amount = (float)($row['amount'] ?? 0);
                        $height = $analyticsMaxHourly > 0
                            ? max(8, min(100, ($amount / $analyticsMaxHourly) * 100))
                            : 8;
                        $hour = (int)($row['hour_bucket'] ?? 0);
                        ?>
                        <div class="fh-hour-item" title="<?= htmlspecialchars(dashboardMoney($amount)) ?>">
                            <div class="fh-hour-value"><?= dashboardMoney($amount) ?></div>
                            <div class="fh-hour-bar-wrap">
                                <div class="fh-hour-bar" style="height: <?= $height ?>%;"></div>
                            </div>
                            <div class="fh-hour-label"><?= sprintf('%02d:00', $hour) ?></div>
                        </div>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

        <div class="fh-analytics-panel">

            <div class="fh-panel-title">
                <div>
                    <h3>Online Sessions</h3>
                    <span>Current active sessions</span>
                </div>
            </div>

            <?php if (!$analyticsSessionBreakdown): ?>

                <div class="fh-empty">No online sessions.</div>

            <?php else: ?>

                <div class="fh-breakdown-list">

                    <?php foreach ($analyticsSessionBreakdown as $row): ?>
                        <?php
                        $type = ucfirst(strtolower((string)($row['session_type'] ?? 'Other')));
                        $total = (int)($row['total'] ?? 0);
                        $share = $analyticsSessionTotal > 0
                            ? round(($total / $analyticsSessionTotal) * 100)
                            : 0;
                        ?>
                        <div class="fh-breakdown-row">
                            <div class="fh-breakdown-top">
                                <strong><?= htmlspecialchars($type) ?></strong>
                                <span><?= dashboardNumber($total) ?></span>
                            </div>
                            <div class="fh-progress">
                                <span style="width: <?= $share ?>%;"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

        <div class="fh-analytics-panel">

            <div class="fh-panel-title">
                <div>
                    <h3>Service Lifecycle</h3>
                    <span>Internet account status</span>
                </div>
            </div>

            <?php if (!$analyticsServiceLifecycle): ?>

                <div class="fh-empty">No internet accounts found.</div>

            <?php else: ?>

                <div class="fh-breakdown-list">

                    <?php foreach ($analyticsServiceLifecycle as $row): ?>
                        <?php
                        $status = ucfirst(strtolower((string)($row['status'] ?? 'Unknown')));
                        $total = (int)($row['total'] ?? 0);
                        ?>
                        <div class="fh-lifecycle-row">
                            <span><?= htmlspecialchars($status) ?></span>
                            <strong><?= dashboardNumber($total) ?></strong>
                        </div>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

        <div class="fh-analytics-panel fh-analytics-wide">

            <div class="fh-panel-title">
                <div>
                    <h3>Collection Trend</h3>
                    <span>Last seven calendar days</span>
                </div>
            </div>

            <?php if (!$analyticsPaymentDays): ?>

                <div class="fh-empty">No collection history available.</div>

            <?php else: ?>

                <div class="fh-day-chart">

                    <?php foreach ($analyticsPaymentDays as $row): ?>
                        <?php
                        $amount = (float)($row['amount'] ?? 0);
                        $height = $analyticsMaxDaily > 0
                            ? max(8, min(100, ($amount / $analyticsMaxDaily) * 100))
                            : 8;
                        $day = !empty($row['payment_day'])
                            ? date('D', strtotime($row['payment_day']))
                            : '—';
                        ?>
                        <div class="fh-day-item">
                            <div class="fh-day-value"><?= dashboardMoney($amount) ?></div>
                            <div class="fh-day-bar-wrap">
                                <div class="fh-day-bar" style="height: <?= $height ?>%;"></div>
                            </div>
                            <div class="fh-day-label"><?= htmlspecialchars($day) ?></div>
                        </div>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>

<style>
.fh-analytics-section {
    margin-top: 20px;
}

.fh-analytics-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.fh-analytics-heading h2 {
    margin: 0;
    color: #111827;
    font-size: 17px;
}

.fh-analytics-heading p {
    margin: 4px 0 0;
    color: #9ca3af;
    font-size: 11px;
}

.fh-analytics-heading a {
    color: #2563eb;
    font-size: 12px;
    font-weight: 600;
}

.fh-analytics-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}

.fh-analytics-card,
.fh-analytics-panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.fh-analytics-card {
    padding: 16px;
}

.fh-analytics-card span,
.fh-analytics-card small {
    display: block;
}

.fh-analytics-card span {
    color: #6b7280;
    font-size: 11px;
}

.fh-analytics-card strong {
    display: block;
    margin: 7px 0 4px;
    color: #111827;
    font-size: 20px;
}

.fh-analytics-card small {
    color: #9ca3af;
    font-size: 10px;
}

.fh-analytics-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.fh-analytics-panel {
    overflow: hidden;
}

.fh-analytics-wide {
    grid-column: span 2;
}

.fh-panel-title {
    min-height: 62px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
}

.fh-panel-title h3 {
    margin: 0;
    color: #111827;
    font-size: 14px;
}

.fh-panel-title span {
    display: block;
    margin-top: 3px;
    color: #9ca3af;
    font-size: 10px;
}

.fh-empty {
    padding: 30px 18px;
    text-align: center;
    color: #9ca3af;
    font-size: 11px;
}

.fh-hour-chart,
.fh-day-chart {
    min-height: 245px;
    padding: 18px;
    display: flex;
    align-items: stretch;
    gap: 7px;
    overflow-x: auto;
}

.fh-hour-item,
.fh-day-item {
    min-width: 42px;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.fh-hour-value,
.fh-day-value {
    min-height: 28px;
    color: #9ca3af;
    font-size: 8px;
    text-align: center;
    overflow: hidden;
    white-space: nowrap;
}

.fh-hour-bar-wrap,
.fh-day-bar-wrap {
    height: 160px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.fh-hour-bar,
.fh-day-bar {
    width: 70%;
    min-height: 3px;
    border-radius: 5px 5px 2px 2px;
    background: #2563eb;
}

.fh-hour-label,
.fh-day-label {
    margin-top: 7px;
    color: #6b7280;
    font-size: 9px;
    text-align: center;
}

.fh-breakdown-list {
    padding: 8px 18px 16px;
}

.fh-breakdown-row {
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.fh-breakdown-row:last-child {
    border-bottom: none;
}

.fh-breakdown-top,
.fh-lifecycle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.fh-breakdown-top strong,
.fh-breakdown-top span,
.fh-lifecycle-row span,
.fh-lifecycle-row strong {
    font-size: 11px;
}

.fh-breakdown-top strong,
.fh-lifecycle-row span {
    color: #374151;
}

.fh-breakdown-top span,
.fh-lifecycle-row strong {
    color: #111827;
}

.fh-progress {
    height: 5px;
    margin-top: 7px;
    overflow: hidden;
    border-radius: 99px;
    background: #f1f5f9;
}

.fh-progress span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #2563eb;
}

.fh-lifecycle-row {
    padding: 11px 0;
    border-bottom: 1px solid #f1f5f9;
}

.fh-lifecycle-row:last-child {
    border-bottom: none;
}

@media (max-width: 1000px) {
    .fh-analytics-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .fh-analytics-summary,
    .fh-analytics-grid {
        grid-template-columns: 1fr;
    }

    .fh-analytics-wide {
        grid-column: span 1;
    }

    .fh-analytics-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
