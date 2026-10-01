-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 08:46 AM
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
-- Database: `aapki_grocery`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(50) DEFAULT 'Home',
  `name` varchar(120) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `alt_phone` varchar(20) DEFAULT NULL,
  `apartment_no` varchar(120) DEFAULT NULL,
  `apartment_name` varchar(160) DEFAULT NULL,
  `area` varchar(160) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `address_type` enum('Home','Office','Other') NOT NULL DEFAULT 'Home',
  `address_line` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `label`, `name`, `phone`, `apartment_no`, `apartment_name`, `area`, `landmark`, `address_type`, `address_line`, `city`, `state`, `pincode`, `is_default`) VALUES
(1, 1, 'UGF-01', 'Shadbhavna Appartment', '9212153207', NULL, NULL, NULL, NULL, 'Home', 'Shakti enclave, Burari', 'Burari', 'Delhi', '110084', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit`
--

CREATE TABLE `admin_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(180) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `title`, `subtitle`, `image`, `link`, `is_active`, `sort_order`, `created_at`) VALUES
(2, '', '', 'assets/images/banners/home-farm-to-home.webp', '', 1, 1, '2026-09-28 23:30:01'),
(4, '', '', 'assets/images/banners/farm-to-home-hero.webp', '', 1, 2, '2026-09-28 23:46:05');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED NOT NULL,
  `qty` decimal(10,3) NOT NULL DEFAULT 1.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `meta_title` varchar(180) DEFAULT NULL,
  `meta_description` varchar(320) DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `listing_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `image`, `is_active`, `sort_order`, `meta_title`, `meta_description`, `meta_keywords`) VALUES
(1, NULL, 'Fresh Fruits', 'fresh-fruits', 'uploads/categories/acb154cb9e76dbe0370b9ac4.webp', 1, 1, 'Fresh Fruits | Aapki Grocery', 'Buy fresh fruits online from Aapki Grocery.', 'fresh fruits, apple, banana, mango, orange'),
(2, NULL, 'Fresh Vegetables', 'fresh-vegetables', 'uploads/categories/afcee41c4b75c2dfb3bbe62e.webp', 1, 2, 'Fresh Vegetables | Aapki Grocery', 'Buy fresh vegetables online from Aapki Grocery.', 'fresh vegetables, potato, tomato, onion, aalu'),
(3, NULL, 'Grains & Pulses', 'grains-pulses', 'uploads/categories/cb7b0536cb079f72a22151d5.webp', 1, 3, 'Grains & Pulses | Aapki Grocery', 'Everyday grains and pulses for your kitchen.', 'rice, dal, atta, grains, pulses'),
(4, NULL, 'Masala & Spices', 'masala-spices', 'uploads/categories/8ff245cdd392db1b658e0a4c.webp', 1, 4, 'Masala & Spices | Aapki Grocery', 'Fresh everyday masala and spices.', 'masala, spices, haldi, turmeric, mirch'),
(5, NULL, 'Dry Fruits', 'dry-fruits', 'uploads/categories/ca6c71970ccc0202f9f69b63.webp', 1, 5, 'Dry Fruits | Aapki Grocery', 'Premium dry fruits and nuts.', 'dry fruits, almonds, kaju, raisins'),
(6, NULL, 'Aata & Dalia', 'aata-dalia', 'uploads/categories/d189c53c4b638fdd03eb7b0d.webp', 0, 6, 'Sweets | Aapki Grocery', 'Traditional sweets for every occasion.', 'sweets, mithai, kaju katli'),
(7, 1, 'Mangoes', 'mangoes', NULL, 1, 1, 'Fresh Mangoes Online | Aapki Grocery', 'Buy fresh mangoes online — Alphonso, Kesar, Dasheri, Langra and more varieties.', 'mangoes,aam,fresh mango,alphonso,kesar,dasheri,buy mango online'),
(9, NULL, 'Exotic Fruits & Veggies', 'exotic-fruits-veggies', 'uploads/categories/8f77c2271f27ddf6e83c85c9.webp', 1, 3, 'Exotic Fruits & Veggies', 'Exotic Fruits & Veggies', 'Exotic Fruits & Veggies'),
(10, NULL, 'Herbs & Seasonings', 'herbs-seasonings', 'uploads/categories/04ebd00e06bf74c4fd9ce7aa.webp', 1, 4, 'Herbs & Seasonings', 'Herbs & Seasonings', 'Herbs & Seasonings'),
(11, NULL, 'Atta, Flours & Sooji', 'atta-flours-sooji', 'uploads/categories/a1d041497664379489b56644.webp', 1, 5, 'Atta/Flours & Sooji', 'Atta, Flours & Sooji', 'Atta, Flours & Sooji'),
(12, NULL, 'Salt, Sugar & Jaggery', 'salt-sugar-jaggery', 'uploads/categories/5573edef05873b850e59a6e4.webp', 1, 6, 'Salt, Sugar & Jaggery', 'Salt, Sugar & Jaggery', 'Salt, Sugar & Jaggery'),
(13, NULL, 'Edible Oils & Ghee', 'edible-oils-ghee', 'uploads/categories/e3536b2e306fac3d7a2fb654.webp', 1, 6, 'Edible Oils & Ghee', 'Edible Oils & Ghee', 'Edible Oils & Ghee');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('percent','flat') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `min_order` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_no` varchar(40) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `address_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `handling_charge` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `points_used` int(11) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `coupon_code` varchar(50) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(30) DEFAULT 'cod',
  `razorpay_order_id` varchar(80) DEFAULT NULL,
  `razorpay_payment_id` varchar(80) DEFAULT NULL,
  `payment_reference` varchar(120) DEFAULT NULL,
  `payment_status` varchar(30) DEFAULT 'pending',
  `status` varchar(40) DEFAULT 'placed',
  `delivery_slot` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_no`, `user_id`, `address_id`, `subtotal`, `handling_charge`, `delivery_fee`, `discount`, `points_used`, `points_earned`, `coupon_code`, `total`, `payment_method`, `razorpay_order_id`, `razorpay_payment_id`, `payment_reference`, `payment_status`, `status`, `delivery_slot`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'AG26092609443670', 1, 1, 98.00, 0.00, 40.00, 0.00, 0, 0, NULL, 138.00, 'cod', NULL, NULL, NULL, 'pending', 'placed', 'Any available slot', NULL, '2026-09-26 04:14:36', '2026-09-26 04:14:36'),
(2, 'AG26092609445084', 1, 1, 98.00, 0.00, 40.00, 0.00, 0, 0, NULL, 138.00, 'cod', NULL, NULL, NULL, 'pending', 'placed', 'Any available slot', NULL, '2026-09-26 04:14:50', '2026-09-26 04:14:50'),
(3, 'AG26092610480115', 1, 1, 98.00, 15.00, 40.00, 0.00, 0, 0, NULL, 153.00, 'cod', NULL, NULL, NULL, 'pending', 'placed', 'Any available slot', NULL, '2026-09-26 05:18:01', '2026-09-26 05:18:01');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_name` varchar(180) NOT NULL,
  `variant_label` varchar(60) DEFAULT NULL,
  `qty` decimal(10,3) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variant_id`, `product_name`, `variant_label`, `qty`, `unit_price`, `line_total`) VALUES
(1, 1, 4, NULL, 'Fresh Tomatoes', '1 KG', 2.000, 49.00, 98.00),
(2, 2, 4, NULL, 'Fresh Tomatoes', '1 KG', 2.000, 49.00, 98.00),
(3, 3, 4, NULL, 'Fresh Tomatoes', '1 KG', 2.000, 49.00, 98.00);

-- --------------------------------------------------------

--
-- Table structure for table `point_transactions`
--

CREATE TABLE `point_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wt_user` (`user_id`),
  KEY `idx_wt_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(140) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` varchar(320) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_page_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` varchar(80) NOT NULL DEFAULT 'General',
  `question` varchar(500) NOT NULL,
  `answer` mediumtext NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faq_cat` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `popups`
--

CREATE TABLE `popups` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `button_text` varchar(80) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `popups`
--

INSERT INTO `popups` (`id`, `title`, `message`, `button_text`, `button_url`, `is_active`, `start_at`, `end_at`) VALUES
(1, 'Aapki Grocery on your Door', 'Coming soon.... \r\nAapki Grocery on your Door\r\nFresh vegitables and fruits only for you from Farm to your home', 'Aapki Grocery', '', 1, '2026-09-29 04:42:00', '2026-09-29 06:43:00');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `sku` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(120) DEFAULT NULL,
  `search_terms` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `image_webp` varchar(255) DEFAULT NULL,
  `image_alt` varchar(255) DEFAULT NULL,
  `mrp` decimal(10,2) DEFAULT 0.00,
  `price` decimal(10,2) DEFAULT 0.00,
  `stock` decimal(12,3) DEFAULT 0.000,
  `low_stock_threshold` decimal(12,3) NOT NULL DEFAULT 5.000,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `meta_title` varchar(180) DEFAULT NULL,
  `meta_description` varchar(320) DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `sku`, `description`, `brand`, `search_terms`, `image`, `image_webp`, `image_alt`, `mrp`, `price`, `stock`, `low_stock_threshold`, `is_featured`, `is_active`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`) VALUES
(1, 2, 'Ginger- Adrak', 'ginger-adrak-ba71cb', 'FRUIT-001', 'Crisp everyday apples.', NULL, 'apple,apples,seb,सेब,fresh apple', 'uploads/products/385081219440f864526dc281.webp', 'uploads/products/385081219440f864526dc281.webp', '', 180.00, 149.00, 700.000, 5.000, 1, 1, '', 'Ginger- Adrak', 'Ginger- Adrak', '2026-09-25 23:06:01', '2026-09-28 09:41:08'),
(2, 2, 'Garlic (Lahsun)', 'garlic-lahsun-457d6f', 'FRUIT-002', 'Garlic (Lahsun)', NULL, 'Garlic (Lahsun)', 'uploads/products/07526bedd83bfe417c225f47.webp', 'uploads/products/07526bedd83bfe417c225f47.webp', '', 80.00, 65.00, 500.000, 5.000, 1, 1, '', 'Garlic (Lahsun)', 'Garlic (Lahsun)', '2026-09-25 23:06:01', '2026-09-28 09:35:00'),
(3, 2, 'Onion - Local', 'onion-local-f11e2e', 'VEG-001', 'Onion - Local', NULL, 'Onion - Local', 'uploads/products/f3581e3e43842d7066e901ef.webp', 'uploads/products/f3581e3e43842d7066e901ef.webp', '', 50.00, 39.00, 500.000, 5.000, 1, 1, '', 'Onion - Local', 'Onion - Local', '2026-09-25 23:06:01', '2026-09-28 09:28:30'),
(4, 2, 'Onion - Premium', 'onion-premium-1da12e', 'VEG-002', 'Onion - Premium', NULL, 'Onion - Premium', 'uploads/products/a02abda5a5c5fb259cf4098b.webp', 'uploads/products/a02abda5a5c5fb259cf4098b.webp', '', 70.00, 49.00, 294.000, 5.000, 1, 1, 'Onion - Premium', 'Onion - Premium', 'Onion - Premium', '2026-09-25 23:06:01', '2026-09-28 09:27:35'),
(5, 2, 'Tomato - Hybrid', 'tomato-hybrid-0412ce', 'VEG-003', 'Tomato - Hybrid', NULL, 'Tomato - Hybrid', 'uploads/products/bf680ae073dcf506aa48e3c7.webp', 'uploads/products/bf680ae073dcf506aa48e3c7.webp', 'Tomato - Hybrid', 30.00, 45.00, 60.000, 5.000, 1, 1, 'Tomato - Hybrid', 'Tomato - Hybrid', 'Tomato - Hybrid', '2026-09-25 23:06:01', '2026-09-28 09:17:47'),
(6, 2, 'Local Tomato - Premium', 'local-tomato-premium-f9cd12', 'GRAIN-001', 'Local Tomato - Premium', NULL, 'Local Tomato - Premium', 'uploads/products/2156ecfe0cf7358b3bafb6f1.webp', 'uploads/products/2156ecfe0cf7358b3bafb6f1.webp', 'Local Tomato - Premium', 35.00, 40.00, 15.000, 5.000, 1, 1, 'Local Tomato - Premium', 'Local Tomato - Premium', 'Local Tomato - Premium', '2026-09-25 23:06:01', '2026-09-28 08:55:21'),
(7, 2, 'Pahadi Potato', 'pahadi-potato-5c6eb5', 'SPICE-001', 'Pahadi Potato', NULL, 'Pahadi Potato', 'uploads/products/6f1ff08001d358f21e30cdfc.webp', 'uploads/products/6f1ff08001d358f21e30cdfc.webp', 'Pahadi Potato', 35.00, 40.00, 0.000, 5.000, 1, 1, 'Pahadi Potato', 'Pahadi Potato', 'Pahadi Potato', '2026-09-25 23:06:01', '2026-09-28 08:59:29'),
(8, 2, 'Chipsona Potato', 'chipsona-potato-9a52a8', 'DRY-001', 'Source of Energy. Contains Vitamin C. Potato helps in reducing inflammation, promote digestion and are good for skin., Controls Cholesterol\r\nStore in open basket. To avoid scuffing, ensure they are not stockpiled. They can be stored at room temperature.', NULL, 'Chipsona Potato, fresh Chipsona Potato', 'uploads/products/9b2daa1c6720ca9f1c0198d8.webp', 'uploads/products/9b2daa1c6720ca9f1c0198d8.webp', 'Chipsona Potato', 32.00, 40.00, 60.000, 5.000, 1, 1, 'Chipsona Potato', 'Source of Energy. Contains Vitamin C. Potato helps in reducing inflammation, promote digestion and are good for skin., Controls Cholesterol\r\nStore in open basket. To avoid scuffing, ensure they are not stockpiled. They can be stored at room temperature.', 'Chipsona Potato', '2026-09-25 23:06:01', '2026-09-28 08:11:52'),
(9, 2, 'Potato', 'potato-d24041', 'SWT-001', 'A starchy root vegetable, potatoes are one of the most commonly consumed vegetables worldwide. They can be boiled, fried, mashed, or roasted, and are a staple in countless dishes across cultures', NULL, '', 'uploads/products/933e2645cfdc8e50dee55f59.webp', 'uploads/products/933e2645cfdc8e50dee55f59.webp', 'Potato', 23.00, 25.00, 40.000, 5.000, 1, 1, 'Potato', 'A starchy root vegetable, potatoes are one of the most commonly consumed vegetables worldwide. They can be boiled, fried, mashed, or roasted, and are a staple in countless dishes across cultures', 'fresh Potato, Organic Potato,', '2026-09-25 23:06:01', '2026-09-28 08:07:05'),
(10, 1, 'Apple - Pink Lady', 'apple-pink-lady-897237', 'AG-051B5A', '', NULL, 'Apple - Pink Lady', 'uploads/products/52103061e32046f4a8612ec2.webp', 'uploads/products/52103061e32046f4a8612ec2.webp', '', 100.00, 100.00, 130.000, 5.000, 1, 1, 'Apple - Pink Lady', '', 'Apple - Pink Lady', '2026-09-28 09:45:45', '2026-09-28 09:45:45'),
(11, 1, 'Apple - Royal Gala', 'apple-royal-gala-0a5246', 'AG-961FAD', 'Apple - Royal Gala', NULL, '', 'uploads/products/e8d7395463040da338b88b02.webp', 'uploads/products/e8d7395463040da338b88b02.webp', 'Apple - Royal Gala', 120.00, 120.00, 120.000, 5.000, 1, 1, 'Apple - Royal Gala', '', 'Apple - Royal Gala', '2026-09-28 09:52:15', '2026-09-28 09:52:46'),
(12, 1, 'Baby Apple Shimla', 'baby-apple-shimla-1a0600', 'AG-192F30', 'Baby Apple Shimla', NULL, '', 'uploads/products/68c26f0350a75a0c9ec58a7f.webp', 'uploads/products/68c26f0350a75a0c9ec58a7f.webp', 'Baby Apple Shimla', 130.00, 130.00, 130.000, 5.000, 1, 1, 'Baby Apple Shimla', '', 'Baby Apple Shimla', '2026-09-28 09:56:31', '2026-09-28 09:56:31'),
(13, 1, 'Pomegranate - Premium', 'pomegranate-premium-c3e889', 'AG-A76263', 'Pomegranate - Premium', NULL, '', 'uploads/products/5989f9deedf72e41a4ed05d4.webp', 'uploads/products/5989f9deedf72e41a4ed05d4.webp', 'Pomegranate - Premium', 150.00, 150.00, 150.000, 5.000, 1, 1, 'Pomegranate - Premium', 'Pomegranate - Premium', 'Pomegranate - Premium', '2026-09-28 10:00:07', '2026-09-28 10:00:07'),
(26, 1, 'Alphonso Mango (Hapus)', 'alphonso-mango-hapus--173d09', 'AG-MAN-001', 'Premium Alphonso mangoes (Hapus) from Ratnagiri. Known for their rich aroma, sweet taste and bright golden colour. Perfect for eating fresh or making aamras.', NULL, 'alphonso,hapus,ratnagiri mango,aam,आम,hapus aam,mango', 'uploads/products/ALPHONSO_MANGO.jpg', NULL, NULL, 699.00, 599.00, 500.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:06:26'),
(27, 1, 'Kesar Mango (Gir Kesar)', 'kesar-mango-gir-kesar--4f2df4', 'AG-MAN-002', 'Gir Kesar mangoes from Gujarat — saffron-coloured pulp with a rich sweet flavour and low fibre. One of India\'s most prized mango varieties, great for juice and eating fresh.', NULL, 'kesar,gir kesar,gujarat mango,aam,आम,kesar aam,mango', NULL, NULL, NULL, 549.00, 479.00, 400.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:01:12'),
(28, 1, 'Dasheri Mango', 'dasheri-mango-bf71ee', 'AG-MAN-003', 'Classic Dasheri mangoes from Lucknow UP. Long golden-yellow fruit with a delicate sweet flavour and smooth fibre-free pulp. Excellent for eating fresh and making shakes.', NULL, 'dasheri,dussehri,lucknow mango,aam,आम,dasheri aam,UP mango', NULL, NULL, NULL, 299.00, 249.00, 600.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:01:12'),
(29, 1, 'Langra Mango', 'langra-mango-cca1fe', 'AG-MAN-004', 'Langra mangoes — a favourite variety from Varanasi with a distinct tangy-sweet taste and greenish-yellow skin even when ripe. Known for their unique flavour and aroma.', NULL, 'langra,langra mango,varanasi mango,aam,आम,langra aam,green mango', NULL, NULL, NULL, 319.00, 269.00, 500.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:01:12'),
(30, 1, 'Totapuri Mango', 'totapuri-mango-aa5a4c', 'AG-MAN-005', 'Totapuri mangoes — large parrot-beaked mangoes with firm flesh and mildly sweet tangy flavour. Ideal for pickling, chutneys, aamchur powder and eating fresh as well.', NULL, 'totapuri,bangalora mango,gilli,collector mango,aam,आम,raw mango', NULL, NULL, NULL, 199.00, 159.00, 800.000, 5.000, 0, 0, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 04:41:07'),
(31, 1, 'Banganapalli Mango', 'banganapalli-mango-f43dc7', 'AG-MAN-006', 'Banganapalli mangoes from Andhra Pradesh — large golden-yellow mangoes with fibre-free sweet pulp. Also called Safeda or Benishan. Great for eating fresh and making juice.', NULL, 'banganapalli,safeda,benishan,andhra mango,aam,आम,AP mango', NULL, NULL, NULL, 279.00, 229.00, 600.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:01:12'),
(32, 1, 'Chausa Mango', 'chausa-mango-6ed02f', 'AG-MAN-007', 'Chausa mangoes — a premium late-season variety from North India with deep yellow skin and exceptionally sweet honey-like flesh. Very low fibre and rich in flavour.', NULL, 'chausa,chaunsa,honey mango,UP mango,aam,आम,chausa aam', NULL, NULL, NULL, 399.00, 349.00, 300.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:01:12'),
(33, 1, 'Neelam Mango', 'neelam-mango-15976e', 'AG-MAN-008', 'Neelam mangoes — a popular south Indian variety with a distinctive aroma and sweet taste. Small to medium sized with yellow-orange skin. Available across India during peak season.', NULL, 'neelam,neelam mango,south india mango,aam,आम,neelam aam', NULL, NULL, NULL, 249.00, 199.00, 700.000, 5.000, 0, 0, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 04:40:12'),
(34, 1, 'Himsagar Mango', 'himsagar-mango-2647be', 'AG-MAN-009', 'Himsagar mangoes from West Bengal — considered one of the finest varieties. Medium sized with greenish-yellow skin, fibre-free pulp and an intensely sweet aroma. Seasonal delicacy.', NULL, 'himsagar,khirsapati,bengal mango,aam,आम,himsagar aam', NULL, NULL, NULL, 479.00, 419.00, 200.000, 5.000, 0, 0, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 04:40:40'),
(35, 1, 'Mango Pulp - Alphonso (Pack)', 'mango-pulp-alphonso-pack--bf1c4a', 'AG-MAN-010', 'Ready-to-use fresh Alphonso mango pulp. Smooth, sweet and aromatic — ideal for aamras, mango lassi, ice cream, kulfi and desserts. Made from premium Alphonso mangoes.', NULL, 'mango pulp,alphonso pulp,aamras,mango juice,आम का पल्प,hapus pulp', NULL, NULL, NULL, 199.00, 169.00, 400.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:01:12'),
(36, 1, 'Raw Green Mango (Kairi)', 'raw-green-mango-kairi--8fa14c', 'AG-MAN-011', 'Fresh raw green mangoes (Kairi/Kachcha Aam) — perfect for making aam panna, raw mango chutney, pickle (achar), dal tadka and refreshing summer drinks.', NULL, 'kairi,kachcha aam,raw mango,green mango,achar wala aam,aam panna,आम,कैरी', NULL, NULL, NULL, 89.00, 69.00, 1000.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:12', '2026-09-29 01:01:12'),
(37, 1, 'Badami Mango (Karnataka Alphonso)', 'badami-mango-karnataka-alphonso--745589', 'AG-MAN-012', 'Badami mangoes from Karnataka — the local Alphonso variety with bright orange-yellow skin and sweet rich pulp. Excellent eating mango with a distinct Karnataka flavour profile.', NULL, 'badami,karnataka alphonso,aam,आम,badami aam,south india mango', NULL, NULL, NULL, 329.00, 279.00, 450.000, 5.000, 0, 1, NULL, NULL, NULL, '2026-09-29 01:01:13', '2026-09-29 01:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `product_synonyms`
--

CREATE TABLE `product_synonyms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `term` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_synonyms`
--

INSERT INTO `product_synonyms` (`id`, `product_id`, `term`) VALUES
(16, 8, 'Chipsona Potato'),
(17, 8, 'fresh Chipsona Potato'),
(28, 6, 'Local Tomato - Premium'),
(30, 7, 'Pahadi Potato'),
(31, 5, 'Tomato - Hybrid'),
(32, 4, 'Onion - Premium'),
(33, 3, 'Onion - Local'),
(34, 2, 'Garlic (Lahsun)'),
(35, 1, 'apple'),
(36, 1, 'apples'),
(37, 1, 'seb'),
(38, 1, 'सेब'),
(39, 1, 'fresh apple'),
(40, 10, 'Apple - Pink Lady'),
(122, 26, 'alphonso'),
(123, 26, 'hapus'),
(124, 26, 'ratnagiri mango'),
(125, 26, 'aam'),
(126, 26, 'आम'),
(127, 26, 'hapus aam'),
(128, 26, 'mango'),
(129, 27, 'kesar'),
(130, 27, 'gir kesar'),
(131, 27, 'gujarat mango'),
(132, 27, 'aam'),
(133, 27, 'आम'),
(134, 27, 'kesar aam'),
(135, 27, 'mango'),
(136, 28, 'dasheri'),
(137, 28, 'dussehri'),
(138, 28, 'lucknow mango'),
(139, 28, 'aam'),
(140, 28, 'आम'),
(141, 28, 'dasheri aam'),
(142, 28, 'UP mango'),
(143, 29, 'langra'),
(144, 29, 'langra mango'),
(145, 29, 'varanasi mango'),
(146, 29, 'aam'),
(147, 29, 'आम'),
(148, 29, 'langra aam'),
(149, 29, 'green mango'),
(150, 30, 'totapuri'),
(151, 30, 'bangalora mango'),
(152, 30, 'gilli'),
(153, 30, 'collector mango'),
(154, 30, 'aam'),
(155, 30, 'आम'),
(156, 30, 'raw mango'),
(157, 31, 'banganapalli'),
(158, 31, 'safeda'),
(159, 31, 'benishan'),
(160, 31, 'andhra mango'),
(161, 31, 'aam'),
(162, 31, 'आम'),
(163, 31, 'AP mango'),
(164, 32, 'chausa'),
(165, 32, 'chaunsa'),
(166, 32, 'honey mango'),
(167, 32, 'UP mango'),
(168, 32, 'aam'),
(169, 32, 'आम'),
(170, 32, 'chausa aam'),
(171, 33, 'neelam'),
(172, 33, 'neelam mango'),
(173, 33, 'south india mango'),
(174, 33, 'aam'),
(175, 33, 'आम'),
(176, 33, 'neelam aam'),
(177, 34, 'himsagar'),
(178, 34, 'khirsapati'),
(179, 34, 'bengal mango'),
(180, 34, 'aam'),
(181, 34, 'आम'),
(182, 34, 'himsagar aam'),
(183, 35, 'mango pulp'),
(184, 35, 'alphonso pulp'),
(185, 35, 'aamras'),
(186, 35, 'mango juice'),
(187, 35, 'आम का पल्प'),
(188, 35, 'hapus pulp'),
(189, 36, 'kairi'),
(190, 36, 'kachcha aam'),
(191, 36, 'raw mango'),
(192, 36, 'green mango'),
(193, 36, 'achar wala aam'),
(194, 36, 'aam panna'),
(195, 36, 'आम'),
(196, 36, 'कैरी'),
(197, 37, 'badami'),
(198, 37, 'karnataka alphonso'),
(199, 37, 'aam'),
(200, 37, 'आम'),
(201, 37, 'badami aam'),
(202, 37, 'south india mango');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(60) NOT NULL,
  `unit_type` enum('g','kg','pc') NOT NULL DEFAULT 'g',
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `mrp` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `image` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `label`, `unit_type`, `quantity`, `mrp`, `price`, `stock`, `image`, `is_default`) VALUES
(20, 9, '1 KG', 'kg', 23.000, 320.00, 289.00, 40.000, NULL, 1),
(21, 8, '1 KG', 'kg', 250.000, 260.00, 229.00, 60.000, NULL, 1),
(52, 6, '500', 'g', 35.000, 40.00, 20.00, 5.000, NULL, 1),
(53, 6, '1 KG', 'kg', 35.000, 40.00, 70.00, 5.000, NULL, 0),
(54, 6, '2 KG', 'kg', 35.000, 40.00, 100.00, 5.000, NULL, 0),
(58, 7, '1 KG', 'kg', 35.000, 35.00, 35.00, 0.000, NULL, 1),
(59, 7, '2 KG', 'kg', 35.000, 70.00, 67.00, 0.000, NULL, 0),
(60, 7, '5 KG', 'kg', 35.000, 175.00, 62.00, 0.000, NULL, 0),
(61, 5, '500 GM', 'g', 20.000, 40.00, 20.00, 30.000, NULL, 1),
(62, 5, '1 KG', 'kg', 30.000, 40.00, 30.00, 30.000, NULL, 0),
(63, 4, '500 GM', 'g', 500.000, 40.00, 29.00, 150.000, NULL, 0),
(64, 4, '1 KG', 'kg', 1.000, 70.00, 49.00, 144.000, NULL, 1),
(65, 3, '500 GM', 'g', 500.000, 30.00, 25.00, 200.000, NULL, 0),
(66, 3, '1 KG', 'kg', 1.000, 50.00, 39.00, 200.000, NULL, 1),
(67, 3, '2 KG', 'kg', 2.000, 95.00, 75.00, 100.000, NULL, 0),
(68, 2, '3 PCS', 'pc', 3.000, 30.00, 25.00, 200.000, NULL, 0),
(69, 2, '6 PCS', 'pc', 6.000, 48.00, 39.00, 200.000, NULL, 1),
(70, 2, '12 PCS', 'pc', 12.000, 80.00, 65.00, 100.000, NULL, 0),
(71, 1, '100 GM', 'g', 100.000, 20.00, 17.00, 200.000, NULL, 0),
(72, 1, '200 GM', 'g', 200.000, 40.00, 33.00, 200.000, NULL, 0),
(73, 1, '500 GM', 'g', 500.000, 95.00, 79.00, 200.000, NULL, 1),
(74, 1, '1 KG', 'kg', 1.000, 180.00, 149.00, 100.000, NULL, 0),
(75, 10, '1 KG', 'kg', 100.000, 110.00, 120.00, 130.000, NULL, 1),
(77, 11, '1 KG', 'kg', 120.000, 120.00, 120.00, 120.000, NULL, 1),
(79, 12, '1 KG', 'kg', 130.000, 130.00, 130.00, 130.000, NULL, 1),
(80, 13, '1 KG', 'kg', 150.000, 150.00, 150.00, 150.000, NULL, 1),
(125, 26, '250 GM', 'g', 250.000, 699.00, 599.00, 100.000, NULL, 1),
(126, 26, '500 GM', 'g', 500.000, 699.00, 599.00, 100.000, NULL, 0),
(127, 26, '1 KG', 'kg', 1.000, 699.00, 599.00, 100.000, NULL, 0),
(128, 26, '2 KG', 'kg', 2.000, 699.00, 599.00, 100.000, NULL, 0),
(129, 26, '3 KG', 'kg', 3.000, 699.00, 599.00, 100.000, NULL, 0),
(130, 27, '500 GM', 'g', 500.000, 549.00, 479.00, 100.000, NULL, 1),
(131, 27, '1 KG', 'kg', 1.000, 549.00, 479.00, 100.000, NULL, 0),
(132, 27, '2 KG', 'kg', 2.000, 549.00, 479.00, 100.000, NULL, 0),
(133, 27, '3 KG', 'kg', 3.000, 549.00, 479.00, 100.000, NULL, 0),
(134, 28, '500 GM', 'g', 500.000, 299.00, 249.00, 150.000, NULL, 1),
(135, 28, '1 KG', 'kg', 1.000, 299.00, 249.00, 150.000, NULL, 0),
(136, 28, '2 KG', 'kg', 2.000, 299.00, 249.00, 150.000, NULL, 0),
(137, 28, '3 KG', 'kg', 3.000, 299.00, 249.00, 150.000, NULL, 0),
(138, 29, '500 GM', 'g', 500.000, 319.00, 269.00, 166.667, NULL, 1),
(139, 29, '1 KG', 'kg', 1.000, 319.00, 269.00, 166.667, NULL, 0),
(140, 29, '2 KG', 'kg', 2.000, 319.00, 269.00, 166.667, NULL, 0),
(141, 30, '500 GM', 'g', 500.000, 199.00, 159.00, 200.000, NULL, 1),
(142, 30, '1 KG', 'kg', 1.000, 199.00, 159.00, 200.000, NULL, 0),
(143, 30, '2 KG', 'kg', 2.000, 199.00, 159.00, 200.000, NULL, 0),
(144, 30, '3 KG', 'kg', 3.000, 199.00, 159.00, 200.000, NULL, 0),
(145, 31, '500 GM', 'g', 500.000, 279.00, 229.00, 150.000, NULL, 1),
(146, 31, '1 KG', 'kg', 1.000, 279.00, 229.00, 150.000, NULL, 0),
(147, 31, '2 KG', 'kg', 2.000, 279.00, 229.00, 150.000, NULL, 0),
(148, 31, '3 KG', 'kg', 3.000, 279.00, 229.00, 150.000, NULL, 0),
(149, 32, '500 GM', 'g', 500.000, 399.00, 349.00, 100.000, NULL, 1),
(150, 32, '1 KG', 'kg', 1.000, 399.00, 349.00, 100.000, NULL, 0),
(151, 32, '2 KG', 'kg', 2.000, 399.00, 349.00, 100.000, NULL, 0),
(152, 33, '500 GM', 'g', 500.000, 249.00, 199.00, 175.000, NULL, 1),
(153, 33, '1 KG', 'kg', 1.000, 249.00, 199.00, 175.000, NULL, 0),
(154, 33, '2 KG', 'kg', 2.000, 249.00, 199.00, 175.000, NULL, 0),
(155, 33, '3 KG', 'kg', 3.000, 249.00, 199.00, 175.000, NULL, 0),
(156, 34, '500 GM', 'g', 500.000, 479.00, 419.00, 66.667, NULL, 1),
(157, 34, '1 KG', 'kg', 1.000, 479.00, 419.00, 66.667, NULL, 0),
(158, 34, '2 KG', 'kg', 2.000, 479.00, 419.00, 66.667, NULL, 0),
(159, 35, '200 GM', 'g', 200.000, 199.00, 169.00, 133.333, NULL, 1),
(160, 35, '500 GM', 'g', 500.000, 199.00, 169.00, 133.333, NULL, 0),
(161, 35, '1 KG', 'kg', 1.000, 199.00, 169.00, 133.333, NULL, 0),
(162, 36, '250 GM', 'g', 250.000, 89.00, 69.00, 250.000, NULL, 1),
(163, 36, '500 GM', 'g', 500.000, 89.00, 69.00, 250.000, NULL, 0),
(164, 36, '1 KG', 'kg', 1.000, 89.00, 69.00, 250.000, NULL, 0),
(165, 36, '2 KG', 'kg', 2.000, 89.00, 69.00, 250.000, NULL, 0),
(166, 37, '500 GM', 'g', 500.000, 329.00, 279.00, 150.000, NULL, 1),
(167, 37, '1 KG', 'kg', 1.000, 329.00, 279.00, 150.000, NULL, 0),
(168, 37, '2 KG', 'kg', 2.000, 329.00, 279.00, 150.000, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `referrer_id` bigint(20) UNSIGNED NOT NULL,
  `referred_user_id` bigint(20) UNSIGNED NOT NULL,
  `points_awarded` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `can_manage_users` tinyint(1) DEFAULT 0,
  `can_manage_catalog` tinyint(1) DEFAULT 0,
  `can_manage_orders` tinyint(1) DEFAULT 0,
  `can_manage_settings` tinyint(1) DEFAULT 0,
  `can_manage_content` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `can_manage_users`, `can_manage_catalog`, `can_manage_orders`, `can_manage_settings`, `can_manage_content`) VALUES
(1, 'Super Admin', 'super_admin', 1, 1, 1, 1, 1),
(2, 'Store Manager', 'manager', 0, 1, 1, 0, 1),
(3, 'Order Agent', 'order_agent', 0, 0, 1, 0, 0),
(4, 'Catalog Editor', 'catalog_editor', 0, 1, 0, 0, 1),
(5, 'Customer', 'customer', 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(120) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('cart_mode', 'live'),
('default_meta_description', 'Fresh fruits, vegetables, grains, masala, sweets and dry fruits from Aapki Grocery.'),
('default_meta_keywords', 'fresh fruits, fresh vegetables, grocery, fruits, vegetables, grains, masala, dry fruits, sweets'),
('default_meta_title', 'Aapki Grocery | Fresh Fruits, Vegetables & Grocery'),
('handling_charge', '15'),
('point_value_rupees', '1'),
('points_enabled', '1'),
('points_per_100', '1'),
('razorpay_enabled', '0'),
('razorpay_key_id', 'admin@gmail.com'),
('razorpay_key_secret', 'admin'),
('razorpay_webhook_secret', ''),
('redeem_enabled', '1'),
('redeem_min_points', '100'),
('referral_enabled', '1'),
('referral_points', '10'),
('store_popup_enabled', '1');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `change_qty` decimal(12,3) NOT NULL,
  `stock_after` decimal(12,3) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `reference_id` varchar(80) DEFAULT NULL,
  `admin_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `variant_id`, `change_qty`, `stock_after`, `reason`, `reference_id`, `admin_user_id`, `created_at`) VALUES
(1, 4, NULL, -2.000, 148.000, 'order', 'AG26092609443670', NULL, '2026-09-26 04:14:36'),
(2, 4, NULL, -2.000, 146.000, 'order', 'AG26092609445084', NULL, '2026-09-26 04:14:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `first_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `provider` enum('local','google','facebook') NOT NULL DEFAULT 'local',
  `provider_id` varchar(190) DEFAULT NULL,
  `role_slug` varchar(80) NOT NULL DEFAULT 'customer',
  `points` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `wallet_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remember_token_hash` varchar(255) DEFAULT NULL,
  `referral_code` varchar(32) DEFAULT NULL,
  `referred_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `provider`, `provider_id`, `role_slug`, `points`, `remember_token_hash`, `referral_code`, `referred_by`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'Administrator', '', 'admin@gmail.com', NULL, '$2y$10$WC6OQj6tfXIX8WVPtfSzQu7UTzoN1CHma1BNjxoVB93dIAkJld8Cm', 'local', NULL, 'super_admin', 0, NULL, 'AAPKIADMIN', NULL, 1, '2026-09-25 23:06:01', '2026-09-26 05:12:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_audit`
--
ALTER TABLE `admin_audit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_item` (`user_id`,`variant_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_parent_id` (`parent_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `idx_order_status` (`status`),
  ADD KEY `idx_order_date` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `popups`
--
ALTER TABLE `popups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_cat_active` (`category_id`,`is_active`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_featured` (`is_featured`,`is_active`);

--
-- Indexes for table `product_synonyms`
--
ALTER TABLE `product_synonyms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_syn_term` (`term`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_variant_product` (`product_id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ref` (`referrer_id`,`referred_user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_product` (`product_id`),
  ADD KEY `idx_stock_created` (`created_at`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `admin_user_id` (`admin_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_provider` (`provider`,`provider_id`),
  ADD KEY `role_slug` (`role_slug`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_audit`
--
ALTER TABLE `admin_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `point_transactions`
--
ALTER TABLE `point_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `popups`
--
ALTER TABLE `popups`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `product_synonyms`
--
ALTER TABLE `product_synonyms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carts_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD CONSTRAINT `point_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `point_transactions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_synonyms`
--
ALTER TABLE `product_synonyms`
  ADD CONSTRAINT `product_synonyms_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_slug`) REFERENCES `roles` (`slug`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
