<?php
require_once __DIR__ . '/functions.php';

if (!function_exists('flexihubGatewayEncrypt')) {
    function flexihubGatewayEncrypt($value) {
        $value=(string)$value;
        if($value==='') return null;
        $key=getenv('FLEXIHUB_APP_KEY') ?: '';
        if($key==='') throw new RuntimeException('FLEXIHUB_APP_KEY is not configured.');
        $key=hash('sha256',$key,true);
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false) throw new RuntimeException('Unable to encrypt gateway credential.');
        return base64_encode($iv.$tag.$cipher);
    }
}
if (!function_exists('flexihubGatewayDecrypt')) {
    function flexihubGatewayDecrypt($value) {
        if($value===null||$value==='') return null;
        $key=getenv('FLEXIHUB_APP_KEY') ?: '';
        if($key==='') throw new RuntimeException('FLEXIHUB_APP_KEY is not configured.');
        $raw=base64_decode($value,true);
        if($raw===false||strlen($raw)<28) throw new RuntimeException('Invalid encrypted gateway credential.');
        $key=hash('sha256',$key,true);
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if($plain===false) throw new RuntimeException('Unable to decrypt gateway credential.');
        return $plain;
    }
}
if (!function_exists('flexihubMpesaBaseUrl')) {
    function flexihubMpesaBaseUrl($environment) {
        return strtolower((string)$environment)==='production'?'https://api.safaricom.co.ke':'https://sandbox.safaricom.co.ke';
    }
}
if (!function_exists('flexihubMpesaNormalizePhone')) {
    function flexihubMpesaNormalizePhone($phone) {
        $phone=preg_replace('/[^0-9+]/','',(string)$phone);
        if(strpos($phone,'+')===0)$phone=substr($phone,1);
        if(strpos($phone,'0')===0&&strlen($phone)===10)$phone='254'.substr($phone,1);
        if(preg_match('/^7[0-9]{8}$/',$phone))$phone='254'.$phone;
        if(!preg_match('/^2547[0-9]{8}$/',$phone))throw new InvalidArgumentException('Invalid Kenyan M-Pesa phone number.');
        return $phone;
    }
}
if (!function_exists('flexihubMpesaRequest')) {
    function flexihubMpesaRequest($url,array $payload,$token) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);
        $body=curl_exec($ch);$error=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false)throw new RuntimeException('M-Pesa request failed: '.$error);
        $data=json_decode($body,true);
        if(!is_array($data))throw new RuntimeException('M-Pesa returned an invalid response.');
        if($http<200||$http>=300)throw new RuntimeException('M-Pesa request failed: '.($data['errorMessage']??$data['error_description']??('HTTP '.$http)));
        return $data;
    }
}
if (!function_exists('flexihubMpesaAccessToken')) {
    function flexihubMpesaAccessToken(array $gateway) {
        $key=flexihubGatewayDecrypt($gateway['consumer_key_encrypted']??null);
        $secret=flexihubGatewayDecrypt($gateway['consumer_secret_encrypted']??null);
        if(!$key||!$secret)throw new RuntimeException('M-Pesa consumer credentials are not configured.');
        $ch=curl_init(flexihubMpesaBaseUrl($gateway['environment']??'sandbox').'/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($key.':'.$secret),'Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>10]);
        $body=curl_exec($ch);$error=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false)throw new RuntimeException('Unable to obtain M-Pesa access token: '.$error);
        $data=json_decode($body,true);
        if($http<200||$http>=300||empty($data['access_token']))throw new RuntimeException('Unable to obtain M-Pesa access token: '.($data['error_description']??('HTTP '.$http)));
        return $data['access_token'];
    }
}
if (!function_exists('flexihubMpesaStkPush')) {
    function flexihubMpesaStkPush(array $gateway,$amount,$phone,$reference,$description,$callbackUrl) {
        $passkey=flexihubGatewayDecrypt($gateway['passkey_encrypted']??null);
        $shortcode=trim((string)($gateway['shortcode']??''));
        if(!$passkey||!$shortcode)throw new RuntimeException('M-Pesa shortcode and passkey are required for STK Push.');
        $phone=flexihubMpesaNormalizePhone($phone);
        $timestamp=date('YmdHis');
        $payload=[
            'BusinessShortCode'=>$shortcode,
            'Password'=>base64_encode($shortcode.$passkey.$timestamp),
            'Timestamp'=>$timestamp,
            'TransactionType'=>strtolower((string)($gateway['shortcode_type']??'paybill'))==='till'?'CustomerBuyGoodsOnline':'CustomerPayBillOnline',
            'Amount'=>max(1,(int)round((float)$amount)),
            'PartyA'=>$phone,'PartyB'=>$shortcode,'PhoneNumber'=>$phone,
            'CallBackURL'=>$callbackUrl,
            'AccountReference'=>substr((string)$reference,0,100),
            'TransactionDesc'=>substr((string)$description,0,255)
        ];
        return flexihubMpesaRequest(flexihubMpesaBaseUrl($gateway['environment']??'sandbox').'/mpesa/stkpush/v1/processrequest',$payload,flexihubMpesaAccessToken($gateway));
    }
}
if (!function_exists('flexihubCreateMpesaStkTransaction')) {
    function flexihubCreateMpesaStkTransaction($tenantId,$gatewayId,$amount,$phone,$flow,$reference,$description,$callbackUrl,$idempotencyKey,$hotspotSaleId=null) {
        global $conn;
        $tenantId=(int)$tenantId;$gatewayId=(int)$gatewayId;$amount=round((float)$amount,2);
        if($tenantId<=0||$gatewayId<=0||$amount<=0||trim((string)$flow)===''||trim((string)$idempotencyKey)==='')throw new InvalidArgumentException('Invalid M-Pesa transaction parameters.');
        $stmt=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE tenant_id=? AND idempotency_key=? LIMIT 1");
        if(!$stmt)throw new RuntimeException('Unable to inspect existing M-Pesa transaction.');
        $stmt->bind_param('is',$tenantId,$idempotencyKey);$stmt->execute();$existing=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($existing)return $existing;
        $stmt=$conn->prepare("SELECT * FROM payment_gateways WHERE id=? AND tenant_id=? AND provider='mpesa' AND status='active' LIMIT 1");
        if(!$stmt)throw new RuntimeException('Unable to load M-Pesa gateway.');
        $stmt->bind_param('ii',$gatewayId,$tenantId);$stmt->execute();$gateway=$stmt->get_result()->fetch_assoc();$stmt->close();
        if(!$gateway)throw new RuntimeException('Active tenant M-Pesa gateway not found.');
        $phone=flexihubMpesaNormalizePhone($phone);
        $stmt=$conn->prepare("INSERT INTO payment_gateway_transactions (tenant_id,gateway_id,provider,flow,status,amount,phone_number,account_reference,transaction_description,idempotency_key,hotspot_sale_id,initiated_at) VALUES (?,?,'mpesa',?,'initiated',?,?,?,?,?,?,NOW())");
        if(!$stmt)throw new RuntimeException('Unable to create M-Pesa transaction.');
        $stmt->bind_param('iisdssssi',$tenantId,$gatewayId,$flow,$amount,$phone,$reference,$description,$idempotencyKey,$hotspotSaleId);
        if(!$stmt->execute()){ $err=$stmt->error;$stmt->close();throw new RuntimeException('Unable to create M-Pesa transaction: '.$err); }
        $id=(int)$conn->insert_id;$stmt->close();
        try{$response=flexihubMpesaStkPush($gateway,$amount,$phone,$reference,$description,$callbackUrl);}
        catch(Throwable $e){$m=substr($e->getMessage(),0,500);$u=$conn->prepare("UPDATE payment_gateway_transactions SET status='failed',failure_reason=? WHERE id=? AND tenant_id=?");if($u){$u->bind_param('sii',$m,$id,$tenantId);$u->execute();$u->close();}throw $e;}
        $merchant=(string)($response['MerchantRequestID']??'');$checkout=(string)($response['CheckoutRequestID']??'');$code=isset($response['ResponseCode'])?(string)$response['ResponseCode']:null;$desc=isset($response['ResponseDescription'])?(string)$response['ResponseDescription']:null;$status=($checkout!==''&&$code==='0')?'pending':'failed';$json=json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $u=$conn->prepare("UPDATE payment_gateway_transactions SET status=?,merchant_request_id=?,checkout_request_id=?,result_code=?,result_description=?,request_payload=?,expires_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=? AND tenant_id=?");
        if(!$u)throw new RuntimeException('Unable to save M-Pesa provider response.');
        $u->bind_param('ssssssii',$status,$merchant,$checkout,$code,$desc,$json,$id,$tenantId);$u->execute();$u->close();
        $q=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? AND tenant_id=? LIMIT 1");if(!$q)throw new RuntimeException('Unable to reload M-Pesa transaction.');$q->bind_param('ii',$id,$tenantId);$q->execute();$fresh=$q->get_result()->fetch_assoc();$q->close();
        return $fresh?:[];
    }
}
