<?php
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

// Verificar autenticación
$session = new Session();
if (!$session->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
    exit;
}

// Verificar que se envió por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?error=invalid_request');
    exit;
}

// Validar token CSRF
if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?error=invalid_token');
    exit;
}

// Obtener ID de la cotización
$quoteId = (int)($_POST['quote_id'] ?? 0);
if ($quoteId <= 0) {
    header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?error=invalid_quote');
    exit;
}

// Obtener opción de adjuntar PDF
$attachPdf = isset($_POST['attach_pdf']) && $_POST['attach_pdf'] === '1';

try {
    // Instanciar servicio de email
    $emailService = new EmailService();
    
    // Verificar configuración
    if (!$emailService->isConfigured()) {
        header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?error=email_not_configured');
        exit;
    }
    
    // Enviar cotización
    $result = $emailService->sendQuoteEmail($quoteId, $attachPdf);
    
    // Cambiar estado de la cotización a "Enviada"
    require_once __DIR__ . '/quoteModel.php';
    $quoteModel = new QuoteModel();
    $quoteModel->changeStatus($quoteId, QUOTE_STATUS_SENT);
    
    // Redireccionar con éxito
    header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?success=quote_sent&email=' . urlencode($result['to']));
    exit;

} catch (Exception $e) {
    error_log("Error sending quote email: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?error=send_failed&message=' . urlencode($e->getMessage()));
    exit;
}
?>