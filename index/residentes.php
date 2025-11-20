<?php
// index/residentes.php – listado + pagar / crear recibo

require __DIR__ . '/init.php';

$action = $_GET['action'] ?? 'index';

/*********** 3) Acciones CRUD ***********/

/* 3.1 Crear residente */
if ($action === 'store' && $_SERVER['REQUEST_METHOD']==='POST') {
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $deuda_extra       = toDecimal(body('deuda_extra')) ?? 0;

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_in))         $errors[]="Cédula es obligatoria.";

  $cedula_digits = digits_only($cedula_in);
  if($cedula_in && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";

  if($errors){
    $_SESSION['errors']=$errors;
    $_SESSION['old']=$_POST;
    header('Location:index.php?action=new'); exit;
  }

  try{
    $stmt=$pdo->prepare(
      "INSERT INTO residentes
       (edif_apto,nombres_apellidos,cedula,codigo,telefono,
        fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,deuda_extra,no_recurrente)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,0)"
    );
    $stmt->execute([
      $edif_apto,
      $nombres_apellidos,
      $cedula_digits,
      $codigo ?: null,
      $telefono ?: null,
      null,null,0,0,0,
      $deuda_extra
    ]);
    header('Location:index.php?saved=1'); exit;
  }catch(PDOException $ex){
    $_SESSION['errors']=[ "No se pudo guardar: ".$ex->getMessage() ];
    $_SESSION['old']=$_POST;
    header('Location:index.php?action=new'); exit;
  }
}

/* 3.2 Pagar / crear recibo (y también “añadir/editar deuda” en modo_deuda) */
if ($action === 'update' && $_SERVER['REQUEST_METHOD']==='POST') {

  $id = (int)body('id');
  if ($id<=0) {
    $_SESSION['errors']=['ID inválido.'];
    header('Location:index.php'); exit;
  }

  $st = $pdo->prepare("SELECT * FROM residentes WHERE id=?");
  $st->execute([$id]);
  $residente = $st->fetch();
  if(!$residente){
    $_SESSION['errors']=['Residente no encontrado.'];
    header('Location:index.php'); exit;
  }

  $edif_apto         = $residente['edif_apto'];
  $nombres_apellidos = $residente['nombres_apellidos'];
  $cedula_digits     = $residente['cedula'];
  $codigo            = $residente['codigo'];
  $telefono          = $residente['telefono'];

  // Flag de modo deuda
  $modo_deuda = (body('modo_deuda','0') === '1');

  // Valores comunes del formulario (cuando NO es modo deuda)
  $fecha_pagada      = date('Y-m-d');
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  // Deuda extra: si el campo editable viene en el POST lo usamos; si no, usamos el hidden (BD)
  $deuda_restante_post = body('deuda_restante', '');
  if ($deuda_restante_post !== '') {
    $deuda_extra_actual = toDecimal($deuda_restante_post) ?? 0;
  } else {
    $deuda_extra_actual = toDecimal(body('deuda_extra_actual')) ?? 0;
  }

  $abono_deuda_extra  = toDecimal(body('abono_deuda_extra')) ?? 0;

  // Cuotas seleccionadas (checkboxes)
  $selected_dues = isset($_POST['selected_dues']) && is_array($_POST['selected_dues'])
    ? $_POST['selected_dues'] : [];
  $selected_dues = array_values(array_filter($selected_dues, 'is_ymd'));

  // === RAMA: MODO DEUDA ===
  if ($modo_deuda) {
    // En este modo NO estamos cobrando cuotas ni registrando pago.
    // Objetivo: actualizar solo la deuda_extra del residente.
    try{
      $stmt=$pdo->prepare(
        "UPDATE residentes SET
          edif_apto=?, nombres_apellidos=?, cedula=?, codigo=?, telefono=?,
          deuda_extra=?
         WHERE id=?"
      );
      $stmt->execute([
        $edif_apto,$nombres_apellidos,$cedula_digits,$codigo ?: null,$telefono ?: null,
        $deuda_extra_actual,
        $id
      ]);

      header('Location:index.php?updated=1'); exit;
    }catch(PDOException $ex){
      $_SESSION['errors']=[ "No se pudo actualizar la deuda: ".$ex->getMessage() ];
      $_SESSION['old']=$_POST;
      header('Location:index.php?action=pagar&id='.$id); exit;
    }
  }

  // === RAMA NORMAL: CREAR RECIBO / COBRAR ===

  // Fecha x pagar = último mes seleccionado (si hay)
  if ($selected_dues) {
    sort($selected_dues);
    $fecha_x_pagar = end($selected_dues);
  } else {
    $fecha_x_pagar = toDateOrNull(body('fecha_x_pagar')) ?: null;
  }

  // Monto base = meses x CUOTA_MONTO + abono deuda extra
  $monto_base_cuotas = count($selected_dues) * CUOTA_MONTO;
  $monto_base        = $monto_base_cuotas + $abono_deuda_extra;

  $pendientes_totales = cuotas_pendientes_residente($pdo, $id, BASE_DUE);
  $cantidad_pendientes_totales = count($pendientes_totales);
  $mora_auto_raw = $cantidad_pendientes_totales > 0
    ? $cantidad_pendientes_totales * CUOTA_MONTO * 0.02
    : 0.0;
  $mora_manual = toDecimal(body('mora'));
  if ($mora_manual !== null) {
    $mora_raw = max(0.0, (float)$mora_manual);
  } else {
    $mora_raw = $mora_auto_raw;
  }
  $mora = number_format($mora_raw, 2, '.', '');

  $monto_a_pagar = $monto_base;
  $monto_pagado  = $monto_base + $mora_raw;

  $nueva_deuda_extra = max(0, $deuda_extra_actual - $abono_deuda_extra);

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_digits))     $errors[]="Cédula es obligatoria.";
  if($cedula_digits && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";
  if(!$fecha_pagada) $errors[]='Debe indicar la fecha pagada.'; // SOLO en modo normal

  if($errors){
    $_SESSION['errors']=$errors;
    $_SESSION['old']=$_POST;
    header('Location:index.php?action=pagar&id='.$id); exit;
  }

  try{
    $pdo->beginTransaction();

    // Actualizar residente
    $stmt=$pdo->prepare(
      "UPDATE residentes SET
        edif_apto=?, nombres_apellidos=?, cedula=?, codigo=?, telefono=?,
        fecha_x_pagar=?, fecha_pagada=?, mora=?, monto_a_pagar=?, monto_pagado=?, deuda_extra=?, no_recurrente=?
       WHERE id=?"
    );
    $stmt->execute([
      $edif_apto,$nombres_apellidos,$cedula_digits,$codigo ?: null,$telefono ?: null,
      $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$nueva_deuda_extra,$no_recurrente,$id
    ]);

    // Registrar pago detallado
    $stmt2 = $pdo->prepare(
      "INSERT INTO pagos_residentes
       (residente_id, fecha_recibo, fecha_pagada, meses_pagados,
        monto_base, mora, total, observaciones)
       VALUES (?,?,?,?,?,?,?,?)"
    );
    $observaciones = $abono_deuda_extra > 0
      ? 'Incluye abono a deuda extra de RD$ '.number_format($abono_deuda_extra,2,'.','')
      : null;

    $stmt2->execute([
      $id,
      date('Y-m-d H:i:s'),
      $fecha_pagada,
      json_encode($selected_dues, JSON_UNESCAPED_UNICODE),
      $monto_base,
      $mora,
      $monto_base + $mora,
      $observaciones
    ]);

    $pdo->commit();
    header('Location:index.php?updated=1'); exit;

  }catch(PDOException $ex){
    $pdo->rollBack();
    $_SESSION['errors']=[
      "El pago se guardó en residente pero NO en pagos_residentes: ".$ex->getMessage()
    ];
    $_SESSION['old']=$_POST;
    header('Location:index.php?action=pagar&id='.$id); exit;
  }
}


/* 3.3 Eliminar residente */
if ($action === 'delete' && isset($_GET['id'])) {
  $id=(int)$_GET['id'];
  if($id>0){
    $pdo->prepare("DELETE FROM residentes WHERE id=?")->execute([$id]);
  }
  header('Location:index.php?deleted=1'); exit;
}

/*********** 5) Vistas ***********/

/* 5.1 Listado */
if ($action === 'index') {
  // Filtro: por defecto solo con deuda; ?filtro=todos para ver todos
  $filtro = $_GET['filtro'] ?? 'pendientes';
  $solo_deudores = ($filtro !== 'todos');

  $current_section = 'residentes';
  render_header('Residentes','residentes');
  $rows=$pdo->query("SELECT * FROM residentes ORDER BY id DESC")->fetchAll();

  if(isset($_GET['saved']))   echo '<div class="alert alert-success">Registro agregado.</div>';
  if(isset($_GET['updated'])) echo '<div class="alert alert-info">Pago registrado.</div>';
  if(isset($_GET['deleted'])) echo '<div class="alert alert-warning">Registro eliminado.</div>';
  ?>

  <!-- CARD DE CONTROLES -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-center">
        <div class="col-md-3 d-flex align-items-center gap-2">
          <span>Mostrar</span>
          <select id="lenSelect" class="form-select form-select-sm" style="width:auto;">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="text-muted">registros</span>
        </div>
        <div class="col-md-3 d-flex align-items-center justify-content-md-center gap-2">
          <label for="globalSearch" class="mb-0">Buscar:</label>
          <input id="globalSearch" type="search" class="form-control form-control-sm" placeholder="Buscar...">
        </div>
        <div class="col-md-3 d-flex justify-content-md-end">
          <div class="btn-group btn-group-sm" role="group">
            <a href="index.php"
               class="btn <?= $solo_deudores ? 'btn-primary' : 'btn-outline-primary' ?>">
              Solo con deuda
            </a>
            <a href="index.php?filtro=todos"
               class="btn <?= !$solo_deudores ? 'btn-primary' : 'btn-outline-primary' ?>">
              Todos
            </a>
          </div>
        </div>
        <div class="col-md-3 d-flex justify-content-md-end">
          <div class="btn-group btn-group-sm" role="group">
            <a href="index.php"
               class="btn <?= ($current_section==='residentes') ? 'btn-primary' : 'btn-outline-primary' ?>">
              Residentes
            </a>
            <a href="index.php?page=pagos"
               class="btn <?= ($current_section==='pagos') ? 'btn-primary' : 'btn-outline-primary' ?>">
              Pagos
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CARD TABLA -->
  <div class="card">
    <div class="card-body">
      <h5 class="mb-3">LISTA DE COPROPIETARIOS</h5>
      <div class="table-responsive">
        <table id="tabla" class="table table-striped table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Edif/Apto</th>
              <th>Nombres y Apellidos</th>
              <th>Cédula</th>
              <th>Teléfono</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <?php
              $idRes = (int)$r['id'];
              // Cuotas pendientes según historial de pagos (día 25)
              $pendientesRes = cuotas_pendientes_residente($pdo, $idRes, BASE_DUE);
              $tiene_cuotas  = count($pendientesRes) > 0;
              $deuda_extra   = isset($r['deuda_extra']) ? (float)$r['deuda_extra'] : 0.0;
              $tiene_deuda_extra = $deuda_extra > 0.0001;
              $tiene_deuda = $tiene_cuotas || $tiene_deuda_extra;

              if ($solo_deudores && !$tiene_deuda) {
                continue; // no mostrar si estamos filtrando solo deudores
              }

              if ($tiene_deuda) {
                $estado_icono = '<span class="text-danger" title="Tiene pagos pendientes">&#9888;</span>'; // ⚠
              } else {
                $estado_icono = '<span class="text-success" title="Al día">&#10003;</span>'; // ✓
              }
            ?>
            <tr>
              <td><?= e($r['edif_apto']) ?></td>
              <td><?= e($r['nombres_apellidos']) ?></td>
              <td><?= e(format_cedula($r['cedula'])) ?></td>
              <td><?= e($r['telefono']) ?></td>
              <td class="text-center"><?= $estado_icono ?></td>
              <td class="text-center">
                <a class="btn btn-primary btn-sm"
                   href="index.php?action=pagar&id=<?= $idRes ?>">
                  PAGAR MANTENIMIENTO
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php
  render_footer(); exit;
}

/* 5.2 Formulario pagar/crear recibo */
if ($action==='new' || $action==='pagar') {
  $editing = $action==='pagar';

  $today = date('Y-m-d');

  $data=[
    'id'=>null,
    'edif_apto'=>'',
    'nombres_apellidos'=>'',
    'cedula'=>'',
    'codigo'=>'',
    'telefono'=>'',
    'fecha_x_pagar'=>'',
    'fecha_pagada'=>$editing ? $today : '',
    'mora'=>'0.00',
    'monto_a_pagar'=>'0.00',
    'monto_pagado'=>'0.00',
    'deuda_extra'=>'0.00',
    'no_recurrente'=>0
  ];

  $old_inputs = $_SESSION['old'] ?? [];

  if($editing){
    $id=(int)($_GET['id'] ?? 0);
    if($id<=0){ header('Location:index.php'); exit; }
    $st=$pdo->prepare("SELECT * FROM residentes WHERE id=?");
    $st->execute([$id]);
    $row=$st->fetch();
    if(!$row){ header('Location:index.php'); exit; }
    $data=array_merge($data,$row);
    $data['fecha_pagada']=$today;
  }

  if(!empty($old_inputs)) $data=array_merge($data,$old_inputs);
  if ($editing) {
    $data['fecha_pagada']=$today;
  }
  $errors=$_SESSION['errors'] ?? [];
  $old_mora_manual = isset($old_inputs['mora']) ? trim((string)$old_inputs['mora']) : '';
  $had_manual_mora = $old_mora_manual !== '';
  $_SESSION['old']=$_SESSION['errors']=null;

  render_header($editing?'Pagar / Crear recibo':'Agregar residente','residentes');

  // Cuotas pendientes según historial (día 25)
  $pendientes = $editing
    ? cuotas_pendientes_residente($pdo, (int)$data['id'], BASE_DUE)
    : [];
  $cantidad   = count($pendientes);

  // Preparar info para adelantos (meses futuros)
  $max_future_advances = 12;
  $next_future_due = null;
  if ($editing) {
    if ($pendientes) {
      $lastPending = new DateTime(end($pendientes));
      $lastPending->modify('+1 month');
      $next_future_due = $lastPending->format('Y-m-d');
    } else {
      $next_future_due = proximo_vencimiento($data['fecha_x_pagar'] ?? null);
    }
  }

  // Mora automática (2% del total pendiente si hay atrasos)
  $total_pendiente_cuotas = $cantidad * CUOTA_MONTO;
  $mora_auto = $cantidad > 0 ? $total_pendiente_cuotas * 0.02 : 0.0;
  if (!$had_manual_mora) {
    $data['mora'] = number_format($mora_auto, 2, '.', '');
  }

  // Deuda extra actual (desde BD)
  $deuda_extra_db   = isset($data['deuda_extra']) ? (float)$data['deuda_extra'] : 0.0;
  $deuda_extra_fmt  = number_format($deuda_extra_db,2,'.','');
  $mostrar_card_deuda = $deuda_extra_db > 0;
  ?>
  <div class="row justify-content-center"><div class="col-lg-10">

    <?php if($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach($errors as $m) echo "<li>".e($m)."</li>"; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="index.php?action=<?= $editing?'update':'store' ?>">
      <?php if($editing): ?>
        <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
      <?php endif; ?>

      <input type="hidden" name="modo_deuda" id="modo_deuda" value="0">

      <!-- CARD DATOS -->
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">Pagar / Crear recibo</h5>
          <?php if ($editing): ?>
            <button type="button" id="btnToggleDeuda" class="btn btn-outline-secondary btn-sm">
              Añadir / editar deuda atrasada
            </button>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Edif/Apto *</label>
            <input type="text" class="form-control"
                   value="<?=e($data['edif_apto'])?>" disabled>
          </div>
          <div class="col-md-5">
            <label class="form-label">Nombres y Apellidos *</label>
            <input type="text" class="form-control"
                   value="<?=e($data['nombres_apellidos'])?>" disabled>
          </div>
          <div class="col-md-2">
            <label class="form-label">Cédula *</label>
            <input type="text" class="form-control"
                   value="<?=e(format_cedula($data['cedula']))?>" disabled>
          </div>
          <div class="col-md-2">
            <label class="form-label">Código</label>
            <input type="text" class="form-control"
                   value="<?=e($data['codigo'])?>" disabled>
          </div>

          <div class="col-md-3">
            <label class="form-label">Teléfono</label>
            <input type="text" class="form-control"
                   value="<?=e($data['telefono'])?>" disabled>
          </div>

          <div class="col-md-3">
            <label class="form-label">Fecha de pago</label>
            <input type="date" name="fecha_pagada" class="form-control"
                   value="<?= $editing ? e($data['fecha_pagada']) : '' ?>"
                   <?= $editing ? ' readonly' : '' ?>>
            <?php if ($editing): ?>
              <div class="form-text">Se usa automáticamente la fecha del día de hoy.</div>
            <?php endif; ?>
          </div>

          <div class="col-md-3">
            <label class="form-label">Mora (si aplica)</label>
            <input type="text" name="mora" class="form-control"
                   placeholder="0.00" value="<?=e($data['mora'])?>" >
            <div class="form-text">
              Por defecto es 2% de las cuotas vencidas, pero puede ajustarlo manualmente.
            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Total a pagar (auto)</label>
            <input type="text" name="monto_a_pagar" class="form-control"
                   placeholder="0.00" value="<?=e($data['monto_a_pagar'])?>" disabled>
            <div class="form-text text-muted" id="totalPagarDetail">
              Subtotal RD$ 0.00
            </div>
          </div>
        </div>
      </div></div>

      <!-- CARD DEUDA EXTRA -->
      <div class="card mb-3 <?= $mostrar_card_deuda ? '' : 'd-none' ?>" id="cardDeudaExtra">
        <div class="card-body">
          <p class="text-muted mb-3">
            Deuda registrada actualmente: <strong>RD$ <?= number_format($deuda_extra_db,2,'.',',') ?></strong>
          </p>

          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Deuda restante (estimada)</label>
              <input type="text" class="form-control"
                     name="deuda_restante" id="deuda_restante"
                     value="<?= $deuda_extra_fmt ?>" disabled>
            </div>
            <div class="col-md-4">
              <label class="form-label">Monto a abonar ahora</label>
              <input type="text" class="form-control"
                     name="abono_deuda_extra" id="abono_deuda_extra"
                     placeholder="0.00" value="">
            </div>
            <div class="col-md-4">
              <label class="form-label">Deuda estimada después de este pago</label>
              <input type="text" class="form-control"
                     id="deuda_despues"
                     value="<?= $deuda_extra_fmt ?>" disabled>
            </div>
          </div>

          <input type="hidden" name="deuda_extra_actual" id="deuda_extra_actual"
                 value="<?= $deuda_extra_fmt ?>">
        </div>
      </div>

      <!-- CARD CUOTAS PENDIENTES -->
      <div class="card mb-3" id="cardCuotas"><div class="card-body">
        <h6 class="mb-2">Cuotas pendientes</h6>

        <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
          <span class="text-muted">
            Pendientes totales:
            <span class="badge bg-<?= $cantidad? 'warning text-dark':'success' ?>">
              <?= $cantidad ?>
            </span>
          </span>
          <span class="text-muted">
            Seleccionadas: <strong id="countSelected">0</strong>
          </span>
          <span class="text-muted">
            Total seleccionado:
            RD$  <strong id="totalSelectedWithMora">0.00</strong>
            </span>
          </span>
          <?php if ($editing && $next_future_due): ?>
            <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
              <span class="form-label mb-0 small text-muted">Meses a adelantar</span>
              <div class="d-flex align-items-center gap-1">
                <button
                  type="button"
                  id="btnAdvanceMinus"
                  class="btn btn-outline-secondary btn-sm"
                  title="Disminuir meses adelantados"
                >&minus;</button>
                <span
                  id="advanceCounter"
                  class="badge bg-light text-dark px-3 py-2"
                  title="Meses adelantados actualmente"
                >0</span>
                <button
                  type="button"
                  id="btnAdvancePlus"
                  class="btn btn-outline-primary btn-sm"
                  title="Aumentar meses adelantados"
                >+</button>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php
          $proximo = proximo_vencimiento($data['fecha_x_pagar'] ?? null);
        ?>
        <div id="dueListWrapper">
          <?php if ($pendientes): ?>
            <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-2" id="dueList">
              <?php foreach($pendientes as $i=>$venc): $label=fecha_larga_es($venc); ?>
                <div class="col">
                  <div class="form-check">
                    <input
                      class="form-check-input due-option"
                      type="checkbox"
                      name="selected_dues[]"
                      id="due<?= $i ?>"
                      value="<?= e($venc) ?>"
                      data-label="<?= e($label) ?>"
                      checked
                    >
                    <label class="form-check-label" for="due<?= $i ?>"><?= e($label) ?></label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div id="noDueMessage" class="alert alert-success mb-0 d-none">
              No hay cuotas pendientes. Próximo vencimiento:
              <strong><?= e(fecha_larga_es($proximo)) ?></strong>.
            </div>
          <?php else: ?>
            <div id="noDueMessage" class="alert alert-success mb-3">
              No hay cuotas pendientes. Próximo vencimiento:
              <strong><?= e(fecha_larga_es($proximo)) ?></strong>.
            </div>
            <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-2" id="dueList"></div>
          <?php endif; ?>
        </div>

        <input type="hidden" name="fecha_x_pagar" id="fecha_x_pagar" value="">
        <input type="hidden" id="cuotaMonto" value="<?= number_format(CUOTA_MONTO,2,'.','') ?>">
        <?php if ($editing && $next_future_due): ?>
          <input
            type="hidden"
            id="nextFutureDue"
            value="<?= e($next_future_due) ?>"
            data-max-advances="<?= $max_future_advances ?>"
          >
        <?php endif; ?>
      </div></div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><?= $editing ? 'Crear recibo' : 'Guardar' ?></button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
      </div>

    </form>
  </div></div>

  <?php
  render_footer(); exit;
}

header('Location:index.php'); exit;
