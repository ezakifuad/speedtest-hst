<?php
declare(strict_types=1);
ini_set('session.cookie_secure', getenv('APP_HTTPS') === '1' ? '1' : '0');
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));
function db(): PDO {
 static $db;
 return $db ??= new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function reply(array $data,int $status=200): never { http_response_code($status);header('Content-Type: application/json');echo json_encode($data);exit; }
function h(string $v): string {return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function csrf(): void {if (!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??'')) reply(['error'=>'Sesi tidak valid. Muat ulang halaman.'],403);}

$historyToken=$_COOKIE['hst_history']??'';
if(!preg_match('/^[a-f0-9]{64}$/',$historyToken)){
 $historyToken=bin2hex(random_bytes(32));
 setcookie('hst_history',$historyToken,['expires'=>time()+31536000,'path'=>'/','secure'=>getenv('APP_HTTPS')==='1','httponly'=>true,'samesite'=>'Lax']);
}
function ownerHash():string {return hash('sha256',$GLOBALS['historyToken']);}
function servers():array {return json_decode(file_get_contents(__DIR__.'/servers.json'),true,512,JSON_THROW_ON_ERROR);}
function clientInfo():array {
 $ip=$_SERVER['REMOTE_ADDR']??'';
 // Trust the proxy-set header only from explicitly configured proxy addresses.
 $trusted=array_filter(explode(',',getenv('TRUSTED_PROXIES')?:''));
 if(in_array($ip,$trusted,true)&&filter_var($_SERVER['HTTP_X_REAL_IP']??'',FILTER_VALIDATE_IP))$ip=$_SERVER['HTTP_X_REAL_IP'];
 $isp='Penyedia tidak teridentifikasi';
 if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))$isp='Jaringan lokal';
 else {try{$root=is_dir(dirname(__DIR__).'/html')?dirname(__DIR__).'/html':dirname(__DIR__).'/public';require_once $root.'/vendor/backend/geoip2.phar';$reader=new MaxMind\Db\Reader($root.'/vendor/backend/country_asn.mmdb');$record=$reader->get($ip);$isp=$record['as_name']??$isp;$reader->close();}catch(Throwable $e){error_log('ISP lookup unavailable');}}
 return ['ip'=>$ip,'isp'=>substr($isp,0,200)];
}
function maskedIp(?string $ip):string {
 if(!$ip)return 'Tidak tersedia';
 if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))return preg_replace('/\.\d+$/','.xxx',$ip);
 return implode(':',array_slice(explode(':',$ip),0,3)).':…';
}
