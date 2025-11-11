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
</style>
</head><body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">RESIDENCIAL COOPNAMA II</a>
    <div class="ms-auto btn-group">
      <a href="index.php"
         class="btn btn-sm <?= $active==='residentes' ? 'btn-primary' : 'btn-outline-primary' ?>">
         Residentes
      </a>
      <a href="index.php?page=pagos"
         class="btn btn-sm <?= $active==='pagos' ? 'btn-primary' : 'btn-outline-primary' ?>">
         Pagos
      </a>
    </div>
  </div>
</nav>
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
  $(document).on('click', '#btnToggleDeuda', function(){
    $('#cardDeudaExtra').removeClass('d-none');
    $('#deuda_restante').prop('disabled', false);
    if (!$('#deuda_restante').val()) {
      $('#deuda_restante').val('0.00');
    }
    $(this).prop('disabled', true);
  });

  // === CUOTAS + DEUDA EXTRA ===
  function recalcDueSelection(){
    var $boxes = $('.due-option:checked');
    var count  = $boxes.length;
    var totalCuotas = count * 1000;

    var deudaStr = $('#deuda_restante').val() || $('#deuda_extra_actual').val() || '0';
    deudaStr = deudaStr.replace(',', '.');
    var deudaActual = parseFloat(deudaStr);
    if (isNaN(deudaActual)) deudaActual = 0;

    var abonoStr = $('#abono_deuda_extra').val() || '0';
    abonoStr = abonoStr.replace(',', '.');
    var abono = parseFloat(abonoStr);
    if (isNaN(abono)) abono = 0;

    var totalBase = totalCuotas + abono;

    if ($('input[name="monto_a_pagar"]').length){
      $('input[name="monto_a_pagar"]').val(totalBase.toFixed(2));
    }

    $('#countSelected').text(count);
    $('#totalSelected').text(
      totalBase.toLocaleString('es-DO', {
        minimumFractionDigits:2,
        maximumFractionDigits:2
      })
    );

    var despues = Math.max(0, deudaActual - abono);
    if ($('#deuda_despues').length){
      $('#deuda_despues').val(despues.toFixed(2));
    }
  }

  $(document).on('change', '.due-option', recalcDueSelection);
  $(document).on('input', '#abono_deuda_extra', recalcDueSelection);
  $(document).on('input', '#deuda_restante', recalcDueSelection);
  recalcDueSelection();

  $(document).on('click', '.btn-delete', function(e){
    if (!confirm('¿Eliminar este registro?')) e.preventDefault();
  });


// ====== MODO DEUDA: al pulsar "Añadir / editar deuda atrasada" ======
function enterDebtMode(){
  // Flag oculto para el backend
  if (!$('#modo_deuda').length){
    $('<input>', {type:'hidden', id:'modo_deuda', name:'modo_deuda', value:'1'}).appendTo('form');
  } else {
    $('#modo_deuda').val('1');
  }

  // Mostrar card de deuda (si estuviera oculta) y habilitar edición del monto
  $('#cardDeudaExtra').removeClass('d-none');
  $('#deuda_restante').prop('disabled', false);

  // Desactivar checkboxes de cuotas, desmarcarlos y ocultar la card
  $('.due-option').prop('checked', false).prop('disabled', true);
  $('#cardCuotas').addClass('d-none');

  // No requerir fecha_pagada y deshabilitar su input para no confundir
  $('input[name="fecha_pagada"]').prop('required', false).prop('disabled', true).val('');

  // Bloquear "Monto a abonar ahora" y "Mora" y ponerlos en 0.00
  $('#abono_deuda_extra').val('0.00').prop('disabled', true);
  $('input[name="mora"]').val('0.00').prop('disabled', true);

  // Como no hay cuotas ni abono, el "monto_a_pagar" queda en 0.00
  $('input[name="monto_a_pagar"]').val('0.00');

  // (Opcional) deshabilitar el propio botón para evitar dobles clics
  $('#btnToggleDeuda').prop('disabled', true);
}

// Botón para entrar al modo deuda
$(document).on('click', '#btnToggleDeuda', function(){
  enterDebtMode();
});


});
</script>
</body></html>
<?php }
