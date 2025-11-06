<?php
/*************************************************
 * Listado de pagos (pagos_residentes)
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

try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (Throwable $e) {
  http_response_code(500);
  exit("DB error: " . htmlspecialchars($e->getMessage()));
}

/*********** 2) Helpers ***********/
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function digits_only($s){ return preg_replace('/\D+/','',$s); }
function format_cedula($d){
  $d=digits_only($d);
  return strlen($d)===11?substr($d,0,3).'-'.substr($d,3,7).'-'.substr($d,10,1):$d;
}
function fecha_larga_es($ymd){
  static $meses=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $ts = strtotime($ymd);
  if(!$ts) return $ymd;
  return '5 de '.$meses[(int)date('n',$ts)-1].' de '.date('Y',$ts);
}

function header_html_pagos($title='Pagos registrados'){ ?>
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
    <a class="navbar-brand fw-bold" href="index.php">RESIDENCIAL COOPNAMA II</a>
    <div class="ms-auto btn-group">
      <a href="index.php" class="btn btn-outline-primary btn-sm">Residentes</a>
      <a href="pagos.php" class="btn btn-primary btn-sm">Pagos</a>
    </div>
  </div>
</nav>
<main class="container my-4">
<?php }

function footer_html_pagos(){ ?>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  var $tbl = $('#tabla_pagos');
  if($tbl.length){
    var dt = $tbl.DataTable({
      pageLength: 10,
      lengthMenu: [5,10,25,50,100],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
      dom: 'tip',
      order: [[1,'desc']]
    });

    $('#globalSearch').on('input', function(){ dt.search(this.value).draw(); });
    $('#lenSelect').on('change', function(){ dt.page.len(parseInt(this.value,10)).draw(); });
    $('#lenSelect').val(dt.page.len());
  }
});
</script>
</body></html>
<?php }

/*********** 3) Consulta de pagos ***********/
$sql = "
  SELECT
    p.*,
    r.edif_apto,
    r.nombres_apellidos,
    r.cedula
  FROM pagos_residentes p
  LEFT JOIN residentes r ON r.id = p.residente_id
  ORDER BY p.fecha_recibo DESC
";
$rows = $pdo->query($sql)->fetchAll();

/*********** 4) Vista ***********/
header_html_pagos('Pagos registrados');
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

<!-- CARD TABLA PAGOS -->
<div class="card">
  <div class="card-body">
    <h5 class="mb-3">Pagos registrados</h5>
    <div class="table-responsive">
      <table id="tabla_pagos" class="table table-striped table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Fecha recibo</th>
            <th>Residente</th>
            <th>Edif/Apto</th>
            <th>Cédula</th>
            <th>Fecha pagada</th>
            <th>Meses pagados</th>
            <th>Monto base</th>
            <th>Mora</th>
            <th>Total</th>
            <th>Obs.</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $p): ?>
          <?php
            $meses = json_decode($p['meses_pagados'] ?? '[]', true) ?: [];
            $meses_legibles = [];
            foreach($meses as $f){
              $meses_legibles[] = fecha_larga_es($f);
            }
          ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= e($p['fecha_recibo']) ?></td>
            <td><?= e($p['nombres_apellidos']) ?></td>
            <td><?= e($p['edif_apto']) ?></td>
            <td><?= e(format_cedula($p['cedula'])) ?></td>
            <td><?= e($p['fecha_pagada']) ?></td>
            <td><?= e(implode(', ', $meses_legibles)) ?></td>
            <td><?= number_format((float)$p['monto_base'],2,'.',',') ?></td>
            <td><?= number_format((float)$p['mora'],2,'.',',') ?></td>
            <td><?= number_format((float)$p['total'],2,'.',',') ?></td>
            <td><?= e($p['observaciones'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
footer_html_pagos();
