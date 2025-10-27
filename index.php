<?php
/*************************************************
 * CRUD Residentes (PDO + Bootstrap + DataTables)
 * Vista simple y Vista completa (todas las columnas)
 *************************************************/
session_start();

/*********** 1) Conexión ***********/
$dbHost = 'localhost';
$dbName = 'u138076177_pw';
$dbUser = 'u138076177_chacharito';
$dbPass = '3spWifiPruev@';
$dsn    = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
try { $pdo = new PDO($dsn, $dbUser, $dbPass, $options); }
catch(Throwable $e){ http_response_code(500); exit("DB error: ".htmlspecialchars($e->getMessage())); }

/*********** 2) Helpers ***********/
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function body($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function required($v){ return isset($v) && trim((string)$v) !== ''; }
function digits_only($s){ return preg_replace('/\D+/','',$s); }
function format_cedula($d){ $d=digits_only($d); return strlen($d)===11?substr($d,0,3).'-'.substr($d,3,7).'-'.substr($d,10,1):$d; }
function cedula_valida($digits){
  $d = digits_only($digits); if(strlen($d)!==11) return false;
  $m=[1,2,1,2,1,2,1,2,1,2]; $s=0;
  for($i=0;$i<10;$i++){ $p=$d[$i]*$m[$i]; if($p>=10)$p=intdiv($p,10)+($p%10); $s+=$p; }
  return ( (10-($s%10))%10 ) == $d[10];
}
function toDecimal($v){ // admite "1,000.50" o "1000,50"
  $v = trim((string)$v); if($v==='') return null;
  $v = str_replace([' ', ','], ['', '.'], $v);
  return is_numeric($v) ? number_format((float)$v, 2, '.', '') : null;
}
function toDateOrNull($v){
  $v = trim((string)$v); if($v==='') return null;
  // admite YYYY-MM-DD o DD/MM/YYYY
  if(preg_match('~^\d{4}-\d{2}-\d{2}$~',$v)) return $v;
  if(preg_match('~^(\d{2})/(\d{2})/(\d{4})$~',$v,$m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
  return null;
}

$action = $_GET['action'] ?? 'index';

/*********** 3) Acciones CRUD ***********/
if ($action === 'store' && $_SERVER['REQUEST_METHOD']==='POST') {
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $fecha_x_pagar     = toDateOrNull(body('fecha_x_pagar'));
  $fecha_pagada      = toDateOrNull(body('fecha_pagada'));
  $mora              = toDecimal(body('mora')) ?? 0;
  $monto_a_pagar     = toDecimal(body('monto_a_pagar')) ?? 0;
  $monto_pagado      = toDecimal(body('monto_pagado')) ?? 0;
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_in))         $errors[]="Cédula es obligatoria.";
  $cedula_digits = digits_only($cedula_in);
  if($cedula_in && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";

  if($errors){ $_SESSION['errors']=$errors; $_SESSION['old']=$_POST; header('Location:?action=new'); exit; }

  try{
    $stmt=$pdo->prepare(
      "INSERT INTO residentes
       (edif_apto,nombres_apellidos,cedula,codigo,telefono,fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,no_recurrente)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
      $edif_apto,$nombres_apellidos,$cedula_digits,$codigo ?: null,$telefono ?: null,
      $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$no_recurrente
    ]);
    header('Location:?saved=1'); exit;
  }catch(PDOException $ex){
    $_SESSION['errors']=[ "No se pudo guardar: ".$ex->getCode() ];
    $_SESSION['old']=$_POST; header('Location:?action=new'); exit;
  }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD']==='POST') {
  $id                = (int)body('id');
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $fecha_x_pagar     = toDateOrNull(body('fecha_x_pagar'));
  $fecha_pagada      = toDateOrNull(body('fecha_pagada'));
  $mora              = toDecimal(body('mora')) ?? 0;
  $monto_a_pagar     = toDecimal(body('monto_a_pagar')) ?? 0;
  $monto_pagado      = toDecimal(body('monto_pagado')) ?? 0;
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  $errors=[];
  if($id<=0)                         $errors[]="ID inválido.";
  if(!required($edif_apto))          $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos))  $errors[]="Nombres y Apellidos es obligatorio.";
  if(!required($cedula_in))          $errors[]="Cédula es obligatoria.";
  $cedula_digits = digits_only($cedula_in);
  if($cedula_in && !cedula_valida($cedula_digits)) $errors[]="Cédula no válida.";

  if($errors){ $_SESSION['errors']=$errors; $_SESSION['old']=$_POST; header('Location:?action=pagar&id='.$id); exit; }

  try{
    $stmt=$pdo->prepare(
      "UPDATE residentes SET
        edif_apto=?, nombres_apellidos=?, cedula=?, codigo=?, telefono=?,
        fecha_x_pagar=?, fecha_pagada=?, mora=?, monto_a_pagar=?, monto_pagado=?, no_recurrente=?
       WHERE id=?"
    );
    $stmt->execute([
      $edif_apto,$nombres_apellidos,$cedula_digits,$codigo ?: null,$telefono ?: null,
      $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$no_recurrente,$id
    ]);
    header('Location:?updated=1'); exit;
  }catch(PDOException $ex){
    $_SESSION['errors']=[ "No se pudo actualizar: ".$ex->getCode() ];
    $_SESSION['old']=$_POST; header('Location:?action=pagar&id='.$id); exit;
  }
}

if ($action === 'delete' && isset($_GET['id'])) {
  $id=(int)$_GET['id']; if($id>0){ $pdo->prepare("DELETE FROM residentes WHERE id=?")->execute([$id]); }
  header('Location:?deleted=1'); exit;
}

/*********** 4) Layout ***********/
function header_html($title='Residentes', $isFull=false){ ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($title)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
 body{background:#f6f7fb}.card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .btn-rounded{border-radius:2rem}.table thead th{font-weight:600}
 
 
 
</style>
</head><body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm"><div class="container">
  <a class="navbar-brand fw-bold" href="?">RESIDENCIAL COOPNAMA II</a>
  <div class="ms-auto d-flex gap-2">

  </div>
</div></nav>
<main class="container my-4">
<?php }
function footer_html(){ ?>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  var $tbl = $('#tabla');
  if ($tbl.length) {
    // Ocultamos la colocación por defecto de length (l) y filter (f) y luego los movemos
    var dt = $tbl.DataTable({
      pageLength: 10,
      lengthMenu: [5,10,25,50,100],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
      dom: 't<"d-none"lf>ip',   // l = length, f = filter (se renderizan dentro de un contenedor oculto)
      columnDefs: [{ targets: -1, className: 'text-center' }] // centra la columna Acciones
    });

    // Mueve los controles al contenedor que creamos en el card-header
    var $wrap = $(dt.table().container());
    var $controls = $('.dt-controls');

    $controls
      .append($wrap.find('.dataTables_length'))   // "Mostrar X registros"
      .append($wrap.find('.dataTables_filter'));  // "Buscar"

    // Unos retoques de Bootstrap
    $controls.find('select').addClass('form-select form-select-sm');
    $controls.find('input[type="search"]').addClass('form-control form-control-sm').attr('placeholder','Buscar...');
    $controls.find('label').addClass('mb-0'); // compacta
  }

  $(document).on('click', '.btn-delete', function(e){
    if (!confirm('¿Eliminar este registro?')) e.preventDefault();
  });
});
</script>

</body></html>
<?php }

/*********** 5) Vistas ***********/

/* 5.1 Listado simple */
if ($action==='index') {
  header_html('Residentes', false);
  $rows=$pdo->query("SELECT * FROM residentes ORDER BY id DESC")->fetchAll();
  if(isset($_GET['saved']))   echo '<div class="alert alert-success">Registro agregado.</div>';
  if(isset($_GET['updated'])) echo '<div class="alert alert-info">Registro actualizado.</div>';
  if(isset($_GET['deleted'])) echo '<div class="alert alert-warning">Registro eliminado.</div>';
  ?>
  <div class="card">
      
  <!-- NUEVO: encabezado para los controles -->
  <div class="card-header bg-white">
    <div class="dt-controls d-flex flex-wrap gap-3 justify-content-between align-items-center"></div>
  </div>


  
  <div class="card-body">
    <h5 class="mb-3">LISTA DE COPROPIETARIOS</h5>
    <div class="table-responsive">
      <table id="tabla" class="table table-striped table-bordered align-middle">
        <thead class="table-light"><tr>
          <!--th>ID</th--><th>Edif/Apto</th><th>Nombres y Apellidos</th><th>Cédula</th><th>Teléfono</th><th>Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= e($r['edif_apto']) ?></td>
            <td><?= e($r['nombres_apellidos']) ?></td>
            <td><?= e(format_cedula($r['cedula'])) ?></td>
            <td><?= e($r['telefono']) ?></td>
            <td class="text-center" id="btnpagar">
              <a class="btn btn-primary btn-sm" href="?action=pagar&id=<?=$r['id']?>">PAGAR MANTENIMIENTO</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div></div>
  <?php footer_html(); exit;
}


/* 5.3 Formulario (Nuevo/pagar) */
if ($action==='new' || $action==='pagar') {
  $editing = $action==='pagar';
  $data=['id'=>null,'edif_apto'=>'','nombres_apellidos'=>'','cedula'=>'','codigo'=>'','telefono'=>'',
         'fecha_x_pagar'=>'','fecha_pagada'=>'','mora'=>'','monto_a_pagar'=>'','monto_pagado'=>'','no_recurrente'=>0];

  if($editing){
    $id=(int)($_GET['id'] ?? 0);
    if($id<=0){ header('Location:?'); exit; }
    $st=$pdo->prepare("SELECT * FROM residentes WHERE id=?"); $st->execute([$id]); $row=$st->fetch();
    if(!$row){ header('Location:?'); exit; } $data=array_merge($data,$row);
  }
  if(!empty($_SESSION['old'])) $data=array_merge($data,$_SESSION['old']);
  $errors=$_SESSION['errors'] ?? []; $_SESSION['old']=$_SESSION['errors']=null;

  header_html($editing?'Editar residente':'Agregar residente', false);
  ?>
  <div class="row justify-content-center"><div class="col-lg-10">
  <div class="card"><div class="card-body">
    <h5 class="card-title mb-3"><?=$editing?'pagar':'Agregar'?> residente</h5>
    <?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $m) echo "<li>".e($m)."</li>"; ?></ul></div><?php endif; ?>

    <form method="post" action="?action=<?=$editing?'update':'store'?>">
      <?php if($editing): ?><input type="hidden" name="id" value="<?= (int)$data['id'] ?>"><?php endif; ?>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Edif/Apto *</label>
          <input type="text" name="edif_apto" class="form-control" maxlength="60" value="<?=e($data['edif_apto'])?>" disabled>
        </div>
        <div class="col-md-5">
          <label class="form-label">Nombres y Apellidos *</label>
          <input type="text" name="nombres_apellidos" class="form-control" maxlength="255" value="<?=e($data['nombres_apellidos'])?>" disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label">Cédula *</label>
          <input type="text" name="cedula" class="form-control" maxlength="13" placeholder="001-1234567-8" value="<?=e(format_cedula($data['cedula']))?>" disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label">Código</label>
          <input type="text" name="codigo" class="form-control" maxlength="30" value="<?=e($data['codigo'])?>" disabled>
        </div>

        <div class="col-md-3">
          <label class="form-label">Teléfono</label>
          <input type="text" name="telefono" class="form-control" maxlength="50" value="<?=e($data['telefono'])?>" disabled>
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha x Pagar</label>
          <input type="date" name="fecha_x_pagar" class="form-control" value="<?=e($data['fecha_x_pagar'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha Pagada</label>
          <input type="date" name="fecha_pagada" class="form-control" value="<?=e($data['fecha_pagada'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Mora</label>
          <input type="text" name="mora" class="form-control" placeholder="0.00" value="<?=e($data['mora'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Monto a Pagar</label>
          <input type="text" name="monto_a_pagar" class="form-control" placeholder="0.00" value="<?=e($data['monto_a_pagar'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Monto Pagado</label>
          <input type="text" name="monto_pagado" class="form-control" placeholder="0.00" value="<?=e($data['monto_pagado'])?>">
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary"><?=$editing?'Actualizar':'Guardar'?></button>
        <a class="btn btn-outline-secondary" href="?">Cancelar</a>
      </div>
    </form>
  </div></div></div></div>
  <?php footer_html(); exit;
}

header('Location:?'); exit;
