<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
 $db=new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->query('SELECT owner_hash FROM results LIMIT 1');
 echo '{"status":"ok"}';
} catch(Throwable $e) {http_response_code(503);echo '{"status":"unavailable"}';}
