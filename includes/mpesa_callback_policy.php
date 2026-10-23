<?php
/** Deterministic validation shared by both M-Pesa callback paths. */
if (!function_exists('flexihubMpesaCallbackMatchesTransaction')) {
    function flexihubMpesaCallbackMatchesTransaction($receipt, $paidAmount, $expectedAmount, $paidPhone = '', $expectedPhone = '', $tolerance = 0.01)
    {
        if (trim((string)$receipt) === '' || $paidAmount === null || !is_numeric($paidAmount) || !is_numeric($expectedAmount)) {
            return false;
        }

        // A successful STK callback must include the phone used to initiate the
        // request. Do not accept missing metadata as a match for a known number.
        $paidPhone = trim((string)$paidPhone);
        $expectedPhone = trim((string)$expectedPhone);
        if ($paidPhone === '' || $expectedPhone === '') {
            return false;
        }

        if (abs((float)$paidAmount - (float)$expectedAmount) > (max(0.0, (float)$tolerance) + 0.000001)) {
            return false;
        }

        return hash_equals($expectedPhone, $paidPhone);
    }
}
