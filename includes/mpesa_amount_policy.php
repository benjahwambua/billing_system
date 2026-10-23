<?php
/**
 * Safaricom STK Push accepts integer KES amounts. Reject fractional amounts
 * before creating a transaction so the stored invoice amount cannot differ
 * from the amount requested from the customer.
 */
if (!function_exists('flexihubMpesaChargeAmount')) {
    function flexihubMpesaChargeAmount($amount): int
    {
        if (!is_numeric($amount) || !is_finite((float)$amount)) {
            throw new InvalidArgumentException('M-Pesa charge amount must be a valid number.');
        }

        $amount = (float)$amount;
        if ($amount <= 0 || abs($amount - round($amount)) > 0.000001) {
            throw new InvalidArgumentException('M-Pesa STK Push requires a positive whole-KES amount. Adjust the invoice amount before requesting payment.');
        }

        return (int)round($amount);
    }
}
