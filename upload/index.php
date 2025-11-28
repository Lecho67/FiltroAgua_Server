<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar archivo CSV</title>
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
        .msg-link {
            margin-top: 8px;
        }
        .msg-link a {
            color: #e0f2fe;
            text-decoration: underline;
            font-size: 0.85em;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Subir archivo CSV</h2>

    <?php
    $msg      = $_GET["msg"] ?? null;
    $msgType  = $_GET["type"] ?? '';
    $fileLink = isset($_GET["file"]) ? basename($_GET["file"]) : '';
    ?>

    <?php if ($msg): ?>
        <div class="msg <?= $msgType ?>">
            <?= htmlspecialchars($msg) ?>
            <?php if ($fileLink): ?>
                <div class="msg-link">
                    <a href="tabla_registros.php?file=<?= urlencode($fileLink) ?>">Ver tabla de registros aprobados</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="procesar.php" method="POST" enctype="multipart/form-data">
        <label for="csv">Selecciona un archivo CSV o UES</label>
        <input type="file" name="csv" id="csv" accept=".csv,.ues" required>

        <button type="submit">Subir archivo</button>
    </form>
</div>

</body>
</html>
