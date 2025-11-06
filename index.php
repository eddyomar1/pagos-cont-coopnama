<?php
/*************************************************
 * Residentes + Pagos mantenimiento
 * - Lista de residentes
 * - Pagar / crear recibo (con cuotas + deuda extra)
 * - Registra en pagos_residentes
 *************************************************/
session_start();

/*********** 1) Configuración ***********/
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

try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (Throwable $e) {
  http_response_code(500);
  exit("DB error: " . htmlspecialchars($e->getMessage()));
}

/*********** 2) Constantes y helpers ***********/
// Fecha base a partir de la cual se cuentan las cuotas (día 5)
const BASE_DUE      = '2025-10-05';
const CUOTA_MONTO   = 1000.00; // RD$ por mes

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

$action = $_GET['action'] ?? 'index';

/*********** 3) Acciones CRUD ***********/

/* 3.1 Crear residente (no se usa para pagos) */
if ($action === 'store' && $_SERVER['REQUEST_METHOD']==='POST') {
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $deuda_extra       = toDecimal(body('deuda_extra')) ?? 0;

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_in))         $errors[]="Cédula es obligatoria.";

  $cedula_digits = digits_only($cedula_in);
  if($cedula_in && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";

  if($errors){
    $_SESSION['errors']=$errors;
    $_SESSION['old']=$_POST;
    header('Location:?action=new'); exit;
  }

  try{
    $stmt=$pdo->prepare(
      "INSERT INTO residentes
       (edif_apto,nombres_apellidos,cedula,codigo,telefono,
        fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,deuda_extra,no_recurrente)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,0)"
    );
    $stmt->execute([
      $edif_apto,
      $nombres_apellidos,
      $cedula_digits,
      $codigo ?: null,
      $telefono ?: null,
      null,null,0,0,0,
      $deuda_extra
    ]);
    header('Location:?saved=1'); exit;
  }catch(PDOException $ex){
    $_SESSION['errors']=[ "No se pudo guardar: ".$ex->getMessage() ];
    $_SESSION['old']=$_POST;
    header('Location:?action=new'); exit;
  }
}

/* 3.2 Pagar / crear recibo (update + insert en pagos_residentes) */
if ($action === 'update' && $_SERVER['REQUEST_METHOD']==='POST') {

  $id = (int)body('id');
  if ($id<=0) {
    $_SESSION['errors']=['ID inválido.'];
    header('Location:?'); exit;
  }

  // Leemos residente actual (para no depender de inputs deshabilitados)
  $st = $pdo->prepare("SELECT * FROM residentes WHERE id=?");
  $st->execute([$id]);
  $residente = $st->fetch();
  if(!$residente){
    $_SESSION['errors']=['Residente no encontrado.'];
    header('Location:?'); exit;
  }

  $edif_apto         = $residente['edif_apto'];
  $nombres_apellidos = $residente['nombres_apellidos'];
  $cedula_digits     = $residente['cedula'];
  $codigo            = $residente['codigo'];
  $telefono          = $residente['telefono'];

  $fecha_pagada      = toDateOrNull(body('fecha_pagada'));
  $mora              = toDecimal(body('mora')) ?? 0;
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  // Deuda extra: si el campo editable viene en el POST lo usamos;
  // si no, tomamos el valor oculto (de la BD)
  $deuda_restante_post = body('deuda_restante', '');
  if ($deuda_restante_post !== '') {
    $deuda_extra_actual = toDecimal($deuda_restante_post) ?? 0;
  } else {
    $deuda_extra_actual = toDecimal(body('deuda_extra_actual')) ?? 0;
  }

  $abono_deuda_extra  = toDecimal(body('abono_deuda_extra')) ?? 0;

  // Cuotas seleccionadas (checkboxes)
  $selected_dues = isset($_POST['selected_dues']) && is_array($_POST['selected_dues'])
    ? $_POST['selected_dues'] : [];
  $selected_dues = array_values(array_filter($selected_dues, 'is_ymd'));

  // Fecha x pagar = último mes seleccionado (si hay)
  if ($selected_dues) {
    sort($selected_dues);
    $fecha_x_pagar = end($selected_dues);
  } else {
    $fecha_x_pagar = toDateOrNull(body('fecha_x_pagar')) ?: null;
  }

  // Monto base = meses x 1000 + abono deuda extra
  $monto_base_cuotas = count($selected_dues) * CUOTA_MONTO;
  $monto_base        = $monto_base_cuotas + $abono_deuda_extra;

  // Lo que guardamos como "monto_a_pagar" (sin mora) y "monto_pagado" (con mora)
  $monto_a_pagar = $monto_base;
  $monto_pagado  = $monto_base + $mora;

  // Nueva deuda extra = deuda_actual - abono (nunca negativa)
  $nueva_deuda_extra = max(0, $deuda_extra_actual - $abono_deuda_extra);

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_digits))     $errors[]="Cédula es obligatoria.";
  if($cedula_digits && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";
  if(!$fecha_pagada) $errors[]='Debe indicar la fecha pagada.';

  if($errors){
    $_SESSION['errors']=$errors;
    $_SESSION['old']=$_POST;
    header('Location:?action=pagar&id='.$id); exit;
  }

  try{
    $pdo->beginTransaction();

    // Actualizar residente
    $stmt=$pdo->prepare(
      "UPDATE residentes SET
        edif_apto=?, nombres_apellidos=?, cedula=?, codigo=?, telefono=?,
        fecha_x_pagar=?, fecha_pagada=?, mora=?, monto_a_pagar=?, monto_pagado=?, deuda_extra=?, no_recurrente=?
       WHERE id=?"
    );
    $stmt->execute([
      $edif_apto,$nombres_apellidos,$cedula_digits,$codigo ?: null,$telefono ?: null,
      $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$nueva_deuda_extra,$no_recurrente,$id
    ]);

    // Registrar pago
    $stmt2 = $pdo->prepare(
      "INSERT INTO pagos_residentes
       (residente_id, fecha_recibo, fecha_pagada, meses_pagados,
        monto_base, mora, total, observaciones)
       VALUES (?,?,?,?,?,?,?,?)"
    );
    $observaciones = $abono_deuda_extra > 0
      ? 'Incluye abono a deuda extra de RD$ '.number_format($abono_deuda_extra,2,'.','')
      : null;

    $stmt2->execute([
      $id,
      date('Y-m-d H:i:s'),
      $fecha_pagada,
      json_encode($selected_dues, JSON_UNESCAPED_UNICODE),
      $monto_base,
      $mora,
      $monto_base + $mora,
      $observaciones
    ]);

    $pdo->commit();
    header('Location:?updated=1'); exit;

  }catch(PDOException $ex){
    $pdo->rollBack();
    $_SESSION['errors']=[
      "El pago se guardó en residente pero NO en pagos_residentes: ".$ex->getMessage()
    ];
    $_SESSION['old']=$_POST;
    header('Location:?action=pagar&id='.$id); exit;
  }
}

/* 3.3 Eliminar residente (opcional) */
if ($action === 'delete' && isset($_GET['id'])) {
  $id=(int)$_GET['id'];
  if($id>0){
    $pdo->prepare("DELETE FROM residentes WHERE id=?")->execute([$id]);
  }
  header('Location:?deleted=1'); exit;
}

/*********** 4) Layout ***********/
function header_html($title='Residentes'){ ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($title)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
 body{background:#f6f7fb}
 .card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .table thead th{font-weight:600}
</style>
</head><body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="?">RESIDENCIAL COOPNAMA II</a>
        <div class="ms-auto btn-group">
      <a href="pagos.php" class="btn btn-primary btn-sm">Pagos</a>
    </div>
  </div>
</nav>
<main class="container my-4">
<?php }
function footer_html(){ ?>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  // DataTable de residentes
  var $tbl = $('#tabla');
  if ($tbl.length) {
    var dt = $tbl.DataTable({
      pageLength: 10,
      lengthMenu: [5,10,25,50,100],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
      dom: 'tip',
      columnDefs: [{ targets: -1, className: 'text-center' }]
    });
    $('#globalSearch').on('input', function(){ dt.search(this.value).draw(); });
    $('#lenSelect').on('change', function(){ dt.page.len(parseInt(this.value,10)).draw(); });
    $('#lenSelect').val(dt.page.len());
  }

  // Botón para mostrar / editar la deuda atrasada
  $(document).on('click', '#btnToggleDeuda', function(){
    $('#cardDeudaExtra').removeClass('d-none');
    $('#deuda_restante').prop('disabled', false);
    if (!$('#deuda_restante').val()) {
      $('#deuda_restante').val('0.00');
    }
    $(this).prop('disabled', true);
  });

  // === CUOTAS + DEUDA EXTRA ===
  function recalcDueSelection(){
    var $boxes = $('.due-option:checked');
    var count  = $boxes.length;
    var totalCuotas = count * 1000;

    // Deuda actual (tomamos la visible; si está vacía, usamos el hidden)
    var deudaStr = $('#deuda_restante').val() || $('#deuda_extra_actual').val() || '0';
    deudaStr = deudaStr.replace(',', '.');
    var deudaActual = parseFloat(deudaStr);
    if (isNaN(deudaActual)) deudaActual = 0;

    // Abono a deuda extra
    var abonoStr = $('#abono_deuda_extra').val() || '0';
    abonoStr = abonoStr.replace(',', '.');
    var abono = parseFloat(abonoStr);
    if (isNaN(abono)) abono = 0;

    // Total base = cuotas + abono
    var totalBase = totalCuotas + abono;

    // Reflejar total base en input "monto_a_pagar"
    if ($('input[name="monto_a_pagar"]').length){
      $('input[name="monto_a_pagar"]').val(totalBase.toFixed(2));
    }

    // Contadores para la card de cuotas
    $('#countSelected').text(count);
    $('#totalSelected').text(
      totalBase.toLocaleString('es-DO', {
        minimumFractionDigits:2,
        maximumFractionDigits:2
      })
    );

    // Deuda estimada después de este pago (solo visual)
    var despues = Math.max(0, deudaActual - abono);
    if ($('#deuda_despues').length){
      $('#deuda_despues').val(despues.toFixed(2));
    }
  }

  $(document).on('change', '.due-option', recalcDueSelection);
  $(document).on('input', '#abono_deuda_extra', recalcDueSelection);
  $(document).on('input', '#deuda_restante', recalcDueSelection);
  recalcDueSelection(); // inicial

  $(document).on('click', '.btn-delete', function(e){
    if (!confirm('¿Eliminar este registro?')) e.preventDefault();
  });
});
</script>
</body></html>
<?php }

/*********** 5) Vistas ***********/

/* 5.1 Listado de residentes */
if ($action==='index') {
  header_html('Residentes');
  $rows=$pdo->query("SELECT * FROM residentes ORDER BY id DESC")->fetchAll();

  if(isset($_GET['saved']))   echo '<div class="alert alert-success">Registro agregado.</div>';
  if(isset($_GET['updated'])) echo '<div class="alert alert-info">Pago registrado.</div>';
  if(isset($_GET['deleted'])) echo '<div class="alert alert-warning">Registro eliminado.</div>';
  ?>

  <!-- CARD DE CONTROLES -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-center">
        <div class="col-sm-6 d-flex align-items-center gap-2">
          <span>Mostrar</span>
          <select id="lenSelect" class="form-select form-select-sm" style="width:auto;">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="text-muted">registros</span>
        </div>
        <div class="col-sm-6 d-flex align-items-center justify-content-sm-end gap-2">
          <label for="globalSearch" class="mb-0">Buscar:</label>
          <input id="globalSearch" type="search" class="form-control form-control-sm" placeholder="Buscar...">
        </div>
      </div>
    </div>
  </div>

  <!-- CARD TABLA -->
  <div class="card">
    <div class="card-body">
      <h5 class="mb-3">LISTA DE COPROPIETARIOS</h5>
      <div class="table-responsive">
        <table id="tabla" class="table table-striped table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Edif/Apto</th>
              <th>Nombres y Apellidos</th>
              <th>Cédula</th>
              <th>Teléfono</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= e($r['edif_apto']) ?></td>
              <td><?= e($r['nombres_apellidos']) ?></td>
              <td><?= e(format_cedula($r['cedula'])) ?></td>
              <td><?= e($r['telefono']) ?></td>
              <td class="text-center">
                <a class="btn btn-primary btn-sm" href="?action=pagar&id=<?= (int)$r['id'] ?>">PAGAR MANTENIMIENTO</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php
  footer_html(); exit;
}

/* 5.2 Formulario Pagar / Crear recibo */
if ($action==='new' || $action==='pagar') {
  $editing = $action==='pagar';

  $data=[
    'id'=>null,
    'edif_apto'=>'',
    'nombres_apellidos'=>'',
    'cedula'=>'',
    'codigo'=>'',
    'telefono'=>'',
    'fecha_x_pagar'=>'',
    'fecha_pagada'=>'',
    'mora'=>'0.00',
    'monto_a_pagar'=>'0.00',
    'monto_pagado'=>'0.00',
    'deuda_extra'=>'0.00',
    'no_recurrente'=>0
  ];

  if($editing){
    $id=(int)($_GET['id'] ?? 0);
    if($id<=0){ header('Location:?'); exit; }
    $st=$pdo->prepare("SELECT * FROM residentes WHERE id=?");
    $st->execute([$id]);
    $row=$st->fetch();
    if(!$row){ header('Location:?'); exit; }
    $data=array_merge($data,$row);
  }

  if(!empty($_SESSION['old'])) $data=array_merge($data,$_SESSION['old']);
  $errors=$_SESSION['errors'] ?? [];
  $_SESSION['old']=$_SESSION['errors']=null;

  header_html($editing?'Pagar / Crear recibo':'Agregar residente');

  // Cálculo de cuotas pendientes
  $pendientes = cuotas_pendientes(BASE_DUE, $data['fecha_pagada'] ?: null);
  $cantidad   = count($pendientes);

  // Deuda extra actual (desde BD)
  $deuda_extra_db   = isset($data['deuda_extra']) ? (float)$data['deuda_extra'] : 0.0;
  $deuda_extra_fmt  = number_format($deuda_extra_db,2,'.','');
  $mostrar_card_deuda = $deuda_extra_db > 0;
  ?>
  <div class="row justify-content-center"><div class="col-lg-10">

    <?php if($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach($errors as $m) echo "<li>".e($m)."</li>"; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="?action=<?=$editing?'update':'store'?>">
      <?php if($editing): ?>
        <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
      <?php endif; ?>

      <!-- CARD DATOS -->
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">Pagar / Crear recibo</h5>
          <?php if ($editing): ?>
            <button type="button" id="btnToggleDeuda" class="btn btn-outline-secondary btn-sm">
              Añadir / editar deuda atrasada
            </button>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Edif/Apto *</label>
            <input type="text" name="edif_apto" class="form-control"
                   maxlength="60" value="<?=e($data['edif_apto'])?>" disabled>
          </div>
          <div class="col-md-5">
            <label class="form-label">Nombres y Apellidos *</label>
            <input type="text" name="nombres_apellidos" class="form-control"
                   maxlength="255" value="<?=e($data['nombres_apellidos'])?>" disabled>
          </div>
          <div class="col-md-2">
            <label class="form-label">Cédula *</label>
            <input type="text" name="cedula" class="form-control"
                   maxlength="13"
                   value="<?=e(format_cedula($data['cedula']))?>" disabled>
          </div>
          <div class="col-md-2">
            <label class="form-label">Código</label>
            <input type="text" name="codigo" class="form-control"
                   maxlength="30" value="<?=e($data['codigo'])?>" disabled>
          </div>

          <div class="col-md-3">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control"
                   maxlength="50" value="<?=e($data['telefono'])?>" disabled>
          </div>

          <div class="col-md-3">
            <label class="form-label">Fecha pagada (ingresada por quien cobra)</label>
            <input type="date" name="fecha_pagada" class="form-control"
                   value="<?=e($data['fecha_pagada'])?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Mora (si aplica)</label>
            <input type="text" name="mora" class="form-control"
                   placeholder="0.00" value="<?=e($data['mora'])?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Monto a pagar (auto)</label>
            <input type="text" name="monto_a_pagar" class="form-control"
                   placeholder="0.00" value="<?=e($data['monto_a_pagar'])?>" disabled>
          </div>
        </div>
      </div></div>

      <!-- CARD DEUDA EXTRA (se muestra u oculta, y se desbloquea con el botón) -->
      <div class="card mb-3 <?= $mostrar_card_deuda ? '' : 'd-none' ?>" id="cardDeudaExtra">
        <div class="card-body">
          <p class="text-muted mb-3">
            Deuda registrada actualmente: <strong>RD$ <?= number_format($deuda_extra_db,2,'.',',') ?></strong>
          </p>

          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Deuda restante (estimada)</label>
              <input type="text" class="form-control"
                     name="deuda_restante" id="deuda_restante"
                     value="<?= $deuda_extra_fmt ?>" <?= $mostrar_card_deuda ? 'disabled' : 'disabled' ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label">Monto a abonar ahora</label>
              <input type="text" class="form-control"
                     name="abono_deuda_extra" id="abono_deuda_extra"
                     placeholder="0.00" value="">
            </div>
            <div class="col-md-4">
              <label class="form-label">Deuda estimada después de este pago</label>
              <input type="text" class="form-control"
                     id="deuda_despues"
                     value="<?= $deuda_extra_fmt ?>" disabled>
            </div>
          </div>

          <!-- valor original de la deuda (por si el campo está deshabilitado y no viene en POST) -->
          <input type="hidden" name="deuda_extra_actual" id="deuda_extra_actual"
                 value="<?= $deuda_extra_fmt ?>">
        </div>
      </div>

      <!-- CARD CUOTAS PENDIENTES -->
      <div class="card mb-3"><div class="card-body">
        <h6 class="mb-2">Cuotas pendientes</h6>

        <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
          <span class="text-muted">
            Pendientes totales:
            <span class="badge bg-<?= $cantidad? 'warning text-dark':'success' ?>">
              <?= $cantidad ?>
            </span>
          </span>
          <span class="text-muted">
            Seleccionadas: <strong id="countSelected">0</strong>
          </span>
          <span class="text-muted">
            Total seleccionado: RD$ <strong id="totalSelected">0.00</strong>
          </span>
        </div>

        <?php if ($pendientes): ?>
          <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-2">
            <?php foreach($pendientes as $i=>$venc): $label=fecha_larga_es($venc); ?>
              <div class="col">
                <div class="form-check">
                  <input
                    class="form-check-input due-option"
                    type="checkbox"
                    name="selected_dues[]"
                    id="due<?= $i ?>"
                    value="<?= e($venc) ?>"
                    data-label="<?= e($label) ?>"
                    checked
                  >
                  <label class="form-check-label" for="due<?= $i ?>"><?= e($label) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-success mb-0">
            No hay cuotas pendientes. Próximo vencimiento:
            <strong><?= e(fecha_larga_es(proximo_quinto())) ?></strong>.
          </div>
        <?php endif; ?>

        <!-- hidden que se envía en el POST (último mes seleccionado) -->
        <input type="hidden" name="fecha_x_pagar" id="fecha_x_pagar" value="">
      </div></div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><?=$editing?'Crear recibo':'Guardar'?></button>
        <a class="btn btn-outline-secondary" href="?">Cancelar</a>
      </div>

    </form>
  </div></div>

  <?php
  footer_html(); exit;
}

header('Location:?'); exit;
