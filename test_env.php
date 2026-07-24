<?php

if (defined('APP_TEST_ENV_BOOTSTRAPPED')) {
  return;
}
define('APP_TEST_ENV_BOOTSTRAPPED', true);

const APP_ENV_LABEL = 'TEST';
const APP_BASE_PATH = '/eo/coopnama/contactos_test';

const TABLE_RESIDENTES = 'test_residentes';
const TABLE_PAGOS_RESIDENTES = 'test_pagos_residentes';
const TABLE_SOURCE_RESIDENTES = 'residentes';
const TABLE_SOURCE_PAGOS_RESIDENTES = 'pagos_residentes';

function app_url(string $path = ''): string{
  $base = rtrim(APP_BASE_PATH, '/');
  $path = ltrim($path, '/');
  return $path === '' ? $base : $base.'/'.$path;
}

function app_env_brand(): string{
  return 'COOPNAMA II '.APP_ENV_LABEL;
}

function sql_ident(string $name): string{
  return '`'.str_replace('`', '``', $name).'`';
}

function t_residentes(): string{
  return sql_ident(TABLE_RESIDENTES);
}

function t_pagos_residentes(): string{
  return sql_ident(TABLE_PAGOS_RESIDENTES);
}

function ensure_parallel_table_copy(PDO $pdo, string $source, string $target): void{
  if ($source === $target) {
    return;
  }

  $targetExists = false;
  try{
    $st = $pdo->query('SHOW TABLES LIKE '.$pdo->quote($target));
    $targetExists = (bool)($st && $st->fetchColumn());
  }catch(Throwable $e){
    $targetExists = false;
  }

  $sourceSql = sql_ident($source);
  $targetSql = sql_ident($target);

  if (!$targetExists) {
    $pdo->exec("CREATE TABLE {$targetSql} LIKE {$sourceSql}");
  }

  $targetCount = (int)$pdo->query("SELECT COUNT(*) FROM {$targetSql}")->fetchColumn();
  if ($targetCount === 0) {
    $pdo->exec("INSERT INTO {$targetSql} SELECT * FROM {$sourceSql}");
  }
}

function ensure_test_environment_tables(PDO $pdo): void{
  static $done = false;
  if ($done) {
    return;
  }
  $done = true;

  ensure_parallel_table_copy($pdo, TABLE_SOURCE_RESIDENTES, TABLE_RESIDENTES);
  ensure_parallel_table_copy($pdo, TABLE_SOURCE_PAGOS_RESIDENTES, TABLE_PAGOS_RESIDENTES);
}
