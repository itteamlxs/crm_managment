<?php
// Modelo para gestionar cotizaciones en el sistema CRM
require_once dirname(__DIR__, 2) . '/core/db.php';
require_once dirname(__DIR__, 2) . '/core/security.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

// Estados de cotización - Solo definir si no están ya definidas
if (!defined('QUOTE_STATUS_DRAFT')) {
    define('QUOTE_STATUS_DRAFT', 1);     // Borrador
}
if (!defined('QUOTE_STATUS_SENT')) {
    define('QUOTE_STATUS_SENT', 2);      // Enviada
}
if (!defined('QUOTE_STATUS_APPROVED')) {
    define('QUOTE_STATUS_APPROVED', 3);  // Aprobada
}
if (!defined('QUOTE_STATUS_REJECTED')) {
    define('QUOTE_STATUS_REJECTED', 4);  // Rechazada
}
if (!defined('QUOTE_STATUS_EXPIRED')) {
    define('QUOTE_STATUS_EXPIRED', 5);   // Vencida
}
if (!defined('QUOTE_STATUS_CANCELLED')) {
    define('QUOTE_STATUS_CANCELLED', 6); // Cancelada
}

class QuoteModel {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Error initializing Database: " . $e->getMessage());
            throw new Exception('No se pudo conectar a la base de datos.');
        }
    }

    // Crear una nueva cotización
    public function create($clientId, $validUntil, $notes = '', $discount = 0.0, $items = []) {
        // Validar entradas
        if (!Security::validate($clientId, 'int')) {
            throw new Exception('Cliente no válido.');
        }
        if (!$this->clientExists($clientId)) {
            throw new Exception('El cliente seleccionado no existe o está inactivo.');
        }
        if (!Security::validate($discount, 'float') || $discount < 0 || $discount > 100) {
            throw new Exception('Descuento no válido (0-100%).');
        }
        if (empty($items)) {
            throw new Exception('Debe agregar al menos un producto a la cotización.');
        }

        // Validar fecha de validez
        $validUntilDate = DateTime::createFromFormat('Y-m-d', $validUntil);
        if (!$validUntilDate || $validUntilDate <= new DateTime()) {
            throw new Exception('La fecha de validez debe ser futura.');
        }

        try {
            $this->db->beginTransaction();

            // Generar número de cotización
            $quoteNumber = $this->generateQuoteNumber();

            // Calcular totales
            $totals = $this->calculateTotals($items, $discount);

            // Insertar cotización principal
            $query = "INSERT INTO quotes (quote_number, client_id, quote_date, valid_until, notes, discount_percent, subtotal, tax_amount, total_amount, status, created_at) 
                      VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $quoteNumber,
                (int)$clientId,
                $validUntil,
                $notes ? Security::sanitize($notes, 'string') : null,
                (float)$discount,
                $totals['subtotal'],
                $totals['tax_amount'],
                $totals['total'],
                QUOTE_STATUS_DRAFT
            ];

            $quoteId = $this->db->insert($query, $params);

            // Insertar detalles de la cotización
            foreach ($items as $item) {
                $this->addQuoteDetail($quoteId, $item);
            }

            $this->db->commit();
            return $quoteId;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creating quote: " . $e->getMessage());
            throw new Exception('Error al crear la cotización: ' . $e->getMessage());
        }
    }

    // Obtener cotización por ID con detalles
    public function getById($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de cotización no válido.');
        }

        try {
            // Obtener cotización principal
            $query = "SELECT q.*, c.name as client_name, c.email as client_email, c.phone as client_phone, c.address as client_address
                      FROM quotes q 
                      LEFT JOIN clients c ON q.client_id = c.id 
                      WHERE q.id = ?";
            
            $result = $this->db->select($query, [(int)$id]);
            if (!$result) {
                return null;
            }

            $quote = $result[0];

            // Obtener detalles de la cotización
            $quote['items'] = $this->getQuoteDetails($id);

            return $quote;

        } catch (Exception $e) {
            error_log("Error fetching quote: " . $e->getMessage());
            throw new Exception('Error al obtener la cotización: ' . $e->getMessage());
        }
    }

    // Listar cotizaciones con filtros
    public function getAll($search = '', $clientId = null, $status = null, $dateFrom = null, $dateTo = null, $limit = null, $offset = 0) {
        $query = "SELECT q.*, c.name as client_name, c.email as client_email
                  FROM quotes q 
                  LEFT JOIN clients c ON q.client_id = c.id 
                  WHERE 1=1";
        $params = [];

        // Filtro de búsqueda
        if (!empty($search)) {
            $query .= " AND (q.quote_number LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR q.notes LIKE ?)";
            $searchTerm = '%' . Security::sanitize($search, 'string') . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filtro por cliente
        if ($clientId && Security::validate($clientId, 'int')) {
            $query .= " AND q.client_id = ?";
            $params[] = (int)$clientId;
        }

        // Filtro por estado
        if ($status !== null && Security::validate($status, 'int')) {
            $query .= " AND q.status = ?";
            $params[] = (int)$status;
        }

        // Filtro por rango de fechas
        if ($dateFrom) {
            $query .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $query .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        // Ordenamiento
        $query .= " ORDER BY q.created_at DESC";

        // Límite y offset para paginación
        if ($limit && Security::validate($limit, 'int')) {
            $query .= " LIMIT ?";
            $params[] = (int)$limit;
            
            if ($offset && Security::validate($offset, 'int')) {
                $query .= " OFFSET ?";
                $params[] = (int)$offset;
            }
        }

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error listing quotes: " . $e->getMessage());
            throw new Exception('Error al listar cotizaciones: ' . $e->getMessage());
        }
    }

    // Contar total de cotizaciones
    public function count($search = '', $clientId = null, $status = null, $dateFrom = null, $dateTo = null) {
        $query = "SELECT COUNT(*) as total FROM quotes q 
                  LEFT JOIN clients c ON q.client_id = c.id 
                  WHERE 1=1";
        $params = [];

        // Aplicar los mismos filtros que en getAll
        if (!empty($search)) {
            $query .= " AND (q.quote_number LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR q.notes LIKE ?)";
            $searchTerm = '%' . Security::sanitize($search, 'string') . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($clientId && Security::validate($clientId, 'int')) {
            $query .= " AND q.client_id = ?";
            $params[] = (int)$clientId;
        }

        if ($status !== null && Security::validate($status, 'int')) {
            $query .= " AND q.status = ?";
            $params[] = (int)$status;
        }

        if ($dateFrom) {
            $query .= " AND q.quote_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $query .= " AND q.quote_date <= ?";
            $params[] = $dateTo;
        }

        try {
            $result = $this->db->select($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error counting quotes: " . $e->getMessage());
            throw new Exception('Error al contar cotizaciones: ' . $e->getMessage());
        }
    }

    // Actualizar cotización
    public function update($id, $clientId, $validUntil, $notes = '', $discount = 0.0, $items = []) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de cotización no válido.');
        }

        // Verificar que la cotización existe y se puede editar
        $quote = $this->getById($id);
        if (!$quote) {
            throw new Exception('Cotización no encontrada.');
        }

        if (!$this->canEdit($quote['status'])) {
            throw new Exception('No se puede editar una cotización en estado ' . $this->getStatusName($quote['status']) . '.');
        }

        // Validar entradas (mismas validaciones que en create)
        if (!Security::validate($clientId, 'int')) {
            throw new Exception('Cliente no válido.');
        }
        if (!$this->clientExists($clientId)) {
            throw new Exception('El cliente seleccionado no existe o está inactivo.');
        }
        if (!Security::validate($discount, 'float') || $discount < 0 || $discount > 100) {
            throw new Exception('Descuento no válido (0-100%).');
        }
        if (empty($items)) {
            throw new Exception('Debe agregar al menos un producto a la cotización.');
        }

        $validUntilDate = DateTime::createFromFormat('Y-m-d', $validUntil);
        if (!$validUntilDate || $validUntilDate <= new DateTime()) {
            throw new Exception('La fecha de validez debe ser futura.');
        }

        try {
            $this->db->beginTransaction();

            // Calcular nuevos totales
            $totals = $this->calculateTotals($items, $discount);

            // Actualizar cotización principal
            $query = "UPDATE quotes SET client_id = ?, valid_until = ?, notes = ?, discount_percent = ?, 
                      subtotal = ?, tax_amount = ?, total_amount = ?, updated_at = NOW() 
                      WHERE id = ?";
            
            $params = [
                (int)$clientId,
                $validUntil,
                $notes ? Security::sanitize($notes, 'string') : null,
                (float)$discount,
                $totals['subtotal'],
                $totals['tax_amount'],
                $totals['total'],
                (int)$id
            ];

            $this->db->execute($query, $params);

            // Eliminar detalles existentes
            $this->db->execute("DELETE FROM quote_details WHERE quote_id = ?", [(int)$id]);

            // Insertar nuevos detalles
            foreach ($items as $item) {
                $this->addQuoteDetail($id, $item);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating quote: " . $e->getMessage());
            throw new Exception('Error al actualizar la cotización: ' . $e->getMessage());
        }
    }

    // Cambiar estado de la cotización
    public function changeStatus($id, $newStatus) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de cotización no válido.');
        }

        $validStatuses = [
            QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT, QUOTE_STATUS_APPROVED, 
            QUOTE_STATUS_REJECTED, QUOTE_STATUS_EXPIRED, QUOTE_STATUS_CANCELLED
        ];

        if (!in_array($newStatus, $validStatuses)) {
            throw new Exception('Estado no válido.');
        }

        // Verificar transiciones válidas
        $quote = $this->getById($id);
        if (!$quote) {
            throw new Exception('Cotización no encontrada.');
        }

        if (!$this->isValidTransition($quote['status'], $newStatus)) {
            throw new Exception('Transición de estado no válida.');
        }

        try {
            $query = "UPDATE quotes SET status = ?, updated_at = NOW() WHERE id = ?";
            return $this->db->execute($query, [(int)$newStatus, (int)$id]);
        } catch (Exception $e) {
            error_log("Error changing quote status: " . $e->getMessage());
            throw new Exception('Error al cambiar estado de la cotización: ' . $e->getMessage());
        }
    }

    // Eliminar cotización (soft delete)
    public function delete($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de cotización no válido.');
        }

        $quote = $this->getById($id);
        if (!$quote) {
            throw new Exception('Cotización no encontrada.');
        }

        if (!$this->canDelete($quote['status'])) {
            throw new Exception('No se puede eliminar una cotización en estado ' . $this->getStatusName($quote['status']) . '.');
        }

        try {
            return $this->changeStatus($id, QUOTE_STATUS_CANCELLED);
        } catch (Exception $e) {
            error_log("Error deleting quote: " . $e->getMessage());
            throw new Exception('Error al eliminar la cotización: ' . $e->getMessage());
        }
    }

    // Generar número de cotización automático
    private function generateQuoteNumber() {
        $year = date('Y');
        $prefix = 'COT-' . $year . '-';
        
        try {
            $query = "SELECT MAX(CAST(SUBSTRING(quote_number, ?) AS UNSIGNED)) as max_num 
                      FROM quotes 
                      WHERE quote_number LIKE ?";
            
            $result = $this->db->select($query, [strlen($prefix) + 1, $prefix . '%']);
            $maxNum = $result[0]['max_num'] ?? 0;
            
            return $prefix . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            error_log("Error generating quote number: " . $e->getMessage());
            throw new Exception('Error al generar número de cotización.');
        }
    }

    // Agregar detalle a la cotización
    private function addQuoteDetail($quoteId, $item) {
        // Validar item
        if (!isset($item['product_id']) || !Security::validate($item['product_id'], 'int')) {
            throw new Exception('Producto no válido en item.');
        }
        if (!isset($item['quantity']) || !Security::validate($item['quantity'], 'int') || $item['quantity'] <= 0) {
            throw new Exception('Cantidad no válida en item.');
        }
        if (!isset($item['unit_price']) || !Security::validate($item['unit_price'], 'float') || $item['unit_price'] <= 0) {
            throw new Exception('Precio unitario no válido en item.');
        }

        $discount = isset($item['discount']) && Security::validate($item['discount'], 'float') ? (float)$item['discount'] : 0.0;
        if ($discount < 0 || $discount > 100) {
            throw new Exception('Descuento de item no válido (0-100%).');
        }

        // Verificar que el producto existe y está activo
        if (!$this->productExists($item['product_id'])) {
            throw new Exception('El producto seleccionado no existe o está inactivo.');
        }

        // Obtener información del producto
        $product = $this->getProductInfo($item['product_id']);
        
        // Calcular valores
        $quantity = (int)$item['quantity'];
        $unitPrice = (float)$item['unit_price'];
        $lineSubtotal = $quantity * $unitPrice;
        $discountAmount = ($lineSubtotal * $discount) / 100;
        $lineTotal = $lineSubtotal - $discountAmount;
        $taxAmount = ($lineTotal * $product['tax_rate']) / 100;
        $lineTotalWithTax = $lineTotal + $taxAmount;

        $query = "INSERT INTO quote_details (quote_id, product_id, product_name, quantity, unit_price, discount_percent, line_subtotal, discount_amount, line_total, tax_rate, tax_amount, line_total_with_tax) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            (int)$quoteId,
            (int)$item['product_id'],
            $product['name'],
            $quantity,
            $unitPrice,
            $discount,
            $lineSubtotal,
            $discountAmount,
            $lineTotal,
            $product['tax_rate'],
            $taxAmount,
            $lineTotalWithTax
        ];

        return $this->db->insert($query, $params);
    }

    // Obtener detalles de la cotización
    private function getQuoteDetails($quoteId) {
        $query = "SELECT qd.*, p.unit, p.base_price as current_base_price
                  FROM quote_details qd 
                  LEFT JOIN products p ON qd.product_id = p.id 
                  WHERE qd.quote_id = ? 
                  ORDER BY qd.id";
        
        try {
            return $this->db->select($query, [(int)$quoteId]);
        } catch (Exception $e) {
            error_log("Error fetching quote details: " . $e->getMessage());
            throw new Exception('Error al obtener detalles de la cotización.');
        }
    }

    // Calcular totales de la cotización
    private function calculateTotals($items, $globalDiscount = 0.0) {
        $subtotal = 0.0;
        $totalDiscountAmount = 0.0;
        $totalTaxAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];
            $discount = isset($item['discount']) ? (float)$item['discount'] : 0.0;
            
            // Obtener información del producto para la tasa de impuesto
            $product = $this->getProductInfo($item['product_id']);
            
            $lineSubtotal = $quantity * $unitPrice;
            $discountAmount = ($lineSubtotal * $discount) / 100;
            $lineTotal = $lineSubtotal - $discountAmount;
            $taxAmount = ($lineTotal * $product['tax_rate']) / 100;
            
            $subtotal += $lineSubtotal;
            $totalDiscountAmount += $discountAmount;
            $totalTaxAmount += $taxAmount;
        }

        // Aplicar descuento global después de descuentos por línea
        $subtotalAfterLineDiscounts = $subtotal - $totalDiscountAmount;
        $globalDiscountAmount = ($subtotalAfterLineDiscounts * $globalDiscount) / 100;
        $finalSubtotal = $subtotalAfterLineDiscounts - $globalDiscountAmount;
        
        // Recalcular impuestos sobre el subtotal con descuento global
        $adjustedTaxAmount = 0.0;
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];
            $discount = isset($item['discount']) ? (float)$item['discount'] : 0.0;
            
            $product = $this->getProductInfo($item['product_id']);
            
            $lineSubtotal = $quantity * $unitPrice;
            $discountAmount = ($lineSubtotal * $discount) / 100;
            $lineTotal = $lineSubtotal - $discountAmount;
            
            // Aplicar proporción del descuento global
            $proportion = $subtotalAfterLineDiscounts > 0 ? $lineTotal / $subtotalAfterLineDiscounts : 0;
            $lineTotalWithGlobalDiscount = $lineTotal - ($globalDiscountAmount * $proportion);
            
            $adjustedTaxAmount += ($lineTotalWithGlobalDiscount * $product['tax_rate']) / 100;
        }

        $total = $finalSubtotal + $adjustedTaxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($totalDiscountAmount + $globalDiscountAmount, 2),
            'tax_amount' => round($adjustedTaxAmount, 2),
            'total' => round($total, 2)
        ];
    }

    // Verificar si el cliente existe y está activo
    private function clientExists($clientId) {
        try {
            $query = "SELECT COUNT(*) as count FROM clients WHERE id = ? AND status = ?";
            $result = $this->db->select($query, [(int)$clientId, STATUS_ACTIVE]);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking client existence: " . $e->getMessage());
            return false;
        }
    }

    // Verificar si el producto existe y está activo
    private function productExists($productId) {
        try {
            $query = "SELECT COUNT(*) as count FROM products WHERE id = ? AND status = ?";
            $result = $this->db->select($query, [(int)$productId, STATUS_ACTIVE]);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking product existence: " . $e->getMessage());
            return false;
        }
    }

    // Obtener información del producto
    private function getProductInfo($productId) {
        try {
            $query = "SELECT name, base_price, tax_rate, unit FROM products WHERE id = ?";
            $result = $this->db->select($query, [(int)$productId]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error getting product info: " . $e->getMessage());
            throw new Exception('Error al obtener información del producto.');
        }
    }

    // Verificar si se puede editar la cotización
    private function canEdit($status) {
        return in_array($status, [QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT]);
    }

    // Verificar si se puede eliminar la cotización
    private function canDelete($status) {
        return in_array($status, [QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT, QUOTE_STATUS_REJECTED]);
    }

    // Verificar transiciones válidas de estado
    private function isValidTransition($currentStatus, $newStatus) {
        $validTransitions = [
            QUOTE_STATUS_DRAFT => [QUOTE_STATUS_SENT, QUOTE_STATUS_CANCELLED],
            QUOTE_STATUS_SENT => [QUOTE_STATUS_APPROVED, QUOTE_STATUS_REJECTED, QUOTE_STATUS_EXPIRED, QUOTE_STATUS_CANCELLED],
            QUOTE_STATUS_APPROVED => [QUOTE_STATUS_CANCELLED],
            QUOTE_STATUS_REJECTED => [QUOTE_STATUS_SENT, QUOTE_STATUS_CANCELLED],
            QUOTE_STATUS_EXPIRED => [QUOTE_STATUS_SENT, QUOTE_STATUS_CANCELLED],
            QUOTE_STATUS_CANCELLED => [] // No se puede cambiar desde cancelada
        ];

        return isset($validTransitions[$currentStatus]) && in_array($newStatus, $validTransitions[$currentStatus]);
    }

    // Obtener nombre del estado
    public function getStatusName($status) {
        $statusNames = [
            QUOTE_STATUS_DRAFT => 'Borrador',
            QUOTE_STATUS_SENT => 'Enviada',
            QUOTE_STATUS_APPROVED => 'Aprobada',
            QUOTE_STATUS_REJECTED => 'Rechazada',
            QUOTE_STATUS_EXPIRED => 'Vencida',
            QUOTE_STATUS_CANCELLED => 'Cancelada'
        ];

        return $statusNames[$status] ?? 'Desconocido';
    }

    // Obtener estadísticas generales
    public function getGeneralStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_quotes,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft_quotes,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent_quotes,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_quotes,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_quotes,
                        SUM(total_amount) as total_value,
                        AVG(total_amount) as avg_value,
                        COUNT(CASE WHEN valid_until < CURDATE() AND status IN (?, ?) THEN 1 END) as expired_quotes
                      FROM quotes";
            
            $params = [
                QUOTE_STATUS_DRAFT, QUOTE_STATUS_SENT, QUOTE_STATUS_APPROVED, QUOTE_STATUS_REJECTED,
                QUOTE_STATUS_SENT, QUOTE_STATUS_DRAFT
            ];
            
            $result = $this->db->select($query, $params);
            if ($result) {
                $stats = $result[0];
                $stats['avg_value'] = round($stats['avg_value'] ?? 0, 2);
                $stats['total_value'] = round($stats['total_value'] ?? 0, 2);
                return $stats;
            }

            return [];
        } catch (Exception $e) {
            error_log("Error getting general stats: " . $e->getMessage());
            throw new Exception('Error al obtener estadísticas generales: ' . $e->getMessage());
        }
    }

    // Marcar cotizaciones vencidas automáticamente
    public function markExpiredQuotes() {
        try {
            $query = "UPDATE quotes SET status = ?, updated_at = NOW() 
                      WHERE valid_until < CURDATE() AND status IN (?, ?)";
            
            return $this->db->execute($query, [
                QUOTE_STATUS_EXPIRED, 
                QUOTE_STATUS_SENT, 
                QUOTE_STATUS_DRAFT
            ]);
        } catch (Exception $e) {
            error_log("Error marking expired quotes: " . $e->getMessage());
            throw new Exception('Error al marcar cotizaciones vencidas.');
        }
    }
}
?>