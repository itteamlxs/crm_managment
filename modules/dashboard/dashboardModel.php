<?php
// Modelo para el Dashboard - manejo de datos y estadísticas
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
                    COALESCE(SUM(total_amount), 0) as total_value
                FROM quotes 
                WHERE quote_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(quote_date, '%Y-%m')
                ORDER BY month ASC
            ");
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

    // Obtener actividad reciente
    public function getRecentActivity() {
        try {
            $activities = [];
            
            // Clientes recientes (últimos 5)
            $recentClients = $this->db->select("
                SELECT id, name, email, created_at, 'client' as type
                FROM clients 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            
            // Cotizaciones recientes (últimas 5)
            $recentQuotes = $this->db->select("
                SELECT 
                    q.id, 
                    q.quote_number, 
                    q.total_amount, 
                    q.quote_date as created_at,
                    c.name as client_name,
                    'quote' as type
                FROM quotes q
                LEFT JOIN clients c ON q.client_id = c.id
                ORDER BY q.quote_date DESC 
                LIMIT 5
            ");
            
            // Productos recientes (últimos 5)
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
                    COALESCE(SUM(q.total_amount), 0) as total_value
                FROM clients c
                LEFT JOIN quotes q ON c.id = q.client_id
                WHERE c.status = ?
                GROUP BY c.id, c.name, c.email
                HAVING COUNT(q.id) > 0
                ORDER BY total_value DESC
                LIMIT ?
            ", [STATUS_ACTIVE, $limit]);
            
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
                    COUNT(qi.id) as times_quoted,
                    COALESCE(SUM(qi.quantity), 0) as total_quantity,
                    COALESCE(SUM(qi.subtotal), 0) as total_revenue
                FROM products p
                LEFT JOIN quote_items qi ON p.id = qi.product_id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.status = ?
                GROUP BY p.id, p.name, p.base_price, c.name
                HAVING COUNT(qi.id) > 0
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
}