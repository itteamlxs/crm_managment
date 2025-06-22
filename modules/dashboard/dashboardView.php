<?php
// Vista principal del Dashboard CRM - VERSIÓN COMPLETA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/dashboard/dashboardController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new DashboardController();
} catch (Exception $e) {
    die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar autenticación
if (!$controller->isAuthenticated()) {
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
    exit;
}

// Procesar acciones AJAX
if (isset($_GET['ajax']) && $_GET['action']) {
    header('Content-Type: application/json');
    $result = $controller->refreshData($_GET['action']);
    echo json_encode($result);
    exit;
}

// Procesar exportación
if (isset($_GET['export'])) {
    $format = $_GET['format'] ?? 'csv';
    $controller->exportStats($format);
    exit;
}

// Obtener datos del dashboard
$dashboardData = $controller->getDashboardData();
if (isset($dashboardData['error'])) {
    $error = $dashboardData['error'];
    $dashboardData = [];
}

$currentUser = $dashboardData['current_user'] ?? [];
$generalStats = $dashboardData['general_stats'] ?? [];
$monthlyStats = $dashboardData['monthly_stats'] ?? [];
$recentActivity = $dashboardData['recent_activity'] ?? [];
$topClients = $dashboardData['top_clients'] ?? [];
$topProducts = $dashboardData['top_products'] ?? [];
$expiringQuotes = $dashboardData['expiring_quotes'] ?? [];
$companyInfo = $dashboardData['company_info'] ?? [];
$alerts = $dashboardData['alerts'] ?? [];

// Función helper para obtener el nombre del estado de cotización
function getQuoteStatusName($status) {
    switch ($status) {
        case QUOTE_STATUS_DRAFT: return 'Borrador';
        case QUOTE_STATUS_SENT: return 'Enviada';
        case QUOTE_STATUS_APPROVED: return 'Aprobada';
        case QUOTE_STATUS_REJECTED: return 'Rechazada';
        case QUOTE_STATUS_EXPIRED: return 'Vencida';
        case QUOTE_STATUS_CANCELLED: return 'Cancelada';
        default: return 'Desconocido';
    }
}

// Función helper para obtener clase CSS del estado
function getQuoteStatusClass($status) {
    switch ($status) {
        case QUOTE_STATUS_DRAFT: return 'bg-gray-100 text-gray-800';
        case QUOTE_STATUS_SENT: return 'bg-blue-100 text-blue-800';
        case QUOTE_STATUS_APPROVED: return 'bg-green-100 text-green-800';
        case QUOTE_STATUS_REJECTED: return 'bg-red-100 text-red-800';
        case QUOTE_STATUS_EXPIRED: return 'bg-yellow-100 text-yellow-800';
        case QUOTE_STATUS_CANCELLED: return 'bg-gray-100 text-gray-600';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo Security::escape($companyInfo['company_name'] ?? 'CRM Sistema'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
        }
        .activity-item {
            transition: background-color 0.2s ease;
        }
        .activity-item:hover {
            background-color: #f8fafc;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .alert-slide-in {
            animation: slideInRight 0.5s ease-out;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .pulse-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php require_once dirname(__DIR__, 2) . '/core/nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        
        <!-- Header del Dashboard -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-4 lg:mb-0">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        📊 Dashboard
                        <?php if (!empty($companyInfo['company_name'])): ?>
                            - <?php echo Security::escape($companyInfo['company_name']); ?>
                        <?php endif; ?>
                    </h1>
                    <?php if (!empty($companyInfo['company_slogan'])): ?>
                        <p class="text-lg text-gray-600"><?php echo Security::escape($companyInfo['company_slogan']); ?></p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500">
                        Bienvenido, <?php echo Security::escape($currentUser['name'] ?? 'Usuario'); ?> 
                        • <?php echo date('l, d F Y'); ?>
                        • <span id="current-time"><?php echo date('H:i:s'); ?></span>
                    </p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="refreshDashboard()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        🔄 Actualizar
                    </button>
                    <button onclick="exportStats()" 
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        📊 Exportar
                    </button>
                </div>
            </div>
        </div>

        <!-- Alertas del Sistema -->
        <?php if (!empty($alerts)): ?>
            <div class="mb-6">
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert-slide-in mb-3 p-4 rounded-lg border-l-4 <?php 
                        switch($alert['type']) {
                            case 'error': echo 'bg-red-50 border-red-500 text-red-700'; break;
                            case 'warning': echo 'bg-yellow-50 border-yellow-500 text-yellow-700'; break;
                            case 'info': echo 'bg-blue-50 border-blue-500 text-blue-700'; break;
                            default: echo 'bg-gray-50 border-gray-500 text-gray-700';
                        }
                    ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-xl mr-3"><?php echo $alert['icon']; ?></span>
                                <div>
                                    <h4 class="font-semibold"><?php echo Security::escape($alert['title']); ?></h4>
                                    <p class="text-sm"><?php echo Security::escape($alert['message']); ?></p>
                                </div>
                            </div>
                            <?php if (isset($alert['action_url'])): ?>
                                <a href="<?php echo Security::escape($alert['action_url']); ?>" 
                                   class="bg-white px-3 py-1 rounded border hover:bg-gray-50 text-sm font-medium">
                                    <?php echo Security::escape($alert['action_text']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas Principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Clientes -->
            <div class="dashboard-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-1">Clientes</h3>
                        <div class="stat-number text-blue-600"><?php echo number_format($generalStats['total_clients'] ?? 0); ?></div>
                        <p class="text-sm text-gray-500">
                            <?php echo number_format($generalStats['active_clients'] ?? 0); ?> activos
                        </p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <span class="text-2xl">👥</span>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/modules/clients/clientList.php" 
                       class="text-blue-600 text-sm font-medium hover:text-blue-700">
                        Ver todos →
                    </a>
                </div>
            </div>

            <!-- Productos -->
            <div class="dashboard-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-1">Productos</h3>
                        <div class="stat-number text-green-600"><?php echo number_format($generalStats['total_products'] ?? 0); ?></div>
                        <p class="text-sm text-gray-500">
                            <?php echo number_format($generalStats['active_products'] ?? 0); ?> activos
                            <?php if (($generalStats['low_stock_products'] ?? 0) > 0): ?>
                                • <span class="text-red-600 font-medium"><?php echo $generalStats['low_stock_products']; ?> stock bajo</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <span class="text-2xl">📦</span>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/modules/products/productList.php" 
                       class="text-green-600 text-sm font-medium hover:text-green-700">
                        Ver todos →
                    </a>
                </div>
            </div>

            <!-- Cotizaciones -->
            <div class="dashboard-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-1">Cotizaciones</h3>
                        <div class="stat-number text-purple-600"><?php echo number_format($generalStats['total_quotes'] ?? 0); ?></div>
                        <p class="text-sm text-gray-500">
                            $<?php echo number_format($generalStats['total_quotes_value'] ?? 0, 2); ?> total
                        </p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <span class="text-2xl">📄</span>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteList.php" 
                       class="text-purple-600 text-sm font-medium hover:text-purple-700">
                        Ver todas →
                    </a>
                </div>
            </div>

            <!-- Usuarios (solo admins) -->
            <?php if ($currentUser['is_admin'] ?? false): ?>
                <div class="dashboard-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-600 mb-1">Usuarios</h3>
                            <div class="stat-number text-gray-600"><?php echo number_format($generalStats['total_users'] ?? 0); ?></div>
                            <p class="text-sm text-gray-500">
                                <?php echo number_format($generalStats['active_users'] ?? 0); ?> activos
                            </p>
                        </div>
                        <div class="bg-gray-100 p-3 rounded-lg">
                            <span class="text-2xl">👤</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="<?php echo BASE_URL; ?>/modules/users/userManagement.php" 
                           class="text-gray-600 text-sm font-medium hover:text-gray-700">
                            Gestionar →
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Accesos Rápidos para Vendedores -->
                <div class="dashboard-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="text-center">
                        <h3 class="text-sm font-medium text-gray-600 mb-4">Acciones Rápidas</h3>
                        <div class="space-y-2">
                            <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteForm.php" 
                               class="block bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
                                ➕ Nueva Cotización
                            </a>
                            <a href="<?php echo BASE_URL; ?>/modules/clients/clientForm.php" 
                               class="block bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                                👤 Nuevo Cliente
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Acciones Rápidas -->
        <div class="mb-8 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">🚀 Acciones Rápidas</h3>
                <p class="text-sm text-gray-500">Accesos directos a las funciones más utilizadas</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    <!-- Nueva Cotización -->
                    <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteForm.php" 
                       class="dashboard-card group p-4 bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg hover:from-blue-100 hover:to-blue-200 transition-all">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                                    <span class="text-white text-xl">📄</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-semibold text-blue-900">Nueva Cotización</h4>
                                <p class="text-xs text-blue-600">Crear cotización</p>
                            </div>
                        </div>
                    </a>

                    <!-- Nuevo Cliente -->
                    <a href="<?php echo BASE_URL; ?>/modules/clients/clientForm.php" 
                       class="dashboard-card group p-4 bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg hover:from-green-100 hover:to-green-200 transition-all">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center group-hover:bg-green-600 transition-colors">
                                    <span class="text-white text-xl">👤</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-semibold text-green-900">Nuevo Cliente</h4>
                                <p class="text-xs text-green-600">Agregar cliente</p>
                            </div>
                        </div>
                    </a>

                    <!-- Nuevo Producto -->
                    <a href="<?php echo BASE_URL; ?>/modules/products/productForm.php" 
                       class="dashboard-card group p-4 bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg hover:from-purple-100 hover:to-purple-200 transition-all">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center group-hover:bg-purple-600 transition-colors">
                                    <span class="text-white text-xl">📦</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-semibold text-purple-900">Nuevo Producto</h4>
                                <p class="text-xs text-purple-600">Agregar producto</p>
                            </div>
                        </div>
                    </a>

                    <!-- Ver Reportes -->
                    <a href="<?php echo BASE_URL; ?>/modules/reports/reportView.php" 
                       class="dashboard-card group p-4 bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg hover:from-orange-100 hover:to-orange-200 transition-all">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center group-hover:bg-orange-600 transition-colors">
                                    <span class="text-white text-xl">📊</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-semibold text-orange-900">Ver Reportes</h4>
                                <p class="text-xs text-orange-600">Análisis y datos</p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Segunda fila de acciones rápidas -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    
                    <!-- Buscar Cliente -->
                    <div class="dashboard-card group p-4 bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-200 rounded-lg">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-gray-500 rounded-lg flex items-center justify-center">
                                <span class="text-white text-lg">🔍</span>
                            </div>
                            <h4 class="ml-3 text-sm font-semibold text-gray-900">Buscar Cliente</h4>
                        </div>
                        <div class="flex">
                            <input type="text" 
                                   id="quick-search-client" 
                                   placeholder="Nombre o email..." 
                                   class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button onclick="quickSearchClient()" 
                                    class="px-4 py-2 bg-gray-600 text-white rounded-r-md hover:bg-gray-700 text-sm">
                                Buscar
                            </button>
                        </div>
                    </div>

                    <!-- Acciones de Admin -->
                    <?php if ($currentUser['is_admin'] ?? false): ?>
                        <div class="dashboard-card group p-4 bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center">
                                    <span class="text-white text-lg">⚙️</span>
                                </div>
                                <h4 class="ml-3 text-sm font-semibold text-red-900">Panel Admin</h4>
                            </div>
                            <div class="space-y-2">
                                <a href="<?php echo BASE_URL; ?>/modules/settings/settingsView.php" 
                                   class="block text-center bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700">
                                    Configuración
                                </a>
                                <a href="<?php echo BASE_URL; ?>/modules/users/userManagement.php" 
                                   class="block text-center bg-gray-600 text-white px-3 py-1 rounded text-xs hover:bg-gray-700">
                                    Usuarios
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Widget de ayuda para vendedores -->
                        <div class="dashboard-card group p-4 bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 rounded-lg">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center">
                                    <span class="text-white text-lg">💡</span>
                                </div>
                                <h4 class="ml-3 text-sm font-semibold text-indigo-900">Ayuda Rápida</h4>
                            </div>
                            <div class="text-xs text-indigo-700 space-y-1">
                                <p>• <strong>Ctrl+Alt+Q:</strong> Nueva cotización</p>
                                <p>• <strong>Ctrl+Alt+C:</strong> Nuevo cliente</p>
                                <p>• <strong>F5:</strong> Actualizar dashboard</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Exportar Datos -->
                    <div class="dashboard-card group p-4 bg-gradient-to-br from-teal-50 to-teal-100 border border-teal-200 rounded-lg">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-teal-500 rounded-lg flex items-center justify-center">
                                <span class="text-white text-lg">💾</span>
                            </div>
                            <h4 class="ml-3 text-sm font-semibold text-teal-900">Exportar</h4>
                        </div>
                        <div class="space-y-2">
                            <button onclick="exportStats('csv')" 
                                    class="w-full bg-teal-600 text-white px-3 py-1 rounded text-xs hover:bg-teal-700">
                                📊 Estadísticas CSV
                            </button>
                            <button onclick="exportStats('json')" 
                                    class="w-full bg-gray-600 text-white px-3 py-1 rounded text-xs hover:bg-gray-700">
                                📄 Datos JSON
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos y Análisis -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Gráfico de Cotizaciones por Estado -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Cotizaciones por Estado</h3>
                <div class="chart-container">
                    <canvas id="quotesStatusChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Actividad Mensual -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Actividad Mensual</h3>
                <div class="chart-container">
                    <canvas id="monthlyActivityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Actividad Reciente -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Actividad Reciente</h3>
                        <span class="pulse-dot w-3 h-3 bg-green-500 rounded-full"></span>
                    </div>
                </div>
                <div class="p-6">
                    <?php if (empty($recentActivity)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="text-4xl mb-4 block">📝</span>
                            <p>No hay actividad reciente</p>
                            <p class="text-sm mt-2">Las actividades aparecerán aquí conforme uses el sistema</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach (array_slice($recentActivity, 0, 8) as $activity): ?>
                                <div class="activity-item flex items-center p-3 rounded-lg">
                                    <div class="flex-shrink-0 mr-4">
                                        <?php
                                        switch ($activity['type']) {
                                            case 'client':
                                                echo '<div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><span class="text-blue-600">👥</span></div>';
                                                break;
                                            case 'quote':
                                                echo '<div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center"><span class="text-purple-600">📄</span></div>';
                                                break;
                                            case 'product':
                                                echo '<div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center"><span class="text-green-600">📦</span></div>';
                                                break;
                                            default:
                                                echo '<div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center"><span class="text-gray-600">📋</span></div>';
                                        }
                                        ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <?php if ($activity['type'] === 'client'): ?>
                                            <p class="text-sm font-medium text-gray-900">
                                                Nuevo cliente: <?php echo Security::escape($activity['name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500"><?php echo Security::escape($activity['email']); ?></p>
                                        <?php elseif ($activity['type'] === 'quote'): ?>
                                            <p class="text-sm font-medium text-gray-900">
                                                Cotización: <?php echo Security::escape($activity['quote_number']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                Cliente: <?php echo Security::escape($activity['client_name'] ?? 'N/A'); ?> 
                                                • $<?php echo number_format($activity['total_amount'], 2); ?>
                                            </p>
                                        <?php elseif ($activity['type'] === 'product'): ?>
                                            <p class="text-sm font-medium text-gray-900">
                                                Producto: <?php echo Security::escape($activity['name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                $<?php echo number_format($activity['base_price'], 2); ?>
                                                <?php if ($activity['category_name']): ?>
                                                    • <?php echo Security::escape($activity['category_name']); ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-shrink-0 text-sm text-gray-400">
                                        <?php echo Utils::formatDateDisplay($activity['created_at']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cotizaciones por Vencer -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">Cotizaciones por Vencer</h3>
                    <p class="text-sm text-gray-500">Próximos 7 días</p>
                </div>
                <div class="p-6">
                    <?php if (empty($expiringQuotes)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="text-4xl mb-4 block">✅</span>
                            <p>Sin cotizaciones por vencer</p>
                            <p class="text-sm mt-2">¡Excelente trabajo!</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($expiringQuotes as $quote): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-medium text-gray-900">
                                            <?php echo Security::escape($quote['quote_number']); ?>
                                        </h4>
                                        <span class="text-sm px-2 py-1 rounded-full <?php echo getQuoteStatusClass($quote['status']); ?>">
                                            <?php echo getQuoteStatusName($quote['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2">
                                        <?php echo Security::escape($quote['client_name'] ?? 'Cliente eliminado'); ?>
                                    </p>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-medium text-green-600">
                                            $<?php echo number_format($quote['total_amount'], 2); ?>
                                        </span>
                                        <span class="text-red-600 font-medium">
                                            <?php
                                            $days = $quote['days_until_expiry'];
                                            if ($days < 0) {
                                                echo 'Vencida';
                                            } elseif ($days == 0) {
                                                echo 'Vence hoy';
                                            } else {
                                                echo $days . ' día' . ($days > 1 ? 's' : '');
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteList.php" 
                               class="text-blue-600 text-sm font-medium hover:text-blue-700">
                                Ver todas las cotizaciones →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Clientes y Productos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Top Clientes -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">Top Clientes</h3>
                    <p class="text-sm text-gray-500">Por valor de cotizaciones</p>
                </div>
                <div class="p-6">
                    <?php if (empty($topClients)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="text-4xl mb-4 block">🏆</span>
                            <p>Sin datos de clientes</p>
                            <p class="text-sm mt-2">Crea cotizaciones para ver estadísticas</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($topClients as $index => $client): ?>
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 text-center">
                                        <?php if ($index === 0): ?>
                                            <span class="text-yellow-500 text-lg">🥇</span>
                                        <?php elseif ($index === 1): ?>
                                            <span class="text-gray-400 text-lg">🥈</span>
                                        <?php elseif ($index === 2): ?>
                                            <span class="text-orange-600 text-lg">🥉</span>
                                        <?php else: ?>
                                            <span class="text-gray-600 font-medium"><?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 ml-3">
                                        <h4 class="font-medium text-gray-900">
                                            <?php echo Security::escape($client['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-500">
                                            <?php echo $client['total_quotes']; ?> cotización(es)
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-green-600">
                                            $<?php echo number_format($client['total_value'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Productos -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">Top Productos</h3>
                    <p class="text-sm text-gray-500">Más cotizados</p>
                </div>
                <div class="p-6">
                    <?php if (empty($topProducts)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="text-4xl mb-4 block">📈</span>
                            <p>Sin datos de productos</p>
                            <p class="text-sm mt-2">Crea cotizaciones para ver estadísticas</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($topProducts as $index => $product): ?>
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 text-center">
                                        <?php if ($index === 0): ?>
                                            <span class="text-yellow-500 text-lg">🥇</span>
                                        <?php elseif ($index === 1): ?>
                                            <span class="text-gray-400 text-lg">🥈</span>
                                        <?php elseif ($index === 2): ?>
                                            <span class="text-orange-600 text-lg">🥉</span>
                                        <?php else: ?>
                                            <span class="text-gray-600 font-medium"><?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 ml-3">
                                        <h4 class="font-medium text-gray-900">
                                            <?php echo Security::escape($product['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-500">
                                            <?php echo $product['times_quoted']; ?> vez(ces) cotizado
                                            <?php if ($product['category_name']): ?>
                                                • <?php echo Security::escape($product['category_name']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-blue-600">
                                            $<?php echo number_format($product['base_price'], 2); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            Cant: <?php echo number_format($product['total_quantity']); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <script>
        // Reloj en tiempo real
        function updateClock() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
        }
        setInterval(updateClock, 1000);

        // Datos para los gráficos
        const quotesStatusData = <?php echo json_encode($generalStats['quotes_by_status'] ?? []); ?>;
        const monthlyData = <?php echo json_encode($monthlyStats); ?>;

        // Gráfico de Cotizaciones por Estado
        if (document.getElementById('quotesStatusChart')) {
            const ctx1 = document.getElementById('quotesStatusChart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: [
                        'Borrador', 'Enviada', 'Aprobada', 'Rechazada', 'Vencida', 'Cancelada'
                    ],
                    datasets: [{
                        data: [
                            quotesStatusData[<?php echo QUOTE_STATUS_DRAFT; ?>] || 0,
                            quotesStatusData[<?php echo QUOTE_STATUS_SENT; ?>] || 0,
                            quotesStatusData[<?php echo QUOTE_STATUS_APPROVED; ?>] || 0,
                            quotesStatusData[<?php echo QUOTE_STATUS_REJECTED; ?>] || 0,
                            quotesStatusData[<?php echo QUOTE_STATUS_EXPIRED; ?>] || 0,
                            quotesStatusData[<?php echo QUOTE_STATUS_CANCELLED; ?>] || 0
                        ],
                        backgroundColor: [
                            '#9CA3AF', '#3B82F6', '#10B981', '#EF4444', '#F59E0B', '#6B7280'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Gráfico de Actividad Mensual
        if (document.getElementById('monthlyActivityChart')) {
            const ctx2 = document.getElementById('monthlyActivityChart').getContext('2d');
            
            // Preparar datos mensuales
            const months = [];
            const clientsData = [];
            const quotesData = [];
            
            if (monthlyData.clients_monthly) {
                monthlyData.clients_monthly.forEach(item => {
                    months.push(item.month);
                    clientsData.push(item.count);
                });
            }
            
            if (monthlyData.quotes_monthly) {
                monthlyData.quotes_monthly.forEach(item => {
                    if (!months.includes(item.month)) {
                        months.push(item.month);
                    }
                    quotesData.push(item.count);
                });
            }
            
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Clientes',
                        data: clientsData,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Cotizaciones',
                        data: quotesData,
                        borderColor: '#8B5CF6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Función para refrescar dashboard
        function refreshDashboard() {
            const button = event.target;
            button.disabled = true;
            button.innerHTML = '🔄 Actualizando...';
            
            // Simular actualización y recargar página
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // Función para exportar estadísticas
        function exportStats(format = null) {
            if (!format) {
                format = confirm('¿Desea exportar en formato CSV? \n\nAceptar = CSV\nCancelar = JSON') ? 'csv' : 'json';
            }
            window.location.href = `?export=1&format=${format}`;
        }

        // Función para búsqueda rápida de clientes
        function quickSearchClient() {
            const searchTerm = document.getElementById('quick-search-client').value.trim();
            if (searchTerm) {
                window.location.href = `<?php echo BASE_URL; ?>/modules/clients/clientList.php?search=${encodeURIComponent(searchTerm)}`;
            } else {
                alert('Por favor, ingrese un término de búsqueda');
            }
        }

        // Atajos de teclado para acciones rápidas
        document.addEventListener('keydown', function(e) {
            // Ctrl+Alt+Q: Nueva cotización
            if (e.ctrlKey && e.altKey && e.key === 'q') {
                e.preventDefault();
                window.location.href = '<?php echo BASE_URL; ?>/modules/quotes/quoteForm.php';
            }
            
            // Ctrl+Alt+C: Nuevo cliente
            if (e.ctrlKey && e.altKey && e.key === 'c') {
                e.preventDefault();
                window.location.href = '<?php echo BASE_URL; ?>/modules/clients/clientForm.php';
            }
            
            // Enter en el campo de búsqueda rápida
            if (e.target.id === 'quick-search-client' && e.key === 'Enter') {
                e.preventDefault();
                quickSearchClient();
            }
        });

        // Auto-refresh cada 5 minutos
        setTimeout(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>