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

/* --- Helpers de vencimientos --- */

function fecha_larga_es($ymd){
  static $meses=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $ts = strtotime($ymd);
  if(!$ts) return $ymd;
  $dia = date('j', $ts);
  return $dia.' de '.$meses[(int)date('n',$ts)-1].' de '.date('Y',$ts);
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
    return $d->format('Y-m-' . DUE_DAY);
  }

  // Calcular a partir de hoy el siguiente día configurado
  $hoy = new DateTime('today');
  $venc = new DateTime($hoy->format('Y-m-' . DUE_DAY));
  if ($hoy >= $venc) {
    $venc->modify('+1 month');
  }
  return $venc->format('Y-m-d');
}

// Normaliza una fecha al día configurado (DUE_DAY) manteniendo año-mes
function align_due_day(string $ymd): string{
  if (!preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $ymd, $m)) return $ymd;
  return $m[1].'-'.$m[2].'-'.DUE_DAY;
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
  // Forzamos día configurable para los vencimientos
  $baseObj = new DateTime($base);
  $baseObj->setDate($baseObj->format('Y'), $baseObj->format('m'), (int)DUE_DAY);
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

  // 1) Construir conteo neto de meses pagados (YYYY-MM-DUE_DAY)
  //    - Un pago suma +1
  //    - Una anulación resta -1
  //    Compat: si la columna tipo no existe aún, caemos a detectar por total < 0.
  try{
    $st = $pdo->prepare("SELECT meses_pagados, total, tipo FROM pagos_residentes WHERE residente_id = ?");
    $st->execute([$residenteId]);
  }catch(Throwable $e){
    $st = $pdo->prepare("SELECT meses_pagados, total FROM pagos_residentes WHERE residente_id = ?");
    $st->execute([$residenteId]);
  }

  $pagados = []; // ymd => int (conteo neto)
  while($row = $st->fetch()){
    if (empty($row['meses_pagados'])) continue;
    $arr = json_decode($row['meses_pagados'], true);
    if (!is_array($arr)) continue;
    $delta = 1;
    if (isset($row['tipo']) && $row['tipo'] === 'anulacion') {
      $delta = -1;
    } elseif (isset($row['total']) && (float)$row['total'] < 0) {
      $delta = -1;
    }
    foreach($arr as $d){
      if (is_ymd($d)) {
        $norm = align_due_day($d);
        $pagados[$norm] = ($pagados[$norm] ?? 0) + $delta;
      }
    }
  }

  // 2) Determinar último vencimiento (día DUE_DAY) que ya pasó o es hoy
  $hoy = new DateTime('today');
  $currentDue = new DateTime($hoy->format('Y-m-' . DUE_DAY));
  if ($hoy < $currentDue) {
    // Todavía no se ha llegado al día configurado de este mes -> último vencimiento fue el mes pasado
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
    $ymd = $d->format('Y-m-' . DUE_DAY); // día configurado siempre
    if (($pagados[$ymd] ?? 0) <= 0) {
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

/**
 * Asegura columnas para anulación de pagos (tipo/anulado_de) en pagos_residentes.
 * Esto permite crear un registro inverso y mantener historial sin borrar.
 */
function ensure_pagos_anulacion_columns(PDO $pdo): bool{
  static $checked = false;
  static $ok      = false;
  if ($checked) return $ok;

  $checked = true;
  $ok = true;

  try{
    $st = $pdo->query("SHOW COLUMNS FROM pagos_residentes LIKE 'tipo'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE pagos_residentes ADD COLUMN tipo ENUM('pago','anulacion') NOT NULL DEFAULT 'pago'");
    }
  }catch(Throwable $e){
    $ok = false;
    app_log('No se pudo asegurar columna pagos_residentes.tipo: '.$e->getMessage());
  }

  try{
    $st = $pdo->query("SHOW COLUMNS FROM pagos_residentes LIKE 'anulado_de'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE pagos_residentes ADD COLUMN anulado_de INT NULL DEFAULT NULL");
      // Índice útil para buscar anulaciones rápido
      try { $pdo->exec("CREATE INDEX idx_pagos_anulado_de ON pagos_residentes(anulado_de)"); } catch(Throwable $e) {}
    }
  }catch(Throwable $e){
    $ok = false;
    app_log('No se pudo asegurar columna pagos_residentes.anulado_de: '.$e->getMessage());
  }

  return $ok;
}
