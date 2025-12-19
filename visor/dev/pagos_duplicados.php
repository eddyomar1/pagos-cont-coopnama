<?php
// Dev: listado de pagos con misma fecha/hora/minuto para depurar duplicados
$_GET['action'] = 'dev';

require __DIR__ . '/../config.php';
require __DIR__ . '/../layout.php';

$provided = $_GET['clave'] ?? $_POST['clave'] ?? '';
if ($provided !== DEV_ACCESS_KEY) {
  http_response_code(404);
  exit('No autorizado');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $delId = (int)$_POST['delete_id'];
  $stmt = $pdo->prepare("DELETE FROM pagos_residentes WHERE id = ?");
  if ($stmt->execute([$delId])) {
    $message = "Pago #{$delId} eliminado.";
  } else {
    $message = "No se pudo eliminar el pago #{$delId}: " . $stmt->errorInfo()[2];
  }
}

// Obtener pagos y agruparlos por fecha_recibo al minuto
$grupos = [];
try{
  $rows = $pdo->query("SELECT p.*, r.nombres_apellidos, r.edif_apto, r.cedula FROM pagos_residentes p LEFT JOIN residentes r ON r.id = p.residente_id ORDER BY p.fecha_recibo DESC")->fetchAll();
  foreach ($rows as $p) {
    $key = date('Y-m-d H:i', strtotime($p['fecha_recibo']));
    $grupos[$key][] = $p;
  }
}catch(Throwable $e){
  $message = 'Error cargando pagos: '.$e->getMessage();
  $grupos = [];
}

render_header('Pagos duplicados','dev');
?>
<div class="card mb-3">
  <div class="card-body d-flex align-items-center justify-content-between">
    <div>
      <h5 class="mb-0">Pagos con misma fecha/hora (minuto)</h5>
      <p class="text-muted mb-0 small">Usa esta vista para eliminar pagos duplicados o erróneos.</p>
    </div>
    <span class="badge text-bg-info"><?= count($grupos) ?> grupos</span>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert-info"><?= e($message) ?></div>
<?php endif; ?>

<?php foreach($grupos as $minuto=>$items): ?>
  <?php if (count($items) < 2) continue; ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Minuto: <?= e($minuto) ?></div>
        <span class="badge text-bg-warning">Pagos: <?= count($items) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle table-bordered">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Residente</th>
              <th>Edif/Apto</th>
              <th>Cédula</th>
              <th>Fecha recibo</th>
              <th>Fecha pagada</th>
              <th>Total</th>
              <th>Obs.</th>
              <th class="text-center">Eliminar</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($items as $p): ?>
              <tr>
                <td class="text-muted"><?= (int)$p['id'] ?></td>
                <td><?= e($p['nombres_apellidos'] ?? '') ?></td>
                <td><?= e($p['edif_apto'] ?? '') ?></td>
                <td><?= e(format_cedula($p['cedula'] ?? '')) ?></td>
                <td><?= e($p['fecha_recibo']) ?></td>
                <td><?= e($p['fecha_pagada']) ?></td>
                <td>RD$ <?= number_format((float)$p['total'],2,'.',',') ?></td>
                <td><?= e($p['observaciones'] ?? '') ?></td>
                <td class="text-center">
                  <form method="post" onsubmit="return confirm('¿Eliminar pago #<?= (int)$p['id'] ?>?');">
                    <input type="hidden" name="clave" value="<?= e(DEV_ACCESS_KEY) ?>">
                    <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$grupos || !array_filter($grupos, fn($g)=>count($g)>=2)): ?>
  <div class="alert alert-success">No se encontraron pagos duplicados por minuto.</div>
<?php endif; ?>

<?php render_footer(); ?>
