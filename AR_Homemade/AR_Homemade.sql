-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2026 at 10:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `AR_Homemade`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `movement_type` enum('Stock In','Stock Out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `movement_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `product_id`, `movement_type`, `quantity`, `remarks`, `movement_date`) VALUES
(1, 16, 'Stock Out', 1, 'Order #3', '2026-10-02 08:46:38');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Preparing','Ready','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `payment_method` varchar(50) NOT NULL DEFAULT 'Cash',
  `payment_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `status`, `payment_method`, `payment_status`, `amount_paid`, `change_amount`, `order_date`) VALUES
(1, 2, 105.00, 'Pending', 'Cash', 'Pending', 0.00, 0.00, '2026-10-02 08:26:42'),
(2, 2, 85.00, 'Pending', 'Cash', 'Pending', 0.00, 0.00, '2026-10-02 08:32:28'),
(3, 2, 85.00, 'Completed', 'Cash', 'Paid', 85.00, 0.00, '2026-10-02 08:46:15'),
(4, 2, 85.00, 'Pending', 'Cash', 'Pending', 0.00, 0.00, '2026-10-02 08:50:34'),
(5, 2, 110.00, 'Pending', 'Cash', 'Pending', 0.00, 0.00, '2026-10-02 08:51:08'),
(6, 2, 105.00, 'Pending', 'Cash', 'Pending', 0.00, 0.00, '2026-10-02 08:53:44'),
(7, 2, 65.00, 'Pending', 'Cash', 'Pending', 0.00, 0.00, '2026-10-02 08:55:59'),
(8, 2, 105.00, 'Pending', 'Cash', 'Pending', 0.00, 0.00, '2026-10-02 08:56:49');

-- Add customer name separately from the staff account that records the order.
ALTER TABLE `orders` ADD COLUMN `customer_name` varchar(150) DEFAULT NULL AFTER `user_id`;
UPDATE `orders` SET `customer_name` = 'Walk-in Customer' WHERE `customer_name` IS NULL OR `customer_name` = '';

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `subtotal`) VALUES
(1, 1, 1, 1, 105.00, 105.00),
(2, 2, 7, 1, 85.00, 85.00),
(3, 3, 16, 1, 85.00, 85.00),
(4, 4, 15, 1, 85.00, 85.00),
(5, 5, 3, 1, 110.00, 110.00),
(6, 6, 2, 1, 105.00, 105.00),
(7, 7, 22, 1, 65.00, 65.00),
(8, 8, 1, 1, 105.00, 105.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock_level` int(11) NOT NULL DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `description`, `image`, `price`, `stock`, `low_stock_level`, `created_at`, `updated_at`) VALUES
(1, '(COMBO A) 2 SHAWARMA W/ FRIES', '2 pcs Shawarma, fries and drink.', '', 105.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(2, '(COMBO B) BURGER W/ SHAWARMA W/ FRIES', '1 pc Burger, 1 pc Shawarma, fries and drink.', 'images/burger.jpg', 105.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(3, '(COMBO C) CHICKEN W/ SHAWARMA W/ FRIES', '1 pc Chicken, 1 pc Shawarma, fries and drink.', '', 110.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(4, 'LIEMPO W/ EGG', 'Java rice meal with liempo and egg.', 'images/liempo.webp', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(5, 'PORKCHOP W/ EGG', 'Java rice meal with porkchop and egg.', 'images/porkchop.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(6, 'CHICKEN W/ EGG', 'Java rice meal with chicken and egg.', 'images/chicken.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(7, 'TOKATSU \"CHIKEN FILLEY W/EGG', 'Java rice meal with tokatsu chicken fillet and egg.', 'images/tokatsu.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(8, 'HUNGARIAN W/ EGG', 'Java rice meal with Hungarian sausage and egg.', 'images/hungarian.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(9, 'LETCHON KAWALI W/ EGG', 'Java rice meal with lechon kawali and egg.', 'images/lechon.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(10, 'SHAWARMA RICE', 'Java rice meal with shawarma.', 'images/shawarma.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(11, 'TAPA W/ EGG', 'Java rice meal with tapa and egg.', 'images/tapsi.webp', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(12, 'TOCINO W/ EGG', 'Java rice meal with tocino and egg.', 'images/tocino.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(13, 'BEEF SISIG', 'Java rice meal with beef sisig.', 'images/beefsisig.png', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(14, 'PORK SISIG W/ RICE', 'Java rice meal with pork sisig.', 'images/porksisig.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(15, 'BEEF OMELETTE W/ RICE', 'Java rice meal with beef omelette.', 'images/beefomelette.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(16, 'BONELESS W/ EGG', 'Java rice meal with boneless meat and egg.', 'images/boneless.png', 85.00, 99, 5, '2026-10-02 08:26:41', '2026-10-02 08:46:38'),
(17, 'HOT DOG W/ EGG', 'Budget meal with hot dog and egg.', '', 65.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(18, 'FRIED SIOMAI W/ EGG', 'Budget meal with fried siomai and egg.', '', 65.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(19, 'SKINLESS W/ EGG', 'Budget meal with skinless sausage and egg.', '', 65.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(20, 'LONGGANISA W/ EGG', 'Budget meal with longganisa and egg.', '', 65.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(21, 'LUMPIA SHANGHAI W/ EGG', 'Budget meal with lumpia Shanghai and egg.', '', 65.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(22, 'BURGER DOUBLE PATTY', 'Budget meal with double patty burger.', 'images/burger.jpg', 65.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(23, 'MALING W/ EGG', 'Budget meal with maling and egg.', '', 65.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(24, 'TARO SHAKE', 'Creamy taro shake.', '', 30.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(25, 'UBE SHAKE', 'Creamy ube shake.', '', 30.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(26, 'CHOCOLATE SHAKE', 'Creamy chocolate shake.', '', 30.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(27, 'STRAWBERRY SHAKE', 'Creamy strawberry shake.', '', 30.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(28, 'BEEF SISIG ALACART', 'Beef sisig pulutan.', 'images/beefsisig.png', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(29, 'PORK SISIG ALACART', 'Pork sisig pulutan.', 'images/porksisig.jpg', 85.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(30, 'TOKWA SISIG', 'Tokwa sisig pulutan.', '', 75.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(31, 'HOTDOG SISIG', 'Hotdog sisig pulutan.', '', 70.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(32, 'SIOMAI SISIG', 'Siomai sisig pulutan.', '', 70.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(33, 'SHAWARMA ALACART', 'Shawarma served as pulutan.', 'images/shawarma.jpg', 90.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(34, 'LETCHON & TOKWA SISIG', 'Lechon and tokwa sisig pulutan.', '', 105.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(35, 'FRIES', 'Crispy fries.', '', 50.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41'),
(36, 'NACHOZ', 'Nachoz with your choice of flavor.', '', 60.00, 100, 5, '2026-10-02 08:26:41', '2026-10-02 08:26:41');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `order_id`, `amount`, `sale_date`) VALUES
(1, 3, 85.00, '2026-10-02 08:46:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `role` enum('Administrator','Staff') NOT NULL DEFAULT 'Staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$12$g6aC6BQa57ee9s9ZFDR7cOax8UppYpg8cAWR/wNhROkXq2.xbyw3m', 'AR Administrator', 'Administrator', '2026-10-02 08:26:41'),
(2, 'staff', '$2y$12$xL2UuHN2ppWwFYLrBjUhSexGRI9Gvy6b5WtOdeOrWE3/rClUYG0qe', 'AR Staff', 'Staff', '2026-10-02 08:26:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_product` (`product_id`),
  ADD KEY `idx_inventory_date` (`movement_date`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_date` (`order_date`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_products_name` (`product_name`),
  ADD KEY `idx_products_stock` (`stock`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sales_order` (`order_id`),
  ADD KEY `idx_sales_date` (`sale_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Ingredient inventory and recipe mapping (recipe rows are intentionally empty).
CREATE TABLE IF NOT EXISTS ingredients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingredient_name VARCHAR(150) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  quantity_in_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  low_stock_level DECIMAL(12,3) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ingredient_name (ingredient_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_recipes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  ingredient_id INT UNSIGNED NOT NULL,
  quantity_required DECIMAL(12,3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_ingredient (product_id, ingredient_id),
  KEY idx_recipe_ingredient (ingredient_id),
  CONSTRAINT fk_recipe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipe_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT,
  CONSTRAINT chk_recipe_quantity CHECK (quantity_required > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredient_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingredient_id INT UNSIGNED NOT NULL,
  movement_type ENUM('Stock In','Stock Out') NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  remarks VARCHAR(255) DEFAULT NULL,
  movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ingredient_movement (ingredient_id, movement_date),
  CONSTRAINT fk_ingredient_movement FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- Ingredient inventory and product recipe tables required by the PHP pages.
CREATE TABLE IF NOT EXISTS ingredients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingredient_name VARCHAR(150) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  quantity_in_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  low_stock_level DECIMAL(12,3) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ingredient_name (ingredient_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_recipes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  ingredient_id INT UNSIGNED NOT NULL,
  quantity_required DECIMAL(12,3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_ingredient (product_id, ingredient_id),
  KEY idx_recipe_ingredient (ingredient_id),
  CONSTRAINT arhm_recipe_product_fk
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT arhm_recipe_ingredient_fk
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT,
  CHECK (quantity_required > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredient_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingredient_id INT UNSIGNED NOT NULL,
  movement_type ENUM('Stock In','Stock Out') NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  remarks VARCHAR(255) DEFAULT NULL,
  movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ingredient_movement (ingredient_id, movement_date),
  CONSTRAINT arhm_ingredient_movement_fk
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
