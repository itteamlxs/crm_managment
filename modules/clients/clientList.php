<?php
// Vista del listado de clientes
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
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
    exit;
}

// Procesar acciones
$error = '';
$success = '';

// Obtener parámetros de URL
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Procesar exportar CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $controller->exportCsv($search, $status);
    exit;
}

// Procesar cambio de estado
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $clientId = (int)$_GET['id'];
    $newStatus = (int)($_GET['new_status'] ?? STATUS_INACTIVE);
    $result = $controller->changeStatus($clientId, $newStatus);
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Procesar eliminación
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $clientId = (int)$_GET['id'];
    $result = $controller->delete($clientId);
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Obtener listado de clientes
$listResult = $controller->list($search, $status, $page, $perPage);
if (isset($listResult['error'])) {
    $error = $listResult['error'];
    $clients = [];
    $pagination = ['total' => 0, 'page' => 1, 'totalPages' => 1];
} else {
    $clients = $listResult['clients'];
    $pagination = [
        'total' => $listResult['total'],
        'page' => $listResult['page'],
        'totalPages' => $listResult['totalPages'],
        'perPage' => $listResult['perPage']
    ];
}

// Manejar mensajes de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success = 'Cliente creado correctamente.';
            break;
        case 'updated':
            $success = 'Cliente actualizado correctamente.';
            break;
        case 'deleted':
            $success = 'Cliente eliminado correctamente.';
            break;
        case 'activated':
            $success = 'Cliente activado correctamente.';
            break;
        case 'deactivated':
            $success = 'Cliente desactivado correctamente.';
            break;
    }
}

$userRole = $controller->getUserRole();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <?php require_once dirname(__DIR__, 2) . '/core/nav.php'; ?>
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-bold text-gray-800">Gestión de Clientes</h1>
                <div class="flex space-x-2">
                    <a href="clientForm.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        + Nuevo Cliente
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/dashboard/dashboardView.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        Dashboard
                    </a>
                </div>
            </div>

            <!-- Filtros y búsqueda -->
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-64">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        value="<?php echo Security::escape($search); ?>"
                        placeholder="Nombre, email o teléfono..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select id="status" name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="<?php echo STATUS_ACTIVE; ?>" <?php echo $status === STATUS_ACTIVE ? 'selected' : ''; ?>>Activos</option>
                        <option value="<?php echo STATUS_INACTIVE; ?>" <?php echo $status === STATUS_INACTIVE ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Buscar
                    </button>
                    <a href="clientList.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Limpiar
                    </a>
                    <a href="?action=export<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Exportar CSV
                    </a>
                </div>
            </form>
        </div>

        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo Security::escape($success); ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($pagination['total']); ?></div>
                    <div class="text-gray-600">Total de Clientes</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        <?php 
                        $activeClients = array_filter($clients, fn($c) => $c['status'] == STATUS_ACTIVE);
                        echo count($activeClients);
                        ?>
                    </div>
                    <div class="text-gray-600">Clientes Activos</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600">
                        Página <?php echo $pagination['page']; ?> de <?php echo $pagination['totalPages']; ?>
                    </div>
                    <div class="text-gray-600">Paginación</div>
                </div>
            </div>
        </div>

        <!-- Tabla de clientes -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if (empty($clients)): ?>
                <div class="p-8 text-center text-gray-500">
                    <p class="text-xl">No se encontraron clientes</p>
                    <p class="mt-2">Intente cambiar los filtros o crear un nuevo cliente</p>
                    <a href="clientForm.php" class="inline-block mt-4 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700">
                        Crear Primer Cliente
                    </a>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contacto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($clients as $client): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo Security::escape($client['name']); ?>
                                        </div>
                                        <?php if ($client['address']): ?>
                                            <div class="text-sm text-gray-500">
                                                <?php echo Security::escape(substr($client['address'], 0, 50)); ?>
                                                <?php echo strlen($client['address']) > 50 ? '...' : ''; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo Security::escape($client['email']); ?></div>
                                    <?php if ($client['phone']): ?>
                                        <div class="text-sm text-gray-500"><?php echo Security::escape($client['phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($client['status'] == STATUS_ACTIVE): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo Utils::formatDateDisplay($client['created_at'], false); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button type="button" onclick="viewClient(<?php echo htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8'); ?>)" 
                                               class="text-green-600 hover:text-green-900">
                                            Ver
                                        </button>
                                        <a href="clientForm.php?id=<?php echo $client['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            Editar
                                        </a>
                                        
                                        <?php if ($client['status'] == STATUS_ACTIVE): ?>
                                            <a href="?action=toggle_status&id=<?php echo $client['id']; ?>&new_status=<?php echo STATUS_INACTIVE; ?>" 
                                               onclick="return confirm('¿Desactivar este cliente?')"
                                               class="text-yellow-600 hover:text-yellow-900">
                                                Desactivar
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=toggle_status&id=<?php echo $client['id']; ?>&new_status=<?php echo STATUS_ACTIVE; ?>" 
                                               class="text-green-600 hover:text-green-900">
                                                Activar
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($userRole === 'admin'): ?>
                                            <a href="?action=delete&id=<?php echo $client['id']; ?>" 
                                               onclick="return confirm('¿Está seguro de eliminar este cliente? Esta acción no se puede deshacer.')"
                                               class="text-red-600 hover:text-red-900">
                                                Eliminar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Paginación -->
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="bg-white rounded-lg shadow-md p-4 mt-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        Mostrando <?php echo (($pagination['page'] - 1) * $pagination['perPage']) + 1; ?> 
                        a <?php echo min($pagination['page'] * $pagination['perPage'], $pagination['total']); ?> 
                        de <?php echo $pagination['total']; ?> clientes
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($pagination['page'] > 1): ?>
                            <a href="?page=<?php echo $pagination['page'] - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?>" 
                               class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['totalPages'], $pagination['page'] + 2); $i++): ?>
                            <?php if ($i == $pagination['page']): ?>
                                <span class="px-3 py-2 bg-blue-800 text-white rounded-md"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?>" 
                                   class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                            <a href="?page=<?php echo $pagination['page'] + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?>" 
                               class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Siguiente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    </div>

    <!-- Modal integrado para ver cliente -->
    <div id="clientModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-auto">
                
                <!-- Header del modal -->
                <div class="bg-blue-600 text-white p-4 rounded-t-lg flex justify-between items-center">
                    <h2 class="text-xl font-bold">Información del Cliente</h2>
                    <button type="button" onclick="closeClientModal()" class="text-white hover:text-gray-200 text-2xl">
                        ×
                    </button>
                </div>

                <!-- Contenido del modal -->
                <div class="p-6">
                    
                    <!-- Información principal -->
                    <div class="mb-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h1 id="modalClientName" class="text-2xl font-bold text-gray-800"></h1>
                                <div class="mt-2">
                                    <span id="modalClientStatus" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"></span>
                                </div>
                            </div>
                            <div class="text-right text-sm text-gray-500">
                                <div>ID: <span id="modalClientId"></span></div>
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
                                    <a id="modalClientEmail" href="" class="text-blue-600 hover:text-blue-800"></a>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Teléfono</label>
                                <div id="modalClientPhone" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Dirección</label>
                                <div id="modalClientAddress" class="mt-1 text-gray-900"></div>
                            </div>
                        </div>

                        <!-- Información del sistema -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                Información del Sistema
                            </h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Fecha de Registro</label>
                                <div id="modalClientCreated" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Última Actualización</label>
                                <div id="modalClientUpdated" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Estado</label>
                                <div id="modalClientStatusDesc" class="mt-1"></div>
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
                    <div class="flex justify-between items-center pt-4 border-t">
                        <div class="flex space-x-2">
                            <button type="button" id="modalEditBtn" onclick="editClientFromModal()" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Editar Cliente
                            </button>
                            <button type="button" onclick="printClientModal()" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                                Imprimir
                            </button>
                        </div>
                        <button type="button" onclick="closeClientModal()" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentClient = null;

        // Función para abrir el modal del cliente
        function viewClient(client) {
            currentClient = client;
            console.log('Abriendo modal para cliente:', client);
            
            // Llenar datos del modal
            document.getElementById('modalClientId').textContent = client.id;
            document.getElementById('modalClientName').textContent = client.name;
            
            // Email con enlace
            const emailElement = document.getElementById('modalClientEmail');
            emailElement.textContent = client.email;
            emailElement.href = 'mailto:' + client.email;
            
            // Teléfono
            const phoneElement = document.getElementById('modalClientPhone');
            if (client.phone) {
                phoneElement.innerHTML = '<a href="tel:' + client.phone + '" class="text-blue-600 hover:text-blue-800">' + client.phone + '</a>';
            } else {
                phoneElement.innerHTML = '<span class="text-gray-500 italic">No especificado</span>';
            }
            
            // Dirección
            const addressElement = document.getElementById('modalClientAddress');
            if (client.address) {
                addressElement.textContent = client.address;
            } else {
                addressElement.innerHTML = '<span class="text-gray-500 italic">No especificada</span>';
            }
            
            // Estado
            const statusElement = document.getElementById('modalClientStatus');
            const statusDescElement = document.getElementById('modalClientStatusDesc');
            
            if (client.status == '<?php echo STATUS_ACTIVE; ?>') {
                statusElement.className = 'inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800';
                statusElement.textContent = '✓ Cliente Activo';
                statusDescElement.innerHTML = '<span class="text-green-700 font-medium">Activo</span><div class="text-sm text-gray-500">El cliente puede realizar transacciones</div>';
            } else {
                statusElement.className = 'inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800';
                statusElement.textContent = '✗ Cliente Inactivo';
                statusDescElement.innerHTML = '<span class="text-red-700 font-medium">Inactivo</span><div class="text-sm text-gray-500">El cliente está deshabilitado</div>';
            }
            
            // Fechas
            document.getElementById('modalClientCreated').textContent = formatDate(client.created_at);
            document.getElementById('modalClientUpdated').textContent = client.updated_at ? formatDate(client.updated_at) : 'No actualizado';
            
            // Mostrar modal
            document.getElementById('clientModal').classList.remove('hidden');
        }

        // Función para cerrar el modal
        function closeClientModal() {
            document.getElementById('clientModal').classList.add('hidden');
            currentClient = null;
        }

        // Función para editar desde el modal
        function editClientFromModal() {
            if (currentClient) {
                window.location.href = 'clientForm.php?id=' + currentClient.id;
            }
        }

        // Función para imprimir
        function printClientModal() {
            window.print();
        }

        // Función para formatear fechas
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('clientModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeClientModal();
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeClientModal();
            }
        });
    </script>

    <style>
        @media print {
            body * { visibility: hidden; }
            #clientModal, #clientModal * { visibility: visible; }
            #clientModal { 
                position: static !important; 
                background: white !important; 
            }
            .no-print { display: none !important; }
        }
    </style>
</body>
</html>