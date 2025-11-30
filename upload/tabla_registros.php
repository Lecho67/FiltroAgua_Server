<?php
// tabla_registros.php

// =======================
// CONFIG / HELPERS
// =======================
date_default_timezone_set('America/Bogota');

$baseDir     = realpath(__DIR__ . '/..');
$storageDir  = $baseDir . '/uploads/';

// -----------------------------------------------
// Forzar conversión de texto ANSI → UTF-8
// -----------------------------------------------
function aUtf8($str)
{
    if ($str === null || $str === '') return '';

    // Si ya es UTF-8, retornar sin cambios
    if (mb_detect_encoding($str, "UTF-8", true)) {
        return $str;
    }

    // Convertir desde Windows-1252 (Excel en Windows)
    return mb_convert_encoding($str, "UTF-8", "Windows-1252");
}

// Normalizar para buscar “aprobado”
function normalizar($txt)
{
    $txt = strtolower(trim($txt));
    $txt = str_replace(
        ['á','é','í','ó','ú','ñ'],
        ['a','e','i','o','u','n'],
        $txt
    );
    return $txt;
}

// Detectar delimitador
function detectarDelimitador($rutaCsv)
{
    $fh = fopen($rutaCsv, 'r');
    if (!$fh) return ';';

    $line = '';
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line !== '') break;
    }
    fclose($fh);

    return (strpos($line, ';') !== false) ? ';' : ',';
}

// =======================
// VALIDAR PARAMETRO FILE
// =======================
if (empty($_GET['file'])) {
    die('Falta parámetro "file".');
}

$nombreInterno = basename($_GET['file']); // sanitizar
$rutaCsv       = $storageDir . $nombreInterno;

if (!file_exists($rutaCsv)) {
    die('No se encontró el archivo solicitado.');
}

// =======================
// LEER CSV COMPLETO
// =======================
$delim   = detectarDelimitador($rutaCsv);
$header  = [];
$rows    = [];

if (($handle = fopen($rutaCsv, 'r')) !== false) {

    $header = fgetcsv($handle, 0, $delim);
    if ($header === false) {
        fclose($handle);
        die('No se pudo leer el encabezado del CSV.');
    }

    while (($data = fgetcsv($handle, 0, $delim)) !== false) {

        $soloVacios = true;
        foreach ($data as $v) {
            if (trim($v) !== '') {
                $soloVacios = false;
                break;
            }
        }
        if (!$soloVacios) {
            $rows[] = $data;
        }
    }

    fclose($handle);
}

$colCount = count($header);

// Buscar columna "Aprobado (Marcar con una X)"
$colAprobado = null;
foreach ($header as $i => $colName) {
    $n = normalizar($colName);
    if ($n === 'aprobado (marcar con una x)' || strpos($n, 'aprobado') !== false) {
        $colAprobado = $i;
        break;
    }
}
if ($colAprobado === null) {
    $colAprobado = 43; // fallback
}

// Contar
$aprobadas   = 0;
$noAprobadas = 0;

foreach ($rows as $row) {

    $rowPadded = array_pad($row, $colCount, '');

    $valor = '';
    if (isset($rowPadded[$colAprobado])) {
        $valor = strtoupper(trim((string)$rowPadded[$colAprobado]));
    }

    if ($valor === 'X') {
        $aprobadas++;
    } else {
        $noAprobadas++;
    }
}

$total = count($rows);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de carga</title>
    
    <link rel="stylesheet" href="../css/estilos_informes.css">
    <style>
        /* Estilos específicos para colorear filas según aprobación */
        table.dataTable tbody tr.aprobado { background-color:#e8f5e9; }
        table.dataTable tbody tr.no-aprobado { background-color:#ffebee; }
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
</head>

<body>

<header class="topbar">
  <div class="topbar-content">
    <img src="../img/uesvalle_logo.png" class="logo" alt="Logo">
    <div class="title-group">
      <h1>Unidad Ejecutora de Saneamiento del Valle del Cauca</h1>
      <span class="subtitle">Sistema de carga y aprobación de registros</span>
    </div>
  </div>
</header>

<div class="wrapper">
    <div class="card resizable">
        <?php if (isset($_GET['msg'])): ?>
            <div class="status-box <?= ($_GET['status'] ?? '') === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="card-header">
            <div>
                <h1>Informe de carga</h1>
                <div class="subtitle">
                    Archivo: <strong><?php echo htmlspecialchars($nombreInterno); ?></strong>
                </div>
            </div>

            <a href="index.php" class="btn">← Volver</a>
        </div>

        <div class="summary">
            <div class="chip chip-total"><span class="chip-dot"></span> Total: <?php echo $total; ?></div>
            <div class="chip chip-aprobadas"><span class="chip-dot"></span> Aprobadas: <?php echo $aprobadas; ?></div>
            <div class="chip chip-noaprobadas"><span class="chip-dot"></span> No aprobadas: <?php echo $noAprobadas; ?></div>
        </div>

        <div class="summary">
            <div class="chip" style="background:#dcfce7;"><span class="chip-dot" style="background:#16a34a;"></span>Verde = aprobada</div>
            <div class="chip" style="background:#fee2e2;"><span class="chip-dot" style="background:#dc2626;"></span>Rojo = será eliminada</div>
        </div>

        <div class="table-wrapper">
            <table id="tabla-registros" class="display nowrap" style="width:100%;">
                <thead>
                <tr>
                    <?php foreach ($header as $col): ?>
                        <?php $titulo = aUtf8($col); ?>
                        <th><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $rowPadded = array_pad($row, $colCount, '');

                    $valorAprobado = strtoupper(trim((string)$rowPadded[$colAprobado] ?? ''));
                    $esAprobado = ($valorAprobado === 'X');
                    $claseFila = $esAprobado ? 'aprobado' : 'no-aprobado';
                    $rowPadded[$colAprobado] = $esAprobado ? 'X' : '-';
                    ?>
                    <tr class="<?php echo $claseFila; ?>">
                        <?php foreach ($rowPadded as $cell): ?>
                            <?php $texto = aUtf8($cell); ?>
                            <td><?php echo htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $startIndex = $total > 0 ? 1 : 0;
        $endIndex = $total > 0 ? min(10, $total) : 0;
        ?>
        <form action="guardar_aprobaciones.php" method="POST">
            <input type="hidden" name="file" value="<?= htmlspecialchars($nombreInterno) ?>">
            <div class="upload-footnote">
            <p><?= $total ?> registros</p>
            <p class="muted"><?= $aprobadas ?> serán aprobadas y <?= $noAprobadas ?> serán borradas</p>
                <button class="btn-approve" type="submit">Actualizar y Aprobar</button>
        </div>
        </form>

    </div>
</div>

<script>
$(document).ready(function () {
    $('#tabla-registros').DataTable({
        pageLength: 10,
        scrollX: true,
        order: [],
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        }
    });
});
</script>

</body>
</html>
