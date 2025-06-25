<?php
require_once CORE_PATH . '/db.php';

class ReportModel {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // ========================================
    // REPORTES DE VENTAS Y COTIZACIONES MEJORADOS
    // ========================================

    /**
     * Resumen general de cotizaciones con métricas mejoradas
     */
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
                
                -- Valores totales
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_sales_value,
                COALESCE(SUM(CASE WHEN q.status = 4 THEN q.total_amount ELSE 0 END), 0) as rejected_value,
                COALESCE(SUM(CASE WHEN q.status = 5 THEN q.total_amount ELSE 0 END), 0) as expired_value,
                
                -- Promedios
                COALESCE(AVG(q.total_amount), 0) as average_quote_value,
                COALESCE(AVG(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as average_sale_value,
                
                -- Extremos
                COALESCE(MAX(q.total_amount), 0) as highest_quote_value,
                COALESCE(MIN(q.total_amount), 0) as lowest_quote_value,
                COALESCE(MAX(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as highest_sale_value,
                
                -- Tasas de conversión
                ROUND(
                    CASE WHEN COUNT(*) > 0 
                    THEN (SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
                ) as approval_rate,
                
                ROUND(
                    CASE WHEN COUNT(*) > 0 
                    THEN (SUM(CASE WHEN q.status = 4 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
                ) as rejection_rate,
                
                ROUND(
                    CASE WHEN COUNT(*) > 0 
                    THEN (SUM(CASE WHEN q.status = 5 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
                ) as expiration_rate,
                
                -- Métricas adicionales
                COALESCE(SUM(q.tax_amount), 0) as total_tax_collected,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.tax_amount ELSE 0 END), 0) as sales_tax_collected,
                COALESCE(AVG(q.discount_percent), 0) as average_discount_percent,
                COALESCE(SUM(q.subtotal * q.discount_percent / 100), 0) as total_discounts_given
                
            FROM quotes q
            $whereClause
        ";

        $result = $this->db->select($query, $params);
        return $result[0] ?? [];
    }

    /**
     * Reporte específico de ventas (solo cotizaciones aprobadas)
     */
    public function getSalesReport($dateFrom = null, $dateTo = null, $limit = null) {
        $whereClause = "WHERE q.status = 3"; // Solo ventas confirmadas
        $params = [];

        if ($dateFrom) {
            $whereClause .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $whereClause .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        $limitClause = '';
        if ($limit && is_numeric($limit)) {
            $limitClause = "LIMIT ?";
            $params[] = intval($limit);
        }

        $query = "
            SELECT 
                q.id,
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
                q.tax_amount,
                q.total_amount as sale_amount,
                
                -- Cálculos adicionales
                ROUND(q.subtotal * q.discount_percent / 100, 2) as discount_amount,
                ROUND(q.total_amount - q.tax_amount, 2) as net_sale_amount,
                
                -- Información adicional
                q.notes as sale_notes,
                q.created_at as processing_date,
                q.updated_at as last_updated,
                
                -- Información del vendedor (si existe relación)
                'Sistema' as seller_name, -- Placeholder hasta implementar relación con usuarios
                
                -- Métricas calculadas
                DATEDIFF(q.quote_date, q.created_at) as days_to_close,
                CASE 
                    WHEN q.quote_date > q.valid_until THEN 'Vencida al aprobar'
                    ELSE 'Aprobada en tiempo'
                END as timing_status
                
            FROM quotes q
            INNER JOIN clients c ON q.client_id = c.id
            $whereClause
            ORDER BY q.quote_date DESC, q.total_amount DESC
            $limitClause
        ";

        return $this->db->select($query, $params);
    }

    /**
     * Análisis detallado de ventas por período
     */
    public function getSalesAnalysis($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE q.status = 3";
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
                -- Métricas básicas de ventas
                COUNT(*) as total_sales,
                COALESCE(SUM(q.total_amount), 0) as total_revenue,
                COALESCE(SUM(q.subtotal), 0) as total_subtotal,
                COALESCE(SUM(q.tax_amount), 0) as total_tax,
                COALESCE(SUM(q.subtotal * q.discount_percent / 100), 0) as total_discounts,
                
                -- Promedios
                COALESCE(AVG(q.total_amount), 0) as average_sale_amount,
                COALESCE(AVG(q.discount_percent), 0) as average_discount_percent,
                
                -- Rangos
                COALESCE(MAX(q.total_amount), 0) as largest_sale,
                COALESCE(MIN(q.total_amount), 0) as smallest_sale,
                
                -- Distribución por rangos de valor
                SUM(CASE WHEN q.total_amount < 100 THEN 1 ELSE 0 END) as sales_under_100,
                SUM(CASE WHEN q.total_amount BETWEEN 100 AND 500 THEN 1 ELSE 0 END) as sales_100_500,
                SUM(CASE WHEN q.total_amount BETWEEN 500 AND 1000 THEN 1 ELSE 0 END) as sales_500_1000,
                SUM(CASE WHEN q.total_amount BETWEEN 1000 AND 5000 THEN 1 ELSE 0 END) as sales_1000_5000,
                SUM(CASE WHEN q.total_amount > 5000 THEN 1 ELSE 0 END) as sales_over_5000,
                
                -- Clientes únicos
                COUNT(DISTINCT q.client_id) as unique_customers,
                
                -- Tiempo promedio desde creación hasta venta
                AVG(DATEDIFF(q.quote_date, q.created_at)) as avg_days_to_close
                
            FROM quotes q
            $whereClause
        ";

        $result = $this->db->select($query, $params);
        return $result[0] ?? [];
    }

    /**
     * Ventas por mes mejorado con más métricas
     */
    public function getSalesbyMonth($months = 12) {
        $query = "
            SELECT 
                DATE_FORMAT(q.quote_date, '%Y-%m') as month,
                DATE_FORMAT(q.quote_date, '%M %Y') as month_name,
                YEAR(q.quote_date) as year,
                MONTH(q.quote_date) as month_number,
                
                -- Cotizaciones totales
                COUNT(q.id) as total_quotes_month,
                
                -- Ventas (cotizaciones aprobadas)
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as sales_count,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as sales_revenue,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.subtotal ELSE 0 END), 0) as sales_subtotal,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.tax_amount ELSE 0 END), 0) as sales_tax,
                
                -- Otras métricas
                SUM(CASE WHEN q.status = 4 THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN q.status = 5 THEN 1 ELSE 0 END) as expired_count,
                
                -- Tasa de conversión mensual
                ROUND(
                    CASE WHEN COUNT(q.id) > 0 
                    THEN (SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) * 100.0 / COUNT(q.id))
                    ELSE 0 END, 2
                ) as monthly_conversion_rate,
                
                -- Valor promedio de venta mensual
                COALESCE(AVG(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as avg_sale_value,
                
                -- Clientes únicos que compraron en el mes
                COUNT(DISTINCT CASE WHEN q.status = 3 THEN q.client_id END) as unique_buyers
                
            FROM quotes q
            WHERE q.quote_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(q.quote_date, '%Y-%m')
            ORDER BY month DESC
        ";

        return $this->db->select($query, [$months]);
    }

    /**
     * Top productos vendidos (basado en cotizaciones aprobadas)
     */
    public function getTopSoldProducts($limit = 20, $dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE q.status = 3"; // Solo ventas confirmadas
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
                p.description,
                c.name as category_name,
                p.base_price as catalog_price,
                p.unit,
                
                -- Métricas de ventas
                COUNT(qd.id) as times_sold,
                SUM(qd.quantity) as total_quantity_sold,
                COALESCE(SUM(qd.line_total_with_tax), 0) as total_revenue,
                COALESCE(SUM(qd.line_subtotal), 0) as total_subtotal,
                COALESCE(SUM(qd.tax_amount), 0) as total_tax_collected,
                
                -- Análisis de precios
                COALESCE(AVG(qd.unit_price), 0) as average_sale_price,
                COALESCE(MAX(qd.unit_price), 0) as highest_sale_price,
                COALESCE(MIN(qd.unit_price), 0) as lowest_sale_price,
                
                -- Descuentos
                COALESCE(AVG(qd.discount_percent), 0) as average_discount,
                COALESCE(SUM(qd.discount_amount), 0) as total_discount_given,
                
                -- Análisis de márgenes
                ROUND(
                    CASE WHEN p.base_price > 0 
                    THEN ((AVG(qd.unit_price) - p.base_price) / p.base_price * 100)
                    ELSE 0 END, 2
                ) as average_markup_percent,
                
                -- Clientes únicos que compraron este producto
                COUNT(DISTINCT q.client_id) as unique_buyers,
                
                -- Participación en ventas totales
                ROUND(
                    (SUM(qd.line_total_with_tax) * 100.0 / 
                     (SELECT SUM(qd2.line_total_with_tax) 
                      FROM quote_details qd2 
                      JOIN quotes q2 ON qd2.quote_id = q2.id 
                      WHERE q2.status = 3)), 2
                ) as revenue_share_percent
                
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            INNER JOIN quote_details qd ON p.id = qd.product_id
            INNER JOIN quotes q ON qd.quote_id = q.id
            $whereClause
            GROUP BY p.id, p.name, p.description, c.name, p.base_price, p.unit
            ORDER BY total_revenue DESC, times_sold DESC
            LIMIT ?
        ";

        return $this->db->select($query, $params);
    }

    /**
     * Análisis de clientes por valor de compras
     */
    public function getCustomerSalesAnalysis($limit = 50) {
        $query = "
            SELECT 
                c.id,
                c.name as customer_name,
                c.email,
                c.phone,
                c.address,
                c.created_at as customer_since,
                
                -- Métricas de cotizaciones
                COUNT(q.id) as total_quotes,
                SUM(CASE WHEN q.status = 1 THEN 1 ELSE 0 END) as draft_quotes,
                SUM(CASE WHEN q.status = 2 THEN 1 ELSE 0 END) as sent_quotes,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as completed_sales,
                SUM(CASE WHEN q.status = 4 THEN 1 ELSE 0 END) as rejected_quotes,
                
                -- Valores totales
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_sales_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.tax_amount ELSE 0 END), 0) as total_tax_paid,
                
                -- Promedios
                COALESCE(AVG(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as average_sale_value,
                COALESCE(AVG(CASE WHEN q.status = 3 THEN q.discount_percent END), 0) as average_discount_received,
                
                -- Extremos
                COALESCE(MAX(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as largest_purchase,
                COALESCE(MIN(CASE WHEN q.status = 3 THEN q.total_amount END), 0) as smallest_purchase,
                
                -- Fechas importantes
                MAX(q.quote_date) as last_quote_date,
                MAX(CASE WHEN q.status = 3 THEN q.quote_date END) as last_purchase_date,
                MIN(CASE WHEN q.status = 3 THEN q.quote_date END) as first_purchase_date,
                
                -- Tasa de conversión del cliente
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
                
                -- Actividad reciente
                CASE 
                    WHEN MAX(q.quote_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 'Muy Activo'
                    WHEN MAX(q.quote_date) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 'Activo'
                    WHEN MAX(q.quote_date) >= DATE_SUB(CURDATE(), INTERVAL 180 DAY) THEN 'Poco Activo'
                    WHEN MAX(q.quote_date) IS NOT NULL THEN 'Inactivo'
                    ELSE 'Sin Actividad'
                END as activity_status
                
            FROM clients c
            LEFT JOIN quotes q ON c.id = q.client_id
            WHERE c.status = 1
            GROUP BY c.id, c.name, c.email, c.phone, c.address, c.created_at
            ORDER BY total_sales_value DESC, total_quotes DESC
            LIMIT ?
        ";

        return $this->db->select($query, [$limit]);
    }

    /**
     * Ingresos por categoría mejorado
     */
    public function getRevenueByCategory($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE q.status = 3"; // Solo ventas confirmadas
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
                cat.id as category_id,
                cat.name as category_name,
                
                -- Métricas de ventas
                COUNT(DISTINCT qd.quote_id) as sales_count,
                COUNT(DISTINCT qd.product_id) as products_sold,
                SUM(qd.quantity) as total_units_sold,
                
                -- Valores
                COALESCE(SUM(qd.line_total_with_tax), 0) as total_revenue,
                COALESCE(SUM(qd.line_subtotal), 0) as total_subtotal,
                COALESCE(SUM(qd.tax_amount), 0) as total_tax,
                COALESCE(SUM(qd.discount_amount), 0) as total_discounts,
                
                -- Promedios
                COALESCE(AVG(qd.unit_price), 0) as average_unit_price,
                COALESCE(AVG(qd.line_total_with_tax), 0) as average_line_value,
                COALESCE(AVG(qd.discount_percent), 0) as average_discount_percent,
                
                -- Participación en el total
                ROUND(
                    (SUM(qd.line_total_with_tax) * 100.0 / 
                     (SELECT SUM(qd2.line_total_with_tax) 
                      FROM quote_details qd2 
                      JOIN quotes q2 ON qd2.quote_id = q2.id 
                      WHERE q2.status = 3)), 2
                ) as revenue_percentage,
                
                -- Clientes únicos por categoría
                COUNT(DISTINCT q.client_id) as unique_customers,
                
                -- Producto más vendido de la categoría
                (SELECT p.name 
                 FROM products p 
                 JOIN quote_details qd_inner ON p.id = qd_inner.product_id
                 JOIN quotes q_inner ON qd_inner.quote_id = q_inner.id
                 WHERE p.category_id = cat.id AND q_inner.status = 3
                 GROUP BY p.id, p.name
                 ORDER BY SUM(qd_inner.line_total_with_tax) DESC
                 LIMIT 1
                ) as top_product
                
            FROM categories cat
            INNER JOIN products p ON cat.id = p.category_id
            INNER JOIN quote_details qd ON p.id = qd.product_id
            INNER JOIN quotes q ON qd.quote_id = q.id
            $whereClause
            GROUP BY cat.id, cat.name
            ORDER BY total_revenue DESC
        ";

        return $this->db->select($query, $params);
    }

    // ========================================
    // MÉTRICAS FINANCIERAS MEJORADAS
    // ========================================

    /**
     * Análisis financiero detallado
     */
    public function getFinancialSummary($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE 1=1";
        $salesWhereClause = "WHERE q.status = 3";
        $params = [];
        $salesParams = [];

        if ($dateFrom) {
            $whereClause .= " AND q.quote_date >= ?";
            $salesWhereClause .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
            $salesParams[] = $dateFrom;
        }
        if ($dateTo) {
            $whereClause .= " AND q.quote_date <= ?";
            $salesWhereClause .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
            $salesParams[] = $dateTo;
        }

        // Consulta principal de ventas
        $salesQuery = "
            SELECT 
                -- Métricas básicas de ventas
                COUNT(*) as total_sales,
                COALESCE(SUM(q.total_amount), 0) as total_revenue,
                COALESCE(SUM(q.subtotal), 0) as total_subtotal,
                COALESCE(SUM(q.tax_amount), 0) as total_tax_collected,
                COALESCE(SUM(q.subtotal * q.discount_percent / 100), 0) as total_discounts_given,
                
                -- Promedios
                COALESCE(AVG(q.total_amount), 0) as average_sale_amount,
                COALESCE(AVG(q.discount_percent), 0) as average_discount_percent,
                
                -- Extremos
                COALESCE(MAX(q.total_amount), 0) as largest_sale,
                COALESCE(MIN(q.total_amount), 0) as smallest_sale,
                
                -- Clientes únicos
                COUNT(DISTINCT q.client_id) as unique_customers_served
                
            FROM quotes q
            $salesWhereClause
        ";

        // Consulta de comparación con cotizaciones totales
        $quotesQuery = "
            SELECT 
                COUNT(*) as total_quotes,
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as approved_quotes,
                SUM(CASE WHEN q.status = 4 THEN 1 ELSE 0 END) as rejected_quotes,
                SUM(CASE WHEN q.status = 5 THEN 1 ELSE 0 END) as expired_quotes
            FROM quotes q
            $whereClause
        ";

        $salesResult = $this->db->select($salesQuery, $salesParams)[0] ?? [];
        $quotesResult = $this->db->select($quotesQuery, $params)[0] ?? [];

        // Combinar resultados y calcular métricas adicionales
        $result = array_merge($salesResult, $quotesResult);

        // Calcular tasas de conversión
        if ($result['total_quotes'] > 0) {
            $result['conversion_rate'] = round(($result['approved_quotes'] / $result['total_quotes']) * 100, 2);
            $result['rejection_rate'] = round(($result['rejected_quotes'] / $result['total_quotes']) * 100, 2);
            $result['expiration_rate'] = round(($result['expired_quotes'] / $result['total_quotes']) * 100, 2);
        } else {
            $result['conversion_rate'] = 0;
            $result['rejection_rate'] = 0;
            $result['expiration_rate'] = 0;
        }

        // Calcular eficiencia de ventas
        if ($result['total_quoted_value'] > 0) {
            $result['revenue_efficiency'] = round(($result['total_revenue'] / $result['total_quoted_value']) * 100, 2);
        } else {
            $result['revenue_efficiency'] = 0;
        }

        return $result;
    }

    // ========================================
    // RESTO DE MÉTODOS (mantenidos igual)
    // ========================================

    public function getQuotesByStatus($dateFrom = null, $dateTo = null) {
        // ... (mantener igual)
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

    // ... (mantener otros métodos existentes)

    /**
     * Exportar datos mejorado con nuevas consultas
     */
    public function exportToCSV($reportType, $params = []) {
        $data = [];
        $headers = [];

        switch ($reportType) {
            case 'sales_report':
                $data = $this->getSalesReport($params['date_from'] ?? null, $params['date_to'] ?? null, $params['limit'] ?? null);
                $headers = [
                    'ID', 'Número de Venta', 'Fecha de Venta', 'Cliente', 'Email', 'Teléfono',
                    'Subtotal', 'Descuento %', 'Impuestos', 'Total Venta', 'Días para Cerrar'
                ];
                break;

            case 'top_sold_products':
                $data = $this->getTopSoldProducts($params['limit'] ?? 50, $params['date_from'] ?? null, $params['date_to'] ?? null);
                $headers = [
                    'ID', 'Producto', 'Categoría', 'Precio Catálogo', 'Veces Vendido',
                    'Cantidad Total', 'Ingresos Totales', 'Precio Promedio', 'Descuento Promedio', 
                    'Margen Promedio %', 'Clientes Únicos', 'Participación %'
                ];
                break;

            case 'customer_sales':
                $data = $this->getCustomerSalesAnalysis($params['limit'] ?? 50);
                $headers = [
                    'ID', 'Cliente', 'Email', 'Teléfono', 'Cliente Desde', 'Total Cotizaciones',
                    'Ventas Completadas', 'Valor Total Vendido', 'Valor Promedio Venta', 
                    'Tasa Conversión %', 'Clasificación', 'Estado Actividad'
                ];
                break;

            case 'revenue_by_category':
                $data = $this->getRevenueByCategory($params['date_from'] ?? null, $params['date_to'] ?? null);
                $headers = [
                    'Categoría', 'Ventas', 'Productos Vendidos', 'Unidades Totales',
                    'Ingresos Totales', 'Precio Promedio', 'Participación %', 
                    'Clientes Únicos', 'Producto Top'
                ];
                break;

            case 'financial_summary':
                $data = [$this->getFinancialSummary($params['date_from'] ?? null, $params['date_to'] ?? null)];
                $headers = [
                    'Total Ventas', 'Ingresos Totales', 'Impuestos Recaudados', 'Descuentos Dados',
                    'Venta Promedio', 'Venta Mayor', 'Clientes Únicos', 'Tasa Conversión %', 
                    'Eficiencia Ingresos %'
                ];
                break;

            case 'sales_by_month':
                $data = $this->getSalesbyMonth($params['months'] ?? 12);
                $headers = [
                    'Mes', 'Año', 'Ventas', 'Ingresos', 'Tasa Conversión %', 
                    'Valor Promedio Venta', 'Compradores Únicos'
                ];
                break;

            // Casos existentes mantenidos
            case 'quotes_summary':
                $data = [$this->getQuotesSummary($params['date_from'] ?? null, $params['date_to'] ?? null)];
                $headers = [
                    'Total Cotizaciones', 'Borradores', 'Enviadas', 'Aprobadas', 
                    'Rechazadas', 'Vencidas', 'Canceladas', 'Valor Total Cotizado',
                    'Valor Total Ventas', 'Tasa Aprobación %', 'Descuentos Totales'
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
    // MÉTODOS ADICIONALES PARA COMPLETAR
    // ========================================

    public function getClientsSummary() {
        $query = "
            SELECT 
                COUNT(*) as total_clients,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_clients,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_clients
            FROM clients
        ";

        $summary = $this->db->select($query)[0] ?? [];

        // Obtener clientes con más ventas (no solo cotizaciones)
        $topClientsQuery = "
            SELECT 
                c.name,
                c.email,
                COUNT(q.id) as total_quotes,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as total_sales,
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_sales_value
            FROM clients c
            LEFT JOIN quotes q ON c.id = q.client_id
            WHERE c.status = 1
            GROUP BY c.id, c.name, c.email
            HAVING COUNT(q.id) > 0
            ORDER BY total_sales_value DESC, total_sales DESC
            LIMIT 10
        ";

        $summary['top_clients'] = $this->db->select($topClientsQuery);
        return $summary;
    }

    public function getClientsByActivity() {
        $query = "
            SELECT 
                c.id,
                c.name,
                c.email,
                c.phone,
                COUNT(q.id) as total_quotes,
                SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as total_sales,
                COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
                COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as total_sales_value,
                MAX(q.created_at) as last_quote_date,
                MAX(CASE WHEN q.status = 3 THEN q.quote_date END) as last_sale_date,
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
            ORDER BY total_sales_value DESC, last_quote_date DESC
        ";

        return $this->db->select($query);
    }

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

    public function getLowStockProducts($threshold = null) {
        $threshold = $threshold ?? 10; // LOW_STOCK_THRESHOLD
        
        $query = "
            SELECT 
                p.id,
                p.name,
                c.name as category_name,
                p.stock,
                p.base_price,
                CASE 
                    WHEN p.stock <= 5 THEN 'Crítico'
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

        return $this->db->select($query, [$threshold, $threshold]);
    }

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
                    WHEN p.stock <= 5 THEN 'Stock crítico'
                    WHEN p.stock <= 10 THEN 'Stock bajo'
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

        return $this->db->select($query);
    }

    public function getChartData($type, $params = []) {
        switch ($type) {
            case 'sales_by_month':
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