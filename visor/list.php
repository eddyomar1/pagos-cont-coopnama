<?php
/*********** Vista: listado completo ***********/
header_html('Residentes (vista completa)');

$hasDeudaInicial = defined('HAS_DEUDA_INICIAL') && HAS_DEUDA_INICIAL;
$columns = "id, cedula, codigo, edif_apto, nombres_apellidos, telefono";
if ($hasDeudaInicial) {
  $columns .= ", deuda_inicial, deuda_extra";
}
$columns .= ", exonerado, exonerado_desde";
$rows=$pdo->query("SELECT $columns FROM residentes ORDER BY id DESC")->fetchAll();
$fieldsToCheck = [
  'cedula'            => 'Cédula',
  'codigo'            => 'Código',
  'edif_apto'         => 'Edif. Apart',
  'nombres_apellidos' => 'Nombres y Apellidos',
  'telefono'          => 'Teléfono',
];

if(isset($_GET['saved']))   echo '<div class="alert alert-success">Registro agregado.</div>';
if(isset($_GET['updated'])) echo '<div class="alert alert-info">Registro actualizado.</div>';
if(isset($_GET['deleted'])) echo '<div class="alert alert-warning">Registro eliminado.</div>';
if(isset($_GET['exonerado'])) echo '<div class="alert alert-success">Deudas pendientes exoneradas para el residente seleccionado.</div>';
if(!empty($_SESSION['errors'] ?? [])){
  echo '<div class="alert alert-danger">'.implode('<br>', array_map('e', $_SESSION['errors'])).'</div>';
  unset($_SESSION['errors']);
}
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
        <?php if($hasDeudaInicial): ?>
          <th>Deuda inicial</th>
          <th>Deuda actual</th>
        <?php endif; ?>
        <th>Estado</th>
        <th class="text-center actions-col">Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach($rows as $r):
        $missing=[];
        foreach($fieldsToCheck as $field=>$label){
          $value = trim((string)($r[$field] ?? ''));
          $isEmpty = $value === '' || strcasecmp($value, 'N/A') === 0;
          if($isEmpty){
            $missing[]=$label;
          }
        }
        $hasMissing=!empty($missing);
      ?>
        <tr class="<?= $hasMissing ? 'table-warning' : '' ?>" tabindex="0">
          <td><?= e(format_cedula($r['cedula'])) ?></td>
          <td><?= e($r['codigo']) ?></td>
          <td><?= e($r['edif_apto']) ?></td>
          <td><?= e($r['nombres_apellidos']) ?></td>
          <td><?= e($r['telefono']) ?></td>
          <?php if($hasDeudaInicial): ?>
            <td>RD$ <?= e(number_format((float)($r['deuda_inicial'] ?? 0),2,'.',',')) ?></td>
            <td>RD$ <?= e(number_format((float)($r['deuda_extra'] ?? 0),2,'.',',')) ?></td>
          <?php endif; ?>
          <td>
            <?php if(!empty($r['exonerado'])): ?>
              <span class="badge text-bg-info" title="Exonerado desde <?= e($r['exonerado_desde'] ?? '—') ?>">Exonerado</span>
            <?php endif; ?>
            <?php if($hasMissing): ?>
              <span class="badge text-bg-warning" title="<?= e('Faltan: '.implode(', ', $missing)) ?>">Incompleto</span>
            <?php else: ?>
              <span class="badge text-bg-success">Completo</span>
            <?php endif; ?>
          </td>
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
