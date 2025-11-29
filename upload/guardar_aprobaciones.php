<?php
// guardar_aprobaciones.php

date_default_timezone_set('America/Bogota');
$baseDir = realpath(__DIR__ . '/..');
$storageDir = $baseDir . '/uploads/';
$approvedDir = $storageDir . 'approved/';
foreach ([$storageDir, $approvedDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['file'])) {
    header('Location: tabla_registros.php');
    exit;
}

$file = basename($_POST['file']);
$sourcePath = $storageDir . $file;
if (!file_exists($sourcePath)) {
    header('Location: tabla_registros.php?msg=' . urlencode('No se encontró el archivo original.') . '&status=error');
    exit;
}

function detectarDelimitador($rutaCsv)
{
    $fh = fopen($rutaCsv, 'r');
    if (!$fh) return ',';
    $line = '';
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line !== '') break;
    }
    fclose($fh);
    return (strpos($line, ';') !== false) ? ';' : ',';
}

function normalizar($txt)
{
    $txt = strtolower(trim($txt));
    $txt = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $txt);
    return $txt;
}

$delim = detectarDelimitador($sourcePath);

if (($handle = fopen($sourcePath, 'r')) === false) {
    header('Location: tabla_registros.php?msg=' . urlencode('No se pudo leer el archivo original.') . '&status=error');
    exit;
}

$header = fgetcsv($handle, 0, $delim);
if ($header === false) {
    fclose($handle);
    header('Location: tabla_registros.php?msg=' . urlencode('Archivo vacío.') . '&status=error');
    exit;
}

$colAprobado = null;
foreach ($header as $i => $colName) {
    if (normalizar($colName) === 'aprobado' || strpos(normalizar($colName), 'aprobado') !== false) {
        $colAprobado = $i;
        break;
    }
}
if ($colAprobado === null) {
    $colAprobado = 43;
}

$rows = [];
while (($row = fgetcsv($handle, 0, $delim)) !== false) {
    if (count($row) <= $colAprobado) {
        $row = array_pad($row, $colAprobado + 1, '');
    }
    $valor = strtoupper(trim((string)($row[$colAprobado] ?? '')));
    $row[$colAprobado] = ($valor === 'X') ? '1' : '0';
    $rows[] = $row;
}

fclose($handle);

$targetName = pathinfo($file, PATHINFO_FILENAME) . '_aprobado.csv';
$targetPath = $approvedDir . $targetName;
if (($out = fopen($targetPath, 'w')) === false) {
    header('Location: tabla_registros.php?msg=' . urlencode('No se pudo generar el archivo aprobado.') . '&status=error');
    exit;
}

fputcsv($out, $header, $delim);
foreach ($rows as $row) {
    fputcsv($out, $row, $delim);
}

fclose($out);

$query = http_build_query([
    'file' => $file,
    'msg' => 'Archivo aprobado guardado como ' . $targetName,
    'status' => 'ok'
]);

header('Location: tabla_registros.php?' . $query);
exit;
