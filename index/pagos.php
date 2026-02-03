<?php
// index/pagos.php – listado de pagos

require __DIR__ . '/init.php';

// Acción: anular un pago (crea un registro inverso)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'anular') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    header('Location: index.php?page=pagos&error=Pago%20inv%C3%A1lido'); exit;
  }

  try{
    $pdo->beginTransaction();

    // Bloquea el registro para evitar doble anulación simultánea
    $st = $pdo->prepare("SELECT * FROM pagos_residentes WHERE id = ? FOR UPDATE");
    $st->execute([$id]);
    $orig = $st->fetch();
    if (!$orig) {
      throw new Exception('Pago no encontrado.');
    }

    $tipoOrig = $orig['tipo'] ?? 'pago';
    if ($tipoOrig === 'anulacion' || (float)($orig['total'] ?? 0) < 0) {
      throw new Exception('No se puede anular un registro de anulación.');
    }

    $stChk = $pdo->prepare("SELECT id FROM pagos_residentes WHERE anulado_de = ? LIMIT 1");
    $stChk->execute([$id]);
    if ($stChk->fetchColumn()) {
      throw new Exception('Este pago ya fue anulado.');
    }

    $monto_base = -1 * (float)($orig['monto_base'] ?? 0);
    $mora       = -1 * (float)($orig['mora'] ?? 0);
    $total      = -1 * (float)($orig['total'] ?? 0);
    $obsOrig    = trim((string)($orig['observaciones'] ?? ''));
    $obsNew     = 'ANULACION de pago #'.$id.($obsOrig ? ' | Original: '.$obsOrig : '');

    $ins = $pdo->prepare(
      "INSERT INTO pagos_residentes
       (residente_id, fecha_recibo, fecha_pagada, meses_pagados,
        monto_base, mora, total, observaciones, tipo, anulado_de)
       VALUES (?,?,?,?,?,?,?,?, 'anulacion', ?)"
    );
    $ins->execute([
      (int)$orig['residente_id'],
      date('Y-m-d H:i:s'),
      date('Y-m-d'),
      $orig['meses_pagados'],
      $monto_base,
      $mora,
      $total,
      $obsNew,
      $id,
    ]);

    $pdo->commit();
    header('Location: index.php?page=pagos&anulado=1'); exit;
  }catch(Throwable $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    app_log('Error anulando pago '.$id.': '.$e->getMessage());
    $msg = urlencode($e->getMessage());
    header("Location: index.php?page=pagos&error={$msg}"); exit;
  }
}

$rows = [];
try{
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
}catch(Throwable $e){
  app_log('Error listando pagos: '.$e->getMessage());
  $rows = [];
}

// Set de ids que ya fueron anulados (para deshabilitar botón)
$anulados = [];
foreach($rows as $p){
  if (!empty($p['anulado_de'])) {
    $anulados[(int)$p['anulado_de']] = true;
  }
}
$totalCobrado = 0.0;
foreach($rows as $p){
  $totalCobrado += (float)($p['total'] ?? 0);
}
$current_section = 'pagos';

render_header('Pagos registrados','pagos');
?>

<!-- CARD DE CONTROLES -->
<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-lg-6 d-flex align-items-center justify-content-lg-start gap-2">
        <label for="globalSearch" class="mb-0">Buscar:</label>
        <input id="globalSearch" type="search" class="form-control form-control-sm" placeholder="Buscar..." style="max-width:240px">
      </div>
      <div class="col-lg-6 d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2">
          <span>Mostrar</span>
          <select id="lenSelect" class="form-select form-select-sm" style="width:auto;">
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100" selected>100</option>
          </select>
          <span class="text-muted">registros</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CARD TABLA PAGOS -->
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
      <h5 class="mb-0">Pagos registrados</h5>
      <div class="fw-semibold text-success">Total cobrado: RD$ <?= number_format($totalCobrado,2,'.',',') ?></div>
    </div>
    <?php if(isset($_GET['anulado'])): ?>
      <div class="alert alert-success">Pago anulado correctamente (se registró una operación inversa).</div>
    <?php endif; ?>
    <?php if(!empty($_GET['error'])): ?>
      <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table id="tabla_pagos" class="table table-striped table-bordered align-middle table-nowrap">
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
            <th class="text-center">Acciones</th>
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
            $tipo = $p['tipo'] ?? 'pago';
            $isAnulacion = ($tipo === 'anulacion') || ((float)($p['total'] ?? 0) < 0);
            $isAnulado = !$isAnulacion && isset($anulados[(int)$p['id']]);
            $rowClass = $isAnulacion ? 'table-danger' : '';
          ?>
          <tr class="<?= $rowClass ?>">
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
            <td class="text-center">
              <?php if($isAnulacion): ?>
                <span class="badge text-bg-danger">Anulación</span>
              <?php elseif($isAnulado): ?>
                <span class="badge text-bg-secondary">Anulado</span>
              <?php else: ?>
                <form method="post" action="index.php?page=pagos" onsubmit="return confirm('¿Anular este pago? Se creará un registro inverso y se ajustarán los totales.');" class="d-inline">
                  <input type="hidden" name="accion" value="anular">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">Anular</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
render_footer();
