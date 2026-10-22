<?php
/**
 * Deterministic retry policy for service activation queue jobs.
 * Delays increase by five minutes per attempt and cap at one hour.
 */
if (!function_exists('flexihubServiceActivationRetryDecision')) {
    function flexihubServiceActivationRetryDecision($attempts, $maxAttempts = 5)
    {
        $attempts = max(0, (int)$attempts);
        $maxAttempts = max(1, (int)$maxAttempts);
        if ($attempts >= $maxAttempts) {
            return ['status' => 'failed', 'delay_minutes' => 0];
        }
        return [
            'status' => 'pending',
            'delay_minutes' => min($attempts * 5, 60),
        ];
    }
}
