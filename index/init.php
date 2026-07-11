<?php
// index/init.php – punto único de arranque común

if (defined('APP_INIT')) {
  return;
}
define('APP_INIT', true);

session_start();

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';

// Asegura que la columna de deuda inicial exista antes de usarla
ensure_deuda_inicial_column($pdo);
ensure_exonerado_columns($pdo);
ensure_residente_cuota_mensual_column($pdo);
ensure_pagos_anulacion_columns($pdo);
ensure_pagos_cantidad_meses_column($pdo);
ensure_pagos_lineas_table($pdo);
ensure_pagos_detalle_cuotas_column($pdo);
