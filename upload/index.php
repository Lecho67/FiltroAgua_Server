<?php
$msg      = $_GET["msg"] ?? null;
$msgType  = $_GET["type"] ?? '';
$fileLink = isset($_GET["file"]) ? basename($_GET["file"]) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Subir archivo CSV</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/estilos_informes.css">
</head>
<body data-theme="light">

<header class="topbar">
    <div class="topbar-content">
        <img src="../img/uesvalle_logo.png" class="logo" alt="Logo">
        <div class="title-group">
            <h1>Unidad Ejecutora de Saneamiento del Valle del Cauca</h1>
            <span class="subtitle">Sube un archivo para aprobar visitas</span>
        </div>
    </div>
</header>

<div class="promo-banner">
    <a class="promo-cta" href="../informes/index.php">Informes</a>
    <button class="promo-secondary" type="button" disabled>Aprobar Visitas</button>
</div>

<div class="container">
    <div class="card resizable upload-card">
        <?php if ($msg): ?>
            <div class="status-box <?= $msgType === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($msg) ?>
                <?php if ($fileLink): ?>
                    <div class="upload-link">
                        <a href="tabla_registros.php?file=<?= urlencode($fileLink) ?>">Ver tabla de registros aprobados</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h2>Subir archivo CSV</h2>

        <form action="procesar.php" method="POST" enctype="multipart/form-data">
            <div class="field">
                <label class="label" for="csv">Selecciona un archivo CSV o UES</label>
                <input class="file-input" type="file" name="csv" id="csv" accept=".csv,.ues" required>
            </div>

            <button type="submit" class="btn">Subir archivo</button>
        </form>
    </div>
</div>

</body>
</html>
