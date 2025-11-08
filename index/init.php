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
