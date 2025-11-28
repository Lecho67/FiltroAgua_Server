<?php
// ========================
// CONFIGURACIÓN INICIAL
// ========================
date_default_timezone_set('America/Bogota');

// Carpeta base del proyecto (…/filtro)
$baseDir    = realpath(__DIR__ . '/..');

// Rutas importantes
$uploadDir  = $baseDir . '/uploads/csv/';          // CSV originales
$backupDir  = $baseDir . '/uploads/backup/';       // Backups
$primeraDir = $baseDir . '/uploads/primera/';      // Clasificados primera
$segDir     = $baseDir . '/uploads/seguimiento/';  // Clasificados seguimiento
$logDir     = $baseDir . '/logs/';                 // Logs

// Crear carpetas si no existen
foreach ([$uploadDir, $backupDir, $primeraDir, $segDir, $logDir] as $dir) {
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

// *** SOLO ACEPTAMOS CSV ***
if ($ext !== 'csv') {
    header('Location: index.php?msg=' . urlencode('Formato no permitido. Exporta tu archivo de Excel como CSV y súbelo de nuevo.') . '&type=error');
    exit;
}

// Nombre interno único que se guarda en uploads/csv
$nombreInterno = 'encuesta_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.csv';
$rutaFinal     = $uploadDir . $nombreInterno;

// Mover archivo subido
if (!move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
    header('Location: index.php?msg=' . urlencode('No se pudo guardar el archivo en el servidor') . '&type=error');
    exit;
}

// ========================
// FUNCIONES AUXILIARES
// ========================

// Backup del archivo original
function hacerBackup($rutaArchivo, $backupDir, $nombreArchivo)
{
    @copy($rutaArchivo, $backupDir . $nombreArchivo);
}

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
// FILTRO POR COLUMNA "Aprobado" + DEBUG
// ========================
// Primera fila = encabezado.
// Busca la columna cuyo encabezado normalizado contenga "aprobado".
// Si no la encuentra, usa la columna 43 (AR) como fallback.
// Una fila está aprobada si ESA columna tiene 'X' (may/min, espacios ignorados).
function filtrarPorAprobado($rutaCsv, $logDir)
{
    $baseName = pathinfo($rutaCsv, PATHINFO_FILENAME);

    if (!file_exists($rutaCsv)) {
        return [
            'aprobados'     => 0,
            'ids'           => [],
            'base_name'     => $baseName,
            'col_usada'     => null,
            'col_encontrada'=> false
        ];
    }

    $filas     = [];
    $aprobados = 0;
    $ids       = [];

    $indiceId = 0;  // primera columna = ID
    $delim    = detectarDelimitador($rutaCsv);

    $debugFile = $logDir . 'debug_aprobado.txt';
    $debugLog  = "==== " . date('Y-m-d H:i:s') . " ====\n";
    $debugLog .= "Delimitador detectado: " . $delim . "\n";

    $colAprobado = null;
    $colEncontradaPorNombre = false;

    if (($handle = fopen($rutaCsv, 'r')) !== false) {

        // Encabezado (primera fila)
        $header = fgetcsv($handle, 0, $delim);
        if ($header === false) {
            fclose($handle);
            file_put_contents($debugFile, $debugLog . "No se pudo leer encabezado.\n\n", FILE_APPEND);
            return [
                'aprobados'     => 0,
                'ids'           => [],
                'base_name'     => $baseName,
                'col_usada'     => null,
                'col_encontrada'=> false
            ];
        }

        // Log de encabezados
        $debugLog .= "Encabezados:\n";
        foreach ($header as $i => $colName) {
            $debugLog .= "[" . $i . "] " . $colName . "\n";
        }

        // Buscar columna Aprobado por nombre
        foreach ($header as $i => $colName) {
            $n = normalizar($colName);
            if ($n === 'aprobado' || strpos($n, 'aprobado') !== false) {
                $colAprobado = $i;
                $colEncontradaPorNombre = true;
                break;
            }
        }

        // Fallback: si no la encontramos por nombre, usamos la 43 (AR)
        if ($colAprobado === null) {
            $colAprobado = 43;
            $debugLog .= "Columna 'Aprobado' NO encontrada por nombre. Usando fallback índice 43 (AR).\n";
        } else {
            $debugLog .= "Columna 'Aprobado' encontrada por nombre en índice: $colAprobado\n";
        }

        $filas[] = $header;

        // Filas de datos
        while (($row = fgetcsv($handle, 0, $delim)) !== false) {

            // Si la fila no llega hasta colAprobado, la saltamos
            if (count($row) <= $colAprobado) {
                continue;
            }

            // Valor crudo de la columna Aprobado
            $raw = $row[$colAprobado] ?? '';

            // Normalización
            $valorAprobado = strtoupper(trim((string)$raw));

            // SOLO aprobado si es exactamente "X"
            $esAprobado = ($valorAprobado === 'X');

            if ($esAprobado) {
                $filas[] = $row;
                $aprobados++;

                if (isset($row[$indiceId])) {
                    $ids[] = $row[$indiceId];
                }
            }
        }

        fclose($handle);
    }

    $debugLog .= "Columna Aprobado usada (índice): " . $colAprobado . "\n";
    $debugLog .= "Filas aprobadas: " . $aprobados . "\n\n";
    file_put_contents($debugFile, $debugLog, FILE_APPEND);

    // Reescribir CSV solo con encabezado + filas aprobadas (si hay)
    if (!empty($filas)) {
        if (($out = fopen($rutaCsv, 'w')) !== false) {
            // ahora siempre lo guardamos con coma
            foreach ($filas as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }
    }

    return [
        'aprobados'      => $aprobados,
        'ids'            => $ids,
        'base_name'      => $baseName,
        'col_usada'      => $colAprobado,
        'col_encontrada' => $colEncontradaPorNombre
    ];
}

// Guardar lista de IDs aprobados en un archivo separado
function guardarListaAprobados($logDir, $baseName, $ids)
{
    if (empty($ids)) return;

    $file = $logDir . 'aprobados_' . $baseName . '.txt';
    $contenido = "IDs aprobados (columna 1):\n" . implode(PHP_EOL, $ids) . PHP_EOL;
    file_put_contents($file, $contenido);
}

// ========================
// CLASIFICAR SEGÚN NOMBRE DEL ARCHIVO
// ========================
// Si el nombre original tiene "Base"  -> Primera visita
// Si el nombre original tiene "Seguimiento" -> Seguimiento
// Cuenta filas del CSV filtrado (solo aprobados) y copia el archivo al folder correspondiente.
function clasificarCsv($rutaCsv, $primeraDir, $segDir, $logDir, $nombreOriginal)
{
    $baseName = pathinfo($rutaCsv, PATHINFO_FILENAME);
    $nombreLower = strtolower($nombreOriginal);

    // Detectar tipo según nombre
    $esPrimera     = (strpos($nombreLower, 'base') !== false);
    $esSeguimiento = (strpos($nombreLower, 'seguimiento') !== false);

    // Contar filas (después del filtro)
    $totalFilas = 0;
    $delim = detectarDelimitador($rutaCsv);

    if (($handle = fopen($rutaCsv, 'r')) !== false) {
        // Encabezado
        $header = fgetcsv($handle, 0, $delim);
        if ($header !== false) {
            while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                // contamos solo filas no totalmente vacías
                $soloVacios = true;
                foreach ($row as $v) {
                    if (trim($v) !== '') {
                        $soloVacios = false;
                        break;
                    }
                }
                if (!$soloVacios) {
                    $totalFilas++;
                }
            }
        }
        fclose($handle);
    }

    $countPrimera = 0;
    $countSeg     = 0;
    $archivoPrimera = null;
    $archivoSeguim  = null;

    // Copiar archivo según tipo
    if ($esPrimera) {
        $archivoPrimera = $baseName . '_primera.csv';
        @copy($rutaCsv, $primeraDir . $archivoPrimera);
        $countPrimera = $totalFilas;
    } elseif ($esSeguimiento) {
        $archivoSeguim = $baseName . '_seguimiento.csv';
        @copy($rutaCsv, $segDir . $archivoSeguim);
        $countSeg = $totalFilas;
    }

    // Log de clasificación
    $logFile = $logDir . 'debug_clasificar.txt';
    $log  = "==== " . date('Y-m-d H:i:s') . " ====\n";
    $log .= "Nombre original: {$nombreOriginal}\n";
    $log .= "Tipo detectado: " . ($esPrimera ? 'PRIMERA' : ($esSeguimiento ? 'SEGUIMIENTO' : 'DESCONOCIDO')) . "\n";
    $log .= "Filas totales filtradas (aprobadas): {$totalFilas}\n";
    $log .= "Primera visita: {$countPrimera}\n";
    $log .= "Seguimiento: {$countSeg}\n\n";
    file_put_contents($logFile, $log, FILE_APPEND);

    return [
        'total'               => $totalFilas,
        'primera'             => $countPrimera,
        'seguimiento'         => $countSeg,
        'archivo_primera'     => $archivoPrimera,
        'archivo_seguimiento' => $archivoSeguim
    ];
}

// Registrar log con info antes/después y lista de IDs aprobados
function registrarLog($logDir, $nombreOriginal, $nombreInterno, $nombreFiltrado, $clasif, $infoAprobados)
{
    $logFile = $logDir . 'upload_log.txt';

    $fecha = date('Y-m-d H:i:s');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

    $idsStr = implode(',', $infoAprobados['ids']);

    $linea = sprintf(
        "[%s] original=\"%s\" interno=\"%s\" filtrado=\"%s\" primera=\"%s\" seguimiento=\"%s\" aprobados=%d total_filtrado=%d ids=[%s]\n",
        $fecha,
        $nombreOriginal,
        $nombreInterno,
        $nombreFiltrado,
        $clasif['archivo_primera']     ?? '-',
        $clasif['archivo_seguimiento'] ?? '-',
        $infoAprobados['aprobados'],
        $clasif['total'],
        $idsStr
    );

    file_put_contents($logFile, $linea, FILE_APPEND);
}

// ========================
// FLUJO PRINCIPAL
// ========================

// 1) Backup del archivo original (antes de cualquier procesamiento)
hacerBackup($rutaFinal, $backupDir, $nombreInterno);

// 2) Filtrar usando SOLO la columna "Aprobado"
$infoAprobados = filtrarPorAprobado($rutaFinal, $logDir);
$registrosAprobados = $infoAprobados['aprobados'];

// 3) Guardar lista de IDs aprobados en un archivo aparte
guardarListaAprobados($logDir, $infoAprobados['base_name'], $infoAprobados['ids']);

// 4) Clasificar en primera / seguimiento SEGÚN NOMBRE DEL ARCHIVO
$clasificacion = clasificarCsv($rutaFinal, $primeraDir, $segDir, $logDir, $nombreOriginal);

// 5) Registrar en log (antes y después de archivos + lista de aprobados)
registrarLog(
    $logDir,
    $nombreOriginal,
    $nombreInterno,
    basename($rutaFinal), // archivo ya filtrado
    $clasificacion,
    $infoAprobados
);

// 6) Preparar mensaje (si no encontró la columna, lo indicamos)
$extra = '';
if ($registrosAprobados === 0) {
    if (!$infoAprobados['col_encontrada']) {
        $extra = ' (OJO: no se encontró columna "Aprobado" por nombre, se usó índice '
            . ($infoAprobados['col_usada'] ?? 'null') . ')';
    }
}

$mensaje = sprintf(
    'Archivo procesado. Registros aprobados: %d | Primera visita: %d | Seguimiento: %d%s',
    $registrosAprobados,
    $clasificacion['primera'],
    $clasificacion['seguimiento'],
    $extra
);

// pasamos el nombre interno para poder ver el detalle
header(
    'Location: index.php?msg=' . urlencode($mensaje) .
    '&type=ok&file=' . urlencode($nombreInterno)
);
exit;

