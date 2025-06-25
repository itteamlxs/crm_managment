<?php
require_once dirname(dirname(__DIR__)) . '/config/constants.php';
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/utils.php';

// Configurar headers de seguridad con CSP más permisivo para reportes
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");

// Inicializar sesión y verificar autenticación
$session = new Session();
if (!$session->isLoggedIn()) {
    Utils::redirect('/login.php');
}

// Obtener mensajes
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Configuración mejorada de campos disponibles para reportes
$reportConfig = [
    'cotizaciones' => [
        'title' => 'Reportes de Cotizaciones',
        'description' => 'Genere reportes detallados de cotizaciones con análisis de tiempo y estado',
        'icon' => '📄',
        'color' => 'blue',
        'tables' => [
            'quotes' => [
                'label' => 'Información de Cotizaciones',
                'fields' => [
                    'id' => 'ID de Cotización',
                    'quote_number' => 'Número de Cotización',
                    'quote_date' => 'Fecha de Cotización',
                    'valid_until' => 'Válida Hasta',
                    'subtotal' => 'Subtotal',
                    'discount_percent' => 'Descuento (%)',
                    'discount_amount' => 'Monto de Descuento',
                    'tax_amount' => 'Impuestos',
                    'total_amount' => 'Monto Total',
                    'status' => 'Estado de Cotización',
                    'notes' => 'Notas y Observaciones',
                    'created_at' => 'Fecha de Creación',
                    'days_in_process' => 'Días en Proceso',
                    'timing_status' => 'Estado de Tiempo'
                ]
            ],
            'clients' => [
                'label' => 'Información del Cliente',
                'fields' => [
                    'name' => 'Nombre del Cliente',
                    'email' => 'Email del Cliente',
                    'phone' => 'Teléfono del Cliente',
                    'address' => 'Dirección del Cliente'
                ]
            ]
        ]
    ],
    'productos' => [
        'title' => 'Reportes de Productos',
        'description' => 'Analice el rendimiento de productos con métricas de ventas reales y cotizaciones',
        'icon' => '📦',
        'color' => 'green',
        'tables' => [
            'products' => [
                'label' => 'Información de Productos',
                'fields' => [
                    'id' => 'ID del Producto',
                    'name' => 'Nombre del Producto',
                    'description' => 'Descripción',
                    'base_price' => 'Precio Base',
                    'final_price' => 'Precio Final (con impuestos)',
                    'tax_rate' => 'Tasa de Impuesto (%)',
                    'unit' => 'Unidad de Medida',
                    'stock' => 'Stock Disponible',
                    'status' => 'Estado del Producto',
                    'stock_status' => 'Estado de Stock'
                ]
            ],
            'categories' => [
                'label' => 'Información de Categoría',
                'fields' => [
                    'name' => 'Nombre de Categoría'
                ]
            ],
            'performance' => [
                'label' => 'Métricas de Rendimiento',
                'fields' => [
                    'times_quoted' => 'Veces Cotizado',
                    'times_sold' => 'Veces Vendido (Real)',
                    'total_sales_value' => 'Valor Total Vendido',
                    'avg_sale_price' => 'Precio Promedio de Venta'
                ]
            ]
        ]
    ],
    'clientes' => [
        'title' => 'Reportes de Clientes',
        'description' => 'Análisis completo de clientes con métricas de actividad y valor de vida',
        'icon' => '👥',
        'color' => 'purple',
        'tables' => [
            'clients' => [
                'label' => 'Información Personal',
                'fields' => [
                    'id' => 'ID del Cliente',
                    'name' => 'Nombre Completo',
                    'email' => 'Correo Electrónico',
                    'phone' => 'Número de Teléfono',
                    'address' => 'Dirección Completa',
                    'status' => 'Estado del Cliente',
                    'created_at' => 'Fecha de Registro'
                ]
            ],
            'quotes_summary' => [
                'label' => 'Métricas de Cotizaciones y Ventas',
                'fields' => [
                    'total_quotes' => 'Total de Cotizaciones',
                    'approved_quotes' => 'Cotizaciones Aprobadas',
                    'total_value' => 'Valor Total Cotizado',
                    'approved_value' => 'Valor Total de Ventas',
                    'last_quote_date' => 'Fecha Última Cotización',
                    'conversion_rate' => 'Tasa de Conversión (%)',
                    'customer_tier' => 'Clasificación de Cliente',
                    'activity_status' => 'Estado de Actividad'
                ]
            ]
        ]
    ],
    'ventas' => [
        'title' => 'Reportes de Ventas Confirmadas',
        'description' => 'Análisis detallado de ventas reales (cotizaciones aprobadas) con métricas avanzadas',
        'icon' => '📈',
        'color' => 'indigo',
        'tables' => [
            'sales_data' => [
                'label' => 'Información de la Venta',
                'fields' => [
                    'sale_id' => 'ID de Venta',
                    'sale_number' => 'Número de Venta',
                    'sale_date' => 'Fecha de Venta',
                    'sale_amount' => 'Monto Total de Venta',
                    'net_sale_amount' => 'Monto Neto (sin impuestos)',
                    'client_name' => 'Nombre del Cliente',
                    'days_to_close' => 'Días para Cerrar Venta',
                    'timing_status' => 'Estado de Tiempo',
                    'discount_category' => 'Categoría de Descuento',
                    'sale_category' => 'Categoría por Valor',
                    'products_count' => 'Cantidad de Productos Diferentes',
                    'total_items_sold' => 'Total de Items Vendidos',
                    'estimated_profit_30_percent' => 'Ganancia Estimada (30%)'
                ]
            ],
            'clients' => [
                'label' => 'Datos del Cliente',
                'fields' => [
                    'email' => 'Email del Cliente',
                    'phone' => 'Teléfono del Cliente',
                    'address' => 'Dirección del Cliente'
                ]
            ],
            'quotes' => [
                'label' => 'Detalles Financieros',
                'fields' => [
                    'subtotal' => 'Subtotal de la Venta',
                    'discount_percent' => 'Porcentaje de Descuento',
                    'discount_amount' => 'Monto de Descuento',
                    'tax_amount' => 'Impuestos Cobrados',
                    'notes' => 'Notas de la Venta',
                    'valid_until' => 'Fecha de Vencimiento',
                    'created_at' => 'Fecha de Creación Original'
                ]
            ]
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Reportes - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Iconos CSS simples para reemplazar Font Awesome */
        .icon-chart::before { content: "📊"; }
        .icon-file::before { content: "📄"; }
        .icon-box::before { content: "📦"; }
        .icon-users::before { content: "👥"; }
        .icon-chart-line::before { content: "📈"; }
        .icon-calendar::before { content: "📅"; }
        .icon-filter::before { content: "🔍"; }
        .icon-download::before { content: "⬇️"; }
        .icon-eye::before { content: "👁️"; }
        .icon-check::before { content: "✅"; }
        .icon-times::before { content: "❌"; }
        .icon-info::before { content: "ℹ️"; }
        .icon-warning::before { content: "⚠️"; }
        .icon-success::before { content: "✅"; }
        .icon-error::before { content: "❌"; }
        .icon-table::before { content: "📋"; }
        .icon-cog::before { content: "⚙️"; }
        
        /* Estilos personalizados para mejorar la experiencia */
        .report-type-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
        }
        .report-type-card:hover {
            transform: translateY(-2px);
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .report-type-card.selected {
            border-color: rgba(59, 130, 246, 0.6);
            background-color: rgba(59, 130, 246, 0.05);
        }
        .field-group {
            transition: all 0.2s ease;
        }
        .field-group:hover {
            background-color: rgba(249, 250, 251, 0.8);
        }
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
        .btn-success {
            background-color: #10b981;
            color: white;
        }
        .btn-success:hover {
            background-color: #059669;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            transition: border-color 0.2s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .checkbox {
            margin-right: 8px;
        }
        
        /* Colores para diferentes tipos */
        .color-blue { color: #3b82f6; }
        .bg-blue-light { background-color: #eff6ff; }
        .border-blue { border-color: #3b82f6; }
        
        .color-green { color: #10b981; }
        .bg-green-light { background-color: #ecfdf5; }
        .border-green { border-color: #10b981; }
        
        .color-purple { color: #8b5cf6; }
        .bg-purple-light { background-color: #f3f4f6; }
        .border-purple { border-color: #8b5cf6; }
        
        .color-indigo { color: #6366f1; }
        .bg-indigo-light { background-color: #eef2ff; }
        .border-indigo { border-color: #6366f1; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navegación -->
    <?php require_once CORE_PATH . '/nav.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Encabezado mejorado -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        <span class="icon-chart"></span>
                        Generador de Reportes Personalizados
                    </h1>
                    <p class="text-gray-600">Seleccione los campos que necesita y genere reportes personalizados en CSV</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">
                        <span class="icon-info"></span>
                        Los reportes de <strong>ventas</strong> incluyen solo cotizaciones aprobadas
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensajes mejorados -->
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-r mb-6">
                <div class="flex items-center">
                    <span class="icon-error mr-3 text-lg"></span>
                    <div>
                        <p class="font-medium">Error</p>
                        <p class="text-sm"><?php echo Security::escape($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-r mb-6">
                <div class="flex items-center">
                    <span class="icon-success mr-3 text-lg"></span>
                    <div>
                        <p class="font-medium">Éxito</p>
                        <p class="text-sm"><?php echo Security::escape($success); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-blue-100">
                        <span class="icon-file color-blue"></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Tipos de Reportes</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo count($reportConfig); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-green-100">
                        <span class="icon-table color-green"></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Campos Disponibles</p>
                        <p class="text-lg font-semibold text-gray-900">
                            <?php 
                            $totalFields = 0;
                            foreach ($reportConfig as $config) {
                                foreach ($config['tables'] as $table) {
                                    $totalFields += count($table['fields']);
                                }
                            }
                            echo $totalFields;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-purple-100">
                        <span class="icon-filter color-purple"></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Filtros Avanzados</p>
                        <p class="text-lg font-semibold text-gray-900">Disponibles</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-indigo-100">
                        <span class="icon-download color-indigo"></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Formato Export</p>
                        <p class="text-lg font-semibold text-gray-900">CSV</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selector de Tipo de Reporte mejorado -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <span class="icon-filter mr-2"></span>Seleccione el Tipo de Reporte
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($reportConfig as $key => $config): ?>
                    <div class="report-type-card bg-white rounded-lg shadow" 
                         data-type="<?php echo $key; ?>"
                         title="<?php echo Security::escape($config['description']); ?>">
                        <div class="p-6">
                            <div class="flex items-center mb-4">
                                <div class="p-3 rounded-full bg-<?php echo $config['color']; ?>-light">
                                    <span class="text-2xl"><?php echo $config['icon']; ?></span>
                                </div>
                                <div class="ml-3 flex-1">
                                    <div class="text-xs text-gray-500 uppercase tracking-wide">
                                        <?php echo count($config['tables']); ?> secciones
                                    </div>
                                </div>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                <?php echo $config['title']; ?>
                            </h3>
                            <p class="text-gray-600 text-sm mb-3">
                                <?php echo $config['description']; ?>
                            </p>
                            <div class="text-xs text-gray-400">
                                <?php 
                                $fieldCount = 0;
                                foreach ($config['tables'] as $table) {
                                    $fieldCount += count($table['fields']);
                                }
                                echo $fieldCount . ' campos disponibles';
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Formulario de Generación de Reportes mejorado -->
        <div id="reportForm" class="bg-white rounded-lg shadow hidden">
            <form id="customReportForm" method="POST" action="generateReport.php">
                <input type="hidden" name="csrf_token" value="<?php echo $session->getCsrfToken(); ?>">
                <input type="hidden" name="report_type" id="selectedReportType">

                <!-- Encabezado del Formulario -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900" id="formTitle">Configurar Reporte</h2>
                            <p class="text-gray-600 mt-1" id="formDescription">Seleccione los campos que desea incluir en su reporte</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="loading-spinner" id="loadingSpinner"></div>
                            <button type="button" onclick="hideReportForm()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <span class="icon-times text-lg"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filtros de Fecha y Límites mejorados -->
                <div class="p-6 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <span class="icon-filter mr-2"></span>Filtros y Opciones
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <span class="icon-calendar mr-1"></span>Fecha Desde
                            </label>
                            <input type="date" name="date_from" id="dateFrom" 
                                   class="form-input"
                                   onchange="validateDateRange()">
                            <p class="text-xs text-gray-500 mt-1">Opcional: filtro por fecha inicial</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <span class="icon-calendar mr-1"></span>Fecha Hasta
                            </label>
                            <input type="date" name="date_to" id="dateTo" 
                                   class="form-input"
                                   onchange="validateDateRange()">
                            <p class="text-xs text-gray-500 mt-1">Opcional: filtro por fecha final</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <span class="icon-table mr-1"></span>Límite de Registros
                            </label>
                            <select name="limit" class="form-input">
                                <option value="">Sin límite</option>
                                <option value="100">100 registros</option>
                                <option value="500">500 registros</option>
                                <option value="1000">1,000 registros</option>
                                <option value="5000">5,000 registros</option>
                                <option value="10000">10,000 registros</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Para archivos más pequeños</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <span class="icon-cog mr-1"></span>Opciones Adicionales
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" id="includeHeaders" checked class="checkbox">
                                    <span class="text-sm text-gray-700">Incluir encabezados</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="formatValues" checked class="checkbox">
                                    <span class="text-sm text-gray-700">Formatear valores</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div id="dateError" class="hidden mt-2 text-sm text-red-600">
                        <span class="icon-warning mr-1"></span>
                        La fecha inicial no puede ser mayor que la fecha final
                    </div>
                </div>

                <!-- Selección de Campos mejorada -->
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            <span class="icon-table mr-2"></span>Campos Disponibles
                        </h3>
                        <div class="flex items-center space-x-4">
                            <button type="button" id="selectAllBtn" 
                                    class="btn btn-primary text-sm">
                                <span class="icon-check mr-1"></span>Seleccionar Todo
                            </button>
                            <button type="button" id="clearAllBtn" 
                                    class="btn btn-secondary text-sm">
                                <span class="icon-times mr-1"></span>Limpiar Selección
                            </button>
                            <div class="text-sm">
                                <span id="selectedCount" class="font-medium color-blue">0</span>
                                <span class="text-gray-500">campos seleccionados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6" id="fieldsContainer">
                        <!-- Los campos se cargarán dinámicamente aquí -->
                    </div>
                </div>

                <!-- Acciones del formulario mejoradas -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center space-x-4">
                            <div class="text-sm text-gray-600">
                                <span class="icon-info mr-1"></span>
                                El archivo CSV se descargará automáticamente
                            </div>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="button" onclick="hideReportForm()" 
                                    class="btn btn-secondary">
                                <span class="icon-times mr-1"></span>Cancelar
                            </button>
                            <button type="button" id="previewBtn" 
                                    class="btn btn-secondary">
                                <span class="icon-eye mr-1"></span>Vista Previa
                            </button>
                            <button type="submit" id="generateBtn"
                                    class="btn btn-success">
                                <span class="icon-download mr-1"></span>Generar CSV
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Vista Previa mejorada -->
        <div id="previewContainer" class="bg-white rounded-lg shadow hidden mt-6">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <span class="icon-eye color-blue mr-2"></span>Vista Previa del Reporte
                    </h3>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-500" id="previewInfo">
                            Mostrando muestra de datos
                        </span>
                        <button type="button" onclick="hidePreview()" 
                                class="text-gray-400 hover:text-gray-600">
                            <span class="icon-times"></span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table id="previewTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <!-- Headers dinámicos -->
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <!-- Datos de ejemplo -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-start">
                        <span class="icon-info color-blue mt-0.5 mr-2"></span>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium">Vista Previa</p>
                            <p>Esta es una muestra de cómo se verán sus datos. El archivo CSV final contendrá todos los registros según los filtros aplicados.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts mejorados -->
    <script>
        const reportConfig = <?php echo json_encode($reportConfig); ?>;
        let selectedFields = [];
        let currentReportType = '';

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando reportes...');
            console.log('Configuración cargada:', reportConfig);
            
            initializeReportCards();
            initializeButtons();
            initializeDateValidation();
            
            console.log('Inicialización completa');
        });

        function initializeReportCards() {
            console.log('Inicializando cards de reportes...');
            const cards = document.querySelectorAll('.report-type-card');
            console.log('Cards encontradas:', cards.length);
            
            cards.forEach((card, index) => {
                console.log(`Configurando card ${index}:`, card.dataset.type);
                card.addEventListener('click', function() {
                    console.log('Card clickeada:', this.dataset.type);
                    
                    // Remover selección anterior
                    cards.forEach(c => c.classList.remove('selected'));
                    // Agregar selección actual
                    this.classList.add('selected');
                    
                    const reportType = this.dataset.type;
                    currentReportType = reportType;
                    showReportForm(reportType);
                });
            });
        }

        function initializeButtons() {
            console.log('Inicializando botones...');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const clearAllBtn = document.getElementById('clearAllBtn');
            const previewBtn = document.getElementById('previewBtn');
            
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', selectAllFields);
                console.log('Botón "Seleccionar Todo" configurado');
            }
            
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', clearAllFields);
                console.log('Botón "Limpiar Selección" configurado');
            }
            
            if (previewBtn) {
                previewBtn.addEventListener('click', showPreview);
                console.log('Botón "Vista Previa" configurado');
            }
        }

        function initializeDateValidation() {
            console.log('Inicializando validación de fechas...');
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            
            if (dateFrom && dateTo) {
                // Establecer fecha máxima como hoy
                const today = new Date().toISOString().split('T')[0];
                dateFrom.setAttribute('max', today);
                dateTo.setAttribute('max', today);
                console.log('Validación de fechas configurada');
            }
        }

        function validateDateRange() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const errorDiv = document.getElementById('dateError');
            
            if (dateFrom && dateTo && dateFrom > dateTo) {
                errorDiv.classList.remove('hidden');
                return false;
            } else {
                errorDiv.classList.add('hidden');
                return true;
            }
        }

        function showReportForm(reportType) {
            console.log('Mostrando formulario para:', reportType);
            const config = reportConfig[reportType];
            if (!config) {
                console.error('Configuración no encontrada para:', reportType);
                return;
            }

            // Mostrar loading
            const loadingSpinner = document.getElementById('loadingSpinner');
            if (loadingSpinner) {
                loadingSpinner.style.display = 'block';
            }

            // Actualizar formulario
            const reportForm = document.getElementById('reportForm');
            const selectedReportType = document.getElementById('selectedReportType');
            const formTitle = document.getElementById('formTitle');
            const formDescription = document.getElementById('formDescription');
            
            if (reportForm) reportForm.classList.remove('hidden');
            if (selectedReportType) selectedReportType.value = reportType;
            if (formTitle) formTitle.textContent = config.title;
            if (formDescription) formDescription.textContent = config.description;

            // Generar campos
            setTimeout(() => {
                generateFieldsHtml(config.tables);
                if (loadingSpinner) {
                    loadingSpinner.style.display = 'none';
                }
                
                // Scroll suave al formulario
                if (reportForm) {
                    reportForm.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }, 300);
        }

        function hideReportForm() {
            console.log('Ocultando formulario');
            const reportForm = document.getElementById('reportForm');
            const previewContainer = document.getElementById('previewContainer');
            
            if (reportForm) reportForm.classList.add('hidden');
            if (previewContainer) previewContainer.classList.add('hidden');
            
            // Limpiar selección de cards
            document.querySelectorAll('.report-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            selectedFields = [];
            currentReportType = '';
            updateSelectedCount();
        }

        function generateFieldsHtml(tables) {
            console.log('Generando HTML de campos para:', tables);
            const container = document.getElementById('fieldsContainer');
            if (!container) {
                console.error('Contenedor de campos no encontrado');
                return;
            }
            
            container.innerHTML = '';

            Object.keys(tables).forEach(tableName => {
                const table = tables[tableName];
                
                const tableDiv = document.createElement('div');
                tableDiv.className = 'field-group bg-gray-50 rounded-lg p-4 border border-gray-200';
                
                const fieldCount = Object.keys(table.fields).length;
                const selectedInGroup = selectedFields.filter(f => f.startsWith(tableName + '.')).length;
                
                tableDiv.innerHTML = `
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900 flex items-center">
                            <span class="icon-table mr-2 ${getTableColorClass(tableName)}"></span>
                            ${table.label}
                        </h4>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs px-2 py-1 bg-gray-200 text-gray-600 rounded-full field-counter">
                                ${selectedInGroup}/${fieldCount}
                            </span>
                            <button type="button" onclick="toggleGroupSelection('${tableName}')" 
                                    class="text-xs btn btn-primary">
                                <span class="icon-check"></span>
                            </button>
                        </div>
                    </div>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        ${Object.keys(table.fields).map(fieldName => `
                            <label class="flex items-center group hover:bg-white hover:bg-opacity-50 p-2 rounded transition-colors">
                                <input type="checkbox" name="fields[]" value="${tableName}.${fieldName}" 
                                       class="field-checkbox checkbox"
                                       onchange="updateSelectedFields()">
                                <span class="ml-3 text-sm text-gray-700 flex-1">${table.fields[fieldName]}</span>
                                <span class="icon-info text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity text-xs"
                                   title="Campo: ${tableName}.${fieldName}"></span>
                            </label>
                        `).join('')}
                    </div>
                `;
                
                container.appendChild(tableDiv);
            });
            
            console.log('HTML de campos generado correctamente');
        }

        function getTableColorClass(tableName) {
            const colors = {
                'quotes': 'color-blue',
                'clients': 'color-purple',
                'products': 'color-green',
                'categories': 'color-yellow',
                'sales_data': 'color-indigo',
                'quotes_summary': 'color-pink',
                'performance': 'color-red'
            };
            return colors[tableName] || 'color-gray';
        }

        function toggleGroupSelection(tableName) {
            console.log('Toggleando selección del grupo:', tableName);
            const groupCheckboxes = document.querySelectorAll(`input[value^="${tableName}."]`);
            const checkedCount = document.querySelectorAll(`input[value^="${tableName}."]:checked`).length;
            const shouldCheck = checkedCount < groupCheckboxes.length;
            
            groupCheckboxes.forEach(cb => {
                cb.checked = shouldCheck;
            });
            
            updateSelectedFields();
        }

        function updateSelectedFields() {
            const checkboxes = document.querySelectorAll('.field-checkbox:checked');
            selectedFields = Array.from(checkboxes).map(cb => cb.value);
            console.log('Campos seleccionados actualizados:', selectedFields);
            
            updateSelectedCount();
            updateGroupCounters();
        }

        function updateSelectedCount() {
            const countElement = document.getElementById('selectedCount');
            if (countElement) {
                countElement.textContent = selectedFields.length;
            }
            
            // Habilitar/deshabilitar botones
            const previewBtn = document.getElementById('previewBtn');
            const generateBtn = document.getElementById('generateBtn');
            
            const isDisabled = selectedFields.length === 0;
            
            if (previewBtn) {
                previewBtn.disabled = isDisabled;
                if (isDisabled) {
                    previewBtn.classList.add('opacity-50');
                } else {
                    previewBtn.classList.remove('opacity-50');
                }
            }
            
            if (generateBtn) {
                generateBtn.disabled = isDisabled;
                if (isDisabled) {
                    generateBtn.classList.add('opacity-50');
                } else {
                    generateBtn.classList.remove('opacity-50');
                }
            }
        }

        function updateGroupCounters() {
            document.querySelectorAll('.field-group').forEach(group => {
                const firstInput = group.querySelector('input');
                if (!firstInput) return;
                
                const tableName = firstInput.value.split('.')[0];
                const total = group.querySelectorAll('input').length;
                const selected = group.querySelectorAll('input:checked').length;
                const counter = group.querySelector('.field-counter');
                
                if (counter) {
                    counter.textContent = `${selected}/${total}`;
                    
                    // Cambiar color según selección
                    counter.className = 'text-xs px-2 py-1 rounded-full field-counter ';
                    if (selected === 0) {
                        counter.className += 'bg-gray-200 text-gray-600';
                    } else if (selected === total) {
                        counter.className += 'bg-green-200 text-green-700';
                    } else {
                        counter.className += 'bg-blue-200 text-blue-700';
                    }
                }
            });
        }

        function selectAllFields() {
            console.log('Seleccionando todos los campos');
            const checkboxes = document.querySelectorAll('.field-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            updateSelectedFields();
        }

        function clearAllFields() {
            console.log('Limpiando selección de campos');
            const checkboxes = document.querySelectorAll('.field-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateSelectedFields();
        }

        function showPreview() {
            console.log('Mostrando vista previa');
            if (selectedFields.length === 0) {
                showNotification('Por favor seleccione al menos un campo para la vista previa.', 'warning');
                return;
            }

            // Generar headers
            const headers = selectedFields.map(field => {
                const parts = field.split('.');
                const tableName = parts[0];
                const fieldName = parts[1];
                
                const config = reportConfig[currentReportType];
                if (config.tables[tableName] && config.tables[tableName].fields[fieldName]) {
                    return config.tables[tableName].fields[fieldName];
                }
                return fieldName;
            });

            // Generar datos de ejemplo
            const sampleData = generateSampleData(selectedFields, 5);

            // Mostrar tabla
            displayPreviewTable(headers, sampleData);
            
            // Mostrar contenedor
            const previewContainer = document.getElementById('previewContainer');
            const previewInfo = document.getElementById('previewInfo');
            
            if (previewContainer) previewContainer.classList.remove('hidden');
            if (previewInfo) {
                previewInfo.textContent = `Mostrando 5 registros de ejemplo de ${selectedFields.length} campos`;
            }
            
            // Scroll a vista previa
            if (previewContainer) {
                previewContainer.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }

        function generateSampleData(fields, rowCount) {
            const sampleData = [];
            
            for (let i = 0; i < rowCount; i++) {
                const row = fields.map(field => {
                    const fieldName = field.split('.')[1];
                    
                    // Generar datos según el tipo de campo
                    if (fieldName.includes('date') || fieldName.includes('created_at')) {
                        const randomDate = new Date(Date.now() - Math.random() * 365 * 24 * 60 * 60 * 1000);
                        return randomDate.toISOString().split('T')[0];
                    } else if (fieldName.includes('amount') || fieldName.includes('price') || fieldName.includes('total')) {
                        return '€' + (Math.random() * 10000).toFixed(2);
                    } else if (fieldName.includes('percent') || fieldName.includes('rate')) {
                        return (Math.random() * 100).toFixed(2) + '%';
                    } else if (fieldName.includes('email')) {
                        const domains = ['empresa.com', 'cliente.es', 'negocio.net'];
                        return `usuario${i + 1}@${domains[Math.floor(Math.random() * domains.length)]}`;
                    } else if (fieldName.includes('phone')) {
                        return `+34 ${Math.floor(Math.random() * 900000000) + 100000000}`;
                    } else if (fieldName.includes('status')) {
                        const statuses = ['Activo', 'Inactivo', 'Pendiente', 'Aprobado', 'Rechazado'];
                        return statuses[Math.floor(Math.random() * statuses.length)];
                    } else if (fieldName.includes('name') || fieldName.includes('client')) {
                        const names = ['Empresa ABC S.L.', 'Comercial XYZ', 'Industrias DEF', 'Servicios GHI'];
                        return names[Math.floor(Math.random() * names.length)];
                    } else if (fieldName.includes('id')) {
                        return Math.floor(Math.random() * 9999) + 1;
                    } else if (fieldName.includes('count') || fieldName.includes('quantity') || fieldName.includes('days')) {
                        return Math.floor(Math.random() * 100) + 1;
                    } else if (fieldName.includes('category')) {
                        const categories = ['Electrónicos', 'Oficina', 'Servicios', 'Productos'];
                        return categories[Math.floor(Math.random() * categories.length)];
                    } else {
                        return `Ejemplo ${i + 1}`;
                    }
                });
                sampleData.push(row);
            }
            
            return sampleData;
        }

        function displayPreviewTable(headers, data) {
            const table = document.getElementById('previewTable');
            if (!table) return;
            
            // Headers
            const thead = table.querySelector('thead');
            if (thead) {
                thead.innerHTML = `
                    <tr>
                        ${headers.map(header => `
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">
                                ${header}
                            </th>
                        `).join('')}
                    </tr>
                `;
            }

            // Datos
            const tbody = table.querySelector('tbody');
            if (tbody) {
                tbody.innerHTML = data.map((row, index) => `
                    <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                        ${row.map(cell => `
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 border-b border-gray-100">
                                ${cell}
                            </td>
                        `).join('')}
                    </tr>
                `).join('');
            }
        }

        function hidePreview() {
            const previewContainer = document.getElementById('previewContainer');
            if (previewContainer) {
                previewContainer.classList.add('hidden');
            }
        }

        function showNotification(message, type = 'info') {
            console.log('Mostrando notificación:', message, type);
            
            // Crear notificación temporal
            const notification = document.createElement('div');
            notification.className = 'notification';
            
            const colors = {
                'info': 'bg-blue-100 border-blue-500 text-blue-700',
                'success': 'bg-green-100 border-green-500 text-green-700',
                'warning': 'bg-yellow-100 border-yellow-500 text-yellow-700',
                'error': 'bg-red-100 border-red-500 text-red-700'
            };
            
            notification.className += ` ${colors[type]} border-l-4`;
            
            const icons = {
                'info': 'icon-info',
                'success': 'icon-success',
                'warning': 'icon-warning',
                'error': 'icon-error'
            };
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <span class="${icons[type]} mr-2"></span>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animar entrada
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Remover después de 3 segundos
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Validar formulario antes de enviar
        const customReportForm = document.getElementById('customReportForm');
        if (customReportForm) {
            customReportForm.addEventListener('submit', function(e) {
                console.log('Enviando formulario...');
                
                if (selectedFields.length === 0) {
                    e.preventDefault();
                    showNotification('Por favor seleccione al menos un campo para generar el reporte.', 'warning');
                    return false;
                }
                
                if (!validateDateRange()) {
                    e.preventDefault();
                    showNotification('Por favor corrija el rango de fechas.', 'error');
                    return false;
                }
                
                // Mostrar indicador de carga
                const generateBtn = document.getElementById('generateBtn');
                if (generateBtn) {
                    const originalText = generateBtn.innerHTML;
                    generateBtn.innerHTML = '<span class="loading-spinner" style="display: inline-block;"></span> Generando...';
                    generateBtn.disabled = true;
                    
                    // Mostrar notificación de inicio
                    showNotification('Generando reporte, por favor espere...', 'info');
                    
                    // Restaurar botón después de un tiempo (en caso de error)
                    setTimeout(() => {
                        generateBtn.innerHTML = originalText;
                        generateBtn.disabled = false;
                    }, 10000);
                }
            });
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Escape para cerrar formulario
            if (e.key === 'Escape') {
                const reportForm = document.getElementById('reportForm');
                const previewContainer = document.getElementById('previewContainer');
                
                if (reportForm && !reportForm.classList.contains('hidden')) {
                    hideReportForm();
                } else if (previewContainer && !previewContainer.classList.contains('hidden')) {
                    hidePreview();
                }
            }
        });

        console.log('Scripts cargados correctamente');
    </script>
</body>
</html>