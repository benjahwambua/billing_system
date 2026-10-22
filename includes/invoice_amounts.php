<?php
/** Shared invoice arithmetic for billing workflows. */
if (!function_exists('flexihubInvoiceOutstandingBalance')) {
    function flexihubInvoiceOutstandingBalance($total, $paid)
    {
        return max(0.0, (float)$total - (float)$paid);
    }
}
if (!function_exists('flexihubInvoiceIsFullyPaid')) {
    function flexihubInvoiceIsFullyPaid($total, $paid, $tolerance = 0.00001)
    {
        $total = (float)$total;
        $paid = (float)$paid;
        return $total <= 0 || $paid + max(0.0, (float)$tolerance) >= $total;
    }
}
if (!function_exists('flexihubInvoicePaymentStatus')) {
    function flexihubInvoicePaymentStatus($total, $paid, $tolerance = 0.00001)
    {
        $paid = (float)$paid;
        if ($paid <= 0) return 'unpaid';
        return flexihubInvoiceIsFullyPaid($total, $paid, $tolerance) ? 'paid' : 'partial';
    }
}
