<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/payment_gateway.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$token=trim((string)($_GET['token']??''));
$reference=trim((string)($_GET['reference']??''));
$phone=flexihubMpesaNormalizePhone($_GET['phone_number']??'');

if($token===''||$reference===''||$phone===''){
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Token and payment reference are required.']);
    exit;
}

global $conn;

$q=$conn->prepare("SELECT tenant_id,portal_name FROM hotspot_portal_settings WHERE public_token=? AND status='active' LIMIT 1");
if(!$q){
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Portal unavailable.']);
    exit;
}
$q->bind_param('s',$token);
$q->execute();
$portal=$q->get_result()->fetch_assoc();
$q->close();

if(!$portal){
    http_response_code(404);
    echo json_encode(['ok'=>false,'message'=>'Portal not found.']);
    exit;
}

$tenantId=(int)$portal['tenant_id'];

$q=$conn->prepare("SELECT s.status,s.expires_at,s.hotspot_username,s.phone_number,p.name package_name,p.duration_minutes
    FROM hotspot_sales s
    JOIN hotspot_packages p ON p.id=s.package_id AND p.tenant_id=s.tenant_id
    WHERE s.tenant_id=? AND s.reference=? LIMIT 1");
if(!$q){
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Payment status unavailable.']);
    exit;
}
$q->bind_param('is',$tenantId,$reference);
$q->execute();
$sale=$q->get_result()->fetch_assoc();
$q->close();

if(!$sale || !hash_equals((string)$sale['phone_number'],(string)$phone)){
    http_response_code(404);
    echo json_encode(['ok'=>false,'message'=>'Payment reference not found.']);
    exit;
}

$out=[
    'ok'=>true,
    'status'=>$sale['status'],
    'package'=>$sale['package_name'],
    'expires_at'=>$sale['expires_at']
];

if(in_array($sale['status'],['access_active','completed'],true)&&$sale['hotspot_username']){
    $out['username']=$sale['hotspot_username'];
}

echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
