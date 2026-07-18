-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 29, 2025 at 04:00 AM
-- Server version: 5.7.24
-- PHP Version: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wod_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `contributions`
--

CREATE TABLE `contributions` (
  `contribution_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `is_paid` tinyint(1) DEFAULT '0',
  `username` varchar(50) DEFAULT NULL,
  `group_gift_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `username_1` varchar(50) NOT NULL,
  `username_2` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `friends`
--

INSERT INTO `friends` (`username_1`, `username_2`, `created_at`) VALUES
('Sarah_Abdullah', 'glamgear.store', '2025-11-29 03:58:14');

-- --------------------------------------------------------

--
-- Table structure for table `group_gifts`
--

CREATE TABLE `group_gifts` (
  `group_gift_id` int(11) NOT NULL,
  `group_size` int(11) DEFAULT NULL,
  `collected_amount` decimal(10,2) DEFAULT '0.00',
  `item_id` int(11) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `likes`
--

CREATE TABLE `likes` (
  `like_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `likes`
--

INSERT INTO `likes` (`like_id`, `username`, `post_id`, `created_at`) VALUES
(15, 'Sarah_Abdullah', 15, '2025-11-29 03:57:44'),
(16, 'Sarah_Abdullah', 12, '2025-11-29 03:57:47'),
(17, 'Sarah_Abdullah', 16, '2025-11-29 03:57:49'),
(18, 'Sarah_Abdullah', 10, '2025-11-29 03:57:50');

-- --------------------------------------------------------

--
-- Table structure for table `occasions`
--

CREATE TABLE `occasions` (
  `occasion_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `date` date NOT NULL,
  `type` enum('personal','birthday','anniversary','graduation','other') DEFAULT 'personal',
  `description` text,
  `username` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `occasions`
--

INSERT INTO `occasions` (`occasion_id`, `title`, `date`, `type`, `description`, `username`, `created_at`) VALUES
(1, 'My birthday ', '2025-12-05', 'birthday', '', 'Sarah_Abdullah', '2025-11-29 03:59:28');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `post_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `image_url` varchar(255) DEFAULT NULL,
  `external_link` varchar(2000) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`post_id`, `title`, `description`, `image_url`, `external_link`, `price`, `username`, `created_at`) VALUES
(9, 'rose bouquet', 'A beautiful pink bouquet of roses.', 'uploads/692a66925b184.jpg', '', '275.00', 'flowers_shop', '2025-11-29 03:20:50'),
(10, 'lilies bouquet', 'A chic lily bouquet with clean lines and fresh blooms, offering a minimalist yet stunning statement.', 'uploads/692a67a3312ce.jpg', '', '299.00', 'flowers_shop', '2025-11-29 03:25:23'),
(12, 'Tulip bouquets', 'A fresh and elegant bouquet of tulips, arranged with soft colors and natural charm-perfect for brightening any day or gifting with love.', 'uploads/692a68a0c99e4.jpg', '', '240.00', 'flowers_shop', '2025-11-29 03:29:36'),
(13, 'Candle Bliss Box', 'Brighten someone’s day with this charming gift box, including a handmade candle that fills any space with love and soft fragrance.', 'uploads/692a6a814a6ae.jpg', '', '80.00', 'cozyflame.co', '2025-11-29 03:37:37'),
(14, 'Orchid Phone Grips', 'Upgrade your phone style with these chic orchid phone grips!', 'uploads/692a6bfd2d27b.jpg', '', '54.00', 'glamgear.store', '2025-11-29 03:43:57'),
(15, 'Star Moon Pendant With Phone Case ', '', 'uploads/692a6c30a4bb7.jpg', '', '70.00', 'glamgear.store', '2025-11-29 03:44:48'),
(16, 'Peony candles', 'The wax flower is made of coconut wax and the base of the candle is made of soy.', 'uploads/692a6cef94b83.jpg', '', '120.00', 'cozyflame.co', '2025-11-29 03:47:59'),
(18, 'Bright & Rosy Mini Lip And Cheek Set', '', 'uploads/692a6f2a3f09e.jpg', 'https://www.sephora.me/sa-en/p/bright-rosy-mini-lip-and-cheek-set/758552', '164.00', 'Sarah_Abdullah', '2025-11-29 03:57:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `username` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `bio` text,
  `user_type` enum('user','store') DEFAULT 'user',
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`username`, `name`, `photo_url`, `bio`, `user_type`, `email`, `password`, `created_at`) VALUES
('cozyflame.co', 'CozyFlame', 'uploads/692a69bc21dee_profile.jpg', 'Cozy candles, warm smells, and handmade love in every jar.\r\nYou can find us in King Fahd Road, Riyadh.', 'store', 'CozyFlame@CozyFlame', '$2y$10$0gM12UdCPhvmdYLgQ4F.uONpyFdbs1QDoHG6DTd9g9sO5FVS0N7xa', '2025-11-29 03:31:24'),
('flowers_shop', 'Florita', 'uploads/692a6726b232e_profile.jpg', 'Blooming beauty in every bouquet. We create handcrafted floral arrangements that speak love, joy, and elegance.', 'store', 'flowers@gmail.com', '$2y$10$RBcwuY22MceSKSRXifqBSunGl5iB4ebEnipeSOPt2Pqp/mCDBPaFK', '2025-11-29 03:15:52'),
('glamgear.store', 'GlamGear', 'uploads/692a6bc26f5e8_profile.jpg', 'Trendy phone cases, charms, and tech accessories to match your style.', 'store', 'GlamGear@GlamGear', '$2y$10$pfFLbDqbbLVSajDzCgvS3..gg4lVKLx8ANVTuHu1TaNKYn2nGgcou', '2025-11-29 03:39:11'),
('Sarah_Abdullah', 'Sarah Abdullah', 'uploads/692a6ead1b0f9_profile.png', 'into finding and sharing things that make everyday life special', 'user', 'Sarah_Abdullah@gmail.com', '$2y$10$qh410S7ZAhOMIogoSxxKvuij0z8GiqiQ5olwecceQhLBJvZkTO0LW', '2025-11-29 03:55:05');

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `wishlist_id` int(11) NOT NULL,
  `visibility` tinyint(1) DEFAULT '1',
  `username` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`wishlist_id`, `visibility`, `username`) VALUES
(4, 0, 'Sarah_Abdullah');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist_items`
--

CREATE TABLE `wishlist_items` (
  `item_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `external_link` varchar(2000) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_reserved` tinyint(1) DEFAULT '0',
  `price` decimal(10,2) DEFAULT NULL,
  `reserved_by` varchar(50) DEFAULT NULL,
  `wishlist_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `post_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `wishlist_items`
--

INSERT INTO `wishlist_items` (`item_id`, `title`, `external_link`, `image_url`, `is_reserved`, `price`, `reserved_by`, `wishlist_id`, `created_at`, `post_id`) VALUES
(12, 'Bright & Rosy Mini Lip And Cheek Set', 'https://www.sephora.me/sa-en/p/bright-rosy-mini-lip-and-cheek-set/758552', 'uploads/692a6f2a3f09e.jpg', 0, '164.00', NULL, 4, '2025-11-29 03:57:33', 18),
(13, 'rose bouquet', '', 'uploads/692a66925b184.jpg', 0, '275.00', NULL, 4, '2025-11-29 03:57:56', 9),
(14, 'Orchid Phone Grips', '', 'uploads/692a6bfd2d27b.jpg', 0, '54.00', NULL, 4, '2025-11-29 03:57:58', 14);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contributions`
--
ALTER TABLE `contributions`
  ADD PRIMARY KEY (`contribution_id`),
  ADD KEY `username` (`username`),
  ADD KEY `group_gift_id` (`group_gift_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`username_1`,`username_2`),
  ADD KEY `username_2` (`username_2`);

--
-- Indexes for table `group_gifts`
--
ALTER TABLE `group_gifts`
  ADD PRIMARY KEY (`group_gift_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `unique_like` (`username`,`post_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `occasions`
--
ALTER TABLE `occasions`
  ADD PRIMARY KEY (`occasion_id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `reserved_by` (`reserved_by`),
  ADD KEY `wishlist_id` (`wishlist_id`),
  ADD KEY `fk_wishlist_items_post` (`post_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contributions`
--
ALTER TABLE `contributions`
  MODIFY `contribution_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `group_gifts`
--
ALTER TABLE `group_gifts`
  MODIFY `group_gift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `likes`
--
ALTER TABLE `likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `occasions`
--
ALTER TABLE `occasions`
  MODIFY `occasion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contributions`
--
ALTER TABLE `contributions`
  ADD CONSTRAINT `contributions_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `contributions_ibfk_2` FOREIGN KEY (`group_gift_id`) REFERENCES `group_gifts` (`group_gift_id`) ON DELETE CASCADE;

--
-- Constraints for table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`username_1`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`username_2`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `group_gifts`
--
ALTER TABLE `group_gifts`
  ADD CONSTRAINT `group_gifts_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `wishlist_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_gifts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Constraints for table `occasions`
--
ALTER TABLE `occasions`
  ADD CONSTRAINT `occasions_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD CONSTRAINT `fk_wishlist_items_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `wishlist_items_ibfk_1` FOREIGN KEY (`reserved_by`) REFERENCES `users` (`username`) ON DELETE SET NULL,
  ADD CONSTRAINT `wishlist_items_ibfk_2` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`wishlist_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
