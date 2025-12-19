<?php
// index/exonerados.php – listado de residentes exonerados

require __DIR__ . '/init.php';

try{
  $sql = "SELECT id, edif_apto, nombres_apellidos, cedula, telefono, exonerado_desde FROM residentes WHERE exonerado = 1 ORDER BY exonerado_desde DESC";
  $rows = $pdo->query($sql)->fetchAll();
}catch(Throwable $e){
  $rows = [];
  app_log('Error listando exonerados: '.$e->getMessage());
}

$current_section = 'exonerados';
render_header('Exonerados','exonerados');
?>
<div class="card mb-3">
  <div class="card-body">
    <h5 class="mb-0">Residentes exonerados</h5>
    <p class="text-muted mb-0">Lista de contactos con exoneración activa.</p>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table id="tabla_exonerados" class="table table-striped table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Edif/Apto</th>
            <th>Nombre</th>
            <th>Cédula</th>
            <th>Teléfono</th>
            <th>Exonerado desde</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['edif_apto']) ?></td>
              <td><?= e($r['nombres_apellidos']) ?></td>
              <td><?= e(format_cedula($r['cedula'])) ?></td>
              <td><?= e($r['telefono']) ?></td>
              <td><?= $r['exonerado_desde'] ? e(date('d/m/Y H:i', strtotime($r['exonerado_desde']))) : '—' ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-primary" href="index.php?page=residentes&action=pagar&id=<?= (int)$r['id'] ?>">Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if(!$rows): ?>
      <div class="text-center text-muted py-3">No hay residentes exonerados.</div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer();
