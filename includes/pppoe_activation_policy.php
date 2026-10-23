<?php
/** Policy that prevents PPPoE service activation before its parent internet account is active. */
if (!function_exists('flexihubIsValidPppoeStatus')) {
    function flexihubIsValidPppoeStatus($status)
    {
        return in_array(strtolower(trim((string)$status)), ['active', 'inactive', 'suspended'], true);
    }
}
if (!function_exists('flexihubPppoeActivationError')) {
    function flexihubPppoeActivationError($internetAccountStatus, $requestedStatus)
    {
        $requestedStatus = strtolower(trim((string)$requestedStatus));
        if (!flexihubIsValidPppoeStatus($requestedStatus)) {
            return 'Select a valid PPPoE account status.';
        }
        if ($requestedStatus === 'active' && strtolower(trim((string)$internetAccountStatus)) !== 'active') {
            return 'The linked internet account must be active after payment and service activation before PPPoE can be enabled.';
        }
        return '';
    }
}
