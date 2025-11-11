<?php
require __DIR__ . '/config.php';

/* ====== Filtros ====== */
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$from = (isset($_GET['from']) && $_GET['from'] !== '') ? $_GET['from'] : null;   // YYYY-MM-DD
$to   = (isset($_GET['to'])   && $_GET['to']   !== '') ? $_GET['to']   : null;   // YYYY-MM-DD
$municipio = (isset($_GET['municipio']) && $_GET['municipio'] !== '') ? $_GET['municipio'] : null;
$busquedaRealizada = isset($_GET['buscar']);  // Solo buscar si se presionó el botón

/* ====== Utils ====== */
function fdate($d) {
    if (!$d) return '';
    $d = trim((string)$d);
    if ($d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
}

/* ====== SQL (filtra por el PRIMER TIMESTAMP: timestamp_ms) ====== */
/* Primera visita */
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

/* Seguimiento */
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

// Solo ejecutar queries si se realizó una búsqueda
$dataPrimera     = [];
$dataSeguimiento = [];
if ($busquedaRealizada) {
  $dataPrimera     = ($tipo==='primera'     || $tipo==='ambos') ? runQuery($pdo,$sqlPrimera,$params_b) : [];
  $dataSeguimiento = ($tipo==='seguimiento' || $tipo==='ambos') ? runQuery($pdo,$sqlSeguimiento,$params_s) : [];
}

/* ====== Etiquetas personalizadas ====== */
$customLabelsPrimera = [

  'info_responsable.fecha' => 'Fecha',
  'info_responsable.responsable' => 'Responsable',
  'info_responsable.empresa' => 'Empresa / Equipo',
  'info_responsable.cedula' => 'No. Cédula',

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
  'observaciones_tecnicas.disp_jabon_lavado_manos' => '¿Dispone de jabón para lavado de manos?',
  'ubicacion.observaciones' => 'Observaciones',

  'timestamp_ms' => 'Timestamp (ms)',
  'ubicacion.latitud' => 'Latitud',
  'ubicacion.altitud' => 'Altitud',

  'aprobado' => 'Aprobado',
  'creado_en' => 'Creado En'
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Informe completo – <?= ucfirst($tipo) ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.7.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.html5.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.0/css/buttons.dataTables.min.css">

<link rel="stylesheet" href="../css/estilos_informes.css">
<style>
/* Garantiza que el wrapper no recorte el resize */
.dataTables_wrapper{ overflow-x:auto; }

/* Mejora aviso de rango */
.range-note{font-size:12px;color:var(--muted);margin:4px 0 12px}
</style>
</head>
<body data-theme="light">

<header class="topbar">
  <div class="topbar-content">
    <img src="../img/uesvalle_logo.png" class="logo" alt="Logo">
    <div class="title-group">
      <h1>Unidad Ejecutora de Saneamiento del Valle del Cauca</h1>
      <span class="subtitle">Informe completo de registros</span>
    </div>
  </div>
</header>

<div class="container">
  <div class="header">
    <div class="title">Informe completo – <?= ucfirst($tipo) ?></div>
    <label class="switch"><input id="themeToggle" type="checkbox"> Modo oscuro</label>
  </div>

  <div class="card resizable">
    <form class="toolbar" method="get">
      <div class="field">
        <span class="label">Sección</span>
        <select name="tipo" required>
          <option value="">Seleccione...</option>
          <option value="primera" <?= $tipo==='primera'?'selected':'' ?>>Primera Visita</option>
          <option value="seguimiento" <?= $tipo==='seguimiento'?'selected':'' ?>>Seguimiento</option>
          <option value="ambos" <?= $tipo==='ambos'?'selected':'' ?>>Ambos</option>
        </select>
      </div>
      <div class="field">
        <span class="label">Municipio</span>
        <select name="municipio" required>
          <option value="">Seleccione...</option>
          <option value="Alcalá" <?= $municipio==='Alcalá'?'selected':'' ?>>Alcalá</option>
          <option value="Andalucía" <?= $municipio==='Andalucía'?'selected':'' ?>>Andalucía</option>
          <option value="Ansermanuevo" <?= $municipio==='Ansermanuevo'?'selected':'' ?>>Ansermanuevo</option>
          <option value="Argelia" <?= $municipio==='Argelia'?'selected':'' ?>>Argelia</option>
          <option value="Bolívar" <?= $municipio==='Bolívar'?'selected':'' ?>>Bolívar</option>
          <option value="Buenaventura" <?= $municipio==='Buenaventura'?'selected':'' ?>>Buenaventura</option>
          <option value="Buga" <?= $municipio==='Buga'?'selected':'' ?>>Buga</option>
          <option value="Bugalagrande" <?= $municipio==='Bugalagrande'?'selected':'' ?>>Bugalagrande</option>
          <option value="Caicedonia" <?= $municipio==='Caicedonia'?'selected':'' ?>>Caicedonia</option>
          <option value="Cali" <?= $municipio==='Cali'?'selected':'' ?>>Cali</option>
          <option value="Calima (El Darién)" <?= $municipio==='Calima (El Darién)'?'selected':'' ?>>Calima (El Darién)</option>
          <option value="Candelaria" <?= $municipio==='Candelaria'?'selected':'' ?>>Candelaria</option>
          <option value="Cartago" <?= $municipio==='Cartago'?'selected':'' ?>>Cartago</option>
          <option value="Dagua" <?= $municipio==='Dagua'?'selected':'' ?>>Dagua</option>
          <option value="El Águila" <?= $municipio==='El Águila'?'selected':'' ?>>El Águila</option>
          <option value="El Cairo" <?= $municipio==='El Cairo'?'selected':'' ?>>El Cairo</option>
          <option value="El Cerrito" <?= $municipio==='El Cerrito'?'selected':'' ?>>El Cerrito</option>
          <option value="El Dovio" <?= $municipio==='El Dovio'?'selected':'' ?>>El Dovio</option>
          <option value="Florida" <?= $municipio==='Florida'?'selected':'' ?>>Florida</option>
          <option value="Ginebra" <?= $municipio==='Ginebra'?'selected':'' ?>>Ginebra</option>
          <option value="Guacarí" <?= $municipio==='Guacarí'?'selected':'' ?>>Guacarí</option>
          <option value="Jamundí" <?= $municipio==='Jamundí'?'selected':'' ?>>Jamundí</option>
          <option value="La Cumbre" <?= $municipio==='La Cumbre'?'selected':'' ?>>La Cumbre</option>
          <option value="La Unión" <?= $municipio==='La Unión'?'selected':'' ?>>La Unión</option>
          <option value="La Victoria" <?= $municipio==='La Victoria'?'selected':'' ?>>La Victoria</option>
          <option value="Obando" <?= $municipio==='Obando'?'selected':'' ?>>Obando</option>
          <option value="Palmira" <?= $municipio==='Palmira'?'selected':'' ?>>Palmira</option>
          <option value="Pradera" <?= $municipio==='Pradera'?'selected':'' ?>>Pradera</option>
          <option value="Restrepo" <?= $municipio==='Restrepo'?'selected':'' ?>>Restrepo</option>
          <option value="Riofrío" <?= $municipio==='Riofrío'?'selected':'' ?>>Riofrío</option>
          <option value="Roldanillo" <?= $municipio==='Roldanillo'?'selected':'' ?>>Roldanillo</option>
          <option value="San Pedro" <?= $municipio==='San Pedro'?'selected':'' ?>>San Pedro</option>
          <option value="Sevilla" <?= $municipio==='Sevilla'?'selected':'' ?>>Sevilla</option>
          <option value="Toro" <?= $municipio==='Toro'?'selected':'' ?>>Toro</option>
          <option value="Trujillo" <?= $municipio==='Trujillo'?'selected':'' ?>>Trujillo</option>
          <option value="Tuluá" <?= $municipio==='Tuluá'?'selected':'' ?>>Tuluá</option>
          <option value="Ulloa" <?= $municipio==='Ulloa'?'selected':'' ?>>Ulloa</option>
          <option value="Versalles" <?= $municipio==='Versalles'?'selected':'' ?>>Versalles</option>
          <option value="Vijes" <?= $municipio==='Vijes'?'selected':'' ?>>Vijes</option>
          <option value="Yotoco" <?= $municipio==='Yotoco'?'selected':'' ?>>Yotoco</option>
          <option value="Yumbo" <?= $municipio==='Yumbo'?'selected':'' ?>>Yumbo</option>
          <option value="Zarzal" <?= $municipio==='Zarzal'?'selected':'' ?>>Zarzal</option>
        </select>
      </div>
      <div class="field">
        <span class="label">Desde (por timestamp)</span>
        <input type="date" name="from" value="<?= htmlspecialchars($from??'') ?>" required>
      </div>
      <div class="field">
        <span class="label">Hasta (por timestamp)</span>
        <input type="date" name="to" value="<?= htmlspecialchars($to??'') ?>" required>
      </div>
      <div style="display:flex;gap:8px;align-items:flex-end;margin-left:auto">
        <button type="submit" name="buscar" value="1" class="btn">Buscar</button>
        <a class="btn btn-secondary" href="index.php">Limpiar</a>
      </div>
    </form>


    <?php if($busquedaRealizada && ($tipo==='primera' || $tipo==='ambos')): ?>
      <div class="section-title">Primera Visita</div>
      <div class="table-wrap">
        <table class="display nowrap" style="width:100%">
          <thead>
            <tr>
              <?php foreach($customLabelsPrimera as $col => $label) echo '<th>'.$label.'</th>'; ?>
              <th>GPS</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($dataPrimera as $r): ?>
            <tr>
              <?php foreach($customLabelsPrimera as $col => $label): ?>
                <td><?= htmlspecialchars($r[$col] ?? '') ?></td>
              <?php endforeach; ?>
              <td>
                <?php 
                  $lat = $r['ubicacion.latitud'] ?? '';
                  $lon = $r['ubicacion.altitud'] ?? '';
                  if($lat && $lon): 
                ?>
                  <a href="https://www.google.com/maps/?q=<?= htmlspecialchars($lat) ?>,<?= htmlspecialchars($lon) ?>" target="_blank">https://www.google.com/maps/?q=<?= htmlspecialchars($lat) ?>,<?= htmlspecialchars($lon) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px;font-size:14px;color:var(--muted);display:flex;justify-content:space-between;align-items:center;">
        <span>Total de registros encontrados: <strong><?= count($dataPrimera) ?></strong></span>
        <a href="descargar_excel.php?tipo=primera&municipio=<?= urlencode($municipio) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn" style="margin:0;">
          Descargar Excel
        </a>
      </div>
    <?php endif; ?>

    <?php if($busquedaRealizada && ($tipo==='seguimiento' || $tipo==='ambos')): ?>
      <div class="section-title">Seguimiento</div>
      <div class="table-wrap">
        <table class="display nowrap" style="width:100%">
          <thead>
            <tr>
              <?php foreach($customLabelsSeg as $col => $label) echo '<th>'.$label.'</th>'; ?>
              <th>GPS</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($dataSeguimiento as $r): ?>
            <tr>
              <?php foreach($customLabelsSeg as $col => $label): ?>
                <td><?= htmlspecialchars($r[$col] ?? '') ?></td>
              <?php endforeach; ?>
              <td>
                <?php 
                  $lat = $r['ubicacion.latitud'] ?? '';
                  $lon = $r['ubicacion.altitud'] ?? '';
                  if($lat && $lon): 
                ?>
                  <a href="https://www.google.com/maps/?q=<?= htmlspecialchars($lat) ?>,<?= htmlspecialchars($lon) ?>" target="_blank">https://www.google.com/maps/?q=<?= htmlspecialchars($lat) ?>,<?= htmlspecialchars($lon) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px;font-size:14px;color:var(--muted);display:flex;justify-content:space-between;align-items:center;">
        <span>Total de registros encontrados: <strong><?= count($dataSeguimiento) ?></strong></span>
        <a href="descargar_excel.php?tipo=seguimiento&municipio=<?= urlencode($municipio) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn" style="margin:0;">
          Descargar Excel
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

<footer class="footer">
  © <?= date('Y') ?> UESVALLE – Unidad Ejecutora de Saneamiento del Valle del Cauca.
</footer>

<script>
/* DataTables global */
$.extend(true,$.fn.dataTable.defaults,{
  language:{
    decimal:",", thousands:".",
    info:"Mostrando _START_ a _END_ de _TOTAL_ registros",
    lengthMenu:"Mostrar _MENU_",
    search:"Buscar:",
    paginate:{first:"Primero",last:"Último",next:"Siguiente",previous:"Anterior"},
    emptyTable:"Sin datos disponibles"
  },
  dom:'Bfrtip',
  buttons:['excel','csv','print','colvis'],
  autoWidth:true,
  responsive:false,
  scrollX:true,
  scrollCollapse:true,
  pageLength:25,
  orderCellsTop:true
});

$('table.display').each(function(){
  $(this).DataTable().columns.adjust();
});

/* Modo oscuro NO persistente */
const cb = document.getElementById('themeToggle');
if (cb) {
  cb.addEventListener('change', function(){
    document.body.dataset.theme = cb.checked ? 'dark' : 'light';
    $('table.display').each(function(){ $(this).DataTable().columns.adjust(); });
  });
}

/* Ajustar DataTables al redimensionar el recuadro */
(function(){
  const panel = document.querySelector('.card.resizable');
  if (!panel) return;
  const ro = new ResizeObserver(() => {
    document.querySelectorAll('table.display').forEach(t => {
      if ($.fn.dataTable.isDataTable(t)) $(t).DataTable().columns.adjust();
    });
  });
  ro.observe(panel);
})();
</script>
</body>
</html>
