<?php
// index/helpers.php – funciones comunes

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function body($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function required($v){ return isset($v) && trim((string)$v) !== ''; }
function digits_only($s){ return preg_replace('/\D+/','',$s); }

function format_cedula($d){
  $d = digits_only($d);
  return strlen($d)===11 ? substr($d,0,3).'-'.substr($d,3,7).'-'.substr($d,10,1) : $d;
}

function cedula_valida($digits){
  $d = digits_only($digits);
  if(strlen($d)!==11) return false;
  $m=[1,2,1,2,1,2,1,2,1,2]; $s=0;
  for($i=0;$i<10;$i++){
    $p=$d[$i]*$m[$i];
    if($p>=10)$p=intdiv($p,10)+($p%10);
    $s+=$p;
  }
  return ((10-($s%10))%10) == $d[10];
}

function toDecimal($v){
  $v = trim((string)$v); if($v==='') return null;
  $v = str_replace([' ', ','], ['', '.'], $v);
  return is_numeric($v) ? number_format((float)$v, 2, '.', '') : null;
}

function toDateOrNull($v){
  $v = trim((string)$v); if($v==='') return null;
  if(preg_match('~^\d{4}-\d{2}-\d{2}$~',$v)) return $v;
  if(preg_match('~^(\d{2})/(\d{2})/(\d{4})$~',$v,$m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
  return null;
}

/**
 * Logging sencillo para depurar errores en producción.
 * Escribe en contactos/logs/app.log (crea la carpeta si falta).
 */
function app_log(string $message): void{
  $dir = __DIR__ . '/../logs';
  $file = $dir . '/app.log';
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
  $timestamp = date('Y-m-d H:i:s');
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $line = "[$timestamp] $uri $message\n";
  @file_put_contents($file, $line, FILE_APPEND);
}

function is_ymd($s){
  return is_string($s) && preg_match('~^\d{4}-\d{2}-\d{2}$~',$s);
}

/* --- Helpers de vencimientos (día 25) --- */

function fecha_larga_es($ymd){
  static $meses=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $ts = strtotime($ymd);
  if(!$ts) return $ymd;
  // NOTA: ahora el vencimiento es el 25 de cada mes
  return '25 de '.$meses[(int)date('n',$ts)-1].' de '.date('Y',$ts);
}

/**
 * Próximo vencimiento (día 25) a partir de hoy.
 */
function proximo_vencimiento($fecha_x_pagar = null){
  // Si nos pasan la fecha del último vencimiento cubierto (YYYY-MM-DD),
  // el próximo vencimiento es simplemente ese mes + 1.
  if (!empty($fecha_x_pagar) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $fecha_x_pagar)) {
    $d = new DateTime($fecha_x_pagar);
    $d->modify('+1 month');
    return $d->format('Y-m-05');
  }

  // Calcular a partir de hoy el siguiente día 5
  $hoy = new DateTime('today');
  $venc = new DateTime($hoy->format('Y-m-05'));
  if ($hoy >= $venc) {
    $venc->modify('+1 month');
  }
  return $venc->format('Y-m-d');
}


/**
 * Devuelve un array de YYYY-MM-DD (día 25) con las cuotas pendientes
 * para un residente, desde BASE_DUE hasta el último vencimiento <= hoy,
 * teniendo en cuenta todos los meses YA PAGADOS en pagos_residentes
 * (incluyendo pagos adelantados).
 */
function cuotas_pendientes_residente(PDO $pdo, int $residenteId, ?string $base=null){
  // Fecha base: preferimos la fecha_x_pagar del residente; si no, BASE_DUE.
  try{
    $stBase = $pdo->prepare("SELECT fecha_x_pagar FROM residentes WHERE id = ? LIMIT 1");
    $stBase->execute([$residenteId]);
    $fechaBaseRow = $stBase->fetchColumn();
  }catch(Throwable $e){
    $fechaBaseRow = null;
  }
  if ($fechaBaseRow && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)$fechaBaseRow)) {
    $base = $fechaBaseRow;
  } elseif ($base === null) {
    $base = BASE_DUE;
  }
  // Forzamos día 5 para los vencimientos
  $baseObj = new DateTime($base);
  $baseObj->setDate($baseObj->format('Y'), $baseObj->format('m'), 5);
  $base = $baseObj->format('Y-m-d');

  // Si está exonerado, no generar pendientes hasta que se reactive
  try{
    $chk = $pdo->prepare("SELECT exonerado FROM residentes WHERE id = ? LIMIT 1");
    $chk->execute([$residenteId]);
    $ex = $chk->fetchColumn();
    if ($ex) {
      return [];
    }
  }catch(Throwable $e){
    // Si falla, continuamos con la lógica normal para no bloquear el flujo.
  }

  // 1) Construir set de meses pagados (YYYY-MM-DD)
  $sql = "SELECT meses_pagados FROM pagos_residentes WHERE residente_id = ?";
  $st = $pdo->prepare($sql);
  $st->execute([$residenteId]);

  $pagados = [];
  while($row = $st->fetch()){
    if (empty($row['meses_pagados'])) continue;
    $arr = json_decode($row['meses_pagados'], true);
    if (!is_array($arr)) continue;
    foreach($arr as $d){
      if (is_ymd($d)) {
        $pagados[$d] = true; // set (evitamos duplicados)
      }
    }
  }

  // 2) Determinar último vencimiento (día 25) que ya pasó o es hoy
  $hoy = new DateTime('today');
  $currentDue = new DateTime($hoy->format('Y-m-05'));
  if ($hoy < $currentDue) {
    // Todavía no se ha llegado al 5 de este mes -> último vencimiento fue el mes pasado
    $ultimo_venc = (clone $currentDue)->modify('-1 month');
  } else {
    $ultimo_venc = $currentDue;
  }

  // Si BASE_DUE está en el futuro, no hay nada pendiente
  $inicio = new DateTime($base);
  if ($inicio > $ultimo_venc) {
    return [];
  }

  // 3) Recorrer meses desde BASE_DUE (o fecha_x_pagar) hasta último vencimiento,
  //    agregando solo los que NO estén pagados
  $pendientes = [];
  for ($d = clone $inicio; $d <= $ultimo_venc; $d->modify('+1 month')) {
    $ymd = $d->format('Y-m-05'); // día 5 siempre
    if (!isset($pagados[$ymd])) {
      $pendientes[] = $ymd;
    }
  }

  return $pendientes;
}

/**
 * Garantiza que exista la columna deuda_inicial; si no, intenta crearla.
 * Devuelve true si la columna existe tras la verificación.
 */
function ensure_deuda_inicial_column(PDO $pdo): bool{
  static $checked = false;
  static $exists  = false;
  if ($checked) {
    return $exists;
  }

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

/**
 * Asegura columnas de exoneración (exonerado y exonerado_desde) en residentes.
 */
function ensure_exonerado_columns(PDO $pdo): bool{
  static $checked = false;
  static $ok      = false;
  if ($checked) {
    return $ok;
  }

  $checked = true;
  $ok = true;
  try{
    $st = $pdo->query("SHOW COLUMNS FROM residentes LIKE 'exonerado'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE residentes ADD COLUMN exonerado TINYINT(1) NOT NULL DEFAULT 0 AFTER no_recurrente");
    }
  }catch(Throwable $e){
    $ok = false;
  }

  try{
    $st = $pdo->query("SHOW COLUMNS FROM residentes LIKE 'exonerado_desde'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE residentes ADD COLUMN exonerado_desde DATETIME NULL DEFAULT NULL AFTER exonerado");
    }
  }catch(Throwable $e){
    $ok = false;
  }

  if (!defined('HAS_EXONERADO')) {
    define('HAS_EXONERADO', $ok);
  }
  return $ok;
}
