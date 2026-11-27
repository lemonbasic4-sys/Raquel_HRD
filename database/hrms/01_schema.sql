
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `action_status` enum('Waiting','Pending','Approved','Returned','Skipped') NOT NULL DEFAULT 'Waiting',
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
  UNIQUE KEY `uq_evaluation_package` (`department_id`,`template_id`,`period_start`,`period_end`),
  KEY `idx_package_status` (`status`,`current_step_order`),
  KEY `fk_package_template` (`template_id`),
  KEY `fk_package_consolidator` (`consolidator_employee_id`),
  CONSTRAINT `fk_package_consolidator` FOREIGN KEY (`consolidator_employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_package_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  CONSTRAINT `fk_package_template` FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5529 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=1383 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `portal_login_failures` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `portal_locked_until` datetime DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

