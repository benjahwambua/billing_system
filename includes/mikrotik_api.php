<?php
/**
 * Minimal RouterOS API client for Flexihub.
 * Uses the native PHP socket extension; no third-party library required.
 */
if (!function_exists('flexihubRosEncodeLength')) {
    function flexihubRosEncodeLength($length) {
        $length=(int)$length;
        if($length < 0x80) return chr($length);
        if($length < 0x4000) { $length |= 0x8000; return chr(($length>>8)&0xff).chr($length&0xff); }
        if($length < 0x200000) { $length |= 0xC00000; return chr(($length>>16)&0xff).chr(($length>>8)&0xff).chr($length&0xff); }
        if($length < 0x10000000) { $length |= 0xE0000000; return chr(($length>>24)&0xff).chr(($length>>16)&0xff).chr(($length>>8)&0xff).chr($length&0xff); }
        return chr(0xF0).pack('N',$length);
    }
}
if (!function_exists('flexihubRosReadLength')) {
    function flexihubRosReadLength($fp) {
        $c=fread($fp,1); if($c===false || $c==='') throw new Exception('RouterOS connection closed.');
        $v=ord($c);
        if($v < 0x80) return $v;
        if(($v & 0xC0) === 0x80) return (($v & 0x3f)<<8)|ord(fread($fp,1));
        if(($v & 0xE0) === 0xC0) { $b=unpack('C3',fread($fp,3)); return (($v&0x1f)<<16)|($b[1]<<8)|$b[2]; }
        if(($v & 0xF0) === 0xE0) { $b=unpack('C3',fread($fp,3)); return (($v&0x0f)<<24)|($b[1]<<16)|($b[2]<<8)|$b[3]; }
        if($v === 0xF0) { $b=fread($fp,4); if(strlen($b)!==4) throw new Exception('Invalid RouterOS length.'); $u=unpack('N',$b); return $u[1]; }
        throw new Exception('Invalid RouterOS API length.');
    }
}
if (!function_exists('flexihubRosWriteSentence')) {
    function flexihubRosWriteSentence($fp,array $words) {
        foreach($words as $word) {
            $word=(string)$word;
            fwrite($fp,flexihubRosEncodeLength(strlen($word)).$word);
        }
        fwrite($fp,chr(0));
    }
}
if (!function_exists('flexihubRosReadSentence')) {
    function flexihubRosReadSentence($fp) {
        $words=[];
        while(true) {
            $len=flexihubRosReadLength($fp);
            if($len===0) return $words;
            $word='';
            while(strlen($word)<$len) {
                $chunk=fread($fp,$len-strlen($word));
                if($chunk===false || $chunk==='') throw new Exception('RouterOS connection closed while reading.');
                $word.=$chunk;
            }
            $words[]=$word;
        }
    }
}
if (!function_exists('flexihubRosParseSentence')) {
    function flexihubRosParseSentence(array $words) {
        $type=$words[0]??'';
        $data=['!type'=>$type];
        foreach(array_slice($words,1) as $word) {
            if($word!=='' && $word[0]==='=') {
                $eq=strpos($word,'=',1);
                if($eq!==false) $data[substr($word,1,$eq-1)]=substr($word,$eq+1);
            } elseif($word!=='' && $word[0]==='!') {
                $data[$word]=true;
            }
        }
        return $data;
    }
}
if (!class_exists('FlexihubRouterOS')) {
    class FlexihubRouterOS {
        private $fp=null;
        private $timeout=8;
        public function __construct($host,$username,$password,$port=8728,$timeout=8) {
            $host=trim((string)$host); $username=(string)$username; $password=(string)$password;
            if($host==='' || $username==='') throw new Exception('Router host and username are required.');
            $port=(int)$port ?: 8728; $this->timeout=max(2,(int)$timeout);
            $errno=0;$errstr='';
            $this->fp=@fsockopen($host,$port,$errno,$errstr,$this->timeout);
            if(!$this->fp) throw new Exception('Unable to connect to router: '.($errstr?:'connection failed').'.');
            stream_set_timeout($this->fp,$this->timeout);
            flexihubRosWriteSentence($this->fp,['/login','=name='.$username,'=password='.$password]);
            $reply=[];
            for($i=0;$i<5;$i++){ $s=flexihubRosReadSentence($this->fp); if(!$s) continue; $reply[]=flexihubRosParseSentence($s); if(($s[0]??'')==='!done') break; }
            $failed=false;$message='RouterOS login failed.';
            foreach($reply as $r){ if(isset($r['=message'])) $message=$r['=message']; if(isset($r['!trap'])||isset($r['!fatal'])) $failed=true; }
            if($failed || !array_filter($reply,fn($r)=>($r['!type']??'')==='!done')) { $this->close(); throw new Exception($message); }
        }
        public function command(array $words) {
            if(!$this->fp) throw new Exception('Router connection is not open.');
            flexihubRosWriteSentence($this->fp,$words); $rows=[];$error='';
            while(true) {
                $s=flexihubRosReadSentence($this->fp); if(!$s) continue;
                $row=flexihubRosParseSentence($s); $rows[]=$row;
                if(($row['!type']??'')==='!trap' || ($row['!type']??'')==='!fatal') $error=$row['message']??$row['=message']??'RouterOS command failed.';
                if(($row['!type']??'')==='!done') break;
            }
            if($error) throw new Exception($error);
            return $rows;
        }
        public function findPppSecret($username) {
            $rows=$this->command(['/ppp/secret/print','=.proplist=.id,name,disabled','=name='.$username]);
            foreach($rows as $r) if(($r['!type']??'')==='!re' && ($r['name']??'')===$username) return $r;
            return null;
        }
        public function setPppSecretDisabled($secretId,$disabled) {
            $this->command(['/ppp/secret/set','=.id='.$secretId,'=disabled='.($disabled?'yes':'no')]);
            return true;
        }
        public function disconnectPppActive($username) {
            $rows=$this->command(['/ppp/active/print','=.proplist=.id,name','=name='.$username]);
            $count=0;
            foreach($rows as $r) if(($r['!type']??'')==='!re' && ($r['name']??'')===$username && !empty($r['.id'])) {
                $this->command(['/ppp/active/remove','=.id='.$r['.id']]); $count++;
            }
            return $count;
        }
        public function findHotspotUser($username) {
            $rows=$this->command(['/ip/hotspot/user/print','=.proplist=.id,name,disabled','=name='.$username]);
            foreach($rows as $r) if(($r['!type']??'')==='!re' && ($r['name']??'')===$username) return $r;
            return null;
        }
        public function setHotspotUserDisabled($username,$disabled=true) {
            $user=$this->findHotspotUser($username);
            if(!$user||empty($user['.id'])) return false;
            $this->command(['/ip/hotspot/user/set','=.id='.$user['.id'],'=disabled='.($disabled?'yes':'no')]);
            return true;
        }
        public function disconnectHotspotActive($username) {
            $rows=$this->command(['/ip/hotspot/active/print','=.proplist=.id,user','=user='.$username]);
            $count=0;
            foreach($rows as $r) if(($r['!type']??'')==='!re' && ($r['user']??'')===$username && !empty($r['.id'])) {
                $this->command(['/ip/hotspot/active/remove','=.id='.$r['.id']]); $count++;
            }
            return $count;
        }
        public function close() { if(is_resource($this->fp)) fclose($this->fp); $this->fp=null; }
        public function __destruct(){ $this->close(); }
    }
}
?>