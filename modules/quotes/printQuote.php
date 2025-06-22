<?php
// Generador de PDF para cotizaciones CON LOGO PROMINENTE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir dependencias
try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/quotes/quoteController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
    
    // Incluir DomPDF
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar que se proporcionó un ID de cotización
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de cotización no válido.');
}

$quoteId = (int)$_GET['id'];

try {
    // Instanciar controlador
    $controller = new QuoteController();
    
    // Verificar autenticación
    if (!$controller->isAuthenticated()) {
        header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
        exit;
    }
    
    // Obtener datos de la cotización
    $result = $controller->getQuoteById($quoteId);
    if (isset($result['error'])) {
        die('Error: ' . $result['error']);
    }
    
    $quote = $result['quote'];
    
    // Obtener configuración de la empresa
    $companyInfo = getCompanyInfo();
    
    // Obtener información del vendedor (usuario actual)
    $sellerInfo = getCurrentUserInfo();
    
} catch (Exception $e) {
    die('Error al obtener datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Función para obtener información de la empresa CON LOGO
function getCompanyInfo() {
    try {
        $db = new Database();
        $query = "SELECT * FROM settings ORDER BY id LIMIT 1";
        $result = $db->select($query);
        
        if ($result && count($result) > 0) {
            return $result[0];
        }
        
        // Valores por defecto si no hay configuración
        return [
            'company_name' => 'Mi Empresa',
            'company_slogan' => 'Soluciones profesionales',
            'company_address' => 'Dirección de la empresa',
            'company_phone' => '+1 (555) 123-4567',
            'company_email' => 'info@miempresa.com',
            'company_website' => 'www.miempresa.com',
            'company_logo' => '',
            'currency_symbol' => '$',
            'tax_name' => 'IVA'
        ];
    } catch (Exception $e) {
        error_log("Error getting company info: " . $e->getMessage());
        return [
            'company_name' => 'Mi Empresa',
            'company_slogan' => 'Soluciones profesionales',
            'company_address' => 'Dirección de la empresa',
            'company_phone' => '+1 (555) 123-4567',
            'company_email' => 'info@miempresa.com',
            'company_website' => 'www.miempresa.com',
            'company_logo' => '',
            'currency_symbol' => '$',
            'tax_name' => 'IVA'
        ];
    }
}

// Función para obtener información del usuario actual
function getCurrentUserInfo() {
    try {
        $session = new Session();
        $userId = $session->getUserId();
        
        if ($userId) {
            $db = new Database();
            $query = "SELECT full_name, email FROM users WHERE id = ?";
            $result = $db->select($query, [$userId]);
            
            if ($result && count($result) > 0) {
                return $result[0];
            }
        }
        
        return [
            'full_name' => 'Vendedor',
            'email' => 'vendedor@empresa.com'
        ];
    } catch (Exception $e) {
        error_log("Error getting seller info: " . $e->getMessage());
        return [
            'full_name' => 'Vendedor',
            'email' => 'vendedor@empresa.com'
        ];
    }
}

// Función para convertir imagen a base64 para el PDF
function getLogoBase64($logoPath) {
    try {
        if (empty($logoPath)) {
            return null;
        }
        
        $fullPath = dirname(__DIR__, 2) . '/' . $logoPath;
        
        if (!file_exists($fullPath)) {
            error_log("Logo file not found: " . $fullPath);
            return null;
        }
        
        $imageData = file_get_contents($fullPath);
        if ($imageData === false) {
            error_log("Could not read logo file: " . $fullPath);
            return null;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);
        
        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        
    } catch (Exception $e) {
        error_log("Error processing logo: " . $e->getMessage());
        return null;
    }
}

// Calcular días válidos restantes
$validUntilDate = new DateTime($quote['valid_until']);
$today = new DateTime();
$daysRemaining = $today->diff($validUntilDate)->days;
$isExpired = $validUntilDate < $today;

// Obtener logo en base64 si existe
$logoBase64 = getLogoBase64($companyInfo['company_logo']);

// Crear el HTML para el PDF
$html = generateQuoteHTML($quote, $companyInfo, $sellerInfo, $daysRemaining, $isExpired, $logoBase64);

// Configurar DomPDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Configurar tamaño de página (Carta)
$dompdf->setPaper('letter', 'portrait');

// Renderizar PDF
$dompdf->render();

// Generar nombre del archivo
$filename = 'Cotizacion_' . $quote['quote_number'] . '_' . date('Y-m-d') . '.pdf';

// Enviar el PDF al navegador
$dompdf->stream($filename, array("Attachment" => false)); // false = ver en navegador, true = descargar

// Función para generar el HTML del PDF CON LOGO PROMINENTE
function generateQuoteHTML($quote, $company, $seller, $daysRemaining, $isExpired, $logoBase64 = null) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                margin: 2cm 1.5cm;
                font-family: Arial, sans-serif;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #333;
                margin: 0;
                padding: 0;
            }
            
            .header {
                border-bottom: 3px solid #2563eb;
                padding-bottom: 20px;
                margin-bottom: 30px;
                min-height: 100px;
            }
            
            .header-content {
                display: table;
                width: 100%;
            }
            
            .header-left {
                display: table-cell;
                width: 60%;
                vertical-align: middle;
            }
            
            .header-right {
                display: table-cell;
                width: 40%;
                text-align: right;
                vertical-align: middle;
            }
            
            .company-logo {
                max-width: 180px;
                max-height: 90px;
                object-fit: contain;
                border: 1px solid #e5e7eb;
                padding: 5px;
                background: white;
            }
            
            .company-info {
                text-align: left;
            }
            
            .company-name {
                font-size: 28px;
                font-weight: bold;
                color: #1e40af;
                margin-bottom: 8px;
                line-height: 1.2;
            }
            
            .company-slogan {
                font-size: 14px;
                color: #6b7280;
                font-style: italic;
                margin-bottom: 15px;
            }
            
            .quote-title {
                font-size: 22px;
                font-weight: bold;
                color: #1e40af;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-top: 10px;
            }
            
            .no-logo-placeholder {
                width: 180px;
                height: 90px;
                border: 2px dashed #d1d5db;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #9ca3af;
                font-size: 12px;
                text-align: center;
                background: #f9fafb;
            }
            
            .info-section {
                display: table;
                width: 100%;
                margin-bottom: 25px;
            }
            
            .info-left, .info-right {
                display: table-cell;
                width: 48%;
                vertical-align: top;
            }
            
            .info-right {
                text-align: right;
            }
            
            .info-box {
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .info-title {
                font-weight: bold;
                color: #1e40af;
                font-size: 14px;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 5px;
                margin-bottom: 10px;
            }
            
            .info-row {
                margin-bottom: 5px;
            }
            
            .info-label {
                font-weight: bold;
                color: #4b5563;
            }
            
            .table-container {
                margin: 25px 0;
            }
            
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .items-table th {
                background-color: #1e40af;
                color: white;
                padding: 12px 8px;
                text-align: left;
                font-weight: bold;
                font-size: 11px;
            }
            
            .items-table td {
                padding: 10px 8px;
                border-bottom: 1px solid #e2e8f0;
                font-size: 11px;
            }
            
            .items-table tr:nth-child(even) {
                background-color: #f8fafc;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .totals-section {
                float: right;
                width: 300px;
                margin-top: 20px;
            }
            
            .totals-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .totals-table td {
                padding: 8px 12px;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .totals-table .total-row {
                background-color: #1e40af;
                color: white;
                font-weight: bold;
                font-size: 14px;
            }
            
            .notes-section {
                clear: both;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
            }
            
            .notes-title {
                font-weight: bold;
                color: #1e40af;
                margin-bottom: 10px;
            }
            
            .notes-content {
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 5px;
                padding: 15px;
                font-style: italic;
            }
            
            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background-color: #1e40af;
                color: white;
                padding: 15px;
                text-align: center;
                font-size: 10px;
            }
            
            .footer-content {
                display: table;
                width: 100%;
            }
            
            .footer-left, .footer-center, .footer-right {
                display: table-cell;
                width: 33.33%;
                vertical-align: middle;
            }
            
            .footer-center {
                text-align: center;
            }
            
            .footer-right {
                text-align: right;
            }
            
            .validity-warning {
                background-color: #fef3cd;
                border: 1px solid #fbbf24;
                color: #92400e;
                padding: 10px;
                border-radius: 5px;
                margin: 15px 0;
                text-align: center;
                font-weight: bold;
            }
            
            .validity-expired {
                background-color: #fee2e2;
                border: 1px solid #ef4444;
                color: #dc2626;
            }
            
            .currency {
                font-weight: bold;
                color: #059669;
            }
            
            .page-break {
                page-break-before: always;
            }
        </style>
    </head>
    <body>
        <!-- Header con logo prominente -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="company-info">
                        <div class="company-name">' . htmlspecialchars($company['company_name']) . '</div>
                        <div class="company-slogan">' . htmlspecialchars($company['company_slogan']) . '</div>
                        <div class="quote-title">Cotización</div>
                    </div>
                </div>
                
                <div class="header-right">';
                
                // Incluir logo si existe, sino mostrar placeholder
                if ($logoBase64) {
                    $html .= '<img src="' . $logoBase64 . '" alt="Logo de ' . htmlspecialchars($company['company_name']) . '" class="company-logo">';
                } else {
                    $html .= '<div class="no-logo-placeholder">
                        <div>
                            Logo de<br>
                            ' . htmlspecialchars($company['company_name']) . '
                        </div>
                    </div>';
                }
                
                $html .= '
                </div>
            </div>
        </div>
        
        <!-- Información de la cotización -->
        <div class="info-section">
            <div class="info-left">
                <div class="info-box">
                    <div class="info-title">Información del Cliente</div>
                    <div class="info-row">
                        <span class="info-label">Cliente:</span> ' . htmlspecialchars($quote['client_name'] ?? 'Cliente eliminado') . '
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span> ' . htmlspecialchars($quote['client_email'] ?? 'N/A') . '
                    </div>
                    ' . ($quote['client_phone'] ? '<div class="info-row"><span class="info-label">Teléfono:</span> ' . htmlspecialchars($quote['client_phone']) . '</div>' : '') . '
                    ' . ($quote['client_address'] ? '<div class="info-row"><span class="info-label">Dirección:</span> ' . htmlspecialchars($quote['client_address']) . '</div>' : '') . '
                </div>
                
                <div class="info-box">
                    <div class="info-title">Vendedor</div>
                    <div class="info-row">
                        <span class="info-label">Nombre:</span> ' . htmlspecialchars($seller['full_name']) . '
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span> ' . htmlspecialchars($seller['email']) . '
                    </div>
                </div>
            </div>
            
            <div class="info-right">
                <div class="info-box">
                    <div class="info-title">Detalles de la Cotización</div>
                    <div class="info-row">
                        <span class="info-label">Número:</span> ' . htmlspecialchars($quote['quote_number']) . '
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha:</span> ' . date('d/m/Y', strtotime($quote['quote_date'])) . '
                    </div>
                    <div class="info-row">
                        <span class="info-label">Hora:</span> ' . date('H:i:s') . '
                    </div>
                    <div class="info-row">
                        <span class="info-label">Válida hasta:</span> ' . date('d/m/Y', strtotime($quote['valid_until'])) . '
                    </div>
                    <div class="info-row">
                        <span class="info-label">Estado:</span> ' . getStatusName($quote['status']) . '
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advertencia de validez -->';
        
        if ($isExpired) {
            $html .= '<div class="validity-warning validity-expired">
                ⚠️ Esta cotización ha VENCIDO (expiró hace ' . $daysRemaining . ' días)
            </div>';
        } elseif ($daysRemaining <= 3) {
            $html .= '<div class="validity-warning">
                ⚠️ Esta cotización vence en ' . $daysRemaining . ' día' . ($daysRemaining != 1 ? 's' : '') . '
            </div>';
        }
        
        $html .= '
        <!-- Tabla de productos -->
        <div class="table-container">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Producto/Servicio</th>
                        <th width="80" class="text-center">Cant.</th>
                        <th width="100" class="text-right">Precio Unit.</th>
                        <th width="80" class="text-center">Desc. %</th>
                        <th width="100" class="text-right">Subtotal</th>
                        <th width="80" class="text-center">' . htmlspecialchars($company['tax_name']) . ' %</th>
                        <th width="100" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
                
                if (isset($quote['items']) && is_array($quote['items'])) {
                    foreach ($quote['items'] as $item) {
                        $html .= '
                        <tr>
                            <td>' . htmlspecialchars($item['product_name']) . '</td>
                            <td class="text-center">' . number_format($item['quantity'], 0) . '</td>
                            <td class="text-right currency">' . $company['currency_symbol'] . number_format($item['unit_price'], 2) . '</td>
                            <td class="text-center">' . number_format($item['discount_percent'], 1) . '%</td>
                            <td class="text-right currency">' . $company['currency_symbol'] . number_format($item['line_total'], 2) . '</td>
                            <td class="text-center">' . number_format($item['tax_rate'], 1) . '%</td>
                            <td class="text-right currency">' . $company['currency_symbol'] . number_format($item['line_total_with_tax'], 2) . '</td>
                        </tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="7" class="text-center">No hay items en esta cotización</td></tr>';
                }
                
        $html .= '
                </tbody>
            </table>
        </div>
        
        <!-- Totales -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td class="text-right currency">' . $company['currency_symbol'] . number_format($quote['subtotal'], 2) . '</td>
                </tr>';
                
                if ($quote['discount_percent'] > 0) {
                    $discountAmount = ($quote['subtotal'] * $quote['discount_percent']) / 100;
                    $html .= '
                    <tr>
                        <td><strong>Descuento (' . number_format($quote['discount_percent'], 1) . '%):</strong></td>
                        <td class="text-right currency">-' . $company['currency_symbol'] . number_format($discountAmount, 2) . '</td>
                    </tr>';
                }
                
        $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($company['tax_name']) . ':</strong></td>
                    <td class="text-right currency">' . $company['currency_symbol'] . number_format($quote['tax_amount'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td><strong>TOTAL:</strong></td>
                    <td class="text-right"><strong>' . $company['currency_symbol'] . number_format($quote['total_amount'], 2) . '</strong></td>
                </tr>
            </table>
        </div>
        
        <!-- Notas -->';
        
        if (!empty($quote['notes'])) {
            $html .= '
            <div class="notes-section">
                <div class="notes-title">Notas y Observaciones:</div>
                <div class="notes-content">
                    ' . nl2br(htmlspecialchars($quote['notes'])) . '
                </div>
            </div>';
        }
        
        $html .= '
        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div class="footer-left">
                    ' . htmlspecialchars($company['company_address']) . '<br>
                    Tel: ' . htmlspecialchars($company['company_phone']) . '
                </div>
                <div class="footer-center">
                    <strong>' . htmlspecialchars($company['company_name']) . '</strong><br>
                    Generado el ' . date('d/m/Y H:i:s') . '
                </div>
                <div class="footer-right">
                    Email: ' . htmlspecialchars($company['company_email']) . '<br>
                    Web: ' . htmlspecialchars($company['company_website']) . '
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Función helper para obtener nombre del estado
function getStatusName($status) {
    $statusNames = [
        1 => 'Borrador',
        2 => 'Enviada',
        3 => 'Aprobada', 
        4 => 'Rechazada',
        5 => 'Vencida',
        6 => 'Cancelada'
    ];
    
    return $statusNames[$status] ?? 'Desconocido';
}
?>