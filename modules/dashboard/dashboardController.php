<?php
// Controlador para el Dashboard - lógica de negocio - VERSIÓN MEJORADA
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
            
            // NUEVO: Estadísticas del día
            $data['today_stats'] = $this->model->getTodayStats();
            
            // NUEVO: Estadísticas de la semana
            $data['week_stats'] = $this->model->getWeekStats();
            
            // Estadísticas mensuales para gráficos
            $data['monthly_stats'] = $this->model->getMonthlyStats();
            
            // NUEVO: Métricas de conversión de ventas
            $data['sales_metrics'] = $this->model->getSalesConversionMetrics();
            
            // NUEVO: Tendencias de crecimiento
            $data['growth_trends'] = $this->model->getGrowthTrends();
            
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
                'title' => 'Cotizaciones por Vencer',
                'message' => count($data['expiring_quotes']) . ' cotización(es) vencen en los próximos 7 días',
                'action_url' => BASE_URL . '/modules/quotes/quoteList.php',
                'action_text' => 'Ver Cotizaciones'
            ];
        }
        
        // NUEVO: Alerta de baja conversión de ventas
        if (isset($data['sales_metrics']['conversion_rate']) && $data['sales_metrics']['conversion_rate'] < 20 && $data['general_stats']['total_quotes'] > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Conversión de Ventas Baja',
                'message' => 'Tasa de conversión actual: ' . $data['sales_metrics']['conversion_rate'] . '%',
                'action_url' => BASE_URL . '/modules/quotes/quoteList.php?status=' . QUOTE_STATUS_SENT,
                'action_text' => 'Revisar Cotizaciones'
            ];
        }
        
        // NUEVO: Alerta de rendimiento del día
        if (isset($data['today_stats']['quotes_today']) && $data['today_stats']['quotes_today'] == 0 && date('H') > 12) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Sin Actividad Hoy',
                'message' => 'No se han creado cotizaciones hoy',
                'action_url' => BASE_URL . '/modules/quotes/quoteForm.php',
                'action_text' => 'Crear Cotización'
            ];
        }
        
        // Alerta de configuración incompleta (solo para admins)
        if ($this->isAdmin() && isset($data['system_health'])) {
            if (!$data['system_health']['settings_configured']) {
                $alerts[] = [
                    'type' => 'error',
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
            $todayStats = $this->model->getTodayStats();
            
            return [
                'clients' => [
                    'total' => $stats['total_clients'] ?? 0,
                    'active' => $stats['active_clients'] ?? 0,
                    'today' => $todayStats['clients_today'] ?? 0,
                    'color' => 'blue'
                ],
                'products' => [
                    'total' => $stats['total_products'] ?? 0,
                    'active' => $stats['active_products'] ?? 0,
                    'low_stock' => $stats['low_stock_products'] ?? 0,
                    'today' => $todayStats['products_today'] ?? 0,
                    'color' => 'green'
                ],
                'quotes' => [
                    'total' => $stats['total_quotes'] ?? 0,
                    'draft' => $stats['quotes_by_status'][QUOTE_STATUS_DRAFT] ?? 0,
                    'sent' => $stats['quotes_by_status'][QUOTE_STATUS_SENT] ?? 0,
                    'approved' => $stats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0,
                    'total_value' => $stats['total_quotes_value'] ?? 0,
                    'today' => $todayStats['quotes_today'] ?? 0,
                    'today_value' => $todayStats['quotes_value_today'] ?? 0,
                    'color' => 'purple'
                ],
                'sales' => [
                    'total' => $stats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0,
                    'total_value' => $stats['quotes_value_by_status'][QUOTE_STATUS_APPROVED] ?? 0,
                    'today' => $todayStats['sales_today'] ?? 0,
                    'today_value' => $todayStats['sales_value_today'] ?? 0,
                    'color' => 'emerald'
                ],
                'users' => [
                    'total' => $stats['total_users'] ?? 0,
                    'active' => $stats['active_users'] ?? 0,
                    'color' => 'gray'
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error getting quick stats: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Obtener datos específicos del día
    public function getTodayData() {
        try {
            return [
                'stats' => $this->model->getTodayStats(),
                'activity' => $this->model->getRecentActivity(true), // Solo del día
                'sales_metrics' => $this->model->getSalesConversionMetrics()
            ];
        } catch (Exception $e) {
            error_log("Error getting today data: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Obtener datos para gráficos en tiempo real
    public function getChartData($type = 'all') {
        try {
            $data = [];
            
            switch ($type) {
                case 'today':
                    $todayStats = $this->model->getTodayStats();
                    $data = [
                        'labels' => ['Clientes', 'Productos', 'Cotizaciones', 'Ventas'],
                        'datasets' => [[
                            'label' => 'Creados Hoy',
                            'data' => [
                                $todayStats['clients_today'] ?? 0,
                                $todayStats['products_today'] ?? 0,
                                $todayStats['quotes_today'] ?? 0,
                                $todayStats['sales_today'] ?? 0
                            ],
                            'backgroundColor' => [
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(16, 185, 129, 0.8)', 
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(5, 150, 105, 0.8)'
                            ]
                        ]]
                    ];
                    break;
                    
                case 'conversion':
                    $salesMetrics = $this->model->getSalesConversionMetrics();
                    $totalQuotes = $salesMetrics['total_quotes'] ?? 0;
                    $totalSales = $salesMetrics['total_sales'] ?? 0;
                    $data = [
                        'labels' => ['Ventas Cerradas', 'Cotizaciones Pendientes'],
                        'datasets' => [[
                            'data' => [$totalSales, $totalQuotes - $totalSales],
                            'backgroundColor' => [
                                'rgba(5, 150, 105, 0.8)',
                                'rgba(156, 163, 175, 0.3)'
                            ]
                        ]]
                    ];
                    break;
                    
                case 'weekly':
                    $weekStats = $this->model->getWeekStats();
                    $labels = [];
                    $quotesData = [];
                    $salesData = [];
                    
                    // Generar datos para los 7 días de la semana
                    for ($i = 0; $i < 7; $i++) {
                        $date = date('Y-m-d', strtotime('monday this week +' . $i . ' days'));
                        $labels[] = date('D', strtotime($date));
                        
                        // Buscar datos para este día
                        $dayData = array_filter($weekStats['weekly_quotes'] ?? [], function($item) use ($date) {
                            return $item['day'] === $date;
                        });
                        
                        $quotesData[] = $dayData ? array_values($dayData)[0]['count'] : 0;
                        $salesData[] = $dayData ? array_values($dayData)[0]['sales_count'] : 0;
                    }
                    
                    $data = [
                        'labels' => $labels,
                        'datasets' => [
                            [
                                'label' => 'Cotizaciones',
                                'data' => $quotesData,
                                'borderColor' => 'rgb(139, 92, 246)',
                                'backgroundColor' => 'rgba(139, 92, 246, 0.1)'
                            ],
                            [
                                'label' => 'Ventas',
                                'data' => $salesData,
                                'borderColor' => 'rgb(5, 150, 105)',
                                'backgroundColor' => 'rgba(5, 150, 105, 0.1)'
                            ]
                        ]
                    ];
                    break;
                    
                default:
                    $monthlyStats = $this->model->getMonthlyStats();
                    $data = [
                        'monthly' => $monthlyStats,
                        'today' => $this->getChartData('today'),
                        'conversion' => $this->getChartData('conversion')
                    ];
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Error getting chart data: " . $e->getMessage());
            return [];
        }
    }

    // Refrescar datos específicos (para AJAX)
    public function refreshData($type) {
        try {
            switch ($type) {
                case 'stats':
                    return $this->getQuickStats();
                case 'today':
                    return $this->getTodayData();
                case 'activity':
                    return $this->model->getRecentActivity();
                case 'alerts':
                    $data = $this->getDashboardData();
                    return $data['alerts'] ?? [];
                case 'expiring':
                    return $this->model->getExpiringQuotes(7);
                case 'charts':
                    return $this->getChartData();
                case 'sales_metrics':
                    return $this->model->getSalesConversionMetrics();
                case 'growth_trends':
                    return $this->model->getGrowthTrends();
                default:
                    return ['error' => 'Tipo de datos no válido'];
            }
        } catch (Exception $e) {
            error_log("Error refreshing data: " . $e->getMessage());
            return ['error' => 'Error al refrescar datos'];
        }
    }

    // NUEVO: Obtener resumen ejecutivo
    public function getExecutiveSummary() {
        try {
            $generalStats = $this->model->getGeneralStats();
            $todayStats = $this->model->getTodayStats();
            $salesMetrics = $this->model->getSalesConversionMetrics();
            $trends = $this->model->getGrowthTrends();
            
            return [
                'totals' => [
                    'clients' => $generalStats['total_clients'] ?? 0,
                    'products' => $generalStats['total_products'] ?? 0,
                    'quotes' => $generalStats['total_quotes'] ?? 0,
                    'sales' => $generalStats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0,
                    'revenue' => $generalStats['quotes_value_by_status'][QUOTE_STATUS_APPROVED] ?? 0
                ],
                'today' => [
                    'clients' => $todayStats['clients_today'] ?? 0,
                    'products' => $todayStats['products_today'] ?? 0,
                    'quotes' => $todayStats['quotes_today'] ?? 0,
                    'sales' => $todayStats['sales_today'] ?? 0,
                    'revenue' => $todayStats['sales_value_today'] ?? 0
                ],
                'conversion' => [
                    'rate' => $salesMetrics['conversion_rate'] ?? 0,
                    'monthly_rate' => $salesMetrics['monthly_conversion_rate'] ?? 0,
                    'avg_quote_value' => $salesMetrics['average_quote_value'] ?? 0,
                    'avg_sale_value' => $salesMetrics['average_sale_value'] ?? 0
                ],
                'growth' => $trends
            ];
            
        } catch (Exception $e) {
            error_log("Error getting executive summary: " . $e->getMessage());
            return [];
        }
    }

    // Exportar estadísticas del dashboard
    public function exportStats($format = 'csv') {
        try {
            $stats = $this->model->getGeneralStats();
            $todayStats = $this->model->getTodayStats();
            $salesMetrics = $this->model->getSalesConversionMetrics();
            $trends = $this->model->getGrowthTrends();
            
            switch ($format) {
                case 'csv':
                    $this->exportStatsToCsv($stats, $todayStats, $salesMetrics, $trends);
                    break;
                case 'json':
                    $this->exportStatsToJson($stats, $todayStats, $salesMetrics, $trends);
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
    private function exportStatsToCsv($stats, $todayStats, $salesMetrics, $trends) {
        $filename = 'dashboard_stats_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Estadísticas generales
        fputcsv($output, ['Estadísticas del Dashboard CRM']);
        fputcsv($output, ['Fecha de Exportación', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        // Totales generales
        fputcsv($output, ['ESTADÍSTICAS GENERALES']);
        fputcsv($output, ['Métrica', 'Total', 'Activos', 'Hoy']);
        fputcsv($output, ['Clientes', $stats['total_clients'] ?? 0, $stats['active_clients'] ?? 0, $todayStats['clients_today'] ?? 0]);
        fputcsv($output, ['Productos', $stats['total_products'] ?? 0, $stats['active_products'] ?? 0, $todayStats['products_today'] ?? 0]);
        fputcsv($output, ['Productos con Stock Bajo', $stats['low_stock_products'] ?? 0, '-', '-']);
        fputcsv($output, ['Cotizaciones', $stats['total_quotes'] ?? 0, '-', $todayStats['quotes_today'] ?? 0]);
        fputcsv($output, ['Ventas', $stats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0, '-', $todayStats['sales_today'] ?? 0]);
        fputcsv($output, ['Usuarios', $stats['total_users'] ?? 0, $stats['active_users'] ?? 0, '-']);
        fputcsv($output, []);
        
        // Valores monetarios
        fputcsv($output, ['VALORES MONETARIOS']);
        fputcsv($output, ['Concepto', 'Valor Total', 'Valor Hoy']);
        fputcsv($output, ['Cotizaciones', '$' . number_format($stats['total_quotes_value'] ?? 0, 2), '$' . number_format($todayStats['quotes_value_today'] ?? 0, 2)]);
        fputcsv($output, ['Ventas', '$' . number_format($stats['quotes_value_by_status'][QUOTE_STATUS_APPROVED] ?? 0, 2), '$' . number_format($todayStats['sales_value_today'] ?? 0, 2)]);
        fputcsv($output, []);
        
        // Métricas de conversión
        fputcsv($output, ['MÉTRICAS DE CONVERSIÓN']);
        fputcsv($output, ['Métrica', 'Valor']);
        fputcsv($output, ['Tasa de Conversión General', ($salesMetrics['conversion_rate'] ?? 0) . '%']);
        fputcsv($output, ['Tasa de Conversión Mensual', ($salesMetrics['monthly_conversion_rate'] ?? 0) . '%']);
        fputcsv($output, ['Valor Promedio de Cotización', '$' . number_format($salesMetrics['average_quote_value'] ?? 0, 2)]);
        fputcsv($output, ['Valor Promedio de Venta', '$' . number_format($salesMetrics['average_sale_value'] ?? 0, 2)]);
        fputcsv($output, []);
        
        // Tendencias de crecimiento
        fputcsv($output, ['TENDENCIAS DE CRECIMIENTO (vs Mes Anterior)']);
        fputcsv($output, ['Métrica', 'Crecimiento %']);
        fputcsv($output, ['Cotizaciones', $trends['quotes_growth'] ?? 0 . '%']);
        fputcsv($output, ['Ventas', $trends['sales_growth'] ?? 0 . '%']);
        fputcsv($output, ['Valor de Ventas', $trends['sales_value_growth'] ?? 0 . '%']);
        fputcsv($output, ['Clientes', $trends['clients_growth'] ?? 0 . '%']);
        
        fclose($output);
        exit;
    }

    // Exportar estadísticas a JSON
    private function exportStatsToJson($stats, $todayStats, $salesMetrics, $trends) {
        $filename = 'dashboard_stats_' . date('Y-m-d_H-i-s') . '.json';
        
        $data = [
            'export_info' => [
                'export_date' => date('Y-m-d H:i:s'),
                'system_name' => SYSTEM_NAME,
                'version' => SYSTEM_VERSION
            ],
            'general_stats' => $stats,
            'today_stats' => $todayStats,
            'sales_metrics' => $salesMetrics,
            'growth_trends' => $trends,
            'summary' => [
                'total_revenue' => $stats['quotes_value_by_status'][QUOTE_STATUS_APPROVED] ?? 0,
                'today_revenue' => $todayStats['sales_value_today'] ?? 0,
                'conversion_rate' => $salesMetrics['conversion_rate'] ?? 0,
                'monthly_conversion_rate' => $salesMetrics['monthly_conversion_rate'] ?? 0
            ]
        ];
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // NUEVO: Obtener datos para widget de alertas en tiempo real
    public function getRealTimeAlerts() {
        try {
            $alerts = [];
            $now = new DateTime();
            
            // Cotizaciones que vencen hoy
            $expiringToday = $this->model->getExpiringQuotes(0);
            if (!empty($expiringToday)) {
                $alerts[] = [
                    'type' => 'urgent',
                    'title' => 'Cotizaciones Vencen Hoy',
                    'message' => count($expiringToday) . ' cotización(es) vencen hoy',
                    'count' => count($expiringToday),
                    'priority' => 'high'
                ];
            }
            
            // Verificar si es hora de seguimiento (9 AM - 6 PM)
            $hour = (int)$now->format('H');
            if ($hour >= 9 && $hour <= 18) {
                $todayStats = $this->model->getTodayStats();
                
                // Sin actividad en horario laboral
                if (($todayStats['quotes_today'] ?? 0) == 0 && $hour > 10) {
                    $alerts[] = [
                        'type' => 'info',
                        'title' => 'Sin Cotizaciones Hoy',
                        'message' => 'No se han creado cotizaciones en horario laboral',
                        'count' => 0,
                        'priority' => 'medium'
                    ];
                }
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("Error getting real-time alerts: " . $e->getMessage());
            return [];
        }
    }

    // NUEVO: Generar reporte automático de fin de día
    public function generateEndOfDayReport() {
        try {
            $todayStats = $this->model->getTodayStats();
            $generalStats = $this->model->getGeneralStats();
            $salesMetrics = $this->model->getSalesConversionMetrics();
            
            $report = [
                'date' => date('Y-m-d'),
                'summary' => [
                    'clients_created' => $todayStats['clients_today'] ?? 0,
                    'products_created' => $todayStats['products_today'] ?? 0,
                    'quotes_created' => $todayStats['quotes_today'] ?? 0,
                    'sales_closed' => $todayStats['sales_today'] ?? 0,
                    'revenue_today' => $todayStats['sales_value_today'] ?? 0,
                    'quotes_value_today' => $todayStats['quotes_value_today'] ?? 0
                ],
                'performance' => [
                    'conversion_rate_today' => $todayStats['quotes_today'] > 0 ? 
                        round((($todayStats['sales_today'] ?? 0) / $todayStats['quotes_today']) * 100, 2) : 0,
                    'avg_quote_value_today' => $todayStats['quotes_today'] > 0 ? 
                        round(($todayStats['quotes_value_today'] ?? 0) / $todayStats['quotes_today'], 2) : 0,
                    'avg_sale_value_today' => ($todayStats['sales_today'] ?? 0) > 0 ? 
                        round(($todayStats['sales_value_today'] ?? 0) / $todayStats['sales_today'], 2) : 0
                ],
                'context' => [
                    'total_clients' => $generalStats['total_clients'] ?? 0,
                    'total_products' => $generalStats['total_products'] ?? 0,
                    'total_quotes' => $generalStats['total_quotes'] ?? 0,
                    'total_sales' => $generalStats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0,
                    'overall_conversion_rate' => $salesMetrics['conversion_rate'] ?? 0
                ]
            ];
            
            return $report;
            
        } catch (Exception $e) {
            error_log("Error generating end of day report: " . $e->getMessage());
            return [];
        }
    }
}
?>