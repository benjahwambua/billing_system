<?php
// CLI worker for Flexihub's own SaaS subscription billing.
// Run after the database migration 023_flexihub_saas_subscriptions.sql.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_billing.php';

$lockFile=sys_get_temp_dir().'/flexihub_platform_billing.lock';
$fp=fopen($lockFile,'c');
if(!$fp || !flock($fp,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Another platform billing worker is running.\n");exit(2);}
try{
    $result=flexihubProcessPlatformBilling();
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(!empty($result['errors'])?2:0);
}finally{flock($fp,LOCK_UN);fclose($fp);}
