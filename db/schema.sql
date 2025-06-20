-- ESQUEMA COMPLETO DE BASE DE DATOS CRM
-- Incluye todos los módulos: Usuarios, Clientes, Productos, Categorías y Cotizaciones
-- Versión: Actualizada con todos los módulos implementados

-- Configuración inicial
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Crear base de datos (opcional - descomenta si necesitas crear la BD)
-- CREATE DATABASE IF NOT EXISTS crm_database DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE crm_database;

-- =====================================================
-- MÓDULO DE USUARIOS
-- =====================================================

-- Tabla de usuarios del sistema
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role TINYINT NOT NULL DEFAULT 2,
    status TINYINT NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_last_login (last_login),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MÓDULO DE CLIENTES
-- =====================================================

-- Tabla de clientes
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    status TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_name (name),
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MÓDULO DE PRODUCTOS Y CATEGORÍAS
-- =====================================================

-- Tabla de categorías de productos
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    status TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de productos
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) NOT NULL,
    stock INT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_name (name),
    INDEX idx_category_id (category_id),
    INDEX idx_base_price (base_price),
    INDEX idx_status (status),
    INDEX idx_stock (stock),
    INDEX idx_created_at (created_at),
    
    -- Claves foráneas
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MÓDULO DE COTIZACIONES
-- =====================================================

-- Tabla principal de cotizaciones
CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(50) NOT NULL UNIQUE,
    client_id INT NOT NULL,
    quote_date DATE NOT NULL,
    valid_until DATE NOT NULL,
    notes TEXT,
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_quote_number (quote_number),
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_quote_date (quote_date),
    INDEX idx_valid_until (valid_until),
    INDEX idx_total_amount (total_amount),
    INDEX idx_created_at (created_at),
    
    -- Claves foráneas
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de detalles de cotizaciones
CREATE TABLE IF NOT EXISTS quote_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    line_subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total_with_tax DECIMAL(10,2) NOT NULL,
    
    -- Índices
    INDEX idx_quote_id (quote_id),
    INDEX idx_product_id (product_id),
    INDEX idx_line_total_with_tax (line_total_with_tax),
    
    -- Claves foráneas
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COMENTARIOS EN LAS TABLAS
-- =====================================================

-- Comentarios generales de tablas
ALTER TABLE users COMMENT = 'Usuarios del sistema CRM con roles y autenticación';
ALTER TABLE clients COMMENT = 'Clientes del CRM con información de contacto';
ALTER TABLE categories COMMENT = 'Categorías para organizar productos';
ALTER TABLE products COMMENT = 'Productos y servicios del catálogo';
ALTER TABLE quotes COMMENT = 'Cotizaciones enviadas a clientes';
ALTER TABLE quote_details COMMENT = 'Detalles/items de cada cotización';

-- Comentarios detallados en columnas críticas
ALTER TABLE users 
MODIFY COLUMN role TINYINT NOT NULL DEFAULT 2 COMMENT 'Rol: 1=Admin, 2=Seller',
MODIFY COLUMN status TINYINT NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activo, 0=Inactivo',
MODIFY COLUMN failed_login_attempts INT DEFAULT 0 COMMENT 'Intentos fallidos de login consecutivos',
MODIFY COLUMN locked_until TIMESTAMP NULL COMMENT 'Bloqueado hasta esta fecha/hora';

ALTER TABLE clients 
MODIFY COLUMN status TINYINT NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activo, 0=Inactivo, 2=Eliminado';

ALTER TABLE categories 
MODIFY COLUMN status TINYINT NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activa, 0=Inactiva';

ALTER TABLE products 
MODIFY COLUMN status TINYINT NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activo, 0=Inactivo',
MODIFY COLUMN stock INT NULL COMMENT 'Stock disponible (NULL = no se maneja inventario)';

ALTER TABLE quotes 
MODIFY COLUMN status TINYINT NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Borrador, 2=Enviada, 3=Aprobada, 4=Rechazada, 5=Vencida, 6=Cancelada';

-- =====================================================
-- DATOS DE EJEMPLO PARA TESTING
-- =====================================================

-- Usuario administrador por defecto (password: admin123)
INSERT IGNORE INTO users (username, email, password_hash, full_name, role, status) VALUES 
('admin', 'admin@crm.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador del Sistema', 1, 1);

-- Usuario vendedor de ejemplo (password: seller123)
INSERT IGNORE INTO users (username, email, password_hash, full_name, role, status) VALUES 
('seller1', 'vendedor@crm.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Pérez Vendedor', 2, 1);

-- Categorías de ejemplo
INSERT IGNORE INTO categories (name, status) VALUES 
('Electrónicos', 1),
('Ropa y Accesorios', 1),
('Hogar y Jardín', 1),
('Servicios', 1),
('Oficina', 1);

-- Clientes de ejemplo
INSERT IGNORE INTO clients (name, email, phone, address, status) VALUES 
('Empresa ABC S.A.', 'contacto@empresaabc.com', '+34 912 345 678', 'Calle Principal 123, Madrid, España', 1),
('María García López', 'maria.garcia@email.com', '+34 655 123 456', 'Avenida Secundaria 456, Barcelona, España', 1),
('Tecnología XYZ Ltd.', 'info@tecnologiaxyz.com', '+34 913 987 654', 'Plaza Mayor 789, Valencia, España', 1),
('Carlos Rodríguez', 'carlos.rodriguez@personal.com', '+34 666 789 123', 'Calle Menor 321, Sevilla, España', 1);

-- Productos de ejemplo
INSERT IGNORE INTO products (name, description, category_id, base_price, tax_rate, unit, stock, status) VALUES 
('Laptop HP EliteBook', 'Laptop profesional para oficina con 16GB RAM y SSD 512GB', 1, 899.00, 21.00, 'unidad', 15, 1),
('Mouse Inalámbrico', 'Mouse ergonómico inalámbrico con batería de larga duración', 1, 25.99, 21.00, 'unidad', 50, 1),
('Consultoría IT', 'Hora de consultoría en tecnologías de información', 4, 75.00, 21.00, 'hora', NULL, 1),
('Camisa Ejecutiva', 'Camisa de vestir para ejecutivos, 100% algodón', 2, 45.50, 21.00, 'unidad', 30, 1),
('Mesa de Oficina', 'Mesa de oficina de madera con cajones incorporados', 5, 299.99, 21.00, 'unidad', 8, 1);

-- Cotización de ejemplo
INSERT IGNORE INTO quotes (quote_number, client_id, quote_date, valid_until, notes, discount_percent, subtotal, tax_amount, total_amount, status) VALUES 
('COT-2025-0001', 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Cotización para renovación de equipos de oficina. Descuento por volumen aplicado.', 5.00, 1844.98, 369.00, 2022.48, 2);

-- Detalles de la cotización de ejemplo
INSERT IGNORE INTO quote_details (quote_id, product_id, product_name, quantity, unit_price, discount_percent, line_subtotal, discount_amount, line_total, tax_rate, tax_amount, line_total_with_tax) VALUES 
(1, 1, 'Laptop HP EliteBook', 2, 899.00, 0.00, 1798.00, 0.00, 1798.00, 21.00, 377.58, 2175.58),
(1, 2, 'Mouse Inalámbrico', 2, 25.99, 0.00, 51.98, 0.00, 51.98, 21.00, 10.92, 62.90);

-- =====================================================
-- VISTAS ÚTILES PARA CONSULTAS
-- =====================================================

-- Vista de productos con categoría
CREATE OR REPLACE VIEW view_products_with_category AS
SELECT 
    p.id,
    p.name as product_name,
    p.description,
    c.name as category_name,
    p.base_price,
    p.tax_rate,
    ROUND(p.base_price + (p.base_price * p.tax_rate / 100), 2) as final_price,
    p.unit,
    p.stock,
    p.status,
    p.created_at,
    p.updated_at
FROM products p
LEFT JOIN categories c ON p.category_id = c.id;

-- Vista de cotizaciones con cliente
CREATE OR REPLACE VIEW view_quotes_with_client AS
SELECT 
    q.id,
    q.quote_number,
    q.quote_date,
    q.valid_until,
    c.name as client_name,
    c.email as client_email,
    c.phone as client_phone,
    q.subtotal,
    q.discount_percent,
    q.tax_amount,
    q.total_amount,
    q.status,
    CASE q.status
        WHEN 1 THEN 'Borrador'
        WHEN 2 THEN 'Enviada'
        WHEN 3 THEN 'Aprobada'
        WHEN 4 THEN 'Rechazada'
        WHEN 5 THEN 'Vencida'
        WHEN 6 THEN 'Cancelada'
        ELSE 'Desconocido'
    END as status_name,
    q.notes,
    q.created_at,
    q.updated_at
FROM quotes q
LEFT JOIN clients c ON q.client_id = c.id;

-- Vista de resumen de cotizaciones por cliente
CREATE OR REPLACE VIEW view_client_quote_summary AS
SELECT 
    c.id as client_id,
    c.name as client_name,
    c.email as client_email,
    COUNT(q.id) as total_quotes,
    SUM(CASE WHEN q.status = 1 THEN 1 ELSE 0 END) as draft_quotes,
    SUM(CASE WHEN q.status = 2 THEN 1 ELSE 0 END) as sent_quotes,
    SUM(CASE WHEN q.status = 3 THEN 1 ELSE 0 END) as approved_quotes,
    SUM(CASE WHEN q.status = 4 THEN 1 ELSE 0 END) as rejected_quotes,
    COALESCE(SUM(q.total_amount), 0) as total_quoted_value,
    COALESCE(SUM(CASE WHEN q.status = 3 THEN q.total_amount ELSE 0 END), 0) as approved_value,
    MAX(q.created_at) as last_quote_date
FROM clients c
LEFT JOIN quotes q ON c.id = q.client_id
WHERE c.status = 1
GROUP BY c.id, c.name, c.email;

/*

-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS ÚTILES
-- =====================================================

-- Procedimiento para obtener estadísticas generales del CRM
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS GetCRMStats()
BEGIN
    SELECT 
        'Usuarios' as module_name,
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_records
    FROM users
    
    UNION ALL
    
    SELECT 
        'Clientes' as module_name,
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_records
    FROM clients
    
    UNION ALL
    
    SELECT 
        'Productos' as module_name,
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_records
    FROM products
    
    UNION ALL
    
    SELECT 
        'Cotizaciones' as module_name,
        COUNT(*) as total_records,
        SUM(CASE WHEN status IN (1,2,3) THEN 1 ELSE 0 END) as active_records
    FROM quotes;
END//
DELIMITER ;



-- Procedimiento para marcar cotizaciones vencidas
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS MarkExpiredQuotes()
BEGIN
    UPDATE quotes 
    SET status = 5, updated_at = NOW() 
    WHERE valid_until < CURDATE() 
    AND status IN (1, 2);
    
    SELECT ROW_COUNT() as quotes_marked_expired;
END//
DELIMITER ;

*/

-- =====================================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- =====================================================

-- Índice compuesto para búsquedas frecuentes
CREATE INDEX idx_clients_name_email ON clients(name, email);
CREATE INDEX idx_products_category_status ON products(category_id, status);
CREATE INDEX idx_quotes_client_status ON quotes(client_id, status);
CREATE INDEX idx_quotes_date_range ON quotes(quote_date, valid_until);

-- Índice para consultas de estadísticas
CREATE INDEX idx_quote_details_quote_product ON quote_details(quote_id, product_id);

-- =====================================================
-- TRIGGERS PARA AUDITORÍA Y CONSISTENCIA
-- =====================================================

-- Trigger para actualizar totales de cotización cuando se modifican detalles
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_quote_totals_after_detail_change
AFTER INSERT ON quote_details
FOR EACH ROW
BEGIN
    UPDATE quotes q
    SET 
        subtotal = (
            SELECT COALESCE(SUM(line_subtotal), 0) 
            FROM quote_details 
            WHERE quote_id = NEW.quote_id
        ),
        tax_amount = (
            SELECT COALESCE(SUM(tax_amount), 0) 
            FROM quote_details 
            WHERE quote_id = NEW.quote_id
        ),
        total_amount = (
            SELECT COALESCE(SUM(line_total_with_tax), 0) 
            FROM quote_details 
            WHERE quote_id = NEW.quote_id
        ),
        updated_at = NOW()
    WHERE q.id = NEW.quote_id;
END//
DELIMITER ;

-- =====================================================
-- VERIFICACIÓN FINAL
-- =====================================================

-- Mostrar todas las tablas creadas
SHOW TABLES;

-- Mostrar estructura de relaciones
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, COLUMN_NAME;

/*

-- Estadísticas de registros insertados
SELECT 
    'users' as tabla, COUNT(*) as registros FROM users
UNION ALL
SELECT 
    'clients' as tabla, COUNT(*) as registros FROM clients
UNION ALL
SELECT 
    'categories' as tabla, COUNT(*) as registros FROM categories
UNION ALL
SELECT 
    'products' as tabla, COUNT(*) as registros FROM products
UNION ALL
SELECT 
    'quotes' as tabla, COUNT(*) as registros FROM quotes
UNION ALL
SELECT 
    'quote_details' as tabla, COUNT(*) as registros FROM quote_details;

COMMIT;

*/