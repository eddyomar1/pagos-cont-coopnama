<?php
/*********** Vista: formulario (nuevo/editar) ***********/
$editing = ($action === 'edit');

$data=[
  'id'=>null,
  'edif_apto'=>'',
  'nombres_apellidos'=>'',
  'cedula'=>'',
  'codigo'=>'',
  'telefono'=>'',
  'deuda_inicial'=>'0.00',
  'deuda_extra'=>'0.00',
  'fecha_x_pagar'=>'',
  'fecha_pagada'=>'',
  'mora'=>'',
  'monto_a_pagar'=>'',
  'monto_pagado'=>'',
  'no_recurrente'=>0,
  'exonerado'=>0,
  'exonerado_desde'=>null
];
$hasDeudaInicial = defined('HAS_DEUDA_INICIAL') && HAS_DEUDA_INICIAL;

if($editing){
  $id=(int)($_GET['id'] ?? 0);
  if($id<=0){ header('Location:?action=full'); exit; }
  $st=$pdo->prepare("SELECT * FROM residentes WHERE id=?");
  $st->execute([$id]);
  $row=$st->fetch();
  if(!$row){ header('Location:?action=full'); exit; }
  $data=array_merge($data,$row);
}

if(!empty($_SESSION['old'])) $data=array_merge($data,$_SESSION['old']);
if(!$editing && isset($data['deuda_inicial'])){
  // Mostrar la deuda actual igual a la inicial al crear
  $data['deuda_extra'] = $data['deuda_inicial'];
}
$errors=$_SESSION['errors'] ?? [];
$_SESSION['old']=$_SESSION['errors']=null;

$pendientes = [];
if ($editing && !empty($data['id'])) {
  $pendientes = cuotas_pendientes_residente_local($pdo, (int)$data['id'], BASE_DUE);
}
$exonerado = $editing && !empty($data['exonerado']);
$exoneradoDesde = $exonerado && !empty($data['exonerado_desde']) ? $data['exonerado_desde'] : null;

header_html($editing?'Editar residente':'Agregar residente');
?>
<div class="row justify-content-center"><div class="col-lg-10">
<div class="card"><div class="card-body">
  <h5 class="card-title mb-3"><?=$editing?'Editar':'Agregar'?> residente</h5>
  <?php if($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach($errors as $m) echo "<li>".e($m)."</li>"; ?>
      </ul>
    </div>
  <?php elseif(isset($_GET['desexonerado'])): ?>
    <div class="alert alert-info mb-3">
      La exoneración fue removida. Las próximas mensualidades se adeudarán normalmente.
    </div>
  <?php endif; ?>

  <form id="residenteForm" method="post" action="?action=<?=$editing?'update':'store'?>">
    <?php if($editing): ?>
      <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
    <?php endif; ?>

    <div class="row g-3 align-items-end">
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label">Edif/Apto *</label>
        <input type="text" name="edif_apto" class="form-control" maxlength="60"
               value="<?=e($data['edif_apto'])?>" required>
      </div>
      <div class="col-12 col-sm-6 col-lg-4">
        <label class="form-label">Nombres y Apellidos *</label>
        <input type="text" name="nombres_apellidos" class="form-control" maxlength="255"
               value="<?=e($data['nombres_apellidos'])?>" required>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label">Cédula</label>
        <input type="text" name="cedula" class="form-control" maxlength="13"
               placeholder="001-1234567-8"
               value="<?=e(format_cedula($data['cedula']))?>">
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label">Código</label>
        <input type="text" name="codigo" class="form-control" maxlength="30"
               value="<?=e($data['codigo'])?>">
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" maxlength="50"
               value="<?=e($data['telefono'])?>">
      </div>
    </div>

    <?php if($hasDeudaInicial): ?>
      <hr class="my-4">
      <div class="row g-3 mt-2 justify-content-end align-items-start">
        <div class="col-md-3 order-md-1 text-md-end">
          <label class="form-label">Deuda inicial</label>
          <input type="text" name="deuda_inicial" class="form-control"
                 placeholder="0.00"
                 value="<?= e(number_format((float)$data['deuda_inicial'],2,'.','')) ?>">
          <div class="form-text">Al crear un residente, este valor se copia a la deuda actual.</div>
        </div>
        <div class="col-md-3 order-md-2 text-md-end">
          <label class="form-label">Deuda actual</label>
          <input type="text" name="deuda_extra" class="form-control"
                 placeholder="0.00"
                 value="<?= e(number_format((float)$data['deuda_extra'],2,'.','')) ?>">
          <div class="form-text">Úsalo para ajustar el balance pendiente.</div>
        </div>
      </div>
    <?php endif; ?>
  </form>
</div></div></div></div>

<?php if($editing): ?>
<div class="row justify-content-center mt-3"><div class="col-lg-10">
  <?php if($exonerado): ?>
    <div class="card border-success-subtle">
      <div class="card-body">
        <h6 class="text-success d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-check-circle-fill"></i> Residente exonerado
        </h6>
        <p class="mb-3 text-muted small">
          Este contacto fue exonerado <?= $exoneradoDesde ? 'el '.e(date('d/m/Y H:i', strtotime($exoneradoDesde))) : 'recientemente' ?>.
          No se generarán cargos hasta que reactivas la facturación.
          Usa el botón para quitar la exoneración; las próximas mensualidades se adeudarán desde el siguiente ciclo.
        </p>
        <form method="post" action="?action=desexonerar" class="d-flex align-items-center gap-3 desexonerar-form">
          <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
          <button type="submit" class="btn btn-outline-secondary btn-sm">Quitar exoneración</button>
        </form>
      </div>
    </div>
  <?php else: ?>
    <div class="card border-danger-subtle">
      <div class="card-body">
        <h6 class="text-danger d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-exclamation-triangle-fill"></i> Exonerar mensualidades pendientes
        </h6>
        <p class="mb-3 text-muted small">
          Esta acción marcará como pagadas todas las mensualidades pendientes (incluyendo la actual si ya es día de pago)
          y limpiará mora/deuda extra. Se registrará un movimiento de pago en RD$ 0 como evidencia.
          <?php if ($pendientes): ?>
            <br><strong>Pendientes detectadas:</strong> <?= e(implode(', ', $pendientes)) ?>
          <?php else: ?>
            <br><strong>No hay mensualidades pendientes detectadas.</strong>
          <?php endif; ?>
        </p>
        <form method="post" action="?action=exonerar" class="d-flex align-items-center gap-3 exonerar-form">
          <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
          <div class="form-check m-0">
            <input class="form-check-input exonerar-check" type="checkbox" id="confirmExonera">
            <label class="form-check-label" for="confirmExonera">Confirmo que quiero exonerar todas las mensualidades pendientes.</label>
          </div>
          <button type="submit" class="btn btn-outline-danger btn-sm">Exonerar ahora</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div></div>
<script>
$(function(){
  $('.exonerar-form').on('submit', function(e){
    var $form = $(this);
    var check = $form.find('.exonerar-check').get(0);
    if(!check || !check.checked){
      e.preventDefault();
      alert('Marca la casilla para confirmar la exoneración de todas las mensualidades pendientes.');
      return;
    }
    if(!confirm('Esto exonerará todas las mensualidades pendientes, incluida la actual si ya es día de pago. ¿Deseas continuar?')){
      e.preventDefault();
    }
  });
  $('.desexonerar-form').on('submit', function(e){
    if(!confirm('¿Quitar exoneración? Las próximas mensualidades se adeudarán normalmente.')){
      e.preventDefault();
    }
  });
});
</script>
<?php endif; ?>

<div class="row justify-content-center mt-3"><div class="col-lg-10">
  <div class="card"><div class="card-body">
    <div class="d-flex justify-content-end gap-2">
      <button form="residenteForm" class="btn btn-primary"><?=$editing?'Actualizar':'Guardar'?></button>
      <a class="btn btn-outline-secondary" href="?action=full">Cancelar</a>
    </div>
  </div></div>
</div></div>
<?php
footer_html();
