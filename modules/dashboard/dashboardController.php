<?php
// Controlador para el Dashboard - lógica de negocio
require_once dirname(__DIR__, 2) . '/core/session.php';
require_once dirname(__DIR__, 2) . '/core/security.php';
require_once dirname(__DIR__) . '/dashboard/dashboardModel.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

class DashboardController {
    private $session;
    private $model;

    public function __construct() {
        $this->session = new Session();
        $this->model = new DashboardModel();
    }

    // Verificar si el usuario está autenticado
    public function isAuthenticated() {
        return $this->session->isLoggedIn();
    }

    // Verificar si el usuario es administrador
    public function isAdmin() {
        return $this->session->hasRole(ROLE_ADMIN);
    }

    // Obtener el rol del usuario actual
    public function getUserRole() {
        return $this->isAdmin() ? 'admin' : 'seller';
    }

    // Obtener información del usuario actual
    public function getCurrentUser() {
        return [
            'id' => $this->session->getUserId(),
            'name' => $this->session->getUserName(),
            'role' => $this->getUserRole(),
            'is_admin' => $this->isAdmin()
        ];
    }

    // Obtener token CSRF
    public function getCsrfToken() {
        return $this->session->getCsrfToken();
    }

    // Obtener todos los datos para el dashboard
    public function getDashboardData() {
        try {
            $data = [];
            
            // Información del usuario actual
            $data['current_user'] = $this->getCurrentUser();
            
            // Estadísticas generales
            $data['general_stats'] = $this->model->getGeneralStats();
            
            // Estadísticas mensuales para gráficos
            $data['monthly_stats'] = $this->model->getMonthlyStats();
            
            // Actividad reciente
            $data['recent_activity'] = $this->model->getRecentActivity();
            
            // Top clientes
            $data['top_clients'] = $this->model->getTopClients(5);
            
            // Top productos
            $data['top_products'] = $this->model->getTopProducts(5);
            
            // Cotizaciones que vencen pronto
            $data['expiring_quotes'] = $this->model->getExpiringQuotes(7);
            
            // Información de la empresa
            $data['company_info'] = $this->model->getCompanyInfo();
            
            // Salud del sistema (solo para admins)
            if ($this->isAdmin()) {
                $data['system_health'] = $this->model->getSystemHealth();
            }
            
            // Alertas del sistema
            $data['alerts'] = $this->generateSystemAlerts($data);
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Error getting dashboard data: " . $e->getMessage());
            return [
                'error' => 'Error al cargar datos del dashboard.'
            ];
        }
    }

    // Generar alertas del sistema basadas en los datos
    private function generateSystemAlerts($data) {
        $alerts = [];
        
        // Alerta de productos con stock bajo
        if (isset($data['general_stats']['low_stock_products']) && $data['general_stats']['low_stock_products'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => 'Stock Bajo',
                'message' => $data['general_stats']['low_stock_products'] . ' producto(s) con stock bajo',
                'action_url' => BASE_URL . '/modules/products/productList.php?low_stock=1',
                'action_text' => 'Ver Productos'
            ];
        }
        
        // Alerta de cotizaciones por vencer
        if (isset($data['expiring_quotes']) && count($data['expiring_quotes']) > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => '⏰',
                'title' => 'Cotizaciones por Vencer',
                'message' => count($data['expiring_quotes']) . ' cotización(es) vencen en los próximos 7 días',
                'action_url' => BASE_URL . '/modules/quotes/quoteList.php',
                'action_text' => 'Ver Cotizaciones'
            ];
        }
        
        // Alerta de configuración incompleta (solo para admins)
        if ($this->isAdmin() && isset($data['system_health'])) {
            if (!$data['system_health']['settings_configured']) {
                $alerts[] = [
                    'type' => 'error',
                    'icon' => '🔧',
                    'title' => 'Configuración Incompleta',
                    'message' => 'El sistema requiere configuración inicial',
                    'action_url' => BASE_URL . '/modules/settings/settingsView.php',
                    'action_text' => 'Configurar'
                ];
            }
        }
        
        // Alerta de modo de mantenimiento
        if (MAINTENANCE_MODE) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '🚧',
                'title' => 'Modo Mantenimiento',
                'message' => 'El sistema está en modo mantenimiento',
                'action_url' => BASE_URL . '/modules/settings/settingsView.php?tab=system',
                'action_text' => 'Configurar'
            ];
        }
        
        // Alerta de modo debug activado
        if (DEBUG_MODE && $this->isAdmin()) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '🐛',
                'title' => 'Modo Debug Activo',
                'message' => 'Recuerde desactivar el modo debug en producción',
                'action_url' => BASE_URL . '/modules/settings/settingsView.php?tab=system',
                'action_text' => 'Configurar'
            ];
        }
        
        return $alerts;
    }

    // Obtener datos para el widget de estadísticas rápidas
    public function getQuickStats() {
        try {
            $stats = $this->model->getGeneralStats();
            
            return [
                'clients' => [
                    'total' => $stats['total_clients'] ?? 0,
                    'active' => $stats['active_clients'] ?? 0,
                    'icon' => '👥',
                    'color' => 'blue'
                ],
                'products' => [
                    'total' => $stats['total_products'] ?? 0,
                    'active' => $stats['active_products'] ?? 0,
                    'low_stock' => $stats['low_stock_products'] ?? 0,
                    'icon' => '📦',
                    'color' => 'green'
                ],
                'quotes' => [
                    'total' => $stats['total_quotes'] ?? 0,
                    'draft' => $stats['quotes_by_status'][QUOTE_STATUS_DRAFT] ?? 0,
                    'sent' => $stats['quotes_by_status'][QUOTE_STATUS_SENT] ?? 0,
                    'approved' => $stats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0,
                    'total_value' => $stats['total_quotes_value'] ?? 0,
                    'icon' => '📄',
                    'color' => 'purple'
                ],
                'users' => [
                    'total' => $stats['total_users'] ?? 0,
                    'active' => $stats['active_users'] ?? 0,
                    'icon' => '👤',
                    'color' => 'gray'
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error getting quick stats: " . $e->getMessage());
            return [];
        }
    }

    // Refrescar datos específicos (para AJAX)
    public function refreshData($type) {
        try {
            switch ($type) {
                case 'stats':
                    return $this->getQuickStats();
                case 'activity':
                    return $this->model->getRecentActivity();
                case 'alerts':
                    $data = $this->getDashboardData();
                    return $data['alerts'] ?? [];
                case 'expiring':
                    return $this->model->getExpiringQuotes(7);
                default:
                    return ['error' => 'Tipo de datos no válido'];
            }
        } catch (Exception $e) {
            error_log("Error refreshing data: " . $e->getMessage());
            return ['error' => 'Error al refrescar datos'];
        }
    }

    // Exportar estadísticas del dashboard
    public function exportStats($format = 'csv') {
        try {
            $stats = $this->model->getGeneralStats();
            $monthlyStats = $this->model->getMonthlyStats();
            
            switch ($format) {
                case 'csv':
                    $this->exportStatsToCsv($stats, $monthlyStats);
                    break;
                case 'json':
                    $this->exportStatsToJson($stats, $monthlyStats);
                    break;
                default:
                    throw new Exception('Formato no soportado');
            }
            
        } catch (Exception $e) {
            error_log("Error exporting stats: " . $e->getMessage());
            return ['error' => 'Error al exportar estadísticas'];
        }
    }

    // Exportar estadísticas a CSV
    private function exportStatsToCsv($stats, $monthlyStats) {
        $filename = 'dashboard_stats_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Estadísticas generales
        fputcsv($output, ['Estadísticas Generales del Dashboard']);
        fputcsv($output, ['Fecha de Exportación', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        fputcsv($output, ['Métrica', 'Valor']);
        fputcsv($output, ['Total de Clientes', $stats['total_clients'] ?? 0]);
        fputcsv($output, ['Clientes Activos', $stats['active_clients'] ?? 0]);
        fputcsv($output, ['Total de Productos', $stats['total_products'] ?? 0]);
        fputcsv($output, ['Productos Activos', $stats['active_products'] ?? 0]);
        fputcsv($output, ['Productos con Stock Bajo', $stats['low_stock_products'] ?? 0]);
        fputcsv($output, ['Total de Cotizaciones', $stats['total_quotes'] ?? 0]);
        fputcsv($output, ['Valor Total de Cotizaciones', '$' . number_format($stats['total_quotes_value'] ?? 0, 2)]);
        fputcsv($output, ['Total de Usuarios', $stats['total_users'] ?? 0]);
        fputcsv($output, ['Usuarios Activos', $stats['active_users'] ?? 0]);
        
        fclose($output);
        exit;
    }

    // Exportar estadísticas a JSON
    private function exportStatsToJson($stats, $monthlyStats) {
        $filename = 'dashboard_stats_' . date('Y-m-d_H-i-s') . '.json';
        
        $data = [
            'export_date' => date('Y-m-d H:i:s'),
            'general_stats' => $stats,
            'monthly_stats' => $monthlyStats
        ];
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}