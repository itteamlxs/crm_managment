<?php
require_once dirname(dirname(__DIR__)) . '/config/constants.php';
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/utils.php';

// Configurar headers de seguridad
Security::setHeaders();

// Inicializar sesión y verificar autenticación
$session = new Session();
if (!$session->isLoggedIn()) {
    Utils::redirect('/login.php');
}

// Obtener mensajes
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Configuración de campos disponibles para reportes
$reportConfig = [
    'cotizaciones' => [
        'title' => 'Reportes de Cotizaciones',
        'description' => 'Genere reportes personalizados de cotizaciones con los campos que necesite',
        'icon' => 'fas fa-file-invoice',
        'color' => 'blue',
        'tables' => [
            'quotes' => [
                'label' => 'Cotizaciones',
                'fields' => [
                    'id' => 'ID de Cotización',
                    'quote_number' => 'Número de Cotización',
                    'quote_date' => 'Fecha de Cotización',
                    'valid_until' => 'Válida Hasta',
                    'subtotal' => 'Subtotal',
                    'discount_percent' => 'Descuento (%)',
                    'tax_amount' => 'Impuestos',
                    'total_amount' => 'Total',
                    'status' => 'Estado',
                    'notes' => 'Notas',
                    'created_at' => 'Fecha de Creación'
                ]
            ],
            'clients' => [
                'label' => 'Cliente',
                'fields' => [
                    'name' => 'Nombre del Cliente',
                    'email' => 'Email del Cliente',
                    'phone' => 'Teléfono del Cliente',
                    'address' => 'Dirección del Cliente'
                ]
            ],
            'users' => [
                'label' => 'Vendedor/Usuario',
                'fields' => [
                    'username' => 'Usuario que Creó',
                    'full_name' => 'Nombre del Vendedor',
                    'email' => 'Email del Vendedor'
                ]
            ]
        ]
    ],
    'productos' => [
        'title' => 'Reportes de Productos',
        'description' => 'Analice el rendimiento y movimiento de sus productos',
        'icon' => 'fas fa-box',
        'color' => 'green',
        'tables' => [
            'products' => [
                'label' => 'Productos',
                'fields' => [
                    'id' => 'ID del Producto',
                    'name' => 'Nombre del Producto',
                    'description' => 'Descripción',
                    'base_price' => 'Precio Base',
                    'tax_rate' => 'Tasa de Impuesto',
                    'unit' => 'Unidad',
                    'stock' => 'Stock Disponible',
                    'status' => 'Estado del Producto'
                ]
            ],
            'categories' => [
                'label' => 'Categoría',
                'fields' => [
                    'name' => 'Nombre de Categoría'
                ]
            ],
            'quote_details' => [
                'label' => 'Detalles de Cotización',
                'fields' => [
                    'quantity' => 'Cantidad Cotizada',
                    'unit_price' => 'Precio Unitario',
                    'line_total' => 'Total de Línea',
                    'discount_percent' => 'Descuento (%)',
                    'line_total_with_tax' => 'Total con Impuestos'
                ]
            ]
        ]
    ],
    'clientes' => [
        'title' => 'Reportes de Clientes',
        'description' => 'Análisis de actividad y rendimiento de clientes',
        'icon' => 'fas fa-users',
        'color' => 'purple',
        'tables' => [
            'clients' => [
                'label' => 'Clientes',
                'fields' => [
                    'id' => 'ID del Cliente',
                    'name' => 'Nombre',
                    'email' => 'Email',
                    'phone' => 'Teléfono',
                    'address' => 'Dirección',
                    'status' => 'Estado',
                    'created_at' => 'Fecha de Registro'
                ]
            ],
            'quotes_summary' => [
                'label' => 'Resumen de Cotizaciones',
                'fields' => [
                    'total_quotes' => 'Total de Cotizaciones',
                    'total_value' => 'Valor Total Cotizado',
                    'approved_quotes' => 'Cotizaciones Aprobadas',
                    'approved_value' => 'Valor Aprobado',
                    'last_quote_date' => 'Última Cotización'
                ]
            ]
        ]
    ],
    'ventas' => [
        'title' => 'Reportes de Ventas',
        'description' => 'Análisis de rendimiento de ventas y conversiones',
        'icon' => 'fas fa-chart-line',
        'color' => 'indigo',
        'tables' => [
            'sales_data' => [
                'label' => 'Datos de Ventas',
                'fields' => [
                    'quote_number' => 'Número de Cotización',
                    'client_name' => 'Cliente',
                    'seller_name' => 'Vendedor',
                    'sale_date' => 'Fecha de Venta',
                    'total_amount' => 'Monto Total',
                    'profit_margin' => 'Margen de Ganancia',
                    'payment_status' => 'Estado de Pago'
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navegación -->
    <?php require_once CORE_PATH . '/nav.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-chart-bar text-blue-600"></i>
                Generador de Reportes Dinamicos
            </h1>
            <p class="text-gray-600">Seleccione los campos que necesita y genere reportes personalizados en CSV</p>
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

        <!-- Selector de Tipo de Reporte -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <?php foreach ($reportConfig as $key => $config): ?>
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow cursor-pointer report-type-card" 
                     data-type="<?php echo $key; ?>">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="p-3 rounded-full bg-<?php echo $config['color']; ?>-100 text-<?php echo $config['color']; ?>-600">
                                <i class="<?php echo $config['icon']; ?> text-2xl"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            <?php echo $config['title']; ?>
                        </h3>
                        <p class="text-gray-600 text-sm">
                            <?php echo $config['description']; ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Formulario de Generación de Reportes -->
        <div id="reportForm" class="bg-white rounded-lg shadow p-6 hidden">
            <form id="customReportForm" method="POST" action="generateReport.php">
                <input type="hidden" name="csrf_token" value="<?php echo $session->getCsrfToken(); ?>">
                <input type="hidden" name="report_type" id="selectedReportType">

                <!-- Encabezado del Formulario -->
                <div class="mb-6 border-b pb-4">
                    <h2 class="text-xl font-bold text-gray-900" id="formTitle">Configurar Reporte</h2>
                    <p class="text-gray-600" id="formDescription">Seleccione los campos que desea incluir en su reporte</p>
                </div>

                <!-- Filtros de Fecha -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1"></i>Fecha Desde
                        </label>
                        <input type="date" name="date_from" id="dateFrom" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1"></i>Fecha Hasta
                        </label>
                        <input type="date" name="date_to" id="dateTo" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-list mr-1"></i>Límite de Registros
                        </label>
                        <select name="limit" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Sin límite</option>
                            <option value="100">100 registros</option>
                            <option value="500">500 registros</option>
                            <option value="1000">1,000 registros</option>
                            <option value="5000">5,000 registros</option>
                        </select>
                    </div>
                </div>

                <!-- Selección de Campos -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" id="fieldsContainer">
                    <!-- Los campos se cargarán dinámicamente aquí -->
                </div>

                <!-- Acciones -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-6 border-t">
                    <div class="flex items-center space-x-4">
                        <button type="button" id="selectAllBtn" 
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-check-square mr-1"></i>Seleccionar Todo
                        </button>
                        <button type="button" id="clearAllBtn" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            <i class="fas fa-square mr-1"></i>Limpiar Selección
                        </button>
                        <span id="selectedCount" class="text-sm text-gray-500">0 campos seleccionados</span>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="button" onclick="hideReportForm()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="button" id="previewBtn" 
                                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                            <i class="fas fa-eye mr-2"></i>Vista Previa
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <i class="fas fa-download mr-2"></i>Generar CSV
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Vista Previa -->
        <div id="previewContainer" class="bg-white rounded-lg shadow p-6 hidden mt-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-eye text-blue-600 mr-2"></i>Vista Previa del Reporte
                </h3>
                <button type="button" onclick="hidePreview()" 
                        class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
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
            <div class="mt-4 text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i>
                Mostrando los primeros 5 registros como ejemplo. El archivo CSV contendrá todos los datos según sus filtros.
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const reportConfig = <?php echo json_encode($reportConfig); ?>;
        let selectedFields = [];

        // Manejar selección de tipo de reporte
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.report-type-card');
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    const reportType = this.dataset.type;
                    showReportForm(reportType);
                });
            });

            // Manejar botones de selección
            document.getElementById('selectAllBtn').addEventListener('click', selectAllFields);
            document.getElementById('clearAllBtn').addEventListener('click', clearAllFields);
            document.getElementById('previewBtn').addEventListener('click', showPreview);
        });

        function showReportForm(reportType) {
            const config = reportConfig[reportType];
            if (!config) return;

            // Mostrar formulario
            document.getElementById('reportForm').classList.remove('hidden');
            document.getElementById('selectedReportType').value = reportType;
            document.getElementById('formTitle').textContent = config.title;
            document.getElementById('formDescription').textContent = config.description;

            // Generar campos
            generateFieldsHtml(config.tables);

            // Scroll al formulario
            document.getElementById('reportForm').scrollIntoView({ behavior: 'smooth' });
        }

        function hideReportForm() {
            document.getElementById('reportForm').classList.add('hidden');
            document.getElementById('previewContainer').classList.add('hidden');
            selectedFields = [];
            updateSelectedCount();
        }

        function generateFieldsHtml(tables) {
            const container = document.getElementById('fieldsContainer');
            container.innerHTML = '';

            Object.keys(tables).forEach(tableName => {
                const table = tables[tableName];
                
                const tableDiv = document.createElement('div');
                tableDiv.className = 'bg-gray-50 rounded-lg p-4';
                
                tableDiv.innerHTML = `
                    <h4 class="font-semibold text-gray-900 mb-3">
                        <i class="fas fa-table mr-2"></i>${table.label}
                    </h4>
                    <div class="space-y-2">
                        ${Object.keys(table.fields).map(fieldName => `
                            <label class="flex items-center">
                                <input type="checkbox" name="fields[]" value="${tableName}.${fieldName}" 
                                       class="field-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                       onchange="updateSelectedFields()">
                                <span class="ml-2 text-sm text-gray-700">${table.fields[fieldName]}</span>
                            </label>
                        `).join('')}
                    </div>
                `;
                
                container.appendChild(tableDiv);
            });
        }

        function updateSelectedFields() {
            const checkboxes = document.querySelectorAll('.field-checkbox:checked');
            selectedFields = Array.from(checkboxes).map(cb => cb.value);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = `${selectedFields.length} campos seleccionados`;
        }

        function selectAllFields() {
            const checkboxes = document.querySelectorAll('.field-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            updateSelectedFields();
        }

        function clearAllFields() {
            const checkboxes = document.querySelectorAll('.field-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateSelectedFields();
        }

        function showPreview() {
            if (selectedFields.length === 0) {
                alert('Por favor seleccione al menos un campo para la vista previa.');
                return;
            }

            // Simular datos para vista previa
            const headers = selectedFields.map(field => {
                const parts = field.split('.');
                const tableName = parts[0];
                const fieldName = parts[1];
                
                // Buscar el label del campo
                const reportType = document.getElementById('selectedReportType').value;
                const config = reportConfig[reportType];
                
                if (config.tables[tableName] && config.tables[tableName].fields[fieldName]) {
                    return config.tables[tableName].fields[fieldName];
                }
                return fieldName;
            });

            // Generar datos de ejemplo
            const sampleData = [];
            for (let i = 0; i < 5; i++) {
                const row = selectedFields.map(field => {
                    const fieldName = field.split('.')[1];
                    
                    // Generar datos de ejemplo según el tipo de campo
                    if (fieldName.includes('date') || fieldName.includes('created_at')) {
                        return new Date(Date.now() - Math.random() * 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    } else if (fieldName.includes('amount') || fieldName.includes('price') || fieldName.includes('total')) {
                        return '$' + (Math.random() * 1000).toFixed(2);
                    } else if (fieldName.includes('email')) {
                        return `ejemplo${i + 1}@empresa.com`;
                    } else if (fieldName.includes('phone')) {
                        return `+34 ${Math.floor(Math.random() * 900000000) + 100000000}`;
                    } else if (fieldName.includes('status')) {
                        const statuses = ['Activo', 'Inactivo', 'Pendiente', 'Aprobado'];
                        return statuses[Math.floor(Math.random() * statuses.length)];
                    } else if (fieldName.includes('name')) {
                        const names = ['Empresa ABC', 'Cliente XYZ', 'Producto 123', 'Vendedor'];
                        return names[Math.floor(Math.random() * names.length)] + ' ' + (i + 1);
                    } else if (fieldName.includes('id')) {
                        return Math.floor(Math.random() * 1000) + 1;
                    } else {
                        return `Dato ${i + 1}`;
                    }
                });
                sampleData.push(row);
            }

            // Mostrar tabla de vista previa
            const table = document.getElementById('previewTable');
            
            // Headers
            const thead = table.querySelector('thead');
            thead.innerHTML = `
                <tr>
                    ${headers.map(header => `<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">${header}</th>`).join('')}
                </tr>
            `;

            // Datos
            const tbody = table.querySelector('tbody');
            tbody.innerHTML = sampleData.map(row => `
                <tr>
                    ${row.map(cell => `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${cell}</td>`).join('')}
                </tr>
            `).join('');

            // Mostrar contenedor de vista previa
            document.getElementById('previewContainer').classList.remove('hidden');
            document.getElementById('previewContainer').scrollIntoView({ behavior: 'smooth' });
        }

        function hidePreview() {
            document.getElementById('previewContainer').classList.add('hidden');
        }

        // Validar formulario antes de enviar
        document.getElementById('customReportForm').addEventListener('submit', function(e) {
            if (selectedFields.length === 0) {
                e.preventDefault();
                alert('Por favor seleccione al menos un campo para generar el reporte.');
                return false;
            }
        });
    </script>
</body>
</html>