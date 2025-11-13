<?php
// admin/api/kardex_export.php - Exportar Stock Actual para Revisión Física
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$auth = new Auth();
$auth->requireLogin();

// Verificar permisos
if (!$auth->hasPermission('kardex') && !$auth->hasPermission('products') && !$auth->hasPermission('all')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado');
}

$database = new Database();
$db = $database->getConnection();

// Obtener parámetros
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'stock-actual';
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Nombre del archivo según la pestaña
$filename = '';
$data = [];

try {
    switch ($tab) {
        case 'stock-actual':
            $filename = 'Stock_Actual_' . date('Y-m-d_His') . '.csv';
            
            // Consulta para stock actual
            $query = "
                SELECT 
                    p.id,
                    p.name as producto,
                    c.name as categoria,
                    p.stock_quantity as stock_inicial,
                    -- Total de compras (purchases)
                    COALESCE((
                        SELECT SUM(pi.quantity)
                        FROM purchase_items pi
                        INNER JOIN purchases pur ON pi.purchase_id = pur.id
                        WHERE pi.product_id = p.id
                    ), 0) as total_compras,
                    -- Total de salidas por órdenes (order_items)
                    COALESCE((
                        SELECT SUM(oi.quantity)
                        FROM order_items oi
                        INNER JOIN orders o ON oi.order_id = o.id
                        WHERE oi.product_id = p.id
                        AND o.status != 'cancelled'
                    ), 0) as total_salidas,
                    -- Stock calculado = stock inicial + compras - salidas
                    (p.stock_quantity + 
                     COALESCE((
                        SELECT SUM(pi.quantity)
                        FROM purchase_items pi
                        INNER JOIN purchases pur ON pi.purchase_id = pur.id
                        WHERE pi.product_id = p.id
                    ), 0) -
                     COALESCE((
                        SELECT SUM(oi.quantity)
                        FROM order_items oi
                        INNER JOIN orders o ON oi.order_id = o.id
                        WHERE oi.product_id = p.id
                        AND o.status != 'cancelled'
                    ), 0)) as stock_actual,
                    p.low_stock_alert as alerta_stock_bajo,
                    p.cost as costo_unitario,
                    p.price as precio_venta,
                    CASE 
                        WHEN (p.stock_quantity + 
                             COALESCE((SELECT SUM(pi.quantity) FROM purchase_items pi INNER JOIN purchases pur ON pi.purchase_id = pur.id WHERE pi.product_id = p.id), 0) -
                             COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'cancelled'), 0)) 
                             <= p.low_stock_alert THEN 'BAJO'
                        WHEN (p.stock_quantity + 
                             COALESCE((SELECT SUM(pi.quantity) FROM purchase_items pi INNER JOIN purchases pur ON pi.purchase_id = pur.id WHERE pi.product_id = p.id), 0) -
                             COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'cancelled'), 0)) 
                             <= p.low_stock_alert * 2 THEN 'MEDIO'
                        ELSE 'NORMAL'
                    END as estado_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.track_inventory = 1 AND p.is_active = 1
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " ORDER BY c.name, p.name";
            
            $stmt = $db->prepare($query);
            if ($category_filter > 0) {
                $stmt->execute([':category_id' => $category_filter]);
            } else {
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular valor total del stock
            foreach ($data as &$row) {
                $row['valor_stock'] = floatval($row['stock_actual']) * floatval($row['costo_unitario']);
                $row['stock_fisico_contado'] = ''; // Columna vacía para conteo físico
                $row['diferencia'] = ''; // Columna vacía para calcular diferencias
                $row['observaciones'] = ''; // Columna vacía para observaciones
            }
            break;

        case 'stock-inicial':
            $filename = 'Stock_Inicial_' . date('Y-m-d_His') . '.csv';
            
            $query = "
                SELECT 
                    p.name as producto,
                    c.name as categoria,
                    p.stock_quantity as stock_inicial,
                    p.cost as costo_unitario,
                    (p.stock_quantity * p.cost) as valor_inicial,
                    (p.stock_quantity + 
                     COALESCE((SELECT SUM(pi.quantity) FROM purchase_items pi INNER JOIN purchases pur ON pi.purchase_id = pur.id WHERE pi.product_id = p.id), 0) -
                     COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'cancelled'), 0)) 
                     as stock_actual,
                    ((p.stock_quantity + 
                     COALESCE((SELECT SUM(pi.quantity) FROM purchase_items pi INNER JOIN purchases pur ON pi.purchase_id = pur.id WHERE pi.product_id = p.id), 0) -
                     COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'cancelled'), 0)) 
                     - p.stock_quantity) as diferencia
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.track_inventory = 1 AND p.is_active = 1
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " ORDER BY c.name, p.name";
            
            $stmt = $db->prepare($query);
            if ($category_filter > 0) {
                $stmt->execute([':category_id' => $category_filter]);
            } else {
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'ingresos':
            $filename = 'Ingresos_' . $date_from . '_a_' . $date_to . '.csv';
            
            $query = "
                SELECT 
                    p.name as producto,
                    c.name as categoria,
                    SUM(pi.quantity) as total_ingresos,
                    pi.unit_cost as costo_unitario,
                    SUM(pi.quantity * pi.unit_cost) as valor_total,
                    COUNT(DISTINCT pur.id) as numero_compras,
                    MIN(pur.purchase_date) as primera_compra,
                    MAX(pur.purchase_date) as ultima_compra
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                INNER JOIN purchase_items pi ON p.id = pi.product_id
                INNER JOIN purchases pur ON pi.purchase_id = pur.id
                WHERE p.track_inventory = 1 
                AND p.is_active = 1
                AND DATE(pur.purchase_date) BETWEEN :date_from AND :date_to
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " GROUP BY p.id, p.name, c.name, pi.unit_cost ORDER BY total_ingresos DESC";
            
            $stmt = $db->prepare($query);
            $params = [':date_from' => $date_from, ':date_to' => $date_to];
            if ($category_filter > 0) {
                $params[':category_id'] = $category_filter;
            }
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'egresos':
            $filename = 'Egresos_' . $date_from . '_a_' . $date_to . '.csv';
            
            $query = "
                SELECT 
                    p.name as producto,
                    c.name as categoria,
                    SUM(oi.quantity) as total_egresos,
                    oi.unit_price as precio_unitario,
                    SUM(oi.quantity * oi.unit_price) as valor_total,
                    COUNT(DISTINCT o.id) as numero_ordenes,
                    MIN(o.created_at) as primera_orden,
                    MAX(o.created_at) as ultima_orden
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                INNER JOIN order_items oi ON p.id = oi.product_id
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE p.track_inventory = 1 
                AND p.is_active = 1
                AND o.status != 'cancelled'
                AND DATE(o.created_at) BETWEEN :date_from AND :date_to
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " GROUP BY p.id, p.name, c.name, oi.unit_price ORDER BY total_egresos DESC";
            
            $stmt = $db->prepare($query);
            $params = [':date_from' => $date_from, ':date_to' => $date_to];
            if ($category_filter > 0) {
                $params[':category_id'] = $category_filter;
            }
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }

    if (empty($data)) {
        header('HTTP/1.1 404 Not Found');
        exit('No hay datos para exportar');
    }

    // Determinar formato de salida
    $format = isset($_GET['format']) ? $_GET['format'] : 'html';
    
    if ($format == 'csv') {
        // Exportar como CSV
        $csv_filename = str_replace('.csv', '', $filename);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csv_filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        if (!empty($data)) {
            fputcsv($output, ['REPORTE: ' . strtoupper(str_replace('-', ' ', $tab))]);
            fputcsv($output, ['Fecha: ' . date('d/m/Y H:i:s')]);
            if ($tab == 'ingresos' || $tab == 'egresos') {
                fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($date_from)) . ' - ' . date('d/m/Y', strtotime($date_to))]);
            }
            fputcsv($output, []);

            $headers = array_keys($data[0]);
            fputcsv($output, $headers);

            foreach ($data as $row) {
                fputcsv($output, array_values($row));
            }
        }

        fclose($output);
        exit;
    }
    
    // Generar HTML por defecto
    $html_filename = str_replace('.csv', '.html', $filename);
    
    // Calcular totales
    $total_productos = count($data);
    $total_stock = 0;
    $total_valor = 0;
    
    if ($tab == 'stock-actual') {
        $total_stock = array_sum(array_column($data, 'stock_actual'));
        $total_valor = array_sum(array_column($data, 'valor_stock'));
    } elseif ($tab == 'stock-inicial') {
        $total_stock = array_sum(array_column($data, 'stock_inicial'));
        $total_valor = array_sum(array_column($data, 'valor_inicial'));
    } elseif ($tab == 'ingresos') {
        $total_stock = array_sum(array_column($data, 'total_ingresos'));
        $total_valor = array_sum(array_column($data, 'valor_total'));
    } elseif ($tab == 'egresos') {
        $total_stock = array_sum(array_column($data, 'total_egresos'));
        $total_valor = array_sum(array_column($data, 'valor_total'));
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo strtoupper(str_replace('-', ' ', $tab)); ?></title>
        <style>
            @media print {
                .no-print { display: none; }
                body { margin: 0; }
                @page { margin: 1cm; }
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f5f5f5;
                padding: 20px;
            }
            
            .container {
                max-width: 1400px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .header {
                border-bottom: 3px solid #667eea;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .header h1 {
                color: #667eea;
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            .header-info {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 15px;
                margin-top: 15px;
            }
            
            .info-item {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .info-label {
                font-weight: 600;
                color: #666;
            }
            
            .info-value {
                color: #333;
            }
            
            .actions {
                margin-bottom: 20px;
                display: flex;
                gap: 10px;
            }
            
            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.3s;
                text-decoration: none;
                display: inline-block;
            }
            
            .btn-primary {
                background: #667eea;
                color: white;
            }
            
            .btn-primary:hover {
                background: #5568d3;
            }
            
            .btn-secondary {
    background: var(--text-secondary) !important;
    color: var(--text-white) !important;
    border-color: var(--text-secondary) !important;
}
            
            .btn-secundary:hover {
                background: #5568d3;
            }
            
            .btn-success {
                background: #28a745;
                color: white;
            }
            
            .btn-success:hover {
                background: #218838;
            }
            
            .stats-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .stat-card h3 {
                font-size: 14px;
                opacity: 0.9;
                margin-bottom: 10px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .stat-card .value {
                font-size: 32px;
                font-weight: bold;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-size: 13px;
            }
            
            thead {
                background: #f8f9fa;
            }
            
            thead th {
                padding: 12px 8px;
                text-align: left;
                font-weight: 600;
                color: #333;
                border-bottom: 2px solid #dee2e6;
                text-transform: uppercase;
                font-size: 11px;
                letter-spacing: 0.5px;
            }
            
            tbody td {
                padding: 12px 8px;
                border-bottom: 1px solid #e9ecef;
                color: #495057;
            }
            
            tbody tr:hover {
                background: #f8f9fa;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                display: inline-block;
            }
            
            .badge-bajo {
                background: #f8d7da;
                color: #721c24;
            }
            
            .badge-medio {
                background: #fff3cd;
                color: #856404;
            }
            
            .badge-normal {
                background: #d4edda;
                color: #155724;
            }
            
            .badge-secondary {
                background: #e9ecef;
                color: #495057;
            }
            
            .totals {
                margin-top: 30px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #667eea;
            }
            
            .totals h3 {
                color: #667eea;
                margin-bottom: 15px;
                font-size: 18px;
            }
            
            .totals-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .total-item {
                display: flex;
                justify-content: space-between;
                padding: 10px;
                background: white;
                border-radius: 5px;
            }
            
            .total-label {
                font-weight: 600;
                color: #666;
            }
            
            .total-value {
                font-weight: bold;
                color: #333;
                font-size: 18px;
            }
            
            .empty-cell {
                background: #fffbcc;
                border: 2px dashed #ffc107;
                min-width: 80px;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .container {
                    box-shadow: none;
                    padding: 0;
                }
                
                table {
                    font-size: 10px;
                }
                
                thead th {
                    font-size: 9px;
                }
                
                tbody td {
                    padding: 8px 4px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📦 <?php echo strtoupper(str_replace('-', ' ', $tab)); ?></h1>
                <div class="header-info">
                    <div class="info-item">
                        <span class="info-label">📅 Fecha de generación:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s'); ?></span>
                    </div>
                    <?php if ($tab == 'ingresos' || $tab == 'egresos'): ?>
                    <div class="info-item">
                        <span class="info-label">📊 Período:</span>
                        <span class="info-value">
                            <?php echo date('d/m/Y', strtotime($date_from)); ?> - 
                            <?php echo date('d/m/Y', strtotime($date_to)); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">📄 Total registros:</span>
                        <span class="info-value"><?php echo $total_productos; ?></span>
                    </div>
                </div>
            </div>

            <div class="actions no-print">
                <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir</button>
                                   <a href="../kardex.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'csv'])); ?>" class="btn btn-success">
                    📊 Descargar Excel
                </a>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <h3>Total Productos</h3>
                    <div class="value"><?php echo number_format($total_productos, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <h3>Total Unidades</h3>
                    <div class="value"><?php echo number_format($total_stock, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <h3>Valor Total</h3>
                    <div class="value">$<?php echo number_format($total_valor, 2, ',', '.'); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <?php if ($tab == 'stock-actual'): ?>
                    <!-- Tabla Stock Actual -->
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th class="text-center">Stock Inicial</th>
                                <th class="text-center">Compras</th>
                                <th class="text-center">Salidas</th>
                                <th class="text-center">Stock Actual</th>
                                <th class="text-center">Alerta</th>
                                <th class="text-right">Costo Unit.</th>
                                <th class="text-right">Valor Stock</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center empty-cell">Físico</th>
                                <th class="text-center empty-cell">Dif.</th>
                                <th class="empty-cell">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['producto']); ?></strong></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['categoria']); ?></span></td>
                                <td class="text-center"><?php echo number_format($row['stock_inicial'], 0, ',', '.'); ?></td>
                                <td class="text-center" style="color: #28a745;">+<?php echo number_format($row['total_compras'], 0, ',', '.'); ?></td>
                                <td class="text-center" style="color: #dc3545;">-<?php echo number_format($row['total_salidas'], 0, ',', '.'); ?></td>
                                <td class="text-center"><strong style="font-size: 15px;"><?php echo number_format($row['stock_actual'], 0, ',', '.'); ?></strong></td>
                                <td class="text-center"><?php echo number_format($row['alerta_stock_bajo'], 0, ',', '.'); ?></td>
                                <td class="text-right">$<?php echo number_format($row['costo_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-right"><strong>$<?php echo number_format($row['valor_stock'], 2, ',', '.'); ?></strong></td>
                                <td class="text-center">
                                    <span class="badge badge-<?php echo strtolower($row['estado_stock']); ?>">
                                        <?php echo $row['estado_stock']; ?>
                                    </span>
                                </td>
                                <td class="empty-cell"></td>
                                <td class="empty-cell"></td>
                                <td class="empty-cell"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php elseif ($tab == 'stock-inicial'): ?>
                    <!-- Tabla Stock Inicial -->
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th class="text-center">Stock Inicial</th>
                                <th class="text-right">Costo Unit.</th>
                                <th class="text-right">Valor Inicial</th>
                                <th class="text-center">Stock Actual</th>
                                <th class="text-center">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['producto']); ?></strong></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['categoria']); ?></span></td>
                                <td class="text-center"><strong><?php echo number_format($row['stock_inicial'], 0, ',', '.'); ?></strong></td>
                                <td class="text-right">$<?php echo number_format($row['costo_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-right"><strong>$<?php echo number_format($row['valor_inicial'], 2, ',', '.'); ?></strong></td>
                                <td class="text-center"><?php echo number_format($row['stock_actual'], 0, ',', '.'); ?></td>
                                <td class="text-center">
                                    <span style="color: <?php echo $row['diferencia'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                                        <?php echo $row['diferencia'] >= 0 ? '+' : ''; ?><?php echo number_format($row['diferencia'], 0, ',', '.'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php elseif ($tab == 'ingresos'): ?>
                    <!-- Tabla Ingresos -->
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th class="text-center">Total Ingresos</th>
                                <th class="text-right">Costo Unit.</th>
                                <th class="text-right">Valor Total</th>
                                <th class="text-center">Nº Compras</th>
                                <th>Primera Compra</th>
                                <th>Última Compra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['producto']); ?></strong></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['categoria']); ?></span></td>
                                <td class="text-center"><strong style="color: #28a745; font-size: 15px;">+<?php echo number_format($row['total_ingresos'], 0, ',', '.'); ?></strong></td>
                                <td class="text-right">$<?php echo number_format($row['costo_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-right"><strong>$<?php echo number_format($row['valor_total'], 2, ',', '.'); ?></strong></td>
                                <td class="text-center"><?php echo $row['numero_compras']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['primera_compra'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['ultima_compra'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php elseif ($tab == 'egresos'): ?>
                    <!-- Tabla Egresos -->
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th class="text-center">Total Egresos</th>
                                <th class="text-right">Precio Unit.</th>
                                <th class="text-right">Valor Total</th>
                                <th class="text-center">Nº Órdenes</th>
                                <th>Primera Orden</th>
                                <th>Última Orden</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['producto']); ?></strong></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['categoria']); ?></span></td>
                                <td class="text-center"><strong style="color: #dc3545; font-size: 15px;">-<?php echo number_format($row['total_egresos'], 0, ',', '.'); ?></strong></td>
                                <td class="text-right">$<?php echo number_format($row['precio_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-right"><strong>$<?php echo number_format($row['valor_total'], 2, ',', '.'); ?></strong></td>
                                <td class="text-center"><?php echo $row['numero_ordenes']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['primera_orden'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['ultima_orden'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="totals">
                <h3>📊 Resumen Total</h3>
                <div class="totals-grid">
                    <div class="total-item">
                        <span class="total-label">Total de Productos:</span>
                        <span class="total-value"><?php echo number_format($total_productos, 0, ',', '.'); ?></span>
                    </div>
                    <div class="total-item">
                        <span class="total-label">Total Unidades:</span>
                        <span class="total-value"><?php echo number_format($total_stock, 0, ',', '.'); ?></span>
                    </div>
                    <div class="total-item">
                        <span class="total-label">Valor Total:</span>
                        <span class="total-value">$<?php echo number_format($total_valor, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;

} catch (Exception $e) {
    error_log("Error en kardex_export.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error al generar la exportación: ' . $e->getMessage());
}
?>