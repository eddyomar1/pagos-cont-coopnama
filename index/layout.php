<?php
// index/layout.php – header/footer comunes

function render_header(string $title='Residentes', string $active='residentes'){ ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
 body{background:#f6f7fb}
 .card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.06);border-radius:1rem}
 .table thead th{font-weight:600}
 .table-nowrap td,
 .table-nowrap th{white-space:nowrap}
 .nav-link.active{background:#0d6efd;color:#fff!important}
</style>
</head><body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm mb-3"><div class="container">
  <a class="navbar-brand fw-bold" href="../coopnama/contactos/index.php">COOPNAMA II</a>
  <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
    <div class="nav nav-pills small">
      <a class="nav-link <?php if ($active==='residentes') echo 'active'; ?>" href="../contactos/index.php?page=residentes">Residentes</a>
      <a class="nav-link <?php if ($active==='pagos') echo 'active'; ?>" href="../contactos/index.php?page=pagos">Pagos</a>
      <a class="nav-link" href="../contactos/visor.php">Visor</a>
      <a class="nav-link" href="../automovilist/index.php">Vehículos</a>
    </div>
  </div>
</div></nav>
<main class="container my-4">
<?php }

function render_footer(){ ?>
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

  // Botón para mostrar / editar la deuda atrasada
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

  function addSingleFutureDue(){
    if (!$nextFutureDue.length) return false;
    var nextDue = $nextFutureDue.val();
    if (!nextDue) return false;
    if (maxAdvances && advancesAdded >= maxAdvances) return false;

    var idx = $('.due-option').length;
    var label = formatDueLabel(nextDue);
    var checkboxId = 'due' + idx;
    if ($dueList.length) {
      var $col = $('<div>', { 'class': 'col' });
      var $formCheck = $('<div>', { 'class': 'form-check' }).appendTo($col);
      var $checkbox = $('<input>', {
        'class': 'form-check-input due-option future-due',
        type: 'checkbox',
        name: 'selected_dues[]',
        id: checkboxId,
        value: nextDue,
        'data-label': label,
        checked: true
      }).appendTo($formCheck);
      $('<label>', {
        'class': 'form-check-label',
        'for': checkboxId,
        text: label
      }).appendTo($formCheck);
      $dueList.append($col);
    }

    $('#' + checkboxId).prop('checked', true);
    if ($noDueMessage.length) {
      $noDueMessage.addClass('d-none');
    }

    futureDueStack.push(nextDue);
    advancesAdded += 1;
    $nextFutureDue.val(addOneMonth(nextDue));

    return true;
  }

  function addFutureDue(count){
    count = parseInt(count, 10);
    if (isNaN(count) || count < 1) count = 1;
    var added = 0;
    for (var i=0; i<count; i++){
      if (!addSingleFutureDue()) break;
      added++;
    }
    if (added > 0) {
      recalcDueSelection();
    }
    updateAdvanceControlsState();
  }

  function removeSingleFutureDue(){
    var $futureBoxes = $('.due-option.future-due');
    if (!$futureBoxes.length) return false;
    var $lastBox = $futureBoxes.last();
    var removedDate = $lastBox.val();
    var $col = $lastBox.closest('.col');
    if ($col.length) {
      $col.remove();
    } else {
      $lastBox.closest('.form-check').remove();
    }
    if (futureDueStack.length) {
      var restored = futureDueStack.pop();
      if ($nextFutureDue.length) {
        $nextFutureDue.val(restored);
      }
    } else if ($nextFutureDue.length && removedDate) {
      $nextFutureDue.val(removedDate);
    }
    advancesAdded = Math.max(0, advancesAdded - 1);
    if ($dueList.find('.due-option').length === 0 && $noDueMessage.length) {
      $noDueMessage.removeClass('d-none');
    }
    recalcDueSelection();
    return true;
  }

  function removeFutureDue(count){
    count = parseInt(count, 10);
    if (isNaN(count) || count < 1) count = 1;
    var removed = 0;
    for (var i=0; i<count; i++){
      if (!removeSingleFutureDue()) break;
      removed++;
    }
    if (removed > 0) {
      updateAdvanceControlsState();
    }
  }

  if ($btnAdvancePlus.length) {
    $btnAdvancePlus.on('click', function(){
      addFutureDue(1);
    });
  }
  if ($btnAdvanceMinus.length) {
    $btnAdvanceMinus.on('click', function(){
      removeFutureDue(1);
    });
  }
  updateAdvanceControlsState();

  recalcDueSelection();

  $(document).on('click', '.btn-delete', function(e){
    if (!confirm('¿Eliminar este registro?')) e.preventDefault();
  });

});
</script>
</body></html>
<?php }
