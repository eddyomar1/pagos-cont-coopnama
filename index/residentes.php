<?php
// index/residentes.php – listado + pagar / crear recibo

require __DIR__ . '/init.php';

$action = $_GET['action'] ?? 'index';

function residentes_estado_pago(PDO $pdo, array $row): array{
  $idRes = (int)($row['id'] ?? 0);
  $pendientesRes = cuotas_pendientes_residente($pdo, $idRes, BASE_DUE);
  $meses_adeudados = count($pendientesRes);
  $tiene_cuotas = $meses_adeudados > 0;
  $deuda_extra = isset($row['deuda_extra']) ? (float)$row['deuda_extra'] : 0.0;
  $tiene_deuda_extra = $deuda_extra > 0.0001;
  $tiene_deuda = $tiene_cuotas || $tiene_deuda_extra;

  return [
    'id' => $idRes,
    'tiene_deuda' => $tiene_deuda,
    'meses_adeudados' => $meses_adeudados,
    'tiene_deuda_extra' => $tiene_deuda_extra,
  ];
}

function residentes_icono_estado(bool $tiene_deuda, int $meses_adeudados=0, bool $tiene_deuda_extra=false): string{
  if ($tiene_deuda) {
    $titulo = $meses_adeudados === 1 ? '1 mes adeudado' : $meses_adeudados.' meses adeudados';
    if ($meses_adeudados === 0 && $tiene_deuda_extra) {
      $titulo = '0 meses adeudados y deuda extra pendiente';
    } elseif ($tiene_deuda_extra) {
      $titulo .= ' y deuda extra pendiente';
    }
    return '<span class="d-inline-flex align-items-center gap-1" title="'.e($titulo).'"><span class="fw-semibold text-danger">'.$meses_adeudados.'</span><span class="text-danger" aria-hidden="true">&#9888;</span></span>';
  }
  return '<span class="text-success" title="Al dia">&#10003;</span>';
}

function residentes_match_busqueda(array $row, string $q, array $meta=[]): bool{
  $q = trim(mb_strtolower($q, 'UTF-8'));
  if ($q === '') return true;

  $tiene_deuda = (bool)($meta['tiene_deuda'] ?? $row['_tiene_deuda'] ?? false);
  $meses_adeudados = (int)($meta['meses_adeudados'] ?? $row['_meses_adeudados'] ?? 0);
  $estado = $tiene_deuda
    ? $meses_adeudados.' deuda pendiente atrasado'
    : 'al dia pagado';

  $haystack = mb_strtolower(implode(' ', [
    (string)($row['edif_apto'] ?? ''),
    (string)($row['nombres_apellidos'] ?? ''),
    (string)format_cedula((string)($row['cedula'] ?? '')),
    (string)($row['telefono'] ?? ''),
    $estado,
  ]), 'UTF-8');

  return mb_strpos($haystack, $q) !== false;
}

function residentes_filtrados(PDO $pdo, array $rows, string $status, string $q=''): array{
  $filtered = [];
  foreach($rows as $r){
    $meta = residentes_estado_pago($pdo, $r);

    if ($status === 'pendientes' && !$meta['tiene_deuda']) {
      continue;
    }
    if ($status === 'pagados' && $meta['tiene_deuda']) {
      continue;
    }
    if (!residentes_match_busqueda($r, $q, $meta)) {
      continue;
    }

    $r['_id_res'] = $meta['id'];
    $r['_tiene_deuda'] = $meta['tiene_deuda'];
    $r['_meses_adeudados'] = $meta['meses_adeudados'];
    $r['_tiene_deuda_extra'] = $meta['tiene_deuda_extra'];
    $filtered[] = $r;
  }
  return $filtered;
}

function residentes_ordenados(array $rows, int $order_col, string $order_dir): array{
  $columns = [
    0 => 'edif_apto',
    1 => 'nombres_apellidos',
    2 => 'cedula',
    3 => 'telefono',
    4 => '_meses_adeudados',
  ];
  if (!isset($columns[$order_col])) {
    return $rows;
  }

  $key = $columns[$order_col];
  $direction = strtolower($order_dir) === 'asc' ? 1 : -1;

  usort($rows, function(array $a, array $b) use ($key, $direction){
    if ($key === '_meses_adeudados') {
      $cmp = ((int)($a['_meses_adeudados'] ?? 0)) <=> ((int)($b['_meses_adeudados'] ?? 0));
      if ($cmp === 0) {
        $cmp = strcmp((string)($a['nombres_apellidos'] ?? ''), (string)($b['nombres_apellidos'] ?? ''));
      }
      return $cmp * $direction;
    }

    $left = $key === 'cedula'
      ? format_cedula((string)($a[$key] ?? ''))
      : (string)($a[$key] ?? '');
    $right = $key === 'cedula'
      ? format_cedula((string)($b[$key] ?? ''))
      : (string)($b[$key] ?? '');
    $cmp = strnatcasecmp($left, $right);

    return $cmp * $direction;
  });

  return $rows;
}

/*********** 3) Acciones CRUD ***********/

/* 3.1 Crear residente */
if ($action === 'store' && $_SERVER['REQUEST_METHOD']==='POST') {
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $deuda_extra       = toDecimal(body('deuda_extra')) ?? 0;
  $deuda_inicial     = $deuda_extra;
  $inicio_pago_mes   = body('inicio_pago_mes');
  $fecha_x_pagar     = preg_match('~^\d{4}-\d{2}$~', $inicio_pago_mes)
    ? $inicio_pago_mes.'-'.DUE_DAY
    : date('Y-m-'.DUE_DAY);
  $cuota_mensual     = toDecimal(body('cuota_mensual'));

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";

  $cedula_digits = digits_only($cedula_in);
  if($cedula_in && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";

  if($errors){
    $_SESSION['errors']=$errors;
    $_SESSION['old']=$_POST;
    header('Location:index.php?action=new'); exit;
  }

  try{
    if (defined('HAS_DEUDA_INICIAL') && HAS_DEUDA_INICIAL) {
      $stmt=$pdo->prepare(
        "INSERT INTO residentes
         (edif_apto,nombres_apellidos,cedula,codigo,telefono,
          fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,deuda_inicial,deuda_extra,cuota_mensual,no_recurrente)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)"
      );
      $stmt->execute([
        $edif_apto,
        $nombres_apellidos,
        $cedula_digits,
        $codigo ?: null,
        $telefono ?: null,
        $fecha_x_pagar,null,0,0,0,
        $deuda_inicial,
        $deuda_extra,
        $cuota_mensual
      ]);
    } else {
      $stmt=$pdo->prepare(
        "INSERT INTO residentes
         (edif_apto,nombres_apellidos,cedula,codigo,telefono,
          fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,deuda_extra,cuota_mensual,no_recurrente)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0)"
      );
      $stmt->execute([
        $edif_apto,
        $nombres_apellidos,
        $cedula_digits,
        $codigo ?: null,
        $telefono ?: null,
        $fecha_x_pagar,null,0,0,0,
        $deuda_extra,
        $cuota_mensual
      ]);
    }
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
  $due_amounts_post = isset($_POST['due_amounts']) && is_array($_POST['due_amounts'])
    ? $_POST['due_amounts'] : [];
  $selected_due_amounts = [];
  foreach($selected_dues as $dueDate){
    $rawAmount = $due_amounts_post[$dueDate] ?? null;
    $parsedAmount = $rawAmount !== null ? toDecimal((string)$rawAmount) : null;
    $amount = $parsedAmount !== null ? (float)$parsedAmount : cuota_monto_residente($pdo, $id, $dueDate);
    if ($amount < 0) $amount = 0.0;
    $selected_due_amounts[$dueDate] = $amount;
  }

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

  // Monto base = suma de cuotas seleccionadas + abono deuda extra
  $monto_base_cuotas = cuotas_total_residente_por_fechas($pdo, $id, $selected_dues, $selected_due_amounts);
  $monto_base        = $monto_base_cuotas + $abono_deuda_extra;

  $pendientes_totales = cuotas_pendientes_residente($pdo, $id, BASE_DUE);
  $cuotas_en_mora_totales = cuotas_en_mora($pendientes_totales, 2);
  $mora_auto_raw = count($cuotas_en_mora_totales) > 0
    ? cuotas_total_residente_por_fechas($pdo, $id, $cuotas_en_mora_totales) * MORA_PCT
    : 0.0;
  $mora_enviada = array_key_exists('mora', $_POST);
  $mora_manual_raw = $mora_enviada ? trim((string)$_POST['mora']) : '';
  $mora_manual = toDecimal($mora_manual_raw);
  if ($mora_enviada) {
    $mora_raw = ($mora_manual !== null) ? max(0.0, (float)$mora_manual) : 0.0;
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
    $observaciones = $abono_deuda_extra > 0
      ? 'Incluye abono a deuda extra de RD$ '.number_format($abono_deuda_extra,2,'.','')
      : null;

    insertar_pago_con_lineas(
      $pdo,
      $id,
      $selected_dues,
      $selected_due_amounts,
      $monto_base,
      $mora,
      $fecha_pagada,
      $observaciones
    );

    $pdo->commit();
    header('Location:index.php?updated=1'); exit;

  }catch(PDOException $ex){
    app_log('Error creando pago/residente '.$id.': '.$ex->getMessage());
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
if ($action === 'print') {
  $status = $_GET['status'] ?? 'pendientes';
  $q = trim((string)($_GET['q'] ?? ''));
  $order_col = (int)($_GET['order_col'] ?? -1);
  $order_dir = (string)($_GET['order_dir'] ?? 'asc');

  render_header('Imprimir copropietarios','residentes');
  $rows = [];
  try{
    $rows = $pdo->query("SELECT * FROM residentes ORDER BY id DESC")->fetchAll();
  }catch(Throwable $e){
    app_log('Error listando residentes para imprimir '.$status.': '.$e->getMessage());
    $rows = [];
  }
  $rows = residentes_filtrados($pdo, $rows, $status, $q);
  $rows = residentes_ordenados($rows, $order_col, $order_dir);
  ?>
  <style>
    @media print{
      .topbar,.sidebar,.sidebar-backdrop,.print-actions{display:none !important;}
      .content{margin-left:0 !important;padding:0 !important;}
      .content-inner{max-width:none !important;}
      .card{box-shadow:none !important;border:1px solid #d1d5db !important;}
    }
  </style>

  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center gap-3 mb-3 print-actions">
        <a href="index.php?page=residentes&status=<?= e($status) ?>" class="btn btn-outline-secondary">Volver</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>
      </div>

      <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <div>
          <h4 class="mb-1">LISTA DE COPROPIETARIOS</h4>
          <div class="text-muted">
            <?= $status === 'pagados' ? 'Solo copropietarios al dia' : 'Solo copropietarios con deuda' ?>
            <?php if ($q !== ''): ?>
              | Filtro: "<?= e($q) ?>"
            <?php endif; ?>
          </div>
        </div>
        <div class="fw-semibold">Total: <?= count($rows) ?></div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Edif/Apto</th>
              <th>Nombres y Apellidos</th>
              <th>Cedula</th>
              <th>Telefono</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= e($r['edif_apto']) ?></td>
              <td><?= e($r['nombres_apellidos']) ?></td>
              <td><?= e(format_cedula($r['cedula'])) ?></td>
              <td><?= telefono_whatsapp_html($r['telefono']) ?></td>
              <td class="text-center"><?= residentes_icono_estado((bool)$r['_tiene_deuda'], (int)$r['_meses_adeudados'], (bool)$r['_tiene_deuda_extra']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  window.addEventListener('load', function(){
    window.setTimeout(function(){ window.print(); }, 150);
  });
  </script>
  <?php
  render_footer(); exit;
}

if ($action === 'index') {
  // Filtro por estado: pendientes (default) o pagados
  $status = $_GET['status'] ?? 'pendientes';
  $q = trim((string)($_GET['q'] ?? ''));

  $current_section = 'residentes';
  render_header('Residentes','residentes');
  $rows = [];
  try{
    $rows=$pdo->query("SELECT * FROM residentes ORDER BY id DESC")->fetchAll();
  }catch(Throwable $e){
    app_log('Error listando residentes '.$status.': '.$e->getMessage());
    $rows = [];
  }
  $rows = residentes_filtrados($pdo, $rows, $status, $q);

  if(isset($_GET['saved']))   echo '<div class="alert alert-success">Registro agregado.</div>';
  if(isset($_GET['updated'])) echo '<div class="alert alert-info">Pago registrado.</div>';
  if(isset($_GET['deleted'])) echo '<div class="alert alert-warning">Registro eliminado.</div>';
  ?>

  <!-- CARD DE CONTROLES -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-center">
        <div class="col-lg-5 d-flex align-items-center gap-2">
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
        <div class="col-lg-7 d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
          <div class="d-flex align-items-center gap-2">
            <label for="globalSearch" class="mb-0">Buscar:</label>
            <input id="globalSearch" type="search" class="form-control form-control-sm" placeholder="Buscar..." style="max-width:240px">
          </div>
          <div class="btn-group" role="group" aria-label="Filtro pagos">
            <a class="btn btn-sm <?= $status==='pendientes'?'btn-primary':'btn-outline-primary' ?>" href="index.php?page=residentes&status=pendientes">Pendientes</a>
            <a class="btn btn-sm <?= $status==='pagados'?'btn-primary':'btn-outline-primary' ?>" href="index.php?page=residentes&status=pagados">Pagados</a>
          </div>
          <button 
            type="button" 
            class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2 print-list-btn"
            id="printListBtn"
            title="Imprimir lista de copropietarios con filtros aplicados"
          >
            <i class="bi bi-printer"></i>
            <span>Imprimir</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- CARD TABLA -->
  <div class="card">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3">
        <h5 class="mb-0">LISTA DE COPROPIETARIOS</h5>
      </div>
      <div class="text-muted small mb-3">
        <?= $status === 'pagados' ? 'Solo copropietarios al día' : 'Solo copropietarios con deuda' ?>
        <?php if ($q !== ''): ?>
          | Filtro de búsqueda: "<?= e($q) ?>"
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table id="tabla" class="table table-striped table-bordered align-middle table-nowrap">
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
              $idRes = (int)$r['_id_res'];
              $estado_icono = residentes_icono_estado((bool)$r['_tiene_deuda'], (int)$r['_meses_adeudados'], (bool)$r['_tiene_deuda_extra']);
            ?>
            <tr>
              <td><?= e($r['edif_apto']) ?></td>
              <td><?= e($r['nombres_apellidos']) ?></td>
              <td><?= e(format_cedula($r['cedula'])) ?></td>
              <td><?= telefono_whatsapp_html($r['telefono']) ?></td>
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

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    var printBtn = document.getElementById('printListBtn');
    if (!printBtn) return;
    
    printBtn.addEventListener('click', function(){
      var base = 'index.php?page=residentes&action=print&status=<?= urlencode($status) ?>';
      var url = new URL(base, window.location.href);
      var searchInput = document.getElementById('globalSearch');
      var q = searchInput ? String(searchInput.value || '').trim() : '';
      if (q !== '') {
        url.searchParams.set('q', q);
      } else {
        url.searchParams.delete('q');
      }
      if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#tabla')) {
        var table = jQuery('#tabla').DataTable();
        var order = table.order();
        var currentSearch = String(table.search() || '').trim();
        if (currentSearch !== '') {
          url.searchParams.set('q', currentSearch);
        }
        if (order && order.length) {
          url.searchParams.set('order_col', order[0][0]);
          url.searchParams.set('order_dir', order[0][1]);
        }
      }
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
    });
  });
  </script>

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
    'deuda_inicial'=>'0.00',
    'cuota_mensual'=>'',
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
  $had_manual_mora = array_key_exists('mora', $old_inputs);
  $old_due_amounts = isset($old_inputs['due_amounts']) && is_array($old_inputs['due_amounts'])
    ? $old_inputs['due_amounts'] : [];
  $_SESSION['old']=$_SESSION['errors']=null;

  render_header($editing?'Pagar / Crear recibo':'Agregar residente','residentes');

  // Cuotas pendientes según historial (día configurable)
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

  // Mora automática según MORA_PCT si hay atrasos
  $cuotas_en_mora_pendientes = cuotas_en_mora($pendientes, 2);
  $total_pendiente_cuotas = $editing
    ? cuotas_total_residente_por_fechas($pdo, (int)$data['id'], $cuotas_en_mora_pendientes)
    : cuotas_total_por_fechas($cuotas_en_mora_pendientes);
  $mora_auto = count($cuotas_en_mora_pendientes) > 0 ? $total_pendiente_cuotas * MORA_PCT : 0.0;
  if (!$had_manual_mora) {
    $data['mora'] = number_format($mora_auto, 2, '.', '');
  } elseif ($old_mora_manual === '') {
    $data['mora'] = '0.00';
  }

  // Deuda extra actual (desde BD)
  $deuda_extra_db   = isset($data['deuda_extra']) ? (float)$data['deuda_extra'] : 0.0;
  $deuda_extra_fmt  = number_format($deuda_extra_db,2,'.','');
  $mostrar_card_deuda = $deuda_extra_db > 0;
	  ?>
	  <div class="row justify-content-center"><div class="col-lg-10">
      <style>
        .due-amount-display{
          display:inline-block;
          margin-left:1.45rem;
          margin-top:.15rem;
          font-size:.85rem;
          color:#64748b;
          user-select:none;
        }
        .due-amount-display[data-editable-amount]{
          cursor:pointer;
          border-bottom:1px dashed rgba(100,116,139,.45);
        }
        .due-amount-display[data-editable-amount]:hover{
          color:#0f172a;
          border-bottom-color:rgba(15,23,42,.4);
        }
      </style>

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

      <!-- CARD DATOS -->
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">Pagar / Crear recibo</h5>
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
            <label class="form-label">Cédula</label>
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
	              Por defecto es 10% de las cuotas con 2 o más meses de retraso, pero puede ajustarlo manualmente.
	            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Total a pagar (auto)</label>
            <input type="text" name="monto_a_pagar" class="form-control"
                   placeholder="0.00" value="<?=e($data['monto_a_pagar'])?>" disabled>
            <div class="form-text text-muted" id="totalPagarDetail">
              Mensualidad + Mora
            </div>
          </div>
        </div>
      </div></div>

      <!-- CARD DEUDA EXTRA -->
      <?php if ($mostrar_card_deuda): ?>
        <div class="card mb-3" id="cardDeudaExtra">
          <div class="card-body">
            <p class="text-muted mb-3">
              Deuda registrada actualmente: <strong>RD$ <?= number_format($deuda_extra_db,2,'.',',') ?></strong>
            </p>

            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Deuda restante (solo lectura)</label>
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
      <?php endif; ?>

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
            Total seleccionado: RD$ <strong id="totalSelected">0.00</strong>
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
                    <?php
                      $defaultAmount = cuota_monto_residente($pdo, (int)$data['id'], $venc);
                      $customAmount = isset($old_due_amounts[$venc]) ? toDecimal((string)$old_due_amounts[$venc]) : null;
                      $dueAmount = $customAmount !== null ? (float)$customAmount : $defaultAmount;
                      $editableDueAmount = $venc >= CUOTA_MONTO_NUEVO_DESDE;
                    ?>
		                <div class="col">
		                  <div class="form-check due-item" data-date="<?= e($venc) ?>">
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
                        <div
                          class="due-amount-display"
                          <?= $editableDueAmount ? 'data-editable-amount="1"' : '' ?>
                          title="<?= e($editableDueAmount ? 'Doble clic para editar el monto de esta cuota' : 'Monto fijo para cuotas anteriores a mayo de 2026') ?>"
                        >RD$ <?= number_format($dueAmount,2,'.',',') ?></div>
                        <input
                          type="hidden"
                          class="due-amount-input"
                          name="due_amounts[<?= e($venc) ?>]"
                          value="<?= number_format($dueAmount,2,'.','') ?>"
                        >
	                  </div>
	                </div>
	              <?php endforeach; ?>
	            </div>
            <div id="noDueMessage" class="alert alert-success mb-0 d-none">
              No hay cuotas pendientes. Próximo vencimiento (día <?= e(DUE_DAY) ?>):
              <strong><?= e(fecha_larga_es($proximo)) ?></strong>.
            </div>
          <?php else: ?>
            <div id="noDueMessage" class="alert alert-success mb-3">
              No hay cuotas pendientes. Próximo vencimiento (día <?= e(DUE_DAY) ?>):
              <strong><?= e(fecha_larga_es($proximo)) ?></strong>.
            </div>
            <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-2" id="dueList"></div>
          <?php endif; ?>
        </div>

	        <input type="hidden" name="fecha_x_pagar" id="fecha_x_pagar" value="">
	        <input type="hidden" id="cuotaMonto" value="<?= number_format(CUOTA_MONTO,2,'.','') ?>">
            <input type="hidden" id="cuotaMontoNuevo" value="<?= number_format(CUOTA_MONTO_NUEVO,2,'.','') ?>">
            <input type="hidden" id="cuotaMontoNuevoDesde" value="<?= e(CUOTA_MONTO_NUEVO_DESDE) ?>">
            <input type="hidden" id="cuotaMontoResidente" value="<?= e(!empty($data['cuota_mensual']) && (float)$data['cuota_mensual'] > 0 ? number_format((float)$data['cuota_mensual'],2,'.','') : '') ?>">
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
        <button class="btn btn-primary" id="btnSubmit"><?= $editing ? 'Crear recibo' : 'Guardar' ?></button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
      </div>

    </form>
  </div></div>

  <?php
  render_footer(); exit;
}

header('Location:index.php'); exit;
