<?php
// Archivo: modules/reports/previewData.php
// Endpoint AJAX para obtener datos reales para la vista previa

require_once dirname(dirname(__DIR__)) . '/config/constants.php';
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/db.php';

// Configurar headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Verificar sesión
    $session = new Session();
    if (!$session->isLoggedIn()) {
        throw new Exception('Sesión no válida');
    }

    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener datos del POST
    $reportType = $_POST['report_type'] ?? '';
    $selectedFields = $_POST['fields'] ?? [];
    $dateFrom = $_POST['date_from'] ?? null;
    $dateTo = $_POST['date_to'] ?? null;

    if (empty($selectedFields)) {
        throw new Exception('No se han seleccionado campos');
    }

    error_log("PREVIEW DEBUG - Tipo: " . $reportType);
    error_log("PREVIEW DEBUG - Campos: " . print_r($selectedFields, true));

    $db = new Database();
    $previewData = [];

    // Generar consulta según el tipo de reporte
    switch ($reportType) {
        case 'cotizaciones':
            $previewData = getQuotesPreview($db, $selectedFields, $dateFrom, $dateTo);
            break;
        case 'productos':
            $previewData = getProductsPreview($db, $selectedFields, $dateFrom, $dateTo);
            break;
        case 'clientes':
            $previewData = getClientsPreview($db, $selectedFields, $dateFrom, $dateTo);
            break;
        case 'ventas':
            $previewData = getSalesPreview($db, $selectedFields, $dateFrom, $dateTo);
            break;
        default:
            throw new Exception('Tipo de reporte no válido');
    }

    echo json_encode([
        'success' => true,
        'data' => $previewData['data'],
        'headers' => $previewData['headers'],
        'total_records' => count($previewData['data']),
        'message' => 'Vista previa generada correctamente'
    ]);

} catch (Exception $e) {
    error_log("Preview error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getQuotesPreview($db, $selectedFields, $dateFrom, $dateTo) {
    $whereConditions = [];
    $params = [];
    
    if ($dateFrom) {
        $whereConditions[] = "q.quote_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $whereConditions[] = "q.quote_date <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $query = "
        SELECT 
            q.id,
            q.quote_number,
            q.quote_date,
            q.valid_until,
            c.name as client_name,
            c.email as client_email,
            c.phone as client_phone,
            c.address as client_address,
            q.subtotal,
            q.discount_percent,
            ROUND(q.subtotal * q.discount_percent / 100, 2) as discount_amount,
            q.tax_amount,
            q.total_amount,
            CASE q.status
                WHEN 1 THEN 'Borrador'
                WHEN 2 THEN 'Enviada'
                WHEN 3 THEN 'Aprobada'
                WHEN 4 THEN 'Rechazada'
                WHEN 5 THEN 'Vencida'
                WHEN 6 THEN 'Cancelada'
                ELSE 'Desconocido'
            END as quote_status,
            q.notes,
            q.created_at,
            q.updated_at,
            DATEDIFF(COALESCE(q.updated_at, NOW()), q.created_at) as days_in_process,
            CASE 
                WHEN q.quote_date > q.valid_until THEN 'Fuera de plazo'
                WHEN q.status = 5 THEN 'Vencida'
                WHEN q.status = 3 THEN 'Cerrada exitosamente'
                ELSE 'En proceso'
            END as timing_status
        FROM quotes q
        LEFT JOIN clients c ON q.client_id = c.id
        $whereClause
        ORDER BY q.created_at DESC
        LIMIT 5
    ";
    
    $data = $db->select($query, $params);
    error_log("COTIZACIONES PREVIEW - Registros encontrados: " . count($data));
    
    return filterPreviewData($data, $selectedFields, 'quotes');
}

function getProductsPreview($db, $selectedFields, $dateFrom, $dateTo) {
    $query = "
        SELECT 
            p.id,
            p.name as product_name,
            p.description,
            cat.name as category_name,
            p.base_price,
            p.tax_rate,
            ROUND(p.base_price * (1 + p.tax_rate / 100), 2) as final_price,
            p.unit,
            p.stock,
            CASE p.status
                WHEN 1 THEN 'Activo'
                WHEN 0 THEN 'Inactivo'
                ELSE 'Desconocido'
            END as product_status,
            p.created_at,
            
            -- Métricas de cotizaciones
            COALESCE(quote_stats.times_quoted, 0) as times_quoted,
            COALESCE(quote_stats.total_quoted_quantity, 0) as total_quoted_quantity,
            COALESCE(quote_stats.total_quoted_value, 0) as total_quoted_value,
            
            -- Métricas de ventas reales
            COALESCE(sales_stats.times_sold, 0) as times_sold,
            COALESCE(sales_stats.total_sold_quantity, 0) as total_sold_quantity,
            COALESCE(sales_stats.total_sales_value, 0) as total_sales_value,
            COALESCE(sales_stats.avg_sale_price, 0) as avg_sale_price,
            
            -- Stock status
            CASE 
                WHEN p.stock IS NULL THEN 'No aplica'
                WHEN p.stock <= 5 THEN 'Stock crítico'
                WHEN p.stock <= 10 THEN 'Stock bajo'
                ELSE 'Stock normal'
            END as stock_status
            
        FROM products p
        LEFT JOIN categories cat ON p.category_id = cat.id
        
        -- Subquery para estadísticas de cotizaciones
        LEFT JOIN (
            SELECT 
                qd.product_id,
                COUNT(qd.id) as times_quoted,
                SUM(qd.quantity) as total_quoted_quantity,
                SUM(qd.line_total_with_tax) as total_quoted_value
            FROM quote_details qd
            JOIN quotes q ON qd.quote_id = q.id
            WHERE q.status IN (1,2,3,4)
            GROUP BY qd.product_id
        ) quote_stats ON p.id = quote_stats.product_id
        
        -- Subquery para estadísticas de ventas reales
        LEFT JOIN (
            SELECT 
                qd.product_id,
                COUNT(qd.id) as times_sold,
                SUM(qd.quantity) as total_sold_quantity,
                SUM(qd.line_total_with_tax) as total_sales_value,
                AVG(qd.unit_price) as avg_sale_price
            FROM quote_details qd
            JOIN quotes q ON qd.quote_id = q.id
            WHERE q.status = 3
            GROUP BY qd.product_id
        ) sales_stats ON p.id = sales_stats.product_id
        
        WHERE p.status = 1
        ORDER BY p.created_at DESC
        LIMIT 5
    ";
    
    $data = $db->select($query);
    error_log("PRODUCTOS PREVIEW - Registros encontrados: " . count($data));
    
    return filterPreviewData($data, $selectedFields, 'products');
}

function getClientsPreview($db, $selectedFields, $dateFrom, $dateTo) {
    $query = "
        SELECT 
            c.id,
            c.name as client_name,
            c.email,
            c.phone,
            c.address,
            CASE c.status
                WHEN 1 THEN 'Activo'
                WHEN 0 THEN 'Inactivo'
                ELSE 'Desconocido'
            END as client_status,
            c.created_at,
            
            -- Métricas de cotizaciones
            COUNT(q.id) as total_quotes,
            SUM(CASE WHEN q.status = 1 THEN 1 ELSE 0 END) as draft_quotes,
            SUM(CASE WHEN q.status = 2 THEN 1 ELSE 0 END) as sent_quotes,
            SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as approved_quotes,
            SUM(CASE WHEN q.status = 4 THEN 1 ELSE 0 END) as rejected_quotes,
            
            -- Valores financieros
            COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
            COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_sales_value,
            COALESCE(AVG(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as avg_sale_value,
            COALESCE(MAX(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as largest_sale,
            
            -- Fechas importantes
            MAX(q.created_at) as last_quote_date,
            MAX(CASE WHEN q.status = 3 THEN q.quote_date END) as last_sale_date,
            
            -- Tasa de conversión
            ROUND(
                CASE WHEN COUNT(q.id) > 0 
                THEN (SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) * 100.0 / COUNT(q.id))
                ELSE 0 END, 2
            ) as conversion_rate,
            
            -- Clasificación del cliente
            CASE 
                WHEN SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END) > 10000 THEN 'VIP'
                WHEN SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END) > 5000 THEN 'Premium'
                WHEN SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END) > 1000 THEN 'Regular'
                WHEN SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END) > 0 THEN 'Básico'
                ELSE 'Prospecto'
            END as customer_tier,
            
            -- Estado de actividad
            CASE 
                WHEN MAX(q.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 'Muy Activo'
                WHEN MAX(q.created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 'Activo'
                WHEN MAX(q.created_at) >= DATE_SUB(CURDATE(), INTERVAL 180 DAY) THEN 'Poco Activo'
                WHEN MAX(q.created_at) IS NOT NULL THEN 'Inactivo'
                ELSE 'Sin Actividad'
            END as activity_status
            
        FROM clients c
        LEFT JOIN quotes q ON c.id = q.client_id
        WHERE c.status = 1
        GROUP BY c.id, c.name, c.email, c.phone, c.address, c.status, c.created_at
        ORDER BY c.created_at DESC
        LIMIT 5
    ";
    
    $data = $db->select($query);
    error_log("CLIENTES PREVIEW - Registros encontrados: " . count($data));
    
    return filterPreviewData($data, $selectedFields, 'clients');
}

function getSalesPreview($db, $selectedFields, $dateFrom, $dateTo) {
    $whereConditions = ["q.status = 3"]; // Solo ventas confirmadas
    $params = [];
    
    if ($dateFrom) {
        $whereConditions[] = "q.quote_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $whereConditions[] = "q.quote_date <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            q.id as sale_id,
            q.quote_number as sale_number,
            q.quote_date as sale_date,
            q.valid_until,
            c.name as client_name,
            c.email as client_email,
            c.phone as client_phone,
            c.address as client_address,
            
            -- Valores de la venta
            q.subtotal,
            q.discount_percent,
            ROUND(q.subtotal * q.discount_percent / 100, 2) as discount_amount,
            q.tax_amount,
            q.total_amount as sale_amount,
            ROUND(q.total_amount - q.tax_amount, 2) as net_sale_amount,
            
            -- Información adicional
            q.notes as sale_notes,
            q.created_at as quote_created_date,
            q.updated_at as sale_confirmed_date,
            
            -- Métricas de tiempo
            DATEDIFF(q.quote_date, q.created_at) as days_to_close,
            CASE 
                WHEN q.quote_date > q.valid_until THEN 'Aprobada fuera de plazo'
                ELSE 'Aprobada en tiempo'
            END as timing_status,
            
            -- Análisis de descuentos
            CASE 
                WHEN q.discount_percent = 0 THEN 'Sin descuento'
                WHEN q.discount_percent <= 5 THEN 'Descuento bajo'
                WHEN q.discount_percent <= 15 THEN 'Descuento moderado'
                ELSE 'Descuento alto'
            END as discount_category,
            
            -- Clasificación por valor
            CASE 
                WHEN q.total_amount < 100 THEN 'Venta pequeña'
                WHEN q.total_amount < 1000 THEN 'Venta mediana'
                WHEN q.total_amount < 5000 THEN 'Venta grande'
                ELSE 'Venta premium'
            END as sale_category,
            
            -- Detalles de productos vendidos
            (SELECT COUNT(DISTINCT qd.product_id) 
             FROM quote_details qd 
             WHERE qd.quote_id = q.id) as products_count,
             
            (SELECT SUM(qd.quantity) 
             FROM quote_details qd 
             WHERE qd.quote_id = q.id) as total_items_sold,
             
            -- Margen estimado
            ROUND(q.total_amount * 0.3, 2) as estimated_profit_30_percent
            
        FROM quotes q
        INNER JOIN clients c ON q.client_id = c.id
        $whereClause
        ORDER BY q.quote_date DESC
        LIMIT 5
    ";
    
    $data = $db->select($query, $params);
    error_log("VENTAS PREVIEW - Registros encontrados: " . count($data));
    
    return filterPreviewData($data, $selectedFields, 'sales_data');
}

function filterPreviewData($data, $selectedFields, $reportType) {
    $fieldMap = getFieldMapping($reportType);
    $filteredData = [];
    $headers = [];
    
    // Generar headers primero
    foreach ($selectedFields as $field) {
        $parts = explode('.', $field);
        $table = $parts[0];
        $column = $parts[1];
        
        if (isset($fieldMap[$table][$column])) {
            $headers[] = getFieldDisplayName($field);
        }
    }
    
    // Filtrar datos
    foreach ($data as $row) {
        $filteredRow = [];
        foreach ($selectedFields as $field) {
            $parts = explode('.', $field);
            $table = $parts[0];
            $column = $parts[1];
            
            if (isset($fieldMap[$table][$column])) {
                $dbColumn = $fieldMap[$table][$column];
                $value = $row[$dbColumn] ?? '';
                
                // Formatear valores
                if (strpos($column, 'date') !== false) {
                    $value = $value ? date('d/m/Y', strtotime($value)) : '';
                } elseif (strpos($column, 'amount') !== false || strpos($column, 'price') !== false || strpos($column, 'value') !== false) {
                    $value = $value ? '$' . number_format($value, 2) : '$0.00';
                } elseif (strpos($column, 'percent') !== false || strpos($column, 'rate') !== false) {
                    $value = $value ? number_format($value, 2) . '%' : '0%';
                } elseif (strpos($column, 'count') !== false || strpos($column, 'quantity') !== false || strpos($column, 'days') !== false) {
                    $value = $value ? number_format($value, 0) : '0';
                }
                
                $filteredRow[] = $value;
            }
        }
        $filteredData[] = $filteredRow;
    }
    
    return ['data' => $filteredData, 'headers' => $headers];
}

function getFieldMapping($reportType) {
    $mappings = [
        'quotes' => [
            'quotes' => [
                'id' => 'id',
                'quote_number' => 'quote_number',
                'quote_date' => 'quote_date',
                'valid_until' => 'valid_until',
                'subtotal' => 'subtotal',
                'discount_percent' => 'discount_percent',
                'discount_amount' => 'discount_amount',
                'tax_amount' => 'tax_amount',
                'total_amount' => 'total_amount',
                'status' => 'quote_status',
                'notes' => 'notes',
                'created_at' => 'created_at',
                'days_in_process' => 'days_in_process',
                'timing_status' => 'timing_status'
            ],
            'clients' => [
                'name' => 'client_name',
                'email' => 'client_email',
                'phone' => 'client_phone',
                'address' => 'client_address'
            ]
        ],
        'products' => [
            'products' => [
                'id' => 'id',
                'name' => 'product_name',
                'description' => 'description',
                'base_price' => 'base_price',
                'final_price' => 'final_price',
                'tax_rate' => 'tax_rate',
                'unit' => 'unit',
                'stock' => 'stock',
                'status' => 'product_status',
                'times_quoted' => 'times_quoted',
                'times_sold' => 'times_sold',
                'total_sales_value' => 'total_sales_value',
                'avg_sale_price' => 'avg_sale_price',
                'stock_status' => 'stock_status'
            ],
            'categories' => [
                'name' => 'category_name'
            ]
        ],
        'clients' => [
            'clients' => [
                'id' => 'id',
                'name' => 'client_name',
                'email' => 'email',
                'phone' => 'phone',
                'address' => 'address',
                'status' => 'client_status',
                'created_at' => 'created_at'
            ],
            'quotes_summary' => [
                'total_quotes' => 'total_quotes',
                'approved_quotes' => 'approved_quotes',
                'total_value' => 'total_quoted_value',
                'approved_value' => 'total_sales_value',
                'last_quote_date' => 'last_quote_date',
                'conversion_rate' => 'conversion_rate',
                'customer_tier' => 'customer_tier',
                'activity_status' => 'activity_status'
            ]
        ],
        'sales_data' => [
            'sales_data' => [
                'sale_id' => 'sale_id',
                'sale_number' => 'sale_number',
                'sale_date' => 'sale_date',
                'sale_amount' => 'sale_amount',
                'net_sale_amount' => 'net_sale_amount',
                'client_name' => 'client_name',
                'days_to_close' => 'days_to_close',
                'timing_status' => 'timing_status',
                'discount_category' => 'discount_category',
                'sale_category' => 'sale_category',
                'products_count' => 'products_count',
                'total_items_sold' => 'total_items_sold',
                'estimated_profit_30_percent' => 'estimated_profit_30_percent'
            ],
            'clients' => [
                'email' => 'client_email',
                'phone' => 'client_phone',
                'address' => 'client_address'
            ],
            'quotes' => [
                'subtotal' => 'subtotal',
                'discount_percent' => 'discount_percent',
                'discount_amount' => 'discount_amount',
                'tax_amount' => 'tax_amount',
                'notes' => 'sale_notes',
                'valid_until' => 'valid_until',
                'created_at' => 'quote_created_date'
            ]
        ]
    ];
    
    return $mappings[$reportType] ?? [];
}

function getFieldDisplayName($field) {
    $displayNames = [
        'quotes.id' => 'ID de Cotización',
        'quotes.quote_number' => 'Número de Cotización',
        'quotes.quote_date' => 'Fecha de Cotización',
        'quotes.valid_until' => 'Válida Hasta',
        'quotes.subtotal' => 'Subtotal',
        'quotes.discount_percent' => 'Descuento (%)',
        'quotes.discount_amount' => 'Monto Descuento',
        'quotes.tax_amount' => 'Impuestos',
        'quotes.total_amount' => 'Total',
        'quotes.status' => 'Estado',
        'quotes.notes' => 'Notas',
        'quotes.created_at' => 'Fecha de Creación',
        'quotes.days_in_process' => 'Días en Proceso',
        'quotes.timing_status' => 'Estado de Tiempo',
        'clients.name' => 'Nombre del Cliente',
        'clients.email' => 'Email del Cliente',
        'clients.phone' => 'Teléfono del Cliente',
        'clients.address' => 'Dirección del Cliente',
        
        'products.id' => 'ID del Producto',
        'products.name' => 'Nombre del Producto',
        'products.description' => 'Descripción',
        'products.base_price' => 'Precio Base',
        'products.final_price' => 'Precio Final',
        'products.tax_rate' => 'Tasa de Impuesto',
        'products.unit' => 'Unidad',
        'products.stock' => 'Stock Disponible',
        'products.status' => 'Estado del Producto',
        'products.times_quoted' => 'Veces Cotizado',
        'products.times_sold' => 'Veces Vendido',
        'products.total_sales_value' => 'Valor Total Vendido',
        'products.avg_sale_price' => 'Precio Promedio Venta',
        'products.stock_status' => 'Estado de Stock',
        'categories.name' => 'Categoría',
        
        'clients.id' => 'ID del Cliente',
        'clients.name' => 'Nombre',
        'clients.email' => 'Email',
        'clients.phone' => 'Teléfono',
        'clients.address' => 'Dirección',
        'clients.status' => 'Estado',
        'clients.created_at' => 'Fecha de Registro',
        'quotes_summary.total_quotes' => 'Total de Cotizaciones',
        'quotes_summary.approved_quotes' => 'Cotizaciones Aprobadas',
        'quotes_summary.total_value' => 'Valor Total Cotizado',
        'quotes_summary.approved_value' => 'Valor Total Vendido',
        'quotes_summary.last_quote_date' => 'Última Cotización',
        'quotes_summary.conversion_rate' => 'Tasa de Conversión (%)',
        'quotes_summary.customer_tier' => 'Clasificación de Cliente',
        'quotes_summary.activity_status' => 'Estado de Actividad',
        
        'sales_data.sale_id' => 'ID de Venta',
        'sales_data.sale_number' => 'Número de Venta',
        'sales_data.sale_date' => 'Fecha de Venta',
        'sales_data.sale_amount' => 'Monto Total de Venta',
        'sales_data.net_sale_amount' => 'Monto Neto (sin impuestos)',
        'sales_data.client_name' => 'Cliente',
        'sales_data.days_to_close' => 'Días para Cerrar',
        'sales_data.timing_status' => 'Estado de Tiempo',
        'sales_data.discount_category' => 'Categoría de Descuento',
        'sales_data.sale_category' => 'Categoría de Venta',
        'sales_data.products_count' => 'Productos Diferentes',
        'sales_data.total_items_sold' => 'Items Totales Vendidos',
        'sales_data.estimated_profit_30_percent' => 'Ganancia Estimada (30%)'
    ];
    
    return $displayNames[$field] ?? ucfirst(str_replace(['_', '.'], [' ', ' '], $field));
}
?>