<?php
/*************************************************
 * CRUD Residentes (PDO + Bootstrap + DataTables)
 * + Registro de pagos en tabla aparte
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
  for($i=0;$i<10;$i++){ $p=$d[$i]*$m[$i]; if($p>=10)$p=intdiv($p,10)+($p%10); $s+=$p; }
  return ( (10-($s%10))%10 ) == $d[10];
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
function cuotas_pendientes($base='2025-10-05', $ultima_pagada=null){
  $hoy = new DateTime('today');
  $ultimo_venc = new DateTime(date('Y-m-05'));
  if ($hoy < $ultimo_venc) $ultimo_venc->modify('-1 month');

  $inicio = new DateTime($base);
  if ($ultima_pagada) {
    $pagada_quinto = anclar_a_quinto($ultima_pagada);
    if ($pagada_quinto >= $inicio) $inicio = (clone $pagada_quinto)->modify('+1 month');
  }

  $out=[];
  for ($d=clone $inicio; $d <= $ultimo_venc; $d->modify('+1 month')) {
    $out[] = $d->format('Y-m-d'); // siempre día 5
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
  return is_string($s) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $s);
}

$action = $_GET['action'] ?? 'index';

/*********** 3) Acciones CRUD ***********/

/* ========== CREAR RESIDENTE ========== */
if ($action === 'store' && $_SERVER['REQUEST_METHOD']==='POST') {
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $fecha_x_pagar     = toDateOrNull(body('fecha_x_pagar'));
  $fecha_pagada      = toDateOrNull(body('fecha_pagada'));
  $mora              = toDecimal(body('mora')) ?? 0;
  $monto_a_pagar     = toDecimal(body('monto_a_pagar')) ?? 0;
  $monto_pagado      = toDecimal(body('monto_pagado')) ?? 0;
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_in))         $errors[]="Cédula es obligatoria.";
  $cedula_digits = digits_only($cedula_in);
  if($cedula_in && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";

  if($errors){ $_SESSION['errors']=$errors; $_SESSION['old']=$_POST; header('Location:?action=new'); exit; }

  // guardamos residente
  $stmt=$pdo->prepare(
    "INSERT INTO residentes
     (edif_apto,nombres_apellidos,cedula,codigo,telefono,fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,no_recurrente)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
  );
  $stmt->execute([
    $edif_apto,$nombres_apellidos,$cedula_digits,$codigo ?: null,$telefono ?: null,
    $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$no_recurrente
  ]);
  header('Location:?saved=1'); exit;
}

/* ========== PAGAR / CREAR RECIBO ========== */
if ($action === 'update' && $_SERVER['REQUEST_METHOD']==='POST') {
  $id                = (int)body('id');
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $fecha_pagada      = toDateOrNull(body('fecha_pagada'));   // cuándo se está registrando que se pagó
  $mora              = toDecimal(body('mora')) ?? 0;
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  // 1) leer los meses seleccionados (checkbox)
  $selected_dues = isset($_POST['selected_dues']) && is_array($_POST['selected_dues']) ? $_POST['selected_dues'] : [];
  $selected_dues = array_values(array_filter($selected_dues, 'is_ymd')); // solo YYYY-MM-DD válidas

  // 2) calcular montos según selección
  $monto_base  = count($selected_dues) * 1000;
  $monto_total = $monto_base + $mora;

  // 3) la fecha_x_pagar que vamos a guardar en residentes será el ÚLTIMO mes seleccionado
  $fecha_x_pagar = null;
  if ($selected_dues) {
    sort($selected_dues);
    $fecha_x_pagar = end($selected_dues);
  }

  // Validaciones mínimas
  $errors=[];
  if($id<=0)                         $errors[]="ID inválido.";
  if(!required($edif_apto))          $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos))  $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_in))          $errors[]="Cédula es obligatoria.";
  $cedula_digits = digits_only($cedula_in);
  if($cedula_in && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";

  if($errors){
    $_SESSION['errors']=$errors; $_SESSION['old']=$_POST; header('Location:?action=pagar&id='.$id); exit;
  }

  // 4) actualizar residente (dejamos registro de la última fecha por pagar)
  $stmt=$pdo->prepare(
    "UPDATE residentes SET
      edif_apto=?, nombres_apellidos=?, cedula=?, codigo=?, telefono=?,
      fecha_x_pagar=?, fecha_pagada=?, mora=?, monto_a_pagar=?, monto_pagado=?, no_recurrente=?
     WHERE id=?"
  );
  $stmt->execute([
    $edif_apto,
    $nombres_apellidos,
    $cedula_digits,
    $codigo ?: null,
    $telefono ?: null,
    $fecha_x_pagar,        // última cuota que está pagando
    $fecha_pagada,         // fecha en la que él dice que pagó (puede ser hoy o antes)
    $mora,
    $monto_base,           // lo que debería pagar según cuotas
    $monto_total,          // lo que efectivamente pagó incluyendo mora
    $no_recurrente,
    $id
  ]);

  // 5) insertar movimiento en tabla de pagos_residentes
  //    aquí registramos el momento real de creación del recibo
  $stmt2 = $pdo->prepare(
    "INSERT INTO pagos_residentes
      (residente_id, fecha_recibo, meses_pagados, monto_base, mora, monto_total, nota)
     VALUES (?,?,?,?,?,?,?)"
  );
  $stmt2->execute([
    $id,
    date('Y-m-d H:i:s'),                     // momento en que se creó el recibo
    json_encode($selected_dues, JSON_UNESCAPED_UNICODE),
    $monto_base,
    $mora,
    $monto_total,
    null
  ]);

  header('Location:?updated=1'); exit;
}

/* ========== ELIMINAR RESIDENTE ========== */
if ($action === 'delete' && isset($_GET['id'])) {
  $id=(int)$_GET['id']; if($id>0){ $pdo->prepare("DELETE FROM residentes WHERE id=?")->execute([$id]); }
  header('Location:?deleted=1'); exit;
}

/*********** 4) Layout ***********/
function header_html($title='Residentes', $isFull=false){ ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($title)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
 body{background:#f6f7fb}.card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .table thead th{font-weight:600}
</style>
</head><body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm"><div class="container">
  <a class="navbar-brand fw-bold" href="?">RESIDENCIAL COOPNAMA II</a>
  <div class="ms-auto d-flex gap-2">
    <a href="?" class="btn btn-sm btn-outline-secondary">Residentes</a>
    <a href="?action=pagos" class="btn btn-sm btn-outline-primary">Pagos</a>
  </div>
</div></nav>
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

  // === CUOTAS: checkboxes -> resumen y hidden ===
  function recalcDueSelection(){
    var $boxes = $('.due-option:checked');
    var dates  = $boxes.map(function(){ return this.value; }).get();
    dates.sort();

    var count  = dates.length;
    var latest = count ? dates[dates.length-1] : '';

    $('#fecha_x_pagar').val(latest);

    $('#countSelected').text(count);
    var total = count * 1000;
    $('input[name="monto_a_pagar"]').val(total.toFixed(2));
    $('#totalSelected').text(
      total.toLocaleString('es-DO', {minimumFractionDigits:2, maximumFractionDigits:2})
    );
  }

  $(document).on('change', '.due-option', recalcDueSelection);
  recalcDueSelection();

  $(document).on('click', '.btn-delete', function(e){
    if (!confirm('¿Eliminar este registro?')) e.preventDefault();
  });
});
</script>

</body></html>
<?php }

/*********** 5) Vistas ***********/

/* 5.1 Listado simple (residentes) */
if ($action==='index') {
  header_html('Residentes', false);
  $rows=$pdo->query("SELECT * FROM residentes ORDER BY id DESC")->fetchAll();
  if(isset($_GET['saved']))   echo '<div class="alert alert-success">Registro agregado.</div>';
  if(isset($_GET['updated'])) echo '<div class="alert alert-info">Registro actualizado.</div>';
  if(isset($_GET['deleted'])) echo '<div class="alert alert-warning">Registro eliminado.</div>';
  ?>
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

  <div class="card">
    <div class="card-body">
      <h5 class="mb-3">LISTA DE COPROPIETARIOS</h5>
      <div class="table-responsive">
        <table id="tabla" class="table table-striped table-bordered align-middle">
          <thead class="table-light"><tr>
            <th>Edif/Apto</th><th>Nombres y Apellidos</th><th>Cédula</th><th>Teléfono</th><th>Acciones</th>
          </tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= e($r['edif_apto']) ?></td>
              <td><?= e($r['nombres_apellidos']) ?></td>
              <td><?= e(format_cedula($r['cedula'])) ?></td>
              <td><?= e($r['telefono']) ?></td>
              <td class="text-center">
                <a class="btn btn-primary btn-sm" href="?action=pagar&id=<?= (int)$r['id'] ?>">PAGAR / RECIBO</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php footer_html(); exit;
}

/* 5.2 Listado de pagos */
if ($action==='pagos') {
  header_html('Pagos', false);
  $pagos = $pdo->query("
    SELECT p.*, r.nombres_apellidos, r.edif_apto
    FROM pagos_residentes p
    JOIN residentes r ON r.id = p.residente_id
    ORDER BY p.id DESC
  ")->fetchAll();
  ?>
  <div class="card">
    <div class="card-body">
      <h5 class="mb-3">Pagos registrados</h5>
      <div class="table-responsive">
        <table id="tabla" class="table table-striped table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Residente</th>
              <th>Edif/Apto</th>
              <th>Fecha recibo</th>
              <th>Meses pagados</th>
              <th>Monto base</th>
              <th>Mora</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($pagos as $p):
              $meses = json_decode($p['meses_pagados'], true) ?: [];
              ?>
              <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= e($p['nombres_apellidos']) ?></td>
                <td><?= e($p['edif_apto']) ?></td>
                <td><?= e($p['fecha_recibo']) ?></td>
                <td>
                  <?php
                    $labels = [];
                    foreach($meses as $m){
                      $labels[] = fecha_larga_es($m);
                    }
                    echo e(implode(', ', $labels));
                  ?>
                </td>
                <td>RD$ <?= number_format($p['monto_base'],2,'.',',') ?></td>
                <td>RD$ <?= number_format($p['mora'],2,'.',',') ?></td>
                <td><strong>RD$ <?= number_format($p['monto_total'],2,'.',',') ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php footer_html(); exit;
}

/* 5.3 Formulario (Nuevo/pagar) */
if ($action==='new' || $action==='pagar') {
  $editing = $action==='pagar';
  $data=['id'=>null,'edif_apto'=>'','nombres_apellidos'=>'','cedula'=>'','codigo'=>'','telefono'=>'',
         'fecha_x_pagar'=>'','fecha_pagada'=>'','mora'=>'','monto_a_pagar'=>'','monto_pagado'=>'','no_recurrente'=>0];

  if($editing){
    $id=(int)($_GET['id'] ?? 0);
    if($id<=0){ header('Location:?'); exit; }
    $st=$pdo->prepare("SELECT * FROM residentes WHERE id=?"); $st->execute([$id]); $row=$st->fetch();
    if(!$row){ header('Location:?'); exit; } $data=array_merge($data,$row);
  }
  if(!empty($_SESSION['old'])) $data=array_merge($data,$_SESSION['old']);
  $errors=$_SESSION['errors'] ?? []; $_SESSION['old']=$_SESSION['errors']=null;

  header_html($editing?'Pagar mantenimiento':'Agregar residente', false);

  // Cálculo de cuotas pendientes
  $BASE_DUE   = '2025-10-05';
  $pendientes = cuotas_pendientes($BASE_DUE, $data['fecha_pagada'] ?: null);
  $cantidad   = count($pendientes);
  ?>
  <div class="row justify-content-center"><div class="col-lg-10">
    <form method="post" action="?action=<?= $editing?'update':'store' ?>">

      <?php if($editing): ?><input type="hidden" name="id" value="<?= (int)$data['id'] ?>"><?php endif; ?>

      <!-- CARD DATOS -->
      <div class="card"><div class="card-body">
        <h5 class="card-title mb-3"><?= $editing?'Pagar / Crear recibo':'Agregar residente' ?></h5>
        <?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $m) echo "<li>".e($m)."</li>"; ?></ul></div><?php endif; ?>

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Edif/Apto *</label>
            <input type="text" name="edif_apto" class="form-control" maxlength="60" value="<?=e($data['edif_apto'])?>" <?= $editing?'disabled':'' ?>>
          </div>
          <div class="col-md-5">
            <label class="form-label">Nombres y Apellidos *</label>
            <input type="text" name="nombres_apellidos" class="form-control" maxlength="255" value="<?=e($data['nombres_apellidos'])?>" <?= $editing?'disabled':'' ?>>
          </div>
          <div class="col-md-2">
            <label class="form-label">Cédula *</label>
            <input type="text" name="cedula" class="form-control" maxlength="13" value="<?=e(format_cedula($data['cedula']))?>" <?= $editing?'disabled':'' ?>>
          </div>
          <div class="col-md-2">
            <label class="form-label">Código</label>
            <input type="text" name="codigo" class="form-control" maxlength="30" value="<?=e($data['codigo'])?>" <?= $editing?'disabled':'' ?>>
          </div>

          <div class="col-md-3">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control" maxlength="50" value="<?=e($data['telefono'])?>" <?= $editing?'disabled':'' ?>>
          </div>

          <div class="col-md-3">
            <label class="form-label">Fecha pagada (ingresada por quien cobra)</label>
            <input type="date" name="fecha_pagada" class="form-control" value="<?= e($data['fecha_pagada']) ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Mora (si aplica)</label>
            <input type="text" name="mora" class="form-control" placeholder="0.00" value="<?= e($data['mora']) ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Monto a pagar (auto)</label>
            <input type="text" name="monto_a_pagar" class="form-control" value="0.00" readonly>
          </div>
        </div>
      </div></div>

      <!-- CARD CUOTAS PENDIENTES -->
      <div class="card mt-3"><div class="card-body">
        <h6 class="mb-2">Cuotas pendientes</h6>

        <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
          <span class="text-muted">
            Pendientes totales: <span class="badge bg-<?= $cantidad? 'warning text-dark':'success' ?>"><?= $cantidad ?></span>
          </span>
          <span class="text-muted">
            Seleccionadas: <strong id="countSelected">0</strong>
          </span>
          <span class="text-muted">
            Total seleccionado: <strong>RD$ <span id="totalSelected">0.00</span></strong>
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

        <input type="hidden" name="fecha_x_pagar" id="fecha_x_pagar" value="">
      </div></div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><?= $editing?'Crear recibo':'Guardar' ?></button>
        <a class="btn btn-outline-secondary" href="?">Cancelar</a>
      </div>

    </form>
  </div></div>
  <?php footer_html(); exit;
}

header('Location:?'); exit;
