<?php
// index/layout.php – header/footer comunes

function render_header(string $title='Residentes', string $active='residentes'){
  $action = $_GET['action'] ?? 'index';
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
   --slate-100:#f4f6fb;
   --slate-200:#eef1f7;
   --slate-600:#2c3648;
   --slate-700:#1f2c3d;
 }
 body{background:var(--slate-100);color:var(--slate-600);}
 .app-shell{min-height:100vh;background:var(--slate-100);}
 .sidebar{width:270px;background:#fff;border-right:1px solid #e7ebf3;box-shadow:6px 0 24px rgba(0,0,0,.04);}
 .brand{font-weight:700;color:var(--slate-700);}
 .brand small{color:#7a8596;}
 .sidebar .section-label{letter-spacing:.05em;text-transform:uppercase;font-size:.78rem;font-weight:700;color:#808aa0;margin:1.1rem .35rem .4rem;}
 .sidebar .nav-link{display:flex;align-items:center;gap:.65rem;color:#2d394c;border-radius:.75rem;padding:.65rem .8rem;font-weight:600;}
 .sidebar .nav-link:hover{background:#f1f4ff;color:#0d6efd;}
 .sidebar .nav-link.active{background:#0d6efd;color:#fff;box-shadow:0 10px 22px rgba(13,110,253,.2);}
 .sidebar hr{margin:1.2rem 0;color:#eef1f6;}
 .topbar{background:#fff;border-bottom:1px solid #e7ebf3;box-shadow:0 3px 18px rgba(0,0,0,.05);}
 .page-heading h1{font-weight:700;color:var(--slate-700);margin-bottom:0;}
 .page-heading .eyebrow{text-transform:uppercase;letter-spacing:.06em;color:#8a93a5;font-size:.75rem;font-weight:700;}
 main.content-body{padding:24px;}
 .card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .table thead th{font-weight:600}
 .table-nowrap td,
 .table-nowrap th{white-space:nowrap}
</style>
</head><body>
<div class="app-shell d-flex">
  <aside class="sidebar d-flex flex-column">
    <div class="p-4 pb-3 border-bottom">
      <div class="brand fs-5">COOPNAMA II</div>
      <div class="text-muted small">Panel administrativo</div>
    </div>
    <div class="flex-grow-1 p-3">
      <div class="section-label">Residentes</div>
      <div class="nav flex-column">
        <a class="nav-link <?= ($active==='residentes' && $action==='index')?'active':'' ?>" href="/eo/coopnama/contactos/index.php?page=residentes">
          <i class="bi bi-card-checklist"></i><span>Listado y cobros</span>
        </a>
        <a class="nav-link <?= ($active==='residentes' && $action==='new')?'active':'' ?>" href="/eo/coopnama/contactos/index.php?action=new">
          <i class="bi bi-person-plus"></i><span>Registrar residente</span>
        </a>
        <a class="nav-link <?= ($active==='visor')?'active':'' ?>" href="/eo/coopnama/contactos/visor.php?action=full">
          <i class="bi bi-window-sidebar"></i><span>Visor completo</span>
        </a>
      </div>

      <hr>

      <div class="section-label">Pagos</div>
      <div class="nav flex-column">
        <a class="nav-link <?= ($active==='pagos')?'active':'' ?>" href="/eo/coopnama/contactos/index.php?page=pagos">
          <i class="bi bi-currency-dollar"></i><span>Pagos registrados</span>
        </a>
      </div>

      <hr>

      <div class="section-label">Vehículos</div>
      <div class="nav flex-column">
        <a class="nav-link" href="/eo/automovilist/index.php">
          <i class="bi bi-truck-front"></i><span>Registrar vehículo</span>
        </a>
      </div>
    </div>
    <div class="p-3 border-top text-muted small">
      <i class="bi bi-info-circle me-1"></i>Atajos rápidos para navegar.
    </div>
  </aside>

  <div class="content-area flex-grow-1 d-flex flex-column">
    <div class="topbar px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="page-heading">
        <div class="eyebrow">Panel administrativo</div>
        <h1 class="h4 mb-0"><?= e($title) ?></h1>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/eo/coopnama/contactos/visor.php?action=full">
          <i class="bi bi-layout-sidebar me-1"></i>Visor
        </a>
        <a href="/eo/coopnama/contactos/index.php?action=new" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>Agregar
        </a>
      </div>
    </div>
    <main class="content-body flex-grow-1">
<?php }

function render_footer(){ ?>
    </main>
  </div>
</div>
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
