<?php
// index/config.php – configuración de app y DB

// Credenciales DB
$dbHost = 'localhost';
$dbName = 'u138076177_pw';
$dbUser = 'u138076177_chacharito';
$dbPass = '3spWifiPruev@';

// Fecha base a partir de la cual se cuentan las cuotas (día configurable)
const BASE_DUE    = '2025-10-05'; // cámbiala aquí si necesitas
const DUE_DAY     = '05';         // Día del mes en que vence la cuota (ej. '05', '25')
const CUOTA_MONTO = 1000.00;      // RD$ por mes
const MORA_PCT    = 0.00;         // Porcentaje de mora (ej. 0.02 = 2%)
