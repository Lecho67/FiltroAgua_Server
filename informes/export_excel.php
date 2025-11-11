<?php
/**
 * export_excel.php
 * Genera un .xlsx con una o dos hojas (Primera / Seguimiento) según el filtro recibido por GET.
 * Requiere PhpSpreadsheet (composer require phpoffice/phpspreadsheet)
 */
require __DIR__ . '/config.php';

/* ===========================
 *  Autoload de Composer
 * =========================== */
$autoloads = [
  __DIR__ . '/vendor/autoload.php',   // si instalaste en esta carpeta
  __DIR__ . '/../vendor/autoload.php' // si instalaste un nivel arriba
];
$loaded = false;
foreach ($autoloads as $path) {
  if (file_exists($path)) { require $path; $loaded = true; break; }
}
if (!$loaded) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "No se encontró vendor/autoload.php.\n".
       "Instala dependencias con:\n".
       "  cd C:\\xampp\\htdocs\\filtro\\informes\n".
       "  composer require phpoffice/phpspreadsheet\n";
  exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/* ===========================
 *  Filtros
 * =========================== */
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'ambos'; // primera | seguimiento | ambos
$from = (isset($_GET['from']) && $_GET['from'] !== '') ? $_GET['from'] : null; // YYYY-MM-DD
$to   = (isset($_GET['to'])   && $_GET['to']   !== '') ? $_GET['to']   : null;

function fdate_excel($d) {
  if (!$d) return '';
  $ts = strtotime($d);
  return $ts ? date('d/m/Y', $ts) : $d;
}

/* ===========================
 *  SQL (idéntico a index.php)
 * =========================== */
$sqlPrimera = "
SELECT
  'Primera' AS tipo,
  FROM_UNIXTIME(b.`timestamp_ms`/1000)    AS fecha_digitacion,
  b.`info_responsable.fecha`              AS fecha_actividad,
  b.`ubicacion.departamento`              AS departamento,
  b.`ubicacion.municipio`                 AS municipio,
  b.`ubicacion.vereda_corregimiento`      AS vereda_corregimiento,
  b.`ubicacion.direccion`                 AS direccion,
  b.`ubicacion.latitud`                   AS latitud,
  b.`ubicacion.altitud`                   AS altitud,
  b.`info_responsable.cedula`             AS cedula_responsable,
  b.`info_responsable.responsable`        AS responsable,
  b.`info_responsable.empresa`            AS empresa,

  b.`beneficiario.tipo_beneficiario`      AS tipo_beneficiario,
  b.`beneficiario.nombre_beneficiario`    AS nombre_beneficiario,
  b.`beneficiario.cedula`                 AS cedula_beneficiario,
  b.`beneficiario.telefono`               AS telefono_beneficiario,

  b.`acceso_agua.tiene_agua`              AS tiene_agua,
  b.`acceso_agua.fuente_respuesta`        AS fuente_agua,

  b.`demografia.menor_5`                  AS dem_menor_5,
  b.`demografia.entre_6_17`               AS dem_6_17,
  b.`demografia.entre_18_64`              AS dem_18_64,
  b.`demografia.mayor_65`                 AS dem_mayor_65,

  b.`salud.enfermedades`                  AS enfermedades,
  b.`salud.observaciones`                 AS salud_observaciones,

  b.`aprobado`                            AS aprobado,
  b.`creado_en`                           AS creado_en
FROM base_filtros b
WHERE 1=1
".($from?" AND b.`info_responsable.fecha` >= :from_b ":"")
 .($to  ?" AND b.`info_responsable.fecha` <= :to_b   ":"");

$sqlSeguimiento = "
SELECT
  'Seguimiento' AS tipo,
  FROM_UNIXTIME(s.`timestamp_ms`/1000)    AS fecha_digitacion,
  s.`info_responsable.fecha`              AS fecha_actividad,
  s.`ubicacion.departamento`              AS departamento,
  s.`ubicacion.municipio`                 AS municipio,
  s.`ubicacion.vereda_corregimiento`      AS vereda_corregimiento,
  s.`ubicacion.direccion`                 AS direccion,
  s.`ubicacion.latitud`                   AS latitud,
  s.`ubicacion.altitud`                   AS altitud,
  s.`info_responsable.cedula`             AS cedula_responsable,
  s.`info_responsable.responsable`        AS responsable,
  s.`info_responsable.empresa`            AS empresa,
  s.`info_responsable.telefono`           AS telefono_responsable,

  s.`acceso_agua_filtro.fecha`            AS acceso_agua_filtro_fecha,
  s.`acceso_agua_filtro.fuente_agua`      AS acceso_agua_filtro_fuente_agua,
  s.`acceso_agua_filtro.porque_arcilla`   AS acceso_agua_filtro_porque_arcilla,
  s.`acceso_agua_filtro.veces_recarga`    AS acceso_agua_filtro_veces_recarga,

  s.`percepciones_cambios.percepcion`     AS percepcion_cambios,
  s.`mantenimiento.frecuencia_mantenimiento` AS frecuencia_mantenimiento,

  s.`aprobado`                            AS aprobado,
  s.`creado_en`                           AS creado_en
FROM seguimiento_filtros s
WHERE 1=1
".($from?" AND s.`info_responsable.fecha` >= :from_s ":"")
 .($to  ?" AND s.`info_responsable.fecha` <= :to_s   ":"");

/* ===========================
 *  Ejecutores
 * =========================== */
function fetchData($pdo, $sql, $from, $to, $suffix) {
  $stmt = $pdo->prepare($sql);
  if ($from) $stmt->bindValue(":from_$suffix", $from);
  if ($to)   $stmt->bindValue(":to_$suffix",   $to);
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$dataPrimera = ($tipo==='primera' || $tipo==='ambos') ? fetchData($pdo, $sqlPrimera, $from, $to, 'b') : [];
$dataSeguimiento = ($tipo==='seguimiento' || $tipo==='ambos') ? fetchData($pdo, $sqlSeguimiento, $from, $to, 's') : [];

/* ===========================
 *  Encabezados y mapping
 * =========================== */
$headPrimera = [
  'Tipo','Fecha Digitación','Fecha Actividad','Departamento','Municipio','Vereda/Corregimiento','Dirección',
  'Latitud','Altitud','Cédula Responsable','Responsable','Empresa',
  'Tipo Beneficiario','Nombre Beneficiario','Cédula Beneficiario','Teléfono Beneficiario',
  'Tiene agua','Fuente agua','Menor 5','6–17','18–64','≥65','Enfermedades','Observaciones','Aprobado','Creado en'
];
$mapPrimera = [
  'tipo','fecha_digitacion','fecha_actividad','departamento','municipio','vereda_corregimiento','direccion',
  'latitud','altitud','cedula_responsable','responsable','empresa',
  'tipo_beneficiario','nombre_beneficiario','cedula_beneficiario','telefono_beneficiario',
  'tiene_agua','fuente_agua','dem_menor_5','dem_6_17','dem_18_64','dem_mayor_65','enfermedades','salud_observaciones',
  'aprobado','creado_en'
];

$headSeguimiento = [
  'Tipo','Fecha Digitación','Fecha Actividad','Departamento','Municipio','Vereda/Corregimiento','Dirección',
  'Latitud','Altitud','Cédula Responsable','Responsable','Empresa','Teléfono Responsable',
  'Fecha (Filtro)','Fuente Agua','¿Por qué arcilla?','Veces recarga','Percepción','Frecuencia Mant.','Aprobado','Creado en'
];
$mapSeguimiento = [
  'tipo','fecha_digitacion','fecha_actividad','departamento','municipio','vereda_corregimiento','direccion',
  'latitud','altitud','cedula_responsable','responsable','empresa','telefono_responsable',
  'acceso_agua_filtro_fecha','acceso_agua_filtro_fuente_agua','acceso_agua_filtro_porque_arcilla','acceso_agua_filtro_veces_recarga',
  'percepcion_cambios','frecuencia_mantenimiento','aprobado','creado_en'
];

/* ===========================
 *  Construcción del Excel
 * =========================== */
$book = new Spreadsheet();

$bookTitle = "informe_" . $tipo . ($from || $to ? ("_" . ($from ?: '---') . "_a_" . ($to ?: '---')) : '') . ".xlsx";

$makeSheet = function($sheet, $headers, $map, $rows) {
  // Header
  $c = 1;
  foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($c, 1, $h);
    $c++;
  }
  // Rows
  $r = 2;
  foreach ($rows as $row) {
    $c = 1;
    foreach ($map as $key) {
      $val = $row[$key] ?? '';
      if (in_array($key, ['fecha_digitacion','fecha_actividad','creado_en','acceso_agua_filtro_fecha'])) {
        $val = fdate_excel($val);
      }
      $sheet->setCellValueByColumnAndRow($c, $r, $val);
      $c++;
    }
    $r++;
  }
  // Autosize & header bold
  $lastCol = $sheet->getHighestColumn();
  $lastColIdx = Coordinate::columnIndexFromString($lastCol);
  for ($i=1; $i <= $lastColIdx; $i++) {
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
  }
  $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
};

// Hojas según tipo
if ($tipo === 'primera') {
  $sheet = $book->getActiveSheet(); $sheet->setTitle('Primera Visita');
  $makeSheet($sheet, $headPrimera, $mapPrimera, $dataPrimera);
} elseif ($tipo === 'seguimiento') {
  $sheet = $book->getActiveSheet(); $sheet->setTitle('Seguimiento');
  $makeSheet($sheet, $headSeguimiento, $mapSeguimiento, $dataSeguimiento);
} else { // ambos
  $sheet1 = $book->getActiveSheet(); $sheet1->setTitle('Primera Visita');
  $makeSheet($sheet1, $headPrimera, $mapPrimera, $dataPrimera);

  $book->createSheet();
  $sheet2 = $book->setActiveSheetIndex(1); $sheet2->setTitle('Seguimiento');
  $makeSheet($sheet2, $headSeguimiento, $mapSeguimiento, $dataSeguimiento);

  $book->setActiveSheetIndex(0);
}

/* ===========================
 *  Output
 * =========================== */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$bookTitle.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($book);
$writer->save('php://output');
exit;
