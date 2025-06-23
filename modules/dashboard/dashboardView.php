<?php
// Vista principal del Dashboard CRM - VERSIÓN MEJORADA
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
                        Dashboard
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
                        Actualizar
                    </button>
                    <button onclick="exportStats()" 
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        Exportar
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            
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
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
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
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
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
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteList.php" 
                       class="text-purple-600 text-sm font-medium hover:text-purple-700">
                        Ver todas →
                    </a>
                </div>
            </div>

            <!-- NUEVO: Ventas (Cotizaciones Aprobadas) -->
            <div class="dashboard-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-1">Ventas</h3>
                        <div class="stat-number text-emerald-600"><?php echo number_format($generalStats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0); ?></div>
                        <p class="text-sm text-gray-500">
                            $<?php echo number_format($generalStats['quotes_value_by_status'][QUOTE_STATUS_APPROVED] ?? 0, 2); ?> vendido
                        </p>
                    </div>
                    <div class="bg-emerald-100 p-3 rounded-lg">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteList.php?status=<?php echo QUOTE_STATUS_APPROVED; ?>" 
                       class="text-emerald-600 text-sm font-medium hover:text-emerald-700">
                        Ver ventas →
                    </a>
                </div>
            </div>

            <!-- Usuarios o Acciones Rápidas -->
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
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
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
                                Nueva Cotización
                            </a>
                            <a href="<?php echo BASE_URL; ?>/modules/clients/clientForm.php" 
                               class="block bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                                Nuevo Cliente
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Acciones Rápidas -->
        <div class="mb-8 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Acciones Rápidas</h3>
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
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
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
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
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
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
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
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    
                    <!-- Acciones de Admin -->
                    <?php if ($currentUser['is_admin'] ?? false): ?>
                        <div class="dashboard-card group p-4 bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
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
                    <?php endif; ?>

                    <!-- Widget de ayuda -->
                    <div class="dashboard-card group p-4 bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 rounded-lg">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <h4 class="ml-3 text-sm font-semibold text-indigo-900"><?php echo ($currentUser['is_admin'] ?? false) ? 'Atajos de Teclado' : 'Ayuda Rápida'; ?></h4>
                        </div>
                        <div class="text-xs text-indigo-700 space-y-1">
                            <p><strong>Ctrl+Alt+Q:</strong> Nueva cotización</p>
                            <p><strong>Ctrl+Alt+C:</strong> Nuevo cliente</p>
                            <p><strong>F5:</strong> Actualizar dashboard</p>
                            <?php if ($currentUser['is_admin'] ?? false): ?>
                                <p><strong>Ctrl+Alt+S:</strong> Configuración</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos de Análisis del Día -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Gráfico de Cotizaciones de Hoy -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Actividad de Hoy</h3>
                <div class="chart-container">
                    <canvas id="todayActivityChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Ventas vs Cotizaciones del Día -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Conversión de Ventas Hoy</h3>
                <div class="chart-container">
                    <canvas id="salesConversionChart"></canvas>
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
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
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
                                                echo '<div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>';
                                                break;
                                            case 'quote':
                                                echo '<div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>';
                                                break;
                                            case 'product':
                                                echo '<div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg></div>';
                                                break;
                                            default:
                                                echo '<div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>';
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
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>Sin cotizaciones por vencer</p>
                            <p class="text-sm mt-2">Excelente trabajo!</p>
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
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <p>Sin datos de clientes</p>
                            <p class="text-sm mt-2">Crea cotizaciones para ver estadísticas</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($topClients as $index => $client): ?>
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 text-center">
                                        <span class="<?php 
                                            if ($index === 0) echo 'text-yellow-500 text-lg font-bold';
                                            elseif ($index === 1) echo 'text-gray-400 text-lg font-bold'; 
                                            elseif ($index === 2) echo 'text-orange-600 text-lg font-bold';
                                            else echo 'text-gray-600 font-medium';
                                        ?>">
                                            <?php echo $index + 1; ?>
                                        </span>
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
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <p>Sin datos de productos</p>
                            <p class="text-sm mt-2">Crea cotizaciones para ver estadísticas</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($topProducts as $index => $product): ?>
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 text-center">
                                        <span class="<?php 
                                            if ($index === 0) echo 'text-yellow-500 text-lg font-bold';
                                            elseif ($index === 1) echo 'text-gray-400 text-lg font-bold'; 
                                            elseif ($index === 2) echo 'text-orange-600 text-lg font-bold';
                                            else echo 'text-gray-600 font-medium';
                                        ?>">
                                            <?php echo $index + 1; ?>
                                        </span>
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
        
    </div>

    <script>
        // Reloj en tiempo real
        function updateClock() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
        }
        setInterval(updateClock, 1000);

        // Datos para los gráficos del día
        const quotesStatusData = <?php echo json_encode($generalStats['quotes_by_status'] ?? []); ?>;
        const todayDate = new Date().toISOString().split('T')[0];

        // Gráfico de Actividad de Hoy
        if (document.getElementById('todayActivityChart')) {
            const ctx1 = document.getElementById('todayActivityChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: ['Clientes', 'Productos', 'Cotizaciones', 'Ventas'],
                    datasets: [{
                        label: 'Creados Hoy',
                        data: [
                            <?php 
                            // Aquí deberías implementar queries para obtener datos del día actual
                            // Por ahora uso datos simulados
                            echo "5, 3, 8, 2"; 
                            ?> 
                        ],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)', 
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(5, 150, 105, 0.8)'
                        ],
                        borderColor: [
                            'rgb(59, 130, 246)',
                            'rgb(16, 185, 129)',
                            'rgb(139, 92, 246)', 
                            'rgb(5, 150, 105)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Conversión de Ventas
        if (document.getElementById('salesConversionChart')) {
            const ctx2 = document.getElementById('salesConversionChart').getContext('2d');
            const totalQuotes = <?php echo $generalStats['total_quotes'] ?? 0; ?>;
            const totalSales = <?php echo $generalStats['quotes_by_status'][QUOTE_STATUS_APPROVED] ?? 0; ?>;
            const conversionRate = totalQuotes > 0 ? ((totalSales / totalQuotes) * 100).toFixed(1) : 0;
            
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Ventas Cerradas', 'Cotizaciones Pendientes'],
                    datasets: [{
                        data: [totalSales, totalQuotes - totalSales],
                        backgroundColor: [
                            'rgba(5, 150, 105, 0.8)',
                            'rgba(156, 163, 175, 0.3)'
                        ],
                        borderColor: [
                            'rgb(5, 150, 105)',
                            'rgb(156, 163, 175)'
                        ],
                        borderWidth: 2
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

        // Función para refrescar dashboard
        function refreshDashboard() {
            const button = event.target;
            button.disabled = true;
            button.innerHTML = 'Actualizando...';
            
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
        });

        // Auto-refresh cada 5 minutos
        setTimeout(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>