<?php
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('log_errors', TRUE);
date_default_timezone_set('Europe/Madrid');
header('Content-Type: text/html; charset=utf-8');

try {
    // Cargar configuración y dependencias
    $current_host = $_SERVER['HTTP_HOST'] ?? 'default';
    $config_file = __DIR__ . '/config/' . $current_host . '.php';
    if (!file_exists($config_file)) {
        $config_file = __DIR__ . '/config/default.php';
    }
    require_once $config_file;
    require_once __DIR__ . '/src/includes/Database.php';
    require_once __DIR__ . '/src/includes/Logger.php';
    require_once __DIR__ . '/src/includes/PayPalManager.php';
    require_once __DIR__ . '/src/includes/WooCommerceAPI.php';

    // Validar acceso admin
    $isMatrix = Database::isMatrix();
    $isAdmin = isset($_GET['u'], $_GET['token']) && $_GET['u'] === 'admin' && $_GET['token'] === 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZG1pbiI6ImRpZGFjIn0.uXqn5qDvcpUk2-DjuFOcmis6nNTDhjAudgqnmDpIuM8';
    if (!$isMatrix || !$isAdmin) {
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
        exit;
    }

    $db = Database::getInstance();
    $logger = new Logger('admin');

    // Filtros
    $date = $_GET['date'] ?? date('Y-m-d');
    $accountId = $_GET['account_id'] ?? null;
    $logType = $_GET['log_type'] ?? null;
    $errorType = $_GET['error_type'] ?? null;
    $u = $_GET['u'] ?? '';
    $token = $_GET['token'] ?? '';

    // Datos para el gráfico: pedidos por día y cuenta (últimos 28 días)
    $chartQuery = "
        SELECT pc.date, pa.email, pa.id as paypal_account_id, pc.count
        FROM payment_counters pc
        INNER JOIN paypal_accounts pa ON pa.id = pc.paypal_account_id
        WHERE pc.date >= DATE_SUB(CURDATE(), INTERVAL 27 DAY)
        ORDER BY pc.date ASC, pa.email ASC
    ";
    $chartStmt = $db->prepare($chartQuery);
    $chartStmt->execute();
    $chartData = $chartStmt->fetchAll();

    // Procesar datos para Chart.js
    $dates = [];
    $accountsMap = [];
    $totalsByDate = [];
    foreach ($chartData as $row) {
        $dates[$row['date']] = true;
        $accountsMap[$row['paypal_account_id']]['email'] = $row['email'];
        $accountsMap[$row['paypal_account_id']]['data'][$row['date']] = (int)$row['count'];
        $totalsByDate[$row['date']] = ($totalsByDate[$row['date']] ?? 0) + (int)$row['count'];
    }
    $dates = array_keys($dates);
    sort($dates);
    $datasets = [];
    foreach ($accountsMap as $accId => $acc) {
        $data = [];
        foreach ($dates as $d) {
            $data[] = $acc['data'][$d] ?? 0;
        }
        $datasets[] = [
            'label' => $acc['email'],
            'data' => $data,
        ];
    }
    // Línea de total de pedidos aceptados por día
    $totalPedidos = [];
    foreach ($dates as $d) {
        $totalPedidos[] = $totalsByDate[$d] ?? 0;
    }
    $datasets[] = [
        'label' => 'Total pedidos',
        'data' => $totalPedidos,
        'borderWidth' => 3,
        'borderDash' => [5,5],
        'borderColor' => '#000',
        'backgroundColor' => '#000',
        'tension' => 0.2,
        'fill' => false
    ];

    // Capacidad alcanzada por cuenta en los últimos 7 días
    $capacidadQuery = "
        SELECT pa.id, pa.email, pa.daily_limit,
            AVG(pc.count/pa.daily_limit)*100 as capacidad
        FROM paypal_accounts pa
        LEFT JOIN payment_counters pc ON pc.paypal_account_id = pa.id AND pc.date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        WHERE pa.is_active = 1
        GROUP BY pa.id, pa.email, pa.daily_limit
        ORDER BY pa.email
    ";
    $capacidadStmt = $db->prepare($capacidadQuery);
    $capacidadStmt->execute();
    $capacidadData = $capacidadStmt->fetchAll();

    // Pedidos por día y cuenta
    $ordersQuery = "
        SELECT pc.date, pa.email, pa.id as paypal_account_id, pc.count
        FROM payment_counters pc
        INNER JOIN paypal_accounts pa ON pa.id = pc.paypal_account_id
        WHERE pc.date = :date
        " . ($accountId ? " AND pa.id = :account_id" : "") . "
        ORDER BY pa.email
    ";
    $ordersStmt = $db->prepare($ordersQuery);
    $ordersParams = ['date' => $date];
    if ($accountId) $ordersParams['account_id'] = $accountId;
    $ordersStmt->execute($ordersParams);
    $orders = $ordersStmt->fetchAll();

    // Logs
    $logsQuery = "
        SELECT * FROM logs
        WHERE DATE(created_at) = :date
        " . ($logType ? " AND type = :log_type" : "") . "
        ORDER BY created_at DESC LIMIT 100
    ";
    $logsStmt = $db->prepare($logsQuery);
    $logsParams = ['date' => $date];
    if ($logType) $logsParams['log_type'] = $logType;
    $logsStmt->execute($logsParams);
    $logs = $logsStmt->fetchAll();

    // Errores
    $errorsQuery = "
        SELECT * FROM php_errors
        WHERE DATE(created_at) = :date
        " . ($errorType ? " AND error_type = :error_type" : "") . "
        ORDER BY created_at DESC LIMIT 100
    ";
    $errorsStmt = $db->prepare($errorsQuery);
    $errorsParams = ['date' => $date];
    if ($errorType) $errorsParams['error_type'] = $errorType;
    $errorsStmt->execute($errorsParams);
    $errors = $errorsStmt->fetchAll();

    // Cuentas PayPal para filtro
    $accounts = $db->query('SELECT id, email, daily_limit, is_active FROM paypal_accounts ORDER BY email')->fetchAll();
    // Tipos de log para filtro
    $logTypes = $db->query('SELECT DISTINCT type FROM logs')->fetchAll(PDO::FETCH_COLUMN);
    // Tipos de error para filtro
    $errorTypes = $db->query('SELECT DISTINCT error_type FROM php_errors')->fetchAll(PDO::FETCH_COLUMN);

    // Procesar cambios en cuentas PayPal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_paypal_account'])) {
        $edit_id = (int)$_POST['edit_id'];
        $new_limit = (int)$_POST['new_limit'];
        $new_active = isset($_POST['new_active']) ? 1 : 0;
        $update = $db->prepare('UPDATE paypal_accounts SET daily_limit = :limit, is_active = :active WHERE id = :id');
        $update->execute([
            'limit' => $new_limit,
            'active' => $new_active,
            'id' => $edit_id
        ]);
        // Redirigir para evitar reenvío de formulario y mantener filtros/acceso
        $params = $_GET;
        $params['u'] = $u;
        $params['token'] = $token;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($params));
        exit;
    }

    // Render HTML (Bootstrap básico)
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Panel Admin - Matriz</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <style>body { padding: 2rem; }</style>
    </head>
    <body>
    <div class="container">
        <h1 class="mb-4">Panel de Control - Sitio Matriz</h1>
        <div class="mb-5">
            <h2>Pedidos últimos 28 días</h2>
            <canvas id="ordersChart" height="100"></canvas>
            <div class="mt-4">
                <h4>Capacidad alcanzada por cuenta (últimos 7 días)</h4>
                <table class="table table-bordered table-sm w-auto">
                    <thead><tr><th>Cuenta PayPal</th><th>Límite diario</th><th>Capacidad promedio (%)</th></tr></thead>
                    <tbody>
                    <?php foreach ($capacidadData as $row): ?>
                        <tr>
                            <td><?=htmlspecialchars($row['email'])?></td>
                            <td><?=htmlspecialchars($row['daily_limit'])?></td>
                            <td><?=number_format($row['capacidad'], 1)?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-5">
            <h4>Administrar cuentas PayPal</h4>
            <table class="table table-bordered table-sm w-auto">
                <thead><tr><th>Cuenta PayPal</th><th>Límite diario</th><th>Activa</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $acc): ?>
                    <tr>
                        <form method="post" class="d-flex align-items-center">
                            <input type="hidden" name="edit_paypal_account" value="1">
                            <input type="hidden" name="edit_id" value="<?=htmlspecialchars($acc['id'])?>">
                            <td><?=htmlspecialchars($acc['email'])?></td>
                            <td><input type="number" name="new_limit" value="<?=htmlspecialchars($acc['daily_limit'] ?? 4)?>" min="1" class="form-control form-control-sm" style="width:90px;"></td>
                            <td class="text-center"><input type="checkbox" name="new_active" value="1" <?=($acc['is_active']??1)?'checked':''?>></td>
                            <td><button type="submit" class="btn btn-sm btn-success">Guardar</button></td>
                        </form>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form class="row g-3 mb-4" method="get">
            <input type="hidden" name="u" value="<?=htmlspecialchars($u)?>">
            <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
            <div class="col-auto">
                <label for="date" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="date" name="date" value="<?=htmlspecialchars($date)?>">
            </div>
            <div class="col-auto">
                <label for="account_id" class="form-label">Cuenta PayPal</label>
                <select class="form-select" id="account_id" name="account_id">
                    <option value="">Todas</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?=$acc['id']?>" <?=($accountId==$acc['id']?'selected':'')?>><?=htmlspecialchars($acc['email'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="log_type" class="form-label">Tipo de Log</label>
                <select class="form-select" id="log_type" name="log_type">
                    <option value="">Todos</option>
                    <?php foreach ($logTypes as $type): ?>
                        <option value="<?=$type?>" <?=($logType==$type?'selected':'')?>><?=htmlspecialchars($type)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="error_type" class="form-label">Tipo de Error</label>
                <select class="form-select" id="error_type" name="error_type">
                    <option value="">Todos</option>
                    <?php foreach ($errorTypes as $type): ?>
                        <option value="<?=$type?>" <?=($errorType==$type?'selected':'')?>><?=htmlspecialchars($type)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto align-self-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
        <h2>Pedidos por día y cuenta</h2>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Fecha</th><th>Cuenta PayPal</th><th>Pagos</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?=htmlspecialchars($o['date'])?></td>
                    <td><?=htmlspecialchars($o['email'])?></td>
                    <td><?=htmlspecialchars($o['count'])?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Logs recientes</h2>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Mensaje</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?=htmlspecialchars($log['created_at'])?></td>
                    <td><?=htmlspecialchars($log['type'])?></td>
                    <td><?=htmlspecialchars($log['message'])?></td>
                    <td><?=htmlspecialchars($log['ip'])?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Errores PHP recientes</h2>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Mensaje</th><th>Archivo</th><th>Línea</th></tr></thead>
            <tbody>
            <?php foreach ($errors as $err): ?>
                <tr>
                    <td><?=htmlspecialchars($err['created_at'])?></td>
                    <td><?=htmlspecialchars($err['error_type'])?></td>
                    <td><?=htmlspecialchars($err['error_message'])?></td>
                    <td><?=htmlspecialchars($err['error_file'])?></td>
                    <td><?=htmlspecialchars($err['error_line'])?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    const ctx = document.getElementById('ordersChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?=json_encode($dates)?>,
            datasets: <?=json_encode(array_map(function($ds, $i) {
                $colors = ['#007bff','#28a745','#dc3545','#ffc107','#17a2b8','#6f42c1','#fd7e14','#20c997','#6610f2','#e83e8c','#000'];
                $ds['borderColor'] = $ds['borderColor'] ?? $colors[$i%count($colors)];
                $ds['backgroundColor'] = $ds['backgroundColor'] ?? $colors[$i%count($colors)];
                $ds['tension'] = $ds['tension'] ?? 0.3;
                $ds['fill'] = $ds['fill'] ?? false;
                if (isset($ds['borderWidth'])) $ds['borderWidth'] = $ds['borderWidth'];
                if (isset($ds['borderDash'])) $ds['borderDash'] = $ds['borderDash'];
                return $ds;
            }, $datasets, array_keys($datasets)))?>
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: { display: false }
            },
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { title: { display: true, text: 'Fecha' } },
                y: { title: { display: true, text: 'Pedidos' }, beginAtZero: true }
            }
        }
    });
    </script>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
} 