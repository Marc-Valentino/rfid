-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: rfid_attendance_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'clear','activity_logs',NULL,'{\"count\":154}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36','2025-06-12 01:19:21');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_history`
--

DROP TABLE IF EXISTS `attendance_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_history` (
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `rfid_uid` varchar(50) NOT NULL,
  `reader_id` int(11) DEFAULT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `attendance_type` enum('Time In','Time Out','Break Out','Break In') DEFAULT 'Time In',
  `attendance_date` date NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `subject_session` varchar(100) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `verification_status` enum('Verified','Pending','Invalid') DEFAULT 'Verified',
  `notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  KEY `idx_student_date` (`student_id`,`attendance_date`),
  KEY `idx_rfid_uid` (`rfid_uid`),
  KEY `idx_scan_time` (`scan_time`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_type` (`attendance_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_history`
--

LOCK TABLES `attendance_history` WRITE;
/*!40000 ALTER TABLE `attendance_history` DISABLE KEYS */;
INSERT INTO `attendance_history` VALUES (2,4,'3870578740',1,'2025-06-03 20:07:49','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:07:49','2025-06-03 20:20:30'),(3,3,'3871243332',1,'2025-06-03 20:08:02','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:08:02','2025-06-03 20:20:30'),(4,4,'3870578740',1,'2025-06-03 20:09:02','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:09:02','2025-06-03 20:20:30'),(5,3,'3871243332',1,'2025-06-03 20:12:01','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:12:01','2025-06-03 20:20:30'),(6,4,'3870578740',1,'2025-06-03 20:55:11','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:55:11','2025-06-03 20:55:42'),(7,4,'3870578740',1,'2025-06-03 20:55:46','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:55:46','2025-06-03 20:57:17'),(8,4,'3870578740',1,'2025-06-03 20:56:39','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:56:39','2025-06-03 20:57:17'),(9,3,'3871243332',1,'2025-06-03 20:56:46','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:56:46','2025-06-03 20:57:17'),(10,3,'3871243332',1,'2025-06-03 20:58:32','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:58:32','2025-06-03 21:01:44'),(11,4,'3870578740',1,'2025-06-03 20:58:56','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 20:58:56','2025-06-03 21:01:44'),(12,4,'3870578740',1,'2025-06-03 21:01:26','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:01:26','2025-06-03 21:01:44'),(13,3,'3871243332',1,'2025-06-03 21:01:35','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:01:35','2025-06-03 21:01:44'),(14,3,'3871243332',1,'2025-06-03 21:02:00','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:02:00','2025-06-03 21:02:23'),(15,4,'3870578740',1,'2025-06-03 21:02:13','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:02:13','2025-06-03 21:02:23'),(16,3,'3871243332',1,'2025-06-03 21:02:30','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:02:30','2025-06-03 21:02:39'),(17,4,'3870578740',1,'2025-06-03 21:02:31','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:02:31','2025-06-03 21:02:39'),(18,3,'3871243332',1,'2025-06-03 21:02:56','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:02:56','2025-06-03 21:03:01'),(19,4,'3870578740',1,'2025-06-03 21:03:44','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:03:44','2025-06-03 21:04:06'),(20,3,'3871243332',1,'2025-06-03 21:03:53','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:03:53','2025-06-03 21:04:06'),(21,3,'3871243332',1,'2025-06-03 21:04:12','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:04:12','2025-06-03 21:08:49'),(22,3,'3871243332',1,'2025-06-03 21:08:40','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:08:40','2025-06-03 21:08:49'),(23,3,'3871243332',1,'2025-06-03 21:56:06','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:56:06','2025-06-04 03:39:10'),(24,4,'3870578740',1,'2025-06-03 21:56:09','','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-03 21:56:09','2025-06-04 03:39:10'),(25,4,'3870578740',1,'2025-06-04 03:38:46','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 03:38:46','2025-06-04 03:39:10'),(26,4,'3870578740',1,'2025-06-04 03:50:03','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 03:50:03','2025-06-04 04:19:01'),(27,4,'3870578740',1,'2025-06-04 03:52:26','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 03:52:26','2025-06-04 04:19:01'),(28,3,'3871243332',1,'2025-06-04 04:15:07','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 04:15:07','2025-06-04 04:19:01'),(29,4,'3870578740',1,'2025-06-04 04:15:18','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 04:15:18','2025-06-04 04:19:01'),(30,4,'3870578740',1,'2025-06-04 04:17:20','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 04:17:20','2025-06-04 04:19:01'),(31,4,'3870578740',1,'2025-06-04 04:18:06','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 04:18:06','2025-06-04 04:19:01'),(32,4,'3870578740',1,'2025-06-04 04:19:20','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 04:19:20','2025-06-04 04:20:19'),(33,3,'3871243332',1,'2025-06-04 04:19:42','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 04:19:42','2025-06-04 04:20:19'),(34,3,'3871243332',1,'2025-06-04 04:20:12','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 04:20:12','2025-06-04 04:20:19'),(35,4,'3870578740',1,'2025-06-04 05:34:37','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 05:34:37','2025-06-04 05:41:44'),(36,9,'3870442404',1,'2025-06-04 06:22:22','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 06:22:22','2025-06-04 14:56:21'),(37,8,'3870991668',1,'2025-06-04 06:22:26','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 06:22:26','2025-06-04 14:56:21'),(38,7,'3870578740',1,'2025-06-04 06:22:32','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 06:22:32','2025-06-04 14:56:21'),(39,6,'3870270324',1,'2025-06-04 06:22:36','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 06:22:36','2025-06-04 14:56:21'),(40,5,'3871093636',1,'2025-06-04 06:22:40','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 06:22:40','2025-06-04 14:56:21'),(41,4,'3871984500',1,'2025-06-04 06:22:43','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 06:22:43','2025-06-04 14:56:21'),(42,3,'3870143476',1,'2025-06-04 06:22:47','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 06:22:47','2025-06-04 14:56:21'),(43,6,'3870270324',1,'2025-06-04 09:11:12','Time In','2025-06-04',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-04 09:11:12','2025-06-04 14:56:21'),(44,5,'3871093636',1,'2025-06-09 02:14:15','','2025-06-09',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-09 02:14:15','2025-06-09 15:40:43'),(45,9,'3870442404',1,'2025-06-09 02:14:22','','2025-06-09',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-09 02:14:22','2025-06-09 15:40:43'),(46,4,'3871984500',1,'2025-06-09 03:30:01','','2025-06-09',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-09 03:30:01','2025-06-09 15:40:43');
/*!40000 ALTER TABLE `attendance_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_records` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `rfid_uid` varchar(50) NOT NULL,
  `reader_id` int(11) DEFAULT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `attendance_type` enum('Time In','Time Out') NOT NULL,
  `attendance_date` date NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `subject_session` varchar(100) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `verification_status` enum('Verified','Pending','Invalid') DEFAULT 'Verified',
  `notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  KEY `reader_id` (`reader_id`),
  KEY `course_id` (`course_id`),
  KEY `idx_student_date` (`student_id`,`attendance_date`),
  KEY `idx_rfid_uid` (`rfid_uid`),
  KEY `idx_scan_time` (`scan_time`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_type` (`attendance_type`),
  KEY `idx_verification_status` (`verification_status`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`reader_id`) REFERENCES `rfid_readers` (`reader_id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_records`
--

LOCK TABLES `attendance_records` WRITE;
/*!40000 ALTER TABLE `attendance_records` DISABLE KEYS */;
INSERT INTO `attendance_records` VALUES (211,21,'3870143476',1,'2025-06-11 11:11:48','Time In','2025-06-11',NULL,NULL,'Main Entrance','Verified',NULL,'::1',NULL,'2025-06-11 11:11:48');
/*!40000 ALTER TABLE `attendance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_settings`
--

DROP TABLE IF EXISTS `attendance_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `time_in_start` time NOT NULL DEFAULT '08:00:00',
  `time_in_end` time NOT NULL DEFAULT '09:00:00',
  `time_in_closing` time NOT NULL DEFAULT '10:00:00',
  `time_out_start` time NOT NULL DEFAULT '16:00:00',
  `time_out_end` time NOT NULL DEFAULT '17:00:00',
  `time_out_closing` time NOT NULL DEFAULT '18:00:00',
  `late_threshold_minutes` int(11) NOT NULL DEFAULT 15,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_settings`
--

LOCK TABLES `attendance_settings` WRITE;
/*!40000 ALTER TABLE `attendance_settings` DISABLE KEYS */;
INSERT INTO `attendance_settings` VALUES (1,'08:00:00','09:00:00','10:00:00','16:00:00','17:00:00','18:00:00',15,'2025-06-09 15:30:35','2025-06-09 15:30:35'),(2,'07:00:00','08:00:00','10:00:00','16:00:00','17:00:00','18:00:00',15,'2025-06-09 15:49:57','2025-06-09 15:49:57');
/*!40000 ALTER TABLE `attendance_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_sessions`
--

DROP TABLE IF EXISTS `class_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `session_name` varchar(150) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_location` varchar(100) DEFAULT NULL,
  `session_status` enum('Scheduled','Active','Completed','Cancelled') DEFAULT 'Scheduled',
  `attendance_required` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_instructor_date` (`instructor_id`,`session_date`),
  KEY `idx_course_session` (`course_id`,`session_date`),
  CONSTRAINT `class_sessions_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `class_sessions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_sessions`
--

LOCK TABLES `class_sessions` WRITE;
/*!40000 ALTER TABLE `class_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_enrollments`
--

DROP TABLE IF EXISTS `course_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_enrollments` (
  `enrollment_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `enrollment_status` enum('Active','Dropped','Completed') DEFAULT 'Active',
  `grade` varchar(5) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`enrollment_id`),
  UNIQUE KEY `unique_student_course` (`student_id`,`course_id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `idx_course_instructor` (`course_id`,`instructor_id`),
  CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  CONSTRAINT `course_enrollments_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_enrollments`
--

LOCK TABLES `course_enrollments` WRITE;
/*!40000 ALTER TABLE `course_enrollments` DISABLE KEYS */;
INSERT INTO `course_enrollments` VALUES (16,21,24,14,'2025-06-10','Active',NULL,'2025-06-10 02:22:08','2025-06-10 02:22:08'),(17,20,24,14,'2025-06-10','Active',NULL,'2025-06-10 02:22:16','2025-06-10 02:22:16'),(18,18,24,14,'2025-06-10','Active',NULL,'2025-06-10 02:22:18','2025-06-10 02:22:18'),(19,17,10,14,'2025-06-10','Active',NULL,'2025-06-10 02:22:25','2025-06-10 02:22:25'),(20,16,24,14,'2025-06-10','Active',NULL,'2025-06-10 02:22:37','2025-06-10 02:22:37'),(21,19,10,14,'2025-06-10','Active',NULL,'2025-06-10 02:22:42','2025-06-10 02:22:42'),(22,15,10,14,'2025-06-10','Active',NULL,'2025-06-10 02:22:46','2025-06-10 02:22:46');
/*!40000 ALTER TABLE `course_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(150) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `credits` int(11) DEFAULT 3,
  `semester` varchar(20) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`course_id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `idx_course_code` (`course_code`),
  KEY `idx_department` (`department_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (10,'Integrative Programming','1234',1,2,'Summer','2023-2024',1,'2025-05-30 13:38:59','2025-06-03 21:41:50'),(24,'Computer Analyst','5678',1,3,'First','2026-2027',1,'2025-06-03 21:45:03','2025-06-03 21:45:03');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_attendance_summary`
--

DROP TABLE IF EXISTS `daily_attendance_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_attendance_summary` (
  `summary_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `first_time_in` time DEFAULT NULL,
  `last_time_out` time DEFAULT NULL,
  `total_hours` decimal(4,2) DEFAULT 0.00,
  `break_duration` decimal(4,2) DEFAULT 0.00,
  `attendance_status` enum('Present','Absent','Late','Half Day','Excused') DEFAULT 'Present',
  `late_minutes` int(11) DEFAULT 0,
  `course_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`summary_id`),
  UNIQUE KEY `unique_student_date` (`student_id`,`attendance_date`),
  KEY `course_id` (`course_id`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_status` (`attendance_status`),
  CONSTRAINT `daily_attendance_summary_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `daily_attendance_summary_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=208 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_attendance_summary`
--

LOCK TABLES `daily_attendance_summary` WRITE;
/*!40000 ALTER TABLE `daily_attendance_summary` DISABLE KEYS */;
INSERT INTO `daily_attendance_summary` VALUES (207,21,'2025-06-11','19:11:48',NULL,0.00,0.00,'Present',0,NULL,'2025-06-11 11:11:48','2025-06-11 11:11:48');
/*!40000 ALTER TABLE `daily_attendance_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(10) NOT NULL,
  `head_of_department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `department_code` (`department_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Information Technology','IT','Fritz Aseo',1,'2025-05-26 16:15:36','2025-05-26 16:15:36');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_attendance`
--

DROP TABLE IF EXISTS `event_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `day_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `attendance_status` enum('Present','Absent','Late','Excused') DEFAULT 'Present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `unique_event_attendance` (`event_id`,`day_id`,`student_id`),
  KEY `day_id` (`day_id`),
  KEY `idx_event_attendance_student` (`student_id`),
  KEY `idx_event_attendance_event` (`event_id`,`day_id`),
  CONSTRAINT `event_attendance_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `event_attendance_ibfk_2` FOREIGN KEY (`day_id`) REFERENCES `event_days` (`day_id`) ON DELETE SET NULL,
  CONSTRAINT `event_attendance_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_attendance`
--

LOCK TABLES `event_attendance` WRITE;
/*!40000 ALTER TABLE `event_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_days`
--

DROP TABLE IF EXISTS `event_days`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_days` (
  `day_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `day_number` int(11) DEFAULT NULL,
  `day_name` varchar(20) DEFAULT NULL,
  `event_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`day_id`),
  UNIQUE KEY `unique_event_day` (`event_id`,`event_date`),
  CONSTRAINT `event_days_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_days`
--

LOCK TABLES `event_days` WRITE;
/*!40000 ALTER TABLE `event_days` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_days` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_registrations`
--

DROP TABLE IF EXISTS `event_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_registrations` (
  `registration_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  `registration_status` enum('Pending','Confirmed','Cancelled','Waitlisted') DEFAULT 'Pending',
  `attendance_status` enum('Registered','Attended','No Show') DEFAULT 'Registered',
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`registration_id`),
  UNIQUE KEY `unique_event_student` (`event_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `idx_event_registrations` (`event_id`,`registration_status`),
  CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_registrations`
--

LOCK TABLES `event_registrations` WRITE;
/*!40000 ALTER TABLE `event_registrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_types`
--

DROP TABLE IF EXISTS `event_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`type_id`),
  UNIQUE KEY `unique_type_name` (`type_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_types`
--

LOCK TABLES `event_types` WRITE;
/*!40000 ALTER TABLE `event_types` DISABLE KEYS */;
INSERT INTO `event_types` VALUES (1,'Seminar','Academic or professional seminar',1,'2025-06-12 01:47:40','2025-06-12 01:47:40'),(2,'Workshop','Hands-on training workshop',1,'2025-06-12 01:47:40','2025-06-12 01:47:40'),(3,'Conference','Professional conference',1,'2025-06-12 01:47:40','2025-06-12 01:47:40'),(4,'Sports Event','Athletic competition or sports day',1,'2025-06-12 01:47:40','2025-06-12 01:47:40'),(5,'Cultural Event','Cultural or artistic performance',1,'2025-06-12 01:47:40','2025-06-12 01:47:40'),(6,'Orientation','Student or employee orientation',1,'2025-06-12 01:47:40','2025-06-12 01:47:40'),(7,'Meeting','General meeting',1,'2025-06-12 01:47:40','2025-06-12 01:47:40'),(8,'Other','Other type of event',1,'2025-06-12 01:47:40','2025-06-12 01:47:40');
/*!40000 ALTER TABLE `event_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type_id` int(11) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `registration_deadline` datetime DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `organizer_id` int(11) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `type_id` (`type_id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `idx_events_dates` (`start_date`,`end_date`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `event_types` (`type_id`) ON DELETE SET NULL,
  CONSTRAINT `events_ibfk_2` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instructor_courses`
--

DROP TABLE IF EXISTS `instructor_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instructor_courses` (
  `instructor_course_id` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`instructor_course_id`),
  UNIQUE KEY `unique_instructor_course` (`instructor_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `instructor_courses_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `instructor_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instructor_courses`
--

LOCK TABLES `instructor_courses` WRITE;
/*!40000 ALTER TABLE `instructor_courses` DISABLE KEYS */;
INSERT INTO `instructor_courses` VALUES (10,14,10,1,'2025-06-03 21:41:50','2025-06-03 21:41:50'),(11,14,24,1,'2025-06-03 21:45:03','2025-06-03 21:45:03');
/*!40000 ALTER TABLE `instructor_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instructor_details`
--

DROP TABLE IF EXISTS `instructor_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instructor_details` (
  `instructor_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`instructor_detail_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `instructor_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `instructor_details_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instructor_details`
--

LOCK TABLES `instructor_details` WRITE;
/*!40000 ALTER TABLE `instructor_details` DISABLE KEYS */;
INSERT INTO `instructor_details` VALUES (5,14,'152',1,'09288964553','Ormoc','2025-06-03 14:18:05','2025-06-09 15:18:48');
/*!40000 ALTER TABLE `instructor_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `monthly_attendance_stats`
--

DROP TABLE IF EXISTS `monthly_attendance_stats`;
/*!50001 DROP VIEW IF EXISTS `monthly_attendance_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `monthly_attendance_stats` AS SELECT
 1 AS `student_id`,
  1 AS `student_number`,
  1 AS `full_name`,
  1 AS `year`,
  1 AS `month`,
  1 AS `total_days`,
  1 AS `present_days`,
  1 AS `absent_days`,
  1 AS `late_days`,
  1 AS `attendance_percentage` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `rfid_cards`
--

DROP TABLE IF EXISTS `rfid_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfid_cards` (
  `card_id` int(11) NOT NULL AUTO_INCREMENT,
  `rfid_uid` varchar(50) NOT NULL,
  `card_number` varchar(30) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `card_status` enum('Active','Inactive','Lost','Damaged','Expired') DEFAULT 'Active',
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `last_used` timestamp NULL DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `security_code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `rfid_uid` (`rfid_uid`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `idx_rfid_uid` (`rfid_uid`),
  CONSTRAINT `rfid_cards_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rfid_cards`
--

LOCK TABLES `rfid_cards` WRITE;
/*!40000 ALTER TABLE `rfid_cards` DISABLE KEYS */;
INSERT INTO `rfid_cards` VALUES (25,'3870270324',NULL,15,'Active','2025-06-10',NULL,'2025-06-11 07:11:09','2025-06-11 07:11:09',NULL,1,'2025-06-09 18:00:56','2025-06-11 07:11:09'),(26,'3871984500',NULL,16,'Active','2025-06-10',NULL,'2025-06-11 07:11:05','2025-06-11 07:11:05',NULL,1,'2025-06-09 18:01:46','2025-06-11 07:11:05'),(27,'3870442404',NULL,17,'Active','2025-06-10',NULL,'2025-06-11 07:22:16','2025-06-11 10:49:45',NULL,1,'2025-06-09 18:07:15','2025-06-11 10:49:45'),(28,'3871243332',NULL,18,'Active','2025-06-10',NULL,'2025-06-11 07:10:56','2025-06-11 07:10:56',NULL,1,'2025-06-09 18:11:25','2025-06-11 07:10:56'),(29,'3872589524',NULL,19,'Active','2025-06-10',NULL,'2025-06-11 07:11:07','2025-06-11 07:11:07',NULL,1,'2025-06-09 18:13:08','2025-06-11 07:11:07'),(30,'3870459140',NULL,20,'Active','2025-06-10',NULL,'2025-06-11 07:10:46','2025-06-11 07:10:46',NULL,1,'2025-06-09 18:13:50','2025-06-11 07:10:46'),(31,'3870143476',NULL,21,'Active','2025-06-10',NULL,'2025-06-11 11:11:48','2025-06-11 11:11:48',NULL,1,'2025-06-09 18:14:21','2025-06-11 11:11:48');
/*!40000 ALTER TABLE `rfid_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rfid_readers`
--

DROP TABLE IF EXISTS `rfid_readers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfid_readers` (
  `reader_id` int(11) NOT NULL AUTO_INCREMENT,
  `reader_name` varchar(100) NOT NULL,
  `reader_location` varchar(150) NOT NULL,
  `reader_ip` varchar(45) DEFAULT NULL,
  `reader_mac` varchar(17) DEFAULT NULL,
  `reader_type` varchar(50) DEFAULT 'USB',
  `reader_status` enum('Online','Offline','Maintenance') DEFAULT 'Online',
  `last_heartbeat` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`reader_id`),
  KEY `idx_reader_status` (`reader_status`),
  KEY `idx_location` (`reader_location`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rfid_readers`
--

LOCK TABLES `rfid_readers` WRITE;
/*!40000 ALTER TABLE `rfid_readers` DISABLE KEYS */;
INSERT INTO `rfid_readers` VALUES (1,'Default Reader','Main Entrance',NULL,NULL,'USB','Online',NULL,1,'2025-06-03 20:07:49','2025-06-03 20:07:49');
/*!40000 ALTER TABLE `rfid_readers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `session_attendance`
--

DROP TABLE IF EXISTS `session_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `session_attendance` (
  `session_attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_status` enum('Present','Absent','Late','Excused') DEFAULT 'Present',
  `check_in_time` timestamp NULL DEFAULT NULL,
  `check_out_time` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_attendance_id`),
  UNIQUE KEY `unique_session_student` (`session_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `marked_by` (`marked_by`),
  KEY `idx_session_attendance` (`session_id`,`attendance_status`),
  CONSTRAINT `session_attendance_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `session_attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `session_attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `session_attendance`
--

LOCK TABLES `session_attendance` WRITE;
/*!40000 ALTER TABLE `session_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `session_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `student_attendance_view`
--

DROP TABLE IF EXISTS `student_attendance_view`;
/*!50001 DROP VIEW IF EXISTS `student_attendance_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `student_attendance_view` AS SELECT
 1 AS `student_id`,
  1 AS `student_number`,
  1 AS `full_name`,
  1 AS `department_name`,
  1 AS `course_name`,
  1 AS `attendance_date`,
  1 AS `first_time_in`,
  1 AS `last_time_out`,
  1 AS `total_hours`,
  1 AS `attendance_status`,
  1 AS `late_minutes` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_number` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year') DEFAULT NULL,
  `enrollment_status` enum('Active','Inactive','Graduated','Dropped') DEFAULT 'Active',
  `profile_image` varchar(255) DEFAULT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `student_number` (`student_number`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_student_number` (`student_number`),
  KEY `idx_name` (`first_name`,`last_name`),
  KEY `idx_department` (`department_id`),
  KEY `idx_course` (`course_id`),
  KEY `idx_enrollment_status` (`enrollment_status`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (15,'1','Rodmarc','Villaflores',NULL,'rodmarc.ariza.villaflores@gmail.com','09288964553',NULL,NULL,NULL,1,10,'1st Year','Active',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-06-09 18:00:56','2025-06-09 18:00:56'),(16,'2','Jhon Carlo','Nudalo',NULL,'jc@gmail.com','09288964553',NULL,NULL,NULL,1,24,'1st Year','Active',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-06-09 18:01:46','2025-06-09 18:01:46'),(17,'3','Kean Andre','Maglasang',NULL,'maglasang@gmail.com','09288964553',NULL,NULL,NULL,1,10,'1st Year','Active',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-06-09 18:07:15','2025-06-09 18:07:15'),(18,'4','Princess Chantille','Gatdula',NULL,'pc@gmail.com','09288964553',NULL,NULL,NULL,1,24,'1st Year','Active',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-06-09 18:11:25','2025-06-09 18:11:25'),(19,'5','Drexler','Torres',NULL,'torres@gmail.com','09288964553',NULL,NULL,NULL,1,10,'1st Year','Active',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-06-09 18:13:08','2025-06-09 18:13:08'),(20,'6','Mariane','Bejemino',NULL,'bejemino@gmail.com','09288964553',NULL,NULL,NULL,1,24,'1st Year','Active',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-06-09 18:13:50','2025-06-09 18:13:50'),(21,'7','Arah','Abajon',NULL,'abajon@gmail.com','09288964553',NULL,NULL,NULL,1,24,'1st Year','Active',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-06-09 18:14:21','2025-06-09 18:14:21');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `is_editable` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'school_name','Eastern Visayas State University','Name of the educational institution','string',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(2,'attendance_grace_period','15','Grace period in minutes for late attendance','integer',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(3,'working_hours_start','08:00','Default start time for attendance','string',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(4,'working_hours_end','17:00','Default end time for attendance','string',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(5,'auto_logout_enabled','true','Enable automatic logout after time out scan','boolean',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(6,'email_notifications','true','Enable email notifications for attendance','boolean',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(7,'backup_frequency','daily','Database backup frequency','string',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(8,'session_timeout','3600','Session timeout in seconds','integer',1,'2025-05-26 16:15:36','2025-05-26 16:15:36'),(11,'enable_attendance_notifications','true','Enable/Disable attendance notifications','boolean',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(12,'enable_sound_notifications','true','Enable/Disable sound for notifications','boolean',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(13,'default_event_duration_hours','8','Default event duration in hours','integer',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(14,'default_attendance_grace_minutes','15','Grace period in minutes for late attendance','integer',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(15,'enable_email_notifications','false','Enable/Disable email notifications','boolean',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(16,'enable_event_registration','true','Enable/Disable event registration','boolean',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(17,'require_event_registration','false','Require registration for events','boolean',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(18,'max_daily_event_hours','12','Maximum hours allowed for a single-day event','integer',1,'2025-06-12 01:47:39','2025-06-12 01:47:39'),(19,'min_event_notice_hours','24','Minimum hours notice required for event creation','integer',1,'2025-06-12 01:47:39','2025-06-12 01:47:39');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','teacher','staff') DEFAULT 'teacher',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@school.edu','$2y$10$y8Cl8SKNpbTSAna6EEA.OexQwnfBkEaNc7dW3FeHwSY8UepEkT5ly','System Administrator','admin',1,'2025-05-26 16:15:36','2025-06-12 00:42:45','2025-06-12 00:42:45',NULL,NULL,NULL,NULL),(14,'Valentino','testuser1@gmail.com','$2y$10$x/9UA2XeHhSdXh.ztub.wOL4lu7NNrmxR3nvFGTE82JzjsG9bab6u','Valentino Casaldan','teacher',1,'2025-06-03 14:18:05','2025-06-12 00:39:05','2025-06-12 00:39:05',1,'12','09288964553','Ormoc City');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `monthly_attendance_stats`
--

/*!50001 DROP VIEW IF EXISTS `monthly_attendance_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `monthly_attendance_stats` AS select `s`.`student_id` AS `student_id`,`s`.`student_number` AS `student_number`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `full_name`,year(`das`.`attendance_date`) AS `year`,month(`das`.`attendance_date`) AS `month`,count(0) AS `total_days`,sum(case when `das`.`attendance_status` = 'Present' then 1 else 0 end) AS `present_days`,sum(case when `das`.`attendance_status` = 'Absent' then 1 else 0 end) AS `absent_days`,sum(case when `das`.`attendance_status` = 'Late' then 1 else 0 end) AS `late_days`,round(sum(case when `das`.`attendance_status` = 'Present' then 1 else 0 end) / count(0) * 100,2) AS `attendance_percentage` from (`students` `s` left join `daily_attendance_summary` `das` on(`s`.`student_id` = `das`.`student_id`)) where `s`.`is_active` = 1 group by `s`.`student_id`,year(`das`.`attendance_date`),month(`das`.`attendance_date`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `student_attendance_view`
--

/*!50001 DROP VIEW IF EXISTS `student_attendance_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `student_attendance_view` AS select `s`.`student_id` AS `student_id`,`s`.`student_number` AS `student_number`,concat(`s`.`first_name`,' ',`s`.`last_name`) AS `full_name`,`d`.`department_name` AS `department_name`,`c`.`course_name` AS `course_name`,`das`.`attendance_date` AS `attendance_date`,`das`.`first_time_in` AS `first_time_in`,`das`.`last_time_out` AS `last_time_out`,`das`.`total_hours` AS `total_hours`,`das`.`attendance_status` AS `attendance_status`,`das`.`late_minutes` AS `late_minutes` from (((`students` `s` left join `departments` `d` on(`s`.`department_id` = `d`.`department_id`)) left join `courses` `c` on(`s`.`course_id` = `c`.`course_id`)) left join `daily_attendance_summary` `das` on(`s`.`student_id` = `das`.`student_id`)) where `s`.`is_active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-12 10:20:10
