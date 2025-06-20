<?php
// Vista del panel de configuración del sistema CRM
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/settings/settingsController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new SettingsController();
} catch (Exception $e) {
    die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar autenticación y permisos de administrador
if (!$controller->isAuthenticated() || !$controller->isAdmin()) {
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=unauthorized');
    exit;
}

// Variables para mensajes y errores
$error = '';
$success = '';

// Procesar acciones POST según el formulario enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_general':
            $result = $controller->updateGeneralSettings();
            break;
        case 'update_regional':
            $result = $controller->updateRegionalSettings();
            break;
        case 'update_tax':
            $result = $controller->updateTaxSettings();
            break;
        case 'update_email':
            $result = $controller->updateEmailSettings();
            break;
        case 'update_theme':
            $result = $controller->updateTheme();
            break;
        case 'upload_logo':
            $result = $controller->uploadLogo();
            break;
        case 'remove_logo':
            $result = $controller->removeLogo();
            break;
        case 'test_email':
            $controller->testEmailConnection();
            exit;
        case 'import_config':
            $result = $controller->importSettings();
            break;
        case 'reset_defaults':
            $result = $controller->resetToDefaults();
            break;
        default:
            $result = ['error' => 'Acción no válida.'];
    }
    
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Procesar acciones GET
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'export':
            $controller->exportSettings();
            exit;
    }
}

// Obtener configuraciones actuales
$settings = $controller->getAllSettings();
$timezones = $controller->getTimezones();
$currencies = $controller->getCurrencies();
$systemInfo = $controller->getSystemInfo();

// Manejar mensajes de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'general_updated':
            $success = 'Configuración general actualizada correctamente.';
            break;
        case 'regional_updated':
            $success = 'Configuración regional actualizada correctamente.';
            break;
        case 'tax_updated':
            $success = 'Configuración de impuestos actualizada correctamente.';
            break;
        case 'email_updated':
            $success = 'Configuración de correo actualizada correctamente.';
            break;
        case 'theme_updated':
            $success = 'Tema actualizado correctamente.';
            break;
        case 'logo_updated':
            $success = 'Logo actualizado correctamente.';
            break;
        case 'logo_removed':
            $success = 'Logo eliminado correctamente.';
            break;
        case 'config_imported':
            $success = 'Configuración importada correctamente.';
            break;
        case 'reset_completed':
            $success = 'Configuración restablecida a valores por defecto.';
            break;
    }
}

// Determinar pestaña activa
$activeTab = $_GET['tab'] ?? 'general';
?>

<!DOCTYPE html>
<html lang="<?php echo Security::escape($settings['language']); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active { 
            background-color: #3B82F6; 
            color: white; 
            border-bottom: 2px solid #1D4ED8;
        }
        .loading { 
            opacity: 0.6; 
            pointer-events: none; 
        }
        .test-result {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">⚙️ Configuración del Sistema</h1>
                    <p class="text-gray-600 mt-2">Panel de administración - Solo para administradores</p>
                </div>
                <div class="flex space-x-2">
                    <a href="<?php echo BASE_URL; ?>/modules/dashboard/dashboardView.php" 
                       class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        ← Dashboard
                    </a>
                    <a href="?action=export" 
                       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        📥 Exportar Config
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                ✅ <?php echo Security::escape($success); ?>
            </div>
        <?php endif; ?>

        <!-- Navegación por pestañas -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6 pt-4">
                    <button onclick="showTab('general')" class="tab-button py-2 px-1 border-b-2 font-medium text-sm <?php echo $activeTab === 'general' ? 'active' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        🏢 General
                    </button>
                    <button onclick="showTab('regional')" class="tab-button py-2 px-1 border-b-2 font-medium text-sm <?php echo $activeTab === 'regional' ? 'active' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        🌍 Regional
                    </button>
                    <button onclick="showTab('tax')" class="tab-button py-2 px-1 border-b-2 font-medium text-sm <?php echo $activeTab === 'tax' ? 'active' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        💰 Impuestos
                    </button>
                    <button onclick="showTab('email')" class="tab-button py-2 px-1 border-b-2 font-medium text-sm <?php echo $activeTab === 'email' ? 'active' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        📧 Correo
                    </button>
                    <button onclick="showTab('appearance')" class="tab-button py-2 px-1 border-b-2 font-medium text-sm <?php echo $activeTab === 'appearance' ? 'active' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        🎨 Apariencia
                    </button>
                    <button onclick="showTab('system')" class="tab-button py-2 px-1 border-b-2 font-medium text-sm <?php echo $activeTab === 'system' ? 'active' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        🔧 Sistema
                    </button>
                </nav>
            </div>

            <!-- Contenido de las pestañas -->
            <div class="p-6">

                <!-- Pestaña General -->
                <div id="general" class="tab-content <?php echo $activeTab === 'general' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Información de la Empresa</h2>
                    
                    <form method="POST" onsubmit="return validateGeneralForm()">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="update_general">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre de la Empresa <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="company_name" 
                                       name="company_name" 
                                       value="<?php echo Security::escape($settings['company_name']); ?>"
                                       maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       required>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="company_address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Dirección de la Empresa
                                </label>
                                <textarea id="company_address" 
                                          name="company_address" 
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="Dirección completa de la empresa"><?php echo Security::escape($settings['company_address']); ?></textarea>
                            </div>
                            
                            <div>
                                <label for="company_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Teléfono
                                </label>
                                <input type="tel" 
                                       id="company_phone" 
                                       name="company_phone" 
                                       value="<?php echo Security::escape($settings['company_phone']); ?>"
                                       maxlength="50"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="+1 234 567 8900">
                            </div>
                            
                            <div>
                                <label for="company_email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email de Contacto
                                </label>
                                <input type="email" 
                                       id="company_email" 
                                       name="company_email" 
                                       value="<?php echo Security::escape($settings['company_email']); ?>"
                                       maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="contacto@empresa.com">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="company_website" class="block text-sm font-medium text-gray-700 mb-2">
                                    Sitio Web
                                </label>
                                <input type="url" 
                                       id="company_website" 
                                       name="company_website" 
                                       value="<?php echo Security::escape($settings['company_website']); ?>"
                                       maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="https://www.empresa.com">
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                💾 Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña Regional -->
                <div id="regional" class="tab-content <?php echo $activeTab === 'regional' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Configuración Regional</h2>
                    
                    <form method="POST" onsubmit="return validateRegionalForm()">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="update_regional">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="language" class="block text-sm font-medium text-gray-700 mb-2">
                                    Idioma del Sistema <span class="text-red-500">*</span>
                                </label>
                                <select id="language" 
                                        name="language" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <option value="es" <?php echo $settings['language'] === 'es' ? 'selected' : ''; ?>>🇪🇸 Español</option>
                                    <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>🇺🇸 English</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Zona Horaria <span class="text-red-500">*</span>
                                </label>
                                <select id="timezone" 
                                        name="timezone" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <?php foreach ($timezones as $region => $tzList): ?>
                                        <optgroup label="<?php echo Security::escape($region); ?>">
                                            <?php foreach ($tzList as $tz): ?>
                                                <option value="<?php echo Security::escape($tz); ?>" 
                                                        <?php echo $settings['timezone'] === $tz ? 'selected' : ''; ?>>
                                                    <?php echo Security::escape(str_replace(['_', '/'], [' ', ' / '], $tz)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="currency_code" class="block text-sm font-medium text-gray-700 mb-2">
                                    Moneda <span class="text-red-500">*</span>
                                </label>
                                <select id="currency_code" 
                                        name="currency_code" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        onchange="updateCurrencySymbol()"
                                        required>
                                    <?php foreach ($currencies as $code => $currency): ?>
                                        <option value="<?php echo Security::escape($code); ?>" 
                                                data-symbol="<?php echo Security::escape($currency['symbol']); ?>"
                                                <?php echo $settings['currency_code'] === $code ? 'selected' : ''; ?>>
                                            <?php echo Security::escape($code . ' - ' . $currency['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="currency_symbol" class="block text-sm font-medium text-gray-700 mb-2">
                                    Símbolo de Moneda <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="currency_symbol" 
                                       name="currency_symbol" 
                                       value="<?php echo Security::escape($settings['currency_symbol']); ?>"
                                       maxlength="10"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       required>
                            </div>
                            
                            <div>
                                <label for="date_format" class="block text-sm font-medium text-gray-700 mb-2">
                                    Formato de Fecha <span class="text-red-500">*</span>
                                </label>
                                <select id="date_format" 
                                        name="date_format" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (<?php echo date('d/m/Y'); ?>)</option>
                                    <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (<?php echo date('m/d/Y'); ?>)</option>
                                    <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (<?php echo date('Y-m-d'); ?>)</option>
                                    <option value="d-m-Y" <?php echo $settings['date_format'] === 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY (<?php echo date('d-m-Y'); ?>)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                💾 Guardar Configuración Regional
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña Impuestos -->
                <div id="tax" class="tab-content <?php echo $activeTab === 'tax' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Configuración de Impuestos</h2>
                    
                    <form method="POST" onsubmit="return validateTaxForm()">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="update_tax">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="tax_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre del Impuesto <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="tax_name" 
                                       name="tax_name" 
                                       value="<?php echo Security::escape($settings['tax_name']); ?>"
                                       maxlength="50"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="IVA, VAT, GST, etc."
                                       required>
                            </div>
                            
                            <div>
                                <label for="tax_rate" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tasa de Impuesto (%) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       id="tax_rate" 
                                       name="tax_rate" 
                                       value="<?php echo Security::escape($settings['tax_rate']); ?>"
                                       step="0.01"
                                       min="0"
                                       max="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="16.00"
                                       required>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                            <h3 class="font-medium text-blue-800 mb-2">💡 Información sobre Impuestos</h3>
                            <ul class="text-blue-700 text-sm space-y-1">
                                <li>• Esta configuración se aplicará como valor por defecto en nuevos productos</li>
                                <li>• Los productos existentes mantendrán su configuración individual</li>
                                <li>• Puede personalizar la tasa por producto en el formulario de productos</li>
                                <li>• Ejemplos comunes: IVA 21% (España), VAT 20% (Reino Unido), GST 10% (Australia)</li>
                            </ul>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                💾 Guardar Configuración de Impuestos
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña Email -->
                <div id="email" class="tab-content <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Configuración de Correo SMTP</h2>
                    
                    <form method="POST" onsubmit="return validateEmailForm()">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="update_email">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">
                                    Servidor SMTP
                                </label>
                                <input type="text" 
                                       id="smtp_host" 
                                       name="smtp_host" 
                                       value="<?php echo Security::escape($settings['smtp_host']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="smtp.gmail.com">
                            </div>
                            
                            <div>
                                <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">
                                    Puerto SMTP
                                </label>
                                <input type="number" 
                                       id="smtp_port" 
                                       name="smtp_port" 
                                       value="<?php echo Security::escape($settings['smtp_port']); ?>"
                                       min="1"
                                       max="65535"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="587">
                            </div>
                            
                            <div>
                                <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">
                                    Usuario SMTP
                                </label>
                                <input type="text" 
                                       id="smtp_username" 
                                       name="smtp_username" 
                                       value="<?php echo Security::escape($settings['smtp_username']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="usuario@gmail.com">
                            </div>
                            
                            <div>
                                <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Contraseña SMTP
                                </label>
                                <input type="password" 
                                       id="smtp_password" 
                                       name="smtp_password" 
                                       value="<?php echo Security::escape($settings['smtp_password']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="••••••••">
                            </div>
                            
                            <div>
                                <label for="smtp_security" class="block text-sm font-medium text-gray-700 mb-2">
                                    Seguridad
                                </label>
                                <select id="smtp_security" 
                                        name="smtp_security" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="none" <?php echo $settings['smtp_security'] === 'none' ? 'selected' : ''; ?>>Sin cifrado</option>
                                    <option value="tls" <?php echo $settings['smtp_security'] === 'tls' ? 'selected' : ''; ?>>TLS (Recomendado)</option>
                                    <option value="ssl" <?php echo $settings['smtp_security'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="smtp_from_email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Remitente
                                </label>
                                <input type="email" 
                                       id="smtp_from_email" 
                                       name="smtp_from_email" 
                                       value="<?php echo Security::escape($settings['smtp_from_email']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="noreply@empresa.com">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="smtp_from_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre del Remitente
                                </label>
                                <input type="text" 
                                       id="smtp_from_name" 
                                       name="smtp_from_name" 
                                       value="<?php echo Security::escape($settings['smtp_from_name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Sistema CRM">
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-between">
                            <button type="button" 
                                    onclick="testEmailConnection()"
                                    class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                🧪 Probar Conexión
                            </button>
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                💾 Guardar Configuración de Correo
                            </button>
                        </div>
                        
                        <div id="email-test-result" class="test-result mt-4 hidden"></div>
                    </form>
                </div>

                <!-- Pestaña Apariencia -->
                <div id="appearance" class="tab-content <?php echo $activeTab === 'appearance' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Apariencia y Branding</h2>
                    
                    <!-- Logo de la empresa -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Logo de la Empresa</h3>
                        
                        <div class="flex items-start space-x-6">
                            <div class="flex-shrink-0">
                                <?php if ($settings['company_logo']): ?>
                                    <img src="<?php echo BASE_URL . '/' . Security::escape($settings['company_logo']); ?>" 
                                         alt="Logo actual" 
                                         class="w-32 h-32 object-contain border border-gray-300 rounded-lg bg-white">
                                    <div class="mt-2 text-center">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                                            <input type="hidden" name="action" value="remove_logo">
                                            <button type="submit" 
                                                    onclick="return confirm('¿Está seguro de eliminar el logo actual?')"
                                                    class="text-red-600 hover:text-red-800 text-sm">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="w-32 h-32 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center bg-gray-100">
                                        <span class="text-gray-500 text-sm">Sin logo</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-1">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="upload_logo">
                                    
                                    <div class="mb-4">
                                        <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">
                                            Subir Nuevo Logo
                                        </label>
                                        <input type="file" 
                                               id="logo" 
                                               name="logo" 
                                               accept="image/*"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div class="text-sm text-gray-600 mb-4">
                                        <ul class="list-disc list-inside space-y-1">
                                            <li>Formatos soportados: PNG, JPG, GIF</li>
                                            <li>Tamaño máximo: 2MB</li>
                                            <li>Resolución recomendada: 200x200px</li>
                                        </ul>
                                    </div>
                                    
                                    <button type="submit" 
                                            class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        📤 Subir Logo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tema del sistema -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Tema del Sistema</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                            <input type="hidden" name="action" value="update_theme">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer <?php echo $settings['theme'] === 'light' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'; ?>">
                                    <input type="radio" 
                                           name="theme" 
                                           value="light" 
                                           <?php echo $settings['theme'] === 'light' ? 'checked' : ''; ?>
                                           class="mr-3">
                                    <div>
                                        <div class="font-medium">☀️ Tema Claro</div>
                                        <div class="text-sm text-gray-600">Fondo blanco, texto oscuro</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer <?php echo $settings['theme'] === 'dark' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'; ?>">
                                    <input type="radio" 
                                           name="theme" 
                                           value="dark" 
                                           <?php echo $settings['theme'] === 'dark' ? 'checked' : ''; ?>
                                           class="mr-3">
                                    <div>
                                        <div class="font-medium">🌙 Tema Oscuro</div>
                                        <div class="text-sm text-gray-600">Fondo oscuro, texto claro</div>
                                    </div>
                                </label>
                            </div>
                            
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                💾 Cambiar Tema
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Pestaña Sistema -->
                <div id="system" class="tab-content <?php echo $activeTab === 'system' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Información del Sistema</h2>
                    
                    <!-- Información del sistema -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Diagnóstico del Sistema</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <strong>Versión PHP:</strong> <?php echo Security::escape($systemInfo['php_version'] ?? 'N/A'); ?>
                            </div>
                            <div>
                                <strong>Servidor Web:</strong> <?php echo Security::escape($systemInfo['server_software'] ?? 'N/A'); ?>
                            </div>
                            <div>
                                <strong>Tamaño máximo de subida:</strong> <?php echo Security::escape($systemInfo['max_upload_size'] ?? 'N/A'); ?>
                            </div>
                            <div>
                                <strong>Tiempo máximo de ejecución:</strong> <?php echo Security::escape($systemInfo['max_execution_time'] ?? 'N/A'); ?>s
                            </div>
                            <div>
                                <strong>Límite de memoria:</strong> <?php echo Security::escape($systemInfo['memory_limit'] ?? 'N/A'); ?>
                            </div>
                            <div>
                                <strong>Zona horaria del servidor:</strong> <?php echo Security::escape($systemInfo['timezone_current'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        
                        <h4 class="text-md font-semibold text-gray-800 mt-6 mb-3">Extensiones PHP</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <?php if (isset($systemInfo['extensions'])): ?>
                                <?php foreach ($systemInfo['extensions'] as $ext => $loaded): ?>
                                    <div class="flex items-center space-x-2">
                                        <span class="<?php echo $loaded ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $loaded ? '✅' : '❌'; ?>
                                        </span>
                                        <span><?php echo Security::escape($ext); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Herramientas de administración -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Herramientas de Administración</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Exportar configuración -->
                            <div class="border rounded-lg p-4 bg-white">
                                <h4 class="font-medium text-gray-800 mb-2">📤 Exportar Configuración</h4>
                                <p class="text-sm text-gray-600 mb-3">Descarga un archivo con toda la configuración actual (sin contraseñas).</p>
                                <a href="?action=export" 
                                   class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm">
                                    Descargar Config
                                </a>
                            </div>
                            
                            <!-- Importar configuración -->
                            <div class="border rounded-lg p-4 bg-white">
                                <h4 class="font-medium text-gray-800 mb-2">📥 Importar Configuración</h4>
                                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="import_config">
                                    <input type="file" 
                                           name="config_file" 
                                           accept=".json"
                                           class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                    <button type="submit" 
                                            onclick="return confirm('¿Está seguro de importar esta configuración? Se sobrescribirá la actual.')"
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
                                        Importar
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Resetear configuración -->
                            <div class="border rounded-lg p-4 bg-white md:col-span-2">
                                <h4 class="font-medium text-red-800 mb-2">🔄 Resetear a Valores por Defecto</h4>
                                <p class="text-sm text-gray-600 mb-3">Restaura toda la configuración a los valores iniciales (excepto configuración de correo).</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="reset_defaults">
                                    <button type="submit" 
                                            onclick="return confirm('⚠️ ¿Está seguro de resetear toda la configuración? Esta acción no se puede deshacer.')"
                                            class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 text-sm">
                                        ⚠️ Resetear Todo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gestión de pestañas
        function showTab(tabName) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.classList.remove('active');
            });
            
            // Quitar clase activa de todos los botones
            document.querySelectorAll('.tab-button').forEach(function(btn) {
                btn.classList.remove('active');
                btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700');
                btn.classList.remove('border-blue-500', 'text-blue-600');
            });
            
            // Mostrar pestaña seleccionada
            document.getElementById(tabName).classList.add('active');
            
            // Activar botón correspondiente
            event.target.classList.add('active');
            event.target.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700');
            event.target.classList.add('border-blue-500', 'text-blue-600');
            
            // Actualizar URL sin recargar
            history.replaceState(null, null, '?tab=' + tabName);
        }

        // Actualizar símbolo de moneda automáticamente
        function updateCurrencySymbol() {
            const select = document.getElementById('currency_code');
            const symbolInput = document.getElementById('currency_symbol');
            const selectedOption = select.options[select.selectedIndex];
            const symbol = selectedOption.getAttribute('data-symbol');
            if (symbol) {
                symbolInput.value = symbol;
            }
        }

        // Probar conexión de correo
        function testEmailConnection() {
            const button = event.target;
            const resultDiv = document.getElementById('email-test-result');
            
            // Mostrar loading
            button.classList.add('loading');
            button.textContent = '🔄 Probando...';
            button.disabled = true;
            
            // Obtener datos del formulario
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('action', 'test_email');
            formData.append('smtp_host', document.getElementById('smtp_host').value);
            formData.append('smtp_port', document.getElementById('smtp_port').value);
            formData.append('smtp_username', document.getElementById('smtp_username').value);
            formData.append('smtp_password', document.getElementById('smtp_password').value);
            formData.append('smtp_security', document.getElementById('smtp_security').value);
            formData.append('smtp_from_email', document.getElementById('smtp_from_email').value);
            formData.append('smtp_from_name', document.getElementById('smtp_from_name').value);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.classList.remove('hidden');
                if (data.success) {
                    resultDiv.className = 'test-result mt-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded';
                    resultDiv.innerHTML = '✅ ' + data.message;
                } else {
                    resultDiv.className = 'test-result mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded';
                    resultDiv.innerHTML = '❌ ' + data.message;
                }
            })
            .catch(error => {
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'test-result mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded';
                resultDiv.innerHTML = '❌ Error de conexión: ' + error.message;
            })
            .finally(() => {
                button.classList.remove('loading');
                button.textContent = '🧪 Probar Conexión';
                button.disabled = false;
            });
        }

        // Validaciones de formularios
        function validateGeneralForm() {
            const companyName = document.getElementById('company_name').value.trim();
            const companyEmail = document.getElementById('company_email').value.trim();
            
            if (!companyName || companyName.length < 2) {
                alert('El nombre de la empresa debe tener al menos 2 caracteres.');
                return false;
            }
            
            if (companyEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(companyEmail)) {
                alert('Correo electrónico de la empresa no válido.');
                return false;
            }
            
            return true;
        }

        function validateRegionalForm() {
            const language = document.getElementById('language').value;
            const timezone = document.getElementById('timezone').value;
            const currencyCode = document.getElementById('currency_code').value;
            const currencySymbol = document.getElementById('currency_symbol').value.trim();
            
            if (!language || !timezone || !currencyCode || !currencySymbol) {
                alert('Todos los campos marcados con * son requeridos.');
                return false;
            }
            
            return true;
        }

        function validateTaxForm() {
            const taxName = document.getElementById('tax_name').value.trim();
            const taxRate = parseFloat(document.getElementById('tax_rate').value);
            
            if (!taxName || taxName.length < 2) {
                alert('El nombre del impuesto debe tener al menos 2 caracteres.');
                return false;
            }
            
            if (isNaN(taxRate) || taxRate < 0 || taxRate > 100) {
                alert('La tasa de impuesto debe estar entre 0% y 100%.');
                return false;
            }
            
            return true;
        }

        function validateEmailForm() {
            const smtpFromEmail = document.getElementById('smtp_from_email').value.trim();
            const smtpPort = parseInt(document.getElementById('smtp_port').value);
            
            if (smtpFromEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(smtpFromEmail)) {
                alert('Email remitente no válido.');
                return false;
            }
            
            if (smtpPort && (smtpPort < 1 || smtpPort > 65535)) {
                alert('Puerto SMTP no válido (1-65535).');
                return false;
            }
            
            return true;
        }

        // Inicializar pestaña activa
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = '<?php echo Security::escape($activeTab); ?>';
            if (activeTab) {
                // Simular click en la pestaña activa
                const tabButton = Array.from(document.querySelectorAll('.tab-button')).find(btn => 
                    btn.textContent.toLowerCase().includes(activeTab)
                );
                if (tabButton) {
                    tabButton.click();
                } else {
                    showTab(activeTab);
                }
            }
        });
    </script>
</body>
</html>