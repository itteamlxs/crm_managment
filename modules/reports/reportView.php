<?php
require_once dirname(dirname(__DIR__)) . '/config/constants.php';
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/utils.php';
require_once __DIR__ . '/reportController.php';

// Configurar headers de seguridad
Security::setHeaders();

// Inicializar sesión y verificar autenticación
$session = new Session();
if (!$session->isLoggedIn()) {
    Utils::redirect('/login.php');
}

// Procesar solicitudes
if (isset($_GET['action']) && $_GET['action'] !== 'view') {
    $controller = new ReportController();
    $controller->handleRequest();
}

// Obtener datos de la sesión
$reportData = $_SESSION['report_data'] ?? [];
$reportType = $_SESSION['report_type'] ?? 'dashboard';
$currentUser = [
    'name' => $session->getUserName(),
    'role' => $session->getUserRole()
];

// Limpiar datos de sesión después de obtenerlos
unset($_SESSION['report_data'], $_SESSION['report_type']);

// Obtener mensajes
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.0/index.min.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Navegación -->
    <?php require_once CORE_PATH . '/nav.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-chart-bar text-blue-600"></i>
                        Centro de Reportes
                    </h1>
                    <p class="text-gray-600">Análisis y estadísticas del sistema CRM</p>
                </div>
                
                <!-- Selector de Reporte -->
                <div class="mt-4 md:mt-0">
                    <select id="reportSelector" class="form-select rounded-md border-gray-300 shadow-sm">
                        <option value="dashboard" <?php echo $reportType === 'dashboard' ? 'selected' : ''; ?>>Dashboard General</option>
                        <option value="quotes" <?php echo $reportType === 'quotes' ? 'selected' : ''; ?>>Reporte de Cotizaciones</option>
                        <option value="clients" <?php echo $reportType === 'clients' ? 'selected' : ''; ?>>Reporte de Clientes</option>
                        <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>Reporte de Productos</option>
                        <option value="inventory" <?php echo $reportType === 'inventory' ? 'selected' : ''; ?>>Reporte de Inventario</option>
                        <option value="financial" <?php echo $reportType === 'financial' ? 'selected' : ''; ?>>Reporte Financiero</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo Security::escape($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo Security::escape($success); ?>
            </div>
        <?php endif; ?>

        <!-- Filtros de Fecha (para reportes que los necesiten) -->
        <?php if (in_array($reportType, ['quotes', 'products', 'financial'])): ?>
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-filter text-gray-600"></i>
                    Filtros de Fecha
                </h3>
                
                <form method="GET" action="reportController.php" class="flex flex-wrap items-end gap-4">
                    <input type="hidden" name="action" value="<?php echo Security::escape($reportType); ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Desde</label>
                        <input type="date" name="date_from" 
                               value="<?php echo Security::escape($reportData['date_from'] ?? ''); ?>"
                               class="form-input rounded-md border-gray-300 shadow-sm">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Hasta</label>
                        <input type="date" name="date_to" 
                               value="<?php echo Security::escape($reportData['date_to'] ?? ''); ?>"
                               class="form-input rounded-md border-gray-300 shadow-sm">
                    </div>
                    
                    <div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                            <i class="fas fa-search mr-2"></i>Filtrar
                        </button>
                    </div>
                    
                    <div>
                        <a href="reportView.php?action=<?php echo Security::escape($reportType); ?>" 
                           class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                            <i class="fas fa-times mr-2"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Contenido del Reporte -->
        <div id="reportContent">
            <?php
            switch ($reportType) {
                case 'dashboard':
                    include __DIR__ . '/views/dashboard.php';
                    break;
                case 'quotes':
                    include __DIR__ . '/views/quotes_report.php';
                    break;
                case 'clients':
                    include __DIR__ . '/views/clients_report.php';
                    break;
                case 'products':
                    include __DIR__ . '/views/products_report.php';
                    break;
                case 'inventory':
                    include __DIR__ . '/views/inventory_report.php';
                    break;
                case 'financial':
                    include __DIR__ . '/views/financial_report.php';
                    break;
                default:
                    echo '<div class="bg-white rounded-lg shadow p-6">';
                    echo '<p class="text-gray-600">Seleccione un tipo de reporte para ver los datos.</p>';
                    echo '</div>';
            }
            ?>
        </div>

        <!-- Modal de Exportación -->
        <div id="exportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">Exportar Reporte</h3>
                    </div>
                    
                    <form id="exportForm" method="POST" action="reportController.php?action=export">
                        <input type="hidden" name="csrf_token" value="<?php echo $session->getCsrfToken(); ?>">
                        <input type="hidden" name="report_type" id="exportReportType" value="<?php echo Security::escape($reportType); ?>">
                        <input type="hidden" name="date_from" value="<?php echo Security::escape($reportData['date_from'] ?? ''); ?>">
                        <input type="hidden" name="date_to" value="<?php echo Security::escape($reportData['date_to'] ?? ''); ?>">
                        
                        <div class="px-6 py-4">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Formato de Exportación</label>
                                <select name="format" class="form-select w-full rounded-md border-gray-300">
                                    <option value="csv">CSV (Excel)</option>
                                    <option value="pdf" disabled>PDF (Próximamente)</option>
                                    <option value="excel" disabled>Excel (Próximamente)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-3">
                            <button type="button" onclick="closeExportModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i class="fas fa-download mr-2"></i>Exportar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Variables globales
        const reportData = <?php echo json_encode($reportData); ?>;
        const currentReportType = '<?php echo Security::escape($reportType); ?>';
        
        // Cambiar tipo de reporte
        document.getElementById('reportSelector').addEventListener('change', function() {
            const reportType = this.value;
            window.location.href = `reportView.php?action=${reportType}`;
        });

        // Funciones del modal de exportación
        function showExportModal(reportType = null) {
            const modal = document.getElementById('exportModal');
            const reportTypeInput = document.getElementById('exportReportType');
            
            if (reportType) {
                reportTypeInput.value = reportType;
            }
            
            modal.classList.remove('hidden');
        }

        function closeExportModal() {
            document.getElementById('exportModal').classList.add('hidden');
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('exportModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExportModal();
            }
        });

        // Utilidades para formatear números
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-ES', {
                style: 'currency',
                currency: '<?php echo DEFAULT_CURRENCY; ?>'
            }).format(amount);
        }

        function formatNumber(number) {
            return new Intl.NumberFormat('es-ES').format(number);
        }

        function formatPercentage(percentage) {
            return new Intl.NumberFormat('es-ES', {
                style: 'percent',
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }).format(percentage / 100);
        }

        // Función para crear gráficos
        function createChart(canvasId, type, data, options = {}) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            return new Chart(ctx, {
                type: type,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    ...options
                }
            });
        }

        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Aquí se pueden agregar inicializaciones específicas por tipo de reporte
            console.log('Reporte cargado:', currentReportType);
        });
    </script>
</body>
</html>