<?php
// Vista modal independiente para mostrar detalles de cotización
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/quotes/quoteController.php';
    require_once dirname(__DIR__) . '/quotes/quoteModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de cotización no válido.');
}

$quoteId = (int)$_GET['id'];

try {
    // Instanciar controlador
    $controller = new QuoteController();
    
    // Verificar autenticación
    if (!$controller->isAuthenticated()) {
        die('Sesión expirada. Recargue la página principal.');
    }
    
    // Obtener datos de la cotización
    $result = $controller->getQuoteById($quoteId);
    if (isset($result['error'])) {
        die('Error: ' . $result['error']);
    }
    
    $quote = $result['quote'];
    $quoteModel = new QuoteModel();
    
} catch (Exception $e) {
    die('Error al obtener datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Función para obtener clase CSS del estado
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización <?php echo Security::escape($quote['quote_number']); ?> - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <?php require_once dirname(__DIR__, 2) . '/core/nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Breadcrumb y botón de regreso -->
        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="hover:text-blue-600">Dashboard</a>
                <span>›</span>
                <a href="quoteList.php" class="hover:text-blue-600">Cotizaciones</a>
                <span>›</span>
                <span class="text-gray-900 font-medium"><?php echo Security::escape($quote['quote_number']); ?></span>
            </nav>
            
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800">
                    Cotización <?php echo Security::escape($quote['quote_number']); ?>
                </h1>
                <a href="quoteList.php" 
                   class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                    ← Volver al Listado
                </a>
            </div>
        </div>
        <!-- Header principal -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2"><?php echo Security::escape($quote['quote_number']); ?></h2>
                    <div class="flex items-center space-x-3">
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php echo getStatusClass($quote['status']); ?>">
                            <?php echo Security::escape($quoteModel->getStatusName($quote['status'])); ?>
                        </span>
                        <?php 
                        $validUntil = new DateTime($quote['valid_until']);
                        $today = new DateTime();
                        if ($validUntil < $today): 
                        ?>
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800">
                                ⚠️ Vencida
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">
                        $<?php echo number_format($quote['total_amount'], 2); ?>
                    </div>
                    <div class="text-sm text-gray-500">Total de la cotización</div>
                    <?php if ($quote['discount_percent'] > 0): ?>
                        <div class="text-sm text-green-600 mt-1">
                            Descuento aplicado: <?php echo number_format($quote['discount_percent'], 1); ?>%
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Botones de acción principales -->
            <div class="flex flex-wrap gap-3 pt-4 border-t">
                <a href="quoteForm.php?id=<?php echo $quote['id']; ?>" 
                   class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                    ✏️ Editar Cotización
                </a>
                
                <a href="printQuote.php?id=<?php echo $quote['id']; ?>" 
                   target="_blank"
                   class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition-colors">
                    📄 Generar PDF
                </a>
                
                <?php if (!empty($quote['client_email']) && ($quote['status'] == QUOTE_STATUS_DRAFT || $quote['status'] == QUOTE_STATUS_SENT)): ?>
                <form method="POST" action="sendQuote.php" style="display: inline;" 
                      onsubmit="return confirmSendEmail('<?php echo Security::escape($quote['client_email']); ?>')">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                    <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
                    <input type="hidden" name="attach_pdf" value="1">
                    <input type="hidden" name="client_email" value="<?php echo Security::escape($quote['client_email']); ?>">
                    <button type="submit" 
                            class="bg-purple-600 text-white px-6 py-2 rounded-md hover:bg-purple-700 transition-colors">
                        📧 Enviar por Email
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if ($quote['status'] == QUOTE_STATUS_DRAFT): ?>
                    <a href="quoteList.php?action=change_status&id=<?php echo $quote['id']; ?>&new_status=<?php echo QUOTE_STATUS_SENT; ?>" 
                       onclick="return confirm('¿Marcar como enviada?')"
                       class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                        ↗️ Marcar como Enviada
                    </a>
                <?php elseif ($quote['status'] == QUOTE_STATUS_SENT): ?>
                    <a href="quoteList.php?action=change_status&id=<?php echo $quote['id']; ?>&new_status=<?php echo QUOTE_STATUS_APPROVED; ?>" 
                       onclick="return confirm('¿Marcar como aprobada?')"
                       class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition-colors">
                        ✅ Aprobar
                    </a>
                    <a href="quoteList.php?action=change_status&id=<?php echo $quote['id']; ?>&new_status=<?php echo QUOTE_STATUS_REJECTED; ?>" 
                       onclick="return confirm('¿Marcar como rechazada?')"
                       class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700 transition-colors">
                        ❌ Rechazar
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <!-- Contenido principal -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-500">
                    <h3 class="font-semibold text-blue-900 mb-2">💰 Total</h3>
                    <div class="text-2xl font-bold text-blue-600">
                        $<?php echo number_format($quote['total_amount'], 2); ?>
                    </div>
                    <?php if ($quote['discount_percent'] > 0): ?>
                        <div class="text-sm text-green-600 mt-1">
                            Desc. <?php echo number_format($quote['discount_percent'], 1); ?>%
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg border-l-4 border-green-500">
                    <h3 class="font-semibold text-green-900 mb-2">📅 Válida hasta</h3>
                    <div class="text-lg font-medium text-green-700">
                        <?php echo Utils::formatDateDisplay($quote['valid_until'], false); ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <?php 
                        $validUntil = new DateTime($quote['valid_until']);
                        $today = new DateTime();
                        $diff = $today->diff($validUntil);
                        
                        if ($validUntil < $today) {
                            echo '<span class="text-red-600 font-semibold">⚠️ Vencida hace ' . $diff->days . ' días</span>';
                        } else {
                            echo 'Válida por ' . $diff->days . ' días más';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="bg-purple-50 p-4 rounded-lg border-l-4 border-purple-500">
                    <h3 class="font-semibold text-purple-900 mb-2">📋 Creada</h3>
                    <div class="text-lg font-medium text-purple-700">
                        <?php echo Utils::formatDateDisplay($quote['quote_date'], false); ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <?php echo Utils::formatDateDisplay($quote['created_at']); ?>
                    </div>
                </div>
            </div>

            <!-- Detalles principales -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Información del cliente -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-800 border-b-2 border-blue-500 pb-2">
                        👤 Información del Cliente
                    </h3>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-600">Nombre</label>
                                <div class="text-lg font-semibold text-gray-900">
                                    <?php echo Security::escape($quote['client_name'] ?? 'Cliente eliminado'); ?>
                                </div>
                            </div>

                            <?php if ($quote['client_email']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-600">Email</label>
                                <div class="text-gray-900">
                                    <a href="mailto:<?php echo Security::escape($quote['client_email']); ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <?php echo Security::escape($quote['client_email']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($quote['client_phone']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-600">Teléfono</label>
                                <div class="text-gray-900">
                                    <a href="tel:<?php echo Security::escape($quote['client_phone']); ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <?php echo Security::escape($quote['client_phone']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($quote['client_address']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-600">Dirección</label>
                                <div class="text-gray-900">
                                    <?php echo Security::escape($quote['client_address']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Resumen financiero -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-800 border-b-2 border-green-500 pb-2">
                        💳 Resumen Financiero
                    </h3>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium">$<?php echo number_format($quote['subtotal'], 2); ?></span>
                            </div>
                            
                            <?php if ($quote['discount_percent'] > 0): ?>
                            <div class="flex justify-between items-center text-green-600">
                                <span>Descuento (<?php echo number_format($quote['discount_percent'], 1); ?>%):</span>
                                <span class="font-medium">
                                    -$<?php echo number_format(($quote['subtotal'] * $quote['discount_percent']) / 100, 2); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Impuestos:</span>
                                <span class="font-medium">$<?php echo number_format($quote['tax_amount'], 2); ?></span>
                            </div>
                            
                            <div class="border-t pt-3 flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-900">TOTAL:</span>
                                <span class="text-2xl font-bold text-blue-600">
                                    $<?php echo number_format($quote['total_amount'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos/Items -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <?php if (isset($quote['items']) && !empty($quote['items'])): ?>
                <h3 class="text-xl font-bold text-gray-800 border-b-2 border-purple-500 pb-2 mb-4">
                    📦 Productos y Servicios
                </h3>
                
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cant.</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">P. Unit.</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Desc.</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">IVA</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($quote['items'] as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">
                                            <?php echo Security::escape($item['product_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo number_format($item['quantity'], 0); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium">
                                        $<?php echo number_format($item['unit_price'], 2); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($item['discount_percent'] > 0): ?>
                                            <span class="text-green-600 font-medium">
                                                <?php echo number_format($item['discount_percent'], 1); ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium">
                                        $<?php echo number_format($item['line_total'], 2); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-xs text-gray-600">
                                            <?php echo number_format($item['tax_rate'], 1); ?>%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-blue-600">
                                        $<?php echo number_format($item['line_total_with_tax'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-500 py-8">
                    <p class="text-lg">No hay productos agregados a esta cotización</p>
                    <a href="quoteForm.php?id=<?php echo $quote['id']; ?>" 
                       class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
                        Agregar productos →
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notas -->
        <?php if (!empty($quote['notes'])): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 border-b-2 border-yellow-500 pb-2 mb-4">
                📝 Notas y Observaciones
            </h3>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="text-gray-700 whitespace-pre-line">
                    <?php echo Security::escape($quote['notes']); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Función para confirmar envío de email
        function confirmSendEmail(email) {
            return confirm('¿Enviar cotización por email a ' + email + '?');
        }
    </script>
</body>
</html>