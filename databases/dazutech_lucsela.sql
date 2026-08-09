-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 04, 2026 at 04:31 PM
-- Server version: 10.11.18-MariaDB-cll-lve
-- PHP Version: 8.4.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dazutech_lucsela`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(150) DEFAULT NULL,
  `role` varchar(60) DEFAULT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `entity_label` varchar(200) DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `changes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `tenant_id`, `user_id`, `username`, `role`, `entity_type`, `entity_id`, `entity_label`, `action`, `changes`, `created_at`) VALUES
(1, 2, 9, 'VickieKaran', 'tenant_owner', 'product', 9, 'DELETE ON SIGHT', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"10\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"35\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"60\"}]', '2026-07-03 06:20:00'),
(2, 2, 9, 'VickieKaran', 'tenant_owner', 'product', 9, 'DELETE ON SIGHT', 'deleted', '[{\"field\":\"quantity\",\"label\":\"Stock at deletion\",\"from\":\"100 piece\",\"to\":\"\\u2014\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"KES 60\",\"to\":\"\\u2014\"}]', '2026-07-03 06:20:28'),
(3, 2, 9, 'VickieKaran', 'tenant_owner', 'product', 10, 'DELETE ON SIGHT', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"400\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"60\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"90\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]', '2026-07-08 07:26:41'),
(4, 2, 2, 'Lucsela', 'tenant_owner', 'product', 11, 'biryani rice', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"136\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"85.6\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 12:28:58'),
(5, 2, 2, 'Lucsela', 'tenant_owner', 'product', 12, 'maha', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"205.15\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"124\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"135\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]', '2026-07-09 12:32:14'),
(6, 2, 2, 'Lucsela', 'tenant_owner', 'product', 13, 'sindano', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"126\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"131.20\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"140\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"160\"}]', '2026-07-09 12:39:29'),
(7, 2, 2, 'Lucsela', 'tenant_owner', 'product', 14, 'pishori Tz', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"81.7\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"130\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]', '2026-07-09 12:41:59'),
(8, 2, 2, 'Lucsela', 'tenant_owner', 'product', 15, 'pishori mwea', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"279.65\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"150\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"160\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"180\"}]', '2026-07-09 12:44:03'),
(9, 2, 2, 'Lucsela', 'tenant_owner', 'product', 16, 'split peas', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"34.85\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"85\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"130\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"160\"}]', '2026-07-09 12:56:08'),
(10, 2, 2, 'Lucsela', 'tenant_owner', 'product', 17, 'popcorn', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"22\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"160\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"200\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"220\"}]', '2026-07-09 12:57:01'),
(11, 2, 2, 'Lucsela', 'tenant_owner', 'product', 18, 'simsim', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"36.70\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"180\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"220\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"240\"}]', '2026-07-09 12:58:26'),
(12, 2, 2, 'Lucsela', 'tenant_owner', 'product', 15, 'pishori mwea', 'updated', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"279.15\",\"to\":\"308.40\"}]', '2026-07-09 12:59:21'),
(13, 2, 2, 'Lucsela', 'tenant_owner', 'product', 19, 'njugu red big', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"86.50\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"190\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"220\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"240\"}]', '2026-07-09 13:02:04'),
(14, 2, 2, 'Lucsela', 'tenant_owner', 'product', 20, 'njugu red small', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"47.70\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"200\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"220\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"240\"}]', '2026-07-09 13:04:39'),
(15, 2, 2, 'Lucsela', 'tenant_owner', 'product', 21, 'njahi', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"82.80\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"72.20\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"90\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 13:09:08'),
(16, 2, 2, 'Lucsela', 'tenant_owner', 'product', 22, 'nylon', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"179.95\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"125\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"140\"}]', '2026-07-09 13:11:23'),
(17, 2, 2, 'Lucsela', 'tenant_owner', 'product', 23, 'Makueni', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"258.5\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"130\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]', '2026-07-09 13:15:04'),
(18, 2, 2, 'Lucsela', 'tenant_owner', 'product', 24, 'minji', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"98.55\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"120\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"140\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]', '2026-07-09 13:16:48'),
(19, 2, 2, 'Lucsela', 'tenant_owner', 'product', 25, 'kamande', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"211.95\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"145\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"170\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"200\"}]', '2026-07-09 13:21:13'),
(20, 2, 2, 'Lucsela', 'tenant_owner', 'product', 26, 'kunde white', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"77\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"88.80\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 13:26:46'),
(21, 2, 2, 'Lucsela', 'tenant_owner', 'product', 27, 'mbaazi', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"158.50\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"83.30\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 13:28:01'),
(22, 2, 2, 'Lucsela', 'tenant_owner', 'product', 28, 'Rosecoco', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"26.95\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"94.40\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"120\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]', '2026-07-09 13:29:10'),
(23, 2, 2, 'Lucsela', 'tenant_owner', 'product', 29, 'Nyayo', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"169.60\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"83.30\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 13:31:11'),
(24, 2, 2, 'Lucsela', 'tenant_owner', 'product', 30, 'muthokoi', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"21.15\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"55\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"70\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"80\"}]', '2026-07-09 13:32:47'),
(25, 2, 2, 'Lucsela', 'tenant_owner', 'product', 31, 'Mwitemania', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"30.40\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"77.70\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 13:39:06'),
(26, 2, 2, 'Lucsela', 'tenant_owner', 'product', 32, 'yellow bean 1', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"229.30\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"94.40\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"120\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"140\"}]', '2026-07-09 14:18:06'),
(27, 2, 2, 'Lucsela', 'tenant_owner', 'product', 33, 'yellow bean 2', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"49.40\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"92.20\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"130\"}]', '2026-07-09 14:19:04'),
(28, 2, 2, 'Lucsela', 'tenant_owner', 'product', 34, 'wairimu', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"202.60\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"74.40\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"90\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 14:21:34'),
(29, 2, 2, 'Lucsela', 'tenant_owner', 'product', 35, 'kunde red', 'created', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"100.2\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"83.3\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]', '2026-07-09 14:23:46'),
(30, 2, 2, 'Lucsela', 'tenant_owner', 'product', 10, 'maize', 'updated', '[{\"field\":\"name\",\"label\":\"Name\",\"from\":\"DELETE ON SIGHT\",\"to\":\"maize\"},{\"field\":\"category_id\",\"label\":\"Category\",\"from\":\"BEANS\",\"to\":\"maize\"},{\"field\":\"subcategory_id\",\"label\":\"Subcategory\",\"from\":\"\\u2014\",\"to\":\"white maize\"},{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"300.00\",\"to\":\"303.70\"},{\"field\":\"buying_price\",\"label\":\"Buying price\",\"from\":\"60.00\",\"to\":\"41.1\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale price\",\"from\":\"90.00\",\"to\":\"50\"},{\"field\":\"retail_price\",\"label\":\"Retail price\",\"from\":\"150.00\",\"to\":\"6041.1\"}]', '2026-07-09 14:31:24'),
(31, 2, 2, 'Lucsela', 'tenant_owner', 'product', 8, 'yellow bean 120', 'updated', '[{\"field\":\"name\",\"label\":\"Name\",\"from\":\"yellow bean 2\",\"to\":\"yellow bean 120\"},{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"1416.65\",\"to\":\"1440\"}]', '2026-07-09 15:33:47'),
(32, 2, 2, 'Lucsela', 'tenant_owner', 'product', 4, 'yellow bean 110', 'updated', '[{\"field\":\"name\",\"label\":\"Name\",\"from\":\"yellow bean 1\",\"to\":\"yellow bean 110\"},{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"1345.40\",\"to\":\"900\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale price\",\"from\":\"120.00\",\"to\":\"110\"}]', '2026-07-09 15:34:42'),
(33, 2, 2, 'Lucsela', 'tenant_owner', 'product', 33, 'yellow bean 2', 'updated', '[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"31.40\",\"to\":\"43.40\"}]', '2026-07-09 15:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#667eea',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_faqs`
--

CREATE TABLE `blog_faqs` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `question` varchar(300) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_sections`
--

CREATE TABLE `blog_sections` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video','youtube','code_block','quote') DEFAULT 'text_only',
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video','youtube') DEFAULT 'image',
  `video_id` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tags`
--

CREATE TABLE `blog_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tag_relations`
--

CREATE TABLE `blog_tag_relations` (
  `blog_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `tenant_id`, `title`, `location`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 2, 'Cereal shop', NULL, 1, '2026-06-23 14:27:46', '2026-06-23 14:27:46');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('active','draft') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `tenant_id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(3, 2, 'BEANS', 'active', '2026-06-23 15:02:46', '2026-06-23 15:02:46'),
(4, 2, 'Ndengu', 'active', '2026-06-23 16:38:33', '2026-07-02 16:40:34'),
(6, 2, 'maize', 'active', '2026-07-02 16:41:24', '2026-07-02 16:41:24'),
(7, 2, 'Njungu', 'active', '2026-07-02 16:42:19', '2026-07-02 16:42:19'),
(8, 2, 'simsim', 'active', '2026-07-02 16:43:19', '2026-07-02 16:43:19'),
(9, 2, 'kamande', 'active', '2026-07-02 16:43:36', '2026-07-02 16:43:36'),
(10, 2, 'mbaazi', 'active', '2026-07-02 16:44:03', '2026-07-02 16:44:03'),
(11, 2, 'minji', 'active', '2026-07-02 16:44:12', '2026-07-02 16:44:12'),
(12, 2, 'muthokoi', 'active', '2026-07-02 16:44:18', '2026-07-02 16:44:18'),
(13, 2, 'njahi', 'active', '2026-07-02 16:44:23', '2026-07-02 16:44:23'),
(14, 2, 'split peas', 'active', '2026-07-02 16:44:33', '2026-07-02 16:44:33'),
(15, 2, 'kunde red', 'active', '2026-07-02 16:44:41', '2026-07-02 16:44:41'),
(16, 2, 'kunde white', 'active', '2026-07-02 16:44:49', '2026-07-02 16:44:49'),
(17, 2, 'pishori Mwea', 'active', '2026-07-02 16:45:01', '2026-07-02 16:45:01'),
(18, 2, 'pishori Tz', 'active', '2026-07-02 16:45:16', '2026-07-02 16:45:16'),
(19, 2, 'maha', 'active', '2026-07-02 16:45:20', '2026-07-02 16:45:20'),
(20, 2, 'sindano', 'active', '2026-07-02 16:45:27', '2026-07-02 16:45:27'),
(21, 2, 'Byriani', 'active', '2026-07-02 16:45:38', '2026-07-02 16:45:38'),
(22, 2, 'Basmati', 'active', '2026-07-02 16:45:48', '2026-07-02 16:45:48'),
(23, 2, 'popcorn', 'active', '2026-07-02 16:46:05', '2026-07-02 16:46:05');

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `service` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','read','contacted','closed') DEFAULT 'new',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enquiries`
--

INSERT INTO `enquiries` (`id`, `name`, `email`, `phone`, `service`, `message`, `status`, `priority`, `notes`, `contacted_at`, `closed_at`, `created_at`, `updated_at`) VALUES
(1, 'Test User', 'test@example.com', '0712345678', 'Commercial Kitchen', 'This is a test enquiry', 'closed', 'medium', NULL, NULL, NULL, '2026-06-17 16:33:54', '2026-06-17 16:33:54');

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_replies`
--

CREATE TABLE `enquiry_replies` (
  `id` int(11) NOT NULL,
  `enquiry_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `reply` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `media_type` enum('image','video') DEFAULT 'image',
  `file_path` varchar(500) NOT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `video_embed_code` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery_categories`
--

CREATE TABLE `gallery_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hero_slides`
--

CREATE TABLE `hero_slides` (
  `id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(20) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(10) DEFAULT NULL,
  `mpesa_channel` varchar(10) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempt_time`) VALUES
(1, 'vickiekaran254@gmail.com', '::1', '2026-06-20 18:13:54'),
(2, 'vickiekaran254@gmail.com', '::1', '2026-06-20 18:19:27'),
(3, 'dazuai01@gmail.com', '::1', '2026-06-21 00:54:05'),
(4, 'vickiekaran254@gmail.com', '::1', '2026-06-21 20:43:33'),
(5, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:08:32'),
(6, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:08:38'),
(7, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:08:54'),
(8, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:09:00'),
(9, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:13:15'),
(10, 'njugunavickie7@gmail.com', '154.159.252.1', '2026-06-23 11:13:34'),
(11, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:20:27'),
(12, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:20:33'),
(13, 'lucsela@gmail.com', '154.159.252.1', '2026-06-23 12:15:11'),
(14, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:08:48'),
(15, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:08:52'),
(16, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-23 13:09:39'),
(17, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:17:05'),
(18, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:17:20'),
(19, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:17:45'),
(20, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:35:24'),
(21, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-26 12:01:55'),
(22, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-26 12:02:12'),
(23, 'lucsela@gmail.com', '102.213.179.43', '2026-06-26 12:12:38'),
(24, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:07'),
(25, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:17'),
(26, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:28'),
(27, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:41'),
(28, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:41:05'),
(29, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:55:51'),
(30, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:55:59'),
(31, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:56:21'),
(32, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:56:40'),
(33, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-30 15:30:31'),
(34, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-30 15:30:42'),
(35, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-02 08:11:11'),
(36, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-02 08:11:22'),
(37, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-02 08:20:34'),
(38, 'Lucsela@gmail.com', '102.213.179.43', '2026-07-02 08:23:50'),
(39, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-02 13:30:44'),
(40, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-02 13:30:58'),
(41, 'Lucsela@gmail.com', '102.213.179.43', '2026-07-02 14:08:43'),
(42, 'Lucsela@gmail.com', '102.213.179.43', '2026-07-02 14:14:05'),
(43, 'jblsduniq@gmail.com', '102.213.179.43', '2026-07-02 15:04:25'),
(44, 'karanjav494@gmail.com', '197.254.8.98', '2026-07-08 07:25:58'),
(45, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 13:33:22'),
(46, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 14:24:58'),
(47, '', '102.213.179.43', '2026-07-09 14:52:52'),
(48, '', '102.213.179.43', '2026-07-09 14:53:09'),
(49, '', '102.213.179.43', '2026-07-09 14:53:33'),
(50, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 14:55:26'),
(51, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 15:12:47'),
(52, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 15:36:13'),
(53, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 15:36:30'),
(54, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 15:54:20'),
(55, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 15:54:38'),
(56, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 15:55:07'),
(57, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 15:55:23'),
(58, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 16:00:30'),
(59, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 16:00:49'),
(60, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 16:01:03'),
(61, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 16:01:24'),
(62, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 16:01:26'),
(63, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 16:02:27'),
(64, 'Lagrics123@gmail.com', '102.213.179.43', '2026-07-09 16:02:42'),
(65, 'karanjav494@gmail.com', '41.90.193.213', '2026-07-16 17:30:24'),
(66, 'lucsela@gmail.com', '105.160.116.161', '2026-07-21 08:46:24'),
(67, 'lucsela@gmail.com', '105.160.116.161', '2026-07-21 08:46:38');

-- --------------------------------------------------------

--
-- Table structure for table `login_otps`
--

CREATE TABLE `login_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `code_hash` varchar(255) NOT NULL,
  `purpose` varchar(32) NOT NULL DEFAULT 'login_2fa',
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `max_attempts` tinyint(4) NOT NULL DEFAULT 5,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_otps`
--

INSERT INTO `login_otps` (`id`, `user_id`, `tenant_id`, `code_hash`, `purpose`, `attempts`, `max_attempts`, `expires_at`, `consumed_at`, `ip`, `created_at`) VALUES
(1, 2, 2, '$2y$10$/izm8xNQ.sRUm7bSXuoyX.pX.IZnJxf29Pi/yL3/JJpmH7TW68.oO', 'login_2fa', 1, 5, '2026-06-20 14:49:20', '2026-06-20 17:40:00', '::1', '2026-06-20 17:39:20'),
(2, 2, 2, '$2y$10$t78.vPDUmGEZzL9Zl9VqJ.agAzlzE27L9XFzRPjQSyu2TSO1sissO', 'login_2fa', 1, 5, '2026-06-20 18:14:03', '2026-06-20 21:04:24', '::1', '2026-06-20 21:04:03'),
(3, 2, 2, '$2y$10$xH2jABi79Wwpj7hzgRFFxeMhjkg85tFdzr1aSrnvcBvTOS0f/LJ0e', 'login_2fa', 1, 5, '2026-06-21 00:20:06', '2026-06-21 03:10:45', '::1', '2026-06-21 03:10:07'),
(4, 5, 2, '$2y$10$OJPe17ichLQsxGOx5cuKMuFqQE8fFyKSzP3B0NCis7MLwrkdrY8Yu', 'login_2fa', 1, 5, '2026-06-21 00:51:16', '2026-06-21 03:42:00', '::1', '2026-06-21 03:41:16'),
(5, 5, 2, '$2y$10$7.7FAk9qu.qbZ2x.ijb2e.SI4jlZtVPBnOTvs36/7yKvOYK77LYRe', 'login_2fa', 1, 5, '2026-06-21 05:17:25', '2026-06-21 08:07:58', '::1', '2026-06-21 08:07:25'),
(6, 2, 2, '$2y$10$gWmQh2jeOpYaQWJ2g6uf9ecrsXUeIHIVCQPcn.ixkX18E2ON1qIMm', 'login_2fa', 1, 5, '2026-06-21 05:34:08', '2026-06-21 08:25:04', '::1', '2026-06-21 08:24:08'),
(7, 2, 2, '$2y$10$XCq/q4f6vT9RV83.IhikMuNkRB7UtocMMBAC4rkvfdgxVGA8P7b8K', 'login_2fa', 1, 5, '2026-06-21 18:02:43', '2026-06-21 20:53:22', '::1', '2026-06-21 20:52:43'),
(8, 5, 2, '$2y$10$BT2jj..1iaOPoj6D.VjbveWsAnGDZGxTRY.agvwZ6aEF60wNT66lW', 'login_2fa', 1, 5, '2026-06-21 18:53:30', '2026-06-21 21:44:11', '::1', '2026-06-21 21:43:30'),
(9, 2, 2, '$2y$10$V7wTX4XX2gMu.LUwlWjHrO0oOu66da83ru8DDdX4hSmLiI76ngp5e', 'login_2fa', 1, 5, '2026-06-21 22:57:11', '2026-06-22 00:02:08', '127.0.0.1', '2026-06-21 23:47:11'),
(10, 2, 2, '$2y$10$ROGCDUUOHFHr5/.iBWErfO7X2E2lrIuAiPgWUmPl2HaRDbyirSAJG', 'password_reset', 1, 5, '2026-06-23 13:39:53', '2026-06-23 16:30:55', '102.213.179.43', '2026-06-23 16:29:53'),
(11, 7, 2, '$2y$10$r9z7IVAbKkFoeWv/dcb4Xef17lZ36A3/SztFRnbN4dItaSzN6EM1G', 'password_reset', 1, 5, '2026-06-23 13:43:25', '2026-06-23 16:34:56', '102.213.179.43', '2026-06-23 16:33:25'),
(12, 9, 2, '$2y$10$3XsxE6CFE8/MBzzZE/tJRuYzKAr4PurtNBegf0oZuVmTH9eMv/Mh6', 'password_reset', 1, 5, '2026-06-24 10:29:21', '2026-06-24 13:20:21', '197.254.8.98', '2026-06-24 13:19:21'),
(13, 10, 2, '$2y$10$SYxDN4htLC1Mf1KG0O8x4OMzcXa7EbcpVqhBX5DeluWGTCHKOQDc6', 'password_reset', 1, 5, '2026-06-30 09:06:55', '2026-06-30 11:57:40', '197.254.8.98', '2026-06-30 11:56:56'),
(14, 8, 2, '$2y$10$VmJZHPFcrxbBMYcf6cbWv.piWCl8rVi0cWxXpxboCzTh6Snp1lSu.', 'password_reset', 1, 5, '2026-06-30 15:40:56', '2026-06-30 18:32:13', '102.213.179.43', '2026-06-30 18:30:56');

-- --------------------------------------------------------

--
-- Table structure for table `page_headers`
--

CREATE TABLE `page_headers` (
  `id` int(10) UNSIGNED NOT NULL,
  `page_key` varchar(60) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) NOT NULL DEFAULT 'piece',
  `buying_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `retail_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`colors`)),
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sizes`)),
  `image_path` varchar(255) DEFAULT NULL,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `low_stock_notified_at` datetime DEFAULT NULL,
  `status` enum('active','draft') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `tenant_id`, `category_id`, `subcategory_id`, `name`, `description`, `quantity`, `unit`, `buying_price`, `selling_price`, `wholesale_price`, `retail_price`, `colors`, `sizes`, `image_path`, `low_stock_threshold`, `low_stock_notified_at`, `status`, `created_at`, `updated_at`) VALUES
(4, 2, 3, 2, 'yellow bean 110', NULL, 900.00, 'kg', 94.00, 130.00, 110.00, 130.00, NULL, NULL, NULL, 450, NULL, 'active', '2026-06-23 15:58:11', '2026-07-09 18:34:42'),
(7, 2, 4, NULL, 'white maize', NULL, 16.00, 'kg', 42.00, 50.00, 50.00, 50.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-06-30 18:23:51', '2026-07-02 11:35:52'),
(8, 2, 3, 10, 'yellow bean 120', NULL, 1440.00, 'kg', 100.00, 140.00, 120.00, 140.00, NULL, NULL, NULL, 450, NULL, 'active', '2026-07-02 17:00:35', '2026-07-09 18:33:47'),
(10, 2, 6, 20, 'maize', NULL, 303.70, 'kg', 41.10, 6041.10, 50.00, 6041.10, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-08 10:26:41', '2026-07-09 17:31:24'),
(11, 2, 21, NULL, 'biryani rice', NULL, 136.00, 'kg', 85.60, 120.00, 100.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:28:58', '2026-07-09 15:28:58'),
(12, 2, 19, NULL, 'maha', NULL, 195.15, 'kg', 124.00, 150.00, 135.00, 150.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:32:14', '2026-07-09 17:26:23'),
(13, 2, 20, NULL, 'sindano', NULL, 126.00, 'kg', 131.20, 160.00, 140.00, 160.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:39:29', '2026-07-09 15:39:29'),
(14, 2, 18, NULL, 'pishori Tz', NULL, 81.70, 'piece', 100.00, 150.00, 130.00, 150.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:41:59', '2026-07-09 15:41:59'),
(15, 2, 17, NULL, 'pishori mwea', NULL, 293.40, 'kg', 150.00, 180.00, 160.00, 180.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:44:03', '2026-07-09 17:07:48'),
(16, 2, 14, NULL, 'split peas', NULL, 34.85, 'kg', 85.00, 160.00, 130.00, 160.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:56:08', '2026-07-09 15:56:08'),
(17, 2, 23, NULL, 'popcorn', NULL, 22.00, 'kg', 160.00, 220.00, 200.00, 220.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:57:01', '2026-07-09 15:57:01'),
(18, 2, 8, NULL, 'simsim', NULL, 36.70, 'kg', 180.00, 240.00, 220.00, 240.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 15:58:26', '2026-07-09 15:58:26'),
(19, 2, 7, 22, 'njugu red big', NULL, 76.50, 'kg', 190.00, 240.00, 220.00, 240.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:02:04', '2026-07-09 17:07:48'),
(20, 2, 7, 23, 'njugu red small', NULL, 47.70, 'kg', 200.00, 240.00, 220.00, 240.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:04:39', '2026-07-09 16:04:39'),
(21, 2, 13, NULL, 'njahi', NULL, 80.80, 'kg', 72.20, 120.00, 90.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:09:08', '2026-07-09 19:11:10'),
(22, 2, 4, 19, 'nylon', NULL, 179.95, 'kg', 100.00, 140.00, 125.00, 140.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:11:23', '2026-07-09 16:11:23'),
(23, 2, 4, 18, 'Makueni', NULL, 257.50, 'kg', 110.00, 150.00, 130.00, 150.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:15:04', '2026-07-09 18:58:37'),
(24, 2, 11, NULL, 'minji', NULL, 93.55, 'kg', 120.00, 150.00, 140.00, 150.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:16:48', '2026-07-09 17:58:26'),
(25, 2, 9, NULL, 'kamande', NULL, 188.45, 'kg', 145.00, 200.00, 170.00, 200.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:21:13', '2026-07-09 18:58:37'),
(26, 2, 16, NULL, 'kunde white', NULL, 77.00, 'kg', 88.80, 120.00, 110.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:26:46', '2026-07-09 16:26:46'),
(27, 2, 10, NULL, 'mbaazi', NULL, 158.50, 'kg', 83.30, 120.00, 100.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:28:01', '2026-07-09 16:28:01'),
(28, 2, 3, 13, 'Rosecoco', NULL, 16.95, 'kg', 94.40, 150.00, 120.00, 150.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:29:10', '2026-07-09 17:07:48'),
(29, 2, 3, 12, 'Nyayo', NULL, 73.10, 'kg', 83.30, 120.00, 110.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:31:11', '2026-07-09 18:01:45'),
(30, 2, 12, NULL, 'muthokoi', NULL, 0.00, 'kg', 55.00, 80.00, 70.00, 80.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:32:47', '2026-07-09 17:07:48'),
(31, 2, 3, 14, 'Mwitemania', NULL, 30.40, 'kg', 77.70, 120.00, 110.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 16:39:06', '2026-07-09 16:39:06'),
(32, 2, 3, 9, 'yellow bean 1', NULL, 211.30, 'kg', 94.40, 140.00, 120.00, 140.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 17:18:06', '2026-07-09 19:03:34'),
(33, 2, 3, 10, 'yellow bean 2', NULL, 42.40, 'kg', 92.20, 130.00, 110.00, 130.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 17:19:04', '2026-07-09 18:58:37'),
(34, 2, 3, 11, 'wairimu', NULL, 202.60, 'kg', 74.40, 120.00, 90.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 17:21:34', '2026-07-09 17:21:34'),
(35, 2, 15, NULL, 'kunde red', NULL, 100.20, 'kg', 83.30, 120.00, 100.00, 120.00, NULL, NULL, NULL, 10, NULL, 'active', '2026-07-09 17:23:46', '2026-07-09 17:23:46');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `small_title` varchar(100) NOT NULL,
  `major_title` varchar(200) NOT NULL,
  `project_slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_categories`
--

CREATE TABLE `project_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_slug` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `project_gallery`
--

CREATE TABLE `project_gallery` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_title` varchar(100) DEFAULT NULL,
  `image_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_tags`
--

CREATE TABLE `project_tags` (
  `id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  `tag_slug` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_videos`
--

CREATE TABLE `project_videos` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `video_title` varchar(200) DEFAULT NULL,
  `video_url` varchar(500) NOT NULL,
  `video_embed_code` text DEFAULT NULL,
  `video_type` enum('youtube','vimeo','local','other') DEFAULT 'youtube',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `scope` enum('platform','tenant') NOT NULL DEFAULT 'tenant',
  `capabilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`capabilities`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `scope`, `capabilities`, `created_at`) VALUES
(1, 'superadmin', 'platform', '[\"*\"]', '2026-06-17 16:29:32'),
(2, 'admin', 'tenant', '[\"inventory.view\", \"inventory.edit\", \"stock.enter\", \"sales.record\", \"sales.view\", \"customers.manage\", \"catalogue.send\", \"reports.view\", \"staff.manage\", \"settings.manage\", \"billing.manage\"]', '2026-06-17 16:29:32'),
(3, 'user', 'tenant', '[\"inventory.view\", \"sales.record\", \"sales.view\"]', '2026-06-17 16:29:32'),
(4, 'platform_admin', 'platform', '[\"*\"]', '2026-06-20 05:10:12'),
(5, 'tenant_owner', 'tenant', '[\"inventory.view\", \"inventory.edit\", \"stock.enter\", \"sales.record\", \"sales.view\", \"customers.manage\", \"catalogue.send\", \"reports.view\", \"branches.manage\", \"staff.manage\", \"settings.manage\", \"billing.manage\"]', '2026-06-20 05:10:14'),
(6, 'staff', 'tenant', '[\"inventory.view\", \"sales.record\", \"sales.view\"]', '2026-06-20 05:10:16');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `sale_type` enum('retail','wholesale') NOT NULL DEFAULT 'retail',
  `receipt_number` varchar(32) NOT NULL,
  `payment_method` enum('cash','mpesa','split','credit') NOT NULL DEFAULT 'cash',
  `mpesa_channel` varchar(10) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_given` decimal(12,2) DEFAULT NULL,
  `change_given` decimal(12,2) DEFAULT NULL,
  `cash_amount` decimal(12,2) DEFAULT NULL,
  `mpesa_amount` decimal(12,2) DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `status` enum('completed','voided') NOT NULL DEFAULT 'completed',
  `payment_status` enum('pending','paid','part_paid','credit','failed') NOT NULL DEFAULT 'paid',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `tenant_id`, `branch_id`, `staff_id`, `sale_type`, `receipt_number`, `payment_method`, `mpesa_channel`, `total`, `subtotal`, `discount_amount`, `amount_paid`, `amount_due`, `amount_given`, `change_given`, `cash_amount`, `mpesa_amount`, `customer_name`, `customer_phone`, `customer_email`, `status`, `payment_status`, `created_at`) VALUES
(1, 2, 1, 5, 'retail', 'RCP-000001', 'mpesa', NULL, 350.00, 350.00, 0.00, 0.00, 0.00, 350.00, 0.00, NULL, NULL, NULL, NULL, 'vickiekaran254@gmail.com', 'completed', 'paid', '2026-06-21 21:45:09'),
(2, 2, 1, 7, 'retail', 'RCP-000002', 'mpesa', NULL, 120.00, 120.00, 0.00, 0.00, 0.00, 120.00, 0.00, NULL, NULL, 'Victor Karanja', NULL, 'vickiekaran254@gmail.com', 'completed', 'paid', '2026-06-23 16:03:03'),
(3, 2, 2, 8, 'retail', 'RCP-000003', 'mpesa', NULL, 600.00, 600.00, 0.00, 0.00, 0.00, 600.00, 0.00, NULL, NULL, 'LUCY', NULL, NULL, 'completed', 'paid', '2026-06-23 16:12:45'),
(4, 2, 2, 8, 'retail', 'RCP-000004', 'mpesa', 'direct', 120.00, 120.00, 0.00, 0.00, 0.00, 120.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-06-30 09:02:51'),
(5, 2, 2, 10, 'retail', 'RCP-000005', 'cash', NULL, 150.00, 150.00, 0.00, 0.00, 0.00, 200.00, 50.00, NULL, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-06-30 11:58:55'),
(6, 2, 2, 10, 'retail', 'RCP-000006', 'mpesa', 'direct', 270.00, 270.00, 0.00, 0.00, 0.00, 270.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-06-30 18:20:51'),
(7, 2, 2, 8, 'retail', 'RCP-000007', 'mpesa', 'direct', 100.00, 100.00, 0.00, 0.00, 0.00, 100.00, 0.00, NULL, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-06-30 18:34:24'),
(8, 2, 2, 10, 'retail', 'RCP-000008', 'split', NULL, 40.00, 50.00, 10.00, 0.00, 0.00, 30.00, 0.00, 30.00, 10.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-01 09:56:48'),
(9, 2, 2, 8, 'retail', 'RCP-000009', 'split', NULL, 50.00, 50.00, 0.00, 0.00, 0.00, 20.00, 0.00, 20.00, 30.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-02 11:12:45'),
(10, 2, 2, 8, 'wholesale', 'RCP-000010', 'split', NULL, 345.00, 360.00, 15.00, 0.00, 0.00, 200.00, 0.00, 200.00, 145.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-02 11:22:16'),
(11, 2, 2, 8, 'wholesale', 'RCP-000011', 'mpesa', NULL, 500.00, 500.00, 0.00, 0.00, 0.00, 500.00, 0.00, NULL, 500.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-02 11:27:57'),
(12, 2, 2, 8, 'retail', 'RCP-000012', 'split', NULL, 500.00, 500.00, 0.00, 0.00, 0.00, 500.00, 0.00, 500.00, NULL, 'goodwill', NULL, NULL, 'completed', 'paid', '2026-07-02 11:30:07'),
(13, 2, 2, 8, 'retail', 'RCP-000013', 'cash', NULL, 250.00, 250.00, 0.00, 0.00, 0.00, 300.00, 50.00, 250.00, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-02 11:33:14'),
(14, 2, 2, 8, 'wholesale', 'RCP-000014', 'split', NULL, 2000.00, 2250.00, 250.00, 0.00, 0.00, 1500.00, 0.00, 1500.00, 500.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-02 11:35:52'),
(15, 2, 2, 8, 'wholesale', 'RCP-000015', 'mpesa', NULL, 840.00, 840.00, 0.00, 0.00, 0.00, 840.00, 0.00, NULL, 840.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-02 17:05:03'),
(16, 2, 2, 8, 'wholesale', 'RCP-000016', 'mpesa', NULL, 840.00, 840.00, 0.00, 0.00, 0.00, 840.00, 0.00, NULL, 840.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-02 17:26:59'),
(17, 2, 2, 10, 'retail', 'RCP-000017', 'cash', NULL, 35.00, 35.00, 0.00, 0.00, 0.00, 50.00, 15.00, 35.00, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-03 08:55:53'),
(18, 2, 2, 10, 'retail', 'RCP-000018', 'credit', NULL, 15000.00, 15000.00, 0.00, 0.00, 15000.00, 0.00, 0.00, NULL, NULL, 'Dazu Ai hub', NULL, 'dazuhubs@gmail.com', 'completed', 'credit', '2026-07-08 10:36:46'),
(19, 2, 2, 8, 'retail', 'RCP-000019', 'mpesa', NULL, 90.00, 90.00, 0.00, 90.00, 0.00, 90.00, 0.00, NULL, 90.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 15:45:16'),
(20, 2, 2, 8, 'wholesale', 'RCP-000020', 'cash', NULL, 90.00, 90.00, 0.00, 90.00, 0.00, 90.00, 0.00, 90.00, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 16:22:38'),
(21, 2, 2, 8, 'retail', 'RCP-000021', 'split', NULL, 400.00, 400.00, 0.00, 400.00, 0.00, 200.00, 0.00, 200.00, 200.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 16:34:44'),
(22, 2, 2, 8, 'wholesale', 'RCP-000022', 'cash', NULL, 810.00, 810.00, 0.00, 810.00, 0.00, 810.00, 0.00, 810.00, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 16:41:10'),
(23, 2, 2, 8, 'wholesale', 'RCP-000023', 'mpesa', NULL, 930.00, 930.00, 0.00, 930.00, 0.00, 930.00, 0.00, NULL, 930.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 16:52:10'),
(24, 2, 2, 8, 'wholesale', 'RCP-000024', 'mpesa', NULL, 690.00, 690.00, 0.00, 690.00, 0.00, 690.00, 0.00, NULL, 690.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 17:00:57'),
(25, 2, 2, 8, 'wholesale', 'RCP-000025', 'cash', NULL, 10470.50, 10470.50, 0.00, 10470.50, 0.00, 10471.00, 0.50, 10470.50, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 17:07:48'),
(26, 2, 2, 8, 'retail', 'RCP-000026', 'cash', NULL, 450.00, 450.00, 0.00, 450.00, 0.00, 1000.00, 550.00, 450.00, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 17:26:23'),
(27, 2, 2, 8, 'retail', 'RCP-000027', 'cash', NULL, 100.00, 100.00, 0.00, 100.00, 0.00, 100.00, 0.00, 100.00, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 17:57:34'),
(28, 2, 2, 8, 'retail', 'RCP-000028', 'cash', NULL, 150.00, 150.00, 0.00, 150.00, 0.00, 1000.00, 850.00, 150.00, NULL, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 17:58:25'),
(29, 2, 2, 8, 'wholesale', 'RCP-000029', 'credit', NULL, 8000.00, 9900.00, 1900.00, 0.00, 8000.00, 0.00, 0.00, NULL, NULL, 'dama', NULL, NULL, 'completed', 'credit', '2026-07-09 18:01:45'),
(30, 2, 2, 8, 'wholesale', 'RCP-000030', 'mpesa', NULL, 660.00, 660.00, 0.00, 660.00, 0.00, 660.00, 0.00, NULL, 660.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 18:38:09'),
(31, 2, 2, 8, 'wholesale', 'RCP-000031', 'mpesa', NULL, 1320.00, 1320.00, 0.00, 1320.00, 0.00, 1320.00, 0.00, NULL, 1320.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 18:40:29'),
(32, 2, 2, 8, 'wholesale', 'RCP-000032', 'mpesa', NULL, 1440.00, 1440.00, 0.00, 1440.00, 0.00, 1440.00, 0.00, NULL, 1440.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 18:43:01'),
(33, 2, 2, 8, 'retail', 'RCP-000033', 'mpesa', NULL, 460.00, 480.00, 20.00, 460.00, 0.00, 460.00, 0.00, NULL, 460.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 18:58:37'),
(34, 2, 2, 8, 'wholesale', 'RCP-000034', 'mpesa', NULL, 720.00, 720.00, 0.00, 720.00, 0.00, 720.00, 0.00, NULL, 720.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 19:03:34'),
(35, 2, 2, 8, 'retail', 'RCP-000035', 'mpesa', NULL, 50.00, 60.00, 10.00, 50.00, 0.00, 50.00, 0.00, NULL, 50.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 19:11:07'),
(36, 2, 2, 8, 'retail', 'RCP-000036', 'mpesa', NULL, 50.00, 60.00, 10.00, 50.00, 0.00, 50.00, 0.00, NULL, 50.00, NULL, NULL, NULL, 'completed', 'paid', '2026-07-09 19:11:10');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(160) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'piece',
  `unit_price` decimal(12,2) NOT NULL,
  `price_type` enum('retail','wholesale') NOT NULL DEFAULT 'retail',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `product_name`, `unit`, `unit_price`, `price_type`, `unit_cost`, `quantity`, `line_total`) VALUES
(1, 2, 1, 2, 'coca soda', 'piece', 350.00, 'retail', 0.00, 1.00, 350.00),
(2, 2, 2, 4, 'yellow bean', 'kg', 120.00, 'retail', 111.00, 1.00, 120.00),
(3, 2, 3, 4, 'yellow bean', 'kg', 120.00, 'retail', 111.00, 5.00, 600.00),
(4, 2, 4, 4, 'yellow bean', 'kg', 120.00, 'retail', 111.00, 1.00, 120.00),
(5, 2, 5, 6, 'delete on sight', 'piece', 150.00, 'retail', 50.00, 1.00, 150.00),
(6, 2, 6, 4, 'yellow bean', 'kg', 120.00, 'retail', 111.00, 1.00, 120.00),
(7, 2, 6, 6, 'delete on sight', 'piece', 150.00, 'retail', 50.00, 1.00, 150.00),
(8, 2, 7, 7, 'white maize', 'kg', 50.00, 'retail', 42.00, 2.00, 100.00),
(9, 2, 8, 7, 'white maize', 'kg', 50.00, 'retail', 0.00, 1.00, 50.00),
(10, 2, 9, 7, 'white maize', 'kg', 50.00, 'retail', 0.00, 1.00, 50.00),
(11, 2, 10, 4, 'yellow bean 1', 'kg', 120.00, 'wholesale', 0.00, 3.00, 360.00),
(12, 2, 11, 7, 'white maize', 'kg', 50.00, 'wholesale', 0.00, 10.00, 500.00),
(13, 2, 12, 7, 'white maize', 'kg', 50.00, 'retail', 0.00, 10.00, 500.00),
(14, 2, 13, 7, 'white maize', 'kg', 50.00, 'retail', 0.00, 5.00, 250.00),
(15, 2, 14, 7, 'white maize', 'kg', 50.00, 'wholesale', 0.00, 45.00, 2250.00),
(16, 2, 15, 5, 'YELLOW BEAN', 'kg', 120.00, 'wholesale', 0.00, 7.00, 840.00),
(17, 2, 16, 5, 'YELLOW BEAN', 'kg', 120.00, 'wholesale', 0.00, 7.00, 840.00),
(18, 2, 17, 5, 'YELLOW BEAN', 'kg', 140.00, 'retail', 0.00, 0.25, 35.00),
(19, 2, 18, 10, 'DELETE ON SIGHT', 'kg', 150.00, 'retail', 0.00, 100.00, 15000.00),
(20, 2, 19, 15, 'pishori mwea', 'kg', 180.00, 'retail', 0.00, 0.50, 90.00),
(21, 2, 20, 21, 'njahi', 'kg', 90.00, 'wholesale', 0.00, 1.00, 90.00),
(22, 2, 21, 25, 'kamande', 'kg', 200.00, 'retail', 0.00, 2.00, 400.00),
(23, 2, 22, 12, 'maha', 'kg', 135.00, 'wholesale', 0.00, 6.00, 810.00),
(24, 2, 23, 12, 'maha', 'kg', 135.00, 'wholesale', 0.00, 1.00, 135.00),
(25, 2, 23, 24, 'minji', 'kg', 140.00, 'wholesale', 0.00, 3.00, 420.00),
(26, 2, 23, 29, 'Nyayo', 'kg', 110.00, 'wholesale', 0.00, 1.50, 165.00),
(27, 2, 23, 30, 'muthokoi', 'kg', 70.00, 'wholesale', 0.00, 3.00, 210.00),
(28, 2, 24, 24, 'minji', 'kg', 140.00, 'wholesale', 0.00, 1.00, 140.00),
(29, 2, 24, 29, 'Nyayo', 'kg', 110.00, 'wholesale', 0.00, 5.00, 550.00),
(30, 2, 25, 15, 'pishori mwea', 'kg', 160.00, 'wholesale', 0.00, 15.00, 2400.00),
(31, 2, 25, 19, 'njugu red big', 'kg', 220.00, 'wholesale', 0.00, 10.00, 2200.00),
(32, 2, 25, 25, 'kamande', 'kg', 170.00, 'wholesale', 0.00, 20.00, 3400.00),
(33, 2, 25, 28, 'Rosecoco', 'kg', 120.00, 'wholesale', 0.00, 10.00, 1200.00),
(34, 2, 25, 30, 'muthokoi', 'kg', 70.00, 'wholesale', 0.00, 18.15, 1270.50),
(35, 2, 26, 12, 'maha', 'kg', 150.00, 'retail', 0.00, 3.00, 450.00),
(36, 2, 27, 25, 'kamande', 'kg', 200.00, 'retail', 0.00, 0.50, 100.00),
(37, 2, 28, 24, 'minji', 'kg', 150.00, 'retail', 0.00, 1.00, 150.00),
(38, 2, 29, 29, 'Nyayo', 'kg', 110.00, 'wholesale', 0.00, 90.00, 9900.00),
(39, 2, 30, 33, 'yellow bean 2', 'kg', 110.00, 'wholesale', 0.00, 6.00, 660.00),
(40, 2, 31, 33, 'yellow bean 2', 'kg', 110.00, 'wholesale', 0.00, 12.00, 1320.00),
(41, 2, 32, 32, 'yellow bean 1', 'kg', 120.00, 'wholesale', 0.00, 12.00, 1440.00),
(42, 2, 33, 23, 'Makueni', 'kg', 150.00, 'retail', 0.00, 1.00, 150.00),
(43, 2, 33, 25, 'kamande', 'kg', 200.00, 'retail', 0.00, 1.00, 200.00),
(44, 2, 33, 33, 'yellow bean 2', 'kg', 130.00, 'retail', 0.00, 1.00, 130.00),
(45, 2, 34, 32, 'yellow bean 1', 'kg', 120.00, 'wholesale', 0.00, 6.00, 720.00),
(46, 2, 35, 21, 'njahi', 'kg', 120.00, 'retail', 0.00, 0.50, 60.00),
(47, 2, 36, 21, 'njahi', 'kg', 120.00, 'retail', 0.00, 0.50, 60.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) NOT NULL DEFAULT 'cash',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_benefits`
--

CREATE TABLE `service_benefits` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `benefit_title` varchar(200) NOT NULL,
  `benefit_description` text DEFAULT NULL,
  `icon_class` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_faqs`
--

CREATE TABLE `service_faqs` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `question` varchar(300) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_gallery`
--

CREATE TABLE `service_gallery` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_title` varchar(100) DEFAULT NULL,
  `image_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_sections`
--

CREATE TABLE `service_sections` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video') DEFAULT 'text_only',
  `title` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video','youtube','vimeo') DEFAULT 'image',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('logo_alt', 'Ismano', '2026-06-17 16:32:07'),
('logo_path', NULL, '2026-06-17 16:32:07'),
('site_name', 'Ismano', '2026-06-17 16:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `store_categories`
--

CREATE TABLE `store_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_categories`
--

INSERT INTO `store_categories` (`id`, `name`, `slug`, `description`, `image_path`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Electronics', 'electronics', 'Electronic devices and gadgets', NULL, 1, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(2, 'Clothing', 'clothing', 'Fashion and apparel', NULL, 2, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(3, 'Books', 'books', 'Books and publications', NULL, 3, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(4, 'Home & Living', 'home-living', 'Home decor and living essentials', NULL, 4, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(5, 'Sports', 'sports', 'Sports equipment and gear', NULL, 5, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22');

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('active','draft') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `tenant_id`, `category_id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(2, 2, 3, 'YELLOW BEAN', 'active', '2026-06-23 15:03:06', '2026-07-02 17:14:51'),
(9, 2, 3, 'yellow bean 1', 'active', '2026-07-02 16:37:26', '2026-07-02 16:37:26'),
(10, 2, 3, 'yellow bean 2', 'active', '2026-07-02 16:37:56', '2026-07-02 16:37:56'),
(11, 2, 3, 'wairimu', 'active', '2026-07-02 16:38:10', '2026-07-02 16:38:10'),
(12, 2, 3, 'Nyayo', 'active', '2026-07-02 16:38:26', '2026-07-02 16:38:26'),
(13, 2, 3, 'Rosecoco', 'active', '2026-07-02 16:38:35', '2026-07-02 16:38:35'),
(14, 2, 3, 'Mwitemania', 'active', '2026-07-02 16:38:51', '2026-07-02 16:38:51'),
(15, 2, 3, 'Army Green', 'active', '2026-07-02 16:39:31', '2026-07-02 16:39:31'),
(18, 2, 4, 'Makueni', 'active', '2026-07-02 16:40:46', '2026-07-02 16:40:46'),
(19, 2, 4, 'nylon', 'active', '2026-07-02 16:41:07', '2026-07-02 16:41:07'),
(20, 2, 6, 'white maize', 'active', '2026-07-02 16:41:35', '2026-07-02 16:41:35'),
(21, 2, 6, 'yellow maize', 'active', '2026-07-02 16:41:49', '2026-07-02 16:41:49'),
(22, 2, 7, 'njugu red big', 'active', '2026-07-02 16:42:38', '2026-07-02 16:43:06'),
(23, 2, 7, 'njugu red small', 'active', '2026-07-02 16:42:51', '2026-07-02 16:42:51'),
(24, 2, 9, 'small', 'active', '2026-07-02 16:43:45', '2026-07-02 16:43:45'),
(25, 2, 9, 'big', 'active', '2026-07-02 16:43:50', '2026-07-02 16:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `status` enum('active','suspended','cancelled') NOT NULL DEFAULT 'active',
  `logo_path` varchar(255) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'KES',
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `receipt_footer` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `slug`, `owner_user_id`, `status`, `logo_path`, `currency`, `phone`, `address`, `receipt_footer`, `created_at`, `updated_at`) VALUES
(1, 'Test Sample shop', 'test-shop', 1, 'active', '/public/uploads/branding/tenant_1_f81d559e.png', 'KES', '', '', 'Thankyou for shopping with Us', '2026-06-20 14:32:08', '2026-06-20 14:37:51'),
(2, 'Lucsela', 'lucsela-pos', 2, 'active', NULL, 'KES', NULL, NULL, NULL, '2026-06-20 14:41:17', '2026-06-23 14:52:47'),
(3, 'Dazu Shop', 'dazu-shop', 3, 'active', NULL, 'KES', NULL, NULL, NULL, '2026-06-20 15:00:11', '2026-06-20 15:00:11'),
(4, 'Dazu Shop', 'dazu-shop-2', 4, 'active', NULL, 'KES', NULL, NULL, NULL, '2026-06-20 15:06:26', '2026-06-20 15:06:27');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_initial` varchar(5) DEFAULT NULL,
  `rating` int(11) DEFAULT 5,
  `testimonial_text` text NOT NULL,
  `service_tag` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `customer_name`, `customer_email`, `customer_phone`, `customer_initial`, `rating`, `testimonial_text`, `service_tag`, `role`, `status`, `is_featured`, `sort_order`, `created_at`, `updated_at`, `approved_at`) VALUES
(1, 'James Mwangi', NULL, NULL, 'J', 5, 'ISMAN designed and installed our 450 sqm hotel kitchen in under 8 weeks. The SS304 fabrication quality exceeded international standards, and their team worked around our operational hours without a single disruption to guests.', 'Commercial Kitchen', 'General Manager, Radisson Blu Nairobi', 'approved', 1, 0, '2026-06-17 16:34:00', '2026-06-17 16:34:00', NULL),
(2, 'Aisha Noor', NULL, NULL, 'A', 5, 'The stainless balustrade work at Two Rivers was flawless. Precision welds, perfect alignment across three floors, and delivered ahead of schedule. We have used them on every project since.', 'Stainless Railing', 'Project Lead, Centum Investment', 'approved', 1, 0, '2026-06-17 16:34:00', '2026-06-17 16:34:00', NULL),
(3, 'Dr. Peter Otieno', NULL, NULL, 'P', 5, 'Their hospital fit-out met every infection-control requirement we set. Documentation was thorough and the finish on the SS316 surfaces is exactly what a sterile environment needs.', 'Hospital Fit-out', 'Facilities Director, Kenyatta National Hospital', 'approved', 1, 0, '2026-06-17 16:34:00', '2026-06-17 16:34:00', NULL),
(4, 'Grace Wambui', NULL, NULL, 'G', 5, 'We commissioned a full processing line and ISMAN handled design, fabrication and install end to end. HACCP-ready, on budget, and running at full throughput from day one.', 'Food Processing', 'Operations Manager, Brookside Dairy', 'approved', 1, 0, '2026-06-17 16:34:00', '2026-06-17 16:34:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_reset_password` tinyint(1) NOT NULL DEFAULT 0,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `activation_token` varchar(64) DEFAULT NULL,
  `activation_expires` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `branch_id`, `username`, `email`, `password_hash`, `must_reset_password`, `role_id`, `is_active`, `email_verified`, `activation_token`, `activation_expires`, `activated_at`, `created_at`, `updated_at`) VALUES
(2, 2, NULL, 'Lucsela', 'lucsela@gmail.com', '$2y$10$dzUF.Q/AQxn/NJGDZRuPu.ZcezMnNmfnlCdiktEvBieCpmYTuZhVy', 0, 5, 1, 1, NULL, NULL, '2026-06-20 11:41:44', '2026-06-20 11:41:17', '2026-06-23 13:30:55'),
(7, 2, 1, 'Admin', 'jblsduniq@gmail.com', '$2y$10$YNfZEk77G/IaW0sezNCQ4.rLoXGEFIC6RMXlRyG2CwvfWZDcQUmca', 0, 5, 1, 1, NULL, NULL, NULL, '2026-06-23 12:18:50', '2026-06-23 13:34:56'),
(8, 2, 2, 'Lucy', 'lagrics123@gmail.com', '$2y$10$wHfjX9mO0k/KZEmW/gynkuBfHpoNuIc4OMrFtqhhHBF6ROa/M8HIi', 0, 6, 1, 1, NULL, NULL, NULL, '2026-06-23 13:05:51', '2026-06-30 15:32:13'),
(9, 2, NULL, 'VickieKaran', 'vickiekaran254@gmail.com', '$2y$10$WXQWB3eoadb2sZS8lNg4f.myGDmqI8iQcFXlVx72WSpHDBYg6gzBO', 0, 5, 1, 1, NULL, NULL, NULL, '2026-06-24 10:18:36', '2026-06-24 10:20:22'),
(10, 2, 2, 'Dazu Ai hub', 'dazuai01@gmail.com', '$2y$10$NkF0ZU9G.0qspiqXc4e75uzTebsHvy5rngFNHpV81k/Z8QaElHexO', 0, 6, 1, 1, NULL, NULL, NULL, '2026-06-30 07:22:42', '2026-06-30 08:57:40');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `capability` varchar(64) NOT NULL,
  `effect` enum('grant','revoke') NOT NULL DEFAULT 'grant',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `phone`, `address`, `created_at`) VALUES
(2, NULL, NULL, '0792248332', NULL, '2026-06-20 11:41:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_tenant_time` (`tenant_id`,`created_at`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_author` (`author_id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_published` (`published_at`),
  ADD KEY `idx_featured` (`is_featured`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog` (`blog_id`);

--
-- Indexes for table `blog_sections`
--
ALTER TABLE `blog_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog` (`blog_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `blog_tags`
--
ALTER TABLE `blog_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `blog_tag_relations`
--
ALTER TABLE `blog_tag_relations`
  ADD PRIMARY KEY (`blog_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_branch_tenant_title` (`tenant_id`,`title`),
  ADD KEY `idx_branch_tenant` (`tenant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_tenant_name` (`tenant_id`,`name`),
  ADD KEY `idx_cat_tenant` (`tenant_id`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_created` (`created_at`);
ALTER TABLE `enquiries` ADD FULLTEXT KEY `idx_search` (`name`,`email`,`message`);

--
-- Indexes for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_enquiry` (`enquiry_id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_media_type` (`media_type`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `gallery_categories`
--
ALTER TABLE `gallery_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hero_active_order` (`is_active`,`sort_order`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_inv_sale` (`sale_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invitem_invoice` (`invoice_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`);

--
-- Indexes for table `login_otps`
--
ALTER TABLE `login_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_user_purpose` (`user_id`,`purpose`),
  ADD KEY `idx_otp_expires` (`expires_at`);

--
-- Indexes for table `page_headers`
--
ALTER TABLE `page_headers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_key` (`page_key`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prod_tenant` (`tenant_id`),
  ADD KEY `idx_prod_cat` (`category_id`),
  ADD KEY `idx_prod_subcat` (`subcategory_id`),
  ADD KEY `idx_prod_status` (`status`),
  ADD KEY `idx_prod_lowstock` (`tenant_id`,`quantity`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_slug` (`project_slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`project_slug`);

--
-- Indexes for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_slug` (`category_slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_slug` (`category_slug`);

--
-- Indexes for table `project_gallery`
--
ALTER TABLE `project_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `project_tags`
--
ALTER TABLE `project_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`),
  ADD UNIQUE KEY `tag_slug` (`tag_slug`);

--
-- Indexes for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sale_receipt` (`tenant_id`,`receipt_number`),
  ADD KEY `idx_sale_tenant` (`tenant_id`),
  ADD KEY `idx_sale_staff` (`staff_id`),
  ADD KEY `idx_sale_branch` (`branch_id`),
  ADD KEY `idx_sale_created` (`tenant_id`,`created_at`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_sale` (`sale_id`),
  ADD KEY `idx_item_tenant` (`tenant_id`);

--
-- Indexes for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale` (`sale_id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `service_benefits`
--
ALTER TABLE `service_benefits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`);

--
-- Indexes for table `service_faqs`
--
ALTER TABLE `service_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`);

--
-- Indexes for table `service_gallery`
--
ALTER TABLE `service_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `service_sections`
--
ALTER TABLE `service_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `store_categories`
--
ALTER TABLE `store_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subcat_tenant_cat_name` (`tenant_id`,`category_id`,`name`),
  ADD KEY `idx_subcat_tenant` (`tenant_id`),
  ADD KEY `idx_subcat_cat` (`category_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_slug` (`slug`),
  ADD KEY `idx_tenant_status` (`status`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_users_tenant` (`tenant_id`),
  ADD KEY `idx_users_activation` (`activation_token`),
  ADD KEY `idx_users_branch` (`branch_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_cap` (`user_id`,`capability`),
  ADD KEY `idx_perm_tenant` (`tenant_id`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_sections`
--
ALTER TABLE `blog_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_tags`
--
ALTER TABLE `blog_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gallery_categories`
--
ALTER TABLE `gallery_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `login_otps`
--
ALTER TABLE `login_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `page_headers`
--
ALTER TABLE `page_headers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_categories`
--
ALTER TABLE `project_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `project_gallery`
--
ALTER TABLE `project_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_tags`
--
ALTER TABLE `project_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_videos`
--
ALTER TABLE `project_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_benefits`
--
ALTER TABLE `service_benefits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_faqs`
--
ALTER TABLE `service_faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_gallery`
--
ALTER TABLE `service_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_sections`
--
ALTER TABLE `service_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_categories`
--
ALTER TABLE `store_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blogs`
--
ALTER TABLE `blogs`
  ADD CONSTRAINT `blogs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blogs_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD CONSTRAINT `blog_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  ADD CONSTRAINT `blog_faqs_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_sections`
--
ALTER TABLE `blog_sections`
  ADD CONSTRAINT `blog_sections_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_tag_relations`
--
ALTER TABLE `blog_tag_relations`
  ADD CONSTRAINT `blog_tag_relations_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  ADD CONSTRAINT `enquiry_replies_ibfk_1` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enquiry_replies_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gallery`
--
ALTER TABLE `gallery`
  ADD CONSTRAINT `gallery_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD CONSTRAINT `project_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_gallery`
--
ALTER TABLE `project_gallery`
  ADD CONSTRAINT `project_gallery_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD CONSTRAINT `project_videos_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_benefits`
--
ALTER TABLE `service_benefits`
  ADD CONSTRAINT `service_benefits_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_faqs`
--
ALTER TABLE `service_faqs`
  ADD CONSTRAINT `service_faqs_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_gallery`
--
ALTER TABLE `service_gallery`
  ADD CONSTRAINT `service_gallery_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_sections`
--
ALTER TABLE `service_sections`
  ADD CONSTRAINT `service_sections_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
