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
function mask_cedula($d){
  $d = digits_only($d);
  $len = strlen($d);
  if ($len <= 4) return $d;
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

/*********** 3) Layout ***********/
function header_html($title='Cambiar cédula'){
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
 body{background:#f6f7fb}
 .card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="portal_residente.php">RESIDENCIAL COOPNAMA II</a>
    <div class="ms-auto">
      <a href="portal_residente.php" class="btn btn-sm btn-outline-secondary">Volver al portal</a>
    </div>
  </div>
</nav>
<main class="container my-4">
<?php
}
function footer_html(){ ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php }

/*********** 4) Lógica de cambio de cédula ***********/
$error   = '';
$success = '';
$step    = 1; // 1 = pedir cédula actual, 2 = pedir nueva cédula, 3 = éxito

$residenteSesion = isset($_SESSION['cambio_residente']) ? $_SESSION['cambio_residente'] : null;
if ($residenteSesion) {
  $step = 2;
}

/* Gestión de formularios */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = $_POST['accion'] ?? '';

  if ($accion === 'verificar_actual') {
    // Paso 1: verificar cédula actual
    $cedula_actual_in = trim($_POST['cedula_actual'] ?? '');
    $cedula_actual    = digits_only($cedula_actual_in);

    if (!cedula_valida($cedula_actual)) {
      $error = "La cédula actual no es válida.";
      $step  = 1;
    } else {
      $st = $pdo->prepare("SELECT id, nombres_apellidos, cedula FROM residentes WHERE cedula = ? LIMIT 1");
      $st->execute([$cedula_actual]);
      $row = $st->fetch();

      if (!$row) {
        $error = "No encontramos un residente con esa cédula.";
        $step  = 1;
      } else {
        // Guardamos en sesión para el segundo paso
        $_SESSION['cambio_residente'] = $row;
        $residenteSesion = $row;
        $step = 2;
      }
    }

  } elseif ($accion === 'guardar_nueva') {
    // Paso 2: guardar nueva cédula
    if (!$residenteSesion) {
      $error = "La sesión del cambio de cédula ha caducado. Vuelve a empezar.";
      $step  = 1;
    } else {
      $cedula_nueva_in  = trim($_POST['cedula_nueva'] ?? '');
      $cedula_conf_in   = trim($_POST['cedula_confirm'] ?? '');

      // Siempre trabajamos con solo dígitos
      $cedula_nueva     = digits_only($cedula_nueva_in);
      $cedula_conf      = digits_only($cedula_conf_in);
      $cedula_actual_bd = digits_only($residenteSesion['cedula']);

      if ($cedula_nueva === '' || $cedula_conf === '') {
        $error = "Debes escribir y confirmar la nueva cédula.";
        $step  = 2;

      } elseif ($cedula_nueva !== $cedula_conf) {
        $error = "La nueva cédula y su confirmación no coinciden.";
        $step  = 2;

      } elseif (!cedula_valida($cedula_nueva)) {
        $error = "La nueva cédula no es válida.";
        $step  = 2;

      } elseif ($cedula_nueva === $cedula_actual_bd) {
        // Aquí cubrimos el caso de que escriban la misma cédula que ya tenían
        $error = "La nueva cédula no puede ser igual a la cédula actual.";
        $step  = 2;

      } else {
        // Verificar que NO exista otro residente con esa cédula
        $st = $pdo->prepare("SELECT id FROM residentes WHERE cedula = ? AND id <> ? LIMIT 1");
        $st->execute([$cedula_nueva, $residenteSesion['id']]);
        $otro = $st->fetch();

        if ($otro) {
          $error = "Ya existe un residente registrado con esa nueva cédula.";
          $step  = 2;
        } else {
          // Actualizar cédula en la base de datos
          $upd = $pdo->prepare("UPDATE residentes SET cedula = ? WHERE id = ?");
          $upd->execute([$cedula_nueva, $residenteSesion['id']]);

          // Actualizar cookie si coincide con la cédula antigua
          if (isset($_COOKIE['cedula_residente'])) {
            $cookieCed = digits_only($_COOKIE['cedula_residente']);
            if ($cookieCed === $cedula_actual_bd) {
              setcookie('cedula_residente', $cedula_nueva, [
                'expires'  => time() + 60*60*24*365,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
              ]);
            }
          }

          $mask_old = mask_cedula($cedula_actual_bd);
          $mask_new = mask_cedula($cedula_nueva);
          $success  = "La cédula se actualizó correctamente de {$mask_old} a {$mask_new}.";
          unset($_SESSION['cambio_residente']);
          $residenteSesion = null;
          $step = 3;
        }
      }
    }
  }
}

/*********** 5) Vistas ***********/
header_html('Cambiar cédula');

if ($error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($step === 1): ?>
  <!-- Paso 1: Verificar cédula actual -->
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h4 class="mb-3 text-center">Verificar cédula actual</h4>
          <p class="text-muted">
            Para cambiar tu cédula, primero necesitamos confirmar la cédula que está registrada actualmente en el sistema.
          </p>
          <form method="post" autocomplete="off">
            <input type="hidden" name="accion" value="verificar_actual">
            <div class="mb-3">
              <label class="form-label">Cédula actual</label>
              <input type="text"
                     name="cedula_actual"
                     class="form-control"
                     placeholder="000-0000000-0"
                     required>
            </div>
            <div class="d-grid">
              <button class="btn btn-primary">Continuar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($step === 2 && $residenteSesion): ?>
  <!-- Paso 2: Introducir nueva cédula -->
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h4 class="mb-3 text-center">Ingresar nueva cédula</h4>
          <p class="text-muted">
            Residente encontrado:
            <strong><?= e($residenteSesion['nombres_apellidos']) ?></strong><br>
            Cédula actual: <strong><?= e(mask_cedula($residenteSesion['cedula'])) ?></strong>
          </p>
          <form method="post" autocomplete="off">
            <input type="hidden" name="accion" value="guardar_nueva">
            <div class="mb-3">
              <label class="form-label">Nueva cédula</label>
              <input type="text"
                     name="cedula_nueva"
                     class="form-control"
                     placeholder="000-0000000-0"
                     required>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirmar nueva cédula</label>
              <input type="text"
                     name="cedula_confirm"
                     class="form-control"
                     placeholder="000-0000000-0"
                     required>
            </div>
            <div class="d-grid">
              <button class="btn btn-primary">Guardar cambios</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($step === 3 && $success): ?>
  <!-- Paso 3: Éxito -->
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body text-center">
          <h4 class="mb-3">Cédula actualizada</h4>
          <div class="alert alert-success"><?= e($success) ?></div>
          <p class="text-muted">
            Ya puedes volver a tu panel de pagos.
          </p>
          <a href="portal_residente.php" class="btn btn-primary">Ir al portal de pagos</a>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php
footer_html();
