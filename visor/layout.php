<?php
/*********** Layout (header + footer) ***********/
function header_html($title='Residentes'){
  $viewAction = $_GET['action'] ?? 'full';
  $isResNew  = ($viewAction === 'new' || $viewAction === 'edit');
  $isVisor   = ($viewAction === 'full');
  $isResList = !$isVisor && !$isResNew;
  $isSupport = ($viewAction === 'support');
  $isDev     = ($viewAction === 'dev');
  $showDev   = isset($_GET['clave']) && $_GET['clave'] === DEV_ACCESS_KEY;
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
   transition:transform .2s ease-in-out;
   z-index:950;
 }
 .sidebar-backdrop{
   position:fixed; top:64px; left:0; right:0; bottom:0;
   background:rgba(15,23,42,.35);
   display:none;
   z-index:900;
 }
 body.sidebar-open .sidebar-backdrop{display:block;}
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
 .content{margin-left:280px;padding:90px 30px 60px;min-height:100vh;transition:margin-left .2s ease-in-out;}
 .content-inner{max-width:1400px;margin:0 auto;}
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
 body.sidebar-collapsed .sidebar{transform:translateX(-100%);}
 body.sidebar-collapsed .content{margin-left:0;}
@media (max-width: 992px){
  .sidebar{transform:translateX(-100%);}
  body.sidebar-open .sidebar{transform:translateX(0);}
  .content{margin-left:0;padding:90px 20px 40px;}
}
</style>
</head><body>
<header class="topbar">
  <div class="brand">
    <button type="button" id="sidebarToggle" class="btn btn-outline-secondary btn-sm" aria-label="Menú">
      <i class="bi bi-list"></i>
    </button>
    COOPNAMA II
  </div>
</header>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

<nav class="sidebar">
  <div class="section-title">Propietarios</div>
  <a class="menu-item <?= $isResNew?'active':'' ?>" href="?action=new">
    <i class="bi bi-person-plus"></i><span>Registrar residente</span>
  </a>
  <a class="menu-item <?= $isVisor?'active':'' ?>" href="?action=full">
    <i class="bi bi-card-checklist"></i><span>Registro</span>
  </a>

  <?php if($showDev): ?>
    <hr>
    <div class="section-title">Dev</div>
    <a class="menu-item <?= $isDev?'active':'' ?>" href="/eo/coopnama/contactos/visor/dev/pagos_duplicados.php?clave=<?= urlencode(DEV_ACCESS_KEY) ?>">
      <i class="bi bi-tools"></i><span>Pagos duplicados</span>
    </a>
  <?php endif; ?>

  <hr>

  <div class="section-title">Deudores</div>
  <a class="menu-item <?= $isResList?'active':'' ?>" href="/eo/coopnama/contactos/index.php?page=residentes">
    <i class="bi bi-people"></i><span>Pagos</span>
  </a>
  <a class="menu-item" href="/eo/coopnama/contactos/index.php?page=pagos">
    <i class="bi bi-check2-circle"></i><span>Registro de pagos</span>
  </a>

  <hr>

  <!-- <div class="section-title">Vehículos</div>
  <a class="menu-item" href="/eo/automovilist/insert.php">
    <i class="bi bi-plus-square"></i><span>Registrar vehículo</span>
  </a>
  <a class="menu-item" href="/eo/automovilist/index.php">
    <i class="bi bi-car-front"></i><span>Listado de vehículos</span>
  </a> -->

  <hr>

  <div class="section-title">Soporte</div>
  <a class="menu-item <?= $isSupport?'active':'' ?>" href="/eo/automovilist/reporte_inconveniente.php">
    <i class="bi bi-life-preserver"></i><span>Reportar inconveniente</span>
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
      pageLength:100,
      lengthMenu:[5,10,25,50,100],
      language:{url:'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'},
      columnDefs:[{targets:-1, className:'text-center'}]
    });
  }
  $(document).on('click','.btn-delete',function(e){ if(!confirm('¿Eliminar este registro?')) e.preventDefault(); });

  // Sidebar: colapsable en desktop y offcanvas en móvil
  (function(){
    var toggle = document.getElementById('sidebarToggle');
    if (!toggle) return;
    var backdrop = document.getElementById('sidebarBackdrop');
    var mq = window.matchMedia('(max-width: 992px)');
    function isMobile(){ return mq.matches; }
    function isSidebarVisible(){
      return isMobile() ? document.body.classList.contains('sidebar-open')
                        : !document.body.classList.contains('sidebar-collapsed');
    }
    function closeMobile(){ document.body.classList.remove('sidebar-open'); }
    function setCollapsed(v){
      if (v) document.body.classList.add('sidebar-collapsed');
      else document.body.classList.remove('sidebar-collapsed');
      try { localStorage.setItem('sidebarCollapsed', v ? '1' : '0'); } catch(e){}
    }
    function toggleSidebar(){
      if (isMobile()) {
        document.body.classList.toggle('sidebar-open');
        if (document.body.classList.contains('sidebar-open')) {
          var first = document.querySelector('.sidebar a.menu-item');
          if (first) first.focus();
        }
      } else {
        setCollapsed(!document.body.classList.contains('sidebar-collapsed'));
        if (!document.body.classList.contains('sidebar-collapsed')) {
          var first = document.querySelector('.sidebar a.menu-item');
          if (first) first.focus();
        }
      }
    }
    window.__toggleSidebar = toggleSidebar;
    window.__isSidebarVisible = isSidebarVisible;
    try {
      if (!isMobile() && localStorage.getItem('sidebarCollapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
      }
    } catch(e){}
    toggle.addEventListener('click', toggleSidebar);
    if (backdrop) backdrop.addEventListener('click', closeMobile);
    window.addEventListener('resize', function(){ if (!isMobile()) closeMobile(); });
    document.querySelectorAll('.sidebar a.menu-item').forEach(function(a){
      a.addEventListener('click', closeMobile);
    });
    document.addEventListener('keydown', function(ev){
      if (ev.key === 'Escape') closeMobile();
    });
  })();

  // Atajos de teclado (ver layout principal)
  (function(){
    function isEditable(el){
      if (!el) return false;
      var tag = (el.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
      return !!el.isContentEditable;
    }
    function isVisible(el){
      return !!(el && el.offsetParent !== null);
    }
    function getSearch(){
      var s = document.getElementById('globalSearch');
      if (s && isVisible(s) && !s.disabled) return s;
      s = document.querySelector('input[type="search"]');
      if (s && isVisible(s) && !s.disabled) return s;
      return null;
    }
    function sidebarItems(){
      return Array.prototype.slice.call(document.querySelectorAll('.sidebar a.menu-item'))
        .filter(isVisible);
    }
    function moveSidebarFocus(dir){
      var items = sidebarItems();
      if (!items.length) return;
      var cur = document.activeElement;
      var idx = items.indexOf(cur);
      if (idx === -1) idx = (dir === 'down') ? -1 : 0;
      var nextIdx = (dir === 'down')
        ? Math.min(items.length - 1, idx + 1)
        : Math.max(0, idx - 1);
      var next = items[nextIdx] || items[0];
      next.focus();
      try { next.scrollIntoView({block:'nearest'}); } catch(e){}
    }
    document.addEventListener('keydown', function(ev){
      if (ev.defaultPrevented) return;
      if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
      if (isEditable(ev.target)) return;

      if (ev.key === 'Enter') {
        if (typeof window.__toggleSidebar === 'function') {
          ev.preventDefault();
          window.__toggleSidebar();
        }
        return;
      }
      if ((ev.key === 'ArrowDown' || ev.key === 'ArrowUp') && typeof window.__isSidebarVisible === 'function' && window.__isSidebarVisible()) {
        ev.preventDefault();
        moveSidebarFocus(ev.key === 'ArrowDown' ? 'down' : 'up');
        return;
      }
      if (ev.key && ev.key.length === 1) {
        var s = getSearch();
        if (!s) return;
        ev.preventDefault();
        s.focus();
        var start = (typeof s.selectionStart === 'number') ? s.selectionStart : s.value.length;
        var end   = (typeof s.selectionEnd === 'number') ? s.selectionEnd : s.value.length;
        var v = s.value || '';
        s.value = v.slice(0, start) + ev.key + v.slice(end);
        var pos = start + ev.key.length;
        try { s.setSelectionRange(pos, pos); } catch(e){}
        s.dispatchEvent(new Event('input', {bubbles:true}));
      }
    });
  })();
});
</script>
</body></html>
<?php }
