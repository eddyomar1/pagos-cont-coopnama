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
const CUOTA_MONTO_NUEVO_DESDE = '2026-05-01'; // Desde esta fecha las nuevas cuotas usan el nuevo monto por defecto
const CUOTA_MONTO_NUEVO = 1700.00;            // RD$ por mes a partir de la fecha anterior
const MORA_PCT    = 0.10;         // 10% de mora sobre cuotas con 2 o más meses de retraso
