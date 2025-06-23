<?php
// Clase de email mejorada usando PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $settings;
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->loadSettings();
    }

    // Cargar configuración de email desde BD
    private function loadSettings() {
        try {
            $query = "SELECT * FROM settings WHERE id = 1 LIMIT 1";
            $result = $this->db->select($query);
            $this->settings = $result ? $result[0] : $this->getDefaultSettings();
        } catch (Exception $e) {
            error_log("Error loading email settings: " . $e->getMessage());
            $this->settings = $this->getDefaultSettings();
        }
    }

    // Configuración por defecto
    private function getDefaultSettings() {
        return [
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_security' => 'tls',
            'smtp_from_email' => '',
            'smtp_from_name' => 'Sistema CRM',
            'company_name' => 'Mi Empresa',
            'company_email' => '',
            'company_logo' => ''
        ];
    }

    // Verificar si el email está configurado
    public function isConfigured() {
        return !empty($this->settings['smtp_host']) && 
               !empty($this->settings['smtp_from_email']) &&
               !empty($this->settings['smtp_username']);
    }

    // Crear instancia configurada de PHPMailer
    private function createMailer() {
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = $this->settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['smtp_username'];
            $mail->Password = $this->settings['smtp_password'];
            
            // Configurar seguridad
            if ($this->settings['smtp_security'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = (int)$this->settings['smtp_port'];

            // Configuración de caracteres
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Debug en desarrollo (comentar en producción)
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

            return $mail;

        } catch (Exception $e) {
            throw new Exception("Error configurando PHPMailer: " . $e->getMessage());
        }
    }

    // Enviar email usando PHPMailer
    public function sendEmail($to, $subject, $htmlBody, $textBody = null, $attachments = []) {
        if (!$this->isConfigured()) {
            throw new Exception('Configuración de email incompleta. Configure SMTP en Configuración > Email.');
        }

        // Validar email destinatario
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email destinatario no válido: ' . $to);
        }

        try {
            $mail = $this->createMailer();

            // Configurar remitente
            $mail->setFrom($this->settings['smtp_from_email'], $this->settings['smtp_from_name']);
            $mail->addReplyTo($this->settings['smtp_from_email'], $this->settings['smtp_from_name']);

            // Configurar destinatario
            $mail->addAddress($to);

            // Configurar contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            
            // Si no hay texto plano, generar desde HTML
            if (!$textBody) {
                $textBody = $this->htmlToText($htmlBody);
            }
            $mail->AltBody = $textBody;

            // Agregar archivos adjuntos si existen
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $filename = $attachment['name'] ?? basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $filename);
                }
            }

            // Enviar email
            $mail->send();

            // Log del envío exitoso
            error_log("Email enviado exitosamente a: $to - Asunto: $subject");
            
            return [
                'success' => true,
                'message' => 'Email enviado correctamente',
                'to' => $to,
                'subject' => $subject
            ];

        } catch (Exception $e) {
            error_log("Error enviando email a $to: " . $e->getMessage());
            throw new Exception('Error al enviar email: ' . $e->getMessage());
        }
    }

    // Probar conexión SMTP
    public function testConnection() {
        if (!$this->isConfigured()) {
            throw new Exception('Email no configurado. Configure SMTP en Configuración del Sistema.');
        }

        try {
            $mail = $this->createMailer();
            
            // Solo probar conexión sin enviar email
            $mail->smtpConnect();
            $mail->smtpClose();
            
            return [
                'success' => true,
                'message' => 'Conexión SMTP exitosa'
            ];

        } catch (Exception $e) {
            throw new Exception('Error de conexión SMTP: ' . $e->getMessage());
        }
    }

    // Enviar cotización por email con PDF adjunto opcional
    public function sendQuoteEmail($quoteId, $attachPdf = true) {
        if (!$this->isConfigured()) {
            throw new Exception('Email no configurado. Configure SMTP en Configuración del Sistema.');
        }

        // Obtener datos de la cotización
        $quote = $this->getQuoteData($quoteId);
        if (!$quote) {
            throw new Exception('Cotización no encontrada.');
        }

        // Validar que la cotización tenga un cliente con email
        if (empty($quote['client_email'])) {
            throw new Exception('El cliente no tiene email registrado.');
        }

        // Generar contenido del email
        $emailData = $this->generateQuoteEmailContent($quote);
        
        // Preparar archivos adjuntos
        $attachments = [];
        if ($attachPdf) {
            $pdfPath = $this->generateQuotePDF($quoteId);
            if ($pdfPath) {
                $attachments[] = [
                    'path' => $pdfPath,
                    'name' => "Cotizacion_{$quote['quote_number']}.pdf"
                ];
            }
        }

        // Enviar email
        $result = $this->sendEmail(
            $quote['client_email'],
            $emailData['subject'],
            $emailData['html_body'],
            $emailData['text_body'],
            $attachments
        );

        // Limpiar archivo PDF temporal
        if ($attachPdf && isset($pdfPath) && file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        // Registrar el envío en la base de datos
        $this->logEmailSent($quoteId, $quote['client_email'], $emailData['subject']);

        return $result;
    }

    // Generar PDF temporal de la cotización
    private function generateQuotePDF($quoteId) {
        try {
            // Generar PDF usando el sistema existente
            $pdfUrl = BASE_URL . "/modules/quotes/printQuote.php?id=" . $quoteId;
            
            // Para generar PDF desde código, necesitaríamos incluir el código de printQuote.php
            // Por simplicidad, vamos a crear un PDF temporal básico
            $tempDir = dirname(__DIR__, 2) . '/temp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $pdfFile = $tempDir . '/quote_' . $quoteId . '_' . uniqid() . '.pdf';
            
            // Aquí incluiríamos la lógica de DomPDF desde printQuote.php
            // Por ahora, retornamos null para no adjuntar PDF
            return null;
            
        } catch (Exception $e) {
            error_log("Error generating PDF: " . $e->getMessage());
            return null;
        }
    }

    // Obtener datos completos de la cotización
    private function getQuoteData($quoteId) {
        try {
            // Cotización principal
            $query = "SELECT q.*, c.name as client_name, c.email as client_email, c.phone as client_phone, c.address as client_address
                      FROM quotes q 
                      LEFT JOIN clients c ON q.client_id = c.id 
                      WHERE q.id = ?";
            
            $result = $this->db->select($query, [(int)$quoteId]);
            if (!$result) {
                return null;
            }

            $quote = $result[0];

            // Obtener items de la cotización
            $itemsQuery = "SELECT qd.*, p.unit 
                          FROM quote_details qd 
                          LEFT JOIN products p ON qd.product_id = p.id 
                          WHERE qd.quote_id = ? 
                          ORDER BY qd.id";
            
            $quote['items'] = $this->db->select($itemsQuery, [(int)$quoteId]);

            return $quote;

        } catch (Exception $e) {
            error_log("Error getting quote data: " . $e->getMessage());
            return null;
        }
    }

    // Generar contenido del email de cotización
    private function generateQuoteEmailContent($quote) {
        $companyName = $this->settings['company_name'];
        $companyEmail = $this->settings['company_email'];
        
        // Calcular días válidos
        $validUntil = new DateTime($quote['valid_until']);
        $today = new DateTime();
        $daysRemaining = $today->diff($validUntil)->days;
        $isExpired = $validUntil < $today;

        // Asunto del email
        $subject = "Cotización {$quote['quote_number']} - {$companyName}";

        // Cuerpo HTML
        $htmlBody = $this->generateHtmlEmailBody($quote, $daysRemaining, $isExpired);

        // Cuerpo texto plano
        $textBody = $this->generateTextEmailBody($quote, $daysRemaining, $isExpired);

        return [
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody
        ];
    }

    // Generar cuerpo HTML del email (versión mejorada)
    private function generateHtmlEmailBody($quote, $daysRemaining, $isExpired) {
        $companyName = htmlspecialchars($this->settings['company_name']);
        $companyEmail = htmlspecialchars($this->settings['company_email']);
        $clientName = htmlspecialchars($quote['client_name']);
        $quoteNumber = htmlspecialchars($quote['quote_number']);
        
        // Logo de la empresa si existe
        $logoHtml = '';
        if (!empty($this->settings['company_logo'])) {
            $logoUrl = BASE_URL . '/' . $this->settings['company_logo'];
            $logoHtml = "<img src='{$logoUrl}' alt='{$companyName}' style='max-height: 80px; margin-bottom: 20px;'>";
        }

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Cotización {$quoteNumber}</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 32px; font-weight: 300; }
                .header .subtitle { margin: 15px 0 0 0; opacity: 0.9; font-size: 16px; }
                .content { padding: 40px 30px; }
                .greeting { font-size: 18px; margin-bottom: 25px; color: #2563eb; }
                .quote-info { background: linear-gradient(135deg, #f8fafc, #e2e8f0); border-left: 5px solid #2563eb; padding: 25px; margin: 25px 0; border-radius: 8px; }
                .quote-info h3 { margin: 0 0 20px 0; color: #1e40af; font-size: 20px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                .info-label { font-weight: 600; color: #4b5563; }
                .info-value { color: #1f2937; font-weight: 500; }
                .items-section { margin: 30px 0; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .items-table th { background: #374151; color: white; padding: 15px 12px; text-align: left; font-weight: 600; }
                .items-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
                .items-table tr:nth-child(even) { background: #f9fafb; }
                .items-table tr:hover { background: #f3f4f6; }
                .totals { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px solid #0ea5e9; }
                .total-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 5px 0; }
                .total-row.final { font-weight: bold; font-size: 20px; color: #1e40af; border-top: 2px solid #2563eb; padding-top: 15px; margin-top: 15px; }
                .validity-warning { background: linear-gradient(135deg, #fef3cd, #fde68a); border: 2px solid #f59e0b; color: #92400e; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; font-weight: 600; }
                .validity-expired { background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #ef4444; color: #dc2626; }
                .footer { background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb; }
                .footer p { margin: 8px 0; color: #6b7280; }
                .cta-section { text-align: center; margin: 35px 0; }
                .cta-button { display: inline-block; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 10px; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); transition: all 0.3s ease; }
                .cta-button:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4); }
                .notes { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border: 1px solid #0ea5e9; border-radius: 8px; padding: 20px; margin: 25px 0; }
                .notes h4 { margin: 0 0 15px 0; color: #0369a1; font-size: 18px; }
                .highlight { background: linear-gradient(135deg, #fef3cd, #fde68a); padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    {$logoHtml}
                    <h1>Cotización {$quoteNumber}</h1>
                    <p class='subtitle'>De: {$companyName}</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>¡Hola <strong>{$clientName}</strong>! 👋</div>
                    
                    <p>Esperamos que se encuentre muy bien. Nos complace enviarle la cotización que nos solicitó. Hemos preparado una propuesta especialmente para usted:</p>";

        // Advertencia de validez
        if ($isExpired) {
            $html .= "<div class='validity-warning validity-expired'>
                        ⚠️ <strong>IMPORTANTE:</strong> Esta cotización ha vencido. Contáctenos para obtener una cotización actualizada.
                      </div>";
        } elseif ($daysRemaining <= 3) {
            $html .= "<div class='validity-warning'>
                        ⏰ <strong>¡TIEMPO LIMITADO!</strong> Esta cotización vence en {$daysRemaining} día" . ($daysRemaining != 1 ? 's' : '') . ". ¡No deje pasar esta oportunidad!
                      </div>";
        }
                    
        $html .= "<div class='quote-info'>
                        <h3>📋 Resumen de su Cotización</h3>
                        <div class='info-grid'>
                            <div class='info-row'>
                                <span class='info-label'>Número:</span>
                                <span class='info-value'>{$quoteNumber}</span>
                            </div>
                            <div class='info-row'>
                                <span class='info-label'>Fecha:</span>
                                <span class='info-value'>" . date('d/m/Y', strtotime($quote['quote_date'])) . "</span>
                            </div>
                            <div class='info-row'>
                                <span class='info-label'>Válida hasta:</span>
                                <span class='info-value'>" . date('d/m/Y', strtotime($quote['valid_until'])) . "</span>
                            </div>
                            <div class='info-row'>
                                <span class='info-label'>Total:</span>
                                <span class='info-value' style='font-size: 18px; color: #059669; font-weight: bold;'>\${$quote['total_amount']}</span>
                            </div>
                        </div>
                    </div>";

        // Tabla de productos
        $html .= "<div class='items-section'>
                    <h3 style='color: #1e40af; margin-bottom: 15px;'>📦 Productos y Servicios Incluidos</h3>
                    <table class='items-table'>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th style='text-align: center; width: 80px;'>Cant.</th>
                                <th style='text-align: right; width: 100px;'>Precio Unit.</th>
                                <th style='text-align: right; width: 100px;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>";

        foreach ($quote['items'] as $item) {
            $html .= "<tr>
                        <td><strong>" . htmlspecialchars($item['product_name']) . "</strong></td>
                        <td style='text-align: center;'>" . number_format($item['quantity']) . "</td>
                        <td style='text-align: right;'>\$" . number_format($item['unit_price'], 2) . "</td>
                        <td style='text-align: right;'><strong style='color: #059669;'>\$" . number_format($item['line_total_with_tax'], 2) . "</strong></td>
                      </tr>";
        }

        $html .= "</tbody></table></div>";

        // Totales
        $html .= "<div class='totals'>
                    <h4 style='margin: 0 0 15px 0; color: #1e40af;'>💰 Desglose de Costos</h4>
                    <div class='total-row'>
                        <span>Subtotal:</span>
                        <span>\$" . number_format($quote['subtotal'], 2) . "</span>
                    </div>";
        
        if ($quote['discount_percent'] > 0) {
            $discountAmount = ($quote['subtotal'] * $quote['discount_percent']) / 100;
            $html .= "<div class='total-row' style='color: #059669;'>
                        <span>🎉 Descuento (" . number_format($quote['discount_percent'], 1) . "%):</span>
                        <span>-\$" . number_format($discountAmount, 2) . "</span>
                      </div>";
        }
        
        $html .= "<div class='total-row'>
                    <span>Impuestos:</span>
                    <span>\$" . number_format($quote['tax_amount'], 2) . "</span>
                  </div>
                  <div class='total-row final'>
                    <span>💎 TOTAL FINAL:</span>
                    <span>\$" . number_format($quote['total_amount'], 2) . "</span>
                  </div>
                </div>";

        // Notas si existen
        if (!empty($quote['notes'])) {
            $html .= "<div class='notes'>
                        <h4>📝 Condiciones y Observaciones Especiales:</h4>
                        <p>" . nl2br(htmlspecialchars($quote['notes'])) . "</p>
                      </div>";
        }

        // Llamada a la acción
        if (!$isExpired) {
            $html .= "<div class='cta-section'>
                        <h3 style='color: #1e40af; margin-bottom: 20px;'>¿Listo para continuar? 🚀</h3>
                        <a href='mailto:{$companyEmail}?subject=Consulta sobre cotización {$quoteNumber}' class='cta-button'>
                            📧 Tengo una pregunta
                        </a>
                        <a href='mailto:{$companyEmail}?subject=Acepto cotización {$quoteNumber}' class='cta-button'>
                            ✅ ¡Acepto esta cotización!
                        </a>
                      </div>";
        }

        $html .= "<div class='highlight'>
                    <strong>🎯 ¿Por qué elegirnos?</strong><br>
                    • Experiencia comprobada en el mercado<br>
                    • Atención personalizada y profesional<br>
                    • Productos/servicios de alta calidad<br>
                    • Soporte técnico especializado
                  </div>";

        $html .= "<p style='font-size: 16px; color: #374151;'>Estamos aquí para resolver cualquier duda que pueda tener. No dude en contactarnos, ¡estaremos encantados de ayudarle!</p>
                  
                  <p style='margin-top: 30px;'>Con los mejores saludos,<br>
                  <strong style='color: #2563eb; font-size: 18px;'>{$companyName}</strong><br>
                  <span style='color: #6b7280;'>Su socio confiable</span></p>
                </div>
                
                <div class='footer'>
                    <p><strong>{$companyName}</strong></p>
                    <p>📧 {$companyEmail} | 🌐 Síguenos en redes sociales</p>
                    <p style='font-size: 12px; color: #9ca3af; margin-top: 15px;'>
                        Este email fue generado automáticamente por nuestro sistema CRM.<br>
                        Si tiene problemas para ver este mensaje, contáctenos directamente.
                    </p>
                </div>
            </div>
        </body>
        </html>";

        return $html;
    }

    // Generar cuerpo texto plano del email
    private function generateTextEmailBody($quote, $daysRemaining, $isExpired) {
        $companyName = $this->settings['company_name'];
        $companyEmail = $this->settings['company_email'];
        
        $text = "🏢 COTIZACIÓN {$quote['quote_number']}\n";
        $text .= str_repeat("=", 60) . "\n\n";
        
        $text .= "¡Hola {$quote['client_name']}!\n\n";
        $text .= "Nos complace enviarle la cotización que nos solicitó.\n\n";
        
        $text .= "📋 INFORMACIÓN DE LA COTIZACIÓN:\n";
        $text .= str_repeat("-", 40) . "\n";
        $text .= "• Número: {$quote['quote_number']}\n";
        $text .= "• Fecha: " . date('d/m/Y', strtotime($quote['quote_date'])) . "\n";
        $text .= "• Válida hasta: " . date('d/m/Y', strtotime($quote['valid_until'])) . "\n";
        $text .= "• Total: \${$quote['total_amount']}\n\n";
        
        if ($isExpired) {
            $text .= "⚠️ IMPORTANTE: Esta cotización ha vencido.\n";
            $text .= "Contáctenos para obtener una cotización actualizada.\n\n";
        } elseif ($daysRemaining <= 3) {
            $text .= "⏰ ¡TIEMPO LIMITADO! Esta cotización vence en {$daysRemaining} día(s).\n\n";
        }
        
        $text .= "📦 PRODUCTOS Y SERVICIOS:\n";
        $text .= str_repeat("-", 60) . "\n";
        
        foreach ($quote['items'] as $item) {
            $text .= "• {$item['product_name']}\n";
            $text .= "  Cantidad: " . number_format($item['quantity']) . "\n";
            $text .= "  Precio unitario: \$" . number_format($item['unit_price'], 2) . "\n";
            $text .= "  Total: \$" . number_format($item['line_total_with_tax'], 2) . "\n\n";
        }
        
        $text .= "💰 RESUMEN DE COSTOS:\n";
        $text .= str_repeat("-", 30) . "\n";
        $text .= "Subtotal: \$" . number_format($quote['subtotal'], 2) . "\n";
        
        if ($quote['discount_percent'] > 0) {
            $discountAmount = ($quote['subtotal'] * $quote['discount_percent']) / 100;
            $text .= "Descuento: -\$" . number_format($discountAmount, 2) . "\n";
        }
        
        $text .= "Impuestos: \$" . number_format($quote['tax_amount'], 2) . "\n";
        $text .= "💎 TOTAL FINAL: \$" . number_format($quote['total_amount'], 2) . "\n\n";
        
        if (!empty($quote['notes'])) {
            $text .= "📝 CONDICIONES Y OBSERVACIONES:\n";
            $text .= str_repeat("-", 40) . "\n";
            $text .= $quote['notes'] . "\n\n";
        }
        
        if (!$isExpired) {
            $text .= "🚀 ¿LISTO PARA CONTINUAR?\n";
            $text .= "Responda a este email o contáctenos:\n";
            $text .= "📧 {$companyEmail}\n\n";
        }
        
        $text .= "Con los mejores saludos,\n";
        $text .= "{$companyName}\n";
        $text .= "Su socio confiable\n\n";
        $text .= str_repeat("-", 60) . "\n";
        $text .= "Este email fue generado automáticamente por nuestro sistema CRM.";
        
        return $text;
    }

    // Convertir HTML a texto plano
    private function htmlToText($html) {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    // Registrar envío de email en log
    private function logEmailSent($quoteId, $recipientEmail, $subject) {
        try {
            // Crear tabla de logs si no existe
            $createLogTable = "CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quote_id INT,
                recipient_email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('sent', 'failed') DEFAULT 'sent',
                error_message TEXT NULL,
                INDEX idx_quote_id (quote_id),
                INDEX idx_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->execute($createLogTable);

            // Insertar log
            $insertLog = "INSERT INTO email_logs (quote_id, recipient_email, subject, status) VALUES (?, ?, ?, 'sent')";
            $this->db->execute($insertLog, [$quoteId, $recipientEmail, $subject]);

        } catch (Exception $e) {
            error_log("Error logging email: " . $e->getMessage());
        }
    }

    // Obtener historial de emails enviados para una cotización
    public function getEmailHistory($quoteId) {
        try {
            $query = "SELECT * FROM email_logs WHERE quote_id = ? ORDER BY sent_at DESC";
            return $this->db->select($query, [(int)$quoteId]);
        } catch (Exception $e) {
            error_log("Error getting email history: " . $e->getMessage());
            return [];
        }
    }

    // Enviar email de prueba
    public function sendTestEmail($testEmail, $testType = 'basic') {
        if (!$this->isConfigured()) {
            throw new Exception('Email no configurado. Configure SMTP en Configuración del Sistema.');
        }

        $companyName = $this->settings['company_name'];
        $timestamp = date('Y-m-d H:i:s');

        if ($testType === 'basic') {
            $subject = "✅ Prueba de Configuración SMTP - {$companyName}";
            $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 30px; text-align: center; border-radius: 8px;'>
                        <h1 style='margin: 0; font-size: 24px;'>🎉 ¡Configuración Exitosa!</h1>
                        <p style='margin: 10px 0 0 0; opacity: 0.9;'>Su sistema de email está funcionando correctamente</p>
                    </div>
                    
                    <div style='padding: 30px; background: #f8fafc; border-radius: 8px; margin-top: 20px;'>
                        <h2 style='color: #1e40af; margin-top: 0;'>📧 Prueba de Configuración SMTP</h2>
                        <p>¡Excelente! Este email confirma que la configuración SMTP de su sistema CRM está funcionando perfectamente.</p>
                        
                        <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #2563eb; margin: 20px 0;'>
                            <h3 style='margin: 0 0 15px 0; color: #374151;'>📊 Detalles de la Prueba:</h3>
                            <p style='margin: 5px 0;'><strong>Empresa:</strong> {$companyName}</p>
                            <p style='margin: 5px 0;'><strong>Fecha y hora:</strong> {$timestamp}</p>
                            <p style='margin: 5px 0;'><strong>Servidor SMTP:</strong> {$this->settings['smtp_host']}</p>
                            <p style='margin: 5px 0;'><strong>Puerto:</strong> {$this->settings['smtp_port']}</p>
                            <p style='margin: 5px 0;'><strong>Seguridad:</strong> " . strtoupper($this->settings['smtp_security']) . "</p>
                        </div>
                        
                        <div style='background: #f0f9ff; padding: 15px; border-radius: 8px; border: 1px solid #0ea5e9;'>
                            <h4 style='margin: 0 0 10px 0; color: #0369a1;'>✨ ¿Qué significa esto?</h4>
                            <ul style='margin: 0; color: #0c4a6e;'>
                                <li>Su configuración SMTP es correcta</li>
                                <li>Los emails de cotizaciones se enviarán sin problemas</li>
                                <li>Los clientes recibirán sus cotizaciones automáticamente</li>
                                <li>El sistema está listo para uso en producción</li>
                            </ul>
                        </div>
                        
                        <p style='margin-top: 25px; color: #6b7280;'>Si recibe este mensaje, puede proceder con confianza a enviar cotizaciones a sus clientes.</p>
                    </div>
                    
                    <div style='text-align: center; padding: 20px; color: #9ca3af; font-size: 12px;'>
                        <p>Este es un email de prueba generado automáticamente por su sistema CRM</p>
                        <p>{$companyName} • Sistema CRM • {$timestamp}</p>
                    </div>
                </div>
            ";

            $textBody = "✅ PRUEBA DE CONFIGURACIÓN SMTP - {$companyName}\n\n" .
                       "¡Excelente! Su configuración SMTP está funcionando correctamente.\n\n" .
                       "DETALLES DE LA PRUEBA:\n" .
                       "• Empresa: {$companyName}\n" .
                       "• Fecha y hora: {$timestamp}\n" .
                       "• Servidor SMTP: {$this->settings['smtp_host']}\n" .
                       "• Puerto: {$this->settings['smtp_port']}\n" .
                       "• Seguridad: " . strtoupper($this->settings['smtp_security']) . "\n\n" .
                       "¿QUÉ SIGNIFICA ESTO?\n" .
                       "• Su configuración SMTP es correcta\n" .
                       "• Los emails de cotizaciones se enviarán sin problemas\n" .
                       "• Los clientes recibirán sus cotizaciones automáticamente\n" .
                       "• El sistema está listo para uso en producción\n\n" .
                       "Si recibe este mensaje, puede proceder con confianza a enviar cotizaciones.\n\n" .
                       "---\n" .
                       "Email de prueba generado automáticamente • {$companyName} • {$timestamp}";
        }

        return $this->sendEmail($testEmail, $subject, $htmlBody, $textBody);
    }
}