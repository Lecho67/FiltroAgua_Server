<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar archivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: #1e293b;
            padding: 25px 30px;
            border-radius: 10px;
            width: 420px;
            box-shadow: 0 0 20px rgba(0,0,0,0.4);
            border: 1px solid #334155;
        }
        h2 {
            margin-top: 0;
            text-align: center;
            color: #38bdf8;
        }
        label {
            font-size: 0.9em;
            margin-bottom: 5px;
            display: block;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #475569;
            background: #0f172a;
            color: white;
        }
        button {
            width: 100%;
            margin-top: 15px;
            padding: 10px;
            background: #22c55e;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: #052e16;
            font-weight: bold;
        }
        button:hover {
            background: #16a34a;
        }
        .msg {
            margin-top: 15px;
            text-align: center;
            font-size: .9em;
        }
        .ok { color: #4ade80; }
        .error { color: #f87171; }
        .help {
            font-size: 0.8em;
            color:#9ca3af;
            margin-top:4px;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Subir archivo CSV / Excel</h2>

    <?php if (isset($_GET["msg"])): ?>
        <div class="msg <?= $_GET["type"] ?? '' ?>">
            <?= htmlspecialchars($_GET["msg"]) ?>
        </div>

        <?php if (!empty($_GET["file"])): ?>
            <a
                class="btn-detalle"
                href="tabla_registros.php?file=<?= htmlspecialchars($_GET["file"], ENT_QUOTES); ?>">
                Ver registros detallados
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <form action="procesar.php" method="POST" enctype="multipart/form-data" style="margin-top:15px;">
        <label for="csv">Selecciona un archivo (CSV, XLSX o XLS)</label>
        <!-- 👇 ahora acepta csv, xlsx y xls -->
        <input type="file" name="csv" id="csv" accept=".csv,.xlsx,.xls" required>
        <div class="help">Puedes subir el reporte directamente desde Excel (.xlsx, .xls) o en formato .csv.</div>

        <button type="submit">Subir archivo</button>
    </form>
</div>

</body>
</html>
