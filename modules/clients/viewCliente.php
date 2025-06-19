<?php
// Vista modal para mostrar información completa del cliente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/clients/clientController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new ClientController();
} catch (Exception $e) {
    die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar autenticación
if (!$controller->isAuthenticated()) {
    http_response_code(401);
    die('No autorizado');
}

// Validar que se proporcionó ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('ID de cliente no válido');
}

$clientId = (int)$_GET['id'];

// Obtener datos del cliente
$result = $controller->getById($clientId);
if (isset($result['error'])) {
    http_response_code(404);
    die('Cliente no encontrado: ' . Security::escape($result['error']));
}

$client = $result['client'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cliente: <?php echo Security::escape($client['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        // Función para cerrar el modal
        function closeModal() {
            if (window.parent && window.parent !== window) {
                // Si está en un iframe, cerrar el modal del padre
                window.parent.postMessage('closeModal', '*');
            } else {
                // Si se abrió en nueva ventana, cerrarla
                window.close();
            }
        }

        // Función para imprimir la información del cliente
        function printClient() {
            window.print();
        }

        // Función para ir a editar
        function editClient() {
            const editUrl = 'clientForm.php?id=<?php echo $client['id']; ?>';
            if (window.parent && window.parent !== window) {
                window.parent.location.href = editUrl;
            } else {
                window.location.href = editUrl;
            }
        }

        // Manejar tecla ESC para cerrar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg">
        
        <!-- Header del modal -->
        <div class="bg-blue-600 text-white p-4 rounded-t-lg no-print">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold">Información del Cliente</h2>
                <button onclick="closeModal()" class="text-white hover:text-gray-200 text-2xl">
                    ×
                </button>
            </div>
        </div>

        <!-- Contenido del cliente -->
        <div class="p-6">
            
            <!-- Información principal -->
            <div class="mb-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <?php echo Security::escape($client['name']); ?>
                        </h1>
                        <div class="mt-2">
                            <?php if ($client['status'] == STATUS_ACTIVE): ?>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                    ✓ Cliente Activo
                                </span>
                            <?php else: ?>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800">
                                    ✗ Cliente Inactivo
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right text-sm text-gray-500">
                        <div>ID: <?php echo $client['id']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Detalles del cliente -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                
                <!-- Información de contacto -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Información de Contacto
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Correo Electrónico</label>
                        <div class="mt-1 text-gray-900">
                            <a href="mailto:<?php echo Security::escape($client['email']); ?>" 
                               class="text-blue-600 hover:text-blue-800">
                                <?php echo Security::escape($client['email']); ?>
                            </a>
                        </div>
                    </div>

                    <?php if ($client['phone']): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Teléfono</label>
                            <div class="mt-1 text-gray-900">
                                <a href="tel:<?php echo Security::escape($client['phone']); ?>" 
                                   class="text-blue-600 hover:text-blue-800">
                                    <?php echo Security::escape($client['phone']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($client['address']): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Dirección</label>
                            <div class="mt-1 text-gray-900">
                                <?php echo nl2br(Security::escape($client['address'])); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Dirección</label>
                            <div class="mt-1 text-gray-500 italic">No especificada</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Información del sistema -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Información del Sistema
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Fecha de Registro</label>
                        <div class="mt-1 text-gray-900">
                            <?php echo Utils::formatDateDisplay($client['created_at']); ?>
                        </div>
                    </div>

                    <?php if ($client['updated_at']): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Última Actualización</label>
                            <div class="mt-1 text-gray-900">
                                <?php echo Utils::formatDateDisplay($client['updated_at']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-600">Estado</label>
                        <div class="mt-1">
                            <?php if ($client['status'] == STATUS_ACTIVE): ?>
                                <span class="text-green-700 font-medium">Activo</span>
                                <div class="text-sm text-gray-500">El cliente puede realizar transacciones</div>
                            <?php else: ?>
                                <span class="text-red-700 font-medium">Inactivo</span>
                                <div class="text-sm text-gray-500">El cliente está deshabilitado</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas rápidas -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Resumen de Actividad</h3>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-blue-600">0</div>
                        <div class="text-sm text-gray-600">Cotizaciones</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">0</div>
                        <div class="text-sm text-gray-600">Ventas</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-600">$0.00</div>
                        <div class="text-sm text-gray-600">Total Facturado</div>
                    </div>
                </div>
                <div class="text-center mt-2 text-sm text-gray-500">
                    <em>Las estadísticas se actualizarán cuando estén disponibles los módulos de cotizaciones y ventas</em>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex justify-between items-center pt-4 border-t no-print">
                <div class="flex space-x-2">
                    <button onclick="editClient()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Editar Cliente
                    </button>
                    <button onclick="printClient()" 
                            class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        Imprimir
                    </button>
                </div>
                <button onclick="closeModal()" 
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Información adicional para impresión -->
    <div class="hidden print:block mt-4 text-center text-sm text-gray-500">
        <p>Documento generado desde Sistema CRM - <?php echo date('d/m/Y H:i'); ?></p>
    </div>
</body>
</html>