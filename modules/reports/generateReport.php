<?php
require_once dirname(dirname(__DIR__)) . '/config/constants.php';
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/utils.php';
require_once CORE_PATH . '/db.php';

// Configurar headers de seguridad
Security::setHeaders();

// Inicializar sesión y verificar autenticación
$session = new Session();
if (!$session->isLoggedIn()) {
    Utils::redirect('/login.php');
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Método no permitido';
    Utils::redirect('/modules/reports/reportView.php');
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';
if (!$session->validateCsrfToken($token)) {
    $_SESSION['error'] = 'Token de seguridad inválido';
    Utils::redirect('/modules/reports/reportView.php');
}

try {
    $db = new Database();
    
    // Obtener parámetros del formulario
    $reportType = $_POST['report_type'] ?? '';
    $selectedFields = $_POST['fields'] ?? [];
    $dateFrom = $_POST['date_from'] ?? null;
    $dateTo = $_POST['date_to'] ?? null;
    $limit = $_POST['limit'] ?? null;
    
    // Validar campos seleccionados
    if (empty($selectedFields)) {
        throw new Exception('Debe seleccionar al menos un campo para generar el reporte');
    }
    
    // Configuración de mapeo de campos a consultas SQL
    $fieldMappings = [
        // Tabla quotes
        'quotes.id' => 'q.id',
        'quotes.quote_number' => 'q.quote_number',
        'quotes.quote_date' => 'q.quote_date',
        'quotes.valid_until' => 'q.valid_until',
        'quotes.subtotal' => 'q.subtotal',
        'quotes.discount_percent' => 'q.discount_percent',
        'quotes.tax_amount' => 'q.tax_amount',
        'quotes.total_amount' => 'q.total_amount',
        'quotes.status' => 'CASE q.status 
                            WHEN 1 THEN "Borrador"
                            WHEN 2 THEN "Enviada"
                            WHEN 3 THEN "Aprobada"
                            WHEN 4 THEN "Rechazada"
                            WHEN 5 THEN "Vencida"
                            WHEN 6 THEN "Cancelada"
                            ELSE "Desconocido"
                           END',
        'quotes.notes' => 'q.notes',
        'quotes.created_at' => 'q.created_at',
        
        // Tabla clients
        'clients.name' => 'c.name',
        'clients.email' => 'c.email',
        'clients.phone' => 'c.phone',
        'clients.address' => 'c.address',
        
        // Tabla users
        'users.username' => 'u.username',
        'users.full_name' => 'u.full_name',
        'users.email' => 'u.email',
        
        // Tabla products
        'products.id' => 'p.id',
        'products.name' => 'p.name',
        'products.description' => 'p.description',
        'products.base_price' => 'p.base_price',
        'products.tax_rate' => 'p.tax_rate',
        'products.unit' => 'p.unit',
        'products.stock' => 'p.stock',
        'products.status' => 'CASE p.status WHEN 1 THEN "Activo" ELSE "Inactivo" END',
        
        // Tabla categories
        'categories.name' => 'cat.name',
        
        // Tabla quote_details
        'quote_details.quantity' => 'qd.quantity',
        'quote_details.unit_price' => 'qd.unit_price',
        'quote_details.line_total' => 'qd.line_total',
        'quote_details.discount_percent' => 'qd.discount_percent',
        'quote_details.line_total_with_tax' => 'qd.line_total_with_tax',
        
        // Campos calculados para clientes
        'quotes_summary.total_quotes' => 'COUNT(DISTINCT q.id)',
        'quotes_summary.total_value' => 'COALESCE(SUM(q.total_amount), 0)',
        'quotes_summary.approved_quotes' => 'SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END)',
        'quotes_summary.approved_value' => 'COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0)',
        'quotes_summary.last_quote_date' => 'MAX(q.created_at)',
        
        // Campos calculados para ventas
        'sales_data.quote_number' => 'q.quote_number',
        'sales_data.client_name' => 'c.name',
        'sales_data.seller_name' => 'u.full_name',
        'sales_data.sale_date' => 'q.quote_date',
        'sales_data.total_amount' => 'q.total_amount',
        'sales_data.profit_margin' => 'ROUND(((q.total_amount - q.subtotal) / q.subtotal * 100), 2)',
        'sales_data.payment_status' => 'CASE q.status WHEN 3 THEN "Pagado" ELSE "Pendiente" END'
    ];
    
    // Mapear campos seleccionados a SQL
    $selectFields = [];
    $fieldLabels = [];
    $tablesNeeded = [];
    
    foreach ($selectedFields as $field) {
        if (isset($fieldMappings[$field])) {
            $selectFields[] = $fieldMappings[$field] . ' AS `' . str_replace('.', '_', $field) . '`';
            
            // Extraer etiqueta del campo
            $parts = explode('.', $field);
            $tableName = $parts[0];
            $fieldName = $parts[1];
            
            // Obtener etiqueta amigable
            $fieldLabels[] = getFieldLabel($field);
            
            // Determinar qué tablas necesitamos
            $tablesNeeded[] = $tableName;
        }
    }
    
    // Eliminar duplicados de tablas
    $tablesNeeded = array_unique($tablesNeeded);
    
    // Construir consulta SQL basada en el tipo de reporte
    $query = buildQuery($reportType, $selectFields, $tablesNeeded, $dateFrom, $dateTo, $limit);
    
    // Ejecutar consulta
    $params = [];
    if ($dateFrom) $params[] = $dateFrom;
    if ($dateTo) $params[] = $dateTo;
    
    $results = $db->select($query, $params);
    
    // Generar CSV
    generateCSV($results, $fieldLabels, $reportType);
    
} catch (Exception $e) {
    error_log("Error generando reporte: " . $e->getMessage());
    $_SESSION['error'] = 'Error al generar el reporte: ' . $e->getMessage();
    Utils::redirect('/modules/reports/reportView.php');
}

function getFieldLabel($field) {
    $labels = [
        // Cotizaciones
        'quotes.id' => 'ID Cotización',
        'quotes.quote_number' => 'Número Cotización',
        'quotes.quote_date' => 'Fecha Cotización',
        'quotes.valid_until' => 'Válida Hasta',
        'quotes.subtotal' => 'Subtotal',
        'quotes.discount_percent' => 'Descuento (%)',
        'quotes.tax_amount' => 'Impuestos',
        'quotes.total_amount' => 'Total',
        'quotes.status' => 'Estado',
        'quotes.notes' => 'Notas',
        'quotes.created_at' => 'Fecha Creación',
        
        // Clientes
        'clients.name' => 'Cliente',
        'clients.email' => 'Email Cliente',
        'clients.phone' => 'Teléfono Cliente',
        'clients.address' => 'Dirección Cliente',
        
        // Usuarios/Vendedores
        'users.username' => 'Usuario',
        'users.full_name' => 'Vendedor',
        'users.email' => 'Email Vendedor',
        
        // Productos
        'products.id' => 'ID Producto',
        'products.name' => 'Producto',
        'products.description' => 'Descripción',
        'products.base_price' => 'Precio Base',
        'products.tax_rate' => 'Tasa Impuesto',
        'products.unit' => 'Unidad',
        'products.stock' => 'Stock',
        'products.status' => 'Estado Producto',
        
        // Categorías
        'categories.name' => 'Categoría',
        
        // Detalles de cotización
        'quote_details.quantity' => 'Cantidad',
        'quote_details.unit_price' => 'Precio Unitario',
        'quote_details.line_total' => 'Total Línea',
        'quote_details.discount_percent' => 'Descuento Línea (%)',
        'quote_details.line_total_with_tax' => 'Total con Impuestos',
        
        // Resumen de cotizaciones
        'quotes_summary.total_quotes' => 'Total Cotizaciones',
        'quotes_summary.total_value' => 'Valor Total',
        'quotes_summary.approved_quotes' => 'Cotizaciones Aprobadas',
        'quotes_summary.approved_value' => 'Valor Aprobado',
        'quotes_summary.last_quote_date' => 'Última Cotización',
        
        // Datos de ventas
        'sales_data.quote_number' => 'Núm. Cotización',
        'sales_data.client_name' => 'Cliente',
        'sales_data.seller_name' => 'Vendedor',
        'sales_data.sale_date' => 'Fecha Venta',
        'sales_data.total_amount' => 'Monto Total',
        'sales_data.profit_margin' => 'Margen (%)',
        'sales_data.payment_status' => 'Estado Pago'
    ];
    
    return $labels[$field] ?? str_replace(['_', '.'], [' ', ' '], ucfirst($field));
}

function buildQuery($reportType, $selectFields, $tablesNeeded, $dateFrom, $dateTo, $limit) {
    $select = implode(', ', $selectFields);
    $where = [];
    $groupBy = '';
    $orderBy = '';
    
    switch ($reportType) {
        case 'cotizaciones':
            $from = 'quotes q';
            $joins = [];
            
            if (in_array('clients', $tablesNeeded)) {
                $joins[] = 'LEFT JOIN clients c ON q.client_id = c.id';
            }
            if (in_array('users', $tablesNeeded)) {
                $joins[] = 'LEFT JOIN users u ON q.created_by = u.id';
            }
            if (in_array('quote_details', $tablesNeeded)) {
                $joins[] = 'LEFT JOIN quote_details qd ON q.id = qd.quote_id';
                $joins[] = 'LEFT JOIN products p ON qd.product_id = p.id';
                $joins[] = 'LEFT JOIN categories cat ON p.category_id = cat.id';
            }
            
            if ($dateFrom) $where[] = 'q.quote_date >= ?';
            if ($dateTo) $where[] = 'q.quote_date <= ?';
            
            $orderBy = 'ORDER BY q.created_at DESC';
            break;
            
        case 'productos':
            $from = 'products p';
            $joins = [];
            
            if (in_array('categories', $tablesNeeded)) {
                $joins[] = 'LEFT JOIN categories cat ON p.category_id = cat.id';
            }
            if (in_array('quote_details', $tablesNeeded)) {
                $joins[] = 'LEFT JOIN quote_details qd ON p.id = qd.product_id';
                $joins[] = 'LEFT JOIN quotes q ON qd.quote_id = q.id';
                
                if ($dateFrom) $where[] = 'q.quote_date >= ?';
                if ($dateTo) $where[] = 'q.quote_date <= ?';
            }
            
            $where[] = 'p.status = 1';
            $orderBy = 'ORDER BY p.name';
            break;
            
        case 'clientes':
            $from = 'clients c';
            $joins = [];
            
            if (in_array('quotes_summary', $tablesNeeded)) {
                $joins[] = 'LEFT JOIN quotes q ON c.id = q.client_id';
                $groupBy = 'GROUP BY c.id';
            }
            
            $where[] = 'c.status = 1';
            $orderBy = 'ORDER BY c.name';
            break;
            
        case 'ventas':
            $from = 'quotes q';
            $joins = [
                'JOIN clients c ON q.client_id = c.id',
                'LEFT JOIN users u ON q.created_by = u.id'
            ];
            
            $where[] = 'q.status = 3'; // Solo cotizaciones aprobadas
            if ($dateFrom) $where[] = 'q.quote_date >= ?';
            if ($dateTo) $where[] = 'q.quote_date <= ?';
            
            $orderBy = 'ORDER BY q.quote_date DESC';
            break;
            
        default:
            throw new Exception('Tipo de reporte no válido');
    }
    
    // Construir consulta completa
    $query = "SELECT {$select} FROM {$from}";
    
    if (!empty($joins)) {
        $query .= ' ' . implode(' ', $joins);
    }
    
    if (!empty($where)) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }
    
    if ($groupBy) {
        $query .= ' ' . $groupBy;
    }
    
    if ($orderBy) {
        $query .= ' ' . $orderBy;
    }
    
    if ($limit && is_numeric($limit)) {
        $query .= ' LIMIT ' . intval($limit);
    }
    
    return $query;
}

function generateCSV($data, $headers, $reportType) {
    // Generar nombre de archivo
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "reporte_{$reportType}_{$timestamp}.csv";
    
    // Configurar headers para descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // Escribir BOM para UTF-8 (para que Excel lo abra correctamente)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribir headers
    fputcsv($output, $headers, ';'); // Usar ; como separador para Excel en español
    
    // Escribir datos
    if (!empty($data)) {
        foreach ($data as $row) {
            // Convertir los valores del array asociativo a array indexado
            $csvRow = array_values($row);
            
            // Formatear fechas y números
            $csvRow = array_map(function($value) {
                // Si es una fecha en formato YYYY-MM-DD, formatearla
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return date('d/m/Y', strtotime($value));
                }
                
                // Si es una fecha y hora, formatearla
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                    return date('d/m/Y H:i', strtotime($value));
                }
                
                // Si es un número decimal, usar coma como separador
                if (is_numeric($value) && strpos($value, '.') !== false) {
                    return str_replace('.', ',', $value);
                }
                
                return $value;
            }, $csvRow);
            
            fputcsv($output, $csvRow, ';');
        }
    } else {
        // Si no hay datos, escribir una fila indicándolo
        fputcsv($output, array_fill(0, count($headers), 'Sin datos'), ';');
    }
    
    fclose($output);
    exit;
}
?>