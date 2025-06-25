-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 25, 2025 at 09:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `crm_managment`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activa, 0=Inactiva',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías para organizar productos';

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activo, 0=Inactivo, 2=Eliminado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clientes del CRM con información de contacto';

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `quote_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','failed') DEFAULT 'sent',
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) NOT NULL,
  `stock` int(11) DEFAULT NULL COMMENT 'Stock disponible (NULL = no se maneja inventario)',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activo, 0=Inactivo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Productos y servicios del catálogo';

-- --------------------------------------------------------

--
-- Table structure for table `quotes`
--

CREATE TABLE `quotes` (
  `id` int(11) NOT NULL,
  `quote_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `quote_date` date NOT NULL,
  `valid_until` date NOT NULL,
  `notes` text DEFAULT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Borrador, 2=Enviada, 3=Aprobada, 4=Rechazada, 5=Vencida, 6=Cancelada',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cotizaciones enviadas a clientes';

-- --------------------------------------------------------

--
-- Table structure for table `quote_details`
--

CREATE TABLE `quote_details` (
  `id` int(11) NOT NULL,
  `quote_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `line_subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total_with_tax` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalles/items de cada cotización';

--
-- Triggers `quote_details`
--
DELIMITER $$
CREATE TRIGGER `update_quote_totals_after_detail_change` AFTER INSERT ON `quote_details` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL DEFAULT 'Mi Empresa CRM',
  `company_slogan` text DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `company_website` varchar(255) DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `language` varchar(5) NOT NULL DEFAULT 'es',
  `timezone` varchar(100) NOT NULL DEFAULT 'America/Mexico_City',
  `currency_code` varchar(5) NOT NULL DEFAULT 'USD',
  `currency_symbol` varchar(10) NOT NULL DEFAULT '$',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 16.00,
  `tax_name` varchar(50) NOT NULL DEFAULT 'IVA',
  `theme` varchar(20) NOT NULL DEFAULT 'light',
  `date_format` varchar(20) NOT NULL DEFAULT 'd/m/Y',
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_security` varchar(10) DEFAULT 'tls',
  `smtp_from_email` varchar(255) DEFAULT NULL,
  `smtp_from_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `company_name`, `company_slogan`, `company_address`, `company_phone`, `company_email`, `company_website`, `company_logo`, `language`, `timezone`, `currency_code`, `currency_symbol`, `tax_rate`, `tax_name`, `theme`, `date_format`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_security`, `smtp_from_email`, `smtp_from_name`, `created_at`, `updated_at`) VALUES
(1, 'Mi Empresa CRM', 'Tu socio confiable en crecimiento empresarial', NULL, NULL, NULL, NULL, NULL, 'es', 'America/Mexico_City', 'USD', '$', 16.00, 'IVA', 'light', 'd/m/Y', NULL, 587, NULL, NULL, 'tls', NULL, NULL, '2025-06-25 17:31:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` tinyint(4) NOT NULL DEFAULT 2 COMMENT 'Rol: 1=Admin, 2=Seller',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Estado: 1=Activo, 0=Inactivo',
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0 COMMENT 'Intentos fallidos de login consecutivos',
  `locked_until` timestamp NULL DEFAULT NULL COMMENT 'Bloqueado hasta esta fecha/hora',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios del sistema CRM con roles y autenticación';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `last_login`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 'root', 'root@sysadmin.com', '$2y$10$XaQhK6Aoj.JvThtybuHkYei5DFQT/1JqfFUSdz5LIRQdM5Bttutpm', 'root sysadmin', 1, 1, '2025-06-25 19:05:10', 0, NULL, '2025-06-20 00:16:43', '2025-06-25 19:05:10');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_client_quote_summary`
-- (See below for the actual view)
--
CREATE TABLE `view_client_quote_summary` (
`client_id` int(11)
,`client_name` varchar(100)
,`client_email` varchar(255)
,`total_quotes` bigint(21)
,`draft_quotes` decimal(22,0)
,`sent_quotes` decimal(22,0)
,`approved_quotes` decimal(22,0)
,`rejected_quotes` decimal(22,0)
,`total_quoted_value` decimal(32,2)
,`approved_value` decimal(32,2)
,`last_quote_date` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_products_with_category`
-- (See below for the actual view)
--
CREATE TABLE `view_products_with_category` (
`id` int(11)
,`product_name` varchar(100)
,`description` text
,`category_name` varchar(50)
,`base_price` decimal(10,2)
,`tax_rate` decimal(5,2)
,`final_price` decimal(15,2)
,`unit` varchar(20)
,`stock` int(11)
,`status` tinyint(4)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_quotes_with_client`
-- (See below for the actual view)
--
CREATE TABLE `view_quotes_with_client` (
`id` int(11)
,`quote_number` varchar(50)
,`quote_date` date
,`valid_until` date
,`client_name` varchar(100)
,`client_email` varchar(255)
,`client_phone` varchar(20)
,`subtotal` decimal(10,2)
,`discount_percent` decimal(5,2)
,`tax_amount` decimal(10,2)
,`total_amount` decimal(10,2)
,`status` tinyint(4)
,`status_name` varchar(11)
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `view_client_quote_summary`
--
DROP TABLE IF EXISTS `view_client_quote_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_client_quote_summary`  AS SELECT `c`.`id` AS `client_id`, `c`.`name` AS `client_name`, `c`.`email` AS `client_email`, count(`q`.`id`) AS `total_quotes`, sum(case when `q`.`status` = 1 then 1 else 0 end) AS `draft_quotes`, sum(case when `q`.`status` = 2 then 1 else 0 end) AS `sent_quotes`, sum(case when `q`.`status` = 3 then 1 else 0 end) AS `approved_quotes`, sum(case when `q`.`status` = 4 then 1 else 0 end) AS `rejected_quotes`, coalesce(sum(`q`.`total_amount`),0) AS `total_quoted_value`, coalesce(sum(case when `q`.`status` = 3 then `q`.`total_amount` else 0 end),0) AS `approved_value`, max(`q`.`created_at`) AS `last_quote_date` FROM (`clients` `c` left join `quotes` `q` on(`c`.`id` = `q`.`client_id`)) WHERE `c`.`status` = 1 GROUP BY `c`.`id`, `c`.`name`, `c`.`email` ;

-- --------------------------------------------------------

--
-- Structure for view `view_products_with_category`
--
DROP TABLE IF EXISTS `view_products_with_category`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_products_with_category`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `product_name`, `p`.`description` AS `description`, `c`.`name` AS `category_name`, `p`.`base_price` AS `base_price`, `p`.`tax_rate` AS `tax_rate`, round(`p`.`base_price` + `p`.`base_price` * `p`.`tax_rate` / 100,2) AS `final_price`, `p`.`unit` AS `unit`, `p`.`stock` AS `stock`, `p`.`status` AS `status`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at` FROM (`products` `p` left join `categories` `c` on(`p`.`category_id` = `c`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `view_quotes_with_client`
--
DROP TABLE IF EXISTS `view_quotes_with_client`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_quotes_with_client`  AS SELECT `q`.`id` AS `id`, `q`.`quote_number` AS `quote_number`, `q`.`quote_date` AS `quote_date`, `q`.`valid_until` AS `valid_until`, `c`.`name` AS `client_name`, `c`.`email` AS `client_email`, `c`.`phone` AS `client_phone`, `q`.`subtotal` AS `subtotal`, `q`.`discount_percent` AS `discount_percent`, `q`.`tax_amount` AS `tax_amount`, `q`.`total_amount` AS `total_amount`, `q`.`status` AS `status`, CASE `q`.`status` WHEN 1 THEN 'Borrador' WHEN 2 THEN 'Enviada' WHEN 3 THEN 'Aprobada' WHEN 4 THEN 'Rechazada' WHEN 5 THEN 'Vencida' WHEN 6 THEN 'Cancelada' ELSE 'Desconocido' END AS `status_name`, `q`.`notes` AS `notes`, `q`.`created_at` AS `created_at`, `q`.`updated_at` AS `updated_at` FROM (`quotes` `q` left join `clients` `c` on(`q`.`client_id` = `c`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_clients_name_email` (`name`,`email`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quote_id` (`quote_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_base_price` (`base_price`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_stock` (`stock`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_products_category_status` (`category_id`,`status`);

--
-- Indexes for table `quotes`
--
ALTER TABLE `quotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quote_number` (`quote_number`),
  ADD KEY `idx_quote_number` (`quote_number`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_quote_date` (`quote_date`),
  ADD KEY `idx_valid_until` (`valid_until`),
  ADD KEY `idx_total_amount` (`total_amount`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_quotes_client_status` (`client_id`,`status`),
  ADD KEY `idx_quotes_date_range` (`quote_date`,`valid_until`);

--
-- Indexes for table `quote_details`
--
ALTER TABLE `quote_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quote_id` (`quote_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_line_total_with_tax` (`line_total_with_tax`),
  ADD KEY `idx_quote_details_quote_product` (`quote_id`,`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_language` (`language`),
  ADD KEY `idx_timezone` (`timezone`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_last_login` (`last_login`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotes`
--
ALTER TABLE `quotes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quote_details`
--
ALTER TABLE `quote_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `quotes`
--
ALTER TABLE `quotes`
  ADD CONSTRAINT `quotes_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `quote_details`
--
ALTER TABLE `quote_details`
  ADD CONSTRAINT `quote_details_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `quote_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
