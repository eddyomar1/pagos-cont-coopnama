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

/* --- Helpers de vencimientos (día 5) --- */
function fecha_larga_es($ymd){
  static $meses=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $ts = strtotime($ymd);
  if(!$ts) return $ymd;
  return '5 de '.$meses[(int)date('n',$ts)-1].' de '.date('Y',$ts);
}
function anclar_a_quinto($date){
  $d = new DateTime($date);
  $quinto = new DateTime($d->format('Y-m-05'));
  if ($d < $quinto) $quinto->modify('-1 month');
  return $quinto;
}
function cuotas_pendientes($base=null, $ultima_pagada=null){
  if ($base === null) $base = BASE_DUE;

  $hoy = new DateTime('today');
  $ultimo_venc = new DateTime(date('Y-m-05'));
  if ($hoy < $ultimo_venc) $ultimo_venc->modify('-1 month');

  $inicio = new DateTime($base);
  if ($ultima_pagada) {
    $pagada_quinto = anclar_a_quinto($ultima_pagada);
    if ($pagada_quinto >= $inicio) {
      $inicio = (clone $pagada_quinto)->modify('+1 month');
    }
  }

  $out=[];
  for ($d=clone $inicio; $d <= $ultimo_venc; $d->modify('+1 month')) {
    $out[] = $d->format('Y-m-d'); // día 5 siempre
  }
  return $out;
}
function proximo_quinto(){
  $hoy = new DateTime('today');
  $quinto = new DateTime(date('Y-m-05'));
  if ($hoy >= $quinto) $quinto->modify('+1 month');
  return $quinto->format('Y-m-d');
}
function is_ymd($s){
  return is_string($s) && preg_match('~^\d{4}-\d{2}-\d{2}$~',$s);
}
