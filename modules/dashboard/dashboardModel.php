<?php
// Modelo para el Dashboard - manejo de datos y estadísticas - VERSIÓN MEJORADA
require_once dirname(__DIR__, 2) . '/core/db.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

class DashboardModel {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Obtener estadísticas generales del sistema
    public function getGeneralStats() {
        try {
            $stats = [];
            
            // Total de clientes
            $clientsResult = $this->db->select("SELECT COUNT(*) as total FROM clients");
            $stats['total_clients'] = $clientsResult[0]['total'] ?? 0;
            
            // Clientes activos
            $activeClientsResult = $this->db->select("SELECT COUNT(*) as total FROM clients WHERE status = ?", [STATUS_ACTIVE]);
            $stats['active_clients'] = $activeClientsResult[0]['total'] ?? 0;
            
            // Total de productos
            $productsResult = $this->db->select("SELECT COUNT(*) as total FROM products");
            $stats['total_products'] = $productsResult[0]['total'] ?? 0;
            
            // Productos activos
            $activeProductsResult = $this->db->select("SELECT COUNT(*) as total FROM products WHERE status = ?", [STATUS_ACTIVE]);
            $stats['active_products'] = $activeProductsResult[0]['total'] ?? 0;
            
            // Productos con stock bajo
            $lowStockResult = $this->db->select("SELECT COUNT(*) as total FROM products WHERE stock IS NOT NULL AND stock <= ?", [LOW_STOCK_THRESHOLD]);
            $stats['low_stock_products'] = $lowStockResult[0]['total'] ?? 0;
            
            // Total de cotizaciones
            $quotesResult = $this->db->select("SELECT COUNT(*) as total FROM quotes");
            $stats['total_quotes'] = $quotesResult[0]['total'] ?? 0;
            
            // Cotizaciones por estado
            $quoteStatsResult = $this->db->select("
                SELECT 
                    status,
                    COUNT(*) as count,
                    COALESCE(SUM(total_amount), 0) as total_amount
                FROM quotes 
                GROUP BY status
            ");
            
            $stats['quotes_by_status'] = [];
            $stats['quotes_value_by_status'] = [];
            foreach ($quoteStatsResult as $row) {
                $stats['quotes_by_status'][$row['status']] = $row['count'];
                $stats['quotes_value_by_status'][$row['status']] = $row['total_amount'];
            }
            
            // Valor total de cotizaciones
            $totalValueResult = $this->db->select("SELECT COALESCE(SUM(total_amount), 0) as total FROM quotes");
            $stats['total_quotes_value'] = $totalValueResult[0]['total'] ?? 0;
            
            // Total de usuarios
            $usersResult = $this->db->select("SELECT COUNT(*) as total FROM users");
            $stats['total_users'] = $usersResult[0]['total'] ?? 0;
            
            // Usuarios activos
            $activeUsersResult = $this->db->select("SELECT COUNT(*) as total FROM users WHERE status = ?", [STATUS_ACTIVE]);
            $stats['active_users'] = $activeUsersResult[0]['total'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting general stats: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Obtener estadísticas del día actual
    public function getTodayStats() {
        try {
            $stats = [];
            $today = date('Y-m-d');
            
            // Clientes creados hoy
            $clientsTodayResult = $this->db->select("
                SELECT COUNT(*) as total 
                FROM clients 
                WHERE DATE(created_at) = ?", [$today]
            );
            $stats['clients_today'] = $clientsTodayResult[0]['total'] ?? 0;
            
            // Productos creados hoy
            $productsTodayResult = $this->db->select("
                SELECT COUNT(*) as total 
                FROM products 
                WHERE DATE(created_at) = ?", [$today]
            );
            $stats['products_today'] = $productsTodayResult[0]['total'] ?? 0;
            
            // Cotizaciones creadas hoy
            $quotesTodayResult = $this->db->select("
                SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as total_value
                FROM quotes 
                WHERE DATE(quote_date) = ?", [$today]
            );
            $stats['quotes_today'] = $quotesTodayResult[0]['total'] ?? 0;
            $stats['quotes_value_today'] = $quotesTodayResult[0]['total_value'] ?? 0;
            
            // Ventas (cotizaciones aprobadas) hoy
            $salesTodayResult = $this->db->select("
                SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as total_value
                FROM quotes 
                WHERE DATE(quote_date) = ? AND status = ?", 
                [$today, QUOTE_STATUS_APPROVED]
            );
            $stats['sales_today'] = $salesTodayResult[0]['total'] ?? 0;
            $stats['sales_value_today'] = $salesTodayResult[0]['total_value'] ?? 0;
            
            // Cotizaciones por estado hoy
            $quotesStatusTodayResult = $this->db->select("
                SELECT 
                    status,
                    COUNT(*) as count,
                    COALESCE(SUM(total_amount), 0) as total_amount
                FROM quotes 
                WHERE DATE(quote_date) = ?
                GROUP BY status", [$today]
            );
            
            $stats['quotes_by_status_today'] = [];
            $stats['quotes_value_by_status_today'] = [];
            foreach ($quotesStatusTodayResult as $row) {
                $stats['quotes_by_status_today'][$row['status']] = $row['count'];
                $stats['quotes_value_by_status_today'][$row['status']] = $row['total_amount'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting today stats: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Obtener estadísticas de la semana actual
    public function getWeekStats() {
        try {
            $stats = [];
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            
            // Cotizaciones de la semana por día
            $weeklyQuotesResult = $this->db->select("
                SELECT 
                    DATE(quote_date) as day,
                    COUNT(*) as count,
                    COALESCE(SUM(total_amount), 0) as total_value,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sales_count,
                    SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as sales_value
                FROM quotes 
                WHERE DATE(quote_date) BETWEEN ? AND ?
                GROUP BY DATE(quote_date)
                ORDER BY DATE(quote_date)", 
                [QUOTE_STATUS_APPROVED, QUOTE_STATUS_APPROVED, $startOfWeek, $endOfWeek]
            );
            $stats['weekly_quotes'] = $weeklyQuotesResult;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting week stats: " . $e->getMessage());
            return [];
        }
    }

    // Obtener estadísticas mensuales (últimos 6 meses)
    public function getMonthlyStats() {
        try {
            $stats = [];
            
            // Clientes creados por mes (últimos 6 meses)
            $clientsMonthly = $this->db->select("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM clients 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stats['clients_monthly'] = $clientsMonthly;
            
            // Cotizaciones creadas por mes
            $quotesMonthly = $this->db->select("
                SELECT 
                    DATE_FORMAT(quote_date, '%Y-%m') as month,
                    COUNT(*) as count,
                    COALESCE(SUM(total_amount), 0) as total_value,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sales_count,
                    SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as sales_value
                FROM quotes 
                WHERE quote_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(quote_date, '%Y-%m')
                ORDER BY month ASC", 
                [QUOTE_STATUS_APPROVED, QUOTE_STATUS_APPROVED]
            );
            $stats['quotes_monthly'] = $quotesMonthly;
            
            // Productos creados por mes
            $productsMonthly = $this->db->select("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM products 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stats['products_monthly'] = $productsMonthly;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting monthly stats: " . $e->getMessage());
            return [];
        }
    }

    // Obtener actividad reciente del día
    public function getRecentActivity($limitToToday = false) {
        try {
            $activities = [];
            $dateFilter = $limitToToday ? "AND DATE(created_at) = CURDATE()" : "";
            
            // Clientes recientes
            $recentClients = $this->db->select("
                SELECT id, name, email, created_at, 'client' as type
                FROM clients 
                WHERE 1=1 {$dateFilter}
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            
            // Cotizaciones recientes
            $recentQuotes = $this->db->select("
                SELECT 
                    q.id, 
                    q.quote_number, 
                    q.total_amount, 
                    q.quote_date as created_at,
                    c.name as client_name,
                    'quote' as type,
                    q.status
                FROM quotes q
                LEFT JOIN clients c ON q.client_id = c.id
                WHERE 1=1 " . ($limitToToday ? "AND DATE(q.quote_date) = CURDATE()" : "") . "
                ORDER BY q.quote_date DESC 
                LIMIT 5
            ");
            
            // Productos recientes
            $recentProducts = $this->db->select("
                SELECT 
                    p.id, 
                    p.name, 
                    p.base_price, 
                    p.created_at,
                    c.name as category_name,
                    'product' as type
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE 1=1 {$dateFilter}
                ORDER BY p.created_at DESC 
                LIMIT 5
            ");
            
            // Combinar todas las actividades
            $allActivities = array_merge(
                $recentClients,
                $recentQuotes,
                $recentProducts
            );
            
            // Ordenar por fecha de creación
            usort($allActivities, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            // Tomar solo las 10 más recientes
            return array_slice($allActivities, 0, 10);
            
        } catch (Exception $e) {
            error_log("Error getting recent activity: " . $e->getMessage());
            return [];
        }
    }

    // Obtener top clientes (por valor de cotizaciones)
    public function getTopClients($limit = 5) {
        try {
            return $this->db->select("
                SELECT 
                    c.id,
                    c.name,
                    c.email,
                    COUNT(q.id) as total_quotes,
                    COALESCE(SUM(q.total_amount), 0) as total_value,
                    SUM(CASE WHEN q.status = ? THEN q.total_amount ELSE 0 END) as sales_value
                FROM clients c
                LEFT JOIN quotes q ON c.id = q.client_id
                WHERE c.status = ?
                GROUP BY c.id, c.name, c.email
                HAVING COUNT(q.id) > 0
                ORDER BY total_value DESC
                LIMIT ?
            ", [QUOTE_STATUS_APPROVED, STATUS_ACTIVE, $limit]);
            
        } catch (Exception $e) {
            error_log("Error getting top clients: " . $e->getMessage());
            return [];
        }
    }

    // Obtener productos más cotizados
    public function getTopProducts($limit = 5) {
        try {
            return $this->db->select("
                SELECT 
                    p.id,
                    p.name,
                    p.base_price,
                    c.name as category_name,
                    COUNT(qd.id) as times_quoted,
                    COALESCE(SUM(qd.quantity), 0) as total_quantity,
                    COALESCE(SUM(qd.line_total_with_tax), 0) as total_revenue
                FROM products p
                LEFT JOIN quote_details qd ON p.id = qd.product_id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.status = ?
                GROUP BY p.id, p.name, p.base_price, c.name
                HAVING COUNT(qd.id) > 0
                ORDER BY times_quoted DESC, total_revenue DESC
                LIMIT ?
            ", [STATUS_ACTIVE, $limit]);
            
        } catch (Exception $e) {
            error_log("Error getting top products: " . $e->getMessage());
            return [];
        }
    }

    // Obtener cotizaciones que vencen pronto
    public function getExpiringQuotes($days = 7) {
        try {
            return $this->db->select("
                SELECT 
                    q.id,
                    q.quote_number,
                    q.total_amount,
                    q.valid_until,
                    q.status,
                    c.name as client_name,
                    c.email as client_email,
                    DATEDIFF(q.valid_until, CURDATE()) as days_until_expiry
                FROM quotes q
                LEFT JOIN clients c ON q.client_id = c.id
                WHERE q.status IN (?, ?) 
                  AND q.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY q.valid_until ASC
            ", [QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT, $days]);
            
        } catch (Exception $e) {
            error_log("Error getting expiring quotes: " . $e->getMessage());
            return [];
        }
    }

    // Obtener configuración de la empresa para el dashboard
    public function getCompanyInfo() {
        try {
            $settings = $this->db->select("
                SELECT company_name, company_slogan, company_logo, theme
                FROM settings 
                WHERE id = 1
            ");
            
            return $settings[0] ?? [
                'company_name' => SYSTEM_NAME,
                'company_slogan' => SYSTEM_DESCRIPTION,
                'company_logo' => '',
                'theme' => DEFAULT_THEME
            ];
            
        } catch (Exception $e) {
            error_log("Error getting company info: " . $e->getMessage());
            return [
                'company_name' => SYSTEM_NAME,
                'company_slogan' => SYSTEM_DESCRIPTION,
                'company_logo' => '',
                'theme' => DEFAULT_THEME
            ];
        }
    }

    // Obtener información del sistema para el dashboard
    public function getSystemHealth() {
        try {
            $health = [];
            
            // Información de la base de datos
            $dbInfo = $this->db->getDatabaseInfo();
            $health['database'] = $dbInfo;
            
            // Verificar configuración crítica
            $settingsCheck = $this->db->select("SELECT COUNT(*) as configured FROM settings WHERE id = 1");
            $health['settings_configured'] = ($settingsCheck[0]['configured'] ?? 0) > 0;
            
            // Verificar si hay administradores
            $adminCheck = $this->db->select("SELECT COUNT(*) as admin_count FROM users WHERE role = ? AND status = ?", [ROLE_ADMIN, STATUS_ACTIVE]);
            $health['has_admin'] = ($adminCheck[0]['admin_count'] ?? 0) > 0;
            
            // Estado del sistema
            $health['maintenance_mode'] = MAINTENANCE_MODE;
            $health['debug_mode'] = DEBUG_MODE;
            
            return $health;
            
        } catch (Exception $e) {
            error_log("Error getting system health: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Obtener métricas de conversión de ventas
    public function getSalesConversionMetrics() {
        try {
            $metrics = [];
            
            // Conversión general
            $conversionResult = $this->db->select("
                SELECT 
                    COUNT(*) as total_quotes,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_sales,
                    COALESCE(SUM(total_amount), 0) as total_quoted_value,
                    SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as total_sales_value
                FROM quotes
            ", [QUOTE_STATUS_APPROVED, QUOTE_STATUS_APPROVED]);
            
            $data = $conversionResult[0] ?? [];
            $totalQuotes = $data['total_quotes'] ?? 0;
            $totalSales = $data['total_sales'] ?? 0;
            
            $metrics['conversion_rate'] = $totalQuotes > 0 ? round(($totalSales / $totalQuotes) * 100, 2) : 0;
            $metrics['total_quotes'] = $totalQuotes;
            $metrics['total_sales'] = $totalSales;
            $metrics['total_quoted_value'] = $data['total_quoted_value'] ?? 0;
            $metrics['total_sales_value'] = $data['total_sales_value'] ?? 0;
            $metrics['average_quote_value'] = $totalQuotes > 0 ? round($metrics['total_quoted_value'] / $totalQuotes, 2) : 0;
            $metrics['average_sale_value'] = $totalSales > 0 ? round($metrics['total_sales_value'] / $totalSales, 2) : 0;
            
            // Conversión del mes actual
            $monthlyConversionResult = $this->db->select("
                SELECT 
                    COUNT(*) as monthly_quotes,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as monthly_sales,
                    COALESCE(SUM(total_amount), 0) as monthly_quoted_value,
                    SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as monthly_sales_value
                FROM quotes
                WHERE YEAR(quote_date) = YEAR(CURDATE()) AND MONTH(quote_date) = MONTH(CURDATE())
            ", [QUOTE_STATUS_APPROVED, QUOTE_STATUS_APPROVED]);
            
            $monthlyData = $monthlyConversionResult[0] ?? [];
            $monthlyQuotes = $monthlyData['monthly_quotes'] ?? 0;
            $monthlySales = $monthlyData['monthly_sales'] ?? 0;
            
            $metrics['monthly_conversion_rate'] = $monthlyQuotes > 0 ? round(($monthlySales / $monthlyQuotes) * 100, 2) : 0;
            $metrics['monthly_quotes'] = $monthlyQuotes;
            $metrics['monthly_sales'] = $monthlySales;
            $metrics['monthly_quoted_value'] = $monthlyData['monthly_quoted_value'] ?? 0;
            $metrics['monthly_sales_value'] = $monthlyData['monthly_sales_value'] ?? 0;
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("Error getting sales conversion metrics: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Obtener estadísticas por rango de fechas
    public function getStatsForDateRange($startDate, $endDate) {
        try {
            $stats = [];
            
            // Cotizaciones en el rango
            $quotesResult = $this->db->select("
                SELECT 
                    COUNT(*) as total_quotes,
                    COALESCE(SUM(total_amount), 0) as total_value,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sales_count,
                    SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as sales_value,
                    AVG(total_amount) as avg_quote_value
                FROM quotes 
                WHERE DATE(quote_date) BETWEEN ? AND ?
            ", [QUOTE_STATUS_APPROVED, QUOTE_STATUS_APPROVED, $startDate, $endDate]);
            
            $stats['quotes'] = $quotesResult[0] ?? [];
            
            // Clientes nuevos en el rango
            $clientsResult = $this->db->select("
                SELECT COUNT(*) as new_clients
                FROM clients 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ", [$startDate, $endDate]);
            
            $stats['new_clients'] = $clientsResult[0]['new_clients'] ?? 0;
            
            // Productos nuevos en el rango
            $productsResult = $this->db->select("
                SELECT COUNT(*) as new_products
                FROM products 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ", [$startDate, $endDate]);
            
            $stats['new_products'] = $productsResult[0]['new_products'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting stats for date range: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Obtener tendencias de crecimiento
    public function getGrowthTrends() {
        try {
            $trends = [];
            
            // Comparar mes actual vs mes anterior
            $currentMonth = date('Y-m');
            $lastMonth = date('Y-m', strtotime('-1 month'));
            
            // Cotizaciones: mes actual vs anterior
            $quoteTrendsResult = $this->db->select("
                SELECT 
                    DATE_FORMAT(quote_date, '%Y-%m') as month,
                    COUNT(*) as quote_count,
                    COALESCE(SUM(total_amount), 0) as quote_value,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sales_count,
                    SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as sales_value
                FROM quotes 
                WHERE DATE_FORMAT(quote_date, '%Y-%m') IN (?, ?)
                GROUP BY DATE_FORMAT(quote_date, '%Y-%m')
            ", [QUOTE_STATUS_APPROVED, QUOTE_STATUS_APPROVED, $currentMonth, $lastMonth]);
            
            $quoteData = [];
            foreach ($quoteTrendsResult as $row) {
                $quoteData[$row['month']] = $row;
            }
            
            // Calcular crecimiento de cotizaciones
            $currentQuotes = $quoteData[$currentMonth]['quote_count'] ?? 0;
            $lastQuotes = $quoteData[$lastMonth]['quote_count'] ?? 0;
            $trends['quotes_growth'] = $lastQuotes > 0 ? round((($currentQuotes - $lastQuotes) / $lastQuotes) * 100, 2) : 0;
            
            // Calcular crecimiento de ventas
            $currentSales = $quoteData[$currentMonth]['sales_count'] ?? 0;
            $lastSales = $quoteData[$lastMonth]['sales_count'] ?? 0;
            $trends['sales_growth'] = $lastSales > 0 ? round((($currentSales - $lastSales) / $lastSales) * 100, 2) : 0;
            
            // Calcular crecimiento de valor de ventas
            $currentSalesValue = $quoteData[$currentMonth]['sales_value'] ?? 0;
            $lastSalesValue = $quoteData[$lastMonth]['sales_value'] ?? 0;
            $trends['sales_value_growth'] = $lastSalesValue > 0 ? round((($currentSalesValue - $lastSalesValue) / $lastSalesValue) * 100, 2) : 0;
            
            // Clientes: mes actual vs anterior
            $clientTrendsResult = $this->db->select("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as client_count
                FROM clients 
                WHERE DATE_FORMAT(created_at, '%Y-%m') IN (?, ?)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ", [$currentMonth, $lastMonth]);
            
            $clientData = [];
            foreach ($clientTrendsResult as $row) {
                $clientData[$row['month']] = $row;
            }
            
            $currentClients = $clientData[$currentMonth]['client_count'] ?? 0;
            $lastClients = $clientData[$lastMonth]['client_count'] ?? 0;
            $trends['clients_growth'] = $lastClients > 0 ? round((($currentClients - $lastClients) / $lastClients) * 100, 2) : 0;
            
            return $trends;
            
        } catch (Exception $e) {
            error_log("Error getting growth trends: " . $e->getMessage());
            return [];
        }
    }
}
?>