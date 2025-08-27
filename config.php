<?php
// ---------- TIMEZONE (Bangladesh) ----------
date_default_timezone_set('Asia/Dhaka'); // PHP time -> Asia/Dhaka (GMT+6)

// ---------- DB CONFIG ----------
define('DB_HOST','localhost');
define('DB_PORT','3306');
define('DB_NAME','starshop_db');
define('DB_USER','root');
define('DB_PASS','');

// ---------- APP CONFIG ----------
define('APP_NAME','Sobuja11');
define('BASE_URL',''); // e.g., '/starshop' if in subfolder
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0777, true); }

// ---------- PDO ----------
function get_pdo(){
  static $pdo=null;
  if($pdo===null){
    $dsn='mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo=new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
    // MySQL session time zone = +06:00 (Asia/Dhaka)
    // নোট: কিছু সার্ভারে 'Asia/Dhaka' নাম কাজ নাও করতে পারে; তাই অফসেট ইউজ করা হলো।
    $pdo->exec("SET time_zone = '+06:00'");
  }
  return $pdo;
}

session_start();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money_bd($n){ return '৳'.number_format((float)$n, 0); }
function today(){ return date('Y-m-d'); }

// (optional) BD time-এ current datetime চাইলে
if (!function_exists('now_sql')) {
  function now_sql(){ return date('Y-m-d H:i:s'); } // Asia/Dhaka time
}

// ---------- settings helpers ----------
function setting($key, $default=''){
  $pdo = get_pdo();
  $stm = $pdo->prepare("SELECT value FROM settings WHERE `key`=? LIMIT 1");
  $stm->execute([$key]);
  $row = $stm->fetch();
  return $row ? $row['value'] : $default;
}

function save_setting($key, $value){
  $pdo = get_pdo();
  $pdo->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)")
      ->execute([$key, $value]);
}
?>
