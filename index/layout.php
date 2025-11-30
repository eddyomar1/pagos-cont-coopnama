<?php
// index/layout.php – header/footer comunes con menú fijo unificado

function render_header(string $title='Residentes', string $active='residentes'){
  $action = $_GET['action'] ?? 'index';
  $isResList = ($active === 'residentes' && $action !== 'new');
  $isResNew  = ($active === 'residentes' && $action === 'new');
  $isVisor   = ($active === 'visor');
  $isPagos   = ($active === 'pagos');
  $isVehList = ($active === 'vehiculos' && $action !== 'new');
  $isVehNew  = ($active === 'vehiculos' && $action === 'new');
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
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
 .table thead th{font-weight:600}
 .table-nowrap td,.table-nowrap th{white-space:nowrap}
 .actions-col{width:140px}
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
  <a href="/eo/coopnama/contactos/index.php?action=new" class="menu-item <?= $isResNew?'active':'' ?>">
    <i class="bi bi-person-plus"></i><span>Registrar residente</span>
  </a>
  <a href="/eo/coopnama/contactos/visor.php?action=full" class="menu-item <?= $isVisor?'active':'' ?>">
    <i class="bi bi-card-checklist"></i><span>Registro</span>
  </a>

  <hr>

  <div class="section-title">Deudores</div>
  <a href="/eo/coopnama/contactos/index.php?page=residentes" class="menu-item <?= $isResList?'active':'' ?>">
    <i class="bi bi-people"></i><span>Pagos</span>
  </a>
  <a href="/eo/coopnama/contactos/index.php?page=pagos" class="menu-item <?= $isPagos?'active':'' ?>">
    <i class="bi bi-check2-circle"></i><span>Registro de pagos</span>
  </a>

  <hr>

  <div class="section-title">Vehículos</div>
  <a href="/eo/automovilist/index.php" class="menu-item <?= $isVehList?'active':'' ?>">
    <i class="bi bi-car-front"></i><span>Listado de vehículos</span>
  </a>
  <a href="/eo/automovilist/insert.php" class="menu-item <?= $isVehNew?'active':'' ?>">
    <i class="bi bi-plus-square"></i><span>Registrar vehículo</span>
  </a>
</nav>

<main class="content">
  <div class="content-inner">
<?php }

function render_footer(){ ?>
  </div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  var cuotaMonto = parseFloat($('#cuotaMonto').val() || '1000');
  if (isNaN(cuotaMonto) || cuotaMonto <= 0) {
    cuotaMonto = 1000;
  }

  // === DataTable RESIDENTES ===
  var $tbl1 = $('#tabla');
  if ($tbl1.length) {
    var dt1 = $tbl1.DataTable({
      pageLength: 10,
      lengthMenu: [5,10,25,50,100],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
      dom: 'tip',
      columnDefs: [{ targets: -1, className: 'text-center' }]
    });
    $('#globalSearch').on('input', function(){ dt1.search(this.value).draw(); });
    $('#lenSelect').on('change', function(){ dt1.page.len(parseInt(this.value,10)).draw(); });
    $('#lenSelect').val(dt1.page.len());
  }

  // === DataTable PAGOS ===
  var $tbl2 = $('#tabla_pagos');
  if ($tbl2.length) {
    var dt2 = $tbl2.DataTable({
      pageLength: 10,
      lengthMenu: [5,10,25,50,100],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
      dom: 'tip',
      order: [[1,'desc']]
    });
    $('#globalSearch').on('input', function(){ dt2.search(this.value).draw(); });
    $('#lenSelect').on('change', function(){ dt2.page.len(parseInt(this.value,10)).draw(); });
    $('#lenSelect').val(dt2.page.len());
  }

  // === CUOTAS + DEUDA EXTRA ===
  var $moraInput = $('input[name="mora"]');
  var $montoPagarInput = $('input[name="monto_a_pagar"]');

  function recalcDueSelection(){
    var $boxes = $('.due-option:checked');
    var count  = $boxes.length;
    var totalCuotas = count * cuotaMonto;

    var deudaStr = $('#deuda_restante').val() || $('#deuda_extra_actual').val() || '0';
    deudaStr = deudaStr.replace(',', '.');
    var deudaActual = parseFloat(deudaStr);
    if (isNaN(deudaActual)) deudaActual = 0;

    var abonoStr = $('#abono_deuda_extra').val() || '0';
    abonoStr = abonoStr.replace(',', '.');
    var abono = parseFloat(abonoStr);
    if (isNaN(abono)) abono = 0;

    var totalBase = totalCuotas + abono;
    var moraValue = 0;
    if ($moraInput.length) {
      var moraStr = ($moraInput.val() || '0').replace(',', '.');
      var moraParsed = parseFloat(moraStr);
      if (!isNaN(moraParsed) && moraParsed > 0) {
        moraValue = moraParsed;
      }
    }
    var totalConMora = totalBase + moraValue;

    if ($montoPagarInput.length){
      $montoPagarInput.val(totalConMora.toFixed(2));
    }

    $('#countSelected').text(count);
    var totalConMoraFormatted = totalConMora.toLocaleString('es-DO', {
      minimumFractionDigits:2,
      maximumFractionDigits:2
    });
    $('#totalSelected').text(totalConMoraFormatted);

    var despues = Math.max(0, deudaActual - abono);
    if ($('#deuda_despues').length){
      $('#deuda_despues').val(despues.toFixed(2));
    }
  }

  $(document).on('change', '.due-option', recalcDueSelection);
  $(document).on('input', '#abono_deuda_extra', recalcDueSelection);
  $(document).on('input', 'input[name="mora"]', recalcDueSelection);

  // === Adelantos (meses futuros) ===
  var $dueList = $('#dueList');
  var $noDueMessage = $('#noDueMessage');
  var $nextFutureDue = $('#nextFutureDue');
  var $btnAdvancePlus = $('#btnAdvancePlus');
  var $btnAdvanceMinus = $('#btnAdvanceMinus');
  var $advanceCounter = $('#advanceCounter');
  var advancesAdded = 0;
  var futureDueStack = [];
  var maxAdvances = 0;
  if ($nextFutureDue.length) {
    maxAdvances = parseInt($nextFutureDue.data('max-advances'), 10);
    if (isNaN(maxAdvances) || maxAdvances < 0) {
      maxAdvances = 0; // 0 => sin límite específico
    }
  }

  function remainingAdvances(){
    if (!maxAdvances) return Infinity;
    return Math.max(0, maxAdvances - advancesAdded);
  }

  function updateAdvanceControlsState(){
    var canAdd = $nextFutureDue.length && (!!$nextFutureDue.val()) && (!maxAdvances || remainingAdvances() > 0);
    if ($btnAdvancePlus.length) {
      $btnAdvancePlus.prop('disabled', !canAdd);
    }
    if ($btnAdvanceMinus.length) {
      $btnAdvanceMinus.prop('disabled', advancesAdded === 0);
    }
    if ($advanceCounter.length) {
      $advanceCounter.text(advancesAdded);
    }
  }

  function formatDueLabel(dateStr){
    var parts = (dateStr || '').split('-');
    if (parts.length !== 3) return dateStr;
    var day = parseInt(parts[2], 10);
    var monthIndex = parseInt(parts[1], 10) - 1;
    var year = parts[0];
    var meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    if (monthIndex < 0 || monthIndex > 11) return dateStr;
    return (day || 25) + ' de ' + meses[monthIndex] + ' de ' + year;
  }

  function addOneMonth(dateStr){
    var parts = (dateStr || '').split('-');
    if (parts.length !== 3) return dateStr;
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10);
    var day = parts[2];
    if (isNaN(year) || isNaN(month)) return dateStr;
    month += 1;
    if (month > 12) {
      month = 1;
      year += 1;
    }
    return year + '-' + String(month).padStart(2,'0') + '-' + day;
  }

  function toggleNoDueMessage(){
    if (!$noDueMessage.length || !$dueList.length) return;
    if ($dueList.children().length){
      $noDueMessage.addClass('d-none');
    } else {
      $noDueMessage.removeClass('d-none');
    }
  }

  function addSingleFutureDue(){
    if (!$nextFutureDue.length) return false;
    var nextDue = $nextFutureDue.val();
    if (!nextDue) return false;
    if (maxAdvances && advancesAdded >= maxAdvances) return false;

    var idx = $('.due-option').length + advancesAdded;
    var label = formatDueLabel(nextDue);
    var checkboxId = 'dueFuture' + idx;
    if ($dueList.length) {
      var $col = $('<div>', { 'class': 'col future-due', 'data-date': nextDue });
      var $check = $('<input>', {
        'class': 'form-check-input due-option',
        type: 'checkbox',
        name: 'selected_dues[]',
        id: checkboxId,
        value: nextDue,
        checked: true,
        'data-label': label
      });
      var $label = $('<label>', { 'class': 'form-check-label', 'for': checkboxId }).text(label);
      $col.append(
        $('<div>', { 'class': 'form-check' }).append($check, $label)
      );
      $dueList.append($col);
      futureDueStack.push({ id: checkboxId, date: nextDue });
      advancesAdded += 1;
      $nextFutureDue.val(addOneMonth(nextDue));
      toggleNoDueMessage();
      updateAdvanceControlsState();
      recalcDueSelection();
      return true;
    }
    return false;
  }

  function removeSingleFutureDue(){
    if (!futureDueStack.length) return false;
    var last = futureDueStack.pop();

    if (last.id) {
      $('#'+last.id).closest('.col').remove();
    } else {
      $('.future-due').last().closest('.col').remove();
    }

    advancesAdded = Math.max(0, advancesAdded - 1);
    $nextFutureDue.val(last.date);
    toggleNoDueMessage();
    updateAdvanceControlsState();
    recalcDueSelection();

    if (advancesAdded === 0 && $dueList.children().length === 0 && $noDueMessage.length){
      $noDueMessage.removeClass('d-none');
    }
    return true;
  }

  if ($btnAdvancePlus.length) {
    $btnAdvancePlus.on('click', function(){ addSingleFutureDue(); });
  }
  if ($btnAdvanceMinus.length) {
    $btnAdvanceMinus.on('click', function(){ removeSingleFutureDue(); });
  }
  if ($nextFutureDue.length) {
    updateAdvanceControlsState();
  }

  toggleNoDueMessage();
  recalcDueSelection();
});
</script>
</body></html>
<?php }
