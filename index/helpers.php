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

function telefono_whatsapp_url($telefono): ?string{
  $digits = digits_only($telefono);
  if ($digits === '') return null;

  // Republica Dominicana: numeros locales de 10 digitos usan prefijo 1 para WhatsApp.
  if (strlen($digits) === 10) {
    $digits = '1'.$digits;
  }

  if (strlen($digits) < 11) {
    return null;
  }

  return 'https://wa.me/'.$digits;
}

function telefono_whatsapp_html($telefono): string{
  $telefono = trim((string)$telefono);
  if ($telefono === '') {
    return '';
  }

  $url = telefono_whatsapp_url($telefono);
  $label = e($telefono);
  if ($url === null) {
    return $label;
  }

  return '<a href="'.e($url).'" target="_blank" rel="noopener" class="text-decoration-none" title="Contactar por WhatsApp">'.$label.'</a>';
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

function cuota_monto_por_fecha(string $ymd): float{
  $default = (float)CUOTA_MONTO;
  if (!defined('CUOTA_MONTO_NUEVO_DESDE') || !defined('CUOTA_MONTO_NUEVO')) {
    return $default;
  }
  if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $ymd)) {
    return $default;
  }
  return ($ymd >= CUOTA_MONTO_NUEVO_DESDE) ? (float)CUOTA_MONTO_NUEVO : $default;
}

function cuotas_total_por_fechas(array $fechas, array $overrideMontos = []): float{
  $total = 0.0;
  foreach($fechas as $fecha){
    if (!is_ymd($fecha)) continue;
    $monto = $overrideMontos[$fecha] ?? cuota_monto_por_fecha($fecha);
    $monto = is_numeric($monto) ? (float)$monto : cuota_monto_por_fecha($fecha);
    if ($monto < 0) $monto = 0.0;
    $total += $monto;
  }
  return $total;
}

function ultimo_vencimiento_actual(): string{
  $hoy = new DateTime('today');
  $currentDue = new DateTime($hoy->format('Y-m-' . DUE_DAY));
  if ($hoy < $currentDue) {
    $currentDue->modify('-1 month');
  }
  return $currentDue->format('Y-m-d');
}

function meses_retraso_cuota(string $fechaCuota, ?string $fechaCorte=null): int{
  if (!is_ymd($fechaCuota)) return 0;
  $fechaCuota = align_due_day($fechaCuota);
  $fechaCorte = $fechaCorte && is_ymd($fechaCorte) ? align_due_day($fechaCorte) : ultimo_vencimiento_actual();

  $due = new DateTime($fechaCuota);
  $cutoff = new DateTime($fechaCorte);
  $months = (((int)$cutoff->format('Y')) - ((int)$due->format('Y'))) * 12;
  $months += ((int)$cutoff->format('n')) - ((int)$due->format('n'));

  return max(0, $months);
}

function cuotas_en_mora(array $fechas, int $minMesesRetraso = 2, ?string $fechaCorte=null): array{
  $out = [];
  foreach($fechas as $fecha){
    if (!is_ymd($fecha)) continue;
    if (meses_retraso_cuota($fecha, $fechaCorte) >= $minMesesRetraso) {
      $out[] = $fecha;
    }
  }
  return $out;
}

function pago_delta_desde_row(array $row): int{
  if (isset($row['tipo']) && $row['tipo'] === 'anulacion') {
    return -1;
  }
  if (isset($row['total']) && (float)$row['total'] < 0) {
    return -1;
  }
  return 1;
}

function extraer_meses_pagados($raw): array{
  if ($raw === null || $raw === '') return [];
  $arr = json_decode((string)$raw, true);
  if (!is_array($arr)) return [];

  $meses = [];
  foreach($arr as $key => $value){
    if (is_ymd($value)) {
      $meses[] = $value;
    } elseif (is_string($key) && is_ymd($key)) {
      // Compatibilidad con JSON tipo {"2026-05-05": 1700.00}
      $meses[] = $key;
    }
  }
  return array_values(array_unique($meses));
}

function conteo_meses_cubiertos_residente(PDO $pdo, int $residenteId): array{
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
    $meses = extraer_meses_pagados($row['meses_pagados'] ?? null);
    if (!$meses) continue;

    $pagoId = isset($row['id']) ? (int)$row['id'] : 0;
    if ($pagoId > 0) {
      $pagosConMeses[$pagoId] = true;
    }

    $delta = pago_delta_desde_row($row);
    foreach($meses as $d){
      $norm = align_due_day($d);
      $pagados[$norm] = ($pagados[$norm] ?? 0) + $delta;
      if ($primeraCuotaHistorica === null || strcmp($norm, $primeraCuotaHistorica) < 0) {
        $primeraCuotaHistorica = $norm;
      }
    }
  }

  // Respaldo para pagos nuevos/antiguos que tengan lineas pero meses_pagados vacio o dañado.
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

      $norm = align_due_day($mes);
      $pagados[$norm] = ($pagados[$norm] ?? 0) + pago_delta_desde_row($line);
      if ($primeraCuotaHistorica === null || strcmp($norm, $primeraCuotaHistorica) < 0) {
        $primeraCuotaHistorica = $norm;
      }
    }
  }catch(Throwable $e){
    // La tabla de lineas puede no existir en instalaciones antiguas.
  }

  return [
    'pagados' => $pagados,
    'primera' => $primeraCuotaHistorica,
  ];
}


/**
 * Devuelve un array de YYYY-MM-DD (día DUE_DAY) con las cuotas pendientes
 * para un residente, desde BASE_DUE hasta el último vencimiento <= hoy,
 * teniendo en cuenta todos los meses YA PAGADOS en pagos_residentes
 * (incluyendo pagos adelantados).
 */
function cuotas_pendientes_residente(PDO $pdo, int $residenteId, ?string $base=null, bool $ignorarExonerado=false){
  // Fecha base: usamos la fecha_x_pagar del residente, pero si en el historial
  // existen cuotas más antiguas (pagadas o anuladas), arrancamos desde la más antigua.
  try{
    $stBase = $pdo->prepare("SELECT fecha_x_pagar FROM residentes WHERE id = ? LIMIT 1");
    $stBase->execute([$residenteId]);
    $fechaBaseRow = $stBase->fetchColumn();
  }catch(Throwable $e){
    $fechaBaseRow = null;
  }
  $baseCandidata = null;
  if ($fechaBaseRow && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)$fechaBaseRow)) {
    $baseCandidata = align_due_day((string)$fechaBaseRow);
  } elseif ($base !== null) {
    $baseCandidata = align_due_day((string)$base);
  } else {
    $baseCandidata = align_due_day(BASE_DUE);
  }

  // Si está exonerado, no generar pendientes visibles hasta que se reactive.
  if (!$ignorarExonerado) {
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
  }

  // 1) Construir conteo neto de meses pagados (YYYY-MM-DUE_DAY)
  //    - Un pago suma +1
  //    - Una anulación resta -1
  //    Compat: si la columna tipo no existe aún, caemos a detectar por total < 0.
  $historial = conteo_meses_cubiertos_residente($pdo, $residenteId);
  $pagados = $historial['pagados'];
  $primeraCuotaHistorica = $historial['primera'];

  if ($primeraCuotaHistorica !== null && strcmp($primeraCuotaHistorica, $baseCandidata) < 0) {
    $baseCandidata = $primeraCuotaHistorica;
  }
  if (strcmp($baseCandidata, align_due_day(BASE_DUE)) < 0) {
    $baseCandidata = align_due_day(BASE_DUE);
  }
  $base = $baseCandidata;

  // 2) Determinar último vencimiento (día DUE_DAY) que ya pasó o es hoy
  $ultimo_venc = new DateTime(ultimo_vencimiento_actual());

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

function ensure_pagos_cantidad_meses_column(PDO $pdo): bool{
  static $checked = false;
  static $ok = false;
  if ($checked) return $ok;

  $checked = true;
  $ok = true;

  try{
    $st = $pdo->query("SHOW COLUMNS FROM pagos_residentes LIKE 'cantidad_meses'");
    if (!$st || !$st->fetch()) {
      $pdo->exec("ALTER TABLE pagos_residentes ADD COLUMN cantidad_meses INT NOT NULL DEFAULT 0 AFTER fecha_pagada");
    }
  }catch(Throwable $e){
    $ok = false;
    app_log('No se pudo asegurar columna pagos_residentes.cantidad_meses: '.$e->getMessage());
  }

  return $ok;
}

function ensure_pagos_lineas_table(PDO $pdo): bool{
  static $checked = false;
  static $ok = false;
  if ($checked) return $ok;

  $checked = true;
  $ok = true;

  try{
    $st = $pdo->query("SHOW TABLES LIKE 'pagos_residentes_lineas'");
    if (!$st || !$st->fetch()) {
      $pdo->exec(
        "CREATE TABLE pagos_residentes_lineas (
           id INT AUTO_INCREMENT PRIMARY KEY,
           pago_id INT NOT NULL,
           mes_vencimiento DATE NOT NULL,
           monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
           mora_linea DECIMAL(10,2) NOT NULL DEFAULT 0.00,
           anulado_de INT NULL DEFAULT NULL,
           INDEX idx_pago_id (pago_id),
           INDEX idx_mes_vencimiento (mes_vencimiento),
           UNIQUE KEY ux_pago_mes (pago_id, mes_vencimiento)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
      );
    }
  }catch(Throwable $e){
    $ok = false;
    app_log('No se pudo asegurar tabla pagos_residentes_lineas: '.$e->getMessage());
  }

  return $ok;
}

function insertar_pago_con_lineas(
  PDO $pdo,
  int $residenteId,
  array $selected_dues,
  array $selected_due_amounts,
  float $monto_base,
  float $mora,
  string $fecha_pagada,
  ?string $observaciones = null
): int {
  $selected_dues = array_values(array_filter($selected_dues, 'is_ymd'));
  sort($selected_dues);

  $pagos = [];
  $totalCuotas = cuotas_total_por_fechas($selected_dues, $selected_due_amounts);
  $extra = max(0.0, $monto_base - $totalCuotas);

  foreach ($selected_dues as $mes) {
    $montoMes = isset($selected_due_amounts[$mes]) && is_numeric($selected_due_amounts[$mes])
      ? max(0.0, (float)$selected_due_amounts[$mes])
      : cuota_monto_por_fecha($mes);
    $pagos[] = [
      'meses' => [$mes],
      'detalle' => [$mes => $montoMes],
      'monto_base' => $montoMes,
      'mora_base' => $montoMes,
      'observaciones' => null,
    ];
  }

  if (!$pagos) {
    $pagos[] = [
      'meses' => [],
      'detalle' => [],
      'monto_base' => max(0.0, $monto_base),
      'mora_base' => max(0.0, $monto_base),
      'observaciones' => $observaciones,
    ];
    $extra = 0.0;
  }

  if ($extra > 0) {
    $pagos[0]['monto_base'] += $extra;
    $pagos[0]['observaciones'] = $observaciones;
  } elseif ($observaciones !== null && count($pagos) === 1) {
    $pagos[0]['observaciones'] = $observaciones;
  }

  $fecha_recibo = date('Y-m-d H:i:s');
  $primerPagoId = 0;
  $moraTotal = round((float)$mora, 2);
  $moraRestante = $moraTotal;
  $moraBaseTotal = max(0.01, array_sum(array_column($pagos, 'mora_base')));
  $ultimoIdx = count($pagos) - 1;

  $stmtLine = $pdo->prepare(
    "INSERT INTO pagos_residentes_lineas
     (pago_id, mes_vencimiento, monto, mora_linea)
     VALUES (?,?,?,?)"
  );

  foreach ($pagos as $idx => $pago) {
    $moraMes = 0.0;
    if ($moraRestante > 0) {
      if ($idx === $ultimoIdx) {
        $moraMes = $moraRestante;
      } else {
        $moraMes = round(((float)$pago['mora_base'] / $moraBaseTotal) * $moraTotal, 2);
        if ($moraMes > $moraRestante) $moraMes = $moraRestante;
      }
      $moraRestante = round($moraRestante - $moraMes, 2);
    }

    $cantidad_meses = count($pago['meses']);
    $meses_pagados_json = json_encode($pago['meses'], JSON_UNESCAPED_UNICODE);
    $detalle_cuotas_json = json_encode($pago['detalle'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    $monto_base_mes = round((float)$pago['monto_base'], 2);
    $total_mes = $monto_base_mes + $moraMes;

    if (defined('HAS_PAGOS_DETALLE_CUOTAS') && HAS_PAGOS_DETALLE_CUOTAS) {
      $stmt = $pdo->prepare(
        "INSERT INTO pagos_residentes
         (residente_id, fecha_recibo, fecha_pagada, cantidad_meses,
          meses_pagados, detalle_cuotas, monto_base, mora, total, observaciones)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
      );
      $stmt->execute([
        $residenteId,
        $fecha_recibo,
        $fecha_pagada,
        $cantidad_meses,
        $meses_pagados_json,
        $detalle_cuotas_json,
        $monto_base_mes,
        $moraMes,
        $total_mes,
        $pago['observaciones']
      ]);
    } else {
      $stmt = $pdo->prepare(
        "INSERT INTO pagos_residentes
         (residente_id, fecha_recibo, fecha_pagada, cantidad_meses,
          meses_pagados, monto_base, mora, total, observaciones)
         VALUES (?,?,?,?,?,?,?,?,?)"
      );
      $stmt->execute([
        $residenteId,
        $fecha_recibo,
        $fecha_pagada,
        $cantidad_meses,
        $meses_pagados_json,
        $monto_base_mes,
        $moraMes,
        $total_mes,
        $pago['observaciones']
      ]);
    }

    $pagoId = (int)$pdo->lastInsertId();
    if ($primerPagoId === 0) {
      $primerPagoId = $pagoId;
    }

    foreach ($pago['meses'] as $mes) {
      $montoLinea = isset($pago['detalle'][$mes]) ? (float)$pago['detalle'][$mes] : 0.0;
      $stmtLine->execute([$pagoId, $mes, $montoLinea, $moraMes]);
    }
  }

  return $primerPagoId;
}

function registrar_meses_exonerados(PDO $pdo, int $residenteId, array $meses, string $observaciones): ?int{
  $meses = array_values(array_unique(array_filter($meses, 'is_ymd')));
  sort($meses);
  if (!$meses) {
    return null;
  }

  $montosCero = [];
  foreach($meses as $mes){
    $montosCero[$mes] = 0.0;
  }

  return insertar_pago_con_lineas(
    $pdo,
    $residenteId,
    $meses,
    $montosCero,
    0.0,
    0.0,
    date('Y-m-d'),
    $observaciones
  );
}

function desexonerar_residente(PDO $pdo, int $residenteId): array{
  $st = $pdo->prepare("SELECT exonerado, exonerado_desde FROM residentes WHERE id = ? LIMIT 1");
  $st->execute([$residenteId]);
  $residente = $st->fetch();
  if (!$residente) {
    throw new RuntimeException('Residente no encontrado.');
  }

  $mesesCubiertos = [];
  if (!empty($residente['exonerado'])) {
    // Ignorar la bandera activa solo para cerrar el periodo exonerado con historial real.
    $mesesCubiertos = cuotas_pendientes_residente($pdo, $residenteId, BASE_DUE, true);
    registrar_meses_exonerados(
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

function ensure_pagos_detalle_cuotas_column(PDO $pdo): bool{
  static $checked = false;
  static $ok = false;
  if ($checked) return $ok;

  $checked = true;
  $ok = true;

  try{
    $st = $pdo->query("SHOW COLUMNS FROM pagos_residentes LIKE 'detalle_cuotas'");
    $exists = (bool)($st && $st->fetch());
    if (!$exists) {
      $pdo->exec("ALTER TABLE pagos_residentes ADD COLUMN detalle_cuotas LONGTEXT NULL DEFAULT NULL AFTER meses_pagados");
    }
  }catch(Throwable $e){
    $ok = false;
    app_log('No se pudo asegurar columna pagos_residentes.detalle_cuotas: '.$e->getMessage());
  }

  if (!defined('HAS_PAGOS_DETALLE_CUOTAS')) {
    define('HAS_PAGOS_DETALLE_CUOTAS', $ok);
  }
  return $ok;
}
