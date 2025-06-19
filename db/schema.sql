-- Script SQL para crear la base de datos del CRM
-- Normalizada en 3FN
-- Compatible con MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_db;

-- Tabla de usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Hash de la contraseña
    role TINYINT NOT NULL, -- 1: Admin, 2: Seller
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1, -- 1: Active, 0: Inactive
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de clientes
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    status TINYINT NOT NULL DEFAULT 1, -- 1: Active, 0: Inactive
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de categorías de productos
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    status TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Tabla de productos
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) NOT NULL, -- Ej: pieza, kg, litro
    stock INT,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de cotizaciones
CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    user_id INT NOT NULL,
    quote_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, accepted, rejected
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de detalles de cotizaciones
CREATE TABLE quote_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de ventas
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT,
    client_id INT NOT NULL,
    user_id INT NOT NULL,
    sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de detalles de ventas
CREATE TABLE sale_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de configuraciones
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL,
    logo_path VARCHAR(255),
    language VARCHAR(10) NOT NULL DEFAULT 'es',
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    timezone VARCHAR(50) NOT NULL DEFAULT 'America/Mexico_City',
    theme VARCHAR(20) NOT NULL DEFAULT 'light',
    email_smtp_host VARCHAR(255),
    email_smtp_port INT,
    email_smtp_user VARCHAR(255),
    email_smtp_pass VARCHAR(255),
    db_host VARCHAR(255) NOT NULL DEFAULT 'localhost',
    db_name VARCHAR(255) NOT NULL DEFAULT 'crm_db',
    db_user VARCHAR(255) NOT NULL DEFAULT 'root',
    db_pass VARCHAR(255) NOT NULL DEFAULT '',
    db_charset VARCHAR(50) NOT NULL DEFAULT 'utf8mb4',
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de logs (para auditoría)
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insertar configuración inicial
INSERT INTO settings (
    company_name, language, currency, tax_rate, timezone, theme,
    db_host, db_name, db_user, db_pass, db_charset
) VALUES (
    'Mi Empresa', 'es', 'USD', 16.00, 'America/Mexico_City', 'light',
    'localhost', 'crm_db', 'root', '', 'utf8mb4'
);