<?php
require dirname(__DIR__).'/app/bootstrap.php';
try {
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(['error'=>'Metode tidak diizinkan'],405);
 csrf();
 $action=$_GET['action']??'';
 if ($action==='begin') {
  if (time()-($_SESSION['last_test']??0)<15) reply(['error'=>'Tunggu sebentar sebelum mengulang tes.'],429);
  $input=json_decode(file_get_contents('php://input'),true);
  $skpd=trim((string)($input['skpd']??'Umum'));
  if(strlen($skpd)>120 || $skpd==='') reply(['error'=>'Nama SKPD maksimal 120 karakter.'],422);
  $server=null;foreach(servers() as $candidate)if($candidate['id']===($input['server']??'hst'))$server=$candidate;
  if(!$server)reply(['error'=>'Server tidak tersedia.'],422);
  $mode=($input['mode']??'multi')==='single'?'single':'multi';
  $_SESSION['last_test']=time();$_SESSION['test']=['id'=>bin2hex(random_bytes(16)),'skpd'=>$skpd,'start'=>time(),'server'=>$server,'mode'=>$mode,'client'=>clientInfo(),'owner'=>ownerHash()];
  reply(['id'=>$_SESSION['test']['id']]);
 }
 if ($action==='save') {
  $input=json_decode(file_get_contents('php://input'),true);
  $test=$_SESSION['test']??null;
  if(!$test || !hash_equals($test['id'],(string)($input['id']??'')) || time()-$test['start']>600) reply(['error'=>'Sesi tes kedaluwarsa. Jalankan tes ulang.'],422);
  $values=[];
  foreach(['download','upload','ping','jitter'] as $key){$v=$input[$key]??null;if(!is_numeric($v)||!is_finite((float)$v)||$v<0||$v>1000000) reply(['error'=>'Hasil tes tidak valid.'],422);$values[]=(float)$v;}
  $q=db()->prepare('INSERT INTO results (id,skpd,download,upload,ping,jitter,owner_hash,client_ip,isp,server_name,server_city,connection_mode) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
  $q->execute([$test['id'],$test['skpd'],...$values,$test['owner'],$test['client']['ip'],$test['client']['isp'],$test['server']['name'],$test['server']['city'],$test['mode']]);
  reply(['id'=>$test['id'],'saved'=>true,'url'=>'/result/'.$test['id']]);
 }
 reply(['error'=>'Tidak ditemukan'],404);
} catch(Throwable $e){error_log($e->getMessage());reply(['error'=>'Database belum tersedia. Hasil belum tersimpan; silakan coba simpan lagi.'],503);}
