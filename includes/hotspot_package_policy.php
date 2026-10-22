<?php
/** Server-side allowlist for hotspot package lifecycle status. */
if (!function_exists('flexihubIsValidHotspotPackageStatus')) {
    function flexihubIsValidHotspotPackageStatus($status)
    {
        return in_array(strtolower(trim((string)$status)), ['active', 'inactive'], true);
    }
}
