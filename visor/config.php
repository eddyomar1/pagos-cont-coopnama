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
const DEV_ACCESS_KEY = 'coopnama-dev';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function body($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function required($v){ return isset($v) && trim((string)$v) !== ''; }
function digits_only($s){ return preg_replace('/\D+/','',$s); }
function format_cedula($d){ $d=digits_only($d); return strlen($d)===11?substr($d,0,3).'-'.substr($d,3,7).'-'.substr($d,10,1):$d; }
function is_ymd($d){ return (bool)preg_match('~^\d{4}-\d{2}-\d{2}$~',(string)$d); }
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

/**
 * Asegura columnas de exoneración en residentes (crea si faltan).
 */
function ensure_exonerado_columns(PDO $pdo): bool{
  static $checked = false;
  static $ok      = false;
  if ($checked) return $ok;

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

function ensure_residente_cuota_mensual_column(PDO $pdo): bool{
  static $checked = false;
  static $ok      = false;
  if ($checked) return $ok;

  $checked = true;
  $ok = true;
  try{
    $st = $pdo->query("SHOW COLUMNS FROM residentes LIKE 'cuota_mensual'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE residentes ADD COLUMN cuota_mensual DECIMAL(10,2) NULL DEFAULT NULL AFTER deuda_extra");
    }
  }catch(Throwable $e){
    $ok = false;
  }

  if (!defined('HAS_CUOTA_MENSUAL')) {
    define('HAS_CUOTA_MENSUAL', $ok);
  }
  return $ok;
}

/**
 * Asegura columnas para anulación de pagos (tipo/anulado_de) en pagos_residentes.
 */
function ensure_pagos_anulacion_columns_local(PDO $pdo): bool{
  $ok = true;
  try{
    $st = $pdo->query("SHOW COLUMNS FROM pagos_residentes LIKE 'tipo'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE pagos_residentes ADD COLUMN tipo ENUM('pago','anulacion') NOT NULL DEFAULT 'pago'");
    }
  }catch(Throwable $e){ $ok = false; }

  try{
    $st = $pdo->query("SHOW COLUMNS FROM pagos_residentes LIKE 'anulado_de'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE pagos_residentes ADD COLUMN anulado_de INT NULL DEFAULT NULL");
      try { $pdo->exec("CREATE INDEX idx_pagos_anulado_de ON pagos_residentes(anulado_de)"); } catch(Throwable $e) {}
    }
  }catch(Throwable $e){ $ok = false; }

  return $ok;
}

/* Routing */
$action = $_GET['action'] ?? 'full';

ensure_deuda_inicial_column($pdo);
ensure_exonerado_columns($pdo);
ensure_residente_cuota_mensual_column($pdo);
ensure_pagos_anulacion_columns_local($pdo);

// Config de cuotas (coincide con la app principal)
if (!defined('BASE_DUE')) {
  define('BASE_DUE', '2025-10-05');
}
if (!defined('DUE_DAY')) {
  define('DUE_DAY', '05');
}
if (!defined('CUOTA_MONTO')) {
  define('CUOTA_MONTO', 1000.00);
}
if (!defined('CUOTA_MONTO_NUEVO_DESDE')) {
  define('CUOTA_MONTO_NUEVO_DESDE', '2026-05-01');
}
if (!defined('CUOTA_MONTO_NUEVO')) {
  define('CUOTA_MONTO_NUEVO', 1700.00);
}

function cuota_monto_por_fecha_local(string $ymd): float{
  if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $ymd)) {
    return (float)CUOTA_MONTO;
  }
  return ($ymd >= CUOTA_MONTO_NUEVO_DESDE) ? (float)CUOTA_MONTO_NUEVO : (float)CUOTA_MONTO;
}

function pago_delta_desde_row_local(array $row): int{
  if (isset($row['tipo']) && $row['tipo'] === 'anulacion') {
    return -1;
  }
  if (isset($row['total']) && (float)$row['total'] < 0) {
    return -1;
  }
  return 1;
}

function extraer_meses_pagados_local($raw): array{
  if ($raw === null || $raw === '') return [];
  $arr = json_decode((string)$raw, true);
  if (!is_array($arr)) return [];

  $meses = [];
  foreach($arr as $key => $value){
    if (is_ymd($value)) {
      $meses[] = $value;
    } elseif (is_string($key) && is_ymd($key)) {
      $meses[] = $key;
    }
  }
  return array_values(array_unique($meses));
}

function conteo_meses_cubiertos_residente_local(PDO $pdo, int $residenteId): array{
  $pagados = [];
  $primeraCuotaHistorica = null;
  $pagosConMeses = [];

  try{
    $st = $pdo->prepare("SELECT id, meses_pagados, total, tipo FROM pagos_residentes WHERE residente_id = ?");
    $st->execute([$residenteId]);
  }catch(Throwable $e){
    $st = $pdo->prepare("SELECT id, meses_pagados, total FROM pagos_residentes WHERE residente_id = ?");
    $st->execute([$residenteId]);
  }

  while($row = $st->fetch()){
    $meses = extraer_meses_pagados_local($row['meses_pagados'] ?? null);
    if (!$meses) continue;

    $pagoId = isset($row['id']) ? (int)$row['id'] : 0;
    if ($pagoId > 0) {
      $pagosConMeses[$pagoId] = true;
    }

    $delta = pago_delta_desde_row_local($row);
    foreach($meses as $d){
      $k = align_due_day_local($d);
      $pagados[$k] = ($pagados[$k] ?? 0) + $delta;
      if ($primeraCuotaHistorica === null || strcmp($k, $primeraCuotaHistorica) < 0) {
        $primeraCuotaHistorica = $k;
      }
    }
  }

  try{
    $stLine = $pdo->prepare(
      "SELECT l.pago_id, l.mes_vencimiento, p.total, p.tipo
       FROM pagos_residentes_lineas l
       INNER JOIN pagos_residentes p ON p.id = l.pago_id
       WHERE p.residente_id = ?"
    );
    $stLine->execute([$residenteId]);
    while($line = $stLine->fetch()){
      $pagoId = isset($line['pago_id']) ? (int)$line['pago_id'] : 0;
      if ($pagoId > 0 && isset($pagosConMeses[$pagoId])) {
        continue;
      }
      $mes = $line['mes_vencimiento'] ?? null;
      if (!is_ymd($mes)) continue;

      $k = align_due_day_local($mes);
      $pagados[$k] = ($pagados[$k] ?? 0) + pago_delta_desde_row_local($line);
      if ($primeraCuotaHistorica === null || strcmp($k, $primeraCuotaHistorica) < 0) {
        $primeraCuotaHistorica = $k;
      }
    }
  }catch(Throwable $e){
    // Instalaciones antiguas pueden no tener tabla de lineas.
  }

  return [
    'pagados' => $pagados,
    'primera' => $primeraCuotaHistorica,
  ];
}

/**
 * Cuotas pendientes desde BASE_DUE hasta el último DUE_DAY ya vencido.
 * Copia ligera de la lógica principal para no duplicar includes.
 */
function cuotas_pendientes_residente_local(PDO $pdo, int $residenteId, string $base, bool $ignorarExonerado=false): array{
  try{
    $stBase = $pdo->prepare("SELECT fecha_x_pagar FROM residentes WHERE id = ? LIMIT 1");
    $stBase->execute([$residenteId]);
    $fechaBaseRow = $stBase->fetchColumn();
  }catch(Throwable $e){
    $fechaBaseRow = null;
  }
  $baseCandidata = $base;
  if ($fechaBaseRow && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)$fechaBaseRow)) {
    $baseCandidata = align_due_day_local((string)$fechaBaseRow);
  }

  // Detener cálculo visible si está exonerado.
  if (!$ignorarExonerado) {
    try{
      $chk = $pdo->prepare("SELECT exonerado FROM residentes WHERE id = ? LIMIT 1");
      $chk->execute([$residenteId]);
      $ex = $chk->fetchColumn();
      if ($ex) {
        return [];
      }
    }catch(Throwable $e){
      // fallback a cálculo normal
    }
  }

  // Compat: si tipo no existe, detectar anulación por total < 0
  $historial = conteo_meses_cubiertos_residente_local($pdo, $residenteId);
  $pagados = $historial['pagados'];
  $primeraCuotaHistorica = $historial['primera'];

  if ($primeraCuotaHistorica !== null && strcmp($primeraCuotaHistorica, $baseCandidata) < 0) {
    $baseCandidata = $primeraCuotaHistorica;
  }
  if (strcmp($baseCandidata, align_due_day_local(BASE_DUE)) < 0) {
    $baseCandidata = align_due_day_local(BASE_DUE);
  }

  $hoy = new DateTime('today');
  $ultimo_venc = new DateTime($hoy->format('Y-m-' . DUE_DAY));
  if ($hoy < $ultimo_venc) {
    $ultimo_venc->modify('-1 month');
  }

  $inicio = new DateTime($baseCandidata);
  $inicio->setDate($inicio->format('Y'), $inicio->format('m'), (int)DUE_DAY);
  if ($inicio > $ultimo_venc) return [];

  $pendientes = [];
  for ($d = clone $inicio; $d <= $ultimo_venc; $d->modify('+1 month')) {
    $ymd = $d->format('Y-m-' . DUE_DAY);
    if (($pagados[$ymd] ?? 0) <= 0) {
      $pendientes[] = $ymd;
    }
  }
  return $pendientes;
}

// Normaliza fecha al día configurado
function align_due_day_local(string $ymd): string{
  if (!preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $ymd, $m)) return $ymd;
  return $m[1].'-'.$m[2].'-'.DUE_DAY;
}

function registrar_meses_exonerados_local(PDO $pdo, int $residenteId, array $meses, string $observaciones): void{
  $meses = array_values(array_unique(array_filter($meses, 'is_ymd')));
  sort($meses);
  if (!$meses) return;

  $stmt = $pdo->prepare(
    "INSERT INTO pagos_residentes
     (residente_id, fecha_recibo, fecha_pagada, meses_pagados, monto_base, mora, total, observaciones)
     VALUES (?,?,?,?,?,?,?,?)"
  );
  $stmt->execute([
    $residenteId,
    date('Y-m-d H:i:s'),
    date('Y-m-d'),
    json_encode($meses, JSON_UNESCAPED_UNICODE),
    0,
    0,
    0,
    $observaciones
  ]);
}

function desexonerar_residente_local(PDO $pdo, int $residenteId): array{
  $st = $pdo->prepare("SELECT exonerado, exonerado_desde FROM residentes WHERE id = ? LIMIT 1");
  $st->execute([$residenteId]);
  $residente = $st->fetch();
  if (!$residente) {
    throw new RuntimeException('Residente no encontrado.');
  }

  $mesesCubiertos = [];
  if (!empty($residente['exonerado'])) {
    $mesesCubiertos = cuotas_pendientes_residente_local($pdo, $residenteId, BASE_DUE, true);
    registrar_meses_exonerados_local(
      $pdo,
      $residenteId,
      $mesesCubiertos,
      'Cierre de exoneracion: meses cubiertos mientras estuvo exonerado'
    );
  }

  $fechaHoy = date('Y-m-d');
  $ultimoCubierto = $mesesCubiertos ? end($mesesCubiertos) : null;
  if ($ultimoCubierto) {
    $stmt = $pdo->prepare(
      "UPDATE residentes
       SET exonerado=0, exonerado_desde=NULL, fecha_x_pagar=?, fecha_pagada=?,
           mora=0, monto_a_pagar=0, monto_pagado=0, deuda_extra=0
       WHERE id=?"
    );
    $stmt->execute([$ultimoCubierto, $fechaHoy, $residenteId]);
  } else {
    $stmt = $pdo->prepare(
      "UPDATE residentes
       SET exonerado=0, exonerado_desde=NULL, fecha_pagada=?,
           mora=0, monto_a_pagar=0, monto_pagado=0, deuda_extra=0
       WHERE id=?"
    );
    $stmt->execute([$fechaHoy, $residenteId]);
  }

  return $mesesCubiertos;
}
