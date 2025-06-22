<?php
require_once dirname(dirname(__DIR__)) . '/config/constants.php';
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/utils.php';
require_once __DIR__ . '/reportModel.php';

class ReportController {
    private $reportModel;
    private $session;

    public function __construct() {
        $this->session = new Session();
        $this->reportModel = new ReportModel();
        
        // Verificar autenticación
        if (!$this->session->isLoggedIn()) {
            Utils::redirect('/login.php');
        }
    }

    // ========================================
    // PROCESAR SOLICITUDES
    // ========================================

    public function handleRequest() {
        try {
            $action = $_GET['action'] ?? 'dashboard';
            
            switch ($action) {
                case 'dashboard':
                    $this->showDashboard();
                    break;
                    
                case 'quotes':
                    $this->showQuotesReport();
                    break;
                    
                case 'clients':
                    $this->showClientsReport();
                    break;
                    
                case 'products':
                    $this->showProductsReport();
                    break;
                    
                case 'inventory':
                    $this->showInventoryReport();
                    break;
                    
                case 'financial':
                    $this->showFinancialReport();
                    break;
                    
                case 'export':
                    $this->handleExport();
                    break;
                    
                case 'ajax':
                    $this->handleAjaxRequest();
                    break;
                    
                default:
                    throw new Exception('Acción no válida');
            }
            
        } catch (Exception $e) {
            error_log("Error in ReportController: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            Utils::redirect('/modules/reports/reportView.php');
        }
    }

    // ========================================
    // VISTAS DE REPORTES
    // ========================================

    private function showDashboard() {
        // Recopilar datos para el dashboard de reportes
        $data = [
            'quotes_summary' => $this->reportModel->getQuotesSummary(),
            'clients_summary' => $this->reportModel->getClientsSummary(),
            'financial_summary' => $this->reportModel->getFinancialSummary(),
            'low_stock_count' => count($this->reportModel->getLowStockProducts()),
            'sales_by_month' => $this->reportModel->getSalesbyMonth(6),
            'quotes_by_status' => $this->reportModel->getQuotesByStatus(),
            'top_products' => $this->reportModel->getTopQuotedProducts(10),
            'date_range' => $this->reportModel->getDateRange()
        ];

        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'dashboard';
    }

    private function showQuotesReport() {
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        // Validar fechas
        $errors = $this->reportModel->validateDateParams($dateFrom, $dateTo);
        if (!empty($errors)) {
            $_SESSION['error'] = implode(', ', $errors);
            Utils::redirect('/modules/reports/reportView.php');
            return;
        }

        $data = [
            'quotes_summary' => $this->reportModel->getQuotesSummary($dateFrom, $dateTo),
            'quotes_by_status' => $this->reportModel->getQuotesByStatus($dateFrom, $dateTo),
            'sales_by_month' => $this->reportModel->getSalesbyMonth(12),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_range' => $this->reportModel->getDateRange()
        ];

        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'quotes';
    }

    private function showClientsReport() {
        $data = [
            'clients_summary' => $this->reportModel->getClientsSummary(),
            'clients_by_activity' => $this->reportModel->getClientsByActivity()
        ];

        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'clients';
    }

    private function showProductsReport() {
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        $data = [
            'top_products' => $this->reportModel->getTopQuotedProducts(50, $dateFrom, $dateTo),
            'price_analysis' => $this->reportModel->getProductPriceAnalysis(),
            'revenue_by_category' => $this->reportModel->getRevenueByCategory($dateFrom, $dateTo),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_range' => $this->reportModel->getDateRange()
        ];

        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'products';
    }

    private function showInventoryReport() {
        $threshold = (int)($_GET['threshold'] ?? LOW_STOCK_THRESHOLD);
        
        $data = [
            'inventory_report' => $this->reportModel->getInventoryReport(),
            'low_stock_products' => $this->reportModel->getLowStockProducts($threshold),
            'threshold' => $threshold
        ];

        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'inventory';
    }

    private function showFinancialReport() {
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        // Validar fechas
        $errors = $this->reportModel->validateDateParams($dateFrom, $dateTo);
        if (!empty($errors)) {
            $_SESSION['error'] = implode(', ', $errors);
            Utils::redirect('/modules/reports/reportView.php');
            return;
        }

        $data = [
            'financial_summary' => $this->reportModel->getFinancialSummary($dateFrom, $dateTo),
            'revenue_by_category' => $this->reportModel->getRevenueByCategory($dateFrom, $dateTo),
            'quotes_by_status' => $this->reportModel->getQuotesByStatus($dateFrom, $dateTo),
            'sales_by_month' => $this->reportModel->getSalesbyMonth(12),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_range' => $this->reportModel->getDateRange()
        ];

        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'financial';
    }

    // ========================================
    // MANEJO DE EXPORTACIONES
    // ========================================

    private function handleExport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Método no permitido');
        }

        // Validar token CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->session->validateCsrfToken($token)) {
            throw new Exception('Token de seguridad inválido');
        }

        $reportType = $_POST['report_type'] ?? '';
        $format = $_POST['format'] ?? 'csv';

        if (!in_array($format, EXPORT_FORMATS)) {
            throw new Exception('Formato de exportación no válido');
        }

        // Preparar parámetros
        $params = [
            'date_from' => $_POST['date_from'] ?? null,
            'date_to' => $_POST['date_to'] ?? null,
            'threshold' => $_POST['threshold'] ?? null
        ];

        switch ($format) {
            case 'csv':
                $this->exportToCSV($reportType, $params);
                break;
            case 'pdf':
                $this->exportToPDF($reportType, $params);
                break;
            case 'excel':
                $this->exportToExcel($reportType, $params);
                break;
            default:
                throw new Exception('Formato no implementado');
        }
    }

    private function exportToCSV($reportType, $params) {
        try {
            $export = $this->reportModel->exportToCSV($reportType, $params);
            
            // Generar nombre de archivo
            $filename = 'reporte_' . $reportType . '_' . date('Y-m-d_H-i-s') . '.csv';
            
            // Si no hay datos, crear archivo vacío con headers
            if (empty($export['data'])) {
                $export['data'] = [array_fill(0, count($export['headers']), 'Sin datos')];
            }

            Utils::generateCsv($export['data'], $filename, $export['headers']);
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al exportar: ' . $e->getMessage();
            Utils::redirect('/modules/reports/reportView.php');
        }
    }

    private function exportToPDF($reportType, $params) {
        // TODO: Implementar exportación a PDF
        $_SESSION['error'] = 'Exportación a PDF no implementada aún';
        Utils::redirect('/modules/reports/reportView.php');
    }

    private function exportToExcel($reportType, $params) {
        // TODO: Implementar exportación a Excel
        $_SESSION['error'] = 'Exportación a Excel no implementada aún';
        Utils::redirect('/modules/reports/reportView.php');
    }

    // ========================================
    // MANEJO DE AJAX
    // ========================================

    private function handleAjaxRequest() {
        header('Content-Type: application/json');
        
        try {
            $action = $_POST['ajax_action'] ?? '';
            
            switch ($action) {
                case 'get_chart_data':
                    $this->getChartData();
                    break;
                    
                case 'update_date_range':
                    $this->updateDateRange();
                    break;
                    
                default:
                    throw new Exception('Acción AJAX no válida');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    private function getChartData() {
        $chartType = $_POST['chart_type'] ?? '';
        $params = [
            'months' => (int)($_POST['months'] ?? 12),
            'date_from' => $_POST['date_from'] ?? null,
            'date_to' => $_POST['date_to'] ?? null
        ];

        $data = $this->reportModel->getChartData($chartType, $params);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    private function updateDateRange() {
        $dateFrom = $_POST['date_from'] ?? null;
        $dateTo = $_POST['date_to'] ?? null;
        
        // Validar fechas
        $errors = $this->reportModel->validateDateParams($dateFrom, $dateTo);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }

        // Obtener datos actualizados
        $data = [
            'quotes_summary' => $this->reportModel->getQuotesSummary($dateFrom, $dateTo),
            'quotes_by_status' => $this->reportModel->getQuotesByStatus($dateFrom, $dateTo),
            'financial_summary' => $this->reportModel->getFinancialSummary($dateFrom, $dateTo)
        ];

        echo json_encode(['success' => true, 'data' => $data]);
    }

    // ========================================
    // UTILIDADES
    // ========================================

    public function getCurrentUser() {
        return [
            'id' => $this->session->getUserId(),
            'name' => $this->session->getUserName(),
            'role' => $this->session->getUserRole()
        ];
    }

    public function hasPermission($permission) {
        // Los reportes están disponibles para todos los usuarios autenticados
        // pero se pueden agregar restricciones específicas aquí
        return $this->session->isLoggedIn();
    }
}

// ========================================
// PROCESAR SOLICITUD SI SE LLAMA DIRECTAMENTE
// ========================================

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    try {
        $controller = new ReportController();
        $controller->handleRequest();
    } catch (Exception $e) {
        error_log("Error in ReportController: " . $e->getMessage());
        $_SESSION['error'] = 'Error interno del servidor';
        Utils::redirect('/modules/reports/reportView.php');
    }
}
?>