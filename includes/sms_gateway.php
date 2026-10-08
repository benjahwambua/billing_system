<?php
if(!function_exists('flexihubSmsKey')){
 function flexihubSmsKey(){ $raw=getenv('FLEXIHUB_SMS_KEY'); return $raw!==false&&$raw!==''?$raw:null; }
}
if(!function_exists('flexihubSmsEncryptCredentials')){
 function flexihubSmsEncryptCredentials($json){$key=flexihubSmsKey();if(!$key)return false;if(json_decode($json,true)===null)return false;$iv=random_bytes(16);$cipher=openssl_encrypt($json,'AES-256-CBC',hash('sha256',$key,true),OPENSSL_RAW_DATA,$iv);return base64_encode($iv.$cipher);}
}
if(!function_exists('flexihubSmsDecryptCredentials')){
 function flexihubSmsDecryptCredentials($value){$key=flexihubSmsKey();$raw=base64_decode((string)$value,true);if(!$key||$raw===false||strlen($raw)<17)return null;$iv=substr($raw,0,16);$cipher=substr($raw,16);$plain=openssl_decrypt($cipher,'AES-256-CBC',hash('sha256',$key,true),OPENSSL_RAW_DATA,$iv);$data=json_decode((string)$plain,true);return is_array($data)?$data:null;}
}
if(!function_exists('flexihubSmsSendMessage')){
 function flexihubSmsSendMessage($tenantId,$gatewayId,$recipient,$message){
  global $conn;$q=$conn->prepare("SELECT * FROM sms_gateways WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");if(!$q)return ['success'=>false,'message'=>'Gateway unavailable.'];$q->bind_param('ii',$gatewayId,$tenantId);$q->execute();$g=$q->get_result()->fetch_assoc();$q->close();if(!$g)return ['success'=>false,'message'=>'Active gateway not found.'];
  $creds=flexihubSmsDecryptCredentials($g['credentials_encrypted']??'');$template=(string)($g['request_template']??'');$payload=['recipient'=>$recipient,'message'=>$message,'sender_id'=>$g['sender_id']??''];if($template!==''){foreach($payload as $k=>$v)$template=str_replace('{{'.$k.'}}',$v,$template);$body=$template;}else{$body=json_encode(['to'=>$recipient,'message'=>$message,'from'=>$g['sender_id']??'']);}
  $headers=['Content-Type: application/json'];$auth=$g['auth_type']??'none';if($auth==='bearer'&&isset($creds['token']))$headers[]='Authorization: Bearer '.$creds['token'];elseif($auth==='api_key'&&isset($creds['api_key']))$headers[]='X-API-Key: '.$creds['api_key'];elseif($auth==='basic'&&isset($creds['username'],$creds['password']))$headers[]='Authorization: Basic '.base64_encode($creds['username'].':'.$creds['password']);
  $ch=curl_init($g['endpoint_url']);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CUSTOMREQUEST=>strtoupper($g['api_method']??'POST'),CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body]);$response=curl_exec($ch);$curlError=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $ok=$curlError===''&&$http>=200&&$http<300;$providerId=null;$decoded=json_decode((string)$response,true);if(is_array($decoded))$providerId=$decoded['message_id']??($decoded['id']??null);
  $status=$ok?'sent':'failed';$reason=$ok?null:substr($curlError!==''?$curlError:('HTTP '.$http.': '.(string)$response),0,500);
  $i=$conn->prepare("INSERT INTO sms_messages (tenant_id,gateway_id,recipient,message,sender_id,status,provider_message_id,response_body,failure_reason,attempts,sent_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");if($i){$attempts=1;$sentAt=$ok?date('Y-m-d H:i:s'):null;$i->bind_param('iisssssssis',$tenantId,$gatewayId,$recipient,$message,$g['sender_id'],$status,$providerId,$response,$reason,$attempts,$sentAt);$i->execute();$i->close();}
  return ['success'=>$ok,'message'=>$ok?'SMS sent.':($reason?:'SMS delivery failed.'),'provider_message_id'=>$providerId];
 }
}
?>