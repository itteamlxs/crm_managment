<?php
// Página mejorada para probar configuración de email
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/email.php';
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar autenticación y permisos de administrador
$session = new Session();
if (!$session->isLoggedIn() || !$session->hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=unauthorized');
    exit;
}

$error = '';
$success = '';
$connectionStatus = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $emailService = new EmailService();
            
            if ($action === 'test_connection') {
                // Probar solo la conexión SMTP
                $result = $emailService->testConnection();
                $success = 'Conexión SMTP exitosa. La configuración es correcta.';
                $connectionStatus = true;
                
            } elseif ($action === 'test_email') {
                // Enviar email de prueba
                $testEmail = Security::sanitize($_POST['test_email'] ?? '', 'email');
                if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email de prueba no válido.');
                }
                
                $result = $emailService->sendTestEmail($testEmail, 'basic');
                $success = "Email de prueba enviado correctamente a: {$testEmail}";
                
            } elseif ($action === 'send_quote') {
                // Enviar cotización específica
                $quoteId = (int)($_POST['quote_id'] ?? 0);
                if ($quoteId <= 0) {
                    throw new Exception('ID de cotización no válido.');
                }
                
                $attachPdf = isset($_POST['attach_pdf']) && $_POST['attach_pdf'] === '1';
                $result = $emailService->sendQuoteEmail($quoteId, $attachPdf);
                $success = "Cotización enviada correctamente a: {$result['to']}";
                
                // Cambiar estado si fue exitoso
                require_once __DIR__ . '/quoteModel.php';
                $quoteModel = new QuoteModel();
                $quoteModel->changeStatus($quoteId, QUOTE_STATUS_SENT);
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            $connectionStatus = false;
        }
    }
}

// Obtener configuración actual
try {
    $emailService = new EmailService();
    $isConfigured = $emailService->isConfigured();
} catch (Exception $e) {
    $isConfigured = false;
}

// Obtener cotizaciones para testing
try {
    require_once __DIR__ . '/quoteController.php';
    $quoteController = new QuoteController();
    $quotesResult = $quoteController->listQuotes('', null, null, null, null, 1, 20);
    $testQuotes = $quotesResult['quotes'] ?? [];
} catch (Exception $e) {
    $testQuotes = [];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Pruebas de Email - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .status-indicator { transition: all 0.3s ease; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php require_once dirname(__DIR__, 2) . '/core/nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">🧪 Centro de Pruebas de Email</h1>
                    <p class="text-gray-600 mt-2">Verificar y probar configuración SMTP</p>
                </div>
                <div class="flex space-x-2">
                    <a href="<?php echo BASE_URL; ?>/modules/settings/settingsView.php?tab=email" 
                       class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        ⚙️ Configurar SMTP
                    </a>
                    <a href="quoteList.php" 
                       class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        ← Cotizaciones
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <div class="flex items-center">
                    <span class="text-red-500 mr-2">❌</span>
                    <div>
                        <strong>Error:</strong> <?php echo Security::escape($error); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <div class="flex items-center">
                    <span class="text-green-500 mr-2">✅</span>
                    <div><?php echo Security::escape($success); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Estado de configuración -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📊 Estado del Sistema de Email</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="status-indicator bg-gray-50 p-4 rounded-lg border-l-4 <?php echo $isConfigured ? 'border-green-500' : 'border-red-500'; ?>">
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full mr-3 <?php echo $isConfigured ? 'bg-green-500' : 'bg-red-500'; ?> <?php echo !$isConfigured ? 'pulse' : ''; ?>"></span>
                        <div>
                            <div class="font-medium">Configuración SMTP</div>
                            <div class="text-sm text-gray-600"><?php echo $isConfigured ? 'Configurado' : 'Pendiente'; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="status-indicator bg-gray-50 p-4 rounded-lg border-l-4 <?php echo extension_loaded('openssl') ? 'border-green-500' : 'border-yellow-500'; ?>">
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full mr-3 <?php echo extension_loaded('openssl') ? 'bg-green-500' : 'bg-yellow-500'; ?>"></span>
                        <div>
                            <div class="font-medium">OpenSSL</div>
                            <div class="text-sm text-gray-600"><?php echo extension_loaded('openssl') ? 'Disponible' : 'No disponible'; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="status-indicator bg-gray-50 p-4 rounded-lg border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full mr-3 bg-blue-500"></span>
                        <div>
                            <div class="font-medium">PHPMailer</div>
                            <div class="text-sm text-gray-600"><?php echo class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'Cargado' : 'No encontrado'; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="status-indicator bg-gray-50 p-4 rounded-lg border-l-4 <?php echo $connectionStatus === true ? 'border-green-500' : ($connectionStatus === false ? 'border-red-500' : 'border-gray-300'); ?>">
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full mr-3 <?php echo $connectionStatus === true ? 'bg-green-500' : ($connectionStatus === false ? 'bg-red-500' : 'bg-gray-300'); ?>"></span>
                        <div>
                            <div class="font-medium">Conexión SMTP</div>
                            <div class="text-sm text-gray-600">
                                <?php 
                                if ($connectionStatus === true) echo 'Exitosa';
                                elseif ($connectionStatus === false) echo 'Falló';
                                else echo 'No probada';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isConfigured): ?>
                <!-- Prueba rápida de conexión -->
                <div class="border-t pt-4">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape($session->getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="test_connection">
                        <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                            🔌 Probar Conexión SMTP
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="border-t pt-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-4">
                        <h3 class="font-medium text-yellow-800 mb-2">⚠️ Configuración Requerida</h3>
                        <p class="text-yellow-700 text-sm">
                            Configure primero los parámetros SMTP para poder enviar emails.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isConfigured): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Prueba de email básico -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📧 Enviar Email de Prueba</h2>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($session->getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="test_email">
                    
                    <div class="mb-4">
                        <label for="test_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email de Destino <span class="text-red-500">*</span>
                        </label>
                        <input type="email" 
                               id="test_email" 
                               name="test_email" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="test@ejemplo.com"
                               required>
                        <p class="text-sm text-gray-500 mt-1">
                            Se enviará un email completo con información de diagnóstico
                        </p>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        🚀 Enviar Email de Prueba
                    </button>
                </form>
            </div>
            
            <!-- Prueba con cotización real -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📋 Enviar Cotización de Prueba</h2>
                
                <?php if (!empty($testQuotes)): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape($session->getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="send_quote">
                        
                        <div class="mb-4">
                            <label for="quote_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Seleccionar Cotización <span class="text-red-500">*</span>
                            </label>
                            <select id="quote_id" 
                                    name="quote_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    onchange="updateQuoteInfo()"
                                    required>
                                <option value="">Seleccione una cotización</option>
                                <?php foreach ($testQuotes as $quote): ?>
                                    <option value="<?php echo $quote['id']; ?>" 
                                            data-client="<?php echo Security::escape($quote['client_name'] ?? ''); ?>"
                                            data-email="<?php echo Security::escape($quote['client_email'] ?? ''); ?>"
                                            data-total="<?php echo $quote['total_amount']; ?>"
                                            data-number="<?php echo Security::escape($quote['quote_number']); ?>">
                                        <?php echo Security::escape($quote['quote_number']); ?> - 
                                        <?php echo Security::escape($quote['client_name'] ?? 'Sin cliente'); ?> 
                                        ($<?php echo number_format($quote['total_amount'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="quote-info" class="mb-4 p-3 bg-gray-50 rounded hidden">
                            <h4 class="font-medium text-gray-800 mb-2">Información de la Cotización:</h4>
                            <div id="quote-details" class="text-sm text-gray-600"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="attach_pdf" 
                                       value="1" 
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Adjuntar PDF de la cotización</span>
                            </label>
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            📨 Enviar Cotización por Email
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No hay cotizaciones disponibles para prueba</p>
                        <a href="quoteForm.php" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
                            Crear una cotización →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historial de emails enviados -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📨 Historial Reciente de Emails</h2>
            
            <?php
            try {
                $emailService = new EmailService();
                $db = new Database();
                $recentEmails = $db->select("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 10");
            } catch (Exception $e) {
                $recentEmails = [];
            }
            ?>
            
            <?php if (!empty($recentEmails)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotización</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asunto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentEmails as $email): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo Security::escape($email['recipient_email']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo Security::escape(substr($email['subject'], 0, 50)); ?>
                                        <?php echo strlen($email['subject']) > 50 ? '...' : ''; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('d/m/Y H:i', strtotime($email['sent_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $email['status'] === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $email['status'] === 'sent' ? '✅ Enviado' : '❌ Error'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No hay emails registrados todavía</p>
                    <p class="text-sm mt-1">Los emails enviados aparecerán aquí</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function updateQuoteInfo() {
            const select = document.getElementById('quote_id');
            const infoDiv = document.getElementById('quote-info');
            const detailsDiv = document.getElementById('quote-details');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const client = option.getAttribute('data-client');
                const email = option.getAttribute('data-email');
                const total = option.getAttribute('data-total');
                const number = option.getAttribute('data-number');
                
                if (email) {
                    detailsDiv.innerHTML = `
                        <div class="grid grid-cols-2 gap-4">
                            <div><strong>Número:</strong> ${number}</div>
                            <div><strong>Total:</strong> ${parseFloat(total).toFixed(2)}</div>
                            <div><strong>Cliente:</strong> ${client}</div>
                            <div><strong>Email:</strong> <span class="text-blue-600">${email}</span></div>
                        </div>
                    `;
                    infoDiv.classList.remove('hidden');
                } else {
                    detailsDiv.innerHTML = `
                        <div class="text-red-600 flex items-center">
                            <span class="mr-2">⚠️</span>
                            <div>
                                <strong>Problema:</strong> Este cliente no tiene email registrado<br>
                                <small>Edite el cliente para agregar un email antes de enviar</small>
                            </div>
                        </div>
                    `;
                    infoDiv.classList.remove('hidden');
                }
            } else {
                infoDiv.classList.add('hidden');
            }
        }

        // Auto-refresh del estado cada 30 segundos
        setInterval(function() {
            // Solo recargar si no hay formularios activos
            const activeForm = document.querySelector('form:focus-within');
            if (!activeForm) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>