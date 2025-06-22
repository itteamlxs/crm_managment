<?php
require_once dirname(dirname(__DIR__)) . '/config/constants.php';
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/utils.php';
require_once CORE_PATH . '/db.php';

class SimpleReportGenerator {
    private $db;
    private $session;
    
    public function __construct() {
        $this->db = new Database();
        $this->session = new Session();
        
        if (!$this->session->isLoggedIn()) {
            Utils::redirect('/login.php');
        }
    }
    
    public function generateReport() {
        try {
            // Validar CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de seguridad inválido');
            }
            
            $reportType = $_POST['report_type'] ?? '';
            $selectedFields = $_POST['fields'] ?? [];
            $dateFrom = $_POST['date_from'] ?? null;
            $dateTo = $_POST['date_to'] ?? null;
            $limit = $_POST['limit'] ?? null;
            
            if (empty($selectedFields)) {
                throw new Exception('Debe seleccionar al menos un campo');
            }
            
            // Validar fechas
            if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                throw new Exception('Formato de fecha inicial inválido');
            }
            if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                throw new Exception('Formato de fecha final inválido');
            }
            
            // Generar reporte según el tipo
            switch ($reportType) {
                case 'cotizaciones':
                    $result = $this->generateQuotesReport($selectedFields, $dateFrom, $dateTo, $limit);
                    break;
                case 'productos':
                    $result = $this->generateProductsReport($selectedFields, $dateFrom, $dateTo, $limit);
                    break;
                case 'clientes':
                    $result = $this->generateClientsReport($selectedFields, $dateFrom, $dateTo, $limit);
                    break;
                case 'ventas':
                    $result = $this->generateSalesReport($selectedFields, $dateFrom, $dateTo, $limit);
                    break;
                default:
                    throw new Exception('Tipo de reporte no válido');
            }
            
            // Generar CSV
            $filename = $this->generateFilename($reportType);
            Utils::generateCsv($result['data'], $filename, $result['headers']);
            
        } catch (Exception $e) {
            error_log("Error generating report: " . $e->getMessage());
            $_SESSION['error'] = 'Error al generar reporte: ' . $e->getMessage();
            Utils::redirect('/modules/reports/reportView.php');
        }
    }
    
    private function generateQuotesReport($selectedFields, $dateFrom, $dateTo, $limit) {
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
        $limitClause = $limit && is_numeric($limit) ? 'LIMIT ' . intval($limit) : '';
        
        $query = "
            SELECT 
                q.id,
                q.quote_number,
                q.quote_date,
                q.valid_until,
                c.name as client_name,
                c.email as client_email,
                c.phone as client_phone,
                q.subtotal,
                q.discount_percent,
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
                q.created_at
            FROM quotes q
            LEFT JOIN clients c ON q.client_id = c.id
            $whereClause
            ORDER BY q.created_at DESC
            $limitClause
        ";
        
        $data = $this->db->select($query, $params);
        $headers = $this->getQuoteHeaders($selectedFields);
        $filteredData = $this->filterDataByFields($data, $selectedFields, 'quotes');
        
        return ['data' => $filteredData, 'headers' => $headers];
    }
    
    private function generateProductsReport($selectedFields, $dateFrom, $dateTo, $limit) {
        $limitClause = $limit && is_numeric($limit) ? 'LIMIT ' . intval($limit) : '';
        
        $query = "
            SELECT 
                p.id,
                p.name as product_name,
                p.description,
                cat.name as category_name,
                p.base_price,
                p.tax_rate,
                p.unit,
                p.stock,
                CASE p.status
                    WHEN 1 THEN 'Activo'
                    WHEN 0 THEN 'Inactivo'
                    ELSE 'Desconocido'
                END as product_status,
                p.created_at
            FROM products p
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE p.status = 1
            ORDER BY p.name
            $limitClause
        ";
        
        $data = $this->db->select($query);
        $headers = $this->getProductHeaders($selectedFields);
        $filteredData = $this->filterDataByFields($data, $selectedFields, 'products');
        
        return ['data' => $filteredData, 'headers' => $headers];
    }
    
    private function generateClientsReport($selectedFields, $dateFrom, $dateTo, $limit) {
        $limitClause = $limit && is_numeric($limit) ? 'LIMIT ' . intval($limit) : '';
        
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
                COUNT(q.id) as total_quotes,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as approved_quotes,
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_sales_value,
                MAX(q.created_at) as last_quote_date
            FROM clients c
            LEFT JOIN quotes q ON c.id = q.client_id
            WHERE c.status = 1
            GROUP BY c.id, c.name, c.email, c.phone, c.address, c.status, c.created_at
            ORDER BY c.name
            $limitClause
        ";
        
        $data = $this->db->select($query);
        $headers = $this->getClientHeaders($selectedFields);
        $filteredData = $this->filterDataByFields($data, $selectedFields, 'clients');
        
        return ['data' => $filteredData, 'headers' => $headers];
    }
    
    private function generateSalesReport($selectedFields, $dateFrom, $dateTo, $limit) {
        // SOLO COTIZACIONES APROBADAS - VENTAS REALES
        $whereConditions = ["q.status = 3"]; // Status 3 = Aprobada = Venta confirmada
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
        $limitClause = $limit && is_numeric($limit) ? 'LIMIT ' . intval($limit) : '';
        
        // CONSULTA CORREGIDA - Usando campos que SÍ existen en el esquema
        $query = "
            SELECT 
                q.id,
                q.quote_number,
                q.quote_date as sale_date,
                c.name as client_name,
                c.email as client_email,
                c.phone as client_phone,
                c.address as client_address,
                q.subtotal,
                q.discount_percent,
                q.tax_amount,
                q.total_amount as sale_amount,
                CASE q.status
                    WHEN 3 THEN 'Venta Confirmada'
                    ELSE 'Otro Estado'
                END as sale_status,
                q.notes,
                q.valid_until,
                q.created_at as processing_date
            FROM quotes q
            LEFT JOIN clients c ON q.client_id = c.id
            $whereClause
            ORDER BY q.quote_date DESC
            $limitClause
        ";
        
        error_log("SALES REPORT - FULL SQL: " . $query);
        error_log("SALES REPORT - PARAMS: " . print_r($params, true));
        
        $data = $this->db->select($query, $params);
        error_log("SALES REPORT - RECORDS FOUND: " . count($data));
        
        $headers = $this->getSalesHeaders($selectedFields);
        $filteredData = $this->filterDataByFields($data, $selectedFields, 'sales_data');
        
        return ['data' => $filteredData, 'headers' => $headers];
    }
    
    private function filterDataByFields($data, $selectedFields, $reportType) {
        $fieldMap = $this->getFieldMapping($reportType);
        $filteredData = [];
        
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
                        $value = $value ? Utils::formatDateDisplay($value, false) : '';
                    } elseif (strpos($column, 'amount') !== false || strpos($column, 'price') !== false) {
                        $value = $value ? DEFAULT_CURRENCY_SYMBOL . number_format($value, 2) : '';
                    } elseif (strpos($column, 'percent') !== false) {
                        $value = $value ? $value . '%' : '';
                    }
                    
                    $filteredRow[] = $value;
                }
            }
            $filteredData[] = $filteredRow;
        }
        
        return $filteredData;
    }
    
    private function getFieldMapping($reportType) {
        $mappings = [
            'quotes' => [
                'quotes' => [
                    'id' => 'id',
                    'quote_number' => 'quote_number',
                    'quote_date' => 'quote_date',
                    'valid_until' => 'valid_until',
                    'subtotal' => 'subtotal',
                    'discount_percent' => 'discount_percent',
                    'tax_amount' => 'tax_amount',
                    'total_amount' => 'total_amount',
                    'status' => 'quote_status',
                    'notes' => 'notes',
                    'created_at' => 'created_at'
                ],
                'clients' => [
                    'name' => 'client_name',
                    'email' => 'client_email',
                    'phone' => 'client_phone'
                ]
            ],
            'products' => [
                'products' => [
                    'id' => 'id',
                    'name' => 'product_name',
                    'description' => 'description',
                    'base_price' => 'base_price',
                    'tax_rate' => 'tax_rate',
                    'unit' => 'unit',
                    'stock' => 'stock',
                    'status' => 'product_status'
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
                    'last_quote_date' => 'last_quote_date'
                ]
            ],
            'sales_data' => [
                'sales_data' => [
                    'quote_number' => 'quote_number',
                    'client_name' => 'client_name',
                    'sale_date' => 'sale_date',
                    'total_amount' => 'sale_amount',
                    'sale_status' => 'sale_status'
                ],
                'clients' => [
                    'email' => 'client_email',
                    'phone' => 'client_phone',
                    'address' => 'client_address'
                ],
                'quotes' => [
                    'subtotal' => 'subtotal',
                    'discount_percent' => 'discount_percent',
                    'tax_amount' => 'tax_amount',
                    'notes' => 'notes',
                    'valid_until' => 'valid_until',
                    'created_at' => 'processing_date'
                ]
            ]
        ];
        
        return $mappings[$reportType] ?? [];
    }
    
    private function getQuoteHeaders($selectedFields) {
        return $this->getHeadersForFields($selectedFields, [
            'quotes.id' => 'ID de Cotización',
            'quotes.quote_number' => 'Número de Cotización',
            'quotes.quote_date' => 'Fecha de Cotización',
            'quotes.valid_until' => 'Válida Hasta',
            'quotes.subtotal' => 'Subtotal',
            'quotes.discount_percent' => 'Descuento (%)',
            'quotes.tax_amount' => 'Impuestos',
            'quotes.total_amount' => 'Total',
            'quotes.status' => 'Estado',
            'quotes.notes' => 'Notas',
            'quotes.created_at' => 'Fecha de Creación',
            'clients.name' => 'Nombre del Cliente',
            'clients.email' => 'Email del Cliente',
            'clients.phone' => 'Teléfono del Cliente'
        ]);
    }
    
    private function getProductHeaders($selectedFields) {
        return $this->getHeadersForFields($selectedFields, [
            'products.id' => 'ID del Producto',
            'products.name' => 'Nombre del Producto',
            'products.description' => 'Descripción',
            'products.base_price' => 'Precio Base',
            'products.tax_rate' => 'Tasa de Impuesto',
            'products.unit' => 'Unidad',
            'products.stock' => 'Stock Disponible',
            'products.status' => 'Estado del Producto',
            'categories.name' => 'Categoría'
        ]);
    }
    
    private function getClientHeaders($selectedFields) {
        return $this->getHeadersForFields($selectedFields, [
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
            'quotes_summary.approved_value' => 'Valor Aprobado',
            'quotes_summary.last_quote_date' => 'Última Cotización'
        ]);
    }
    
    private function getSalesHeaders($selectedFields) {
        return $this->getHeadersForFields($selectedFields, [
            'sales_data.quote_number' => 'Número de Venta',
            'sales_data.client_name' => 'Cliente',
            'sales_data.sale_date' => 'Fecha de Venta',
            'sales_data.total_amount' => 'Monto Total de Venta',
            'sales_data.sale_status' => 'Estado de Venta',
            'clients.email' => 'Email del Cliente',
            'clients.phone' => 'Teléfono del Cliente', 
            'clients.address' => 'Dirección del Cliente',
            'quotes.subtotal' => 'Subtotal de la Venta',
            'quotes.discount_percent' => 'Descuento Aplicado (%)',
            'quotes.tax_amount' => 'Impuestos Cobrados',
            'quotes.notes' => 'Notas de la Venta',
            'quotes.valid_until' => 'Válida Hasta',
            'quotes.created_at' => 'Fecha de Procesamiento'
        ]);
    }
    
    private function getHeadersForFields($selectedFields, $headerMap) {
        $headers = [];
        foreach ($selectedFields as $field) {
            $headers[] = $headerMap[$field] ?? ucfirst(str_replace(['_', '.'], [' ', ' '], $field));
        }
        return $headers;
    }
    
    private function generateFilename($reportType) {
        $typeNames = [
            'cotizaciones' => 'cotizaciones',
            'productos' => 'productos',
            'clientes' => 'clientes',
            'ventas' => 'ventas_aprobadas'
        ];
        
        $typeName = $typeNames[$reportType] ?? 'reporte';
        return "reporte_{$typeName}_" . date('Y-m-d_H-i-s') . '.csv';
    }
}

// Procesar solicitud si se llama directamente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $generator = new SimpleReportGenerator();
        $generator->generateReport();
    } catch (Exception $e) {
        error_log("Fatal error in report generation: " . $e->getMessage());
        $_SESSION['error'] = 'Error crítico al generar reporte. Revise los logs del servidor.';
        Utils::redirect('/modules/reports/reportView.php');
    }
} else {
    Utils::redirect('/modules/reports/reportView.php');
}
?>