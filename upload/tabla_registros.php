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

// Buscar columna “Aprobado”
$colAprobado = null;
foreach ($header as $i => $colName) {
    $n = normalizar($colName);
    if ($n === 'aprobado' || strpos($n, 'aprobado') !== false) {
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

    <!-- Estilos generales -->
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background:#f3f4f6;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color:#111827;
        }
        .wrapper {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px 32px;
        }
        .card {
            background:#ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.12);
            padding: 20px 24px 24px;
        }
        .card-header {
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom: 10px;
        }
        h1 {
            margin:0;
            font-size: 1.6rem;
            color:#059669;
        }
        .subtitle {
            font-size:0.85rem;
            color:#6b7280;
        }
        .btn-back {
            background:#3b82f6;
            color:#ffffff;
            border:none;
            border-radius:8px;
            padding:8px 14px;
            font-size:0.85rem;
            cursor:pointer;
            text-decoration:none;
        }
        .btn-back:hover { background:#2563eb; }

        .logo-strip {
            background:#fff;
            border-radius:14px;
            padding:18px 12px;
            margin-bottom:18px;
            display:flex;
            justify-content:center;
            box-shadow:0 10px 30px rgba(15,23,42,0.08);
        }
        .logo-strip img {
            width:100%;
            max-width:820px;
            height:auto;
            display:block;
            border-radius:10px;
        }
        .summary {
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            margin:10px 0 16px;
            font-size:0.9rem;
        }
        .chip {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 10px;
            border-radius:999px;
            background:#f3f4f6;
        }
        .chip-dot {
            width:10px; height:10px; border-radius:999px;
        }
        .chip-total .chip-dot { background:#4b5563; }
        .chip-aprobadas .chip-dot { background:#16a34a; }
        .chip-noaprobadas .chip-dot { background:#dc2626; }

        table.dataTable tbody tr.aprobado { background-color:#e8f5e9; }
        table.dataTable tbody tr.no-aprobado { background-color:#ffebee; }

        .table-wrapper { overflow-x:auto; }
        .status-box {
            margin: 0 0 16px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #0f172a;
        }
        .status-box.ok { background: #dcfce7; border: 1px solid #22c55e; }
        .status-box.error { background: #fee2e2; border: 1px solid #ef4444; }
        .upload-footnote {
            margin-top: 20px;
            padding: 16px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.12);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .upload-footnote p {
            margin: 0;
            font-size: 0.9rem;
            color: #111827;
        }
        .upload-footnote .muted {
            color: #6b7280;
        }
        .upload-footnote .btn-approve {
            align-self: flex-start;
            margin-top: 8px;
            background: #22c55e;
            color: #052e16;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }

    </style>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
</head>

<body>
<div class="wrapper">
    <div class="card">
        <?php if (isset($_GET['msg'])): ?>
            <div class="status-box <?= ($_GET['status'] ?? '') === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="logo-strip" aria-label="Gobernación del Valle, Paraíso de Todos y UESVALLE">
            <img src="../img/uesvalle_logo.png" alt="Gobernación del Valle, Paraíso de Todos y UESVALLE" loading="lazy">
        </div>

        <div class="card-header">
            <div>
                <h1>Informe de carga</h1>
                <div class="subtitle">
                    Archivo: <strong><?php echo htmlspecialchars($nombreInterno); ?></strong>
                </div>
            </div>

            <a href="index.php" class="btn-back">← Volver</a>
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
            <table id="tabla-registros" class="display" style="width:100%;">
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
                    $rowPadded[$colAprobado] = $esAprobado ? '1' : '0';
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
            <p>Mostrando registros del <?= $startIndex ?> al <?= $endIndex ?> de un total de <?= $total ?> registros</p>
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
