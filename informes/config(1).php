<?php
// informes/config.php
// Ajusta las credenciales a tu entorno XAMPP/phpMyAdmin:
$DB_HOST = 'localhost';
$DB_NAME = 'filtros';   // Por tu USE filtros;
$DB_USER = 'root';      // XAMPP por defecto
$DB_PASS = '';          // XAMPP por defecto (vacío)
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
  http_response_code(500);
  echo "Error de conexión: " . htmlspecialchars($e->getMessage());
  exit;
}
