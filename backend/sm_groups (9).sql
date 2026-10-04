-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2026 at 03:44 PM
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
-- Database: `sm_groups`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','staff') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'SM Group Admin', 'sriramneelamegam04@gmail.com', '9360552619', '$2y$10$ZhXuZqT54f0zk1RC95u5q.EUg/DuWrgKFhDXSm/wMlW6OWta/A1O6', 'admin', 'active', NULL, '2026-09-19 19:11:24', '2026-09-23 13:18:00'),
(2, 'Staff Kumar', 'dheeransriram04@gmail.com', '9003408669', '$2y$10$wnKSi3lv8p/iIy1qLIKkJ.ud40cU/jq0/i0HVu4ChCwruAaVm9SSi', 'staff', 'active', NULL, '2026-09-19 20:59:28', '2026-09-23 13:18:20');

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `icon`, `status`, `created_at`) VALUES
(1, 'Air Conditioner', 'ac', 'active', '2026-09-19 14:36:21'),
(2, 'Free WiFi', 'wifi', 'active', '2026-09-19 14:36:21'),
(3, 'TV', 'tv', 'active', '2026-09-19 14:36:21'),
(4, 'Hot Water', 'hot-water', 'active', '2026-09-19 14:36:21'),
(5, 'Attached Bathroom', 'bathroom', 'active', '2026-09-19 14:36:21'),
(6, 'Parking', 'parking', 'active', '2026-09-19 14:36:21'),
(7, 'Room Service', 'room-service', 'active', '2026-09-19 14:36:21'),
(8, '24/7 Reception', 'reception', 'active', '2026-09-19 14:36:21'),
(9, 'Power Backup', 'power-backup', 'active', '2026-09-19 14:36:21');

-- --------------------------------------------------------

--
-- Table structure for table `booking_activity_logs`
--

CREATE TABLE `booking_activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `performed_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_activity_logs`
--

INSERT INTO `booking_activity_logs` (`id`, `booking_id`, `action`, `description`, `performed_by_admin_id`, `created_at`) VALUES
(2, 2, 'booking_created', 'Room booking created by customer.', NULL, '2026-09-21 18:33:53'),
(3, 2, 'booking_cancelled', 'Booking cancelled by customer. Reason: Personal reason', NULL, '2026-09-21 18:49:33'),
(4, 3, 'booking_created', 'Room booking created by customer.', NULL, '2026-09-21 18:54:22'),
(5, 3, 'razorpay_order_created', 'Razorpay payment order created for booking.', NULL, '2026-09-21 19:08:49');

-- --------------------------------------------------------

--
-- Table structure for table `booking_food_items`
--

CREATE TABLE `booking_food_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `food_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_food_items`
--

INSERT INTO `booking_food_items` (`id`, `booking_id`, `food_item_id`, `quantity`, `notes`, `created_at`) VALUES
(2, 2, 2, 2, 'Less spicy', '2026-09-21 18:33:53'),
(3, 3, 2, 2, 'Less spicy', '2026-09-21 18:54:22');

-- --------------------------------------------------------

--
-- Table structure for table `booking_food_packages`
--

CREATE TABLE `booking_food_packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `package_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_food_packages`
--

INSERT INTO `booking_food_packages` (`id`, `booking_id`, `package_id`, `quantity`, `notes`, `created_at`) VALUES
(2, 2, 1, 2, 'Breakfast', '2026-09-21 18:33:53'),
(3, 3, 1, 2, 'Breakfast', '2026-09-21 18:54:22');

-- --------------------------------------------------------

--
-- Table structure for table `booking_rooms`
--

CREATE TABLE `booking_rooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `room_price_per_night` decimal(12,2) NOT NULL,
  `number_of_nights` int(10) UNSIGNED NOT NULL,
  `room_total` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_rooms`
--

INSERT INTO `booking_rooms` (`id`, `booking_id`, `room_id`, `room_price_per_night`, `number_of_nights`, `room_total`, `created_at`) VALUES
(2, 2, 1, 2000.00, 2, 4000.00, '2026-09-21 18:33:53'),
(3, 3, 1, 2000.00, 2, 4000.00, '2026-09-21 18:54:22');

-- --------------------------------------------------------

--
-- Table structure for table `catering_lead_details`
--

CREATE TABLE `catering_lead_details` (
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `catering_package_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` varchar(100) DEFAULT NULL,
  `guest_count` int(10) UNSIGNED DEFAULT NULL,
  `venue_address` text DEFAULT NULL,
  `estimated_amount` decimal(12,2) DEFAULT NULL,
  `other_requirements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `catering_lead_details`
--

INSERT INTO `catering_lead_details` (`lead_id`, `catering_package_id`, `event_type`, `event_date`, `event_time`, `guest_count`, `venue_address`, `estimated_amount`, `other_requirements`) VALUES
(2, 1, 'Wedding', '2026-10-15', '12:00 PM', 150, 'Karaikudi', 67500.00, 'Pure vegetarian catering required');

-- --------------------------------------------------------

--
-- Table structure for table `catering_lead_items`
--

CREATE TABLE `catering_lead_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `food_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `catering_lead_items`
--

INSERT INTO `catering_lead_items` (`id`, `lead_id`, `food_item_id`, `quantity`, `notes`) VALUES
(1, 2, 1, 2, 'Less spicy'),
(2, 2, 2, 1, 'Required for all guests');

-- --------------------------------------------------------

--
-- Table structure for table `catering_packages`
--

CREATE TABLE `catering_packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate_unit` enum('per_person','per_plate','per_event','per_package') NOT NULL DEFAULT 'per_person',
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `price_includes_gst` tinyint(1) NOT NULL DEFAULT 1,
  `minimum_quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `image_path` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `catering_packages`
--

INSERT INTO `catering_packages` (`id`, `name`, `description`, `rate`, `rate_unit`, `gst_rate`, `price_includes_gst`, `minimum_quantity`, `image_path`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Wedding Catering Premium', 'Premium vegetarian catering package', 450.00, 'per_person', 18.00, 1, 50, 'uploads/catering_packages/1/image/afac7b96451a0c583c9d6ae97af995e9.jpg', 'active', '2026-09-22 19:42:56', '2026-09-22 19:50:01');

-- --------------------------------------------------------

--
-- Table structure for table `catering_package_items`
--

CREATE TABLE `catering_package_items` (
  `package_id` bigint(20) UNSIGNED NOT NULL,
  `food_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `catering_package_items`
--

INSERT INTO `catering_package_items` (`package_id`, `food_item_id`, `quantity`) VALUES
(1, 1, 1),
(1, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `password_hash`, `status`, `email_verified_at`, `phone_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Sriram', 'sriramneelamegam04@gmail.com', '9876543211', '$2y$10$ColltifFVGlaJnB3XafxYuuuIRmRcTgKp6GfybtvR/4DLiMGIjl5.', 'active', NULL, NULL, '2026-09-21 19:28:49', '2026-09-19 20:54:00', '2026-09-21 19:28:49');

-- --------------------------------------------------------

--
-- Table structure for table `customer_sessions`
--

CREATE TABLE `customer_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `refresh_token_hash` varchar(255) NOT NULL,
  `device_name` varchar(150) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dining_lead_details`
--

CREATE TABLE `dining_lead_details` (
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `dining_package_id` bigint(20) UNSIGNED DEFAULT NULL,
  `meal_type` enum('lunch','dinner','breakfast','other') DEFAULT NULL,
  `dining_date` date DEFAULT NULL,
  `dining_time` varchar(100) DEFAULT NULL,
  `guest_count` int(10) UNSIGNED DEFAULT NULL,
  `other_requirements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dining_lead_details`
--

INSERT INTO `dining_lead_details` (`lead_id`, `dining_package_id`, `meal_type`, `dining_date`, `dining_time`, `guest_count`, `other_requirements`) VALUES
(1, 1, 'lunch', '2026-10-05', '1:00 PM', 50, 'Need less spicy food');

-- --------------------------------------------------------

--
-- Table structure for table `dining_lead_items`
--

CREATE TABLE `dining_lead_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `food_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dining_lead_items`
--

INSERT INTO `dining_lead_items` (`id`, `lead_id`, `food_item_id`, `quantity`, `notes`) VALUES
(1, 1, 1, 5, 'Less spicy'),
(2, 1, 2, 3, 'Extra quantity');

-- --------------------------------------------------------

--
-- Table structure for table `dining_packages`
--

CREATE TABLE `dining_packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `meal_type` enum('lunch','dinner','breakfast','combo','other') NOT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dining_packages`
--

INSERT INTO `dining_packages` (`id`, `name`, `description`, `meal_type`, `image_path`, `status`, `created_at`, `updated_at`) VALUES
(1, 'South Indian Lunch', 'Traditional South Indian lunch package', 'lunch', 'uploads/dining_packages/1/image/7ba281468042b10a6312c94b5b821d8b.jpg', 'active', '2026-09-22 13:17:15', '2026-09-22 13:17:15');

-- --------------------------------------------------------

--
-- Table structure for table `dining_package_items`
--

CREATE TABLE `dining_package_items` (
  `package_id` bigint(20) UNSIGNED NOT NULL,
  `food_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dining_package_items`
--

INSERT INTO `dining_package_items` (`package_id`, `food_item_id`, `quantity`) VALUES
(1, 1, 5),
(1, 2, 4);

-- --------------------------------------------------------

--
-- Table structure for table `food_categories`
--

CREATE TABLE `food_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `food_categories`
--

INSERT INTO `food_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES
(1, 'Breakfast', 'Breakfast items', 'active', '2026-09-19 14:36:21'),
(2, 'Lunch', 'Lunch items', 'active', '2026-09-19 14:36:21'),
(3, 'Dinner', 'Dinner items', 'active', '2026-09-19 14:36:21'),
(4, 'Main Course', 'Main course dishes', 'active', '2026-09-19 14:36:21'),
(5, 'Starters', 'Starter dishes', 'active', '2026-09-19 14:36:21'),
(6, 'Desserts', 'Desserts', 'active', '2026-09-19 14:36:21'),
(7, 'Beverages', 'Drinks and beverages', 'active', '2026-09-19 14:36:21'),
(8, 'Rice', 'Rice based dishes', 'active', '2026-09-19 14:36:21'),
(9, 'Bread', 'Bread and roti items', 'active', '2026-09-19 14:36:21');

-- --------------------------------------------------------

--
-- Table structure for table `food_items`
--

CREATE TABLE `food_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `food_category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `food_type` enum('veg','non_veg','egg','vegan','other') NOT NULL DEFAULT 'veg',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `food_items`
--

INSERT INTO `food_items` (`id`, `food_category_id`, `name`, `description`, `image_path`, `food_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'Chicken Biriyani Special', 'Delicious chicken biriyani with raita', 'uploads/food/1/image/9788414c2b05d47d11e9ffac13a7ce68.jpg', 'non_veg', 'active', '2026-09-21 13:34:20', '2026-09-21 13:41:06'),
(2, 2, 'Mutton Biriyani', 'Delicious chicken biriyani with raita', 'uploads/food/2/image/2c0497e27bc750f1d0679b48acdae9e3.jpg', 'non_veg', 'active', '2026-09-21 13:34:55', '2026-09-21 13:34:55');

-- --------------------------------------------------------

--
-- Table structure for table `halls`
--

CREATE TABLE `halls` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hall_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `area` varchar(100) DEFAULT NULL,
  `price_description` varchar(255) DEFAULT NULL,
  `status` enum('available','inactive','maintenance') NOT NULL DEFAULT 'available',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `halls`
--

INSERT INTO `halls` (`id`, `hall_type_id`, `name`, `slug`, `description`, `capacity`, `area`, `price_description`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Grand Marriage Hall', 'grand-marriage-hall', 'Spacious marriage hall suitable for weddings and large functions.', 500, '5000 sq.ft', 'Contact us for package and pricing details.', 'available', '2026-09-22 11:10:06', '2026-09-22 11:10:06'),
(2, 2, 'SM Grand Party Hall', 'sm-grand-party-hall', 'Large party hall suitable for family functions.', 750, '6500 sq.ft', 'Contact management for package details.', 'available', '2026-09-22 11:14:21', '2026-09-22 11:14:35');

-- --------------------------------------------------------

--
-- Table structure for table `hall_facilities`
--

CREATE TABLE `hall_facilities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hall_facilities`
--

INSERT INTO `hall_facilities` (`id`, `name`, `status`, `created_at`) VALUES
(1, 'Air Conditioning', 'active', '2026-09-19 14:36:21'),
(2, 'Parking', 'active', '2026-09-19 14:36:21'),
(3, 'Stage', 'active', '2026-09-19 14:36:21'),
(4, 'Dining Area', 'active', '2026-09-19 14:36:21'),
(5, 'Power Backup', 'active', '2026-09-19 14:36:21'),
(6, 'Sound System', 'active', '2026-09-19 14:36:21'),
(7, 'Projector', 'active', '2026-09-19 14:36:21'),
(8, 'Decoration Support', 'active', '2026-09-19 14:36:21'),
(9, 'Catering Support', 'active', '2026-09-19 14:36:21');

-- --------------------------------------------------------

--
-- Table structure for table `hall_facility_map`
--

CREATE TABLE `hall_facility_map` (
  `hall_id` bigint(20) UNSIGNED NOT NULL,
  `facility_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hall_facility_map`
--

INSERT INTO `hall_facility_map` (`hall_id`, `facility_id`) VALUES
(1, 1),
(1, 2),
(1, 4),
(1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `hall_images`
--

CREATE TABLE `hall_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hall_id` bigint(20) UNSIGNED NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hall_images`
--

INSERT INTO `hall_images` (`id`, `hall_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`, `created_at`) VALUES
(1, 1, 'uploads/halls/1/image/a3602380a81d7996e0790e4ff8c1c8f3.jpg', 'Grand Marriage Hall', 1, 1, '2026-09-22 11:41:01'),
(2, 1, 'uploads/halls/1/image/4fb68b0e728cdcd8c7f870b915d01c07.webp', 'Grand Marriage Hall', 0, 2, '2026-09-22 11:41:19'),
(3, 1, 'uploads/halls/1/image/fbc99437aef4ef601db1860fc4b6a455.png', 'Grand Marriage Hall', 0, 3, '2026-09-22 11:41:30'),
(5, 1, 'uploads/halls/1/image/cae96370503f7554131075c906d6e292.webp', 'Grand Marriage Hall', 0, 4, '2026-09-22 11:45:30');

-- --------------------------------------------------------

--
-- Table structure for table `hall_lead_details`
--

CREATE TABLE `hall_lead_details` (
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `hall_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` varchar(100) DEFAULT NULL,
  `guest_count` int(10) UNSIGNED DEFAULT NULL,
  `food_required` tinyint(1) NOT NULL DEFAULT 0,
  `decoration_required` tinyint(1) NOT NULL DEFAULT 0,
  `other_requirements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hall_lead_details`
--

INSERT INTO `hall_lead_details` (`lead_id`, `hall_id`, `event_type`, `event_date`, `event_time`, `guest_count`, `food_required`, `decoration_required`, `other_requirements`) VALUES
(3, 1, 'Wedding', '2026-10-25', '10:00 AM', 400, 1, 1, 'Need vegetarian food and stage decoration.');

-- --------------------------------------------------------

--
-- Table structure for table `hall_types`
--

CREATE TABLE `hall_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hall_types`
--

INSERT INTO `hall_types` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Marriage Hall', 'marriage-hall', 'Hall suitable for marriage and wedding functions', 'active', '2026-09-19 14:36:21', '2026-09-19 14:36:21'),
(2, 'Party Hall', 'party-hall', 'Hall suitable for birthday, corporate and private events', 'active', '2026-09-19 14:36:21', '2026-09-19 14:36:21'),
(4, 'Premium Marriage Hall', 'premium-marriage-hall', 'Spacious hall suitable for marriage and wedding functions.', 'active', '2026-09-22 11:03:35', '2026-09-22 11:03:35');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lead_reference` varchar(50) NOT NULL,
  `domain` enum('hall','dining','catering') NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `guest_count` int(10) UNSIGNED DEFAULT NULL,
  `preferred_time` varchar(100) DEFAULT NULL,
  `requirement` text DEFAULT NULL,
  `status` enum('new','contacted','follow_up','converted','lost','cancelled') NOT NULL DEFAULT 'new',
  `estimated_amount` decimal(12,2) DEFAULT NULL,
  `converted_amount` decimal(12,2) DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `source` enum('website','whatsapp','phone','admin','other') NOT NULL DEFAULT 'website',
  `assigned_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `lead_reference`, `domain`, `customer_name`, `customer_phone`, `customer_email`, `event_date`, `guest_count`, `preferred_time`, `requirement`, `status`, `estimated_amount`, `converted_amount`, `converted_at`, `source`, `assigned_admin_id`, `created_at`, `updated_at`) VALUES
(1, 'DIN-20260922-D39E65BC', 'dining', 'Sriram', '9876543211', 'sriram@example.com', '2026-10-05', 50, '1:00 PM', 'Need less spicy food', 'converted', NULL, 35000.00, '2026-09-23 11:57:54', 'website', 2, '2026-09-22 13:36:04', '2026-09-23 11:57:54'),
(2, 'CAT-20260922-08183CBD', 'catering', 'Sriram Kumar', '9876543210', 'sriram@gmail.com', '2026-10-15', 150, '12:00 PM', 'Pure vegetarian catering required', 'contacted', 67500.00, NULL, NULL, 'website', NULL, '2026-09-22 20:09:18', '2026-09-23 11:03:04'),
(3, 'HAL-20260923-50A0AB52', 'hall', 'Arun Kumar', '9876543215', 'arun@gmail.com', '2026-10-25', 400, '10:00 AM', 'Need vegetarian food and stage decoration.', 'new', NULL, NULL, NULL, 'website', NULL, '2026-09-23 12:07:44', '2026-09-23 12:07:44');

-- --------------------------------------------------------

--
-- Table structure for table `lead_notifications`
--

CREATE TABLE `lead_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('whatsapp','email') NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `provider_message_id` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lead_notifications`
--

INSERT INTO `lead_notifications` (`id`, `lead_id`, `channel`, `recipient`, `message`, `status`, `provider_message_id`, `error_message`, `sent_at`, `created_at`) VALUES
(1, 1, 'whatsapp', '919360552619', '*SM GROUP - New Lead*\n\nLead ID: 1\nReference: DIN-20260922-D39E65BC\nDomain: DINING\nStatus: CONVERTED\n\n*Customer Details*\nName: Sriram\nPhone: 9876543211\nEmail: sriram@example.com\n\n*Dining Details*\nPackage: South Indian Lunch\nMeal Type: lunch\nDate: 2026-10-05\nTime: 1:00 PM\nGuests: 50\nRequirements: Need less spicy food\n\n*Assigned To:* Staff Kumar\nRole: STAFF\n\nPlease follow up with the customer.', 'pending', NULL, NULL, NULL, '2026-09-23 12:56:08'),
(2, 1, 'whatsapp', '919003408669', '*SM GROUP - New Lead*\n\nLead ID: 1\nReference: DIN-20260922-D39E65BC\nDomain: DINING\nStatus: CONVERTED\n\n*Customer Details*\nName: Sriram\nPhone: 9876543211\nEmail: sriram@example.com\n\n*Dining Details*\nPackage: South Indian Lunch\nMeal Type: lunch\nDate: 2026-10-05\nTime: 1:00 PM\nGuests: 50\nRequirements: Need less spicy food\n\n*Assigned To:* Staff Kumar\nRole: STAFF\n\nPlease follow up with the customer.', 'pending', NULL, NULL, NULL, '2026-09-23 12:56:08'),
(3, 1, 'email', 'sriramneelamegam04@gmail.com', 'SM GROUP - NEW LEAD NOTIFICATION\n\nLead Reference: DIN-20260922-D39E65BC\nLead ID: 1\nDomain: DINING\nStatus: CONVERTED\n\nCUSTOMER DETAILS\nName: Sriram\nPhone: 9876543211\nEmail: sriram@example.com\n\nDINING DETAILS\nPackage: South Indian Lunch\nMeal Type: lunch\nDining Date: 2026-10-05\nDining Time: 1:00 PM\nGuests: 50\n\nCUSTOMER REQUIREMENTS\nNeed less spicy food\n\nASSIGNED TO\nStaff Kumar (staff)\n\nPlease review this enquiry and contact the customer.\n\nSM GROUP', 'sent', NULL, NULL, '2026-09-23 14:05:33', '2026-09-23 14:05:28'),
(4, 1, 'email', 'dheeransriram04@gmail.com', 'SM GROUP - NEW LEAD NOTIFICATION\n\nLead Reference: DIN-20260922-D39E65BC\nLead ID: 1\nDomain: DINING\nStatus: CONVERTED\n\nCUSTOMER DETAILS\nName: Sriram\nPhone: 9876543211\nEmail: sriram@example.com\n\nDINING DETAILS\nPackage: South Indian Lunch\nMeal Type: lunch\nDining Date: 2026-10-05\nDining Time: 1:00 PM\nGuests: 50\n\nCUSTOMER REQUIREMENTS\nNeed less spicy food\n\nASSIGNED TO\nStaff Kumar (staff)\n\nPlease review this enquiry and contact the customer.\n\nSM GROUP', 'sent', NULL, NULL, '2026-09-23 14:05:38', '2026-09-23 14:05:33');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `payment_gateway` enum('razorpay') NOT NULL DEFAULT 'razorpay',
  `razorpay_order_id` varchar(150) DEFAULT NULL,
  `razorpay_payment_id` varchar(150) DEFAULT NULL,
  `razorpay_signature` varchar(500) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `payment_status` enum('created','authorized','captured','failed','refunded') NOT NULL DEFAULT 'created',
  `payment_method` varchar(50) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `payment_gateway`, `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `amount`, `currency`, `payment_status`, `payment_method`, `gateway_response`, `paid_at`, `created_at`, `updated_at`) VALUES
(2, 2, 'razorpay', 'order_TehFhvtHteakQ7', NULL, NULL, 4000.00, 'INR', 'created', NULL, '{\"amount\":400000,\"amount_due\":400000,\"amount_paid\":0,\"attempts\":0,\"created_at\":1789995835,\"currency\":\"INR\",\"entity\":\"order\",\"id\":\"order_TehFhvtHteakQ7\",\"notes\":{\"booking_id\":\"2\",\"booking_reference\":\"SMB-20260921-02FBD155\",\"customer_id\":\"1\"},\"offer_id\":null,\"receipt\":\"SMB-20260921-02FBD155\",\"status\":\"created\"}', NULL, '2026-09-21 18:33:53', '2026-09-21 18:33:53'),
(3, 3, 'razorpay', 'order_TehbLWS9ZCs4ND', NULL, NULL, 4000.00, 'INR', 'created', NULL, '{\"amount\":400000,\"amount_due\":400000,\"amount_paid\":0,\"attempts\":0,\"created_at\":1789997064,\"currency\":\"INR\",\"entity\":\"order\",\"id\":\"order_TehbLWS9ZCs4ND\",\"notes\":{\"booking_id\":\"3\",\"booking_reference\":\"SMB-20260921-58B1FCDE\",\"customer_id\":\"1\"},\"offer_id\":null,\"receipt\":\"SMB-20260921-58B1FCDE\",\"status\":\"created\"}', NULL, '2026-09-21 18:54:22', '2026-09-21 18:54:22'),
(4, 3, 'razorpay', 'order_TehqbGxmACuJEe', NULL, NULL, 4000.00, 'INR', '', NULL, NULL, NULL, '2026-09-21 19:08:49', '2026-09-21 19:08:49');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_type_id` bigint(20) UNSIGNED NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `bed_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `guest_capacity` int(10) UNSIGNED NOT NULL DEFAULT 2,
  `price_per_night` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `price_includes_gst` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('available','maintenance','inactive') NOT NULL DEFAULT 'available',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_type_id`, `room_number`, `name`, `description`, `bed_count`, `guest_capacity`, `price_per_night`, `gst_rate`, `price_includes_gst`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '101', 'Premium Room', 'Spacious premium room with modern facilities.', 2, 4, 2000.00, 18.00, 1, 'available', '2026-09-20 12:35:36', '2026-09-21 11:33:10'),
(3, 1, '102', 'Premium Room 02', 'Spacious premium room with modern facilities.', 3, 6, 3500.00, 18.00, 1, 'available', '2026-09-21 11:53:36', '2026-09-21 11:56:51');

-- --------------------------------------------------------

--
-- Table structure for table `room_amenities`
--

CREATE TABLE `room_amenities` (
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `amenity_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_amenities`
--

INSERT INTO `room_amenities` (`room_id`, `amenity_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9);

-- --------------------------------------------------------

--
-- Table structure for table `room_bookings`
--

CREATE TABLE `room_bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_reference` varchar(50) NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `total_guests` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `special_request` text DEFAULT NULL,
  `room_subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `gst_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `booking_status` enum('pending','confirmed','checked_in','checked_out','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_bookings`
--

INSERT INTO `room_bookings` (`id`, `booking_reference`, `customer_id`, `check_in_date`, `check_out_date`, `total_guests`, `special_request`, `room_subtotal`, `gst_rate`, `gst_amount`, `total_amount`, `payment_status`, `booking_status`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(2, 'SMB-20260921-02FBD155', 1, '2026-09-25', '2026-09-27', 2, 'Late check-in', 3389.83, 18.00, 610.17, 4000.00, 'pending', 'cancelled', 'Personal reason', '2026-09-21 18:33:53', '2026-09-21 18:49:33'),
(3, 'SMB-20260921-58B1FCDE', 1, '2026-09-25', '2026-09-27', 2, 'Late check-in', 3389.83, 18.00, 610.17, 4000.00, 'pending', 'pending', NULL, '2026-09-21 18:54:22', '2026-09-21 18:54:22');

-- --------------------------------------------------------

--
-- Table structure for table `room_food_packages`
--

CREATE TABLE `room_food_packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `meal_type` enum('breakfast','lunch','dinner','combo','other') NOT NULL DEFAULT 'combo',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_food_packages`
--

INSERT INTO `room_food_packages` (`id`, `name`, `description`, `meal_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Lunch Combo', 'Complete Lunch package for room guests.', 'lunch', 'active', '2026-09-21 13:55:25', '2026-09-21 13:55:25');

-- --------------------------------------------------------

--
-- Table structure for table `room_food_package_items`
--

CREATE TABLE `room_food_package_items` (
  `package_id` bigint(20) UNSIGNED NOT NULL,
  `food_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_food_package_items`
--

INSERT INTO `room_food_package_items` (`package_id`, `food_item_id`, `quantity`) VALUES
(1, 1, 3),
(1, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`id`, `room_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`, `created_at`) VALUES
(2, 1, 'uploads/rooms/1/image/b11ecd5bbd7f8498d9744053b9ae6d77.webp', 'Premium Room Interior', 1, 1, '2026-09-21 12:16:10'),
(3, 1, 'uploads/rooms/1/image/bacf4f9ea595934694e3ed5086ee8678.jpg', 'Premium Room Interior', 0, 2, '2026-09-21 12:16:39'),
(4, 1, 'uploads/rooms/1/image/1cc32ca726177faad68de648de7b8dbd.webp', 'Premium Room Interior', 0, 3, '2026-09-21 12:17:28'),
(6, 1, 'uploads/rooms/1/image/28e4495455b1432f1ddf45d7f44ee7ea.jpg', 'Premium Room Interior', 0, 5, '2026-09-21 12:18:12'),
(7, 1, 'uploads/rooms/1/image/ecb84351f272bc508dd0a5579d856655.jpg', 'Premium Room Interior', 0, 4, '2026-09-21 12:25:04');

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `default_bed_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `default_guest_capacity` int(10) UNSIGNED NOT NULL DEFAULT 2,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`id`, `name`, `slug`, `short_description`, `description`, `default_bed_count`, `default_guest_capacity`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Premium Suite', 'premium-suite', NULL, 'Spacious premium suite with modern facilities and comfortable accommodation.', 1, 2, 'active', '2026-09-19 21:25:54', '2026-09-19 21:45:22');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `setting_key` varchar(150) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') NOT NULL DEFAULT 'string',
  `description` varchar(500) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'default_gst_rate', '18', 'number', 'Default GST percentage', '2026-09-19 14:36:21'),
(2, 'room_price_includes_gst', '1', 'boolean', 'Room tariff is GST inclusive', '2026-09-19 14:36:21'),
(3, 'currency', 'INR', 'string', 'System currency', '2026-09-19 14:36:21'),
(4, 'currency_symbol', '₹', 'string', 'Currency symbol', '2026-09-19 14:36:21');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_lead_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_lead_summary` (
`domain` enum('hall','dining','catering')
,`total_leads` bigint(21)
,`new_leads` decimal(22,0)
,`contacted_leads` decimal(22,0)
,`follow_up_leads` decimal(22,0)
,`converted_leads` decimal(22,0)
,`lost_leads` decimal(22,0)
,`converted_revenue` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_monthly_lead_conversion`
-- (See below for the actual view)
--
CREATE TABLE `vw_monthly_lead_conversion` (
`lead_month` varchar(7)
,`domain` enum('hall','dining','catering')
,`total_leads` bigint(21)
,`converted_leads` decimal(22,0)
,`converted_revenue` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_monthly_room_revenue`
-- (See below for the actual view)
--
CREATE TABLE `vw_monthly_room_revenue` (
`revenue_month` varchar(7)
,`total_bookings` bigint(21)
,`revenue` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_room_revenue`
-- (See below for the actual view)
--
CREATE TABLE `vw_room_revenue` (
`total_bookings` bigint(21)
,`total_booking_value` decimal(34,2)
,`paid_revenue` decimal(34,2)
,`pending_amount` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_lead_summary`
--
DROP TABLE IF EXISTS `vw_lead_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_lead_summary`  AS SELECT `leads`.`domain` AS `domain`, count(0) AS `total_leads`, sum(case when `leads`.`status` = 'new' then 1 else 0 end) AS `new_leads`, sum(case when `leads`.`status` = 'contacted' then 1 else 0 end) AS `contacted_leads`, sum(case when `leads`.`status` = 'follow_up' then 1 else 0 end) AS `follow_up_leads`, sum(case when `leads`.`status` = 'converted' then 1 else 0 end) AS `converted_leads`, sum(case when `leads`.`status` = 'lost' then 1 else 0 end) AS `lost_leads`, coalesce(sum(case when `leads`.`status` = 'converted' then `leads`.`converted_amount` else 0 end),0) AS `converted_revenue` FROM `leads` GROUP BY `leads`.`domain` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_monthly_lead_conversion`
--
DROP TABLE IF EXISTS `vw_monthly_lead_conversion`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_monthly_lead_conversion`  AS SELECT date_format(`leads`.`created_at`,'%Y-%m') AS `lead_month`, `leads`.`domain` AS `domain`, count(0) AS `total_leads`, sum(case when `leads`.`status` = 'converted' then 1 else 0 end) AS `converted_leads`, coalesce(sum(case when `leads`.`status` = 'converted' then `leads`.`converted_amount` else 0 end),0) AS `converted_revenue` FROM `leads` GROUP BY date_format(`leads`.`created_at`,'%Y-%m'), `leads`.`domain` ORDER BY date_format(`leads`.`created_at`,'%Y-%m') DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_monthly_room_revenue`
--
DROP TABLE IF EXISTS `vw_monthly_room_revenue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_monthly_room_revenue`  AS SELECT date_format(`room_bookings`.`created_at`,'%Y-%m') AS `revenue_month`, count(0) AS `total_bookings`, coalesce(sum(case when `room_bookings`.`payment_status` = 'paid' then `room_bookings`.`total_amount` else 0 end),0) AS `revenue` FROM `room_bookings` WHERE `room_bookings`.`booking_status` not in ('cancelled','no_show') GROUP BY date_format(`room_bookings`.`created_at`,'%Y-%m') ORDER BY date_format(`room_bookings`.`created_at`,'%Y-%m') DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_room_revenue`
--
DROP TABLE IF EXISTS `vw_room_revenue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_room_revenue`  AS SELECT count(0) AS `total_bookings`, coalesce(sum(case when `room_bookings`.`payment_status` in ('paid','partial') then `room_bookings`.`total_amount` else 0 end),0) AS `total_booking_value`, coalesce(sum(case when `room_bookings`.`payment_status` = 'paid' then `room_bookings`.`total_amount` else 0 end),0) AS `paid_revenue`, coalesce(sum(case when `room_bookings`.`payment_status` in ('pending','partial') then `room_bookings`.`total_amount` else 0 end),0) AS `pending_amount` FROM `room_bookings` WHERE `room_bookings`.`booking_status` not in ('cancelled','no_show') ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_amenity_name` (`name`);

--
-- Indexes for table `booking_activity_logs`
--
ALTER TABLE `booking_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bal_admin` (`performed_by_admin_id`),
  ADD KEY `idx_bal_booking` (`booking_id`);

--
-- Indexes for table `booking_food_items`
--
ALTER TABLE `booking_food_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bfi_food` (`food_item_id`),
  ADD KEY `idx_bfi_booking` (`booking_id`);

--
-- Indexes for table `booking_food_packages`
--
ALTER TABLE `booking_food_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bfp_package` (`package_id`),
  ADD KEY `idx_bfp_booking` (`booking_id`);

--
-- Indexes for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_rooms_booking` (`booking_id`),
  ADD KEY `idx_booking_rooms_room` (`room_id`);

--
-- Indexes for table `catering_lead_details`
--
ALTER TABLE `catering_lead_details`
  ADD PRIMARY KEY (`lead_id`),
  ADD KEY `fk_cld_package` (`catering_package_id`);

--
-- Indexes for table `catering_lead_items`
--
ALTER TABLE `catering_lead_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cli_food` (`food_item_id`),
  ADD KEY `idx_cli_lead` (`lead_id`);

--
-- Indexes for table `catering_packages`
--
ALTER TABLE `catering_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `catering_package_items`
--
ALTER TABLE `catering_package_items`
  ADD PRIMARY KEY (`package_id`,`food_item_id`),
  ADD KEY `fk_cpi_food` (`food_item_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_customer_phone` (`phone`),
  ADD UNIQUE KEY `uk_customer_email` (`email`);

--
-- Indexes for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_customer` (`customer_id`),
  ADD KEY `idx_session_expiry` (`expires_at`);

--
-- Indexes for table `dining_lead_details`
--
ALTER TABLE `dining_lead_details`
  ADD PRIMARY KEY (`lead_id`),
  ADD KEY `fk_dld_package` (`dining_package_id`);

--
-- Indexes for table `dining_lead_items`
--
ALTER TABLE `dining_lead_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dli_food` (`food_item_id`),
  ADD KEY `idx_dli_lead` (`lead_id`);

--
-- Indexes for table `dining_packages`
--
ALTER TABLE `dining_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dining_package_items`
--
ALTER TABLE `dining_package_items`
  ADD PRIMARY KEY (`package_id`,`food_item_id`),
  ADD KEY `fk_dpi_food` (`food_item_id`);

--
-- Indexes for table `food_categories`
--
ALTER TABLE `food_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_food_category_name` (`name`);

--
-- Indexes for table `food_items`
--
ALTER TABLE `food_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_food_category` (`food_category_id`),
  ADD KEY `idx_food_status` (`status`);

--
-- Indexes for table `halls`
--
ALTER TABLE `halls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_hall_type` (`hall_type_id`),
  ADD KEY `idx_hall_status` (`status`);

--
-- Indexes for table `hall_facilities`
--
ALTER TABLE `hall_facilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_hall_facility_name` (`name`);

--
-- Indexes for table `hall_facility_map`
--
ALTER TABLE `hall_facility_map`
  ADD PRIMARY KEY (`hall_id`,`facility_id`),
  ADD KEY `fk_hfm_facility` (`facility_id`);

--
-- Indexes for table `hall_images`
--
ALTER TABLE `hall_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hall_images_hall` (`hall_id`);

--
-- Indexes for table `hall_lead_details`
--
ALTER TABLE `hall_lead_details`
  ADD PRIMARY KEY (`lead_id`),
  ADD KEY `fk_hld_hall` (`hall_id`);

--
-- Indexes for table `hall_types`
--
ALTER TABLE `hall_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lead_reference` (`lead_reference`),
  ADD KEY `fk_lead_admin` (`assigned_admin_id`),
  ADD KEY `idx_lead_domain` (`domain`),
  ADD KEY `idx_lead_status` (`status`),
  ADD KEY `idx_lead_date` (`event_date`),
  ADD KEY `idx_lead_phone` (`customer_phone`),
  ADD KEY `idx_lead_created` (`created_at`);

--
-- Indexes for table `lead_notifications`
--
ALTER TABLE `lead_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_lead` (`lead_id`),
  ADD KEY `idx_notification_status` (`status`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_booking` (`booking_id`),
  ADD KEY `idx_payment_order` (`razorpay_order_id`),
  ADD KEY `idx_payment_payment_id` (`razorpay_payment_id`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `idx_rooms_type` (`room_type_id`),
  ADD KEY `idx_rooms_status` (`status`);

--
-- Indexes for table `room_amenities`
--
ALTER TABLE `room_amenities`
  ADD PRIMARY KEY (`room_id`,`amenity_id`),
  ADD KEY `fk_ra_amenity` (`amenity_id`);

--
-- Indexes for table `room_bookings`
--
ALTER TABLE `room_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `idx_booking_customer` (`customer_id`),
  ADD KEY `idx_booking_dates` (`check_in_date`,`check_out_date`),
  ADD KEY `idx_booking_status` (`booking_status`),
  ADD KEY `idx_booking_payment_status` (`payment_status`);

--
-- Indexes for table `room_food_packages`
--
ALTER TABLE `room_food_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `room_food_package_items`
--
ALTER TABLE `room_food_package_items`
  ADD PRIMARY KEY (`package_id`,`food_item_id`),
  ADD KEY `fk_rfpi_food` (`food_item_id`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_images_room` (`room_id`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `booking_activity_logs`
--
ALTER TABLE `booking_activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `booking_food_items`
--
ALTER TABLE `booking_food_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `booking_food_packages`
--
ALTER TABLE `booking_food_packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `catering_lead_items`
--
ALTER TABLE `catering_lead_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `catering_packages`
--
ALTER TABLE `catering_packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dining_lead_items`
--
ALTER TABLE `dining_lead_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dining_packages`
--
ALTER TABLE `dining_packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `food_categories`
--
ALTER TABLE `food_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `halls`
--
ALTER TABLE `halls`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hall_facilities`
--
ALTER TABLE `hall_facilities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `hall_images`
--
ALTER TABLE `hall_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `hall_types`
--
ALTER TABLE `hall_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lead_notifications`
--
ALTER TABLE `lead_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `room_bookings`
--
ALTER TABLE `room_bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `room_food_packages`
--
ALTER TABLE `room_food_packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `room_types`
--
ALTER TABLE `room_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_activity_logs`
--
ALTER TABLE `booking_activity_logs`
  ADD CONSTRAINT `fk_bal_admin` FOREIGN KEY (`performed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bal_booking` FOREIGN KEY (`booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_food_items`
--
ALTER TABLE `booking_food_items`
  ADD CONSTRAINT `fk_bfi_booking` FOREIGN KEY (`booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bfi_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`);

--
-- Constraints for table `booking_food_packages`
--
ALTER TABLE `booking_food_packages`
  ADD CONSTRAINT `fk_bfp_booking` FOREIGN KEY (`booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bfp_package` FOREIGN KEY (`package_id`) REFERENCES `room_food_packages` (`id`);

--
-- Constraints for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  ADD CONSTRAINT `fk_booking_room_booking` FOREIGN KEY (`booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_room_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Constraints for table `catering_lead_details`
--
ALTER TABLE `catering_lead_details`
  ADD CONSTRAINT `fk_cld_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cld_package` FOREIGN KEY (`catering_package_id`) REFERENCES `catering_packages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `catering_lead_items`
--
ALTER TABLE `catering_lead_items`
  ADD CONSTRAINT `fk_cli_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_cli_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `catering_package_items`
--
ALTER TABLE `catering_package_items`
  ADD CONSTRAINT `fk_cpi_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_cpi_package` FOREIGN KEY (`package_id`) REFERENCES `catering_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  ADD CONSTRAINT `fk_session_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dining_lead_details`
--
ALTER TABLE `dining_lead_details`
  ADD CONSTRAINT `fk_dld_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dld_package` FOREIGN KEY (`dining_package_id`) REFERENCES `dining_packages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dining_lead_items`
--
ALTER TABLE `dining_lead_items`
  ADD CONSTRAINT `fk_dli_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_dli_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dining_package_items`
--
ALTER TABLE `dining_package_items`
  ADD CONSTRAINT `fk_dpi_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_dpi_package` FOREIGN KEY (`package_id`) REFERENCES `dining_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `food_items`
--
ALTER TABLE `food_items`
  ADD CONSTRAINT `fk_food_category` FOREIGN KEY (`food_category_id`) REFERENCES `food_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `halls`
--
ALTER TABLE `halls`
  ADD CONSTRAINT `fk_hall_type` FOREIGN KEY (`hall_type_id`) REFERENCES `hall_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `hall_facility_map`
--
ALTER TABLE `hall_facility_map`
  ADD CONSTRAINT `fk_hfm_facility` FOREIGN KEY (`facility_id`) REFERENCES `hall_facilities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hfm_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hall_images`
--
ALTER TABLE `hall_images`
  ADD CONSTRAINT `fk_hall_image` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hall_lead_details`
--
ALTER TABLE `hall_lead_details`
  ADD CONSTRAINT `fk_hld_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hld_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `fk_lead_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lead_notifications`
--
ALTER TABLE `lead_notifications`
  ADD CONSTRAINT `fk_notification_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `room_bookings` (`id`);

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_room_type` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`);

--
-- Constraints for table `room_amenities`
--
ALTER TABLE `room_amenities`
  ADD CONSTRAINT `fk_ra_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ra_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_bookings`
--
ALTER TABLE `room_bookings`
  ADD CONSTRAINT `fk_booking_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `room_food_package_items`
--
ALTER TABLE `room_food_package_items`
  ADD CONSTRAINT `fk_rfpi_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_rfpi_package` FOREIGN KEY (`package_id`) REFERENCES `room_food_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `fk_room_image_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
