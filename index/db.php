<?php
// index/db.php – conexión PDO en $pdo

if (!isset($pdo)) {
  $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    ensure_test_environment_tables($pdo);
  } catch (Throwable $e) {
    http_response_code(500);
    exit("DB error: " . htmlspecialchars($e->getMessage()));
  }
}
