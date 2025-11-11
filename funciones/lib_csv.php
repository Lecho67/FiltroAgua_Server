<?php
// filtro/funciones/lib_csv.php
require_once __DIR__ . '/db.php';

/* =========================================================
   Lectura de CSV
   ========================================================= */

/** Lee CSV a arreglo respetando comillas y BOM. Retorna: [ [c1,c2,...], ... ] */
function csv_read_rows(string $path): array {
  if (!is_file($path)) return [];
  $rows = [];
  $fh = fopen($path, 'r');
  if (!$fh) return [];

  // Lee primera línea y elimina BOM si existe
  $first = fgets($fh);
  if ($first === false) { fclose($fh); return []; }
  $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
  $rows[] = str_getcsv($first);

  while (($data = fgetcsv($fh)) !== false) {
    $rows[] = $data;
  }
  fclose($fh);
  return $rows;
}

/* =========================================================
   Utilidades
   ========================================================= */

/** Normaliza nombre de columna a algo seguro para comparación (no se usa para crear columnas). */
function normalize_col_key(string $h): string {
  $h = strtolower(trim($h));
  $h = preg_replace('/\s+/', ' ', $h);
  return $h;
}

/** Convierte fecha dd/mm/yyyy o mm/dd/yyyy a YYYY-MM-DD. Si falla, retorna NULL o la original si $fallbackOriginal=true */
function to_sql_date(?string $s, bool $fallbackOriginal=false): ?string {
  if ($s === null) return null;
  $s = trim($s);
  if ($s === '') return null;

  // Reemplazar - por /
  $t = str_replace('-', '/', $s);

  // dd/mm/yyyy
  if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $t, $m)) {
    $a = intval($m[1]); $b = intval($m[2]); $y = intval($m[3]);
    // heurística simple: si el segundo valor > 12, asumimos dd/mm
    if ($b > 12) { $d = $a; $M = $b; }
    else {
      // intenta dd/mm primero (contexto COL)
      // si dd>12 y mm<=12, entonces era mm/dd
      if ($a > 12 && $b <= 12) { $d = $a; $M = $b; }
      else if ($a <= 12 && $b > 12) { $d = $b; $M = $a; }
      else {
        // ambiguo -> preferimos dd/mm
        $d = $a; $M = $b;
      }
    }
    return sprintf('%04d-%02d-%02d', $y, $M, $d);
  }

  // yyyy-mm-dd ya válido
  if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $s)) return $s;

  return $fallbackOriginal ? $s : null;
}

/** Obtiene lista de columnas reales de una tabla (tal cual, incluyendo nombres con puntos) */
function table_columns(PDO $pdo, string $table): array {
  $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
  $cols = [];
  foreach ($stmt as $r) $cols[] = $r['Field'];
  return $cols;
}

/** Crea un INSERT preparado a partir de una lista de columnas exactas */
function build_insert_exact(PDO $pdo, string $table, array $cols): PDOStatement {
  $colSql = '`' . implode('`,`', $cols) . '`';
  $ph     = rtrim(str_repeat('?,', count($cols)), ',');
  $sql    = "INSERT INTO `{$table}` ({$colSql}) VALUES ({$ph})";
  return $pdo->prepare($sql);
}

/** Decide tabla por valor de la columna tipo */
function pick_table_by_tipo(string $tipo): ?string {
  $t = strtolower(trim($tipo));
  $t = str_replace(['-', '_'], ' ', $t);
  $t = preg_replace('/\s+/', ' ', $t);
  if (strpos($t, 'primera') !== false) return 'base_filtros';
  if (strpos($t, 'seguim')  !== false) return 'seguimiento_filtros';
  return null;
}

/* =========================================================
   MAPEO POSICIONAL (SIN ENCABEZADO)
   =========================================================
   Índices basados en el CSV luego de ELIMINAR la columna 2 (tipo).
   Ejemplo CSV: [0]=timestamp_ms, [1]=tipo, [2]=cedula, [3]=departamento, ...
   => quitamos [1] y reindexamos: [0]=timestamp_ms, [1]=cedula, [2]=departamento, ...
   Ajusta estos mapas si tu orden real difiere.
   --------------------------------------------------------- */

$MAP_BASE = [
  // CSV pos -> columna en base_filtros
  0  => 'timestamp_ms',
  1  => 'info_responsable.cedula',

  2  => 'ubicacion.departamento',
  3  => 'ubicacion.municipio',
  4  => 'ubicacion.vereda_corregimiento',
  5  => 'ubicacion.direccion',
  6  => 'ubicacion.latitud',
  7  => 'ubicacion.altitud',

  8  => 'info_responsable.fecha',
  9  => 'info_responsable.responsable',
  10 => 'info_responsable.empresa',

  11 => 'beneficiario.tipo_beneficiario',
  12 => 'beneficiario.grupo_poblacional',
  13 => 'beneficiario.nombre_beneficiario',
  14 => 'beneficiario.cedula',
  15 => 'beneficiario.telefono',

  16 => 'demografia.menor_5',
  17 => 'demografia.entre_6_17',
  18 => 'demografia.entre_18_64',
  19 => 'demografia.mayor_65',

  20 => 'acceso_agua.tiene_agua',
  21 => 'acceso_agua.fuente_respuesta',
  22 => 'acceso_agua.usa_otra_fuente',
  23 => 'acceso_agua.administra_servicio',
  24 => 'acceso_agua.horas_dia',

  25 => 'desplazamiento.necesita_desplazarse',
  26 => 'desplazamiento.medio_utiliza',
  27 => 'desplazamiento.tiempo_min',

  28 => 'percepcion_agua.percepcion',
  29 => 'percepcion_agua.opinion_sabor',
  30 => 'percepcion_agua.aspecto',
  31 => 'percepcion_agua.presenta_olores',

  32 => 'almacenamiento_tratamiento.tanque',
  33 => 'almacenamiento_tratamiento.tratamientos',
  34 => 'almacenamiento_tratamiento.hierve_como',
  35 => 'almacenamiento_tratamiento.quien_labores',
  36 => 'almacenamiento_tratamiento.gasto_mensual',

  37 => 'contaminacion.contacto_fuentes',
  38 => 'contaminacion.fuente_protegida',
  39 => 'contaminacion.importancia_consumir_buena',
  40 => 'contaminacion.beneficios',

  41 => 'saneamiento.taza',
  42 => 'saneamiento.sistema_residuos',

  43 => 'higiene.capacitacion_higiene',
  44 => 'higiene.practica_lavado_manos',
  45 => 'higiene.practica_limpieza_hogar',
  46 => 'higiene.practica_cepillado_dientes',
  47 => 'higiene.practica_otro',
  48 => 'higiene.practica_bano_diario',

  49 => 'salud.dolor_estomago',
  50 => 'salud.enfermedades',
  51 => 'salud.observaciones',
];

$MAP_SEGUIMIENTO = [
  // CSV pos -> columna en seguimiento_filtros
  0  => 'timestamp_ms',
  1  => 'info_responsable.cedula',

  2  => 'ubicacion.departamento',
  3  => 'ubicacion.municipio',
  4  => 'ubicacion.vereda_corregimiento',
  5  => 'ubicacion.direccion',
  6  => 'ubicacion.latitud',
  7  => 'ubicacion.altitud',

  8  => 'info_responsable.fecha',
  9  => 'info_responsable.responsable',
  10 => 'info_responsable.empresa',
  11 => 'info_responsable.telefono',

  12 => 'acceso_agua_filtro.fecha',
  13 => 'acceso_agua_filtro.fuente_agua',
  14 => 'acceso_agua_filtro.porque_arcilla',
  15 => 'acceso_agua_filtro.dias_almacenada',
  16 => 'acceso_agua_filtro.veces_recarga',
  17 => 'acceso_agua_filtro.miembro_recarga',
  18 => 'acceso_agua_filtro.uso_del_agua',

  19 => 'percepciones_cambios.cambios',
  20 => 'percepciones_cambios.percepcion',
  21 => 'percepciones_cambios.sabor',
  22 => 'percepciones_cambios.color',
  23 => 'percepciones_cambios.olor',
  24 => 'percepciones_cambios.enfermedades_disminuyen',
  25 => 'percepciones_cambios.gastos_disminuyen',
  26 => 'percepciones_cambios.gasto_actual',

  27 => 'mantenimiento.frecuencia_mantenimiento',
  28 => 'mantenimiento.productos_limpieza_arcilla',
  29 => 'mantenimiento.productos_limpieza_plastico',
  30 => 'mantenimiento.conoce_vida_util_arcilla',
  31 => 'mantenimiento.sabe_donde_conseguir_repuestos',

  32 => 'observaciones_tecnicas.estable_seguro',
  33 => 'observaciones_tecnicas.ensamblado_tapado',
  34 => 'observaciones_tecnicas.limpio_parte_externa',
  35 => 'observaciones_tecnicas.lavado_manos_previo',
  36 => 'observaciones_tecnicas.manipulacion_arcilla_adecuada',
  37 => 'observaciones_tecnicas.limpieza_tanque_sin_sedimentos',
  38 => 'observaciones_tecnicas.limpieza_vasija_sin_sedimentos',
  39 => 'observaciones_tecnicas.fisuras_arcilla',
  40 => 'observaciones_tecnicas.niveles_agua_impiden_manipulacion',
  41 => 'observaciones_tecnicas.instalacion_lavado_manos',
  42 => 'observaciones_tecnicas.disp_jabon_lavado_manos',

  43 => 'ubicacion.observaciones',
];

/* =========================================================
   Inserción principal
   ========================================================= */

/**
 * Inserta un CSV en:
 *   - base_filtros            (si col.2 contiene "primera")
 *   - seguimiento_filtros     (si col.2 contiene "seguim")
 *
 * No crea columnas nuevas.
 * Ignora la columna 2 (tipo).
 * Acepta CSV con o sin encabezado.
 */
function insert_csv_by_tipo_to_filtros(string $csvPath): array {
  $pdo   = pdo();
  $rows  = csv_read_rows($csvPath);
  if (count($rows) === 0) return ['total'=>0,'ok'=>0,'fail'=>0];

  // Detectar si la primera fila es datos (sin encabezado)
  $firstRow = $rows[0];
  $noHeader = false;
  if (isset($firstRow[1])) {
    $maybeTipo = strtolower(trim($firstRow[1]));
    if (strpos($maybeTipo, 'primera') !== false || strpos($maybeTipo, 'seguim') !== false) {
      $noHeader = true;
    }
  }

  // Si trae encabezado real, lo retiramos
  $startIndex = 0;
  if (!$noHeader) {
    $startIndex = 1; // saltar encabezado
  }

  // Columnas existentes reales en BD
  $colsBase   = table_columns($pdo, 'base_filtros');
  $colsSeg    = table_columns($pdo, 'seguimiento_filtros');

  // Preparador cache por firma de columnas
  $stmtCache = []; // key = table|col1,col2,...

  $total = 0; $ok = 0; $fail = 0;

  global $MAP_BASE, $MAP_SEGUIMIENTO;

  for ($i = $startIndex; $i < count($rows); $i++) {
    $row = $rows[$i];
    // Debe existir la columna 2 (tipo)
    if (!isset($row[1])) { $fail++; continue; }

    $tipo = $row[1];
    $table = pick_table_by_tipo($tipo);
    if ($table === null) { $fail++; continue; }

    // Construir arreglo sin la columna tipo (índice 1)
    // Mantiene posiciones: 0,2,3,... reindexadas a 0..n
    $compact = [];
    foreach ($row as $idx => $val) {
      if ($idx == 1) continue; // omitir tipo
      $compact[] = $val;
    }

    // Elegir mapeo y columnas de tabla
    $map   = ($table === 'base_filtros') ? $MAP_BASE : $MAP_SEGUIMIENTO;
    $validCols = ($table === 'base_filtros') ? $colsBase : $colsSeg;

    // Construir par (colsInsert, valsInsert) respetando columnas existentes
    $colsInsert = [];
    $valsInsert = [];
    foreach ($map as $csvPos => $colName) {
      if (!array_key_exists($csvPos, $compact)) continue;
      if (!in_array($colName, $validCols, true)) continue; // no crear columnas nuevas

      $val = $compact[$csvPos];

      // Normalizaciones por tipo de campo (fechas, números)
      // Fechas conocidas
      if (in_array($colName, [
        'info_responsable.fecha',
        'acceso_agua_filtro.fecha',
      ], true)) {
        $val = to_sql_date($val);
      }

      // Lat/Lon: reemplazar coma decimal por punto si llegara a ocurrir
      if (in_array($colName, ['ubicacion.latitud','ubicacion.altitud'], true)) {
        if (is_string($val)) $val = str_replace(',', '.', $val);
      }

      $colsInsert[] = $colName;
      $valsInsert[] = ($val === '' ? null : $val);
    }

    // Si no hay nada que insertar, fallamos esa fila
    if (!$colsInsert) { $fail++; continue; }

    // Preparar statement (cache por firma de columnas)
    $key = $table . '|' . implode(',', $colsInsert);
    if (!isset($stmtCache[$key])) {
      $stmtCache[$key] = build_insert_exact($pdo, $table, $colsInsert);
    }
    try {
      $stmtCache[$key]->execute($valsInsert);
      $ok++; $total++;
    } catch (Throwable $e) {
      $fail++; $total++;
      error_log("{$csvPath} fila {$i} error: " . $e->getMessage());
    }
  }

  return ['total' => $total, 'ok' => $ok, 'fail' => $fail];
}
