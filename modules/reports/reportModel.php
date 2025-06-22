<?php
require_once CORE_PATH . '/db.php';

class ReportModel {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // ========================================
    // REPORTES DE VENTAS Y COTIZACIONES
    // ========================================

    // Resumen general de cotizaciones
    public function getQuotesSummary($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($dateFrom) {
            $whereClause .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $whereClause .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        $query = "
            SELECT 
                COUNT(*) as total_quotes,
                SUM(CASE WHEN q.status = 1 THEN 1 ELSE 0 END) as draft_quotes,
                SUM(CASE WHEN q.status = 2 THEN 1 ELSE 0 END) as sent_quotes,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as approved_quotes,
                SUM(CASE WHEN q.status = 4 THEN 1 ELSE 0 END) as rejected_quotes,
                SUM(CASE WHEN q.status = 5 THEN 1 ELSE 0 END) as expired_quotes,
                SUM(CASE WHEN q.status = 6 THEN 1 ELSE 0 END) as cancelled_quotes,
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as approved_value,
                COALESCE(AVG(q.total_amount), 0) as average_quote_value,
                COALESCE(MAX(q.total_amount), 0) as highest_quote_value,
                ROUND(
                    (SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0)), 
                    2
                ) as approval_rate
            FROM quotes q
            $whereClause
        ";

        $result = $this->db->select($query, $params);
        return $result[0] ?? [];
    }

    // Cotizaciones por estado en rango de fechas
    public function getQuotesByStatus($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($dateFrom) {
            $whereClause .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $whereClause .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        $query = "
            SELECT 
                q.status,
                CASE q.status
                    WHEN 1 THEN 'Borrador'
                    WHEN 2 THEN 'Enviada'
                    WHEN 3 THEN 'Aprobada'
                    WHEN 4 THEN 'Rechazada'
                    WHEN 5 THEN 'Vencida'
                    WHEN 6 THEN 'Cancelada'
                    ELSE 'Desconocido'
                END as status_name,
                COUNT(*) as count,
                COALESCE(SUM(q.total_amount), 0) as total_value,
                COALESCE(AVG(q.total_amount), 0) as average_value
            FROM quotes q
            $whereClause
            GROUP BY q.status
            ORDER BY q.status
        ";

        return $this->db->select($query, $params);
    }

    // Ventas por mes (últimos 12 meses)
    public function getSalesbyMonth($months = 12) {
        $query = "
            SELECT 
                DATE_FORMAT(q.quote_date, '%Y-%m') as month,
                DATE_FORMAT(q.quote_date, '%M %Y') as month_name,
                COUNT(*) as total_quotes,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as approved_quotes,
                COALESCE(SUM(q.total_amount), 0) as total_quoted,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_approved
            FROM quotes q
            WHERE q.quote_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(q.quote_date, '%Y-%m')
            ORDER BY month DESC
        ";

        return $this->db->select($query, [$months]);
    }

    // ========================================
    // REPORTES DE CLIENTES
    // ========================================

    // Resumen de clientes
    public function getClientsSummary() {
        $query = "
            SELECT 
                COUNT(*) as total_clients,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_clients,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_clients
            FROM clients
        ";

        $summary = $this->db->select($query)[0] ?? [];

        // Obtener clientes con más cotizaciones
        $topClientsQuery = "
            SELECT 
                c.name,
                c.email,
                COUNT(q.id) as total_quotes,
                COALESCE(SUM(q.total_amount), 0) as total_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as approved_value
            FROM clients c
            LEFT JOIN quotes q ON c.id = q.client_id
            WHERE c.status = 1
            GROUP BY c.id, c.name, c.email
            HAVING COUNT(q.id) > 0
            ORDER BY total_value DESC
            LIMIT 10
        ";

        $summary['top_clients'] = $this->db->select($topClientsQuery);
        return $summary;
    }

    // Clientes por actividad
    public function getClientsByActivity() {
        $query = "
            SELECT 
                c.id,
                c.name,
                c.email,
                c.phone,
                COUNT(q.id) as total_quotes,
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as approved_value,
                MAX(q.created_at) as last_quote_date,
                CASE 
                    WHEN COUNT(q.id) = 0 THEN 'Sin actividad'
                    WHEN MAX(q.created_at) < DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN 'Inactivo'
                    WHEN MAX(q.created_at) < DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 'Poco activo'
                    ELSE 'Activo'
                END as activity_level
            FROM clients c
            LEFT JOIN quotes q ON c.id = q.client_id
            WHERE c.status = 1
            GROUP BY c.id, c.name, c.email, c.phone
            ORDER BY last_quote_date DESC
        ";

        return $this->db->select($query);
    }

    // ========================================
    // REPORTES DE PRODUCTOS
    // ========================================

    // Productos más cotizados
    public function getTopQuotedProducts($limit = 20, $dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE q.status IN (1,2,3)";
        $params = [];

        if ($dateFrom) {
            $whereClause .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $whereClause .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        $params[] = $limit;

        $query = "
            SELECT 
                p.id,
                p.name as product_name,
                c.name as category_name,
                p.base_price,
                COUNT(qd.id) as times_quoted,
                SUM(qd.quantity) as total_quantity_quoted,
                COALESCE(SUM(qd.line_total_with_tax), 0) as total_value_quoted,
                COALESCE(AVG(qd.unit_price), 0) as average_quoted_price,
                COALESCE(MAX(qd.unit_price), 0) as max_quoted_price,
                COALESCE(MIN(qd.unit_price), 0) as min_quoted_price
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN quote_details qd ON p.id = qd.product_id
            LEFT JOIN quotes q ON qd.quote_id = q.id
            $whereClause
            GROUP BY p.id, p.name, c.name, p.base_price
            HAVING COUNT(qd.id) > 0
            ORDER BY times_quoted DESC, total_value_quoted DESC
            LIMIT ?
        ";

        return $this->db->select($query, $params);
    }

    // Análisis de precios por producto
    public function getProductPriceAnalysis() {
        $query = "
            SELECT 
                p.id,
                p.name as product_name,
                c.name as category_name,
                p.base_price,
                COUNT(qd.id) as times_quoted,
                COALESCE(AVG(qd.unit_price), 0) as avg_quoted_price,
                COALESCE(MAX(qd.unit_price), 0) as max_quoted_price,
                COALESCE(MIN(qd.unit_price), 0) as min_quoted_price,
                COALESCE(STDDEV(qd.unit_price), 0) as price_variance,
                ROUND(
                    ((AVG(qd.unit_price) - p.base_price) / p.base_price * 100), 2
                ) as avg_markup_percent
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN quote_details qd ON p.id = qd.product_id
            LEFT JOIN quotes q ON qd.quote_id = q.id
            WHERE q.status IN (1,2,3) AND p.status = 1
            GROUP BY p.id, p.name, c.name, p.base_price
            HAVING COUNT(qd.id) > 0
            ORDER BY times_quoted DESC
        ";

        return $this->db->select($query);
    }

    // ========================================
    // REPORTES DE INVENTARIO
    // ========================================

    // Productos con stock bajo
    public function getLowStockProducts($threshold = null) {
        $threshold = $threshold ?? LOW_STOCK_THRESHOLD;
        
        $query = "
            SELECT 
                p.id,
                p.name,
                c.name as category_name,
                p.stock,
                p.base_price,
                CASE 
                    WHEN p.stock <= ? THEN 'Crítico'
                    WHEN p.stock <= ? THEN 'Bajo'
                    ELSE 'Normal'
                END as stock_level
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 1 
            AND p.stock IS NOT NULL 
            AND p.stock <= ?
            ORDER BY p.stock ASC, p.name
        ";

        return $this->db->select($query, [
            STOCK_WARNING_THRESHOLD, 
            $threshold, 
            $threshold
        ]);
    }

    // Reporte de inventario completo
    public function getInventoryReport() {
        $query = "
            SELECT 
                p.id,
                p.name,
                c.name as category_name,
                p.stock,
                p.base_price,
                COALESCE(p.stock * p.base_price, 0) as inventory_value,
                COUNT(qd.id) as times_quoted_recently,
                COALESCE(SUM(qd.quantity), 0) as total_quoted_quantity,
                CASE 
                    WHEN p.stock IS NULL THEN 'Sin inventario'
                    WHEN p.stock <= ? THEN 'Stock crítico'
                    WHEN p.stock <= ? THEN 'Stock bajo'
                    ELSE 'Stock normal'
                END as stock_status
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN quote_details qd ON p.id = qd.product_id
            LEFT JOIN quotes q ON qd.quote_id = q.id AND q.quote_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            WHERE p.status = 1
            GROUP BY p.id, p.name, c.name, p.stock, p.base_price
            ORDER BY c.name, p.name
        ";

        return $this->db->select($query, [
            STOCK_WARNING_THRESHOLD,
            LOW_STOCK_THRESHOLD
        ]);
    }

    // ========================================
    // REPORTES FINANCIEROS
    // ========================================

    // Análisis financiero general
    public function getFinancialSummary($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($dateFrom) {
            $whereClause .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $whereClause .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        $query = "
            SELECT 
                COALESCE(SUM(q.subtotal), 0) as total_subtotal,
                COALESCE(SUM(q.tax_amount), 0) as total_tax_collected,
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_approved_value,
                COALESCE(SUM(q.total_amount * q.discount_percent / 100), 0) as total_discounts_given,
                COALESCE(AVG(q.total_amount), 0) as average_quote_value,
                COUNT(*) as total_quotes,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as approved_quotes
            FROM quotes q
            $whereClause
        ";

        $result = $this->db->select($query, $params)[0] ?? [];

        // Calcular métricas adicionales
        if ($result['total_quotes'] > 0) {
            $result['conversion_rate'] = round(($result['approved_quotes'] / $result['total_quotes']) * 100, 2);
        } else {
            $result['conversion_rate'] = 0;
        }

        return $result;
    }

    // Ingresos por categoría de producto
    public function getRevenueByCategory($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE q.status = 3"; // Solo cotizaciones aprobadas
        $params = [];

        if ($dateFrom) {
            $whereClause .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $whereClause .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        $query = "
            SELECT 
                c.name as category_name,
                COUNT(DISTINCT qd.quote_id) as quotes_count,
                SUM(qd.quantity) as total_quantity_sold,
                COALESCE(SUM(qd.line_total_with_tax), 0) as total_revenue,
                COALESCE(AVG(qd.unit_price), 0) as average_unit_price
            FROM quote_details qd
            JOIN products p ON qd.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            JOIN quotes q ON qd.quote_id = q.id
            $whereClause
            GROUP BY c.id, c.name
            ORDER BY total_revenue DESC
        ";

        return $this->db->select($query, $params);
    }

    // ========================================
    // REPORTES PERSONALIZADOS
    // ========================================

    // Obtener datos para gráficos
    public function getChartData($type, $params = []) {
        switch ($type) {
            case 'quotes_by_month':
                return $this->getSalesbyMonth($params['months'] ?? 12);
                
            case 'quotes_by_status':
                return $this->getQuotesByStatus(
                    $params['date_from'] ?? null, 
                    $params['date_to'] ?? null
                );
                
            case 'revenue_by_category':
                return $this->getRevenueByCategory(
                    $params['date_from'] ?? null, 
                    $params['date_to'] ?? null
                );
                
            default:
                return [];
        }
    }

    // Exportar datos a CSV
    public function exportToCSV($reportType, $params = []) {
        $data = [];
        $headers = [];

        switch ($reportType) {
            case 'quotes_summary':
                $data = $this->getQuotesSummary($params['date_from'] ?? null, $params['date_to'] ?? null);
                $headers = [
                    'Total Cotizaciones', 'Borradores', 'Enviadas', 'Aprobadas', 
                    'Rechazadas', 'Vencidas', 'Canceladas', 'Valor Total Cotizado',
                    'Valor Aprobado', 'Valor Promedio', 'Valor Máximo', 'Tasa de Aprobación'
                ];
                break;

            case 'top_products':
                $data = $this->getTopQuotedProducts(50, $params['date_from'] ?? null, $params['date_to'] ?? null);
                $headers = [
                    'ID', 'Producto', 'Categoría', 'Precio Base', 'Veces Cotizado',
                    'Cantidad Total', 'Valor Total', 'Precio Promedio', 'Precio Máximo', 'Precio Mínimo'
                ];
                break;

            case 'clients_activity':
                $data = $this->getClientsByActivity();
                $headers = [
                    'ID', 'Cliente', 'Email', 'Teléfono', 'Total Cotizaciones',
                    'Valor Total', 'Valor Aprobado', 'Última Cotización', 'Nivel de Actividad'
                ];
                break;

            case 'inventory_report':
                $data = $this->getInventoryReport();
                $headers = [
                    'ID', 'Producto', 'Categoría', 'Stock', 'Precio Base',
                    'Valor Inventario', 'Veces Cotizado', 'Cantidad Cotizada', 'Estado Stock'
                ];
                break;

            default:
                throw new Exception('Tipo de reporte no válido para exportación.');
        }

        return ['data' => $data, 'headers' => $headers];
    }

    // ========================================
    // UTILIDADES
    // ========================================

    // Obtener rango de fechas disponible
    public function getDateRange() {
        $query = "
            SELECT 
                MIN(quote_date) as min_date,
                MAX(quote_date) as max_date
            FROM quotes
        ";

        $result = $this->db->select($query);
        return $result[0] ?? ['min_date' => date('Y-m-d'), 'max_date' => date('Y-m-d')];
    }

    // Validar parámetros de fecha
    public function validateDateParams($dateFrom, $dateTo) {
        $errors = [];

        if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $errors[] = 'Formato de fecha inicial inválido';
        }

        if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $errors[] = 'Formato de fecha final inválido';
        }

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            $errors[] = 'La fecha inicial no puede ser mayor que la fecha final';
        }

        return $errors;
    }
}
?>