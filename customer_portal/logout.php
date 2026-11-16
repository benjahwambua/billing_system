<?php
require_once __DIR__.'/../includes/auth.php';
if(session_status()===PHP_SESSION_ACTIVE){
 unset($_SESSION['customer_portal_authenticated'],$_SESSION['customer_portal_user_id'],$_SESSION['customer_portal_tenant_id'],$_SESSION['customer_portal_customer_id']);
 session_regenerate_id(true);
}
header('Location: login.php');exit;
