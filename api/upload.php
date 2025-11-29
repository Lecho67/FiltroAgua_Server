<?php
// api/upload.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../funciones/lib_csv.php'; // usa insert_csv_by_tipo_to_filtros()

// --- rutas base (no usamos outputs) ---
$uploadsDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads';
if ($uploadsDir === false) { $uploadsDir = __DIR__ . '/../uploads'; }
if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }

// --- validación de archivo ---
if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'No llegó el archivo (campo "file")']);
  exit;
}

// --- nombre destino con timestamp + aleatorio ---
$orig = basename($_FILES['file']['name'] ?? 'archivo.csv');
$ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if ($ext === '') $ext = 'csv';
$destName = 'encuestas_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
$destPath = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $destName;

// --- mover a uploads ---
if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo guardar el archivo en uploads/']);
  exit;
}

// --- procesar: insertar directo a BD según columna 2 ---
try {
  $proc = insert_csv_by_tipo_to_filtros($destPath); // en lib_csv.php
  echo json_encode([
    'ok'    => true,
    'file'  => $destName,
    'size'  => (int)($_FILES['file']['size'] ?? 0),
    'proc'  => [
      'total'      => $proc['total'] ?? 0,
      'insertados' => $proc['ok'] ?? 0,
      'fallidos'   => $proc['fail'] ?? 0,
      'duplicados' => $proc['duplicates'] ?? 0,
      'tabla_primera'    => 'base_filtros',
      'tabla_seguimiento'=> 'seguimiento_filtros'
    ]
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'   => false,
    'file' => $destName,
    'msg'  => 'Subido pero no procesado',
    'error'=> $e->getMessage()
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
