<?php
/*********** Llaves digitales de un solo uso (QR) ***********/
header_html('Llaves digitales');

// Crea tabla si no existe
$pdo->exec("CREATE TABLE IF NOT EXISTS llaves_qr (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cedula VARCHAR(50) NOT NULL,
  nombre VARCHAR(180) NULL,
  apartamento VARCHAR(80) NULL,
  codigo VARCHAR(64) NOT NULL UNIQUE,
  estado ENUM('generada','entrada','salida','expirada') NOT NULL DEFAULT 'generada',
  usado_entrada DATETIME NULL,
  usado_salida DATETIME NULL,
  expira_despues_salida DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Expira automáticamente las que ya pasaron 10 min luego de salir
$pdo->exec("UPDATE llaves_qr SET estado='expirada' WHERE estado='salida' AND expira_despues_salida IS NOT NULL AND expira_despues_salida < NOW()");

$alert = null;

// Usar llave (entrada/salida)
if (isset($_GET['use'], $_GET['tipo'])) {
  $code = trim($_GET['use']);
  $tipo = $_GET['tipo'];
  $stmt = $pdo->prepare("SELECT * FROM llaves_qr WHERE codigo=? LIMIT 1");
  $stmt->execute([$code]);
  $row = $stmt->fetch();
  if (!$row) {
    $alert = ['danger','Código no encontrado.'];
  } else {
    if ($row['estado'] === 'expirada') {
      $alert = ['danger','La llave ya está expirada.'];
    } elseif ($tipo === 'entrada') {
      if ($row['usado_entrada']) {
        $alert = ['warning','Ya se registró la entrada con esta llave.'];
      } else {
        $now = date('Y-m-d H:i:s');
        $upd = $pdo->prepare("UPDATE llaves_qr SET estado='entrada', usado_entrada=? WHERE id=?");
        $upd->execute([$now, $row['id']]);
        $alert = ['success','Entrada registrada.'];
      }
    } elseif ($tipo === 'salida') {
      if (!$row['usado_entrada']) {
        $alert = ['danger','Primero debes registrar la entrada con esta llave.'];
      } elseif ($row['usado_salida']) {
        $alert = ['warning','La salida ya fue registrada.'];
      } else {
        $now = date('Y-m-d H:i:s');
        $exp = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $upd = $pdo->prepare("UPDATE llaves_qr SET estado='salida', usado_salida=?, expira_despues_salida=? WHERE id=?");
        $upd->execute([$now, $exp, $row['id']]);
        $alert = ['success','Salida registrada. La llave expirará en 10 minutos.'];
      }
    }
  }
}

// Crear nueva llave
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cedula = digits_only(body('cedula'));
  $nombre = trim((string)body('nombre'));
  $apto   = trim((string)body('apto'));

  if ($cedula === '' || strlen($cedula) < 7) {
    $alert = ['danger','Ingresa una cédula válida.'];
  } else {
    $codigo = substr(bin2hex(random_bytes(16)),0,24);
    $stmt = $pdo->prepare("INSERT INTO llaves_qr (cedula, nombre, apartamento, codigo) VALUES (?,?,?,?)");
    if ($stmt->execute([$cedula, $nombre ?: null, $apto ?: null, $codigo])) {
      $alert = ['success','Llave generada. Escanea el QR o comparte el código.'];
      $generated = ['codigo'=>$codigo,'cedula'=>$cedula,'nombre'=>$nombre,'apto'=>$apto];
    } else {
      $alert = ['danger','No se pudo crear la llave.'];
    }
  }
}

// Listado reciente
$llaves = $pdo->query("SELECT * FROM llaves_qr ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<div class="card mb-3"><div class="card-body">
  <h1 class="fw-bold mb-3">Llaves digitales (QR)</h1>
  <p class="text-muted">Llaves de un solo uso: entrada y salida solo una vez. Tras registrar la salida, expiran en 10 minutos.</p>
  <?php if($alert): ?>
    <div class="alert alert-<?= e($alert[0]) ?>"><?= e($alert[1]) ?></div>
  <?php endif; ?>

  <form class="row g-3" method="post">
    <div class="col-md-3">
      <label class="form-label">Cédula *</label>
      <input type="text" name="cedula" class="form-control" maxlength="20" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Nombre (opcional)</label>
      <input type="text" name="nombre" class="form-control" placeholder="Nombre del visitante o residente">
    </div>
    <div class="col-md-3">
      <label class="form-label">Apartamento (opcional)</label>
      <input type="text" name="apto" class="form-control" placeholder="Ej. 01 Apto 2A">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-primary w-100">Generar llave</button>
    </div>
  </form>
</div></div>

<?php if(isset($generated)): ?>
  <div class="card mb-3"><div class="card-body">
    <h5 class="mb-3">Llave creada</h5>
    <div class="row g-3 align-items-center">
      <div class="col-md-4 text-center">
        <div id="qr-container"></div>
        <div class="small text-muted mt-2">Escanea este QR para registrar entrada/salida.</div>
      </div>
      <div class="col-md-8">
        <p class="mb-1"><strong>Código:</strong> <code><?= e($generated['codigo']) ?></code></p>
        <p class="mb-1"><strong>Cédula:</strong> <?= e(format_cedula($generated['cedula'])) ?></p>
        <?php if($generated['nombre']): ?><p class="mb-1"><strong>Nombre:</strong> <?= e($generated['nombre']) ?></p><?php endif; ?>
        <?php if($generated['apto']): ?><p class="mb-1"><strong>Apartamento:</strong> <?= e($generated['apto']) ?></p><?php endif; ?>
        <div class="alert alert-info mt-2 mb-0">
          La salida debe registrarse con la misma llave. Tras la salida, caduca a los 10 minutos.
        </div>
      </div>
    </div>
  </div></div>
<?php endif; ?>

<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Últimas 50 llaves</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle" id="tabla">
      <thead class="table-light">
        <tr>
          <th>Código</th><th>Cédula</th><th>Nombre</th><th>Apto</th><th>Estado</th><th>Entrada</th><th>Salida</th><th>Expira</th><th class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($llaves as $l): ?>
          <tr>
            <td><code><?= e($l['codigo']) ?></code></td>
            <td><?= e(format_cedula($l['cedula'])) ?></td>
            <td><?= e($l['nombre']) ?></td>
            <td><?= e($l['apartamento']) ?></td>
            <td>
              <?php
                $badge = 'secondary';
                if ($l['estado']==='generada') $badge='primary';
                elseif($l['estado']==='entrada') $badge='info';
                elseif($l['estado']==='salida') $badge='warning';
                elseif($l['estado']==='expirada') $badge='dark';
              ?>
              <span class="badge text-bg-<?= $badge ?> text-capitalize"><?= e($l['estado']) ?></span>
            </td>
            <td><?= e($l['usado_entrada'] ?? '—') ?></td>
            <td><?= e($l['usado_salida'] ?? '—') ?></td>
            <td><?= e($l['expira_despues_salida'] ?? '—') ?></td>
            <td class="text-center d-flex gap-1">
              <a class="btn btn-sm btn-outline-primary" href="?action=keys&use=<?= urlencode($l['codigo']) ?>&tipo=entrada">Entrada</a>
              <a class="btn btn-sm btn-outline-success" href="?action=keys&use=<?= urlencode($l['codigo']) ?>&tipo=salida">Salida</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<?php if(isset($generated)): ?>
<script>
  (function(){
    const code = <?= json_encode($generated['codigo']) ?>;
    new QRCode(document.getElementById('qr-container'), {
      text: code,
      width: 220,
      height: 220,
      correctLevel: QRCode.CorrectLevel.H
    });
  })();
</script>
<?php endif; ?>
<?php footer_html(); ?>
