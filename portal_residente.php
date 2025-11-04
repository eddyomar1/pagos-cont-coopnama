<?php
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
function digits_only($s){ return preg_replace('/\D+/','',$s); }
function format_cedula($d){
  $d = digits_only($d);
  return strlen($d)===11 ? substr($d,0,3).'-'.substr($d,3,7).'-'.substr($d,10,1) : $d;
}

/* Mostrar solo últimos 4 dígitos y lo demás en asteriscos */
function mask_cedula($d){
  $d = digits_only($d);
  $len = strlen($d);
  if ($len <= 4) return $d; // por si viene algo raro
  return str_repeat('*', $len - 4) . substr($d, -4);
}

function cedula_valida($digits){
  $d = digits_only($digits); if(strlen($d)!==11) return false;
  $m=[1,2,1,2,1,2,1,2,1,2]; $s=0;
  for($i=0;$i<10;$i++){
    $p=$d[$i]*$m[$i];
    if($p>=10)$p=intdiv($p,10)+($p%10);
    $s+=$p;
  }
  return ( (10-($s%10))%10 ) == $d[10];
}
function is_ymd($s){ return is_string($s) && preg_match('~^\d{4}-\d{2}-\d{2}$~',$s); }
function fecha_larga_es($ymd){
  static $meses=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $ts = strtotime($ymd);
  return '5 de '.$meses[(int)date('n',$ts)-1].' de '.date('Y',$ts);
}

/*********** 3) Layout ***********/
function header_html($title='Mis pagos'){
  global $residente;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
 body{background:#f6f7fb}
 .card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .table thead th{font-weight:600}
 .nav-link.active{background:#fff;border-color:#dee2e6 #dee2e6 #fff;}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="portal_residente.php">RESIDENCIAL COOPNAMA II</a>
    <div class="ms-auto d-flex gap-2">
      <?php if(!empty($residente)): ?>
        <span class="navbar-text me-2 d-none d-sm-inline">
          Sesión de: <strong><?= e($residente['nombres_apellidos']) ?></strong>
        </span>
        <!-- <a href="?logout=1" class="btn btn-sm btn-outline-danger">No soy esta persona</a> -->
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container my-4">
<?php
}
function footer_html(){ ?>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  var $tbl = $('#tablaPagos');
  if ($tbl.length) {
    $tbl.DataTable({
      order: [[0,'desc']],
      pageLength: 10,
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
      dom: 'tip'
    });
  }
});
</script>
</body>
</html>
<?php }

/*********** 4) Lógica de “login” por cédula ***********/
$error = '';
$residente = null;
$pagos = [];

/* Botón "No soy esta persona" */
if (isset($_GET['logout'])) {
  setcookie('cedula_residente','',time()-3600,'/');
  header('Location: portal_residente.php');
  exit;
}

/* Si envió el formulario de cédula */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cedula'])) {
  $cedula_in = trim($_POST['cedula']);
  $cedula_digits = digits_only($cedula_in);

  if (!cedula_valida($cedula_digits)) {
    $error = "La cédula no es válida. Verifícala e inténtalo de nuevo.";
  } else {
    $stmt = $pdo->prepare("SELECT * FROM residentes WHERE cedula = ? LIMIT 1");
    $stmt->execute([$cedula_digits]);
    $residente = $stmt->fetch();

    if (!$residente) {
      $error = "No encontramos un residente con esa cédula.";
    } else {
      // Guardar cédula en cookie (1 año)
      setcookie('cedula_residente', $residente['cedula'], [
        'expires'  => time() + 60*60*24*365,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      header('Location: portal_residente.php');
      exit;
    }
  }
}

/* Si ya tiene cookie, cargar residente automáticamente */
if (!$residente && isset($_COOKIE['cedula_residente'])) {
  $ced = digits_only($_COOKIE['cedula_residente']);
  if ($ced) {
    $stmt = $pdo->prepare("SELECT * FROM residentes WHERE cedula = ? LIMIT 1");
    $stmt->execute([$ced]);
    $residente = $stmt->fetch();
    if (!$residente) {
      // Cookie inválida, la borramos
      setcookie('cedula_residente','',time()-3600,'/');
    }
  }
}

/* Si ya tenemos residente válido, cargamos sus pagos */
if ($residente) {
  $st = $pdo->prepare("SELECT * FROM pagos_residentes WHERE residente_id = ? ORDER BY id DESC");
  $st->execute([$residente['id']]);
  $pagos = $st->fetchAll();
}

/*********** 5) Vistas ***********/

/* --- Vista: formulario de cédula (sin residente aún) --- */
if (!$residente) {
  header_html('Consulta tus pagos');
  ?>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h4 class="mb-3 text-center">Consulta de pagos</h4>
          <p class="text-muted mb-4 text-center">
            Ingresa tu número de cédula para ver el historial de pagos de tu apartamento.
          </p>
          <?php if($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
          <?php endif; ?>
          <form method="post" autocomplete="off">
            <div class="mb-3">
              <label class="form-label">Cédula</label>
              <input type="text"
                     name="cedula"
                     class="form-control"
                     placeholder="000-0000000-0"
                     required>
              <div class="form-text">
                Solo la usarás la primera vez; luego el sistema te reconocerá automáticamente.
              </div>
            </div>
            <div class="d-grid">
              <button class="btn btn-primary">Ver mis pagos</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php
  footer_html();
  exit;
}

/* --- Vista: tabla de pagos del residente --- */
header_html('Mis pagos');
?>
<div class="row">
  <div class="col-12">
    <div class="mb-4">
      <h2 class="fw-bold mb-1">Hola, <?= e($residente['nombres_apellidos']) ?></h2>
      <p class="text-muted mb-0">
        Bienvenido a tu panel de pagos del <strong>RESIDENCIAL COOPNAMA II</strong>.
      </p>
      <p class="text-muted">
        Cédula registrada: <strong><?= e(mask_cedula($residente['cedula'])) ?></strong>
      </p>
      <p class="text-muted small">
        ¿Cambiaste de cédula? Puedes actualizarla en
        <a href="cambiar_cedula.php">esta página</a>.
      </p>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h5 class="mb-3">Pagos registrados a tu nombre</h5>

    <?php if(!$pagos): ?>
      <div class="alert alert-info mb-0">
        Aún no tenemos pagos registrados para tu usuario.
        Si crees que esto es un error, contacta a la administración.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table id="tablaPagos" class="table table-striped table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Fecha recibo</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($pagos as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= e($p['fecha_recibo']) ?></td>
              <td><?= number_format((float)$p['total'],2,'.',',') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
footer_html();
