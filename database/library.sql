-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: library
-- ------------------------------------------------------
-- Server version	8.0.44

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

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `FullName` varchar(100) DEFAULT NULL,
  `AdminEmail` varchar(120) DEFAULT NULL,
  `UserName` varchar(100) NOT NULL,
  `Password` varchar(100) NOT NULL,
  `updationDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES (1,'Sravan Kumar','admin@gmail.com','admin','86a9be1d9364e31fcea6f9b1b5ccca3b','2026-03-14 12:25:30');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblauthors`
--

DROP TABLE IF EXISTS `tblauthors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblauthors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `AuthorName` varchar(159) DEFAULT NULL,
  `creationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblauthors`
--

LOCK TABLES `tblauthors` WRITE;
/*!40000 ALTER TABLE `tblauthors` DISABLE KEYS */;
INSERT INTO `tblauthors` VALUES (1,'Anuj kumar','2023-12-31 21:23:03','2025-01-07 06:18:43'),(3,'Anita Desai','2023-12-31 21:23:03','2025-01-07 06:18:50'),(4,'HC Verma','2023-12-31 21:23:03','2025-01-07 06:18:50'),(5,'R.D. Sharma ','2023-12-31 21:23:03','2025-01-07 06:18:50'),(9,'fwdfrwer','2023-12-31 21:23:03','2025-01-07 06:18:50'),(10,'Dr. Andy Williams','2023-12-31 21:23:03','2025-01-07 06:18:50'),(11,'Kyle Hill','2023-12-31 21:23:03','2025-01-07 06:18:50'),(12,'Robert T. Kiyosak','2023-12-31 21:23:03','2025-01-07 06:18:50'),(13,'Kelly Barnhill','2023-12-31 21:23:03','2025-01-07 06:18:50'),(14,'Herbert Schildt','2023-12-31 21:23:03','2025-01-07 06:18:50'),(16,' Tiffany Timbers','2025-01-07 06:55:54',NULL);
/*!40000 ALTER TABLE `tblauthors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblbookrequests`
--

DROP TABLE IF EXISTS `tblbookrequests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblbookrequests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `BookId` int DEFAULT NULL,
  `StudentId` varchar(100) DEFAULT NULL,
  `RequestDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Status` tinyint(1) NOT NULL DEFAULT '0',
  `UserRemark` mediumtext,
  `AdminRemark` mediumtext,
  `ActionDate` timestamp NULL DEFAULT NULL,
  `IssuedBookId` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_book_requests_student_status` (`StudentId`,`Status`),
  KEY `idx_book_requests_book_status` (`BookId`,`Status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblbookrequests`
--

LOCK TABLES `tblbookrequests` WRITE;
/*!40000 ALTER TABLE `tblbookrequests` DISABLE KEYS */;
INSERT INTO `tblbookrequests` VALUES (1,1,'SID014','2026-03-14 10:50:23',1,NULL,'Issued from approved book request','2026-03-14 10:51:11',7),(2,12,'SID009','2026-03-14 10:51:41',1,NULL,'Approved from request flow test','2026-03-14 10:52:39',8),(3,3,'SID014','2026-03-14 10:59:38',2,NULL,'Request rejected by admin','2026-03-14 11:01:03',NULL);
/*!40000 ALTER TABLE `tblbookrequests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblbooks`
--

DROP TABLE IF EXISTS `tblbooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblbooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `BookName` varchar(255) DEFAULT NULL,
  `CatId` int DEFAULT NULL,
  `AuthorId` int DEFAULT NULL,
  `ISBNNumber` varchar(25) DEFAULT NULL,
  `BookPrice` decimal(10,2) DEFAULT NULL,
  `bookImage` varchar(250) NOT NULL,
  `isIssued` int DEFAULT NULL,
  `RegDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `bookQty` int DEFAULT NULL,
  `PreviewLink` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblbooks`
--

LOCK TABLES `tblbooks` WRITE;
/*!40000 ALTER TABLE `tblbooks` DISABLE KEYS */;
INSERT INTO `tblbooks` VALUES (1,'PHP And MySql programming',5,1,'222333',20.00,'1efecc0ca822e40b7b673c0d79ae943f.jpg',0,'2024-01-02 01:23:03','2025-01-14 07:08:11',10,NULL),(3,'physics',6,4,'1111',15.00,'dd8267b57e0e4feee5911cb1e1a03a79.jpg',NULL,'2024-01-02 01:23:03','2025-01-13 11:11:01',10,NULL),(5,'Murach\'s MySQL',5,1,'9350237695',455.00,'5939d64655b4d2ae443830d73abc35b6.jpg',NULL,'2024-01-02 01:23:03','2025-01-13 11:11:01',20,NULL),(6,'WordPress for Beginners 2022: A Visual Step-by-Step Guide to Mastering WordPress',5,10,'B019MO3WCM',100.00,'144ab706ba1cb9f6c23fd6ae9c0502b3.jpg',NULL,'2024-01-02 01:23:03','2026-03-14 11:32:09',15,NULL),(7,'WordPress Mastery Guide:',5,11,'B09NKWH7NP',53.00,'90083a56014186e88ffca10286172e64.jpg',NULL,'2024-01-02 01:23:03','2025-01-13 11:05:39',14,NULL),(8,'Rich Dad Poor Dad: What the Rich Teach Their Kids About Money That the Poor and Middle Class Do Not',8,12,'B07C7M8SX9',120.00,'52411b2bd2a6b2e0df3eb10943a5b640.jpg',NULL,'2024-01-02 01:23:03','2025-01-13 11:05:41',5,NULL),(9,'The Girl Who Drank the Moon',8,13,'1848126476',200.00,'f05cd198ac9335245e1fdffa793207a7.jpg',NULL,'2024-01-02 01:23:03','2025-01-13 11:05:45',1,NULL),(10,'C++: The Complete Reference, 4th Edition',5,14,'007053246X',142.00,'36af5de9012bf8c804e499dc3c3b33a5.jpg',NULL,'2024-01-02 01:23:03','2025-01-13 11:11:01',2,NULL),(11,'ASP.NET Core 5 for Beginners',9,11,'GBSJ36344563',422.00,'b1b6788016bbfab12cfd2722604badc9.jpg',NULL,'2024-01-02 01:23:03','2025-01-13 11:11:01',5,NULL),(12,'Python Packages',9,16,'0367687771',3034.00,'ba719639def504c64ebac89cdd0d0a85.jpg',NULL,'2025-01-07 06:56:50',NULL,25,NULL);
/*!40000 ALTER TABLE `tblbooks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblbookreviews`
--

DROP TABLE IF EXISTS `tblbookreviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblbookreviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `BookId` int NOT NULL,
  `StudentId` varchar(100) NOT NULL,
  `Rating` tinyint NOT NULL,
  `ReviewText` mediumtext,
  `CreatedDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_book_review_student` (`BookId`,`StudentId`),
  KEY `idx_book_reviews_book` (`BookId`),
  KEY `idx_book_reviews_student` (`StudentId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblbookreviews`
--

LOCK TABLES `tblbookreviews` WRITE;
/*!40000 ALTER TABLE `tblbookreviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `tblbookreviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblcart`
--

DROP TABLE IF EXISTS `tblcart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblcart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` varchar(100) NOT NULL,
  `BookId` int NOT NULL,
  `Quantity` int NOT NULL DEFAULT '1',
  `AddedDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cart_student_book` (`StudentId`,`BookId`),
  KEY `idx_cart_student` (`StudentId`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblcart`
--

LOCK TABLES `tblcart` WRITE;
/*!40000 ALTER TABLE `tblcart` DISABLE KEYS */;
/*!40000 ALTER TABLE `tblcart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblcategory`
--

DROP TABLE IF EXISTS `tblcategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblcategory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `CategoryName` varchar(150) DEFAULT NULL,
  `Status` int DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblcategory`
--

LOCK TABLES `tblcategory` WRITE;
/*!40000 ALTER TABLE `tblcategory` DISABLE KEYS */;
INSERT INTO `tblcategory` VALUES (4,'Romantic',1,'2025-01-01 07:23:03','2025-01-07 06:19:11'),(5,'Technology',1,'2025-01-01 07:23:03','2025-01-07 06:19:21'),(6,'Science',1,'2025-01-01 07:23:03','2025-01-07 06:19:21'),(7,'Management',1,'2025-01-01 07:23:03','2025-01-07 06:19:21'),(8,'General',1,'2025-01-01 07:23:03','2025-01-07 06:19:21'),(9,'Programming',1,'2025-01-01 07:23:03','2025-01-07 06:19:21');
/*!40000 ALTER TABLE `tblcategory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblissuedbookdetails`
--

DROP TABLE IF EXISTS `tblissuedbookdetails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblissuedbookdetails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `BookId` int DEFAULT NULL,
  `StudentID` varchar(150) DEFAULT NULL,
  `IssuesDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ReturnDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `RetrunStatus` int DEFAULT NULL,
  `fine` int DEFAULT NULL,
  `ReturnRequestStatus` tinyint(1) NOT NULL DEFAULT '0',
  `ReturnRequestDate` timestamp NULL DEFAULT NULL,
  `ReturnProcessedDate` timestamp NULL DEFAULT NULL,
  `remark` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblissuedbookdetails`
--

LOCK TABLES `tblissuedbookdetails` WRITE;
/*!40000 ALTER TABLE `tblissuedbookdetails` DISABLE KEYS */;
INSERT INTO `tblissuedbookdetails` VALUES (1,1,'SID002','2025-01-13 11:12:40','2025-01-14 06:00:56',1,0,0,NULL,NULL,'NA'),(2,7,'SID010','2025-01-14 05:55:25','2026-03-14 11:22:07',1,0,2,NULL,'2026-03-14 11:22:07','NA'),(3,1,'SID010','2025-01-14 05:55:39','2026-03-14 11:22:03',1,0,2,NULL,'2026-03-14 11:22:03','NA'),(5,1,'SID002','2025-01-14 06:02:14','2025-01-14 06:03:36',1,0,0,NULL,NULL,'ds'),(6,1,'SID002','2026-03-14 10:35:11','2026-03-14 11:21:53',1,0,2,NULL,'2026-03-14 11:21:53','codex flow test'),(7,1,'SID014','2026-03-14 10:51:11','2026-03-14 11:21:08',1,0,2,NULL,'2026-03-14 11:21:08','Issued from approved book request'),(8,12,'SID009','2026-03-14 10:52:39','2026-03-14 11:20:56',1,0,2,NULL,'2026-03-14 11:20:56','Approved from request flow test');
/*!40000 ALTER TABLE `tblissuedbookdetails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblorderitems`
--

DROP TABLE IF EXISTS `tblorderitems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblorderitems` (
  `id` int NOT NULL AUTO_INCREMENT,
  `OrderId` int NOT NULL,
  `BookId` int NOT NULL,
  `Quantity` int NOT NULL DEFAULT '1',
  `UnitPrice` decimal(10,2) NOT NULL DEFAULT '0.00',
  `LineTotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `CreatedDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orderitems_order` (`OrderId`),
  KEY `idx_orderitems_book` (`BookId`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblorderitems`
--

LOCK TABLES `tblorderitems` WRITE;
/*!40000 ALTER TABLE `tblorderitems` DISABLE KEYS */;
INSERT INTO `tblorderitems` VALUES (3,3,3,1,15.00,15.00,'2026-03-14 11:13:06');
/*!40000 ALTER TABLE `tblorderitems` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblorders`
--

DROP TABLE IF EXISTS `tblorders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblorders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `OrderNumber` varchar(40) NOT NULL,
  `StudentId` varchar(100) NOT NULL,
  `TotalAmount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `PaymentMethod` varchar(50) NOT NULL DEFAULT 'demo_gateway',
  `PaymentProvider` varchar(50) DEFAULT NULL,
  `PaymentStatus` varchar(50) NOT NULL DEFAULT 'paid',
  `OrderStatus` varchar(50) NOT NULL DEFAULT 'placed',
  `StatusNote` mediumtext,
  `TransactionId` varchar(120) DEFAULT NULL,
  `CreatedDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_number` (`OrderNumber`),
  KEY `idx_orders_student` (`StudentId`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblorders`
--

LOCK TABLES `tblorders` WRITE;
/*!40000 ALTER TABLE `tblorders` DISABLE KEYS */;
INSERT INTO `tblorders` VALUES (3,'ORD20260314111306B132C6','SID014',15.00,'demo_gateway','Demo Gateway','refund_pending','cancelled','Order cancelled by user. Your money will be refunded shortly.','TXN20260314111306B4F690','2026-03-14 11:13:06','2026-03-14 11:42:18');
/*!40000 ALTER TABLE `tblorders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblstudents`
--

DROP TABLE IF EXISTS `tblstudents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblstudents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` varchar(100) DEFAULT NULL,
  `FullName` varchar(120) DEFAULT NULL,
  `EmailId` varchar(120) DEFAULT NULL,
  `MobileNumber` char(11) DEFAULT NULL,
  `Password` varchar(120) DEFAULT NULL,
  `Status` int DEFAULT NULL,
  `RegDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `StudentId` (`StudentId`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblstudents`
--

LOCK TABLES `tblstudents` WRITE;
/*!40000 ALTER TABLE `tblstudents` DISABLE KEYS */;
INSERT INTO `tblstudents` VALUES (1,'SID002','Anuj kumar','anujk@gmail.com','9865472555','f925916e2754e5e03f75dd58a5733251',1,'2024-01-03 07:23:03','2026-03-14 10:29:02'),(4,'SID005','sdfsd','csfsd@dfsfks.com','8569710025','92228410fc8b872914e023160cf4ae8f',1,'2024-01-03 07:23:03','2025-01-07 06:20:36'),(8,'SID009','test','test@gmail.com','2359874527','f925916e2754e5e03f75dd58a5733251',1,'2024-01-03 07:23:03','2025-01-07 06:20:36'),(9,'SID010','Amit','amit@gmail.com','8585856224','f925916e2754e5e03f75dd58a5733251',1,'2024-01-03 07:23:03','2025-01-07 06:20:36'),(10,'SID011','Sarita Pandey','sarita@gmail.com','4672423754','f925916e2754e5e03f75dd58a5733251',1,'2024-01-03 07:23:03','2025-01-07 06:20:36'),(11,'SID012','John Doe','john@test.com','1234569870','f925916e2754e5e03f75dd58a5733251',1,'2024-01-03 07:23:03','2025-01-07 06:20:36'),(12,'SID014','sravanakkaladevi','nktql0nla7@wnbaldwy.com','8121804029','098f6bcd4621d373cade4e832627b4f6',1,'2026-03-14 10:26:52',NULL);
/*!40000 ALTER TABLE `tblstudents` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-14 18:12:49
