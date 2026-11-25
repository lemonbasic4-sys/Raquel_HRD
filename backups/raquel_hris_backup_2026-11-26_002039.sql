-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: raquel_hris
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
-- Table structure for table `annual_cycle_department_owners`
--

DROP TABLE IF EXISTS `annual_cycle_department_owners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annual_cycle_department_owners` (
  `department_id` int(11) NOT NULL,
  `owner_position_id` int(11) NOT NULL,
  PRIMARY KEY (`department_id`),
  KEY `fk_annual_cycle_owner_position` (`owner_position_id`),
  CONSTRAINT `fk_annual_cycle_owner_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  CONSTRAINT `fk_annual_cycle_owner_position` FOREIGN KEY (`owner_position_id`) REFERENCES `job_titles` (`job_title_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annual_cycle_department_owners`
--

LOCK TABLES `annual_cycle_department_owners` WRITE;
/*!40000 ALTER TABLE `annual_cycle_department_owners` DISABLE KEYS */;
INSERT INTO `annual_cycle_department_owners` VALUES (1,101),(2,200),(3,300),(4,400),(5,500),(6,600),(8,800),(9,900),(11,1000),(10,1100),(12,1200);
/*!40000 ALTER TABLE `annual_cycle_department_owners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `annual_evaluation_cycles`
--

DROP TABLE IF EXISTS `annual_evaluation_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annual_evaluation_cycles` (
  `cycle_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `started_by` int(11) DEFAULT NULL,
  `self_rating_deadline` date DEFAULT NULL,
  `first_finalized_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `first_completed_at` datetime DEFAULT NULL,
  `ready_for_approval_at` datetime DEFAULT NULL,
  `cycle_approved_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `active_period_key` tinyint(4) GENERATED ALWAYS AS (if(`cancelled_at` is null,1,NULL)) STORED,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `individual_evaluation_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cycle_id`),
  UNIQUE KEY `annual_department_active_period` (`department_id`,`period_start`,`period_end`,`active_period_key`,`individual_evaluation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annual_evaluation_cycles`
--

LOCK TABLES `annual_evaluation_cycles` WRITE;
/*!40000 ALTER TABLE `annual_evaluation_cycles` DISABLE KEYS */;
/*!40000 ALTER TABLE `annual_evaluation_cycles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `annual_evaluation_members`
--

DROP TABLE IF EXISTS `annual_evaluation_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annual_evaluation_members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `evaluation_id` int(11) DEFAULT NULL,
  `position_id` int(11) NOT NULL,
  `reviewer_position_id` int(11) DEFAULT NULL,
  `position_name` varchar(150) NOT NULL,
  `self_values_json` text DEFAULT NULL,
  `current_version` int(11) NOT NULL DEFAULT 0,
  `revision_pending` tinyint(4) NOT NULL DEFAULT 0,
  `finalized_at` datetime DEFAULT NULL,
  `is_late_participant` tinyint(4) NOT NULL DEFAULT 0,
  `head_approved_by` int(11) DEFAULT NULL,
  `head_approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `annual_member` (`cycle_id`,`employee_id`),
  UNIQUE KEY `annual_evaluation` (`evaluation_id`),
  KEY `annual_employee` (`employee_id`),
  CONSTRAINT `annual_member_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `annual_evaluation_cycles` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annual_evaluation_members`
--

LOCK TABLES `annual_evaluation_members` WRITE;
/*!40000 ALTER TABLE `annual_evaluation_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `annual_evaluation_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `annual_evaluation_versions`
--

DROP TABLE IF EXISTS `annual_evaluation_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annual_evaluation_versions` (
  `version_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `finalized_by` int(11) NOT NULL,
  `finalized_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text NOT NULL,
  `snapshot_json` longtext NOT NULL,
  PRIMARY KEY (`version_id`),
  UNIQUE KEY `annual_version` (`member_id`,`version_number`),
  CONSTRAINT `annual_version_member` FOREIGN KEY (`member_id`) REFERENCES `annual_evaluation_members` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annual_evaluation_versions`
--

LOCK TABLES `annual_evaluation_versions` WRITE;
/*!40000 ALTER TABLE `annual_evaluation_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `annual_evaluation_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `annual_kra_approvals`
--

DROP TABLE IF EXISTS `annual_kra_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annual_kra_approvals` (
  `approval_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text NOT NULL,
  `ratings_json` longtext NOT NULL,
  `rater_signature_data` longtext DEFAULT NULL,
  `rater_signed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`approval_id`),
  KEY `annual_kra_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annual_kra_approvals`
--

LOCK TABLES `annual_kra_approvals` WRITE;
/*!40000 ALTER TABLE `annual_kra_approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `annual_kra_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `annual_peer_submissions`
--

DROP TABLE IF EXISTS `annual_peer_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annual_peer_submissions` (
  `submission_id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `rater_employee_id` int(11) NOT NULL,
  `target_employee_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `scores_json` text NOT NULL,
  `rater_signature_data` longtext DEFAULT NULL,
  `rater_signed_at` datetime DEFAULT NULL,
  `is_late` tinyint(4) NOT NULL DEFAULT 0,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `annual_peer_once` (`cycle_id`,`rater_employee_id`,`target_employee_id`),
  KEY `annual_peer_target` (`cycle_id`,`target_employee_id`),
  CONSTRAINT `annual_peer_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `annual_evaluation_cycles` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annual_peer_submissions`
--

LOCK TABLES `annual_peer_submissions` WRITE;
/*!40000 ALTER TABLE `annual_peer_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `annual_peer_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `annual_required_raters`
--

DROP TABLE IF EXISTS `annual_required_raters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annual_required_raters` (
  `cycle_id` int(11) NOT NULL,
  `target_employee_id` int(11) NOT NULL,
  `rater_employee_id` int(11) NOT NULL,
  PRIMARY KEY (`cycle_id`,`target_employee_id`,`rater_employee_id`),
  KEY `target_employee_id` (`target_employee_id`),
  KEY `rater_employee_id` (`rater_employee_id`),
  CONSTRAINT `annual_required_raters_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `annual_evaluation_cycles` (`cycle_id`),
  CONSTRAINT `annual_required_raters_ibfk_2` FOREIGN KEY (`target_employee_id`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `annual_required_raters_ibfk_3` FOREIGN KEY (`rater_employee_id`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annual_required_raters`
--

LOCK TABLES `annual_required_raters` WRITE;
/*!40000 ALTER TABLE `annual_required_raters` DISABLE KEYS */;
/*!40000 ALTER TABLE `annual_required_raters` ENABLE KEYS */;
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
  `action_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `module_name` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `target_employee_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `previous_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `action_status` enum('Successful','Failed','Cancelled') NOT NULL DEFAULT 'Successful',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'LOGIN','Authentication','Authentication & Access',1,NULL,'Portal: HRIS Portal; account: admin; result: Successful.',NULL,NULL,102,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:17:32'),(2,1,'CREATE','Employee','Employee Management',1,1,'Admin created initial HR Manager employee: elena delgado',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:06'),(3,1,'CREATE','User','User & Access Management',2,NULL,'Created user: elena.delgado (HR Manager)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:13'),(4,1,'LOGOUT','Authentication','Authentication & Access',1,NULL,'User signed out.',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:15'),(5,2,'LOGIN','Authentication','Authentication & Access',2,1,'Portal: HRIS Portal; account: elena.delgado; result: Successful.',NULL,NULL,102,7,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:18'),(6,2,'UPDATE','Employee','Employee Management',1,1,'Imported/Updated via CSV: Elena Delgado',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:30'),(7,2,'CREATE','Employee','Employee Management',2,2,'Imported/Updated via CSV: Patricia Gomez',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:30'),(8,2,'CREATE','Employee','Employee Management',3,3,'Imported/Updated via CSV: Miguel Torres',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:30'),(9,2,'UPDATE','Employee','Employee Management',2,2,'Separated employee with status: AWOL, Effective: 2026-11-25',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:18:50'),(10,2,'UPDATE','Employee','Employee Management',2,2,'Reactivated employee and reset separation record',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:19:13'),(11,2,'LOGOUT','Authentication','Authentication & Access',2,NULL,'User signed out.',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:34:59'),(12,1,'LOGIN','Authentication','Authentication & Access',1,NULL,'Portal: HRIS Portal; account: admin; result: Successful.',NULL,NULL,102,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:35:01'),(13,1,'CREATE','User','User & Access Management',3,NULL,'Created user: HRD-001 (Employee)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:36:10'),(14,1,'CREATE','User','User & Access Management',4,NULL,'Created user: patricia.gomez (HR Supervisor)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:50:01'),(15,1,'CREATE','User','User & Access Management',5,NULL,'Created user: anjing (Admin)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:55:01'),(16,1,'UPDATE','User','User & Access Management',5,NULL,'Changed user account status to Inactive.','Active','Inactive',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:57:47'),(17,1,'DELETE','User','User & Access Management',5,NULL,'Deleted user account: anjing',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 15:58:06'),(18,1,'CHANGE_PASSWORD','User','User & Access Management',2,NULL,'Administrator reset an account password.',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 16:03:44'),(19,1,'CHANGE_PASSWORD','User','User & Access Management',2,NULL,'Administrator reset an account password.',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 16:04:57'),(20,1,'CREATE','User','User & Access Management',6,NULL,'Created user: miguel.torres (HR Staff)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 16:05:36'),(21,1,'CHANGE_PASSWORD','User','User & Access Management',2,NULL,'Administrator reset an account password.',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 16:07:09'),(22,1,'CHANGE_PASSWORD','User','User & Access Management',4,NULL,'Administrator reset an account password.',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36','Successful','2026-11-25 16:07:29');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `branch_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`branch_id`),
  KEY `idx_branch_status` (`is_active`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Agdangan Branch','Agdangan, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(2,'Alabat Branch','Alabat, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(3,'Alaminos Branch','Alaminos, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(4,'Angono Branch 1','Angono, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(5,'Angono Branch 2','Angono, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(6,'Antipolo Branch 2','Antipolo, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(7,'Antipolo Branch 3','Antipolo, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(8,'Apalit Branch','Apalit, Pampanga',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(9,'Apalit Branch 2','Apalit, Pampanga',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(10,'Atimonan Branch 1','Atimonan, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(11,'Atimonan Branch 2','Atimonan, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(12,'Bacoor Branch','Bacoor, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(13,'Balayan Branch','Balayan, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(14,'Banilad Branch','Cebu City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(15,'Baras Branch','Baras, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(16,'Bauan Branch','Bauan, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(17,'Biñan Branch','Biñan, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(18,'Binangonan Branch','Binangonan, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(19,'Binangonan Branch 2','Binangonan, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(20,'Binangonan Branch 3','Binangonan, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(21,'Boac Branch','Boac, Marinduque',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(22,'Butuan Branch 1','Butuan City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(23,'Cabana Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(24,'Cabuyao Branch 1','Cabuyao, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(25,'Cabuyao Branch 2','Cabuyao, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(26,'Cabuyao Branch 3','Cabuyao, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(27,'Cainta Branch','Cainta, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(28,'Calamba Branch 1','Calamba, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(29,'Calamba Branch 2','Calamba, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(30,'Calatagan Branch','Calatagan, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(31,'Calauag Branch','Calauag, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(32,'Calauan Branch','Calauan, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(33,'Candelaria Branch 1','Candelaria, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(34,'Candelaria Branch 2','Candelaria, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(35,'Carmona Branch','Carmona, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(36,'Catanauan Branch','Catanauan, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(37,'Cavite City Branch','Cavite City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(38,'CDO Branch 1','Cagayan de Oro',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(39,'CDO Branch 3','Cagayan de Oro',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(40,'CM Recto Branch','Manila',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(41,'Commonwealth Branch','Quezon City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(42,'Cuenca Branch','Cuenca, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(43,'Daet Branch','Daet, Camarines Norte',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(44,'Daet Branch 2','Daet, Camarines Norte',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(45,'Dalahican Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(46,'Dasmariñas Branch','Dasmariñas, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(47,'Dolores Branch','Dolores, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(48,'Enriquez Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(49,'General Trias Branch 1','Gen. Trias, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(50,'General Trias Branch 2','Gen. Trias, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(51,'GMA Branch','GMA, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(52,'Gomez Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(53,'Guinyangan Branch','Guinyangan, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(54,'Gulang-Gulang Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(55,'Gumaca Branch','Gumaca, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(56,'Indang Branch','Indang, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(57,'Infanta Branch','Infanta, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(58,'Iyam Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(59,'Kawit Branch','Kawit, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(60,'Labangon Branch 2','Cebu City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(61,'Lapasan Branch','Cagayan de Oro',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(62,'Lapu-Lapu Branch 1','Lapu-Lapu City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(63,'Lapu-Lapu Branch 2','Lapu-Lapu City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(64,'Las Piñas Branch 2','Las Piñas City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(65,'Laurel Branch','Laurel, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(66,'Lemery Branch','Lemery, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(67,'Lian Branch','Lian, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(68,'Liliw Branch','Liliw, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(69,'Lipa Branch 1','Lipa, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(70,'Lipa TM Kalaw Branch','Lipa, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(71,'Lopez Branch','Lopez, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(72,'Los Baños Branch','Los Baños, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(73,'Lubao Branch','Lubao, Pampanga',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(74,'Lucban Branch 1','Lucban, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(75,'Lucban Branch 2','Lucban, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(76,'Luisiana Branch','Luisiana, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(77,'Lumban Branch','Lumban, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(78,'Macalelon Branch','Macalelon, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(79,'Magdalena Branch','Magdalena, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(80,'Majayjay Branch','Majayjay, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(81,'Mariveles Branch','Mariveles, Bataan',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(82,'Mataas na Kahoy Branch','Mataas na Kahoy, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(83,'Mauban Branch','Mauban, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(84,'Mendez Branch','Mendez, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(85,'Mercedes Branch','Daet, Camarines Norte',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(86,'Merchan Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(87,'Mogpog Branch','Mogpog, Marinduque',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(88,'Morong Branch','Morong, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(89,'Mulanay Branch','Mulanay, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(90,'Muntinlupa Branch 1','Muntinlupa City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(91,'Muntinlupa Branch 2','Muntinlupa City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(92,'Nagcarlan Branch','Nagcarlan, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(93,'Naic Branch','Naic, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(94,'Nasugbu Branch','Nasugbu, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(95,'Obando Branch','Obando, Bulacan',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(96,'Pacita Branch','San Pedro, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(97,'Paete Branch','Paete, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(98,'Pagbilao Branch','Pagbilao, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(99,'Pagbilao Branch 2','Pagbilao, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(100,'Pagsanjan Branch','Pagsanjan, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(101,'Paracale Branch','Paracale, Camarines Norte',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(102,'Raquel Pawnshop Main Office','RGC Building, Diversion Road, Sitio 1 Barangay Mayuwi Tayabas City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(103,'Parañaque Branch','Parañaque City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(104,'Pasig Branch 1','Pasig City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(105,'Perez Branch','Perez, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(106,'Pililla Branch','Pililla, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(107,'Plaridel Branch','Plaridel, Bulacan',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(108,'Profugo Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(109,'Quezon Avenue Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(110,'Real Branch','Real, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(111,'Retiro Branch','Quezon City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(112,'Rodriguez Branch','Rodriguez, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(113,'Rosario Branch','Rosario, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(114,'San Andres Branch','San Andres, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(115,'San Francisco Branch','San Francisco, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(116,'San Juan Branch','San Juan, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(117,'San Mateo Banaba Branch','San Mateo, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(118,'San Mateo Branch 2','San Mateo, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(119,'San Narciso Branch','San Narciso, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(120,'San Pablo Branch','San Pablo, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(121,'San Pascual Branch','San Pascual, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(122,'San Pedro Branch','San Pedro, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(123,'Sariaya Branch 1','Sariaya, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(124,'Sariaya Branch 2','Sariaya, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(125,'Silang Branch','Silang, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(126,'Siniloan Branch','Siniloan, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(127,'Sta. Cruz Branch','Sta. Cruz, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(128,'Sta. Cruz, M. Branch','Sta. Cruz, Marinduque',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(129,'Sta. Elena Branch','Sta. Elena, Camarines Norte',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(130,'Sta. Maria Branch','Sta. Maria, Bulacan',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(131,'Sta. Rosa Branch','Sta. Rosa, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(132,'Sto. Tomas Branch','Sto. Tomas, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(133,'Taal Branch','Taal, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(134,'Tagkawayan Branch','Tagkawayan, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(135,'Talipan Branch','Pagbilao, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(136,'Talisay Branch','Talisay, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(137,'Tanauan Branch','Tanauan, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(138,'Tanay Branch','Tanay, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(139,'Tanay Branch 2','Tanay, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(140,'Tanza Branch','Tanza, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(141,'Tayabas Branch 1','Tayabas City, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(142,'Tayabas Branch 2','Tayabas City, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(143,'Taytay Branch','Taytay, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(144,'Teresa Branch','Teresa, Rizal',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(145,'Ternate Branch','Ternate, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(146,'Tiaong Branch','Tiaong, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(147,'Trece Martires Branch','Trece Martires, Cavite',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(148,'Tuy Branch','Tuy, Batangas',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(149,'Unisan Branch','Unisan, Quezon',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(150,'Victoria Branch','Victoria, Laguna',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(151,'Zamora Branch','Lucena City',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL);
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `career_movements`
--

DROP TABLE IF EXISTS `career_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `career_movements` (
  `movement_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `movement_type` enum('Promotion','Transfer','Demotion','Role Change') NOT NULL,
  `previous_position` varchar(100) DEFAULT NULL,
  `new_position` varchar(100) NOT NULL,
  `previous_branch_id` int(11) DEFAULT NULL,
  `new_branch_id` int(11) DEFAULT NULL,
  `previous_department_name` varchar(150) DEFAULT NULL,
  `new_department_name` varchar(150) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `logged_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `decision_date` datetime DEFAULT NULL,
  `manager_comments` text DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `is_applied` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`movement_id`),
  KEY `fk_career_progression_employee` (`employee_id`),
  KEY `fk_career_progression_logger` (`logged_by`),
  KEY `fk_career_progression_approver` (`approved_by`),
  KEY `fk_career_progression_prev_branch` (`previous_branch_id`),
  KEY `fk_career_progression_new_branch` (`new_branch_id`),
  CONSTRAINT `fk_career_progression_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_career_progression_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_career_progression_logger` FOREIGN KEY (`logged_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_career_progression_new_branch` FOREIGN KEY (`new_branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_career_progression_prev_branch` FOREIGN KEY (`previous_branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `career_movements`
--

LOCK TABLES `career_movements` WRITE;
/*!40000 ALTER TABLE `career_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `career_movements` ENABLE KEYS */;
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
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`department_id`),
  KEY `idx_department_status` (`is_active`,`deleted_at`),
  KEY `idx_department_name` (`department_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Acquired Properties','Handles acquired property coordination, sales, and support.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(2,'Audit','Handles internal audit and compliance checking.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(3,'Business Development','Handles growth programs and business expansion work.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(4,'Compliance','Handles regulatory and internal compliance work.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(5,'Finance','Handles financial management, accounting, and treasury.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(6,'General Services','Handles facilities, logistics, inventory, and support services.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(7,'Human Resources','Handles personnel management and recruitment.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(8,'Information Technology','Handles systems, technical support, and helpdesk services.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(9,'Marketing','Handles marketing programs and brand communications.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(10,'Office of the President','Handles executive leadership and direct executive support.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(11,'Operations','Handles branch and field operations.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL),(12,'Purchasing','Handles procurement and supplier management.',1,'2026-11-25 15:17:29','2026-11-25 15:17:29',NULL);
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_addresses`
--

DROP TABLE IF EXISTS `employee_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `address_type` enum('Residential','Permanent') NOT NULL,
  `region` varchar(150) DEFAULT NULL,
  `house_no` varchar(100) DEFAULT NULL,
  `street` varchar(150) DEFAULT NULL,
  `subdivision` varchar(150) DEFAULT NULL,
  `barangay` varchar(150) DEFAULT NULL,
  `city` varchar(150) DEFAULT NULL,
  `province` varchar(150) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`address_id`),
  UNIQUE KEY `uniq_employee_address_type` (`employee_id`,`address_type`),
  CONSTRAINT `fk_addresses_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_addresses`
--

LOCK TABLES `employee_addresses` WRITE;
/*!40000 ALTER TABLE `employee_addresses` DISABLE KEYS */;
INSERT INTO `employee_addresses` VALUES (1,1,'Residential','Region IV-A (CALABARZON)','14','Quezon Avenue','City Proper','San Isidro','Tayabas City','Quezon','4327','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,1,'Permanent','Region IV-A (CALABARZON)','14','Quezon Avenue','City Proper','San Isidro','Tayabas City','Quezon','4327','2026-11-25 15:18:29','2026-11-25 15:18:29'),(3,2,'Residential','National Capital Region (NCR)','25','Ortigas Avenue','Central District','Bagong Ilog','Pasig City','Metro Manila','1600','2026-11-25 15:18:30','2026-11-25 15:18:30'),(4,2,'Permanent','Region III (Central Luzon)','25','Mabini Street','Central District','Sangitan East','Cabanatuan City','Nueva Ecija','3100','2026-11-25 15:18:30','2026-11-25 15:18:30'),(5,3,'Residential','National Capital Region (NCR)','38','General Luna Street','Central District','Ususan','Taguig City','Metro Manila','1639','2026-11-25 15:18:30','2026-11-25 15:18:30'),(6,3,'Permanent','Region IV-A (CALABARZON)','38','General Luna Street','Central District','Burol','Dasmarinas City','Cavite','4114','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_change_requests`
--

DROP TABLE IF EXISTS `employee_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_change_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `changes_json` longtext NOT NULL,
  `change_summary` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `manager_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `idx_ecr_status` (`status`,`employee_id`),
  KEY `fk_ecr_employee` (`employee_id`),
  KEY `fk_ecr_submitter` (`submitted_by`),
  KEY `fk_ecr_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_ecr_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ecr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ecr_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_change_requests`
--

LOCK TABLES `employee_change_requests` WRITE;
/*!40000 ALTER TABLE `employee_change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_children`
--

DROP TABLE IF EXISTS `employee_children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_children` (
  `child_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`child_id`),
  KEY `fk_children_employee` (`employee_id`),
  CONSTRAINT `fk_children_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_children`
--

LOCK TABLES `employee_children` WRITE;
/*!40000 ALTER TABLE `employee_children` DISABLE KEYS */;
INSERT INTO `employee_children` VALUES (1,1,'Delgado','Sofia','Santos','2018-03-04','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,2,'Gomez','Mika','Luna','2015-11-28','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_children` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_contacts`
--

DROP TABLE IF EXISTS `employee_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_contacts` (
  `contact_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `telephone_number` varchar(20) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `personal_email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`contact_id`),
  UNIQUE KEY `uniq_contacts_employee` (`employee_id`),
  CONSTRAINT `fk_contacts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_contacts`
--

LOCK TABLES `employee_contacts` WRITE;
/*!40000 ALTER TABLE `employee_contacts` DISABLE KEYS */;
INSERT INTO `employee_contacts` VALUES (2,1,'(042) 710-0101','09161234567','elena.delgado@example.com','2026-11-25 15:18:29','2026-11-25 15:18:29'),(3,2,'(02) 8700-3001','09201234567','patricia.gomez@example.com','2026-11-25 15:18:30','2026-11-25 15:18:30'),(4,3,'(02) 8700-3002','09211234567','miguel.torres@example.com','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_details`
--

DROP TABLE IF EXISTS `employee_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `height_m` decimal(4,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `citizenship` varchar(100) DEFAULT 'Filipino',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`detail_id`),
  UNIQUE KEY `uniq_details_employee` (`employee_id`),
  CONSTRAINT `fk_details_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_details`
--

LOCK TABLES `employee_details` WRITE;
/*!40000 ALTER TABLE `employee_details` DISABLE KEYS */;
INSERT INTO `employee_details` VALUES (1,1,1.68,62.00,'A+','Filipino','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,2,1.58,52.00,'AB+','Filipino','2026-11-25 15:18:30','2026-11-25 15:18:30'),(3,3,1.72,70.00,'O-','Filipino','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_disclosures`
--

DROP TABLE IF EXISTS `employee_disclosures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_disclosures` (
  `disclosure_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `is_related_to_company` tinyint(1) DEFAULT 0,
  `related_details` text DEFAULT NULL,
  `has_admin_offense` tinyint(1) DEFAULT 0,
  `admin_offense_details` text DEFAULT NULL,
  `has_criminal_charge` tinyint(1) DEFAULT 0,
  `criminal_charge_details` text DEFAULT NULL,
  `has_criminal_conviction` tinyint(1) DEFAULT 0,
  `criminal_conviction_details` text DEFAULT NULL,
  `has_been_separated` tinyint(1) DEFAULT 0,
  `separation_details` text DEFAULT NULL,
  `is_pwd` tinyint(1) DEFAULT 0,
  `pwd_details` text DEFAULT NULL,
  `is_solo_parent` tinyint(1) DEFAULT 0,
  `solo_parent_details` text DEFAULT NULL,
  `has_recent_hospital` tinyint(1) DEFAULT 0,
  `hospital_details` text DEFAULT NULL,
  `has_current_treatment` tinyint(1) DEFAULT 0,
  `treatment_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`disclosure_id`),
  UNIQUE KEY `uniq_disclosures_employee` (`employee_id`),
  CONSTRAINT `fk_disclosures_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_disclosures`
--

LOCK TABLES `employee_disclosures` WRITE;
/*!40000 ALTER TABLE `employee_disclosures` DISABLE KEYS */;
INSERT INTO `employee_disclosures` VALUES (1,1,0,'',0,'',0,'',0,'',0,'',0,'',0,'',0,'',0,'','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,2,0,'',0,'',0,'',0,'',0,'',0,'',0,'',0,'',0,'','2026-11-25 15:18:30','2026-11-25 15:18:30'),(3,3,0,'',0,'',0,'',0,'',0,'',0,'',0,'',0,'',0,'','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_disclosures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_edit_history`
--

DROP TABLE IF EXISTS `employee_edit_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_edit_history` (
  `edit_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `edited_by` int(11) NOT NULL,
  `editor_name` varchar(150) NOT NULL,
  `editor_role` varchar(50) NOT NULL,
  `step_number` int(11) DEFAULT NULL,
  `step_name` varchar(100) DEFAULT NULL,
  `change_summary` text DEFAULT NULL,
  `changes_json` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`edit_id`),
  KEY `idx_eeh_emp` (`employee_id`),
  KEY `idx_eeh_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_edit_history`
--

LOCK TABLES `employee_edit_history` WRITE;
/*!40000 ALTER TABLE `employee_edit_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_edit_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_education`
--

DROP TABLE IF EXISTS `employee_education`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_education` (
  `education_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `education_level` enum('Elementary','Secondary','Senior High School','Vocational','College','Graduate Studies') NOT NULL,
  `school_name` varchar(255) DEFAULT NULL,
  `degree_course` varchar(255) DEFAULT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `highest_level_units` varchar(100) DEFAULT NULL,
  `year_graduated` varchar(10) DEFAULT NULL,
  `honors_received` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`education_id`),
  KEY `fk_edu_employee` (`employee_id`),
  CONSTRAINT `fk_edu_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_education`
--

LOCK TABLES `employee_education` WRITE;
/*!40000 ALTER TABLE `employee_education` DISABLE KEYS */;
INSERT INTO `employee_education` VALUES (1,1,'Elementary','Tayabas East Central School','',NULL,NULL,NULL,'2002',NULL,'2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,1,'Secondary','Tayabas Western Academy','',NULL,NULL,NULL,'2006',NULL,'2026-11-25 15:18:29','2026-11-25 15:18:29'),(3,1,'College','University of the Philippines','BS Psychology',NULL,NULL,NULL,'2012',NULL,'2026-11-25 15:18:29','2026-11-25 15:18:29'),(4,1,'Graduate Studies','Ateneo de Manila University','MA Human Resource Management',NULL,NULL,NULL,'2016',NULL,'2026-11-25 15:18:29','2026-11-25 15:18:29'),(5,2,'Elementary','Pasig Elementary School','',NULL,NULL,NULL,'2001',NULL,'2026-11-25 15:18:30','2026-11-25 15:18:30'),(6,2,'Secondary','Rizal High School','',NULL,NULL,NULL,'2005',NULL,'2026-11-25 15:18:30','2026-11-25 15:18:30'),(7,2,'College','Ateneo de Manila University','BS Management',NULL,NULL,NULL,'2012',NULL,'2026-11-25 15:18:30','2026-11-25 15:18:30'),(8,3,'Elementary','Caloocan Central Elementary School','',NULL,NULL,NULL,'2012',NULL,'2026-11-25 15:18:30','2026-11-25 15:18:30'),(9,3,'Secondary','Caloocan High School','',NULL,NULL,NULL,'2016',NULL,'2026-11-25 15:18:30','2026-11-25 15:18:30'),(10,3,'Senior High School','Caloocan City Senior High School','Humanities and Social Sciences',NULL,NULL,NULL,'2018',NULL,'2026-11-25 15:18:30','2026-11-25 15:18:30'),(11,3,'College','University of Santo Tomas','BS Human Resource Management',NULL,NULL,NULL,'2021',NULL,'2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_education` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_eligibility`
--

DROP TABLE IF EXISTS `employee_eligibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_eligibility` (
  `eligibility_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `license_title` varchar(255) DEFAULT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `date_of_exam` date DEFAULT NULL,
  `place_of_exam` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`eligibility_id`),
  KEY `fk_elig_employee` (`employee_id`),
  CONSTRAINT `fk_elig_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_eligibility`
--

LOCK TABLES `employee_eligibility` WRITE;
/*!40000 ALTER TABLE `employee_eligibility` DISABLE KEYS */;
INSERT INTO `employee_eligibility` VALUES (1,1,'Career Service Professional Eligibility',NULL,NULL,'SAMPLE-CSP-101845',NULL,'','2026-11-25 15:18:29'),(2,2,'Career Service Professional Eligibility',NULL,NULL,'SAMPLE-CSP-301672',NULL,'','2026-11-25 15:18:30'),(3,3,'Certified Human Resource Associate',NULL,NULL,'SAMPLE-CHRA-30219',NULL,'','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_eligibility` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_emergency_contacts`
--

DROP TABLE IF EXISTS `employee_emergency_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_emergency_contacts` (
  `emergency_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `contact_name` varchar(150) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`emergency_id`),
  KEY `idx_emergency_employee` (`employee_id`),
  CONSTRAINT `fk_emergency_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_emergency_contacts`
--

LOCK TABLES `employee_emergency_contacts` WRITE;
/*!40000 ALTER TABLE `employee_emergency_contacts` DISABLE KEYS */;
INSERT INTO `employee_emergency_contacts` VALUES (1,1,'Marco Antonio Delgado','Spouse','09161111111',0,'2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,2,'Carlos Gomez','Father','09175556666',0,'2026-11-25 15:18:30','2026-11-25 15:18:30'),(3,3,'Rosa Torres','Mother','09185557777',0,'2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_emergency_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_family`
--

DROP TABLE IF EXISTS `employee_family`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_family` (
  `family_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `member_type` enum('Spouse','Father','Mother') NOT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `name_extension` varchar(10) DEFAULT NULL,
  `occupation` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`family_id`),
  UNIQUE KEY `uniq_family_member_type` (`employee_id`,`member_type`),
  CONSTRAINT `fk_family_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_family`
--

LOCK TABLES `employee_family` WRITE;
/*!40000 ALTER TABLE `employee_family` DISABLE KEYS */;
INSERT INTO `employee_family` VALUES (1,1,'Spouse',NULL,'Marco Antonio Delgado',NULL,NULL,'Civil Engineer','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,1,'Father','Delgado','Santiago','Rodriguez','','Retired Government Officer','2026-11-25 15:18:29','2026-11-25 15:18:29'),(3,1,'Mother','Santos','Carmen','Reyes',NULL,'Retired Teacher','2026-11-25 15:18:29','2026-11-25 15:18:29'),(4,2,'Father','Gomez','Carlos','Reyes','','Business Owner','2026-11-25 15:18:30','2026-11-25 15:18:30'),(5,2,'Mother','Luna','Rosario','Dizon',NULL,'Educator','2026-11-25 15:18:30','2026-11-25 15:18:30'),(6,3,'Father','Torres','Manuel','Pascual','','Government Employee','2026-11-25 15:18:30','2026-11-25 15:18:30'),(7,3,'Mother','Cruz','Rosa','Santos',NULL,'Nurse','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_family` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_government_ids`
--

DROP TABLE IF EXISTS `employee_government_ids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_government_ids` (
  `id_entry_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `sss_number` varchar(50) DEFAULT NULL,
  `philhealth_number` varchar(50) DEFAULT NULL,
  `pagibig_number` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_entry_id`),
  UNIQUE KEY `uniq_ids_employee` (`employee_id`),
  CONSTRAINT `fk_ids_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_government_ids`
--

LOCK TABLES `employee_government_ids` WRITE;
/*!40000 ALTER TABLE `employee_government_ids` DISABLE KEYS */;
INSERT INTO `employee_government_ids` VALUES (1,1,'33-0234567-0','12-787654321-5','1210-6666-7788','023-456-789-001','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,2,'33-4234567-4','12-687654321-3','1210-2266-7788','423-456-789-002','2026-11-25 15:18:30','2026-11-25 15:18:30'),(3,3,'33-5234567-5','12-587654321-4','1210-1166-7788','523-456-789-003','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_government_ids` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_liabilities`
--

DROP TABLE IF EXISTS `employee_liabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_liabilities` (
  `liability_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `nature_of_liability` varchar(255) DEFAULT NULL,
  `creditor_name` varchar(255) DEFAULT NULL,
  `outstanding_balance` decimal(14,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`liability_id`),
  KEY `fk_liab_employee` (`employee_id`),
  CONSTRAINT `fk_liab_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_liabilities`
--

LOCK TABLES `employee_liabilities` WRITE;
/*!40000 ALTER TABLE `employee_liabilities` DISABLE KEYS */;
INSERT INTO `employee_liabilities` VALUES (1,1,'Housing loan','',1800000.00,'2026-11-25 15:18:29'),(2,2,'Housing loan','',1800000.00,'2026-11-25 15:18:30'),(3,3,'Personal loan','',280000.00,'2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_liabilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_memberships`
--

DROP TABLE IF EXISTS `employee_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_memberships` (
  `membership_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `organization_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`membership_id`),
  KEY `fk_member_employee` (`employee_id`),
  CONSTRAINT `fk_member_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_memberships`
--

LOCK TABLES `employee_memberships` WRITE;
/*!40000 ALTER TABLE `employee_memberships` DISABLE KEYS */;
INSERT INTO `employee_memberships` VALUES (1,1,'People Management Association of the Philippines','2026-11-25 15:18:29'),(2,2,'Philippine Society for Talent Development','2026-11-25 15:18:30'),(3,3,'Human Resource Educators Association of the Philippines','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_memberships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_pds_submissions`
--

DROP TABLE IF EXISTS `employee_pds_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_pds_submissions` (
  `submission_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `status` enum('Draft','Submitted','Under Review','Approved','Rejected','Changes Requested') DEFAULT 'Draft',
  `hr_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`submission_id`),
  KEY `fk_pds_employee` (`employee_id`),
  KEY `fk_pds_submitter` (`submitted_by`),
  KEY `fk_pds_reviewer` (`reviewed_by`),
  KEY `idx_pds_status` (`status`,`employee_id`),
  KEY `idx_pds_submitted_at` (`submitted_at`),
  CONSTRAINT `fk_pds_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pds_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pds_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_pds_submissions`
--

LOCK TABLES `employee_pds_submissions` WRITE;
/*!40000 ALTER TABLE `employee_pds_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_pds_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_personal_properties`
--

DROP TABLE IF EXISTS `employee_personal_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_personal_properties` (
  `property_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `year_acquired` varchar(10) DEFAULT NULL,
  `acquisition_cost` decimal(14,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`property_id`),
  KEY `fk_pers_employee` (`employee_id`),
  CONSTRAINT `fk_pers_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_personal_properties`
--

LOCK TABLES `employee_personal_properties` WRITE;
/*!40000 ALTER TABLE `employee_personal_properties` DISABLE KEYS */;
INSERT INTO `employee_personal_properties` VALUES (1,1,'2019 family vehicle','',1350000.00,'2026-11-25 15:18:29'),(2,2,'2021 family vehicle','',1100000.00,'2026-11-25 15:18:30'),(3,3,'Motorcycle','',120000.00,'2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_personal_properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_profile_correction_requests`
--

DROP TABLE IF EXISTS `employee_profile_correction_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_profile_correction_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `field_key` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `idx_profile_correction_employee` (`employee_id`,`created_at`),
  KEY `fk_profile_correction_submitter` (`submitted_by`),
  CONSTRAINT `fk_profile_correction_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_correction_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_profile_correction_requests`
--

LOCK TABLES `employee_profile_correction_requests` WRITE;
/*!40000 ALTER TABLE `employee_profile_correction_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_profile_correction_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_real_properties`
--

DROP TABLE IF EXISTS `employee_real_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_real_properties` (
  `property_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `kind` varchar(100) DEFAULT NULL,
  `exact_location` text DEFAULT NULL,
  `assessed_value` decimal(14,2) DEFAULT NULL,
  `market_value` decimal(14,2) DEFAULT NULL,
  `acquisition_year_mode` varchar(100) DEFAULT NULL,
  `acquisition_cost` decimal(14,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`property_id`),
  KEY `fk_real_employee` (`employee_id`),
  CONSTRAINT `fk_real_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_real_properties`
--

LOCK TABLES `employee_real_properties` WRITE;
/*!40000 ALTER TABLE `employee_real_properties` DISABLE KEYS */;
INSERT INTO `employee_real_properties` VALUES (1,1,'Residential house in Tayabas City','','',NULL,2500000.00,NULL,NULL,'2026-11-25 15:18:29'),(2,2,'Residential house in Pasig City','','',NULL,2800000.00,NULL,NULL,'2026-11-25 15:18:30'),(3,3,'Vacant lot in Cavite','','',NULL,950000.00,NULL,NULL,'2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_real_properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_recognitions`
--

DROP TABLE IF EXISTS `employee_recognitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_recognitions` (
  `recognition_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `recognition_title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`recognition_id`),
  KEY `fk_recog_employee` (`employee_id`),
  CONSTRAINT `fk_recog_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_recognitions`
--

LOCK TABLES `employee_recognitions` WRITE;
/*!40000 ALTER TABLE `employee_recognitions` DISABLE KEYS */;
INSERT INTO `employee_recognitions` VALUES (1,1,'Outstanding HR Leadership Award','2026-11-25 15:18:29'),(2,2,'Excellence in Employee Relations Award','2026-11-25 15:18:30'),(3,3,'HR Service Excellence Award','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_recognitions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_references`
--

DROP TABLE IF EXISTS `employee_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_references` (
  `reference_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `reference_name` varchar(200) DEFAULT NULL,
  `reference_address` text DEFAULT NULL,
  `reference_telephone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reference_id`),
  KEY `fk_ref_employee` (`employee_id`),
  CONSTRAINT `fk_ref_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_references`
--

LOCK TABLES `employee_references` WRITE;
/*!40000 ALTER TABLE `employee_references` DISABLE KEYS */;
INSERT INTO `employee_references` VALUES (1,1,'Dr. Reynaldo Cruz','Pasig City, Metro Manila','09170001011','2026-11-25 15:18:29'),(2,1,'Marta Villanueva','Lucena City, Quezon','09170001012','2026-11-25 15:18:29'),(3,1,'Jose Bautista','Tayabas City, Quezon','09170001013','2026-11-25 15:18:29'),(4,2,'Atty. Rosa Fernandez','Pasig City, Metro Manila','09170003011','2026-11-25 15:18:30'),(5,2,'Lucia Ramos','Cabanatuan City, Nueva Ecija','09170003012','2026-11-25 15:18:30'),(6,2,'Daniel Mercado','Quezon City, Metro Manila','09170003013','2026-11-25 15:18:30'),(7,3,'Prof. Antonio Lopez','Caloocan City, Metro Manila','09170003021','2026-11-25 15:18:30'),(8,3,'Leah Villareal','Taguig City, Metro Manila','09170003022','2026-11-25 15:18:30'),(9,3,'Marco Garcia','Dasmarinas City, Cavite','09170003023','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_references` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_siblings`
--

DROP TABLE IF EXISTS `employee_siblings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_siblings` (
  `sibling_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sibling_id`),
  KEY `fk_siblings_employee` (`employee_id`),
  CONSTRAINT `fk_siblings_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_siblings`
--

LOCK TABLES `employee_siblings` WRITE;
/*!40000 ALTER TABLE `employee_siblings` DISABLE KEYS */;
INSERT INTO `employee_siblings` VALUES (1,1,'Delgado','Miguel','Santos','1992-08-10','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,2,'Gomez','Gabriela','Luna','1988-12-03','2026-11-25 15:18:30','2026-11-25 15:18:30'),(3,3,'Torres','Emmanuel','Cruz','1995-06-18','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_siblings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_skills`
--

DROP TABLE IF EXISTS `employee_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_skills` (
  `skill_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `skill_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`skill_id`),
  KEY `fk_skill_employee` (`employee_id`),
  CONSTRAINT `fk_skill_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_skills`
--

LOCK TABLES `employee_skills` WRITE;
/*!40000 ALTER TABLE `employee_skills` DISABLE KEYS */;
INSERT INTO `employee_skills` VALUES (1,1,'Strategic Human Resource Management','2026-11-25 15:18:29'),(2,2,'Performance Management','2026-11-25 15:18:30'),(3,3,'HR Records Administration','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_trainings`
--

DROP TABLE IF EXISTS `employee_trainings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_trainings` (
  `training_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `training_title` varchar(255) DEFAULT NULL,
  `training_type` varchar(100) DEFAULT NULL,
  `no_of_hours` decimal(7,2) DEFAULT NULL,
  `conducted_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`training_id`),
  KEY `fk_train_employee` (`employee_id`),
  CONSTRAINT `fk_train_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_trainings`
--

LOCK TABLES `employee_trainings` WRITE;
/*!40000 ALTER TABLE `employee_trainings` DISABLE KEYS */;
INSERT INTO `employee_trainings` VALUES (1,1,NULL,NULL,'Advanced HR Management Seminar','',40.00,'Philippine HR Association','2026-11-25 15:18:29'),(2,2,NULL,NULL,'Performance Management Workshop','',16.00,'Human Resources Institute','2026-11-25 15:18:30'),(3,3,NULL,NULL,'Recruitment Best Practices','',20.00,'HR Development Forum','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_trainings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_voluntary_work`
--

DROP TABLE IF EXISTS `employee_voluntary_work`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_voluntary_work` (
  `voluntary_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `organization_address` text DEFAULT NULL,
  `no_of_hours` decimal(7,2) DEFAULT NULL,
  `position_nature` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`voluntary_id`),
  KEY `fk_vol_employee` (`employee_id`),
  CONSTRAINT `fk_vol_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_voluntary_work`
--

LOCK TABLES `employee_voluntary_work` WRITE;
/*!40000 ALTER TABLE `employee_voluntary_work` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_voluntary_work` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_work_experience`
--

DROP TABLE IF EXISTS `employee_work_experience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_work_experience` (
  `work_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `job_title` varchar(150) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `monthly_salary` decimal(12,2) DEFAULT NULL,
  `appointment_status` varchar(100) DEFAULT NULL,
  `reason_for_leaving` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`work_id`),
  KEY `fk_work_employee` (`employee_id`),
  CONSTRAINT `fk_work_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_work_experience`
--

LOCK TABLES `employee_work_experience` WRITE;
/*!40000 ALTER TABLE `employee_work_experience` DISABLE KEYS */;
INSERT INTO `employee_work_experience` VALUES (1,1,'2012-01-01','2019-12-31','HR Specialist','Business Process Outsourcing Inc.',35000.00,'0','Accepted a management role','2026-11-25 15:18:29','2026-11-25 15:18:29'),(2,2,'2012-01-01','2016-12-31','HR Coordinator','Global Staffing Solutions',30000.00,'0','Accepted a supervisory role','2026-11-25 15:18:30','2026-11-25 15:18:30'),(3,3,'2021-01-01','2021-12-31','HR Assistant','Talent Quest Recruitment',22000.00,'0','Accepted a permanent HR role','2026-11-25 15:18:30','2026-11-25 15:18:30');
/*!40000 ALTER TABLE `employee_work_experience` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(30) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `name_extension` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Other','Preferred not to say') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Other') DEFAULT NULL,
  `hire_date` date NOT NULL,
  `job_title` varchar(150) DEFAULT NULL,
  `job_title_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `rank_category_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `reports_to` int(11) DEFAULT NULL,
  `employment_status` enum('OJT','Probationary','Project Based','Regular','Separated','Trainee','AWOL','Retirement','Death','Permanent or Total Disability','Resignation','Failed in Training','Termination for Cause') DEFAULT 'Regular',
  `probationary_regularization_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `probationary_eligible_evaluation_id` int(11) DEFAULT NULL,
  `probationary_eligible_at` datetime DEFAULT NULL,
  `regularization_effective_date` date DEFAULT NULL,
  `probationary_next_evaluation_type` varchar(20) DEFAULT NULL,
  `probationary_next_evaluation_due_date` date DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time') DEFAULT 'Full-time',
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `separation_date` date DEFAULT NULL,
  `separation_remarks` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `fk_employees_branch` (`branch_id`),
  KEY `fk_employees_job_title` (`job_title_id`),
  KEY `fk_employees_rank_category` (`rank_category_id`),
  KEY `idx_employee_names` (`last_name`,`first_name`),
  KEY `idx_employee_dept` (`department_id`,`branch_id`),
  KEY `idx_employee_reports_to` (`reports_to`),
  KEY `idx_employee_employment_status` (`employment_status`,`is_active`,`deleted_at`),
  KEY `idx_employee_job_title` (`job_title`),
  CONSTRAINT `fk_employees_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employees_job_title` FOREIGN KEY (`job_title_id`) REFERENCES `job_titles` (`job_title_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employees_rank_category` FOREIGN KEY (`rank_category_id`) REFERENCES `rank_categories` (`rank_category_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'HRD-001','Elena','Delgado','Santos','','1990-05-15','Tayabas, Quezon','Female','Married','2020-01-15','HR Manager I',700,7,3,102,NULL,'Regular',0,NULL,NULL,NULL,NULL,NULL,'Full-time',NULL,NULL,NULL,NULL,NULL,1,'2026-11-25 15:18:06','2026-11-25 15:18:29',NULL),(2,'HRD-002','Patricia','Gomez','Luna','','1989-11-08','Pasig, Metro Manila','Female','Widowed','2017-02-14','HR Supervisor I',705,7,4,102,NULL,'Regular',0,NULL,NULL,NULL,NULL,NULL,'Full-time',NULL,NULL,NULL,NULL,NULL,1,'2026-11-25 15:18:30','2026-11-25 15:19:13',NULL),(3,'HRD-003','Miguel','Torres','Cruz','','2000-04-17','Caloocan, Metro Manila','Male','Single','2022-01-05','HR Staff I',710,7,5,102,NULL,'Regular',0,NULL,NULL,NULL,NULL,NULL,'Full-time',NULL,NULL,NULL,NULL,NULL,1,'2026-11-25 15:18:30','2026-11-25 15:18:30',NULL);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_criteria`
--

DROP TABLE IF EXISTS `evaluation_criteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_criteria` (
  `criterion_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `section` enum('KRA','Behavior') DEFAULT 'KRA',
  `criterion_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `kpi_description` text DEFAULT NULL,
  `weight` decimal(5,2) NOT NULL,
  `scoring_method` enum('Scale_1_5','Scale_1_10','Percentage','Scale_1_4') DEFAULT 'Scale_1_4',
  `sort_order` int(11) DEFAULT 0,
  `is_custom` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`criterion_id`),
  KEY `fk_criteria_template` (`template_id`),
  CONSTRAINT `fk_criteria_template` FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates` (`template_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_criteria`
--

LOCK TABLES `evaluation_criteria` WRITE;
/*!40000 ALTER TABLE `evaluation_criteria` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_criteria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_dev_plans`
--

DROP TABLE IF EXISTS `evaluation_dev_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_dev_plans` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `improvement_area` text DEFAULT NULL,
  `support_needed` text DEFAULT NULL,
  `time_frame` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`plan_id`),
  KEY `fk_devplan_eval` (`evaluation_id`),
  CONSTRAINT `fk_devplan_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_dev_plans`
--

LOCK TABLES `evaluation_dev_plans` WRITE;
/*!40000 ALTER TABLE `evaluation_dev_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_dev_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_governance_approvers`
--

DROP TABLE IF EXISTS `evaluation_governance_approvers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_governance_approvers` (
  `governance_approver_id` int(11) NOT NULL AUTO_INCREMENT,
  `governance_type` enum('Board of Directors','Audit Committee','President','Division VP') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`governance_approver_id`),
  UNIQUE KEY `uq_governance_employee` (`governance_type`,`department_id`,`employee_id`),
  KEY `fk_governance_employee` (`employee_id`),
  KEY `fk_governance_user` (`user_id`),
  KEY `fk_governance_department` (`department_id`),
  CONSTRAINT `fk_governance_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_governance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_governance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_governance_approvers`
--

LOCK TABLES `evaluation_governance_approvers` WRITE;
/*!40000 ALTER TABLE `evaluation_governance_approvers` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_governance_approvers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_import_batches`
--

DROP TABLE IF EXISTS `evaluation_import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_import_batches` (
  `import_batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `uploaded_filename` varchar(255) NOT NULL,
  `import_mode` enum('Summary','Detailed') NOT NULL,
  `imported_by` int(11) NOT NULL,
  `imported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `successful_rows` int(11) NOT NULL DEFAULT 0,
  `failed_rows` int(11) NOT NULL DEFAULT 0,
  `status` enum('Preview','Imported','Cancelled','Rolled Back') NOT NULL DEFAULT 'Preview',
  `error_log` longtext DEFAULT NULL,
  PRIMARY KEY (`import_batch_id`),
  KEY `idx_import_batch_user` (`imported_by`),
  KEY `idx_import_batch_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_import_batches`
--

LOCK TABLES `evaluation_import_batches` WRITE;
/*!40000 ALTER TABLE `evaluation_import_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_import_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_import_errors`
--

DROP TABLE IF EXISTS `evaluation_import_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_import_errors` (
  `error_id` int(11) NOT NULL AUTO_INCREMENT,
  `import_batch_id` int(11) NOT NULL,
  `row_number` int(11) NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `error_message` text NOT NULL,
  `raw_data` longtext DEFAULT NULL,
  PRIMARY KEY (`error_id`),
  KEY `idx_import_error_batch` (`import_batch_id`),
  CONSTRAINT `fk_import_error_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `evaluation_import_batches` (`import_batch_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_import_errors`
--

LOCK TABLES `evaluation_import_errors` WRITE;
/*!40000 ALTER TABLE `evaluation_import_errors` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_import_errors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_package_audit`
--

DROP TABLE IF EXISTS `evaluation_package_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_package_audit` (
  `package_audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`package_audit_id`),
  KEY `idx_package_audit` (`package_id`,`created_at`),
  KEY `fk_pkg_audit_user` (`user_id`),
  CONSTRAINT `fk_pkg_audit_pkg` FOREIGN KEY (`package_id`) REFERENCES `evaluation_packages` (`package_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pkg_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_package_audit`
--

LOCK TABLES `evaluation_package_audit` WRITE;
/*!40000 ALTER TABLE `evaluation_package_audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_package_audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_package_members`
--

DROP TABLE IF EXISTS `evaluation_package_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_package_members` (
  `package_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `member_status` enum('Normal','Late Rejoined','Pending Supervisor Catchup','Pending HR Catchup','Catchup Endorsed','Catchup Complete') NOT NULL DEFAULT 'Normal',
  `joined_at_step` int(11) DEFAULT NULL,
  PRIMARY KEY (`package_id`,`evaluation_id`),
  KEY `fk_package_member_evaluation` (`evaluation_id`),
  CONSTRAINT `fk_package_member_evaluation` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_package_member_package` FOREIGN KEY (`package_id`) REFERENCES `evaluation_packages` (`package_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_package_members`
--

LOCK TABLES `evaluation_package_members` WRITE;
/*!40000 ALTER TABLE `evaluation_package_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_package_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_package_route_steps`
--

DROP TABLE IF EXISTS `evaluation_package_route_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_package_route_steps` (
  `package_route_step_id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `reviewer_employee_id` int(11) DEFAULT NULL,
  `reviewer_user_id` int(11) DEFAULT NULL,
  `step_label` varchar(160) NOT NULL,
  `step_type` enum('Consolidation','Review','Governance') NOT NULL DEFAULT 'Review',
  `eligible_role` varchar(50) DEFAULT NULL,
  `eligible_rank_category_id` int(11) DEFAULT NULL,
  `action_status` enum('Waiting','Pending','Approved','Returned','Skipped') NOT NULL DEFAULT 'Waiting',
  `claimed_at` datetime DEFAULT NULL,
  `acted_at` datetime DEFAULT NULL,
  `comments` text DEFAULT NULL,
  PRIMARY KEY (`package_route_step_id`),
  UNIQUE KEY `uq_package_route_step` (`package_id`,`step_order`),
  KEY `idx_route_reviewer` (`reviewer_user_id`,`action_status`),
  KEY `fk_route_employee` (`reviewer_employee_id`),
  CONSTRAINT `fk_route_employee` FOREIGN KEY (`reviewer_employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_route_package` FOREIGN KEY (`package_id`) REFERENCES `evaluation_packages` (`package_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_route_user` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_package_route_steps`
--

LOCK TABLES `evaluation_package_route_steps` WRITE;
/*!40000 ALTER TABLE `evaluation_package_route_steps` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_package_route_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_packages`
--

DROP TABLE IF EXISTS `evaluation_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_packages` (
  `package_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `evaluation_type` enum('Initial','Final','Quarterly','Annual') NOT NULL DEFAULT 'Annual',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `consolidator_employee_id` int(11) DEFAULT NULL,
  `shared_behavior_score` decimal(5,2) DEFAULT NULL,
  `current_step_order` int(11) DEFAULT NULL,
  `status` enum('Pending Self-Ratings','Pending Consolidation','Pending Review','Pending Audit Approval','Pending Board Approval','Approved and Applied','Returned','Cancelled') NOT NULL DEFAULT 'Pending Self-Ratings',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`package_id`),
  KEY `idx_package_status` (`status`,`current_step_order`),
  KEY `fk_package_template` (`template_id`),
  KEY `fk_package_consolidator` (`consolidator_employee_id`),
  KEY `idx_package_dept` (`department_id`),
  CONSTRAINT `fk_package_consolidator` FOREIGN KEY (`consolidator_employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_package_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  CONSTRAINT `fk_package_template` FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_packages`
--

LOCK TABLES `evaluation_packages` WRITE;
/*!40000 ALTER TABLE `evaluation_packages` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_scores`
--

DROP TABLE IF EXISTS `evaluation_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_scores` (
  `score_id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `criterion_id` int(11) NOT NULL,
  `score_value` decimal(5,2) NOT NULL,
  `weighted_score` decimal(5,2) NOT NULL,
  `supervisor_override_score` decimal(5,2) DEFAULT NULL,
  `supervisor_override_by` int(11) DEFAULT NULL,
  `supervisor_override_at` datetime DEFAULT NULL,
  `manager_override_score` decimal(5,2) DEFAULT NULL,
  `manager_override_by` int(11) DEFAULT NULL,
  `manager_override_at` datetime DEFAULT NULL,
  `dept_manager_override_score` decimal(5,2) DEFAULT NULL COMMENT 'Score adjusted by Department Manager',
  `dept_manager_override_by` int(11) DEFAULT NULL COMMENT 'User ID of Department Manager who adjusted',
  `dept_manager_override_at` datetime DEFAULT NULL COMMENT 'When Department Manager adjustment was made',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`score_id`),
  KEY `fk_score_eval` (`evaluation_id`),
  KEY `fk_score_criteria` (`criterion_id`),
  CONSTRAINT `fk_score_criteria` FOREIGN KEY (`criterion_id`) REFERENCES `evaluation_criteria` (`criterion_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_score_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_scores`
--

LOCK TABLES `evaluation_scores` WRITE;
/*!40000 ALTER TABLE `evaluation_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_template_positions`
--

DROP TABLE IF EXISTS `evaluation_template_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_template_positions` (
  `evaluation_template_position_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `job_title_id` int(11) NOT NULL,
  PRIMARY KEY (`evaluation_template_position_id`),
  UNIQUE KEY `uq_evaluation_template_position` (`template_id`,`job_title_id`),
  KEY `idx_evaluation_template_position` (`job_title_id`),
  CONSTRAINT `fk_etp_job_title` FOREIGN KEY (`job_title_id`) REFERENCES `job_titles` (`job_title_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_etp_template` FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates` (`template_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_template_positions`
--

LOCK TABLES `evaluation_template_positions` WRITE;
/*!40000 ALTER TABLE `evaluation_template_positions` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_template_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_templates`
--

DROP TABLE IF EXISTS `evaluation_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_templates` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `target_department` varchar(100) DEFAULT NULL,
  `target_department_id` int(11) DEFAULT NULL,
  `target_job_title_id` int(11) DEFAULT NULL,
  `behavior_framework_code` varchar(80) DEFAULT NULL,
  `behavior_framework_version` varchar(40) DEFAULT NULL,
  `evaluation_type` enum('Initial','Final','Quarterly','Annual') DEFAULT 'Annual',
  `individual_workflow` tinyint(4) NOT NULL DEFAULT 0,
  `assessment_from` date DEFAULT NULL,
  `assessment_to` date DEFAULT NULL,
  `kra_weight` decimal(5,2) DEFAULT 80.00,
  `behavior_weight` decimal(5,2) DEFAULT 20.00,
  `form_code` varchar(50) DEFAULT 'HRD Form-013.01',
  `revision_date` date DEFAULT NULL,
  `effective_date_form` date DEFAULT NULL,
  `status` enum('Draft','Active','Archived') DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`template_id`),
  KEY `fk_template_creator` (`created_by`),
  KEY `fk_template_department` (`target_department_id`),
  KEY `fk_template_position` (`target_job_title_id`),
  KEY `idx_template_status` (`status`,`deleted_at`),
  CONSTRAINT `fk_template_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_template_department` FOREIGN KEY (`target_department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_template_position` FOREIGN KEY (`target_job_title_id`) REFERENCES `job_titles` (`job_title_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_templates`
--

LOCK TABLES `evaluation_templates` WRITE;
/*!40000 ALTER TABLE `evaluation_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluations`
--

DROP TABLE IF EXISTS `evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluations` (
  `evaluation_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `evaluation_type` enum('Initial','Final','Quarterly','Annual') DEFAULT 'Annual',
  `evaluation_period_start` date DEFAULT NULL,
  `evaluation_period_end` date DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `endorsed_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `supervisor_confirmed_by` int(11) DEFAULT NULL,
  `supervisor_confirmed_date` datetime DEFAULT NULL,
  `dept_supervisor_confirmed_by` int(11) DEFAULT NULL,
  `dept_supervisor_confirmed_date` datetime DEFAULT NULL,
  `dept_manager_endorsed_by` int(11) DEFAULT NULL,
  `dept_manager_endorsed_date` datetime DEFAULT NULL,
  `supervisor_lock_user_id` int(11) DEFAULT NULL,
  `supervisor_lock_expires` datetime DEFAULT NULL,
  `manager_lock_user_id` int(11) DEFAULT NULL,
  `manager_lock_expires` datetime DEFAULT NULL,
  `supervisor_altered_scores` tinyint(1) DEFAULT 0,
  `sent_to_hr_date` datetime DEFAULT NULL,
  `sent_to_hr_by` int(11) DEFAULT NULL,
  `status` enum('Draft','Pending Self-Rating','Pending Reporting Review','Pending Dept Supervisor','Pending Dept Manager','Pending Supervisor','Pending HR Consolidation','Pending Team Consolidation','Pending Manager','Supervisor Confirmed','Approved','Imported','Rejected','Returned') DEFAULT 'Draft',
  `total_score` decimal(5,2) DEFAULT NULL,
  `kra_subtotal` decimal(5,2) DEFAULT NULL,
  `behavior_average` decimal(5,2) DEFAULT NULL,
  `performance_level` varchar(50) DEFAULT NULL,
  `submitted_date` datetime DEFAULT NULL,
  `endorsed_date` datetime DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `staff_comments` text DEFAULT NULL,
  `employee_consent_agreed` tinyint(1) DEFAULT 0,
  `employee_signature_data` longtext DEFAULT NULL,
  `employee_signed_at` datetime DEFAULT NULL,
  `supervisor_comments` text DEFAULT NULL,
  `dept_manager_comments` text DEFAULT NULL,
  `supervisor_rating` decimal(5,2) DEFAULT NULL,
  `manager_comments` text DEFAULT NULL,
  `manager_rating` decimal(5,2) DEFAULT NULL,
  `evaluator_comments` text DEFAULT NULL,
  `current_position` varchar(150) DEFAULT NULL,
  `months_in_position` int(11) DEFAULT NULL,
  `desired_position` varchar(150) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `career_growth_suited` tinyint(1) DEFAULT 0,
  `career_growth_details` text DEFAULT NULL,
  `hr_received_date` date DEFAULT NULL,
  `hr_received_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_historical` tinyint(1) NOT NULL DEFAULT 0,
  `record_source` varchar(40) NOT NULL DEFAULT 'System Workflow',
  `import_batch_id` int(11) DEFAULT NULL,
  `legacy_reference` varchar(150) DEFAULT NULL,
  `historical_remarks` text DEFAULT NULL,
  PRIMARY KEY (`evaluation_id`),
  KEY `fk_eval_employee` (`employee_id`),
  KEY `fk_eval_template` (`template_id`),
  KEY `fk_eval_submitter` (`submitted_by`),
  KEY `fk_eval_assigner` (`assigned_by`),
  KEY `fk_eval_endorser` (`endorsed_by`),
  KEY `fk_eval_approver` (`approved_by`),
  KEY `idx_eval_status` (`status`,`deleted_at`),
  KEY `idx_eval_date` (`approved_date`,`submitted_date`),
  CONSTRAINT `fk_eval_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eval_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eval_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eval_endorser` FOREIGN KEY (`endorsed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eval_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eval_template` FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates` (`template_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluations`
--

LOCK TABLES `evaluations` WRITE;
/*!40000 ALTER TABLE `evaluations` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_titles`
--

DROP TABLE IF EXISTS `job_titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_titles` (
  `job_title_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_title` varchar(200) NOT NULL,
  `rank_category_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_head` tinyint(1) DEFAULT 0,
  `reports_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`job_title_id`),
  KEY `reports_to` (`reports_to`),
  KEY `idx_job_title` (`job_title`),
  KEY `idx_rank_category` (`rank_category_id`),
  KEY `idx_department` (`department_id`),
  CONSTRAINT `job_titles_ibfk_1` FOREIGN KEY (`rank_category_id`) REFERENCES `rank_categories` (`rank_category_id`),
  CONSTRAINT `job_titles_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  CONSTRAINT `job_titles_ibfk_3` FOREIGN KEY (`reports_to`) REFERENCES `job_titles` (`job_title_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_titles`
--

LOCK TABLES `job_titles` WRITE;
/*!40000 ALTER TABLE `job_titles` DISABLE KEYS */;
INSERT INTO `job_titles` VALUES (100,'VP for Acquired Properties',1,1,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(101,'AP Manager I',3,1,1,1,100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(102,'AP Manager II',3,1,1,1,100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(103,'AP Manager III',3,1,1,1,100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(104,'AP Manager IV',3,1,1,1,100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(105,'AP Supervisor I',4,1,1,1,101,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(106,'AP Supervisor II',4,1,1,1,101,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(107,'AP Supervisor III',4,1,1,1,101,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(108,'AP Staff I',5,1,1,0,105,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(109,'AP Staff II',5,1,1,0,105,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(110,'AP Staff III',5,1,1,0,105,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(111,'AP Staff IV',5,1,1,0,105,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(112,'AP Staff V',5,1,1,0,105,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(113,'Sales Associate I',5,1,1,0,105,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(114,'Sales Associate II',5,1,1,0,105,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(200,'Audit Manager I',3,2,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(201,'Audit Manager II',3,2,1,1,200,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(202,'Audit Manager III',3,2,1,1,200,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(203,'Audit Supervisor I',4,2,1,1,201,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(204,'Audit Supervisor II',4,2,1,1,201,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(205,'Audit Supervisor III',4,2,1,1,201,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(206,'Auditor I',5,2,1,0,203,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(207,'Auditor II',5,2,1,0,203,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(208,'Auditor III',5,2,1,0,203,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(209,'Auditor IV',5,2,1,0,203,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(210,'Auditor V',5,2,1,0,203,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(300,'Business Development Officer I',3,3,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(302,'Business Development Staff I',5,3,1,0,300,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(303,'Business Development Staff II',5,3,1,0,300,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(400,'Compliance Supervisor I',4,4,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(401,'Compliance Supervisor II',4,4,1,0,400,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(402,'Compliance Supervisor III',4,4,1,0,400,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(403,'Compliance Staff I',5,4,1,0,401,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(404,'Compliance Staff II',5,4,1,0,401,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(405,'Compliance Staff III',5,4,1,0,401,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(500,'VP for Finance',1,5,1,0,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(501,'Accounting Manager I',3,5,1,1,500,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(502,'Accounting Supervisor I',4,5,1,0,501,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(503,'Accounting Supervisor II',4,5,1,0,501,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(504,'Accounting Supervisor III',4,5,1,0,501,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(505,'Accounting Supervisor IV',4,5,1,0,501,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(506,'Treasury Supervisor I',4,5,1,0,500,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(507,'Treasury Supervisor II',4,5,1,0,500,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(508,'Treasury Supervisor III',4,5,1,0,500,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(509,'Treasury Supervisor IV',4,5,1,0,500,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(510,'Treasury Supervisor V',4,5,1,0,500,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(511,'Accounting Staff I',5,5,1,0,502,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(512,'Accounting Staff II',5,5,1,0,502,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(513,'Accounting Staff III',5,5,1,0,502,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(514,'Accounting Staff IV',5,5,1,0,502,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(515,'Accounting Staff V',5,5,1,0,502,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(516,'Treasury Staff I',5,5,1,0,506,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(517,'Treasury Staff II',5,5,1,0,506,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(600,'VP for General Services',1,6,1,0,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(601,'GS Manager I',3,6,1,1,600,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(602,'GS Manager II',3,6,1,1,600,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(603,'GS Manager III',3,6,1,1,600,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(604,'GS Manager IV',3,6,1,1,600,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(605,'GS Supervisor I',4,6,1,1,601,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(606,'GS Supervisor II',4,6,1,1,601,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(607,'GS Supervisor III',4,6,1,1,601,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(608,'GS Supervisor IV',4,6,1,1,601,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(609,'Driver I',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(610,'Driver II',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(611,'Driver III',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(612,'Driver IV',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(613,'Driver V',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(614,'Security Monitoring Staff I',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(615,'Security Monitoring Staff II',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(616,'Security Monitoring Staff III',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(617,'Security Monitoring Staff IV',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(618,'Facilities Maintenance Staff I',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(619,'Facilities Maintenance Staff II',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(620,'Facilities Maintenance Staff III',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(621,'Warehouse Staff I',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(622,'Messenger I',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(623,'Messenger II',5,6,1,0,605,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(700,'HR Manager I',3,7,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(701,'HR Manager II',3,7,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(702,'HR Manager III',3,7,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(703,'HR Manager IV',3,7,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(704,'HR Manager V',3,7,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(705,'HR Supervisor I',4,7,1,1,700,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(706,'HR Supervisor II',4,7,1,1,700,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(707,'HR Supervisor III',4,7,1,1,700,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(708,'HR Supervisor IV',4,7,1,1,700,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(709,'HR Supervisor V',4,7,1,1,700,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(710,'HR Staff I',5,7,1,0,705,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(711,'HR Staff II',5,7,1,0,705,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(712,'HR Staff III',5,7,1,0,705,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(713,'HR Staff IV',5,7,1,0,705,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(714,'HR Staff V',5,7,1,0,705,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(800,'IT Manager I',3,8,1,1,1000,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(801,'IT Manager II',3,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(802,'IT Manager III',3,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(803,'IT Manager IV',3,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(804,'IT Supervisor I',4,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(805,'IT Supervisor II',4,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(806,'IT Supervisor III',4,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(807,'IT Supervisor IV',4,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(808,'IT Supervisor V',4,8,1,1,800,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(809,'Programmer I',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(810,'Technical Support Staff I',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(811,'Technical Support Staff II',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(812,'Technical Support Staff III',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(813,'Technical Support Staff IV',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(814,'Technical Support Staff V',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(815,'Helpdesk Assistant I',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(816,'Helpdesk Assistant II',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(817,'Helpdesk Assistant III',5,8,1,0,804,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(900,'Marketing Manager I',3,9,1,1,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(901,'Marketing Manager II',3,9,1,1,900,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(902,'Marketing Supervisor I',4,9,1,1,900,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(903,'Marketing Supervisor II',4,9,1,1,900,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(904,'Marketing Staff I',5,9,1,0,902,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(905,'Marketing Staff II',5,9,1,0,902,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1000,'VP for Operations',1,11,1,0,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1001,'Regional Manager I',3,11,1,1,1000,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1002,'Regional Manager II',3,11,1,1,1000,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1004,'Area Coordinator I',4,11,1,1,1001,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1005,'Area Coordinator II',4,11,1,1,1001,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1006,'Area Coordinator III',4,11,1,1,1001,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1007,'Focal Person I',4,11,1,0,1004,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1008,'Focal Person II',4,11,1,0,1004,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1009,'Focal Person III',4,11,1,0,1004,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1010,'Focal Person IV',4,11,1,0,1004,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1011,'Focal Person V',4,11,1,0,1004,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1012,'Branch Staff I',5,11,1,0,1007,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1013,'Branch Staff II',5,11,1,0,1007,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1014,'Branch Staff III',5,11,1,0,1007,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1015,'Branch Staff IV',5,11,1,0,1007,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1016,'Branch Staff V',5,11,1,0,1007,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1100,'President and CEO',1,10,1,1,NULL,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1101,'Executive Assistant I',5,10,1,0,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1102,'Executive Assistant II',5,10,1,0,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1103,'Executive Assistant III',5,10,1,0,1100,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1200,'Procurement Supervisor I',4,12,1,1,601,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(1202,'Purchasing Staff I',5,12,1,0,1200,'2026-11-25 15:17:29','2026-11-25 15:17:29');
/*!40000 ALTER TABLE `job_titles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `idx_ip_time` (`ip_address`,`attempt_time`),
  KEY `idx_identifier_time` (`identifier`,`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `category` varchar(32) DEFAULT NULL,
  `needs_action` tinyint(1) NOT NULL DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `fk_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'Successful Login','System Admin (Admin) has logged in successfully from IP ::1',NULL,'system',0,0,'2026-11-25 15:17:32'),(2,1,'Successful Login','elena delgado (HR Manager) has logged in successfully from IP ::1',NULL,'system',0,0,'2026-11-25 15:18:18'),(3,1,'Successful Login','System Admin (Admin) has logged in successfully from IP ::1',NULL,'system',0,0,'2026-11-25 15:35:01');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `probationary_evaluation_reviews`
--

DROP TABLE IF EXISTS `probationary_evaluation_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `probationary_evaluation_reviews` (
  `evaluation_id` int(11) NOT NULL,
  `recommendation` varchar(40) DEFAULT NULL,
  `recommendation_reason` text DEFAULT NULL,
  `immediate_head_user_id` int(11) DEFAULT NULL,
  `immediate_head_signature_data` longtext DEFAULT NULL,
  `immediate_head_signed_at` datetime DEFAULT NULL,
  `employee_acknowledged_by` int(11) DEFAULT NULL,
  `employee_ack_signature_data` longtext DEFAULT NULL,
  `employee_acknowledged_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`evaluation_id`),
  KEY `fk_probationary_review_head` (`immediate_head_user_id`),
  KEY `fk_probationary_review_employee` (`employee_acknowledged_by`),
  CONSTRAINT `fk_probationary_review_employee` FOREIGN KEY (`employee_acknowledged_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_probationary_review_evaluation` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_probationary_review_head` FOREIGN KEY (`immediate_head_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `probationary_evaluation_reviews`
--

LOCK TABLES `probationary_evaluation_reviews` WRITE;
/*!40000 ALTER TABLE `probationary_evaluation_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `probationary_evaluation_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_cycle_department_owners`
--

DROP TABLE IF EXISTS `quarterly_cycle_department_owners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_cycle_department_owners` (
  `department_id` int(11) NOT NULL,
  `owner_position_id` int(11) NOT NULL,
  PRIMARY KEY (`department_id`),
  KEY `fk_quarterly_cycle_owner_position` (`owner_position_id`),
  CONSTRAINT `fk_quarterly_cycle_owner_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  CONSTRAINT `fk_quarterly_cycle_owner_position` FOREIGN KEY (`owner_position_id`) REFERENCES `job_titles` (`job_title_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_cycle_department_owners`
--

LOCK TABLES `quarterly_cycle_department_owners` WRITE;
/*!40000 ALTER TABLE `quarterly_cycle_department_owners` DISABLE KEYS */;
INSERT INTO `quarterly_cycle_department_owners` VALUES (1,101),(2,200),(3,300),(4,400),(5,500),(6,600),(8,800),(9,900),(11,1000),(10,1100),(12,1200);
/*!40000 ALTER TABLE `quarterly_cycle_department_owners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_evaluation_cycles`
--

DROP TABLE IF EXISTS `quarterly_evaluation_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_evaluation_cycles` (
  `cycle_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `started_by` int(11) DEFAULT NULL,
  `self_rating_deadline` date DEFAULT NULL,
  `first_finalized_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `first_completed_at` datetime DEFAULT NULL,
  `ready_for_approval_at` datetime DEFAULT NULL,
  `cycle_approved_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `active_period_key` tinyint(4) GENERATED ALWAYS AS (if(`cancelled_at` is null,1,NULL)) STORED,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `individual_evaluation_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cycle_id`),
  UNIQUE KEY `quarterly_department_active_period` (`department_id`,`period_start`,`period_end`,`active_period_key`,`individual_evaluation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_evaluation_cycles`
--

LOCK TABLES `quarterly_evaluation_cycles` WRITE;
/*!40000 ALTER TABLE `quarterly_evaluation_cycles` DISABLE KEYS */;
/*!40000 ALTER TABLE `quarterly_evaluation_cycles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_evaluation_members`
--

DROP TABLE IF EXISTS `quarterly_evaluation_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_evaluation_members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `evaluation_id` int(11) DEFAULT NULL,
  `position_id` int(11) NOT NULL,
  `reviewer_position_id` int(11) DEFAULT NULL,
  `position_name` varchar(150) NOT NULL,
  `self_values_json` text DEFAULT NULL,
  `current_version` int(11) NOT NULL DEFAULT 0,
  `revision_pending` tinyint(4) NOT NULL DEFAULT 0,
  `finalized_at` datetime DEFAULT NULL,
  `is_late_participant` tinyint(4) NOT NULL DEFAULT 0,
  `head_approved_by` int(11) DEFAULT NULL,
  `head_approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `quarterly_member` (`cycle_id`,`employee_id`),
  UNIQUE KEY `quarterly_evaluation` (`evaluation_id`),
  KEY `quarterly_employee` (`employee_id`),
  CONSTRAINT `quarterly_member_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `quarterly_evaluation_cycles` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_evaluation_members`
--

LOCK TABLES `quarterly_evaluation_members` WRITE;
/*!40000 ALTER TABLE `quarterly_evaluation_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `quarterly_evaluation_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_evaluation_versions`
--

DROP TABLE IF EXISTS `quarterly_evaluation_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_evaluation_versions` (
  `version_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `finalized_by` int(11) NOT NULL,
  `finalized_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text NOT NULL,
  `snapshot_json` longtext NOT NULL,
  PRIMARY KEY (`version_id`),
  UNIQUE KEY `quarterly_version` (`member_id`,`version_number`),
  CONSTRAINT `quarterly_version_member` FOREIGN KEY (`member_id`) REFERENCES `quarterly_evaluation_members` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_evaluation_versions`
--

LOCK TABLES `quarterly_evaluation_versions` WRITE;
/*!40000 ALTER TABLE `quarterly_evaluation_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `quarterly_evaluation_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_kra_approvals`
--

DROP TABLE IF EXISTS `quarterly_kra_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_kra_approvals` (
  `approval_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text NOT NULL,
  `ratings_json` longtext NOT NULL,
  `rater_signature_data` longtext DEFAULT NULL,
  `rater_signed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`approval_id`),
  KEY `quarterly_kra_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_kra_approvals`
--

LOCK TABLES `quarterly_kra_approvals` WRITE;
/*!40000 ALTER TABLE `quarterly_kra_approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `quarterly_kra_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_peer_submissions`
--

DROP TABLE IF EXISTS `quarterly_peer_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_peer_submissions` (
  `submission_id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `rater_employee_id` int(11) NOT NULL,
  `target_employee_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `scores_json` text NOT NULL,
  `rater_signature_data` longtext DEFAULT NULL,
  `rater_signed_at` datetime DEFAULT NULL,
  `is_late` tinyint(4) NOT NULL DEFAULT 0,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `quarterly_peer_once` (`cycle_id`,`rater_employee_id`,`target_employee_id`),
  KEY `quarterly_peer_target` (`cycle_id`,`target_employee_id`),
  CONSTRAINT `quarterly_peer_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `quarterly_evaluation_cycles` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_peer_submissions`
--

LOCK TABLES `quarterly_peer_submissions` WRITE;
/*!40000 ALTER TABLE `quarterly_peer_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `quarterly_peer_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_required_raters`
--

DROP TABLE IF EXISTS `quarterly_required_raters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_required_raters` (
  `cycle_id` int(11) NOT NULL,
  `target_employee_id` int(11) NOT NULL,
  `rater_employee_id` int(11) NOT NULL,
  PRIMARY KEY (`cycle_id`,`target_employee_id`,`rater_employee_id`),
  KEY `target_employee_id` (`target_employee_id`),
  KEY `rater_employee_id` (`rater_employee_id`),
  CONSTRAINT `quarterly_required_raters_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `quarterly_evaluation_cycles` (`cycle_id`),
  CONSTRAINT `quarterly_required_raters_ibfk_2` FOREIGN KEY (`target_employee_id`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `quarterly_required_raters_ibfk_3` FOREIGN KEY (`rater_employee_id`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_required_raters`
--

LOCK TABLES `quarterly_required_raters` WRITE;
/*!40000 ALTER TABLE `quarterly_required_raters` DISABLE KEYS */;
/*!40000 ALTER TABLE `quarterly_required_raters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rank_categories`
--

DROP TABLE IF EXISTS `rank_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rank_categories` (
  `rank_category_id` int(11) NOT NULL AUTO_INCREMENT,
  `rank_name` varchar(50) NOT NULL,
  `level_order` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rank_category_id`),
  KEY `idx_rank_order` (`level_order`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rank_categories`
--

LOCK TABLES `rank_categories` WRITE;
/*!40000 ALTER TABLE `rank_categories` DISABLE KEYS */;
INSERT INTO `rank_categories` VALUES (1,'Executives',1,1,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(2,'Management Team',2,1,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(3,'Manager',3,1,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(4,'Supervisor',4,1,'2026-11-25 15:17:29','2026-11-25 15:17:29'),(5,'R&F',5,1,'2026-11-25 15:17:29','2026-11-25 15:17:29');
/*!40000 ALTER TABLE `rank_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `regularization_requests`
--

DROP TABLE IF EXISTS `regularization_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `regularization_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `recommended_by` int(11) NOT NULL,
  `recommendation_reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `decision_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `evaluation_id` (`evaluation_id`),
  KEY `idx_regularization_status` (`status`,`created_at`),
  KEY `fk_reg_request_employee` (`employee_id`),
  CONSTRAINT `fk_reg_request_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reg_request_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `regularization_requests`
--

LOCK TABLES `regularization_requests` WRITE;
/*!40000 ALTER TABLE `regularization_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `regularization_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `succession_endorsements`
--

DROP TABLE IF EXISTS `succession_endorsements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `succession_endorsements` (
  `endorsement_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `target_job_title_id` int(11) NOT NULL,
  `recommended_by` int(11) NOT NULL,
  `recommendation_reason` text NOT NULL,
  `status` enum('Pending HR Review','Pending CEO Approval','Approved','Returned','Rejected') NOT NULL DEFAULT 'Pending HR Review',
  `hr_reviewed_by` int(11) DEFAULT NULL,
  `hr_reviewed_at` datetime DEFAULT NULL,
  `hr_review_notes` text DEFAULT NULL,
  `qualifications_verified` tinyint(1) NOT NULL DEFAULT 0,
  `opportunity_verified` tinyint(1) NOT NULL DEFAULT 0,
  `records_verified` tinyint(1) NOT NULL DEFAULT 0,
  `ceo_decided_by` int(11) DEFAULT NULL,
  `ceo_decided_at` datetime DEFAULT NULL,
  `ceo_decision_notes` text DEFAULT NULL,
  `previous_job_title` varchar(150) DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`endorsement_id`),
  KEY `idx_succession_employee_status` (`employee_id`,`status`),
  KEY `idx_succession_status_created` (`status`,`created_at`),
  KEY `fk_succession_target` (`target_job_title_id`),
  KEY `fk_succession_recommender` (`recommended_by`),
  KEY `fk_succession_hr_reviewer` (`hr_reviewed_by`),
  KEY `fk_succession_ceo_decider` (`ceo_decided_by`),
  CONSTRAINT `fk_succession_ceo_decider` FOREIGN KEY (`ceo_decided_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_succession_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `fk_succession_hr_reviewer` FOREIGN KEY (`hr_reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_succession_recommender` FOREIGN KEY (`recommended_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_succession_target` FOREIGN KEY (`target_job_title_id`) REFERENCES `job_titles` (`job_title_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `succession_endorsements`
--

LOCK TABLES `succession_endorsements` WRITE;
/*!40000 ALTER TABLE `succession_endorsements` DISABLE KEYS */;
/*!40000 ALTER TABLE `succession_endorsements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
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
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` enum('Admin','HR Manager','HR Supervisor','HR Staff','Employee','President and CEO') NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `account_hold` tinyint(1) NOT NULL DEFAULT 0,
  `account_hold_reason` text DEFAULT NULL,
  `account_hold_at` datetime DEFAULT NULL,
  `account_hold_movement_id` int(11) DEFAULT NULL,
  `first_login_completed` tinyint(1) DEFAULT 0,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `sidebar_collapse_mode` enum('hidden','compact') NOT NULL DEFAULT 'hidden',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_user_role` (`role`),
  KEY `idx_user_role_status` (`is_active`,`deleted_at`),
  KEY `idx_user_employee_id` (`employee_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,'admin','admin@company.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','System Admin',NULL,'Admin',102,1,0,NULL,NULL,NULL,1,0,'hidden','2026-11-25 15:17:29','2026-11-25 15:17:32',NULL),(2,1,'elena.delgado','elena.delgado@example.com','$2y$10$q0B64zteohkCo5YW6kOxpOX8I.EZAEmDMLIxwsIlY7.SC2h6Hg.Hy','elena delgado',NULL,'HR Manager',102,1,0,NULL,NULL,NULL,1,0,'hidden','2026-11-25 15:18:13','2026-11-25 16:07:09',NULL),(3,1,'HRD-001','employee-1@portal.raquel.local','$2y$10$HThHJXHVy3zj/HtHoHIiMOH5hVy/bFO033RyzKqDGm9DHHSvmeHsu','Elena Delgado',NULL,'Employee',NULL,1,0,NULL,NULL,NULL,0,0,'hidden','2026-11-25 15:36:10','2026-11-25 15:36:10',NULL),(4,2,'patricia.gomez','patricia.gomez@example.com','$2y$10$M3Ith0HeRLPnoPdqU6HUbeUSFc/woq5sVTq9pMyipWK1MVJdf4/uy','Patricia Gomez',NULL,'HR Supervisor',102,1,0,NULL,NULL,NULL,0,0,'hidden','2026-11-25 15:50:01','2026-11-25 16:07:29',NULL),(6,3,'miguel.torres','miguel.torres@example.com','$2y$10$WC/0bw7PHtWuCKaPGcY.deJ0qaUV5ndZ2ByPVE2bdYvlq3DSdgnO6','Miguel Torres',NULL,'HR Staff',102,1,0,NULL,NULL,NULL,0,0,'hidden','2026-11-25 16:05:35','2026-11-25 16:05:35',NULL);
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

-- Dump completed on 2026-11-26  0:20:40
