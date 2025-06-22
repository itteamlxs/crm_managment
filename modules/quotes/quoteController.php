<?php
// Controlador para gestionar cotizaciones
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/quotes/quoteModel.php';
    require_once dirname(__DIR__) . '/clients/clientModel.php';
    require_once dirname(__DIR__) . '/products/productModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

class QuoteController {
    private $session;
    private $quoteModel;
    private $clientModel;
    private $productModel;

    public function __construct() {
        try {
            $this->session = new Session();
            $this->quoteModel = new QuoteModel();
            $this->clientModel = new ClientModel();
            $this->productModel = new ProductModel();
            Security::setHeaders();
        } catch (Exception $e) {
            error_log("Error initializing QuoteController: " . $e->getMessage());
            die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    // Verificar autenticación y permisos
    private function checkAuth() {
        if (!$this->session->isLoggedIn()) {
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
            exit;
        }
    }

    // Obtener token CSRF para formularios
    public function getCsrfToken() {
        return $this->session->getCsrfToken();
    }

    // Verificar si está autenticado
    public function isAuthenticated() {
        return $this->session->isLoggedIn();
    }

    // Obtener rol del usuario actual
    public function getUserRole() {
        return $this->session->hasRole(ROLE_ADMIN) ? 'admin' : 'seller';
    }

    // Listar cotizaciones con filtros
    public function listQuotes($search = '', $clientId = null, $status = null, $dateFrom = null, $dateTo = null, $page = 1, $perPage = 20) {
        $this->checkAuth();
        
        try {
            // Marcar cotizaciones vencidas automáticamente
            $this->quoteModel->markExpiredQuotes();
            
            $offset = ($page - 1) * $perPage;
            $quotes = $this->quoteModel->getAll($search, $clientId, $status, $dateFrom, $dateTo, $perPage, $offset);
            $total = $this->quoteModel->count($search, $clientId, $status, $dateFrom, $dateTo);
            
            return [
                'quotes' => $quotes,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage),
                'search' => $search,
                'clientId' => $clientId,
                'status' => $status,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ];
        } catch (Exception $e) {
            error_log("Error listing quotes: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Obtener cotización por ID
    public function getQuoteById($id) {
        $this->checkAuth();
        
        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de cotización no válido.'];
        }

        try {
            $quote = $this->quoteModel->getById($id);
            return $quote ? ['quote' => $quote] : ['error' => 'Cotización no encontrada.'];
        } catch (Exception $e) {
            error_log("Error getting quote: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Crear nueva cotización
    public function createQuote() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos básicos
            $clientId = (int)($_POST['client_id'] ?? 0);
            $validUntil = trim($_POST['valid_until'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $discount = (float)($_POST['discount'] ?? 0);

            // Validaciones básicas
            if ($clientId <= 0) {
                return ['error' => 'Debe seleccionar un cliente.'];
            }

            if (empty($validUntil)) {
                return ['error' => 'La fecha de validez es requerida.'];
            }

            // Validar formato de fecha
            $validUntilDate = DateTime::createFromFormat('Y-m-d', $validUntil);
            if (!$validUntilDate) {
                return ['error' => 'Formato de fecha no válido.'];
            }

            if ($validUntilDate <= new DateTime()) {
                return ['error' => 'La fecha de validez debe ser futura.'];
            }

            if ($discount < 0 || $discount > 100) {
                return ['error' => 'El descuento debe estar entre 0% y 100%.'];
            }

            // Procesar items
            $items = $this->processQuoteItems($_POST);
            if (isset($items['error'])) {
                return $items;
            }

            if (empty($items)) {
                return ['error' => 'Debe agregar al menos un producto a la cotización.'];
            }

            try {
                $quoteId = $this->quoteModel->create($clientId, $validUntil, $notes, $discount, $items);
                header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?success=created');
                exit;
            } catch (Exception $e) {
                error_log("Error creating quote: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Actualizar cotización
    public function updateQuote() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos básicos
            $id = (int)($_POST['id'] ?? 0);
            $clientId = (int)($_POST['client_id'] ?? 0);
            $validUntil = trim($_POST['valid_until'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $discount = (float)($_POST['discount'] ?? 0);

            // Validaciones básicas
            if ($id <= 0) {
                return ['error' => 'ID de cotización no válido.'];
            }

            if ($clientId <= 0) {
                return ['error' => 'Debe seleccionar un cliente.'];
            }

            if (empty($validUntil)) {
                return ['error' => 'La fecha de validez es requerida.'];
            }

            $validUntilDate = DateTime::createFromFormat('Y-m-d', $validUntil);
            if (!$validUntilDate) {
                return ['error' => 'Formato de fecha no válido.'];
            }

            if ($validUntilDate <= new DateTime()) {
                return ['error' => 'La fecha de validez debe ser futura.'];
            }

            if ($discount < 0 || $discount > 100) {
                return ['error' => 'El descuento debe estar entre 0% y 100%.'];
            }

            // Procesar items
            $items = $this->processQuoteItems($_POST);
            if (isset($items['error'])) {
                return $items;
            }

            if (empty($items)) {
                return ['error' => 'Debe agregar al menos un producto a la cotización.'];
            }

            try {
                $this->quoteModel->update($id, $clientId, $validUntil, $notes, $discount, $items);
                header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?success=updated');
                exit;
            } catch (Exception $e) {
                error_log("Error updating quote: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Cambiar estado de la cotización
    public function changeQuoteStatus($id, $newStatus) {
        $this->checkAuth();
        
        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de cotización no válido.'];
        }

        $validStatuses = [
            QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT, QUOTE_STATUS_APPROVED, 
            QUOTE_STATUS_REJECTED, QUOTE_STATUS_EXPIRED, QUOTE_STATUS_CANCELLED
        ];

        if (!in_array($newStatus, $validStatuses)) {
            return ['error' => 'Estado no válido.'];
        }

        try {
            // Ejecutar el cambio de estado (que incluye el descuento de stock si es aprobación)
            $this->quoteModel->changeStatus($id, $newStatus);
            
            // Si se aprobó la cotización, verificar stock bajo
            $alertMessage = '';
            if ($newStatus == QUOTE_STATUS_APPROVED) {
                $stockAlert = $this->quoteModel->checkLowStockAfterApproval($id);
                if ($stockAlert['has_low_stock']) {
                    $alertMessage = '&stock_alert=' . urlencode($stockAlert['message']);
                }
            }
            
            $statusNames = [
                QUOTE_STATUS_DRAFT => 'borrador',
                QUOTE_STATUS_SENT => 'enviada',
                QUOTE_STATUS_APPROVED => 'aprobada',
                QUOTE_STATUS_REJECTED => 'rechazada',
                QUOTE_STATUS_EXPIRED => 'vencida',
                QUOTE_STATUS_CANCELLED => 'cancelada'
            ];
            
            $action = isset($statusNames[$newStatus]) ? $statusNames[$newStatus] : 'actualizada';
            header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?success=' . $action . $alertMessage);
            exit;
            
        } catch (Exception $e) {
            error_log("Error changing quote status: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // *** NUEVA FUNCIÓN: Obtener productos con stock bajo ***
    public function getLowStockProducts() {
        $this->checkAuth();
        
        try {
            return $this->quoteModel->getAllLowStockProducts();
        } catch (Exception $e) {
            error_log("Error getting low stock products: " . $e->getMessage());
            return [];
        }
    }

    // *** NUEVA FUNCIÓN: Obtener estadísticas de stock ***
    public function getStockStatistics() {
        $this->checkAuth();
        
        try {
            return $this->quoteModel->getStockStatistics();
        } catch (Exception $e) {
            error_log("Error getting stock statistics: " . $e->getMessage());
            return [];
        }
    }

    // *** NUEVA FUNCIÓN: Verificar stock disponible antes de crear/editar cotización ***
    public function validateStockForQuote($items) {
        $this->checkAuth();
        
        $warnings = [];
        
        foreach ($items as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                continue;
            }
            
            try {
                $productInfo = $this->getProductStockInfo($item['product_id']);
                
                if ($productInfo && $productInfo['stock'] !== null) {
                    $requiredQuantity = (int)$item['quantity'];
                    $availableStock = (int)$productInfo['stock'];
                    
                    if ($availableStock < $requiredQuantity) {
                        $warnings[] = [
                            'product_name' => $productInfo['name'],
                            'required' => $requiredQuantity,
                            'available' => $availableStock,
                            'deficit' => $requiredQuantity - $availableStock
                        ];
                    } elseif ($availableStock - $requiredQuantity <= STOCK_WARNING_THRESHOLD) {
                        $warnings[] = [
                            'product_name' => $productInfo['name'],
                            'required' => $requiredQuantity,
                            'available' => $availableStock,
                            'warning' => 'Stock quedará muy bajo después de la venta'
                        ];
                    }
                }
                
            } catch (Exception $e) {
                error_log("Error validating stock for product {$item['product_id']}: " . $e->getMessage());
            }
        }
        
        return $warnings;
    }

    // *** FUNCIÓN HELPER: Obtener información de stock de un producto ***
    private function getProductStockInfo($productId) {
        try {
            $db = new Database();
            $query = "SELECT id, name, stock, unit FROM products WHERE id = ? AND status = ?";
            $result = $db->select($query, [(int)$productId, STATUS_ACTIVE]);
            
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error getting product stock info: " . $e->getMessage());
            return null;
        }
    }

    // Eliminar cotización
    public function deleteQuote($id) {
        $this->checkAuth();
        
        // Solo administradores pueden eliminar
        if (!$this->session->hasRole(ROLE_ADMIN)) {
            return ['error' => 'No tiene permisos para eliminar cotizaciones.'];
        }

        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de cotización no válido.'];
        }

        try {
            $this->quoteModel->delete($id);
            header('Location: ' . BASE_URL . '/modules/quotes/quoteList.php?success=deleted');
            exit;
        } catch (Exception $e) {
            error_log("Error deleting quote: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Obtener clientes activos para selectores
    public function getActiveClients() {
        $this->checkAuth();
        
        try {
            return $this->clientModel->getAll('', STATUS_ACTIVE); // Sin límite para obtener todos
        } catch (Exception $e) {
            error_log("Error getting active clients: " . $e->getMessage());
            return [];
        }
    }

    // Obtener productos activos para selectores
    public function getActiveProducts() {
        $this->checkAuth();
        
        try {
            return $this->productModel->getActive();
        } catch (Exception $e) {
            error_log("Error getting active products: " . $e->getMessage());
            return [];
        }
    }

    // Obtener información de producto para AJAX
    public function getProductInfo($productId) {
        $this->checkAuth();
        
        if (!Security::validate($productId, 'int')) {
            return ['error' => 'ID de producto no válido.'];
        }

        try {
            $product = $this->productModel->getById($productId);
            if (!$product) {
                return ['error' => 'Producto no encontrado.'];
            }

            // Calcular precio final
            $finalPrice = $this->productModel->calculateFinalPrice($product['base_price'], $product['tax_rate']);

            return [
                'success' => true,
                'product' => [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'base_price' => $product['base_price'],
                    'tax_rate' => $product['tax_rate'],
                    'final_price' => $finalPrice,
                    'unit' => $product['unit'],
                    'stock' => $product['stock'],
                    'category_name' => $product['category_name']
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting product info: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Procesar items de la cotización desde POST
    private function processQuoteItems($postData) {
        $items = [];
        
        if (!isset($postData['items']) || !is_array($postData['items'])) {
            return ['error' => 'No se encontraron items en la cotización.'];
        }

        foreach ($postData['items'] as $index => $item) {
            // Validar cada campo del item
            if (!isset($item['product_id']) || empty($item['product_id'])) {
                continue; // Saltar items vacíos
            }

            $productId = (int)$item['product_id'];
            $quantity = (int)($item['quantity'] ?? 0);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $discount = (float)($item['discount'] ?? 0);

            // Validaciones del item
            if ($productId <= 0) {
                return ['error' => "Producto no válido en item " . ($index + 1) . "."];
            }

            if ($quantity <= 0) {
                return ['error' => "Cantidad no válida en item " . ($index + 1) . ". Debe ser mayor a 0."];
            }

            if ($unitPrice <= 0) {
                return ['error' => "Precio unitario no válido en item " . ($index + 1) . ". Debe ser mayor a 0."];
            }

            if ($discount < 0 || $discount > 100) {
                return ['error' => "Descuento no válido en item " . ($index + 1) . ". Debe estar entre 0% y 100%."];
            }

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount
            ];
        }

        return $items;
    }

    // Exportar cotizaciones a CSV
    public function exportQuotesCsv($search = '', $clientId = null, $status = null, $dateFrom = null, $dateTo = null) {
        $this->checkAuth();
        
        try {
            $quotes = $this->quoteModel->getAll($search, $clientId, $status, $dateFrom, $dateTo); // Sin límite para exportar todos
            
            // Headers para CSV
            $headers = [
                'Número',
                'Cliente',
                'Email Cliente',
                'Fecha Cotización',
                'Válida Hasta',
                'Estado',
                'Subtotal',
                'Descuento (%)',
                'Impuestos',
                'Total',
                'Notas',
                'Fecha Creación',
                'Última Actualización'
            ];
            
            // Preparar datos
            $data = [$headers];
            foreach ($quotes as $quote) {
                $data[] = [
                    $quote['quote_number'],
                    $quote['client_name'] ?? '',
                    $quote['client_email'] ?? '',
                    Utils::formatDateDisplay($quote['quote_date']),
                    Utils::formatDateDisplay($quote['valid_until']),
                    $this->quoteModel->getStatusName($quote['status']),
                    number_format($quote['subtotal'], 2),
                    number_format($quote['discount_percent'], 2),
                    number_format($quote['tax_amount'], 2),
                    number_format($quote['total_amount'], 2),
                    $quote['notes'] ?? '',
                    Utils::formatDateDisplay($quote['created_at']),
                    $quote['updated_at'] ? Utils::formatDateDisplay($quote['updated_at']) : ''
                ];
            }
            
            // Generar nombre de archivo
            $filename = 'cotizaciones_' . date('Y-m-d_H-i-s') . '.csv';
            
            // Enviar CSV
            Utils::generateCsv($data, $filename);
            
        } catch (Exception $e) {
            error_log("Error exporting quotes: " . $e->getMessage());
            return ['error' => 'Error al exportar cotizaciones: ' . $e->getMessage()];
        }
    }

    // Obtener estadísticas generales
    public function getGeneralStats() {
        $this->checkAuth();
        
        try {
            return $this->quoteModel->getGeneralStats();
        } catch (Exception $e) {
            error_log("Error getting general stats: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Obtener estados disponibles para filtros
    public function getAvailableStatuses() {
        return [
            QUOTE_STATUS_DRAFT => 'Borrador',
            QUOTE_STATUS_SENT => 'Enviada',
            QUOTE_STATUS_APPROVED => 'Aprobada',
            QUOTE_STATUS_REJECTED => 'Rechazada',
            QUOTE_STATUS_EXPIRED => 'Vencida',
            QUOTE_STATUS_CANCELLED => 'Cancelada'
        ];
    }

    // Verificar si se puede editar una cotización
    public function canEditQuote($status) {
        return in_array($status, [QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT]);
    }

    // Verificar si se puede eliminar una cotización
    public function canDeleteQuote($status) {
        return in_array($status, [QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT, QUOTE_STATUS_REJECTED]);
    }

    // Calcular totales para preview en tiempo real (AJAX)
    public function calculateTotalsPreview() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['items']) || !is_array($input['items'])) {
                echo json_encode(['error' => 'Items no válidos']);
                return;
            }

            $globalDiscount = (float)($input['discount'] ?? 0);
            
            if ($globalDiscount < 0 || $globalDiscount > 100) {
                echo json_encode(['error' => 'Descuento global no válido']);
                return;
            }

            // Simular el cálculo usando el método del modelo
            $items = [];
            foreach ($input['items'] as $item) {
                if (empty($item['product_id']) || empty($item['quantity']) || empty($item['unit_price'])) {
                    continue;
                }

                $items[] = [
                    'product_id' => (int)$item['product_id'],
                    'quantity' => (int)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'discount' => (float)($item['discount'] ?? 0)
                ];
            }

            if (empty($items)) {
                echo json_encode([
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total' => 0
                ]);
                return;
            }

            // Usar el método privado a través de reflexión o crear un método público
            $totals = $this->calculateTotalsForItems($items, $globalDiscount);
            echo json_encode($totals);

        } catch (Exception $e) {
            error_log("Error calculating totals preview: " . $e->getMessage());
            echo json_encode(['error' => 'Error al calcular totales']);
        }
    }

    // Método público para calcular totales (helper para AJAX)
    private function calculateTotalsForItems($items, $globalDiscount = 0.0) {
        $subtotal = 0.0;
        $totalDiscountAmount = 0.0;
        $totalTaxAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];
            $discount = (float)($item['discount'] ?? 0);
            
            // Obtener información del producto para la tasa de impuesto
            try {
                $product = $this->productModel->getById($item['product_id']);
                $taxRate = $product ? (float)$product['tax_rate'] : 0.0;
            } catch (Exception $e) {
                $taxRate = 0.0;
            }
            
            $lineSubtotal = $quantity * $unitPrice;
            $discountAmount = ($lineSubtotal * $discount) / 100;
            $lineTotal = $lineSubtotal - $discountAmount;
            $taxAmount = ($lineTotal * $taxRate) / 100;
            
            $subtotal += $lineSubtotal;
            $totalDiscountAmount += $discountAmount;
            $totalTaxAmount += $taxAmount;
        }

        // Aplicar descuento global
        $subtotalAfterLineDiscounts = $subtotal - $totalDiscountAmount;
        $globalDiscountAmount = ($subtotalAfterLineDiscounts * $globalDiscount) / 100;
        $finalSubtotal = $subtotalAfterLineDiscounts - $globalDiscountAmount;
        
        // Recalcular impuestos sobre el subtotal con descuento global
        $adjustedTaxAmount = 0.0;
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];
            $discount = (float)($item['discount'] ?? 0);
            
            try {
                $product = $this->productModel->getById($item['product_id']);
                $taxRate = $product ? (float)$product['tax_rate'] : 0.0;
            } catch (Exception $e) {
                $taxRate = 0.0;
            }
            
            $lineSubtotal = $quantity * $unitPrice;
            $discountAmount = ($lineSubtotal * $discount) / 100;
            $lineTotal = $lineSubtotal - $discountAmount;
            
            // Aplicar proporción del descuento global
            $proportion = $subtotalAfterLineDiscounts > 0 ? $lineTotal / $subtotalAfterLineDiscounts : 0;
            $lineTotalWithGlobalDiscount = $lineTotal - ($globalDiscountAmount * $proportion);
            
            $adjustedTaxAmount += ($lineTotalWithGlobalDiscount * $taxRate) / 100;
        }

        $total = $finalSubtotal + $adjustedTaxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($totalDiscountAmount + $globalDiscountAmount, 2),
            'tax_amount' => round($adjustedTaxAmount, 2),
            'total' => round($total, 2)
        ];
    }
}
?>