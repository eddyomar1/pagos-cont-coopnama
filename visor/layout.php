<?php
/*********** Layout (header + footer) ***********/
function header_html($title='Residentes'){
  $viewAction = $_GET['action'] ?? 'full';
  $isResNew  = ($viewAction === 'new' || $viewAction === 'edit');
  $isVisor   = ($viewAction === 'full');
  $isResList = !$isVisor && !$isResNew;
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($title)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
 :root{
   --bg-body:#f8fafc;
   --bg-sidebar:#ffffff;
   --bg-topbar:#ffffff;
   --primary:#2563eb;
   --text-primary:#1e293b;
   --text-secondary:#64748b;
   --border:#e2e8f0;
   --hover:#f1f5f9;
   --shadow-sm:0 1px 3px rgba(0,0,0,0.1);
   --shadow-md:0 4px 12px rgba(0,0,0,0.08);
   --radius:12px;
 }
 *{box-sizing:border-box;margin:0;padding:0;}
 body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg-body);color:var(--text-primary);}
 .topbar{
   height:64px;background:var(--bg-topbar);border-bottom:1px solid var(--border);
   display:flex;align-items:center;justify-content:space-between;gap:16px;
   padding:0 24px;position:fixed;top:0;left:0;right:0;z-index:1000;
   box-shadow:var(--shadow-sm);font-size:20px;font-weight:600;
 }
 .topbar .brand{display:flex;align-items:center;gap:10px;}
 .sidebar{
   width:280px;background:var(--bg-sidebar);border-right:1px solid var(--border);
   position:fixed;top:64px;left:0;bottom:0;padding:24px 16px;overflow-y:auto;
   box-shadow:var(--shadow-md);
 }
 .section-title{
   font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;
   color:var(--text-secondary);margin:24px 12px 10px;
 }
 .menu-item{
   display:flex;align-items:center;padding:12px 14px;margin:4px 8px;border-radius:var(--radius);
   color:var(--text-primary);text-decoration:none;font-weight:500;transition:all .2s ease;
 }
 .menu-item:hover{background:var(--hover);transform:translateX(4px);}
 .menu-item svg,.menu-item i{width:22px;height:22px;margin-right:14px;opacity:.8;flex-shrink:0;}
 .menu-item:hover svg,.menu-item:hover i{opacity:1;}
 .menu-item.active{background:var(--primary);color:#fff;box-shadow:var(--shadow-sm);transform:translateX(4px);}
 .menu-item.active svg,.menu-item.active i{opacity:1;color:#fff;}
 .content{margin-left:280px;padding:90px 40px 60px;min-height:100vh;}
 .content-inner{max-width:1200px;margin:0 auto;}
 hr{border:none;border-top:1px solid var(--border);margin:20px 12px;}
 .card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .btn-rounded{border-radius:2rem}.table thead th{font-weight:600}
 /* Acciones solo en hover/focus (accesible) */
 td .actions{visibility:hidden; opacity:0; transition:opacity .18s ease-in-out; white-space:nowrap;}
 tbody tr:hover .actions,
 tbody tr:focus-within .actions{visibility:visible; opacity:1;}
 /* En pantallas táctiles (sin hover), siempre visibles */
 @media (hover:none){ td .actions{visibility:visible; opacity:1;} }
 th.actions-col, td.actions-col{width: 140px;}
 @media (max-width: 992px){
   .sidebar{transform:translateX(-100%);}
   .content{margin-left:0;padding:90px 20px 40px;}
 }
</style>
</head><body>
<header class="topbar">
  <div class="brand">COOPNAMA II</div>
</header>

<nav class="sidebar">
  <div class="section-title">Propietarios</div>
  <a class="menu-item <?= $isResNew?'active':'' ?>" href="?action=new">
    <i class="bi bi-person-plus"></i><span>Registrar residente</span>
  </a>
  <a class="menu-item <?= $isVisor?'active':'' ?>" href="?action=full">
    <i class="bi bi-card-checklist"></i><span>Visor completo</span>
  </a>

  <hr>

  <div class="section-title">Deudores</div>
  <a class="menu-item <?= $isResList?'active':'' ?>" href="/eo/coopnama/contactos/index.php?page=residentes">
    <i class="bi bi-people"></i><span>Ver todos</span>
  </a>
  <a class="menu-item" href="/eo/coopnama/contactos/index.php?page=pagos">
    <i class="bi bi-check2-circle"></i><span>Pagados</span>
  </a>
  <a class="menu-item <?= $isVisor?'active':'' ?>" href="?action=full">
    <i class="bi bi-exclamation-circle"></i><span>Pendientes</span>
  </a>

  <hr>

  <div class="section-title">Vehículos</div>
  <a class="menu-item" href="/eo/automovilist/insert.php">
    <i class="bi bi-plus-square"></i><span>Registrar vehículo</span>
  </a>
  <a class="menu-item" href="/eo/automovilist/index.php">
    <i class="bi bi-car-front"></i><span>Listado de vehículos</span>
  </a>
</nav>

<main class="content">
  <div class="content-inner">
<?php }

function footer_html(){ ?>
  </div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  var $tbl=$('#tabla');
  if($tbl.length){
    $tbl.DataTable({
      pageLength:10,
      lengthMenu:[5,10,25,50,100],
      language:{url:'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'},
      columnDefs:[{targets:-1, className:'text-center'}]
    });
  }
  $(document).on('click','.btn-delete',function(e){ if(!confirm('¿Eliminar este registro?')) e.preventDefault(); });
});
</script>
</body></html>
<?php }
