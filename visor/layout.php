<?php
/*********** Layout (header + footer) ***********/
function header_html($title='Residentes'){ ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($title)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
 body{background:#f6f7fb}.card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .btn-rounded{border-radius:2rem}.table thead th{font-weight:600}

 /* Acciones solo en hover/focus (accesible) */
 td .actions{visibility:hidden; opacity:0; transition:opacity .18s ease-in-out; white-space:nowrap;}
 tbody tr:hover .actions,
 tbody tr:focus-within .actions{visibility:visible; opacity:1;}

 /* En pantallas táctiles (sin hover), siempre visibles */
 @media (hover:none){
   td .actions{visibility:visible; opacity:1;}
 }

 /* Mantener ancho estable para evitar saltos */
 th.actions-col, td.actions-col{width: 140px;}
 .nav-link.active{background:#0d6efd;color:#fff!important}
</style>
</head><body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm"><div class="container">
  <a class="navbar-brand fw-bold" href="../coopnama/contactos/visor.php">COOPNAMA II — Visor</a>
  <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
    <div class="nav nav-pills small">
      <a class="nav-link" href="../contactos/index.php">Residentes</a>
      <a class="nav-link active" href="../contactos/visor.php">Visor</a>
      <a class="nav-link" href="../automovilist/index.php">Vehículos</a>
    </div>
    <a href="?action=new" class="btn btn-primary btn-sm ">Agregar</a>
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
