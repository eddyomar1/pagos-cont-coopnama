<?php
/*************************************************
 * Config + conexión + helpers (Residentes)
 *************************************************/
session_start();

/*********** 1) Conexión ***********/
$dbHost = 'localhost';
$dbName = 'u138076177_pw';
$dbUser = 'u138076177_chacharito';
$dbPass = '3spWifiPruev@';
$dsn    = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
try { $pdo = new PDO($dsn, $dbUser, $dbPass, $options); }
catch(Throwable $e){ http_response_code(500); exit("DB error: ".htmlspecialchars($e->getMessage())); }

/*********** 2) Helpers ***********/
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function body($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function required($v){ return isset($v) && trim((string)$v) !== ''; }
function digits_only($s){ return preg_replace('/\D+/','',$s); }
function format_cedula($d){ $d=digits_only($d); return strlen($d)===11?substr($d,0,3).'-'.substr($d,3,7).'-'.substr($d,10,1):$d; }
function cedula_valida($digits){
  $d = digits_only($digits); if(strlen($d)!==11) return false;
  $m=[1,2,1,2,1,2,1,2,1,2]; $s=0;
  for($i=0;$i<10;$i++){ $p=((int)$d[$i])*$m[$i]; if($p>=10)$p=intdiv($p,10)+($p%10); $s+=$p; }
  return ( (10-($s%10))%10 ) == (int)$d[10];
}
function toDecimal($v){
  $v = trim((string)$v); if($v==='') return null;
  $v = str_replace([' ', ','], ['', '.'], $v);
  return is_numeric($v) ? number_format((float)$v, 2, '.', '') : null;
}
function toDateOrNull($v){
  $v = trim((string)$v); if($v==='') return null;
  if(preg_match('~^\d{4}-\d{2}-\d{2}$~',$v)) return $v;
  if(preg_match('~^(\d{2})/(\d{2})/(\d{4})$~',$v,$m)) return "$m[3]-$m[2]-$m[1]";
  return null;
}

/**
 * Asegura la columna deuda_inicial en residentes (crea si falta).
 */
function ensure_deuda_inicial_column(PDO $pdo): bool{
  static $checked = false;
  static $exists  = false;
  if ($checked) return $exists;

  $checked = true;
  try{
    $st = $pdo->query("SHOW COLUMNS FROM residentes LIKE 'deuda_inicial'");
    $exists = (bool) ($st && $st->fetch());
    if (!$exists) {
      $pdo->exec("ALTER TABLE residentes ADD COLUMN deuda_inicial DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER telefono");
      $exists = true;
    }
  }catch(Throwable $e){
    $exists = false;
  }

  if (!defined('HAS_DEUDA_INICIAL')) {
    define('HAS_DEUDA_INICIAL', $exists);
  }
  return $exists;
}

/* Routing */
$action = $_GET['action'] ?? 'full';

ensure_deuda_inicial_column($pdo);
