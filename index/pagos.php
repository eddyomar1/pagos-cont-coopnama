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

$viewAction = $_GET['action'] ?? 'index';
if ($viewAction === 'factura') {
  $facturaId = (int)($_GET['id'] ?? 0);
  $factura = null;
  if ($facturaId > 0) {
    try{
      $st = $pdo->prepare("
        SELECT
          p.*,
          r.edif_apto,
          r.nombres_apellidos,
          r.cedula,
          r.telefono
        FROM pagos_residentes p
        LEFT JOIN residentes r ON r.id = p.residente_id
        WHERE p.id = ?
        LIMIT 1
      ");
      $st->execute([$facturaId]);
      $factura = $st->fetch();
    }catch(Throwable $e){
      app_log('Error cargando factura de pago '.$facturaId.': '.$e->getMessage());
      $factura = null;
    }
  }

  render_header('Factura de pago','pagos');
  ?>
  <style>
    .factura-linea{margin-bottom:.4rem;}
    .factura-linea strong{display:inline-block;min-width:140px;}
    .factura-meses{display:flex;flex-wrap:wrap;gap:.35rem;}
    @media print{
      .topbar,.sidebar,.sidebar-backdrop,.print-actions{display:none !important;}
      .content{margin-left:0 !important;padding:0 !important;}
      .content-inner{max-width:none !important;}
      .card{box-shadow:none !important;border:1px solid #d1d5db !important;}
    }
  </style>

  <?php if(!$factura): ?>
    <div class="card">
      <div class="card-body">
        <div class="alert alert-danger mb-3">No se encontró el pago solicitado.</div>
        <a href="index.php?page=pagos" class="btn btn-outline-secondary">Volver a pagos registrados</a>
      </div>
    </div>
  <?php else: ?>
    <?php
      $mesesFactura = json_decode($factura['meses_pagados'] ?? '[]', true) ?: [];
      $mesesLegibles = [];
      foreach($mesesFactura as $mf){
        $mesesLegibles[] = fecha_larga_es($mf);
      }
      $isAnulacionFactura = (($factura['tipo'] ?? 'pago') === 'anulacion') || ((float)($factura['total'] ?? 0) < 0);
    ?>
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3 print-actions">
          <a href="index.php?page=pagos" class="btn btn-outline-secondary">Volver</a>
          <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir factura</button>
        </div>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h4 class="mb-1">Factura de pago #<?= (int)$factura['id'] ?></h4>
            <div class="text-muted">Fecha de recibo: <?= e((string)$factura['fecha_recibo']) ?></div>
          </div>
          <?php if($isAnulacionFactura): ?>
            <span class="badge text-bg-danger">Registro de anulación</span>
          <?php else: ?>
            <span class="badge text-bg-success">Pago aplicado</span>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="factura-linea"><strong>Residente:</strong> <?= e((string)($factura['nombres_apellidos'] ?? '')) ?></div>
            <div class="factura-linea"><strong>Edif/Apto:</strong> <?= e((string)($factura['edif_apto'] ?? '')) ?></div>
            <div class="factura-linea"><strong>Cedula:</strong> <?= e(format_cedula((string)($factura['cedula'] ?? ''))) ?></div>
            <div class="factura-linea"><strong>Telefono:</strong> <?= e((string)($factura['telefono'] ?? '')) ?></div>
          </div>
          <div class="col-lg-6">
            <div class="factura-linea"><strong>Fecha pagada:</strong> <?= e((string)($factura['fecha_pagada'] ?? '')) ?></div>
            <div class="factura-linea"><strong>Observaciones:</strong> <?= e((string)($factura['observaciones'] ?? '')) ?></div>
          </div>
        </div>

        <hr>

        <h6 class="mb-2">Meses pagados</h6>
        <?php if(!$mesesLegibles): ?>
          <div class="text-muted mb-3">Sin meses asociados.</div>
        <?php else: ?>
          <div class="factura-meses mb-3">
            <?php foreach($mesesLegibles as $mesTxt): ?>
              <span class="badge rounded-pill text-bg-light border"><?= e($mesTxt) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <tbody>
              <tr>
                <th style="width:220px;">Monto base</th>
                <td>RD$ <?= number_format((float)($factura['monto_base'] ?? 0), 2, '.', ',') ?></td>
              </tr>
              <tr>
                <th>Mora</th>
                <td>RD$ <?= number_format((float)($factura['mora'] ?? 0), 2, '.', ',') ?></td>
              </tr>
              <tr class="table-light">
                <th>Total</th>
                <td><strong>RD$ <?= number_format((float)($factura['total'] ?? 0), 2, '.', ',') ?></strong></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php
  render_footer();
  return;
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
    <style>
      /* Evita que "Meses pagados" fuerce ancho excesivo */
      #tabla_pagos td.col-meses{white-space:normal;max-width:420px;}
      #tabla_pagos td.col-meses .mes-badges{display:flex;flex-wrap:wrap;gap:.25rem;}
      #tabla_pagos td.col-meses .mes-badge{border:1px solid rgba(0,0,0,.08);background:#f8fafc;color:#0f172a;font-weight:500;}
      #tabla_pagos tbody tr.row-pago{cursor:pointer;}
      #tabla_pagos tbody tr.row-pago:focus{outline:2px solid rgba(37,99,235,.45);outline-offset:-2px;}
    </style>
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
              // Ej: "5 de febrero de 2026" -> "febrero de 2026"
              $txt = fecha_larga_es($f);
              $meses_legibles[] = preg_replace('~^\\d+\\s+de\\s+~u', '', $txt);
            }
            $tipo = $p['tipo'] ?? 'pago';
            $isAnulacion = ($tipo === 'anulacion') || ((float)($p['total'] ?? 0) < 0);
            $isAnulado = !$isAnulacion && isset($anulados[(int)$p['id']]);
            $rowClass = $isAnulacion ? 'table-danger' : '';
            $facturaUrl = 'index.php?page=pagos&action=factura&id='.(int)$p['id'];
          ?>
          <tr
            class="<?= trim($rowClass.' row-pago') ?>"
            tabindex="0"
            role="button"
            data-factura-url="<?= e($facturaUrl) ?>"
            aria-label="Abrir factura del pago #<?= (int)$p['id'] ?>"
          >
            <td><?= (int)$p['id'] ?></td>
            <td><?= e($p['fecha_recibo']) ?></td>
            <td><?= e($p['nombres_apellidos']) ?></td>
            <td><?= e($p['edif_apto']) ?></td>
            <td><?= e(format_cedula($p['cedula'])) ?></td>
            <td><?= e($p['fecha_pagada']) ?></td>
            <td class="col-meses">
              <?php if(!$meses_legibles): ?>
                <span class="text-muted">—</span>
              <?php else: ?>
                <div class="mes-badges">
                  <?php foreach($meses_legibles as $m): ?>
                    <span class="badge rounded-pill mes-badge"><?= e($m) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
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

<script>
$(function(){
  var $tablaPagos = $('#tabla_pagos');
  if (!$tablaPagos.length) return;

  $tablaPagos.on('click', 'tbody tr[data-factura-url]', function(ev){
    if ($(ev.target).closest('a,button,input,select,textarea,label,form').length) return;
    var url = $(this).data('factura-url');
    if (url) window.location.href = url;
  });

  $tablaPagos.on('keydown', 'tbody tr[data-factura-url]', function(ev){
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    ev.preventDefault();
    var url = $(this).data('factura-url');
    if (url) window.location.href = url;
  });
});
</script>

<?php
render_footer();
