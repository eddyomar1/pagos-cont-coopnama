<?php
// index.php – router principal -

require __DIR__ . '/index/init.php';

$page   = $_GET['page']   ?? 'residentes';
$action = $_GET['action'] ?? 'index';

switch ($page) {
  case 'pagos':
    require __DIR__ . '/index/pagos.php';
    break;

  case 'residentes':
  default:
    require __DIR__ . '/index/residentes.php';
    break;
}
