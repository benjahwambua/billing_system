<?php
/**
 * Newly created internet accounts must wait for the normal payment/provisioning workflow.
 * Submitted status is deliberately ignored to prevent direct activation.
 */
if (!function_exists('flexihubInitialInternetAccountStatus')) {
    function flexihubInitialInternetAccountStatus($requestedStatus = null)
    {
        return 'pending';
    }
}
