<?php
if(!function_exists('flexihubSmsKey')){
 function flexihubSmsKey(){ $raw=getenv('FLEXIHUB_SMS_KEY'); return $raw!==false&&$raw!==''?$raw:null; }
}
if(!function_exists('flexihubSmsIpInCidr')){
 function flexihubSmsIpInCidr($ip,$cidr){
  $parts=explode('/',(string)$cidr,2);$network=inet_pton($parts[0]??'');$address=inet_pton((string)$ip);
  if($network===false||$address===false||strlen($network)!==strlen($address))return false;
  $bits=isset($parts[1])?(int)$parts[1]:(strlen($network)*8);
  if($bits<0||$bits>strlen($network)*8)return false;
  $whole=intdiv($bits,8);$remaining=$bits%8;
  if($whole>0&&substr($address,0,$whole)!==substr($network,0,$whole))return false;
  if($remaining>0){$mask=(0xff<<(8-$remaining))&0xff;if((ord($address[$whole])&$mask)!==(ord($network[$whole])&$mask))return false;}
  return true;
 }
}
if(!function_exists('flexihubSmsIpIsPublic')){
 function flexihubSmsIpIsPublic($ip){
  if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
  $blocked=[
   '0.0.0.0/8','100.64.0.0/10','192.0.0.0/24','192.0.2.0/24','192.88.99.0/24',
   '198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4',
   '100::/64','2001:db8::/32','fc00::/7','fe80::/10','ff00::/8','::/128','::1/128'
  ];
  foreach($blocked as $cidr)if(flexihubSmsIpInCidr($ip,$cidr))return false;
  return true;
 }
}
if(!function_exists('flexihubSmsEndpointPolicy')){
 function flexihubSmsEndpointPolicy($url){
  $parts=parse_url(trim((string)$url));
  if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])){
   return ['valid'=>false,'message'=>'SMS gateway endpoints must be HTTPS URLs without embedded credentials or fragments.','resolve'=>null];
  }
  $host=strtolower(rtrim((string)$parts['host'],'.'));if(str_starts_with($host,'[')&&str_ends_with($host,']'))$host=substr($host,1,-1);
  $port=(int)($parts['port']??443);
  if($host===''||$port!==443||$host==='localhost'||str_ends_with($host,'.localhost')||str_ends_with($host,'.local')||str_ends_with($host,'.internal')||str_ends_with($host,'.test')){
   return ['valid'=>false,'message'=>'SMS gateway endpoint host or port is not permitted. Use a public HTTPS endpoint on port 443.','resolve'=>null];
  }
  $ips=[];
  if(filter_var($host,FILTER_VALIDATE_IP)){
   $ips[]=$host;
  }else{
   $records=@dns_get_record($host,DNS_A|DNS_AAAA);
   if(is_array($records))foreach($records as $record){
    if(!empty($record['ip']))$ips[]=$record['ip'];
    if(!empty($record['ipv6']))$ips[]=$record['ipv6'];
   }
  }
  $ips=array_values(array_unique($ips));
  if(!$ips)return ['valid'=>false,'message'=>'SMS gateway hostname could not be resolved to a public IP address.','resolve'=>null];
  foreach($ips as $ip){
   if(!flexihubSmsIpIsPublic($ip)){
    return ['valid'=>false,'message'=>'SMS gateway endpoint resolves to a private or reserved IP address.','resolve'=>null];
   }
  }
  $resolve=null;
  if(!filter_var($host,FILTER_VALIDATE_IP)){
   $ip=$ips[0];
   $resolve=$host.':443:'.(strpos($ip,':')!==false?'['.$ip.']':$ip);
  }
  return ['valid'=>true,'message'=>'','resolve'=>$resolve];
 }
}
if(!function_exists('flexihubSmsEncryptCredentials')){
 function flexihubSmsEncryptCredentials($json){$key=flexihubSmsKey();if(!$key)return false;if(json_decode($json,true)===null)return false;$iv=random_bytes(16);$cipher=openssl_encrypt($json,'AES-256-CBC',hash('sha256',$key,true),OPENSSL_RAW_DATA,$iv);return base64_encode($iv.$cipher);}
}
if(!function_exists('flexihubSmsDecryptCredentials')){
 function flexihubSmsDecryptCredentials($value){$key=flexihubSmsKey();$raw=base64_decode((string)$value,true);if(!$key||$raw===false||strlen($raw)<17)return null;$iv=substr($raw,0,16);$cipher=substr($raw,16);$plain=openssl_decrypt($cipher,'AES-256-CBC',hash('sha256',$key,true),OPENSSL_RAW_DATA,$iv);$data=json_decode((string)$plain,true);return is_array($data)?$data:null;}
}
if(!function_exists('flexihubSmsSendMessage')){
 function flexihubSmsSendMessage($tenantId,$gatewayId,$recipient,$message,$existingMessageId=0,$attemptNumber=1){
  global $conn;$q=$conn->prepare("SELECT * FROM sms_gateways WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");if(!$q)return ['success'=>false,'message'=>'Gateway unavailable.'];$q->bind_param('ii',$gatewayId,$tenantId);$q->execute();$g=$q->get_result()->fetch_assoc();$q->close();if(!$g)return ['success'=>false,'message'=>'Active gateway not found.'];
  $creds=flexihubSmsDecryptCredentials($g['credentials_encrypted']??'');$template=(string)($g['request_template']??'');$payload=['recipient'=>$recipient,'message'=>$message,'sender_id'=>$g['sender_id']??''];if($template!==''){foreach($payload as $k=>$v)$template=str_replace('{{'.$k.'}}',$v,$template);$body=$template;}else{$body=json_encode(['to'=>$recipient,'message'=>$message,'from'=>$g['sender_id']??'']);}
  $headers=['Content-Type: application/json'];$auth=$g['auth_type']??'none';if($auth==='bearer'&&isset($creds['token']))$headers[]='Authorization: Bearer '.$creds['token'];elseif($auth==='api_key'&&isset($creds['api_key']))$headers[]='X-API-Key: '.$creds['api_key'];elseif($auth==='basic'&&isset($creds['username'],$creds['password']))$headers[]='Authorization: Basic '.base64_encode($creds['username'].':'.$creds['password']);
  $endpointPolicy=flexihubSmsEndpointPolicy($g['endpoint_url']??'');if(!$endpointPolicy['valid']){$response='';$curlError=$endpointPolicy['message'];$http=0;}else{$ch=curl_init($g['endpoint_url']);$curlOptions=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CUSTOMREQUEST=>strtoupper($g['api_method']??'POST'),CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body];if(!empty($endpointPolicy['resolve']))$curlOptions[CURLOPT_RESOLVE]=[$endpointPolicy['resolve']];curl_setopt_array($ch,$curlOptions);$response=curl_exec($ch);$curlError=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);}
  $ok=$curlError===''&&$http>=200&&$http<300;$providerId=null;$decoded=json_decode((string)$response,true);if(is_array($decoded))$providerId=$decoded['message_id']??($decoded['id']??null);
  $status=$ok?'sent':'failed';$reason=$ok?null:substr($curlError!==''?$curlError:('HTTP '.$http.': '.(string)$response),0,500);
  $attempts=max(1,(int)$attemptNumber);$sentAt=$ok?date('Y-m-d H:i:s'):null;if($existingMessageId>0){$i=$conn->prepare("UPDATE sms_messages SET status=?,provider_message_id=?,response_body=?,failure_reason=?,attempts=?,sent_at=? WHERE id=? AND tenant_id=?");if($i){$i->bind_param('ssssissi',$status,$providerId,$response,$reason,$attempts,$sentAt,$existingMessageId,$tenantId);$i->execute();$i->close();}}else{$i=$conn->prepare("INSERT INTO sms_messages (tenant_id,gateway_id,recipient,message,sender_id,status,provider_message_id,response_body,failure_reason,attempts,sent_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");if($i){$i->bind_param('iisssssssis',$tenantId,$gatewayId,$recipient,$message,$g['sender_id'],$status,$providerId,$response,$reason,$attempts,$sentAt);$i->execute();$i->close();}}
  return ['success'=>$ok,'message'=>$ok?'SMS sent.':($reason?:'SMS delivery failed.'),'provider_message_id'=>$providerId];
 }
}
?>