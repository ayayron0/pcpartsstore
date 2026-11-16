-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 16, 2026 at 03:39 AM
-- Server version: 11.8.5-MariaDB-log
-- PHP Version: 8.5.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pcpartsstore_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`) VALUES
(1, 'NVIDIA'),
(2, 'AMD'),
(3, 'Intel'),
(4, 'Crucial'),
(5, 'Corsair'),
(6, 'Gigabyte'),
(7, 'Asus'),
(8, 'MSI'),
(9, 'Corsair'),
(10, 'Samsung'),
(11, 'NZXT'),
(12, 'Razer'),
(13, 'ASRock');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Processing'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `status`) VALUES
(10001, 1001, 'Processing'),
(10002, 1002, 'Delivered'),
(10003, 1003, 'Processing'),
(10004, 1003, 'Shipped'),
(10005, 1004, 'Delivered'),
(10006, 1004, 'Cancelled'),
(10007, 1005, 'Processing'),
(10008, 1005, 'Shipped'),
(10009, 1006, 'Delivered'),
(10010, 1006, 'Cancelled'),
(10011, 1007, 'Processing'),
(10012, 1007, 'Delivered');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `part_id`, `quantity`) VALUES
(100001, 10001, 104, 2),
(100002, 10002, 102, 3),
(100003, 10003, 106, 1),
(100004, 10003, 103, 2),
(100005, 10004, 105, 1),
(100006, 10005, 103, 1),
(100007, 10006, 101, 1),
(100008, 10007, 105, 1),
(100009, 10008, 107, 1),
(100010, 10009, 103, 2),
(100011, 10010, 106, 1);

-- --------------------------------------------------------

--
-- Table structure for table `parts`
--

CREATE TABLE `parts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parts`
--

INSERT INTO `parts` (`id`, `name`, `category`, `brand_id`, `price`) VALUES
(101, 'GeForce RTX 5070', 'GPU', 1, 834.24),
(102, 'Core i7 13700K', 'CPU', 3, 578.23),
(103, '8GB DDR4-3200', 'RAM', 4, 110.14),
(104, 'DeathAdder Essential Gaming Mouse', 'Mouse', 12, 59.99),
(105, '990 Pro NVMe M.2 SSD', 'Storage', 10, 419.99),
(106, 'H7 Flow', 'PC Case', 11, 219.99),
(107, 'B550M Pro-VDH WiFi', 'Motherboard', 8, 119.98);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `role`) VALUES
(1001, 'Sam', 'Lee', 'sam@example.com', NULL, 'customer'),
(1002, 'John', 'Smith', 'john@example.com', NULL, 'customer'),
(1003, 'Alex', 'Chen', 'demo.alex.chen@example.com', NULL, 'customer'),
(1004, 'Alex', 'Patel', 'demo.alex.patel@example.com', NULL, 'customer'),
(1005, 'Maya', 'Chen', 'demo.maya.chen@example.com', NULL, 'customer'),
(1006, 'Noah', 'Singh', 'demo.noah.singh@example.com', NULL, 'customer'),
(1007, 'Olivia', 'Brown', 'demo.olivia.brown@example.com', NULL, 'customer'),
(1008, 'Liam', 'Wilson', 'demo.liam.wilson@example.com', NULL, 'customer');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orders_fk_1` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_fk_1` (`order_id`),
  ADD KEY `order_items_fk_2` (`part_id`);

--
-- Indexes for table `parts`
--
ALTER TABLE `parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parts_fk_1` (`brand_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10013;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100012;

--
-- AUTO_INCREMENT for table `parts`
--
ALTER TABLE `parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1009;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_fk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_fk_2` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`);

--
-- Constraints for table `parts`
--
ALTER TABLE `parts`
  ADD CONSTRAINT `parts_fk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
