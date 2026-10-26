<?php

/**
 * Validate an already-created receipt/ledger row before treating a payment
 * retry as idempotent. Only compare optional fields that exist in the schema.
 */
if (!function_exists('flexihubPaymentArtifactMatches')) {
    function flexihubPaymentArtifactMatches(array $row, array $columns, int $invoiceId, float $amount): bool
    {
        if ($invoiceId <= 0 || !is_finite($amount) || $amount <= 0) {
            return false;
        }

        if (in_array('invoice_id', $columns, true)) {
            if (!isset($row['invoice_id']) || !is_numeric($row['invoice_id']) ||
                (int) $row['invoice_id'] !== $invoiceId) {
                return false;
            }
        }

        if (in_array('amount', $columns, true)) {
            if (!isset($row['amount']) || !is_numeric($row['amount']) ||
                abs((float) $row['amount'] - $amount) > 0.01) {
                return false;
            }
        }

        return true;
    }
}
