<?php
require __DIR__ . '/config.php';

/* ====== Filtros ====== */
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$from = (isset($_GET['from']) && $_GET['from'] !== '') ? $_GET['from'] : null;
$to   = (isset($_GET['to'])   && $_GET['to']   !== '') ? $_GET['to']   : null;
$municipio = (isset($_GET['municipio']) && $_GET['municipio'] !== '') ? $_GET['municipio'] : null;

/* ====== Utils ====== */
function fdate($d) {
    if (!$d) return '';
    $d = trim((string)$d);
    if ($d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
}

/* ====== SQL ====== */
$sqlPrimera = "
  SELECT
    b.*,
    FROM_UNIXTIME(b.`timestamp_ms`/1000) AS ts_fecha
  FROM base_filtros b
  WHERE 1=1
";
if ($from) $sqlPrimera .= " AND FROM_UNIXTIME(b.`timestamp_ms`/1000) >= :from_b";
if ($to)   $sqlPrimera .= " AND FROM_UNIXTIME(b.`timestamp_ms`/1000) <  DATE_ADD(:to_b, INTERVAL 1 DAY)";
if ($municipio) $sqlPrimera .= " AND b.`ubicacion.municipio` = :municipio_b";

$sqlSeguimiento = "
  SELECT
    s.*,
    FROM_UNIXTIME(s.`timestamp_ms`/1000) AS ts_fecha
  FROM seguimiento_filtros s
  WHERE 1=1
";
if ($from) $sqlSeguimiento .= " AND FROM_UNIXTIME(s.`timestamp_ms`/1000) >= :from_s";
if ($to)   $sqlSeguimiento .= " AND FROM_UNIXTIME(s.`timestamp_ms`/1000) <  DATE_ADD(:to_s, INTERVAL 1 DAY)";
if ($municipio) $sqlSeguimiento .= " AND s.`ubicacion.municipio` = :municipio_s";

/* ====== Ejecutar ====== */
function runQuery($pdo, $sql, $params = []) {
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) $stmt->bindValue($k, $v);
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$params_b = []; $params_s = [];
if ($from) { $params_b[':from_b']=$from; $params_s[':from_s']=$from; }
if ($to)   { $params_b[':to_b']=$to;     $params_s[':to_s']=$to; }
if ($municipio) { $params_b[':municipio_b']=$municipio; $params_s[':municipio_s']=$municipio; }

$data = [];
if ($tipo === 'primera') {
  $data = runQuery($pdo, $sqlPrimera, $params_b);
} elseif ($tipo === 'seguimiento') {
  $data = runQuery($pdo, $sqlSeguimiento, $params_s);
}

/* ====== Etiquetas personalizadas ====== */
function codificar($dato)
{
	$salida=convBase($dato, "0123456789", "0123456789abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$%^&*()+-><=?[]_{}|");
	return $salida;
}

function decodificar($dato)
{
	$salida=convBase($dato, "0123456789abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$%^&*()+-><=?[]_{}|" , "0123456789");
	return $salida;
}


function convBase($numberInput, $fromBaseInput, $toBaseInput)
{
  if ($fromBaseInput==$toBaseInput) return $numberInput;
  $fromBase = str_split($fromBaseInput,1);
  $toBase = str_split($toBaseInput,1);
  $number = str_split($numberInput,1);
  $fromLen=strlen($fromBaseInput);
  $toLen=strlen($toBaseInput);
  $numberLen=strlen($numberInput);
  $retval='';
  if ($toBaseInput == '0123456789')
  {
    $retval=0;
    for ($i = 1;$i <= $numberLen; $i++)
      $retval = bcadd($retval, bcmul(array_search($number[$i-1], $fromBase),bcpow($fromLen,$numberLen-$i)));
    return $retval;
  }
  if ($fromBaseInput != '0123456789')
    $base10=convBase($numberInput, $fromBaseInput, '0123456789');
  else
    $base10 = $numberInput;
  if ($base10<strlen($toBaseInput))
    return $toBase[$base10];
  while($base10 != '0')
  {
    $retval = $toBase[bcmod($base10,$toLen)].$retval;
    $base10 = bcdiv($base10,$toLen,0);
  }
  return $retval;
}

$customLabelsPrimera = [
  'info_responsable.fecha' => 'Fecha',
  'info_responsable.responsable' => 'Responsable',
  'info_responsable.empresa' => 'Empresa / Equipo',
  'info_responsable.cedula' => 'No. Cédula',
  'info_responsable.telefono' => 'Teléfono',
  'beneficiario.tipo_beneficiario' => 'Tipo de beneficiario',
  'beneficiario.grupo_poblacional' => 'Grupo Poblacional',
  'beneficiario.nombre_beneficiario' => 'Nombre del beneficiado',
  'beneficiario.cedula' => 'Cédula',
  'beneficiario.telefono' => 'Teléfono (wsp)',
  'ubicacion.departamento' => 'Departamento',
  'ubicacion.municipio' => 'Municipio',
  'ubicacion.vereda_corregimiento' => 'Vereda / Corregimiento',
  'ubicacion.direccion' => 'Dirección',
  'demografia.menor_5' => 'Menor de 5 años',
  'demografia.entre_6_17' => 'Entre 6 y 17 años',
  'demografia.entre_18_64' => 'Entre 18 y 64 años',
  'demografia.mayor_65' => 'Mayor de 65 años',
  'acceso_agua.tiene_agua' => 'Tiene agua disponible para sus actividades diarias.',
  'acceso_agua.fuente_respuesta' => '¿De dónde toma el agua?',
  'acceso_agua.usa_otra_fuente' => '¿Utiliza otra fuente de agua?',
  'acceso_agua.administra_servicio' => '¿Quién administra el servicio?',
  'acceso_agua.horas_dia' => '¿Cuántas horas al día cuenta con agua?',
  'desplazamiento.necesita_desplazarse' => '¿Tiene que desplazarse para acceder al agua?',
  'desplazamiento.medio_utiliza' => '¿Qué medio utiliza?',
  'desplazamiento.tiempo_min' => '¿Cuánto tiempo se demora? (min)',
  'percepcion_agua.percepcion' => 'Percepción del agua',
  'percepcion_agua.opinion_sabor' => '¿Qué opina del sabor del agua?',
  'percepcion_agua.aspecto' => '¿El agua es clara, oscura o por temporada?',
  'percepcion_agua.presenta_olores' => '¿Presenta olores?',
  'almacenamiento_tratamiento.tanque' => '¿Almacena agua en tanque?',
  'almacenamiento_tratamiento.tratamientos' => '¿Realiza tratamientos al agua? (Opción múltiple)',
  'almacenamiento_tratamiento.hierve_como' => 'En caso de hervir el agua, ¿cómo lo hace?',
  'almacenamiento_tratamiento.quien_labores' => '¿Quién realiza estas labores?',
  'almacenamiento_tratamiento.gasto_mensual' => '¿Cuánto dinero invierte al mes?',
  'contaminacion.contacto_fuentes' => '¿El agua tiene contacto con fuentes de contaminación? (Opción múltiple)',
  'contaminacion.fuente_protegida' => '¿La fuente está protegida?',
  'contaminacion.importancia_consumir_buena' => '¿Es importante consumir agua de calidad?',
  'contaminacion.beneficios' => 'Beneficios de consumir agua potable (Opción múltiple)',
  'saneamiento.taza' => '¿Cuenta con sanitario (taza)?',
  'saneamiento.sistema_residuos' => '¿Tiene fosa séptica/pozo séptico/alcantarillado?',
  'higiene.capacitacion_higiene' => '¿Ha recibido capacitación en higiene?',
  'higiene.practica_lavado_manos' => 'Prácticas: Lavado de manos',
  'higiene.practica_limpieza_hogar' => 'Prácticas: Limpieza del hogar',
  'higiene.practica_cepillado_dientes' => 'Prácticas: Cepillado dental',
  'higiene.practica_otro' => 'Prácticas: Otras',
  'higiene.practica_bano_diario' => 'Prácticas: Baño diario',
  'salud.dolor_estomago' => '¿Presentan dolores de estómago?',
  'salud.enfermedades' => '¿Se han presentado enfermedades hídricas? (Opción múltiple)',
  'salud.observaciones' => 'Observaciones',
  'timestamp_ms' => 'Timestamp (ms)',
  'ubicacion.latitud' => 'Latitud',
  'ubicacion.altitud' => 'Altitud',
  'codigo_no_modificar' => 'Codigo(No Modificar)',
  'aprobado' => 'Aprobado',
  'creado_en' => 'Creado En'
];

$customLabelsSeg = [
  'info_responsable.fecha' => 'Fecha',
  'info_responsable.responsable' => 'Encuestador',
  'ubicacion.departamento' => 'Departamento',
  'ubicacion.municipio' => 'Municipio',
  'ubicacion.vereda_corregimiento' => 'Vereda o Corregimiento',
  'ubicacion.direccion' => 'Dirección',
  'info_responsable.cedula' => 'Documento',
  'info_responsable.telefono' => 'Teléfono',
  'beneficiario.tipo_beneficiario' => 'Tipo de beneficiario',
  'beneficiario.grupo_poblacional' => 'Grupo Poblacional',
  'beneficiario.nombre_beneficiario' => 'Nombre del beneficiado',
  'beneficiario.cedula' => 'Cédula',
  'beneficiario.telefono' => 'Teléfono (wsp)',
  'acceso_agua_filtro.fecha' => 'Fecha en que obtuvo el filtro',
  'acceso_agua_filtro.fuente_agua' => 'Fuente donde toma el agua',
  'acceso_agua_filtro.porque_arcilla' => '¿Por qué usas el filtro de arcilla?',
  'acceso_agua_filtro.dias_almacenada' => '¿Cuánto tiempo dura el agua almacenada? (días)',
  'acceso_agua_filtro.veces_recarga' => '¿Cuántas veces al día recarga el filtro?',
  'acceso_agua_filtro.miembro_recarga' => '¿Qué miembro de la familia recarga el filtro?',
  'acceso_agua_filtro.uso_del_agua' => '¿En qué emplea el agua del filtro?',
  'percepciones_cambios.cambios' => '¿Qué cambios ha notado con el filtro?',
  'percepciones_cambios.percepcion' => 'Percepción del agua después de recibir el filtro',
  'percepciones_cambios.sabor' => '¿Qué opina del sabor del agua?',
  'percepciones_cambios.color' => '¿El agua presenta color?',
  'percepciones_cambios.olor' => '¿El agua presenta olor?',
  'percepciones_cambios.enfermedades_disminuyen' => '¿Han disminuido las enfermedades por agua?',
  'percepciones_cambios.gastos_disminuyen' => '¿Han disminuido los gastos por agua apta?',
  'percepciones_cambios.gasto_actual' => '¿Cuánto gasta actualmente?',
  'mantenimiento.frecuencia_mantenimiento' => '¿Cada cuánto realiza mantenimiento?',
  'mantenimiento.productos_limpieza_arcilla' => 'Productos limpieza (arcilla)',
  'mantenimiento.productos_limpieza_plastico' => 'Productos limpieza (plástico)',
  'mantenimiento.conoce_vida_util_arcilla' => '¿Conoce vida útil de la arcilla?',
  'mantenimiento.sabe_donde_conseguir_repuestos' => '¿Sabe dónde conseguir repuestos?',
  'observaciones_tecnicas.estable_seguro' => '¿El filtro está en un lugar estable y/o seguro?',
  'observaciones_tecnicas.ensamblado_tapado' => '¿El filtro está ensamblado y tapado?',
  'observaciones_tecnicas.limpio_parte_externa' => '¿El filtro está limpio externamente?',
  'observaciones_tecnicas.lavado_manos_previo' => '¿Lavado de manos previo a la manipulación?',
  'observaciones_tecnicas.manipulacion_arcilla_adecuada' => 'Manipulación adecuada de la arcilla',
  'observaciones_tecnicas.limpieza_tanque_sin_sedimentos' => 'Limpieza del tanque (sin sedimentos)',
  'observaciones_tecnicas.limpieza_vasija_sin_sedimentos' => 'Limpieza de la vasija (sin sedimentos)',
  'observaciones_tecnicas.fisuras_arcilla' => '¿Se evidencian fisuras en la arcilla?',
  'observaciones_tecnicas.niveles_agua_impiden_manipulacion' => '¿La vasija presenta niveles de agua que imposibiliten su manipulación?',
  'observaciones_tecnicas.instalacion_lavado_manos' => '¿Cuenta con instalación de lavado de manos?',
  'observaciones_tecnicas.disp_jabon_lavado_manos' => '¿Dispone de jabón para lavado de manos',
  'ubicacion.observaciones' => 'Observaciones',
  'timestamp_ms' => 'Timestamp (ms)',
  'ubicacion.latitud' => 'Latitud',
  'ubicacion.altitud' => 'Altitud',
  'codigo_no_modificar' => 'Codigo(No Modificar)',
  'aprobado' => 'Aprobado',
  'creado_en' => 'Creado En'
];

/* ====== Generar nombre del archivo ======*/
$tipoNombre = ($tipo === 'primera') ? 'Base' : 'Seguimiento';
$fromFormatted = $from ? $from : 'inicio';
$toFormatted = $to ? $to : 'fin';
$filename = "{$tipoNombre} {$fromFormatted} hasta {$toFormatted}.csv";

/* ====== Headers para descarga ======*/
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Pragma: no-cache");
header("Expires: 0");

/* ====== Generar contenido CSV ======*/
$customLabels = ($tipo === 'primera') ? $customLabelsPrimera : $customLabelsSeg;
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8 compatible con Excel

$headers = array_values($customLabels);
$headers[] = 'GPS';
fputcsv($output, $headers);

foreach ($data as $row) {
  $line = [];
  $timestamp = $row['timestamp_ms'] ?? '';
  foreach ($customLabels as $col => $_label) {
    if ($col === 'codigo_no_modificar') {
      $line[] = ($timestamp !== '') ? codificar($timestamp) : '';
      continue;
    }
    $line[] = $row[$col] ?? '';
  }
  $lat = $row['ubicacion.latitud'] ?? '';
  $lon = $row['ubicacion.altitud'] ?? '';
  if ($lat && $lon) {
    $line[] = "https://www.google.com/maps/?q={$lat},{$lon}";
  } else {
    $line[] = '';
  }
  fputcsv($output, $line);
}

fclose($output);
exit;
