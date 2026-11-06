<?php
require dirname(__DIR__).'/app/bootstrap.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 csrf();
 if(isset($_POST['logout'])) {session_destroy();header('Location: /admin.php');exit;}
 if(time()<($_SESSION['blocked_until']??0)) $error='Terlalu banyak percobaan. Tunggu 5 menit.';
 elseif(getenv('ADMIN_PASSWORD') && hash_equals(getenv('ADMIN_PASSWORD'),(string)($_POST['password']??''))) {session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['failures']=0;header('Location: /admin.php');exit;}
 else {$_SESSION['failures']=($_SESSION['failures']??0)+1;if($_SESSION['failures']>=5)$_SESSION['blocked_until']=time()+300;$error='Kata sandi tidak sesuai.';}
}
$logged=$_SESSION['admin']??false;$rows=[];$total=0;$summary=[];$page=max(1,min(100000,(int)($_GET['page']??1)));$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');$skpd=substr(trim($_GET['skpd']??''),0,120);
foreach(['from','to'] as $field) { $v=$$field;if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)||!strtotime($v)) $$field=date('Y-m-d'); }
if($from>$to)[$from,$to]=[$to,$from];
if($logged) {
 try {
  $where=' WHERE tested_at >= ? AND tested_at < DATE_ADD(?, INTERVAL 1 DAY)';$params=[$from,$to];
  if($skpd!=='') {$where.=' AND skpd LIKE ?';$params[]='%'.$skpd.'%';}
  $q=db()->prepare('SELECT COUNT(*) total,AVG(download) download,AVG(upload) upload,AVG(ping) ping FROM results'.$where);$q->execute($params);$summary=$q->fetch(PDO::FETCH_ASSOC);$total=(int)$summary['total'];
  if(isset($_GET['export'])) {
   header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="speedtest-hst.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['ID','Waktu UTC','SKPD','Download Mbps','Upload Mbps','Ping ms','Jitter ms']);
   $q=db()->prepare('SELECT id,tested_at,skpd,download,upload,ping,jitter FROM results'.$where.' ORDER BY tested_at DESC LIMIT 10000');$q->execute($params);
   while($r=$q->fetch(PDO::FETCH_NUM)){foreach($r as &$v){if(preg_match('/^[=+@\-\t\r\n]/',(string)$v))$v="'".$v;}unset($v);fputcsv($out,$r);}exit;
  }
  $q=db()->prepare('SELECT * FROM results'.$where.' ORDER BY tested_at DESC LIMIT 50 OFFSET '.(($page-1)*50));$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 }catch(Throwable $e){error_log($e->getMessage());$error='Database belum dapat diakses. Periksa konfigurasi pemasangan.';}
}
function querylink(array $extra):string {return '?'.http_build_query(array_merge(['from'=>$GLOBALS['from'],'to'=>$GLOBALS['to'],'skpd'=>$GLOBALS['skpd']],$extra));}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard Admin · Speedtest HST</title><link rel="stylesheet" href="/style.css"></head><body><header><a class="brand" href="/"><img src="/logo.png" alt="Lambang HST"><span>SPEEDTEST HST<strong>Dashboard Pemantauan</strong></span></a><a href="/">← Halaman Tes</a></header><main class="admin">
<?php if(!$logged): ?><form class="login" method="post"><h1>Masuk administrator</h1><p>Riwayat dan laporan pengujian koneksi.</p><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><label for="password">Kata sandi</label><input id="password" type="password" name="password" required autocomplete="current-password"><button>Masuk Dashboard</button><p class="error" role="alert"><?=h($error)?></p></form>
<?php else: ?><div class="toolbar"><div><div class="eyebrow" style="text-align:left">MONITORING KONEKSI</div><h1>Riwayat pengujian</h1></div><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button class="secondary" name="logout">Keluar</button></form></div><p class="error" role="alert"><?=h($error)?></p><form class="filters"><label>Dari (UTC)<input type="date" name="from" value="<?=h($from)?>" required></label><label>Sampai (UTC)<input type="date" name="to" value="<?=h($to)?>" required></label><label>SKPD / pengguna<input name="skpd" value="<?=h($skpd)?>" maxlength="120" placeholder="Semua pengguna"></label><button>Terapkan</button><a href="<?=h(querylink(['export'=>1]))?>">↓ Ekspor CSV</a></form>
<div class="metrics"><div><span>Total tes</span><strong><?=$total?></strong><small>hasil tercatat</small></div><?php foreach(['download'=>'Download','upload'=>'Upload','ping'=>'Ping'] as $k=>$label): ?><div><span>Rata-rata <?=h($label)?></span><strong><?=isset($summary[$k])?number_format((float)$summary[$k],2):'—'?></strong><small><?=$k==='ping'?'ms':'Mbps'?></small></div><?php endforeach?></div>
<h2 style="font-size:14px;margin-top:30px">Download · 50 hasil pada halaman ini</h2><div class="bars" aria-label="Grafik kecepatan download hasil pada halaman ini"><?php $max=max(1,...array_column($rows,'download'));foreach(array_reverse($rows) as $r): ?><div style="height:<?=max(2,round(100*(float)$r['download']/$max))?>%" title="<?=h($r['skpd'].' · '.$r['download'].' Mbps')?>"></div><?php endforeach?></div><div class="table-wrap"><table><thead><tr><th>Waktu (UTC)</th><th>Pengguna / SKPD</th><th>Download</th><th>Upload</th><th>Ping</th><th>Jitter</th><th>ID</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h($r['tested_at'])?></td><td><?=h($r['skpd'])?></td><td><?=h($r['download'])?> Mbps</td><td><?=h($r['upload'])?> Mbps</td><td><?=h($r['ping'])?> ms</td><td><?=h($r['jitter'])?> ms</td><td><a class="mint" href="/result/<?=h($r['id'])?>"><?=h(substr($r['id'],0,8))?> ↗</a></td></tr><?php endforeach?><?php if(!$rows):?><tr><td colspan="7">Belum ada hasil pada rentang ini. Jalankan pengujian melalui halaman utama.</td></tr><?php endif?></tbody></table></div><div class="pager"><span>Halaman <?=$page?> · <?=$total?> hasil</span><span><?php if($page>1):?><a href="<?=h(querylink(['page'=>$page-1]))?>">← Sebelumnya</a> · <?php endif?><?php if($page*50<$total):?><a href="<?=h(querylink(['page'=>$page+1]))?>">Berikutnya →</a><?php endif?></span></div><p class="intro" style="text-align:left">Data dilaporkan oleh browser pengguna. Ekspor dibatasi 10.000 hasil per berkas; persempit tanggal bila diperlukan.</p><?php endif?></main></body></html>
