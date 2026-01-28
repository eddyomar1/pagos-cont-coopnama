<?php
// Archivo principal del visor de residentes

require __DIR__ . '/visor/config.php';
require __DIR__ . '/visor/layout.php';
require __DIR__ . '/visor/actions.php';

// Redirección cómoda
if ($action === 'index') {
  header('Location:?action=full');
  exit;
}

// Enrutador de vistas
switch ($action) {
  case 'full':
    require __DIR__ . '/visor/list.php';
    break;

  case 'new':
  case 'edit':
    require __DIR__ . '/visor/form.php';
    break;

  case 'keys':
    require __DIR__ . '/visor/llaves.php';
    break;

  default:
    header('Location:?action=full');
    exit;
}
