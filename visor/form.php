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
  'no_recurrente'=>0
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
  <?php endif; ?>

  <form id="residenteForm" method="post" action="?action=<?=$editing?'update':'store'?>">
    <?php if($editing): ?>
      <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Edif/Apto *</label>
        <input type="text" name="edif_apto" class="form-control" maxlength="60"
               value="<?=e($data['edif_apto'])?>" required>
      </div>
      <div class="col-md-5">
        <label class="form-label">Nombres y Apellidos *</label>
        <input type="text" name="nombres_apellidos" class="form-control" maxlength="255"
               value="<?=e($data['nombres_apellidos'])?>" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Cédula</label>
        <input type="text" name="cedula" class="form-control" maxlength="13"
               placeholder="001-1234567-8"
               value="<?=e(format_cedula($data['cedula']))?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Código</label>
        <input type="text" name="codigo" class="form-control" maxlength="30"
               value="<?=e($data['codigo'])?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" maxlength="50"
               value="<?=e($data['telefono'])?>">
      </div>
    </div>

    <?php if($hasDeudaInicial): ?>
      <hr class="my-4">
      <div class="row g-3 align-items-start">
        <div class="col-md-3 order-md-1">
          <label class="form-label">Deuda inicial</label>
          <input type="text" name="deuda_inicial" class="form-control"
                 placeholder="0.00"
                 value="<?= e(number_format((float)$data['deuda_inicial'],2,'.','')) ?>">
          <div class="form-text">Al crear un residente, este valor se copia a la deuda actual.</div>
        </div>
        <div class="col-md-3 order-md-2">
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
