<?php
/*********** Vista: listado completo ***********/
header_html('Residentes (vista completa)');

$rows=$pdo->query("SELECT id, cedula, codigo, edif_apto, nombres_apellidos, telefono FROM residentes ORDER BY id DESC")->fetchAll();

if(isset($_GET['saved']))   echo '<div class="alert alert-success">Registro agregado.</div>';
if(isset($_GET['updated'])) echo '<div class="alert alert-info">Registro actualizado.</div>';
if(isset($_GET['deleted'])) echo '<div class="alert alert-warning">Registro eliminado.</div>';
?>
<div class="card"><div class="card-body">
  <div class="table-responsive">
    <table id="tabla" class="table table-striped table-bordered align-middle">
      <thead class="table-light"><tr>
        <th>Cédula</th>
        <th>Código</th>
        <th>Edif. Apart</th>
        <th>Nombres y Apellidos</th>
        <th>Teléfono</th>
        <th class="text-center actions-col">Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr tabindex="0">
          <td><?= e(format_cedula($r['cedula'])) ?></td>
          <td><?= e($r['codigo']) ?></td>
          <td><?= e($r['edif_apto']) ?></td>
          <td><?= e($r['nombres_apellidos']) ?></td>
          <td><?= e($r['telefono']) ?></td>
          <td class="text-center actions-col">
            <div class="actions d-inline-flex gap-1">
              <a class="btn btn-warning btn-sm" href="?action=edit&id=<?= (int)$r['id'] ?>">Editar</a>
              <a class="btn btn-danger btn-sm btn-delete" href="?action=delete&id=<?= (int)$r['id'] ?>">Eliminar</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php
footer_html();
