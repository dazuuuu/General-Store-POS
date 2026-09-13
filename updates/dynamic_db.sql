-- Preamble restored: the original export's header was stripped except for
-- its closing restore statements, and the missing FOREIGN_KEY_CHECKS=0 here
-- is what let mysqldump's alphabetical table order (audit_log, blogs, ...
-- users) create tables before the ones they reference. The SQL_LOG_BIN
-- set/restore pair is intentionally left out — shared hosting DB users don't
-- have the SUPER/BINLOG ADMIN privilege it needs, and it isn't required for
-- a plain import.
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `entity_type` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_label` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `changes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_tenant_time` (`tenant_id`,`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,2,9,'VickieKaran','tenant_owner','product',9,'DELETE ON SIGHT','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"10\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"35\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"60\"}]','2026-07-03 06:20:00'),(2,2,9,'VickieKaran','tenant_owner','product',9,'DELETE ON SIGHT','deleted','[{\"field\":\"quantity\",\"label\":\"Stock at deletion\",\"from\":\"100 piece\",\"to\":\"\\u2014\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"KES 60\",\"to\":\"\\u2014\"}]','2026-07-03 06:20:28'),(3,2,9,'VickieKaran','tenant_owner','product',10,'DELETE ON SIGHT','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"400\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"60\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"90\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]','2026-07-08 07:26:41'),(4,2,2,'Lucsela','tenant_owner','product',11,'biryani rice','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"136\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"85.6\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 12:28:58'),(5,2,2,'Lucsela','tenant_owner','product',12,'maha','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"205.15\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"124\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"135\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]','2026-07-09 12:32:14'),(6,2,2,'Lucsela','tenant_owner','product',13,'sindano','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"126\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"131.20\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"140\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"160\"}]','2026-07-09 12:39:29'),(7,2,2,'Lucsela','tenant_owner','product',14,'pishori Tz','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"81.7\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"130\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]','2026-07-09 12:41:59'),(8,2,2,'Lucsela','tenant_owner','product',15,'pishori mwea','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"279.65\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"150\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"160\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"180\"}]','2026-07-09 12:44:03'),(9,2,2,'Lucsela','tenant_owner','product',16,'split peas','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"34.85\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"85\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"130\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"160\"}]','2026-07-09 12:56:08'),(10,2,2,'Lucsela','tenant_owner','product',17,'popcorn','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"22\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"160\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"200\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"220\"}]','2026-07-09 12:57:01'),(11,2,2,'Lucsela','tenant_owner','product',18,'simsim','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"36.70\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"180\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"220\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"240\"}]','2026-07-09 12:58:26'),(12,2,2,'Lucsela','tenant_owner','product',15,'pishori mwea','updated','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"279.15\",\"to\":\"308.40\"}]','2026-07-09 12:59:21'),(13,2,2,'Lucsela','tenant_owner','product',19,'njugu red big','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"86.50\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"190\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"220\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"240\"}]','2026-07-09 13:02:04'),(14,2,2,'Lucsela','tenant_owner','product',20,'njugu red small','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"47.70\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"200\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"220\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"240\"}]','2026-07-09 13:04:39'),(15,2,2,'Lucsela','tenant_owner','product',21,'njahi','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"82.80\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"72.20\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"90\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 13:09:08'),(16,2,2,'Lucsela','tenant_owner','product',22,'nylon','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"179.95\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"125\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"140\"}]','2026-07-09 13:11:23'),(17,2,2,'Lucsela','tenant_owner','product',23,'Makueni','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"258.5\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"130\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]','2026-07-09 13:15:04'),(18,2,2,'Lucsela','tenant_owner','product',24,'minji','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"98.55\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"120\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"140\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]','2026-07-09 13:16:48'),(19,2,2,'Lucsela','tenant_owner','product',25,'kamande','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"211.95\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"145\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"170\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"200\"}]','2026-07-09 13:21:13'),(20,2,2,'Lucsela','tenant_owner','product',26,'kunde white','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"77\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"88.80\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 13:26:46'),(21,2,2,'Lucsela','tenant_owner','product',27,'mbaazi','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"158.50\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"83.30\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 13:28:01'),(22,2,2,'Lucsela','tenant_owner','product',28,'Rosecoco','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"26.95\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"94.40\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"120\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"150\"}]','2026-07-09 13:29:10'),(23,2,2,'Lucsela','tenant_owner','product',29,'Nyayo','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"169.60\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"83.30\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 13:31:11'),(24,2,2,'Lucsela','tenant_owner','product',30,'muthokoi','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"21.15\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"55\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"70\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"80\"}]','2026-07-09 13:32:47'),(25,2,2,'Lucsela','tenant_owner','product',31,'Mwitemania','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"30.40\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"77.70\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 13:39:06'),(26,2,2,'Lucsela','tenant_owner','product',32,'yellow bean 1','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"229.30\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"94.40\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"120\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"140\"}]','2026-07-09 14:18:06'),(27,2,2,'Lucsela','tenant_owner','product',33,'yellow bean 2','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"49.40\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"92.20\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"110\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"130\"}]','2026-07-09 14:19:04'),(28,2,2,'Lucsela','tenant_owner','product',34,'wairimu','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"202.60\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"74.40\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"90\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 14:21:34'),(29,2,2,'Lucsela','tenant_owner','product',35,'kunde red','created','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"\\u2014\",\"to\":\"100.2\"},{\"field\":\"buying_price\",\"label\":\"Buying\",\"from\":\"\\u2014\",\"to\":\"83.3\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale\",\"from\":\"\\u2014\",\"to\":\"100\"},{\"field\":\"retail_price\",\"label\":\"Retail\",\"from\":\"\\u2014\",\"to\":\"120\"}]','2026-07-09 14:23:46'),(30,2,2,'Lucsela','tenant_owner','product',10,'maize','updated','[{\"field\":\"name\",\"label\":\"Name\",\"from\":\"DELETE ON SIGHT\",\"to\":\"maize\"},{\"field\":\"category_id\",\"label\":\"Category\",\"from\":\"BEANS\",\"to\":\"maize\"},{\"field\":\"subcategory_id\",\"label\":\"Subcategory\",\"from\":\"\\u2014\",\"to\":\"white maize\"},{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"300.00\",\"to\":\"303.70\"},{\"field\":\"buying_price\",\"label\":\"Buying price\",\"from\":\"60.00\",\"to\":\"41.1\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale price\",\"from\":\"90.00\",\"to\":\"50\"},{\"field\":\"retail_price\",\"label\":\"Retail price\",\"from\":\"150.00\",\"to\":\"6041.1\"}]','2026-07-09 14:31:24'),(31,2,2,'Lucsela','tenant_owner','product',8,'yellow bean 120','updated','[{\"field\":\"name\",\"label\":\"Name\",\"from\":\"yellow bean 2\",\"to\":\"yellow bean 120\"},{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"1416.65\",\"to\":\"1440\"}]','2026-07-09 15:33:47'),(32,2,2,'Lucsela','tenant_owner','product',4,'yellow bean 110','updated','[{\"field\":\"name\",\"label\":\"Name\",\"from\":\"yellow bean 1\",\"to\":\"yellow bean 110\"},{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"1345.40\",\"to\":\"900\"},{\"field\":\"wholesale_price\",\"label\":\"Wholesale price\",\"from\":\"120.00\",\"to\":\"110\"}]','2026-07-09 15:34:42'),(33,2,2,'Lucsela','tenant_owner','product',33,'yellow bean 2','updated','[{\"field\":\"quantity\",\"label\":\"Stock\",\"from\":\"31.40\",\"to\":\"43.40\"}]','2026-07-09 15:44:17');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_categories`
--

DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `color` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '#667eea',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `created_by` (`created_by`),
  KEY `idx_slug` (`slug`),
  CONSTRAINT `blog_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_categories`
--

LOCK TABLES `blog_categories` WRITE;
/*!40000 ALTER TABLE `blog_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_faqs`
--

DROP TABLE IF EXISTS `blog_faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_faqs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `blog_id` int NOT NULL,
  `question` varchar(300) COLLATE utf8mb4_general_ci NOT NULL,
  `answer` text COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog` (`blog_id`),
  CONSTRAINT `blog_faqs_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_faqs`
--

LOCK TABLES `blog_faqs` WRITE;
/*!40000 ALTER TABLE `blog_faqs` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_faqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_sections`
--

DROP TABLE IF EXISTS `blog_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `blog_id` int NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video','youtube','code_block','quote') COLLATE utf8mb4_general_ci DEFAULT 'text_only',
  `title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_general_ci,
  `media_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `media_type` enum('image','video','youtube') COLLATE utf8mb4_general_ci DEFAULT 'image',
  `video_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog` (`blog_id`),
  KEY `idx_sort` (`sort_order`),
  CONSTRAINT `blog_sections_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_sections`
--

LOCK TABLES `blog_sections` WRITE;
/*!40000 ALTER TABLE `blog_sections` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_tag_relations`
--

DROP TABLE IF EXISTS `blog_tag_relations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_tag_relations` (
  `blog_id` int NOT NULL,
  `tag_id` int NOT NULL,
  PRIMARY KEY (`blog_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `blog_tag_relations_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blog_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_tag_relations`
--

LOCK TABLES `blog_tag_relations` WRITE;
/*!40000 ALTER TABLE `blog_tag_relations` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_tag_relations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_tags`
--

DROP TABLE IF EXISTS `blog_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_tags`
--

LOCK TABLES `blog_tags` WRITE;
/*!40000 ALTER TABLE `blog_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blogs`
--

DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_general_ci,
  `content` longtext COLLATE utf8mb4_general_ci,
  `featured_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `author_id` int NOT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `view_count` int DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `meta_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_general_ci,
  `meta_keywords` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_slug` (`slug`),
  KEY `idx_author` (`author_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_published` (`published_at`),
  KEY `idx_featured` (`is_featured`),
  CONSTRAINT `blogs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blogs_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blogs`
--

LOCK TABLES `blogs` WRITE;
/*!40000 ALTER TABLE `blogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `blogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','draft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_tenant_name` (`tenant_id`,`name`),
  KEY `idx_cat_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (24,2,'Whisky','/assets/uploads/categories/cat_0d143e0ba9b9.jpg','active','2026-08-05 11:13:24','2026-08-05 11:13:24');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enquiries`
--

DROP TABLE IF EXISTS `enquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enquiries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `service` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci,
  `status` enum('new','read','contacted','closed') COLLATE utf8mb4_general_ci DEFAULT 'new',
  `priority` enum('low','medium','high') COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `notes` text COLLATE utf8mb4_general_ci,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`),
  KEY `idx_created` (`created_at`),
  FULLTEXT KEY `idx_search` (`name`,`email`,`message`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enquiries`
--

LOCK TABLES `enquiries` WRITE;
/*!40000 ALTER TABLE `enquiries` DISABLE KEYS */;
INSERT INTO `enquiries` VALUES (1,'Test User','test@example.com','0712345678','Commercial Kitchen','This is a test enquiry','closed','medium',NULL,NULL,NULL,'2026-06-17 16:33:54','2026-06-17 16:33:54');
/*!40000 ALTER TABLE `enquiries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enquiry_replies`
--

DROP TABLE IF EXISTS `enquiry_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enquiry_replies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enquiry_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `reply` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_enquiry` (`enquiry_id`),
  CONSTRAINT `enquiry_replies_ibfk_1` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enquiry_replies_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enquiry_replies`
--

LOCK TABLES `enquiry_replies` WRITE;
/*!40000 ALTER TABLE `enquiry_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `enquiry_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery`
--

DROP TABLE IF EXISTS `gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gallery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `media_type` enum('image','video') COLLATE utf8mb4_general_ci DEFAULT 'image',
  `file_path` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `thumbnail_path` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `video_embed_code` text COLLATE utf8mb4_general_ci,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tags` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `view_count` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_media_type` (`media_type`),
  KEY `idx_sort` (`sort_order`),
  CONSTRAINT `gallery_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery`
--

LOCK TABLES `gallery` WRITE;
/*!40000 ALTER TABLE `gallery` DISABLE KEYS */;
/*!40000 ALTER TABLE `gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery_categories`
--

DROP TABLE IF EXISTS `gallery_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gallery_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery_categories`
--

LOCK TABLES `gallery_categories` WRITE;
/*!40000 ALTER TABLE `gallery_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `gallery_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `held_order_items`
--

DROP TABLE IF EXISTS `held_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `held_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `held_order_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(160) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_helditem_held` (`held_order_id`),
  KEY `idx_helditem_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `held_order_items`
--

LOCK TABLES `held_order_items` WRITE;
/*!40000 ALTER TABLE `held_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `held_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `held_orders`
--

DROP TABLE IF EXISTS `held_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `held_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `staff_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_held_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `held_orders`
--

LOCK TABLES `held_orders` WRITE;
/*!40000 ALTER TABLE `held_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `held_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hero_slides`
--

DROP TABLE IF EXISTS `hero_slides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hero_slides` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hero_active_order` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hero_slides`
--

LOCK TABLES `hero_slides` WRITE;
/*!40000 ALTER TABLE `hero_slides` DISABLE KEYS */;
/*!40000 ALTER TABLE `hero_slides` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invitem_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `branch_id` int DEFAULT NULL,
  `staff_id` int DEFAULT NULL,
  `invoice_number` varchar(20) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(10) DEFAULT NULL,
  `mpesa_channel` varchar(10) DEFAULT NULL,
  `sale_id` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inv_tenant_status` (`tenant_id`,`status`),
  KEY `idx_inv_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_time` (`email`,`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'vickiekaran254@gmail.com','::1','2026-06-20 18:13:54'),(2,'vickiekaran254@gmail.com','::1','2026-06-20 18:19:27'),(3,'dazuai01@gmail.com','::1','2026-06-21 00:54:05'),(4,'vickiekaran254@gmail.com','::1','2026-06-21 20:43:33'),(5,'vickiekaran254@gmail.com','154.159.252.1','2026-06-23 11:08:32'),(6,'vickiekaran254@gmail.com','154.159.252.1','2026-06-23 11:08:38'),(7,'vickiekaran254@gmail.com','154.159.252.1','2026-06-23 11:08:54'),(8,'vickiekaran254@gmail.com','154.159.252.1','2026-06-23 11:09:00'),(9,'vickiekaran254@gmail.com','154.159.252.1','2026-06-23 11:13:15'),(10,'njugunavickie7@gmail.com','154.159.252.1','2026-06-23 11:13:34'),(11,'vickiekaran254@gmail.com','154.159.252.1','2026-06-23 11:20:27'),(12,'vickiekaran254@gmail.com','154.159.252.1','2026-06-23 11:20:33'),(13,'lucsela@gmail.com','154.159.252.1','2026-06-23 12:15:11'),(14,'lucsela@gmail.com','102.213.179.43','2026-06-23 13:08:48'),(15,'lucsela@gmail.com','102.213.179.43','2026-06-23 13:08:52'),(16,'Lagrics123@gmail.com','102.213.179.43','2026-06-23 13:09:39'),(17,'lucsela@gmail.com','102.213.179.43','2026-06-23 13:17:05'),(18,'lucsela@gmail.com','102.213.179.43','2026-06-23 13:17:20'),(19,'lucsela@gmail.com','102.213.179.43','2026-06-23 13:17:45'),(20,'lucsela@gmail.com','102.213.179.43','2026-06-23 13:35:24'),(21,'Lagrics123@gmail.com','102.213.179.43','2026-06-26 12:01:55'),(22,'Lagrics123@gmail.com','102.213.179.43','2026-06-26 12:02:12'),(23,'lucsela@gmail.com','102.213.179.43','2026-06-26 12:12:38'),(24,'dazuai01@gmail.com','197.254.8.98','2026-06-30 07:15:07'),(25,'dazuai01@gmail.com','197.254.8.98','2026-06-30 07:15:17'),(26,'dazuai01@gmail.com','197.254.8.98','2026-06-30 07:15:28'),(27,'dazuai01@gmail.com','197.254.8.98','2026-06-30 07:15:41'),(28,'dazuai01@gmail.com','197.254.8.98','2026-06-30 07:41:05'),(29,'dazuai01@gmail.com','197.254.8.98','2026-06-30 08:55:51'),(30,'dazuai01@gmail.com','197.254.8.98','2026-06-30 08:55:59'),(31,'dazuai01@gmail.com','197.254.8.98','2026-06-30 08:56:21'),(32,'dazuai01@gmail.com','197.254.8.98','2026-06-30 08:56:40'),(33,'Lagrics123@gmail.com','102.213.179.43','2026-06-30 15:30:31'),(34,'Lagrics123@gmail.com','102.213.179.43','2026-06-30 15:30:42'),(35,'Lagrics123@gmail.com','102.213.179.43','2026-07-02 08:11:11'),(36,'Lagrics123@gmail.com','102.213.179.43','2026-07-02 08:11:22'),(37,'Lagrics123@gmail.com','102.213.179.43','2026-07-02 08:20:34'),(38,'Lucsela@gmail.com','102.213.179.43','2026-07-02 08:23:50'),(39,'Lagrics123@gmail.com','102.213.179.43','2026-07-02 13:30:44'),(40,'Lagrics123@gmail.com','102.213.179.43','2026-07-02 13:30:58'),(41,'Lucsela@gmail.com','102.213.179.43','2026-07-02 14:08:43'),(42,'Lucsela@gmail.com','102.213.179.43','2026-07-02 14:14:05'),(43,'jblsduniq@gmail.com','102.213.179.43','2026-07-02 15:04:25'),(44,'karanjav494@gmail.com','197.254.8.98','2026-07-08 07:25:58'),(45,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 13:33:22'),(46,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 14:24:58'),(47,'','102.213.179.43','2026-07-09 14:52:52'),(48,'','102.213.179.43','2026-07-09 14:53:09'),(49,'','102.213.179.43','2026-07-09 14:53:33'),(50,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 14:55:26'),(51,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 15:12:47'),(52,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 15:36:13'),(53,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 15:36:30'),(54,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 15:54:20'),(55,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 15:54:38'),(56,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 15:55:07'),(57,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 15:55:23'),(58,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 16:00:30'),(59,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 16:00:49'),(60,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 16:01:03'),(61,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 16:01:24'),(62,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 16:01:26'),(63,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 16:02:27'),(64,'Lagrics123@gmail.com','102.213.179.43','2026-07-09 16:02:42'),(65,'karanjav494@gmail.com','41.90.193.213','2026-07-16 17:30:24'),(66,'lucsela@gmail.com','105.160.116.161','2026-07-21 08:46:24'),(67,'lucsela@gmail.com','105.160.116.161','2026-07-21 08:46:38'),(68,'karanjav494@gmail.com','::1','2026-08-04 13:59:42'),(69,'karanjav494@gmail.com','::1','2026-08-04 13:59:50'),(70,'dazuhubs@gmail.com','::1','2026-08-06 06:19:37'),(71,'dazuhubs@gmail.com','::1','2026-08-06 06:19:42'),(72,'karanjav494@gmail.com','::1','2026-08-06 06:19:57');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_otps`
--

DROP TABLE IF EXISTS `login_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tenant_id` int DEFAULT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'login_2fa',
  `attempts` tinyint NOT NULL DEFAULT '0',
  `max_attempts` tinyint NOT NULL DEFAULT '5',
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_user_purpose` (`user_id`,`purpose`),
  KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_otps`
--

LOCK TABLES `login_otps` WRITE;
/*!40000 ALTER TABLE `login_otps` DISABLE KEYS */;
INSERT INTO `login_otps` VALUES (1,2,2,'$2y$10$/izm8xNQ.sRUm7bSXuoyX.pX.IZnJxf29Pi/yL3/JJpmH7TW68.oO','login_2fa',1,5,'2026-06-20 14:49:20','2026-06-20 17:40:00','::1','2026-06-20 17:39:20'),(2,2,2,'$2y$10$t78.vPDUmGEZzL9Zl9VqJ.agAzlzE27L9XFzRPjQSyu2TSO1sissO','login_2fa',1,5,'2026-06-20 18:14:03','2026-06-20 21:04:24','::1','2026-06-20 21:04:03'),(3,2,2,'$2y$10$xH2jABi79Wwpj7hzgRFFxeMhjkg85tFdzr1aSrnvcBvTOS0f/LJ0e','login_2fa',1,5,'2026-06-21 00:20:06','2026-06-21 03:10:45','::1','2026-06-21 03:10:07'),(4,5,2,'$2y$10$OJPe17ichLQsxGOx5cuKMuFqQE8fFyKSzP3B0NCis7MLwrkdrY8Yu','login_2fa',1,5,'2026-06-21 00:51:16','2026-06-21 03:42:00','::1','2026-06-21 03:41:16'),(5,5,2,'$2y$10$7.7FAk9qu.qbZ2x.ijb2e.SI4jlZtVPBnOTvs36/7yKvOYK77LYRe','login_2fa',1,5,'2026-06-21 05:17:25','2026-06-21 08:07:58','::1','2026-06-21 08:07:25'),(6,2,2,'$2y$10$gWmQh2jeOpYaQWJ2g6uf9ecrsXUeIHIVCQPcn.ixkX18E2ON1qIMm','login_2fa',1,5,'2026-06-21 05:34:08','2026-06-21 08:25:04','::1','2026-06-21 08:24:08'),(7,2,2,'$2y$10$XCq/q4f6vT9RV83.IhikMuNkRB7UtocMMBAC4rkvfdgxVGA8P7b8K','login_2fa',1,5,'2026-06-21 18:02:43','2026-06-21 20:53:22','::1','2026-06-21 20:52:43'),(8,5,2,'$2y$10$BT2jj..1iaOPoj6D.VjbveWsAnGDZGxTRY.agvwZ6aEF60wNT66lW','login_2fa',1,5,'2026-06-21 18:53:30','2026-06-21 21:44:11','::1','2026-06-21 21:43:30'),(9,2,2,'$2y$10$V7wTX4XX2gMu.LUwlWjHrO0oOu66da83ru8DDdX4hSmLiI76ngp5e','login_2fa',1,5,'2026-06-21 22:57:11','2026-06-22 00:02:08','127.0.0.1','2026-06-21 23:47:11'),(10,2,2,'$2y$10$ROGCDUUOHFHr5/.iBWErfO7X2E2lrIuAiPgWUmPl2HaRDbyirSAJG','password_reset',1,5,'2026-06-23 13:39:53','2026-06-23 16:30:55','102.213.179.43','2026-06-23 16:29:53'),(11,7,2,'$2y$10$r9z7IVAbKkFoeWv/dcb4Xef17lZ36A3/SztFRnbN4dItaSzN6EM1G','password_reset',1,5,'2026-06-23 13:43:25','2026-06-23 16:34:56','102.213.179.43','2026-06-23 16:33:25'),(12,9,2,'$2y$10$3XsxE6CFE8/MBzzZE/tJRuYzKAr4PurtNBegf0oZuVmTH9eMv/Mh6','password_reset',1,5,'2026-06-24 10:29:21','2026-06-24 13:20:21','197.254.8.98','2026-06-24 13:19:21'),(13,10,2,'$2y$10$SYxDN4htLC1Mf1KG0O8x4OMzcXa7EbcpVqhBX5DeluWGTCHKOQDc6','password_reset',1,5,'2026-06-30 09:06:55','2026-06-30 11:57:40','197.254.8.98','2026-06-30 11:56:56'),(14,8,2,'$2y$10$VmJZHPFcrxbBMYcf6cbWv.piWCl8rVi0cWxXpxboCzTh6Snp1lSu.','password_reset',1,5,'2026-06-30 15:40:56','2026-06-30 18:32:13','102.213.179.43','2026-06-30 18:30:56');
/*!40000 ALTER TABLE `login_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(160) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `added_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orderitem_order` (`order_id`),
  KEY `idx_orderitem_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,2,1,36,'Tusker',299.94,1.00,299.94,11,'2026-08-04 18:48:55'),(2,2,2,36,'Tusker',299.94,2.00,599.88,11,'2026-08-04 19:24:59'),(3,2,3,36,'Tusker',299.94,1.00,299.94,11,'2026-08-05 10:36:16'),(4,2,4,36,'Tusker',299.94,1.00,299.94,11,'2026-08-05 10:44:31');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `table_name` varchar(120) NOT NULL,
  `channel` enum('walkin','tab') NOT NULL DEFAULT 'tab',
  `opened_by` int NOT NULL,
  `receipt_number` varchar(32) NOT NULL,
  `status` enum('open','paid','void') NOT NULL DEFAULT 'open',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('cash','mpesa','split') DEFAULT NULL,
  `cash_amount` decimal(12,2) DEFAULT NULL,
  `mpesa_amount` decimal(12,2) DEFAULT NULL,
  `amount_tendered` decimal(12,2) DEFAULT NULL,
  `change_due` decimal(12,2) DEFAULT NULL,
  `paid_by` int DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_receipt` (`tenant_id`,`receipt_number`),
  KEY `idx_order_tenant` (`tenant_id`),
  KEY `idx_order_status` (`tenant_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,2,'Table 4','tab',11,'ORD-000001','paid',299.94,299.94,'cash',299.94,NULL,1000.00,700.06,11,'2026-08-04 19:22:49','2026-08-04 18:48:55','2026-08-04 19:22:49'),(2,2,'Table 4','tab',11,'ORD-000002','paid',599.88,599.88,'mpesa',NULL,599.88,NULL,NULL,11,'2026-08-04 19:25:13','2026-08-04 19:24:59','2026-08-04 19:25:13'),(3,2,'Walkin','tab',11,'ORD-000003','open',299.94,299.94,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 10:36:16','2026-08-05 10:36:16'),(4,2,'Walk-in Customer','walkin',11,'RCP-000004','paid',299.94,299.94,'mpesa',NULL,299.94,NULL,NULL,11,'2026-08-05 10:44:31','2026-08-05 10:44:31','2026-08-05 10:44:31');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `page_headers`
--

DROP TABLE IF EXISTS `page_headers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_headers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `page_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_key` (`page_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_headers`
--

LOCK TABLES `page_headers` WRITE;
/*!40000 ALTER TABLE `page_headers` DISABLE KEYS */;
INSERT INTO `page_headers` VALUES (1,'services','Our Services','Comprehensive digital solutions tailored to elevate your business.',NULL,'2026-06-17 16:32:14'),(2,'projects','Our Projects','A selection of the work we are proud of.',NULL,'2026-06-17 16:32:14'),(3,'blogs','Our Blog','Insights, ideas and updates from the team.',NULL,'2026-06-17 16:32:14'),(4,'contact','Get in Touch','We would love to hear about your project.',NULL,'2026-06-17 16:32:14');
/*!40000 ALTER TABLE `page_headers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `subcategory_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'piece',
  `size_value` decimal(10,2) DEFAULT NULL,
  `size_unit` enum('ml','l') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buying_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `wholesale_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `retail_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `low_stock_threshold` int NOT NULL DEFAULT '10',
  `low_stock_notified_at` datetime DEFAULT NULL,
  `status` enum('active','draft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_tenant` (`tenant_id`),
  KEY `idx_prod_cat` (`category_id`),
  KEY `idx_prod_subcat` (`subcategory_id`),
  KEY `idx_prod_status` (`status`),
  KEY `idx_prod_lowstock` (`tenant_id`,`quantity`),
  KEY `idx_prod_supplier` (`supplier_id`),
  CONSTRAINT `products_chk_1` CHECK (json_valid(`colors`)),
  CONSTRAINT `products_chk_2` CHECK (json_valid(`sizes`))
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (36,2,NULL,NULL,1,'Tusker',NULL,5.00,'piece',500.00,'ml',120.00,299.94,299.94,299.94,NULL,NULL,'/public/assets/uploads/products/prod_4fc999793639.jpg',10,NULL,'active','2026-08-04 18:40:27','2026-08-05 10:44:31'),(37,2,24,NULL,1,'Makali',NULL,10.00,'piece',400.00,'ml',400.00,800.00,800.00,800.00,NULL,NULL,'/assets/uploads/products/prod_6bf991342ac7.jpg',10,NULL,'active','2026-08-05 11:33:44','2026-08-05 11:33:44');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_categories`
--

DROP TABLE IF EXISTS `project_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `category_slug` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `category_description` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_slug` (`category_slug`),
  KEY `created_by` (`created_by`),
  KEY `idx_slug` (`category_slug`),
  CONSTRAINT `project_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_categories`
--

LOCK TABLES `project_categories` WRITE;
/*!40000 ALTER TABLE `project_categories` DISABLE KEYS */;
INSERT INTO `project_categories` VALUES (1,'Web Development','web-development','Web development projects including websites and web applications',NULL,'2026-06-17 16:30:25','2026-06-17 16:30:25'),(2,'Mobile Apps','mobile-apps','Mobile application development projects',NULL,'2026-06-17 16:30:25','2026-06-17 16:30:25'),(3,'UI/UX Design','ui-ux-design','User interface and experience design projects',NULL,'2026-06-17 16:30:25','2026-06-17 16:30:25'),(4,'E-commerce','ecommerce','E-commerce platform and online store projects',NULL,'2026-06-17 16:30:25','2026-06-17 16:30:25'),(5,'Custom Software','custom-software','Custom software development projects',NULL,'2026-06-17 16:30:25','2026-06-17 16:30:25');
/*!40000 ALTER TABLE `project_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_gallery`
--

DROP TABLE IF EXISTS `project_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_gallery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `image_title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_description` text COLLATE utf8mb4_general_ci,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_sort` (`sort_order`),
  CONSTRAINT `project_gallery_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_gallery`
--

LOCK TABLES `project_gallery` WRITE;
/*!40000 ALTER TABLE `project_gallery` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_tags`
--

DROP TABLE IF EXISTS `project_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tag_slug` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag_name` (`tag_name`),
  UNIQUE KEY `tag_slug` (`tag_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_tags`
--

LOCK TABLES `project_tags` WRITE;
/*!40000 ALTER TABLE `project_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_videos`
--

DROP TABLE IF EXISTS `project_videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `video_title` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `video_embed_code` text COLLATE utf8mb4_general_ci,
  `video_type` enum('youtube','vimeo','local','other') COLLATE utf8mb4_general_ci DEFAULT 'youtube',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  CONSTRAINT `project_videos_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_videos`
--

LOCK TABLES `project_videos` WRITE;
/*!40000 ALTER TABLE `project_videos` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `small_title` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `major_title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `project_slug` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `cover_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `view_count` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_slug` (`project_slug`),
  KEY `created_by` (`created_by`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_slug` (`project_slug`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `scope` enum('platform','tenant') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'tenant',
  `capabilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`),
  CONSTRAINT `roles_chk_1` CHECK (json_valid(`capabilities`))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'superadmin','platform','[\"*\"]','2026-06-17 16:29:32'),(2,'admin','tenant','[\"inventory.view\", \"inventory.edit\", \"stock.enter\", \"sales.record\", \"sales.view\", \"customers.manage\", \"catalogue.send\", \"reports.view\", \"staff.manage\", \"settings.manage\", \"billing.manage\"]','2026-06-17 16:29:32'),(3,'user','tenant','[\"inventory.view\", \"sales.record\", \"sales.view\"]','2026-06-17 16:29:32'),(4,'platform_admin','platform','[\"*\"]','2026-06-20 05:10:12'),(5,'tenant_owner','tenant','[\"inventory.view\", \"inventory.edit\", \"stock.enter\", \"sales.record\", \"sales.view\", \"payments.process\", \"customers.manage\", \"catalogue.send\", \"reports.view\", \"staff.manage\", \"settings.manage\", \"billing.manage\"]','2026-06-20 05:10:14'),(6,'staff','tenant','[\"inventory.view\", \"sales.record\", \"sales.view\"]','2026-06-20 05:10:16');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `sale_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'piece',
  `unit_price` decimal(12,2) NOT NULL,
  `price_type` enum('retail','wholesale') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retail',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `quantity` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item_sale` (`sale_id`),
  KEY `idx_item_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,2,1,2,'coca soda','piece',350.00,'retail',0.00,1.00,350.00),(2,2,2,4,'yellow bean','kg',120.00,'retail',111.00,1.00,120.00),(3,2,3,4,'yellow bean','kg',120.00,'retail',111.00,5.00,600.00),(4,2,4,4,'yellow bean','kg',120.00,'retail',111.00,1.00,120.00),(5,2,5,6,'delete on sight','piece',150.00,'retail',50.00,1.00,150.00),(6,2,6,4,'yellow bean','kg',120.00,'retail',111.00,1.00,120.00),(7,2,6,6,'delete on sight','piece',150.00,'retail',50.00,1.00,150.00),(8,2,7,7,'white maize','kg',50.00,'retail',42.00,2.00,100.00),(9,2,8,7,'white maize','kg',50.00,'retail',0.00,1.00,50.00),(10,2,9,7,'white maize','kg',50.00,'retail',0.00,1.00,50.00),(11,2,10,4,'yellow bean 1','kg',120.00,'wholesale',0.00,3.00,360.00),(12,2,11,7,'white maize','kg',50.00,'wholesale',0.00,10.00,500.00),(13,2,12,7,'white maize','kg',50.00,'retail',0.00,10.00,500.00),(14,2,13,7,'white maize','kg',50.00,'retail',0.00,5.00,250.00),(15,2,14,7,'white maize','kg',50.00,'wholesale',0.00,45.00,2250.00),(16,2,15,5,'YELLOW BEAN','kg',120.00,'wholesale',0.00,7.00,840.00),(17,2,16,5,'YELLOW BEAN','kg',120.00,'wholesale',0.00,7.00,840.00),(18,2,17,5,'YELLOW BEAN','kg',140.00,'retail',0.00,0.25,35.00),(19,2,18,10,'DELETE ON SIGHT','kg',150.00,'retail',0.00,100.00,15000.00),(20,2,19,15,'pishori mwea','kg',180.00,'retail',0.00,0.50,90.00),(21,2,20,21,'njahi','kg',90.00,'wholesale',0.00,1.00,90.00),(22,2,21,25,'kamande','kg',200.00,'retail',0.00,2.00,400.00),(23,2,22,12,'maha','kg',135.00,'wholesale',0.00,6.00,810.00),(24,2,23,12,'maha','kg',135.00,'wholesale',0.00,1.00,135.00),(25,2,23,24,'minji','kg',140.00,'wholesale',0.00,3.00,420.00),(26,2,23,29,'Nyayo','kg',110.00,'wholesale',0.00,1.50,165.00),(27,2,23,30,'muthokoi','kg',70.00,'wholesale',0.00,3.00,210.00),(28,2,24,24,'minji','kg',140.00,'wholesale',0.00,1.00,140.00),(29,2,24,29,'Nyayo','kg',110.00,'wholesale',0.00,5.00,550.00),(30,2,25,15,'pishori mwea','kg',160.00,'wholesale',0.00,15.00,2400.00),(31,2,25,19,'njugu red big','kg',220.00,'wholesale',0.00,10.00,2200.00),(32,2,25,25,'kamande','kg',170.00,'wholesale',0.00,20.00,3400.00),(33,2,25,28,'Rosecoco','kg',120.00,'wholesale',0.00,10.00,1200.00),(34,2,25,30,'muthokoi','kg',70.00,'wholesale',0.00,18.15,1270.50),(35,2,26,12,'maha','kg',150.00,'retail',0.00,3.00,450.00),(36,2,27,25,'kamande','kg',200.00,'retail',0.00,0.50,100.00),(37,2,28,24,'minji','kg',150.00,'retail',0.00,1.00,150.00),(38,2,29,29,'Nyayo','kg',110.00,'wholesale',0.00,90.00,9900.00),(39,2,30,33,'yellow bean 2','kg',110.00,'wholesale',0.00,6.00,660.00),(40,2,31,33,'yellow bean 2','kg',110.00,'wholesale',0.00,12.00,1320.00),(41,2,32,32,'yellow bean 1','kg',120.00,'wholesale',0.00,12.00,1440.00),(42,2,33,23,'Makueni','kg',150.00,'retail',0.00,1.00,150.00),(43,2,33,25,'kamande','kg',200.00,'retail',0.00,1.00,200.00),(44,2,33,33,'yellow bean 2','kg',130.00,'retail',0.00,1.00,130.00),(45,2,34,32,'yellow bean 1','kg',120.00,'wholesale',0.00,6.00,720.00),(46,2,35,21,'njahi','kg',120.00,'retail',0.00,0.50,60.00),(47,2,36,21,'njahi','kg',120.00,'retail',0.00,0.50,60.00);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_payments`
--

DROP TABLE IF EXISTS `sale_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `sale_id` int NOT NULL,
  `staff_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) NOT NULL DEFAULT 'cash',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sale` (`sale_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_payments`
--

LOCK TABLES `sale_payments` WRITE;
/*!40000 ALTER TABLE `sale_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `staff_id` int NOT NULL,
  `sale_type` enum('retail','wholesale') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retail',
  `receipt_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` enum('cash','mpesa','split','credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `mpesa_channel` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `amount_due` decimal(12,2) NOT NULL DEFAULT '0.00',
  `amount_given` decimal(12,2) DEFAULT NULL,
  `change_given` decimal(12,2) DEFAULT NULL,
  `cash_amount` decimal(12,2) DEFAULT NULL,
  `mpesa_amount` decimal(12,2) DEFAULT NULL,
  `customer_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('completed','voided') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `payment_status` enum('pending','paid','part_paid','credit','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sale_receipt` (`tenant_id`,`receipt_number`),
  KEY `idx_sale_tenant` (`tenant_id`),
  KEY `idx_sale_staff` (`staff_id`),
  KEY `idx_sale_created` (`tenant_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,2,5,'retail','RCP-000001','mpesa',NULL,350.00,350.00,0.00,0.00,0.00,350.00,0.00,NULL,NULL,NULL,NULL,'vickiekaran254@gmail.com','completed','paid','2026-06-21 21:45:09'),(2,2,7,'retail','RCP-000002','mpesa',NULL,120.00,120.00,0.00,0.00,0.00,120.00,0.00,NULL,NULL,'Victor Karanja',NULL,'vickiekaran254@gmail.com','completed','paid','2026-06-23 16:03:03'),(3,2,8,'retail','RCP-000003','mpesa',NULL,600.00,600.00,0.00,0.00,0.00,600.00,0.00,NULL,NULL,'LUCY',NULL,NULL,'completed','paid','2026-06-23 16:12:45'),(4,2,8,'retail','RCP-000004','mpesa','direct',120.00,120.00,0.00,0.00,0.00,120.00,0.00,NULL,NULL,NULL,NULL,NULL,'completed','paid','2026-06-30 09:02:51'),(5,2,10,'retail','RCP-000005','cash',NULL,150.00,150.00,0.00,0.00,0.00,200.00,50.00,NULL,NULL,NULL,NULL,NULL,'completed','paid','2026-06-30 11:58:55'),(6,2,10,'retail','RCP-000006','mpesa','direct',270.00,270.00,0.00,0.00,0.00,270.00,0.00,NULL,NULL,NULL,NULL,NULL,'completed','paid','2026-06-30 18:20:51'),(7,2,8,'retail','RCP-000007','mpesa','direct',100.00,100.00,0.00,0.00,0.00,100.00,0.00,NULL,NULL,NULL,NULL,NULL,'completed','paid','2026-06-30 18:34:24'),(8,2,10,'retail','RCP-000008','split',NULL,40.00,50.00,10.00,0.00,0.00,30.00,0.00,30.00,10.00,NULL,NULL,NULL,'completed','paid','2026-07-01 09:56:48'),(9,2,8,'retail','RCP-000009','split',NULL,50.00,50.00,0.00,0.00,0.00,20.00,0.00,20.00,30.00,NULL,NULL,NULL,'completed','paid','2026-07-02 11:12:45'),(10,2,8,'wholesale','RCP-000010','split',NULL,345.00,360.00,15.00,0.00,0.00,200.00,0.00,200.00,145.00,NULL,NULL,NULL,'completed','paid','2026-07-02 11:22:16'),(11,2,8,'wholesale','RCP-000011','mpesa',NULL,500.00,500.00,0.00,0.00,0.00,500.00,0.00,NULL,500.00,NULL,NULL,NULL,'completed','paid','2026-07-02 11:27:57'),(12,2,8,'retail','RCP-000012','split',NULL,500.00,500.00,0.00,0.00,0.00,500.00,0.00,500.00,NULL,'goodwill',NULL,NULL,'completed','paid','2026-07-02 11:30:07'),(13,2,8,'retail','RCP-000013','cash',NULL,250.00,250.00,0.00,0.00,0.00,300.00,50.00,250.00,NULL,NULL,NULL,NULL,'completed','paid','2026-07-02 11:33:14'),(14,2,8,'wholesale','RCP-000014','split',NULL,2000.00,2250.00,250.00,0.00,0.00,1500.00,0.00,1500.00,500.00,NULL,NULL,NULL,'completed','paid','2026-07-02 11:35:52'),(15,2,8,'wholesale','RCP-000015','mpesa',NULL,840.00,840.00,0.00,0.00,0.00,840.00,0.00,NULL,840.00,NULL,NULL,NULL,'completed','paid','2026-07-02 17:05:03'),(16,2,8,'wholesale','RCP-000016','mpesa',NULL,840.00,840.00,0.00,0.00,0.00,840.00,0.00,NULL,840.00,NULL,NULL,NULL,'completed','paid','2026-07-02 17:26:59'),(17,2,10,'retail','RCP-000017','cash',NULL,35.00,35.00,0.00,0.00,0.00,50.00,15.00,35.00,NULL,NULL,NULL,NULL,'completed','paid','2026-07-03 08:55:53'),(18,2,10,'retail','RCP-000018','credit',NULL,15000.00,15000.00,0.00,0.00,15000.00,0.00,0.00,NULL,NULL,'Dazu Ai hub',NULL,'dazuhubs@gmail.com','completed','credit','2026-07-08 10:36:46'),(19,2,8,'retail','RCP-000019','mpesa',NULL,90.00,90.00,0.00,90.00,0.00,90.00,0.00,NULL,90.00,NULL,NULL,NULL,'completed','paid','2026-07-09 15:45:16'),(20,2,8,'wholesale','RCP-000020','cash',NULL,90.00,90.00,0.00,90.00,0.00,90.00,0.00,90.00,NULL,NULL,NULL,NULL,'completed','paid','2026-07-09 16:22:38'),(21,2,8,'retail','RCP-000021','split',NULL,400.00,400.00,0.00,400.00,0.00,200.00,0.00,200.00,200.00,NULL,NULL,NULL,'completed','paid','2026-07-09 16:34:44'),(22,2,8,'wholesale','RCP-000022','cash',NULL,810.00,810.00,0.00,810.00,0.00,810.00,0.00,810.00,NULL,NULL,NULL,NULL,'completed','paid','2026-07-09 16:41:10'),(23,2,8,'wholesale','RCP-000023','mpesa',NULL,930.00,930.00,0.00,930.00,0.00,930.00,0.00,NULL,930.00,NULL,NULL,NULL,'completed','paid','2026-07-09 16:52:10'),(24,2,8,'wholesale','RCP-000024','mpesa',NULL,690.00,690.00,0.00,690.00,0.00,690.00,0.00,NULL,690.00,NULL,NULL,NULL,'completed','paid','2026-07-09 17:00:57'),(25,2,8,'wholesale','RCP-000025','cash',NULL,10470.50,10470.50,0.00,10470.50,0.00,10471.00,0.50,10470.50,NULL,NULL,NULL,NULL,'completed','paid','2026-07-09 17:07:48'),(26,2,8,'retail','RCP-000026','cash',NULL,450.00,450.00,0.00,450.00,0.00,1000.00,550.00,450.00,NULL,NULL,NULL,NULL,'completed','paid','2026-07-09 17:26:23'),(27,2,8,'retail','RCP-000027','cash',NULL,100.00,100.00,0.00,100.00,0.00,100.00,0.00,100.00,NULL,NULL,NULL,NULL,'completed','paid','2026-07-09 17:57:34'),(28,2,8,'retail','RCP-000028','cash',NULL,150.00,150.00,0.00,150.00,0.00,1000.00,850.00,150.00,NULL,NULL,NULL,NULL,'completed','paid','2026-07-09 17:58:25'),(29,2,8,'wholesale','RCP-000029','credit',NULL,8000.00,9900.00,1900.00,0.00,8000.00,0.00,0.00,NULL,NULL,'dama',NULL,NULL,'completed','credit','2026-07-09 18:01:45'),(30,2,8,'wholesale','RCP-000030','mpesa',NULL,660.00,660.00,0.00,660.00,0.00,660.00,0.00,NULL,660.00,NULL,NULL,NULL,'completed','paid','2026-07-09 18:38:09'),(31,2,8,'wholesale','RCP-000031','mpesa',NULL,1320.00,1320.00,0.00,1320.00,0.00,1320.00,0.00,NULL,1320.00,NULL,NULL,NULL,'completed','paid','2026-07-09 18:40:29'),(32,2,8,'wholesale','RCP-000032','mpesa',NULL,1440.00,1440.00,0.00,1440.00,0.00,1440.00,0.00,NULL,1440.00,NULL,NULL,NULL,'completed','paid','2026-07-09 18:43:01'),(33,2,8,'retail','RCP-000033','mpesa',NULL,460.00,480.00,20.00,460.00,0.00,460.00,0.00,NULL,460.00,NULL,NULL,NULL,'completed','paid','2026-07-09 18:58:37'),(34,2,8,'wholesale','RCP-000034','mpesa',NULL,720.00,720.00,0.00,720.00,0.00,720.00,0.00,NULL,720.00,NULL,NULL,NULL,'completed','paid','2026-07-09 19:03:34'),(35,2,8,'retail','RCP-000035','mpesa',NULL,50.00,60.00,10.00,50.00,0.00,50.00,0.00,NULL,50.00,NULL,NULL,NULL,'completed','paid','2026-07-09 19:11:07'),(36,2,8,'retail','RCP-000036','mpesa',NULL,50.00,60.00,10.00,50.00,0.00,50.00,0.00,NULL,50.00,NULL,NULL,NULL,'completed','paid','2026-07-09 19:11:10');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_benefits`
--

DROP TABLE IF EXISTS `service_benefits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_benefits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `benefit_title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `benefit_description` text COLLATE utf8mb4_general_ci,
  `icon_class` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  CONSTRAINT `service_benefits_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_benefits`
--

LOCK TABLES `service_benefits` WRITE;
/*!40000 ALTER TABLE `service_benefits` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_benefits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_faqs`
--

DROP TABLE IF EXISTS `service_faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_faqs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `question` varchar(300) COLLATE utf8mb4_general_ci NOT NULL,
  `answer` text COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  CONSTRAINT `service_faqs_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_faqs`
--

LOCK TABLES `service_faqs` WRITE;
/*!40000 ALTER TABLE `service_faqs` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_faqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_gallery`
--

DROP TABLE IF EXISTS `service_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_gallery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `image_title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_description` text COLLATE utf8mb4_general_ci,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_sort` (`sort_order`),
  CONSTRAINT `service_gallery_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_gallery`
--

LOCK TABLES `service_gallery` WRITE;
/*!40000 ALTER TABLE `service_gallery` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_sections`
--

DROP TABLE IF EXISTS `service_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video') COLLATE utf8mb4_general_ci DEFAULT 'text_only',
  `title` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_general_ci,
  `media_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `media_type` enum('image','video','youtube','vimeo') COLLATE utf8mb4_general_ci DEFAULT 'image',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_sort` (`sort_order`),
  CONSTRAINT `service_sections_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_sections`
--

LOCK TABLES `service_sections` WRITE;
/*!40000 ALTER TABLE `service_sections` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `short_description` text COLLATE utf8mb4_general_ci,
  `cover_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `view_count` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_slug` (`slug`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES ('logo_alt','Ismano','2026-06-17 16:32:07'),('logo_path',NULL,'2026-06-17 16:32:07'),('site_name','Ismano','2026-06-17 16:32:07');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_time_logs`
--

DROP TABLE IF EXISTS `staff_time_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_time_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `user_id` int NOT NULL,
  `clock_in_at` datetime NOT NULL,
  `clock_out_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timelog_tenant` (`tenant_id`),
  KEY `idx_timelog_user_time` (`user_id`,`clock_in_at`),
  KEY `idx_timelog_open` (`user_id`,`clock_out_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_time_logs`
--

LOCK TABLES `staff_time_logs` WRITE;
/*!40000 ALTER TABLE `staff_time_logs` DISABLE KEYS */;
INSERT INTO `staff_time_logs` VALUES (1,2,11,'2026-08-05 10:16:02',NULL,'2026-08-05 10:16:02');
/*!40000 ALTER TABLE `staff_time_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_intake_items`
--

DROP TABLE IF EXISTS `stock_intake_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_intake_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `stock_intake_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(160) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `buying_price` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intakeitem_intake` (`stock_intake_id`),
  KEY `idx_intakeitem_tenant` (`tenant_id`),
  KEY `idx_intakeitem_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_intake_items`
--

LOCK TABLES `stock_intake_items` WRITE;
/*!40000 ALTER TABLE `stock_intake_items` DISABLE KEYS */;
INSERT INTO `stock_intake_items` VALUES (1,2,1,36,'Tusker',10.00,120.00,'2026-08-04 18:40:27'),(2,2,2,37,'Makali',10.00,400.00,'2026-08-05 11:33:44');
/*!40000 ALTER TABLE `stock_intake_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_intakes`
--

DROP TABLE IF EXISTS `stock_intakes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_intakes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `staff_id` int NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intake_tenant` (`tenant_id`),
  KEY `idx_intake_supplier` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_intakes`
--

LOCK TABLES `stock_intakes` WRITE;
/*!40000 ALTER TABLE `stock_intakes` DISABLE KEYS */;
INSERT INTO `stock_intakes` VALUES (1,2,1,9,NULL,'2026-08-04 18:40:27'),(2,2,1,9,NULL,'2026-08-05 11:33:44');
/*!40000 ALTER TABLE `stock_intakes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_categories`
--

DROP TABLE IF EXISTS `store_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `image_path` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_active` (`is_active`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_categories`
--

LOCK TABLES `store_categories` WRITE;
/*!40000 ALTER TABLE `store_categories` DISABLE KEYS */;
INSERT INTO `store_categories` VALUES (1,'Electronics','electronics','Electronic devices and gadgets',NULL,1,1,'2026-06-17 16:33:22','2026-06-17 16:33:22'),(2,'Clothing','clothing','Fashion and apparel',NULL,2,1,'2026-06-17 16:33:22','2026-06-17 16:33:22'),(3,'Books','books','Books and publications',NULL,3,1,'2026-06-17 16:33:22','2026-06-17 16:33:22'),(4,'Home & Living','home-living','Home decor and living essentials',NULL,4,1,'2026-06-17 16:33:22','2026-06-17 16:33:22'),(5,'Sports','sports','Sports equipment and gear',NULL,5,1,'2026-06-17 16:33:22','2026-06-17 16:33:22');
/*!40000 ALTER TABLE `store_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subcategories`
--

DROP TABLE IF EXISTS `subcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','draft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subcat_tenant_cat_name` (`tenant_id`,`category_id`,`name`),
  KEY `idx_subcat_tenant` (`tenant_id`),
  KEY `idx_subcat_cat` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcategories`
--

LOCK TABLES `subcategories` WRITE;
/*!40000 ALTER TABLE `subcategories` DISABLE KEYS */;
INSERT INTO `subcategories` VALUES (2,2,3,'YELLOW BEAN','active','2026-06-23 15:03:06','2026-07-02 17:14:51'),(9,2,3,'yellow bean 1','active','2026-07-02 16:37:26','2026-07-02 16:37:26'),(10,2,3,'yellow bean 2','active','2026-07-02 16:37:56','2026-07-02 16:37:56'),(11,2,3,'wairimu','active','2026-07-02 16:38:10','2026-07-02 16:38:10'),(12,2,3,'Nyayo','active','2026-07-02 16:38:26','2026-07-02 16:38:26'),(13,2,3,'Rosecoco','active','2026-07-02 16:38:35','2026-07-02 16:38:35'),(14,2,3,'Mwitemania','active','2026-07-02 16:38:51','2026-07-02 16:38:51'),(15,2,3,'Army Green','active','2026-07-02 16:39:31','2026-07-02 16:39:31'),(18,2,4,'Makueni','active','2026-07-02 16:40:46','2026-07-02 16:40:46'),(19,2,4,'nylon','active','2026-07-02 16:41:07','2026-07-02 16:41:07'),(20,2,6,'white maize','active','2026-07-02 16:41:35','2026-07-02 16:41:35'),(21,2,6,'yellow maize','active','2026-07-02 16:41:49','2026-07-02 16:41:49'),(22,2,7,'njugu red big','active','2026-07-02 16:42:38','2026-07-02 16:43:06'),(23,2,7,'njugu red small','active','2026-07-02 16:42:51','2026-07-02 16:42:51'),(24,2,9,'small','active','2026-07-02 16:43:45','2026-07-02 16:43:45'),(25,2,9,'big','active','2026-07-02 16:43:50','2026-07-02 16:43:50');
/*!40000 ALTER TABLE `subcategories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `name` varchar(160) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supplier_tenant_name` (`tenant_id`,`name`),
  KEY `idx_supplier_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,2,'Government',NULL,NULL,'2026-08-04 18:40:27','2026-08-04 18:40:27');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_user_id` int DEFAULT NULL,
  `status` enum('active','suspended','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KES',
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_footer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kra_pin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_slug` (`slug`),
  KEY `idx_tenant_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Test Sample shop','test-shop',1,'active','/public/uploads/branding/tenant_1_f81d559e.png','KES','','','Thankyou for shopping with Us',NULL,'2026-06-20 14:32:08','2026-06-20 14:37:51'),(2,'2IN1','lucsela-pos',2,'active','/public/uploads/branding/tenant_2_789b311f.jpg','KES','','Witeithie House','','','2026-06-20 14:41:17','2026-08-04 19:38:54'),(3,'Dazu Shop','dazu-shop',3,'active',NULL,'KES',NULL,NULL,NULL,NULL,'2026-06-20 15:00:11','2026-06-20 15:00:11'),(4,'Dazu Shop','dazu-shop-2',4,'active',NULL,'KES',NULL,NULL,NULL,NULL,'2026-06-20 15:06:26','2026-06-20 15:06:27');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `testimonials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `customer_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `customer_phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `customer_initial` varchar(5) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rating` int DEFAULT '5',
  `testimonial_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `service_tag` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_rating` (`rating`),
  KEY `idx_featured` (`is_featured`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` VALUES (1,'James Mwangi',NULL,NULL,'J',5,'ISMAN designed and installed our 450 sqm hotel kitchen in under 8 weeks. The SS304 fabrication quality exceeded international standards, and their team worked around our operational hours without a single disruption to guests.','Commercial Kitchen','General Manager, Radisson Blu Nairobi','approved',1,0,'2026-06-17 16:34:00','2026-06-17 16:34:00',NULL),(2,'Aisha Noor',NULL,NULL,'A',5,'The stainless balustrade work at Two Rivers was flawless. Precision welds, perfect alignment across three floors, and delivered ahead of schedule. We have used them on every project since.','Stainless Railing','Project Lead, Centum Investment','approved',1,0,'2026-06-17 16:34:00','2026-06-17 16:34:00',NULL),(3,'Dr. Peter Otieno',NULL,NULL,'P',5,'Their hospital fit-out met every infection-control requirement we set. Documentation was thorough and the finish on the SS316 surfaces is exactly what a sterile environment needs.','Hospital Fit-out','Facilities Director, Kenyatta National Hospital','approved',1,0,'2026-06-17 16:34:00','2026-06-17 16:34:00',NULL),(4,'Grace Wambui',NULL,NULL,'G',5,'We commissioned a full processing line and ISMAN handled design, fabrication and install end to end. HACCP-ready, on budget, and running at full throughput from day one.','Food Processing','Operations Manager, Brookside Dairy','approved',1,0,'2026-06-17 16:34:00','2026-06-17 16:34:00',NULL);
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `user_id` int NOT NULL,
  `capability` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `effect` enum('grant','revoke') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grant',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_cap` (`user_id`,`capability`),
  KEY `idx_perm_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_profiles`
--

DROP TABLE IF EXISTS `user_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_profiles` (
  `user_id` int NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_profiles`
--

LOCK TABLES `user_profiles` WRITE;
/*!40000 ALTER TABLE `user_profiles` DISABLE KEYS */;
INSERT INTO `user_profiles` VALUES (2,NULL,NULL,'0792248332',NULL,'2026-06-20 11:41:17');
/*!40000 ALTER TABLE `user_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `pin_hash` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `must_reset_password` tinyint(1) NOT NULL DEFAULT '0',
  `role_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `email_verified` tinyint(1) DEFAULT '0',
  `activation_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activation_expires` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  KEY `role_id` (`role_id`),
  KEY `idx_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_users_activation` (`activation_token`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,2,'Lucsela','lucsela@gmail.com','$2y$10$dzUF.Q/AQxn/NJGDZRuPu.ZcezMnNmfnlCdiktEvBieCpmYTuZhVy',NULL,NULL,0,5,1,1,NULL,NULL,'2026-06-20 11:41:44','2026-06-20 11:41:17','2026-06-23 13:30:55'),(7,2,'Admin','jblsduniq@gmail.com','$2y$10$YNfZEk77G/IaW0sezNCQ4.rLoXGEFIC6RMXlRyG2CwvfWZDcQUmca',NULL,NULL,0,5,1,1,NULL,NULL,NULL,'2026-06-23 12:18:50','2026-06-23 13:34:56'),(9,2,'VickieKaran','vickiekaran254@gmail.com','$2y$10$WXQWB3eoadb2sZS8lNg4f.myGDmqI8iQcFXlVx72WSpHDBYg6gzBO',NULL,NULL,0,5,1,1,NULL,NULL,NULL,'2026-06-24 10:18:36','2026-06-24 10:20:22'),(12,2,'Kasuku','pin.23cd96cd8d9c62cc@staff.local','$2y$12$zOCL87rLSPTleRnnbj1f0.BJ/WKGYeyLljQVYgRk1nkwt2MS.ADd2','$2y$12$YHHlaU3U6SgEj4GNgkfPzeqfUgX/J4tlsovk915CTTM9yoLarWpM6',NULL,0,6,1,1,NULL,NULL,NULL,'2026-08-06 06:20:53','2026-08-06 06:20:53');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-06  9:52:33
