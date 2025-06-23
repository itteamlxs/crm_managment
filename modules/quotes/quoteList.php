<?php
// Vista del listado de cotizaciones - VERSIÓN CORREGIDA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__, 2) . '/core/session.php'; 
    require_once dirname(__DIR__) . '/quotes/quoteController.php';
    require_once dirname(__DIR__) . '/quotes/quoteModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new QuoteController();
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
$clientId = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? (int)$_GET['client_id'] : null;
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Procesar exportar CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $controller->exportQuotesCsv($search, $clientId, $status, $dateFrom, $dateTo);
    exit;
}

// Procesar cambio de estado
if (isset($_GET['action']) && $_GET['action'] === 'change_status' && isset($_GET['id']) && isset($_GET['new_status'])) {
    $quoteId = (int)$_GET['id'];
    $newStatus = (int)$_GET['new_status'];
    $result = $controller->changeQuoteStatus($quoteId, $newStatus);
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Procesar eliminación
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $quoteId = (int)$_GET['id'];
    $result = $controller->deleteQuote($quoteId);
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Obtener listado de cotizaciones
$listResult = $controller->listQuotes($search, $clientId, $status, $dateFrom, $dateTo, $page, $perPage);
if (isset($listResult['error'])) {
    $error = $listResult['error'];
    $quotes = [];
    $pagination = ['total' => 0, 'page' => 1, 'totalPages' => 1];
} else {
    $quotes = $listResult['quotes'];
    $pagination = [
        'total' => $listResult['total'],
        'page' => $listResult['page'],
        'totalPages' => $listResult['totalPages'],
        'perPage' => $listResult['perPage']
    ];
}

// Obtener datos para filtros
$clients = $controller->getActiveClients();
$availableStatuses = $controller->getAvailableStatuses();

// Manejar mensajes de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success = 'Cotización creada correctamente.';
            break;
        case 'updated':
            $success = 'Cotización actualizada correctamente.';
            break;
        case 'deleted':
            $success = 'Cotización eliminada correctamente.';
            break;
        case 'enviada':
            $success = 'Cotización marcada como enviada.';
            break;
        case 'aprobada':
            $success = 'Cotización aprobada correctamente.';
            break;
        case 'rechazada':
            $success = 'Cotización rechazada.';
            break;
        case 'cancelada':
            $success = 'Cotización cancelada.';
            break;
        case 'quote_sent':
            $email = $_GET['email'] ?? '';
            $success = 'Cotización enviada exitosamente por email a: ' . Security::escape($email);
            break;
    }
}

// Manejar mensajes de error
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_request':
            $error = 'Solicitud no válida.';
            break;
        case 'invalid_token':
            $error = 'Token de seguridad inválido.';
            break;
        case 'invalid_quote':
            $error = 'Cotización no válida.';
            break;
        case 'quote_not_found':
            $error = 'Cotización no encontrada.';
            break;
        case 'invalid_status':
            $message = $_GET['message'] ?? 'Estado de cotización no válido';
            $error = Security::escape($message);
            break;
        case 'email_not_configured':
            $error = 'Email no configurado. Configure SMTP en Configuración del Sistema.';
            break;
        case 'send_failed':
            $message = $_GET['message'] ?? 'Error desconocido';
            $error = 'Error al enviar email: ' . Security::escape($message);
            break;
    }
}

$userRole = $controller->getUserRole();

// Función helper para obtener clase CSS del estado
function getStatusClass($status) {
    switch ($status) {
        case QUOTE_STATUS_DRAFT:
            return 'bg-gray-100 text-gray-800';
        case QUOTE_STATUS_SENT:
            return 'bg-blue-100 text-blue-800';
        case QUOTE_STATUS_APPROVED:
            return 'bg-green-100 text-green-800';
        case QUOTE_STATUS_REJECTED:
            return 'bg-red-100 text-red-800';
        case QUOTE_STATUS_EXPIRED:
            return 'bg-yellow-100 text-yellow-800';
        case QUOTE_STATUS_CANCELLED:
            return 'bg-gray-100 text-gray-600';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Función para escapar datos para atributos HTML y JavaScript
function escapeForDataAttribute($value) {
    if ($value === null) {
        return '';
    }
    // Escapar comillas y saltos de línea para atributos HTML
    return htmlspecialchars(str_replace(["\r\n", "\n", "\r"], ' ', $value), ENT_QUOTES, 'UTF-8');
}

// Función para limpiar completamente cualquier string problemático
function cleanString($value) {
    if ($value === null) {
        return '';
    }
    // Remover caracteres problemáticos
    $value = preg_replace('/[\r\n\t]/', ' ', $value);
    $value = preg_replace('/["\']/', '', $value);
    return trim($value);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cotizaciones - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <?php require_once dirname(__DIR__, 2) . '/core/nav.php'; ?>
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-bold text-gray-800">Gestión de Cotizaciones</h1>
                <div class="flex space-x-2">
                    <a href="quoteForm.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        + Nueva Cotización
                    </a>
                    <a href="emailTest.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                        Pruebas Email
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/products/productList.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Productos
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/clients/clientList.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        Clientes
                    </a>
                </div>
            </div>

            <!-- Filtros y búsqueda -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        value="<?php echo Security::escape($search); ?>"
                        placeholder="Número, cliente, email..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select id="client_id" name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos los clientes</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $clientId === $client['id'] ? 'selected' : ''; ?>>
                                <?php echo Security::escape($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos los estados</option>
                        <?php foreach ($availableStatuses as $statusValue => $statusName): ?>
                            <option value="<?php echo $statusValue; ?>" <?php echo $status === $statusValue ? 'selected' : ''; ?>>
                                <?php echo Security::escape($statusName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                    <input 
                        type="date" 
                        id="date_from" 
                        name="date_from" 
                        value="<?php echo Security::escape($dateFrom); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                    <input 
                        type="date" 
                        id="date_to" 
                        name="date_to" 
                        value="<?php echo Security::escape($dateTo); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 whitespace-nowrap">
                        Buscar
                    </button>
                    <a href="quoteList.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 whitespace-nowrap">
                        Limpiar
                    </a>
                    <a href="?action=export<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $clientId ? '&client_id=' . $clientId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 whitespace-nowrap">
                        Exportar CSV
                    </a>
                </div>
            </form>
        </div>

        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
                <?php if ($error === 'Email no configurado. Configure SMTP en Configuración del Sistema.'): ?>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>/modules/settings/settingsView.php?tab=email" class="text-red-800 underline">
                            Configurar Email aquí
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo Security::escape($success); ?>
                <?php if (isset($_GET['success']) && $_GET['success'] === 'quote_sent'): ?>
                    <div class="mt-2">
                        <a href="emailTest.php" class="text-green-800 underline text-sm">
                            Ver historial de emails
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($pagination['total']); ?></div>
                    <div class="text-gray-600">Total Cotizaciones</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        <?php 
                        $approvedQuotes = array_filter($quotes, fn($q) => $q['status'] == QUOTE_STATUS_APPROVED);
                        echo count($approvedQuotes);
                        ?>
                    </div>
                    <div class="text-gray-600">Aprobadas</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">
                        <?php 
                        $pendingQuotes = array_filter($quotes, fn($q) => in_array($q['status'], [QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT]));
                        echo count($pendingQuotes);
                        ?>
                    </div>
                    <div class="text-gray-600">Pendientes</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        $<?php 
                        $totalValue = array_sum(array_column($quotes, 'total_amount'));
                        echo number_format($totalValue, 2);
                        ?>
                    </div>
                    <div class="text-gray-600">Valor Total</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600">
                        Página <?php echo $pagination['page']; ?> de <?php echo $pagination['totalPages']; ?>
                    </div>
                    <div class="text-gray-600">Paginación</div>
                </div>
            </div>
        </div>

        <!-- Tabla de cotizaciones -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if (empty($quotes)): ?>
                <div class="p-8 text-center text-gray-500">
                    <p class="text-xl">No se encontraron cotizaciones</p>
                    <p class="mt-2">Intente cambiar los filtros o crear una nueva cotización</p>
                    <a href="quoteForm.php" class="inline-block mt-4 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700">
                        Crear Primera Cotización
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotización</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fechas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($quotes as $quote): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo Security::escape($quote['quote_number']); ?>
                                            </div>
                                            <?php if ($quote['notes']): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo Security::escape(substr($quote['notes'], 0, 50)); ?>
                                                    <?php echo strlen($quote['notes']) > 50 ? '...' : ''; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo Security::escape($quote['client_name'] ?? 'Cliente eliminado'); ?>
                                            </div>
                                            <?php if ($quote['client_email']): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo Security::escape($quote['client_email']); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-sm text-red-500">Sin email</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <div><strong>Creada:</strong> <?php echo Utils::formatDateDisplay($quote['quote_date']); ?></div>
                                            <div class="<?php echo strtotime($quote['valid_until']) < time() ? 'text-red-600 font-semibold' : 'text-gray-500'; ?>">
                                                <strong>Válida hasta:</strong> <?php echo Utils::formatDateDisplay($quote['valid_until']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <div class="text-lg font-bold">$<?php echo number_format($quote['total_amount'], 2); ?></div>
                                            <?php if ($quote['discount_percent'] > 0): ?>
                                                <div class="text-xs text-green-600">
                                                    Desc. <?php echo number_format($quote['discount_percent'], 1); ?>%
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo getStatusClass($quote['status']); ?>">
                                            <?php 
                                            $quoteModel = new QuoteModel();
                                            echo Security::escape($quoteModel->getStatusName($quote['status'])); 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex flex-wrap gap-2">
                                            <!-- Ver detalles - ENLACE DIRECTO COMO EDIT -->
                                            <a href="quoteView.php?id=<?php echo $quote['id']; ?>" 
                                               class="text-green-600 hover:text-green-900">
                                                Ver
                                            </a>
                                            
                                            <!-- PDF -->
                                            <a href="printQuote.php?id=<?php echo $quote['id']; ?>" 
                                               target="_blank"
                                               class="text-purple-600 hover:text-purple-900"
                                               title="Generar PDF">
                                                PDF
                                            </a>
                                            
                                            <!-- ENVIAR EMAIL SIMPLIFICADO -->
                                            <?php if (($quote['status'] == QUOTE_STATUS_DRAFT || $quote['status'] == QUOTE_STATUS_SENT) && !empty($quote['client_email'])): ?>
                                                <form method="POST" action="sendQuote.php" style="display: inline;" 
                                                      onsubmit="return confirmSendEmail('<?php echo Security::escape($quote['client_email']); ?>')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                                                    <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
                                                    <input type="hidden" name="attach_pdf" value="0">
                                                    <input type="hidden" name="client_email" value="<?php echo Security::escape($quote['client_email']); ?>">
                                                    <button type="submit" 
                                                            class="text-blue-600 hover:text-blue-900 send-email-btn">
                                                        Enviar Email
                                                    </button>
                                                </form>
                                            <?php elseif (($quote['status'] == QUOTE_STATUS_DRAFT || $quote['status'] == QUOTE_STATUS_SENT) && empty($quote['client_email'])): ?>
                                                <span class="text-gray-400" title="Cliente sin email">
                                                    Sin Email
                                                </span>
                                                <a href="<?php echo BASE_URL; ?>/modules/clients/clientForm.php?id=<?php echo $quote['client_id']; ?>" 
                                                   class="text-orange-600 hover:text-orange-800 text-xs">
                                                    Agregar
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Editar -->
                                            <?php if ($controller->canEditQuote($quote['status'])): ?>
                                                <a href="quoteForm.php?id=<?php echo $quote['id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-900">
                                                    Editar
                                                </a>
                                            <?php endif; ?>

                                            <!-- Acciones de estado -->
                                            <?php if ($quote['status'] == QUOTE_STATUS_DRAFT): ?>
                                                <a href="?action=change_status&id=<?php echo $quote['id']; ?>&new_status=<?php echo QUOTE_STATUS_SENT; ?>" 
                                                   class="text-blue-600 hover:text-blue-900"
                                                   onclick="return confirm('¿Marcar como enviada?')">
                                                    Marcar Enviada
                                                </a>
                                            <?php elseif ($quote['status'] == QUOTE_STATUS_SENT): ?>
                                                <a href="?action=change_status&id=<?php echo $quote['id']; ?>&new_status=<?php echo QUOTE_STATUS_APPROVED; ?>" 
                                                   class="text-green-600 hover:text-green-900"
                                                   onclick="return confirm('¿Marcar como aprobada?')">
                                                    Aprobar
                                                </a>
                                                <a href="?action=change_status&id=<?php echo $quote['id']; ?>&new_status=<?php echo QUOTE_STATUS_REJECTED; ?>" 
                                                   class="text-red-600 hover:text-red-900"
                                                   onclick="return confirm('¿Marcar como rechazada?')">
                                                    Rechazar
                                                </a>
                                            <?php endif; ?>

                                            <!-- Eliminar (solo admin) -->
                                            <?php if ($userRole === 'admin' && $controller->canDeleteQuote($quote['status'])): ?>
                                                <a href="?action=delete&id=<?php echo $quote['id']; ?>" 
                                                   class="text-red-600 hover:text-red-900"
                                                   onclick="return confirm('¿Está seguro de eliminar esta cotización? Esta acción no se puede deshacer.')">
                                                    Eliminar
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paginación -->
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="bg-white rounded-lg shadow-md p-4 mt-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        Mostrando <?php echo (($pagination['page'] - 1) * $pagination['perPage']) + 1; ?> 
                        a <?php echo min($pagination['page'] * $pagination['perPage'], $pagination['total']); ?> 
                        de <?php echo $pagination['total']; ?> cotizaciones
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($pagination['page'] > 1): ?>
                            <a href="?page=<?php echo $pagination['page'] - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $clientId ? '&client_id=' . $clientId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>" 
                               class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['totalPages'], $pagination['page'] + 2); $i++): ?>
                            <?php if ($i == $pagination['page']): ?>
                                <span class="px-3 py-2 bg-blue-800 text-white rounded-md"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $clientId ? '&client_id=' . $clientId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>" 
                                   class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                            <a href="?page=<?php echo $pagination['page'] + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $clientId ? '&client_id=' . $clientId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>" 
                               class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Siguiente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Ayuda sobre estados -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
            <h3 class="text-lg font-medium text-blue-800 mb-2">Estados de Cotización</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-blue-700 text-sm">
                <div>
                    <strong>Borrador:</strong> En proceso de creación
                </div>
                <div>
                    <strong>Enviada:</strong> Enviada al cliente
                </div>
                <div>
                    <strong>Aprobada:</strong> Aceptada por el cliente
                </div>
                <div>
                    <strong>Rechazada:</strong> Rechazada por el cliente
                </div>
                <div>
                    <strong>Vencida:</strong> Fecha de validez expirada
                </div>
                <div>
                    <strong>Cancelada:</strong> Cancelada internamente
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver cotización -->
    <div id="quoteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-auto">
                
                <!-- Header del modal -->
                <div class="bg-blue-600 text-white p-4 rounded-t-lg flex justify-between items-center">
                    <h2 class="text-xl font-bold">Información de la Cotización</h2>
                    <button type="button" id="closeModalBtn" class="text-white hover:text-gray-200 text-2xl">
                        ×
                    </button>
                </div>

                <!-- Contenido del modal -->
                <div class="p-6">
                    
                    <!-- Información principal -->
                    <div class="mb-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h1 id="modalQuoteNumber" class="text-2xl font-bold text-gray-800"></h1>
                                <div class="mt-2 flex space-x-2">
                                    <span id="modalQuoteStatus" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-blue-600" id="modalQuoteTotal"></div>
                                <div class="text-sm text-gray-500">Total</div>
                            </div>
                        </div>
                    </div>

                    <!-- Detalles de la cotización -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        
                        <!-- Información del cliente -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                Información del Cliente
                            </h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Cliente</label>
                                <div id="modalClientName" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Email</label>
                                <div id="modalClientEmail" class="mt-1 text-gray-900"></div>
                            </div>
                        </div>

                        <!-- Información de fechas -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                Fechas y Montos
                            </h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Fecha de Cotización</label>
                                <div id="modalQuoteDate" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Válida Hasta</label>
                                <div id="modalValidUntil" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Subtotal</label>
                                <div id="modalSubtotal" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Impuestos</label>
                                <div id="modalTaxAmount" class="mt-1 text-gray-900"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Notas -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-3">Notas</h3>
                        <div id="modalNotes" class="text-gray-700 bg-gray-50 p-3 rounded"></div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-between items-center pt-4 border-t">
                        <div class="flex space-x-2">
                            <button type="button" id="modalEditBtn" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Editar Cotización
                            </button>
                            <button type="button" id="modalPrintBtn" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                                Generar PDF
                            </button>
                        </div>
                        <button type="button" id="modalCloseBtn" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript CORREGIDO -->
    <script>
        let currentQuote = null;
        
        // Constantes de estado
        const QUOTE_STATUS = {
            DRAFT: <?php echo QUOTE_STATUS_DRAFT; ?>,
            SENT: <?php echo QUOTE_STATUS_SENT; ?>,
            APPROVED: <?php echo QUOTE_STATUS_APPROVED; ?>,
            REJECTED: <?php echo QUOTE_STATUS_REJECTED; ?>,
            EXPIRED: <?php echo QUOTE_STATUS_EXPIRED; ?>,
            CANCELLED: <?php echo QUOTE_STATUS_CANCELLED; ?>
        };

        // Función para confirmar envío de email
        function confirmSendEmail(email) {
            const confirmed = confirm('¿Enviar cotización por email a ' + email + '?');
            if (confirmed) {
                // Mostrar estado de carga en el botón
                event.target.textContent = 'Enviando...';
                event.target.disabled = true;
                event.target.style.opacity = '0.7';
            }
            return confirmed;
        }

        // Función para abrir el modal de la cotización
        function viewQuote(quoteData) {
            currentQuote = quoteData;
            
            // Llenar datos del modal
            document.getElementById('modalQuoteNumber').textContent = quoteData.quoteNumber || '';
            document.getElementById('modalQuoteTotal').textContent = ' + parseFloat(quoteData.totalAmount || 0).toFixed(2);
            
            // Cliente
            document.getElementById('modalClientName').textContent = quoteData.clientName || 'Cliente eliminado';
            document.getElementById('modalClientEmail').textContent = quoteData.clientEmail || 'N/A';
            
            // Fechas
            document.getElementById('modalQuoteDate').textContent = formatDate(quoteData.quoteDate);
            document.getElementById('modalValidUntil').textContent = formatDate(quoteData.validUntil);
            
            // Montos
            document.getElementById('modalSubtotal').textContent = ' + parseFloat(quoteData.subtotal || 0).toFixed(2);
            document.getElementById('modalTaxAmount').textContent = ' + parseFloat(quoteData.taxAmount || 0).toFixed(2);
            
            // Estado
            const statusElement = document.getElementById('modalQuoteStatus');
            statusElement.textContent = getStatusName(quoteData.status);
            statusElement.className = 'inline-flex px-3 py-1 text-sm font-semibold rounded-full ' + getStatusClass(quoteData.status);
            
            // Notas
            const notesElement = document.getElementById('modalNotes');
            if (quoteData.notes && quoteData.notes.trim()) {
                // Restaurar saltos de línea que fueron convertidos a espacios
                notesElement.innerHTML = quoteData.notes.replace(/\n/g, '<br>');
            } else {
                notesElement.innerHTML = '<em class="text-gray-500">Sin notas</em>';
            }
            
            // Mostrar modal
            document.getElementById('quoteModal').classList.remove('hidden');
        }

        // Función para cerrar el modal
        function closeQuoteModal() {
            document.getElementById('quoteModal').classList.add('hidden');
            currentQuote = null;
        }

        // Función para formatear fechas
        function formatDate(dateString) {
            if (!dateString) return '';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('es-ES');
            } catch (e) {
                return dateString;
            }
        }

        // Función para obtener nombre del estado
        function getStatusName(status) {
            const statusNames = {};
            statusNames[QUOTE_STATUS.DRAFT] = 'Borrador';
            statusNames[QUOTE_STATUS.SENT] = 'Enviada';
            statusNames[QUOTE_STATUS.APPROVED] = 'Aprobada';
            statusNames[QUOTE_STATUS.REJECTED] = 'Rechazada';
            statusNames[QUOTE_STATUS.EXPIRED] = 'Vencida';
            statusNames[QUOTE_STATUS.CANCELLED] = 'Cancelada';
            
            return statusNames[status] || 'Desconocido';
        }

        // Función para obtener clase CSS del estado
        function getStatusClass(status) {
            const statusClasses = {};
            statusClasses[QUOTE_STATUS.DRAFT] = 'bg-gray-100 text-gray-800';
            statusClasses[QUOTE_STATUS.SENT] = 'bg-blue-100 text-blue-800';
            statusClasses[QUOTE_STATUS.APPROVED] = 'bg-green-100 text-green-800';
            statusClasses[QUOTE_STATUS.REJECTED] = 'bg-red-100 text-red-800';
            statusClasses[QUOTE_STATUS.EXPIRED] = 'bg-yellow-100 text-yellow-800';
            statusClasses[QUOTE_STATUS.CANCELLED] = 'bg-gray-100 text-gray-600';
            
            return statusClasses[status] || 'bg-gray-100 text-gray-800';
        }

        // Inicialización cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando cotizaciones...');
            
            // Event listeners para botones "Ver cotización" - RESTAURADO EL MÉTODO ORIGINAL
            document.querySelectorAll('.view-quote-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const quoteData = JSON.parse(this.getAttribute('data-quote'));
                    viewQuote(quoteData);
                });
            });

            // Event listeners para botones del modal
            document.getElementById('closeModalBtn').addEventListener('click', closeQuoteModal);
            document.getElementById('modalCloseBtn').addEventListener('click', closeQuoteModal);
            
            document.getElementById('modalEditBtn').addEventListener('click', function() {
                if (currentQuote) {
                    window.location.href = 'quoteForm.php?id=' + currentQuote.id;
                }
            });
            
            document.getElementById('modalPrintBtn').addEventListener('click', function() {
                if (currentQuote) {
                    window.open('printQuote.php?id=' + currentQuote.id, '_blank');
                }
            });
            
            // Cerrar modal al hacer clic fuera
            document.getElementById('quoteModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeQuoteModal();
                }
            });

            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeQuoteModal();
                }
            });

            console.log('Inicialización completada');
        });
    </script>
</body>
</html>