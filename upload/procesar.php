<?php
// ========================
// CONFIGURACIÓN INICIAL
// ========================
date_default_timezone_set('America/Bogota');

// Carpeta base del proyecto (…/filtro)
$baseDir    = realpath(__DIR__ . '/..');

// Rutas importantes
$storageDir = $baseDir . '/uploads/';
$logDir     = $baseDir . '/logs/';

// Crear carpetas necesarias
foreach ([$storageDir, $logDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// ========================
// VALIDAR ARCHIVO SUBIDO
// ========================
if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php?msg=' . urlencode('Error al subir archivo') . '&type=error');
    exit;
}

$archivo        = $_FILES['csv'];
$nombreOriginal = $archivo['name'];
$ext            = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

// Aceptar CSV o UES
$permitted = ['csv', 'ues'];
if (!in_array($ext, $permitted, true)) {
    header('Location: index.php?msg=' . urlencode('Formato no permitido. Exporta tu archivo de Excel como CSV o UES y súbelo de nuevo.') . '&type=error');
    exit;
}

// Nombre interno único que se guarda en uploads/csv
$nombreInterno = 'encuesta_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.csv';
$rutaFinal     = $storageDir . $nombreInterno;

// Mover archivo subido
if (!move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
    header('Location: index.php?msg=' . urlencode('No se pudo guardar el archivo en el servidor') . '&type=error');
    exit;
}

// ========================
// FUNCIONES AUXILIARES
// ========================

// Normalizar texto (minúsculas, sin espacios y sin tildes)
function normalizar($txt)
{
    $txt = strtolower(trim($txt));
    $txt = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
        ['a', 'e', 'i', 'o', 'u', 'n'],
        $txt
    );
    return $txt;
}

// Detectar delimitador ; o ,
// Si la línea tiene ';' usamos ';', si no, usamos ','
function detectarDelimitador($rutaCsv)
{
    $fh = fopen($rutaCsv, 'r');
    if (!$fh) return ';'; // por defecto ; para Excel en español

    $line = '';
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line !== '') break;
    }
    fclose($fh);

    if (strpos($line, ';') !== false) {
        return ';';
    }
    return ',';
}

// ========================
// AYUDANTES SIMPLIFICADOS
// ========================

function obtenerIndiceAprobado($rutaCsv, $delim)
{
    if (($handle = fopen($rutaCsv, 'r')) === false) {
        return [43, false];
    }

    $header = fgetcsv($handle, 0, $delim);
    fclose($handle);

    if ($header === false) {
        return [43, false];
    }

    foreach ($header as $i => $colName) {
        $n = normalizar($colName);
        if ($n === 'aprobado' || strpos($n, 'aprobado') !== false) {
            return [$i, true];
        }
    }

    return [43, false];
}

function contarRegistros($rutaCsv, $delim, $colAprobado)
{
    $total     = 0;
    $aprobados = 0;

    if (($handle = fopen($rutaCsv, 'r')) === false) {
        return ['total' => 0, 'aprobados' => 0];
    }

    fgetcsv($handle, 0, $delim); // saltar encabezado

    while (($row = fgetcsv($handle, 0, $delim)) !== false) {
        $soloVacios = true;
        foreach ($row as $v) {
            if (trim($v) !== '') {
                $soloVacios = false;
                break;
            }
        }
        if ($soloVacios) {
            continue;
        }

        if (!array_key_exists($colAprobado, $row)) {
            $row = array_pad($row, $colAprobado + 1, '');
        }

        $valor = strtoupper(trim((string)$row[$colAprobado]));
        if ($valor === 'X') {
            $aprobados++;
        }

        $total++;
    }

    fclose($handle);

    return ['total' => $total, 'aprobados' => $aprobados];
}

function registrarLog($logDir, $nombreOriginal, $nombreInterno, $infoAprobados, $colAprobado, $colEncontrada)
{
    $logFile = $logDir . 'upload_log.txt';

    $fecha = date('Y-m-d H:i:s');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

    $linea = sprintf(
        "[%s] original=\"%s\" interno=\"%s\" aprobados=%d total=%d col_aprobado=%d encontrado=%s ip=%s\n",
        $fecha,
        $nombreOriginal,
        $nombreInterno,
        $infoAprobados['aprobados'],
        $infoAprobados['total'],
        $colAprobado,
        $colEncontrada ? 'si' : 'no',
        $ip
    );

    file_put_contents($logFile, $linea, FILE_APPEND);
}

// ========================
// FLUJO PRINCIPAL
// ========================

$delim = detectarDelimitador($rutaFinal);
[$colAprobado, $colEncontrada] = obtenerIndiceAprobado($rutaFinal, $delim);
$infoAprobados = contarRegistros($rutaFinal, $delim, $colAprobado);

registrarLog($logDir, $nombreOriginal, $nombreInterno, $infoAprobados, $colAprobado, $colEncontrada);

$extra = '';
if (!$colEncontrada) {
    $extra = ' (OJO: no se encontró columna "Aprobado" por nombre, se usó índice ' . $colAprobado . ')';
}

$mensaje = sprintf(
    'Archivo procesado. Registros aprobados: %d de %d%s',
    $infoAprobados['aprobados'],
    $infoAprobados['total'],
    $extra
);

header(
    'Location: index.php?msg=' . urlencode($mensaje) .
    '&type=ok&file=' . urlencode($nombreInterno)
);
exit;

