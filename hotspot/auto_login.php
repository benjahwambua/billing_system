<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/payment_gateway.php';
header('Cache-Control: no-store');
$token=trim((string)($_GET['token']??''));
$reference=trim((string)($_GET['reference']??''));$phone=flexihubMpesaNormalizePhone($_GET['phone_number']??'');
if($token===''||$reference===''||$phone==='')die('Invalid hotspot access request.');
global $conn;
$q=$conn->prepare("SELECT tenant_id FROM hotspot_portal_settings WHERE public_token=? AND status='active' LIMIT 1");$q->bind_param('s',$token);$q->execute();$portal=$q->get_result()->fetch_assoc();$q->close();if(!$portal)die('Hotspot portal not found.');
$tenantId=(int)$portal['tenant_id'];
$q=$conn->prepare("SELECT s.*,p.name package_name,p.router_id,r.hotspot_login_url FROM hotspot_sales s JOIN hotspot_packages p ON p.id=s.package_id AND p.tenant_id=s.tenant_id LEFT JOIN mikrotik_routers r ON r.id=p.router_id AND r.tenant_id=p.tenant_id WHERE s.tenant_id=? AND s.reference=? LIMIT 1");$q->bind_param('is',$tenantId,$reference);$q->execute();$sale=$q->get_result()->fetch_assoc();$q->close();
if(!$sale||!hash_equals((string)$sale['phone_number'],(string)$phone)||!in_array($sale['status'],['access_active','completed'],true))die('Hotspot access is not active yet. Please return to the portal and check again.');
$url=trim((string)($sale['hotspot_login_url']??''));
if($url===''||!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('/^https?:$/i',(string)parse_url($url,PHP_URL_SCHEME)))die('Hotspot login URL is not configured for this package router.');
$password=flexihubGatewayDecrypt($sale['hotspot_password_encrypted']??null);if(!$password)die('Hotspot credentials are unavailable.');
$dst=trim((string)($_GET['dst']??''));
if($dst!==''&&!preg_match('/^https?:\/\//i',$dst))$dst='';
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connecting…</title></head>
<body><p>Connecting you to the internet…</p><form id="hotspot-login" method="post" action="<?=e($url)?>"><input type="hidden" name="username" value="<?=e($sale['hotspot_username'])?>"><input type="hidden" name="password" value="<?=e($password)?>"><?php if($dst!==''):?><input type="hidden" name="dst" value="<?=e($dst)?>"><?php endif;?></form>
<noscript>Please enable JavaScript to connect automatically, or use the hotspot login page.</noscript><script>document.getElementById('hotspot-login').submit();</script></body></html>