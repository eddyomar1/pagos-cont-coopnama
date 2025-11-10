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
    return $d->format('Y-m-d');
  }

  // Comportamiento antiguo: calcular a partir de hoy el siguiente día 25
  $hoy = new DateTime('today');
  $venc = new DateTime($hoy->format('Y-m-25'));
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
  if ($base === null) {
    $base = BASE_DUE;
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
  $currentDue = new DateTime($hoy->format('Y-m-25'));
  if ($hoy < $currentDue) {
    // Todavía no se ha llegado al 25 de este mes -> último vencimiento fue el mes pasado
    $ultimo_venc = (clone $currentDue)->modify('-1 month');
  } else {
    $ultimo_venc = $currentDue;
  }

  // Si BASE_DUE está en el futuro, no hay nada pendiente
  $inicio = new DateTime($base);
  if ($inicio > $ultimo_venc) {
    return [];
  }

  // 3) Recorrer meses desde BASE_DUE hasta último vencimiento,
  //    agregando solo los que NO estén pagados
  $pendientes = [];
  for ($d = clone $inicio; $d <= $ultimo_venc; $d->modify('+1 month')) {
    $ymd = $d->format('Y-m-d'); // día 25 siempre (porque BASE_DUE está en 25)
    if (!isset($pagados[$ymd])) {
      $pendientes[] = $ymd;
    }
  }

  return $pendientes;
}
