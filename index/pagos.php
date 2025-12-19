<?php
// index/pagos.php – listado de pagos

require __DIR__ . '/init.php';

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
render_footer();
