-- AIMS production schema and approved baseline seed data
-- Import into a NEW, EMPTY database using phpMyAdmin.
-- Contains no users, passwords, sessions, applications, payments, or private records.

-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: aims_nigeria
-- ------------------------------------------------------
-- Server version	8.4.3

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
-- Table structure for table `audit_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `auditable_type` varchar(120) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `auditable_id` varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` char(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_audit_logs_action_created` (`action`,`created_at`),
  KEY `idx_audit_logs_auditable` (`auditable_type`,`auditable_id`,`created_at`),
  KEY `idx_audit_logs_request_id` (`request_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_request_rate_limits`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_request_rate_limits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `scope_type` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `scope_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `window_started_at` datetime(6) NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_request_rate_limit_bucket` (`action`,`scope_type`,`scope_hash`,`window_started_at`),
  KEY `idx_auth_request_rate_limits_cleanup` (`window_started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificate_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `code` varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `validity_value` smallint unsigned DEFAULT NULL,
  `validity_unit` varchar(10) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `template_key` varchar(100) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificate_types_public_id` (`public_id`),
  UNIQUE KEY `uq_certificate_types_code` (`code`),
  KEY `idx_certificate_types_active` (`active`,`name`),
  KEY `fk_certificate_types_creator` (`created_by_user_id`),
  CONSTRAINT `fk_certificate_types_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_certificate_type_validity` CHECK ((((`validity_value` is null) and (`validity_unit` is null)) or ((`validity_value` > 0) and (`validity_unit` in (_utf8mb4'days',_utf8mb4'months',_utf8mb4'years')))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificate_verifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `certificate_id` bigint unsigned DEFAULT NULL,
  `verification_method` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `lookup_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `outcome` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificate_verifications_public_id` (`public_id`),
  KEY `idx_certificate_verifications_certificate` (`certificate_id`,`verified_at`),
  KEY `idx_certificate_verifications_lookup` (`lookup_hash`,`verified_at`),
  KEY `idx_certificate_verifications_outcome` (`outcome`,`verified_at`),
  CONSTRAINT `fk_certificate_verifications_certificate` FOREIGN KEY (`certificate_id`) REFERENCES `certificates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_certificate_verification_method` CHECK ((`verification_method` in (_utf8mb4'membership_number',_utf8mb4'certificate_number',_utf8mb4'qr_token'))),
  CONSTRAINT `chk_certificate_verification_outcome` CHECK ((`outcome` in (_utf8mb4'valid',_utf8mb4'revoked',_utf8mb4'expired',_utf8mb4'not_found')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `certificate_number` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `certificate_type_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned DEFAULT NULL,
  `person_id` bigint unsigned DEFAULT NULL,
  `programme_id` bigint unsigned DEFAULT NULL,
  `event_id` bigint unsigned DEFAULT NULL,
  `issued_by_user_id` bigint unsigned NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'issued',
  `verification_token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `revoked_by_user_id` bigint unsigned DEFAULT NULL,
  `revocation_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificates_public_id` (`public_id`),
  UNIQUE KEY `uq_certificates_number` (`certificate_number`),
  UNIQUE KEY `uq_certificates_token_hash` (`verification_token_hash`),
  KEY `idx_certificates_member_status` (`member_id`,`status`,`issue_date`),
  KEY `idx_certificates_person_status` (`person_id`,`status`,`issue_date`),
  KEY `idx_certificates_type_status` (`certificate_type_id`,`status`),
  KEY `idx_certificates_programme` (`programme_id`),
  KEY `idx_certificates_event` (`event_id`),
  KEY `fk_certificates_issuer` (`issued_by_user_id`),
  KEY `fk_certificates_revoker` (`revoked_by_user_id`),
  CONSTRAINT `fk_certificates_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_certificates_issuer` FOREIGN KEY (`issued_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_certificates_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_certificates_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_certificates_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_certificates_revoker` FOREIGN KEY (`revoked_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_certificates_type` FOREIGN KEY (`certificate_type_id`) REFERENCES `certificate_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_certificates_dates` CHECK (((`expiry_date` is null) or (`expiry_date` >= `issue_date`))),
  CONSTRAINT `chk_certificates_holder` CHECK ((((`member_id` is not null) and (`person_id` is null)) or ((`member_id` is null) and (`person_id` is not null)))),
  CONSTRAINT `chk_certificates_revocation` CHECK ((((`status` = _utf8mb4'revoked') and (`revoked_at` is not null) and (`revocation_reason` is not null)) or ((`status` <> _utf8mb4'revoked') and (`revoked_at` is null) and (`revoked_by_user_id` is null) and (`revocation_reason` is null)))),
  CONSTRAINT `chk_certificates_status` CHECK ((`status` in (_utf8mb4'issued',_utf8mb4'revoked',_utf8mb4'expired')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coordinator_assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coordinator_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `person_id` bigint unsigned NOT NULL,
  `programme_id` bigint unsigned DEFAULT NULL,
  `professional_area_id` bigint unsigned DEFAULT NULL,
  `role_title` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `starts_at` date DEFAULT NULL,
  `ends_at` date DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coordinator_assignments_public_id` (`public_id`),
  KEY `idx_coordinator_assignments_person_active` (`person_id`,`active`),
  KEY `idx_coordinator_assignments_programme` (`programme_id`,`active`,`display_order`),
  KEY `idx_coordinator_assignments_area` (`professional_area_id`,`active`,`display_order`),
  CONSTRAINT `fk_coordinator_assignments_area` FOREIGN KEY (`professional_area_id`) REFERENCES `professional_areas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_coordinator_assignments_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_coordinator_assignments_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_coordinator_assignments_dates` CHECK (((`ends_at` is null) or (`starts_at` is null) or (`ends_at` >= `starts_at`))),
  CONSTRAINT `chk_coordinator_assignments_target` CHECK (((`programme_id` is not null) or (`professional_area_id` is not null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `downloads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `downloads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `title` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_id` bigint unsigned NOT NULL,
  `download_count` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `published_at` datetime(6) DEFAULT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `updated_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_downloads_public_id` (`public_id`),
  UNIQUE KEY `uq_downloads_slug` (`slug`),
  KEY `idx_downloads_public` (`status`,`published_at`),
  KEY `idx_downloads_media` (`media_id`),
  KEY `fk_downloads_creator` (`created_by_user_id`),
  KEY `fk_downloads_updater` (`updated_by_user_id`),
  CONSTRAINT `fk_downloads_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_downloads_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_downloads_updater` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_downloads_publication` CHECK ((((`status` = _utf8mb4'published') and (`published_at` is not null)) or (`status` <> _utf8mb4'published'))),
  CONSTRAINT `chk_downloads_status` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'published',_utf8mb4'archived')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_verification_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_verification_tokens_hash` (`token_hash`),
  KEY `idx_email_verification_tokens_user_pending` (`user_id`,`used_at`,`expires_at`),
  KEY `idx_email_verification_tokens_expires_at` (`expires_at`),
  CONSTRAINT `fk_email_verification_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_attendance`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `event_registration_id` bigint unsigned NOT NULL,
  `checked_in_by_user_id` bigint unsigned NOT NULL,
  `check_in_method` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'manual',
  `checked_in_at` datetime(6) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_attendance_public_id` (`public_id`),
  UNIQUE KEY `uq_event_attendance_registration` (`event_registration_id`),
  KEY `idx_event_attendance_actor` (`checked_in_by_user_id`),
  CONSTRAINT `fk_event_attendance_actor` FOREIGN KEY (`checked_in_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_event_attendance_registration` FOREIGN KEY (`event_registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_event_attendance_method` CHECK ((`check_in_method` in (_utf8mb4'manual',_utf8mb4'qr')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_registrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `registration_reference` varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `registrant_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registrant_email` varchar(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `registrant_phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'registered',
  `attendance_token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `registered_at` datetime(6) NOT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_registrations_public_id` (`public_id`),
  UNIQUE KEY `uq_event_registrations_reference` (`registration_reference`),
  UNIQUE KEY `uq_event_registrations_event_email` (`event_id`,`registrant_email`),
  UNIQUE KEY `uq_event_registrations_event_user` (`event_id`,`user_id`),
  UNIQUE KEY `uq_event_registrations_attendance_token` (`attendance_token_hash`),
  KEY `idx_event_registrations_event_status` (`event_id`,`status`,`registered_at`),
  KEY `idx_event_registrations_user` (`user_id`,`status`),
  KEY `idx_event_registrations_registered_event_status` (`registered_at`,`event_id`,`status`),
  CONSTRAINT `fk_event_registrations_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_event_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_event_attendance_token_hash` CHECK (((`attendance_token_hash` is null) or regexp_like(`attendance_token_hash`,_ascii'^[0-9a-f]{64}$'))),
  CONSTRAINT `chk_event_registrations_status` CHECK ((`status` in (_ascii'registered',_ascii'cancelled',_ascii'attended')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_types_public_id` (`public_id`),
  UNIQUE KEY `uq_event_types_name` (`name`),
  UNIQUE KEY `uq_event_types_slug` (`slug`),
  KEY `idx_event_types_active_order` (`active`,`display_order`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `event_type_id` bigint unsigned NOT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `title` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `online_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_deadline` datetime(6) DEFAULT NULL,
  `capacity` int unsigned DEFAULT NULL,
  `fee` decimal(12,2) DEFAULT NULL,
  `fee_currency` char(3) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `eligibility` text COLLATE utf8mb4_unicode_ci,
  `registration_access` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'public',
  `banner_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_public_id` (`public_id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  KEY `idx_events_public_date` (`status`,`deleted_at`,`event_date`),
  KEY `idx_events_type_date` (`event_type_id`,`event_date`),
  KEY `idx_events_creator` (`created_by_user_id`),
  CONSTRAINT `fk_events_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_events_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_events_access` CHECK ((`registration_access` in (_utf8mb4'public',_utf8mb4'member'))),
  CONSTRAINT `chk_events_fee` CHECK (((`fee` is null) or (`fee` >= 0))),
  CONSTRAINT `chk_events_status` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'published',_utf8mb4'cancelled',_utf8mb4'completed'))),
  CONSTRAINT `chk_events_times` CHECK (((`end_time` is null) or (`start_time` is null) or (`end_time` > `start_time`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `faqs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `question` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `published_at` datetime(6) DEFAULT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `updated_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faqs_public_id` (`public_id`),
  KEY `idx_faqs_public` (`status`,`display_order`,`published_at`),
  KEY `fk_faqs_creator` (`created_by_user_id`),
  KEY `fk_faqs_updater` (`updated_by_user_id`),
  CONSTRAINT `fk_faqs_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_faqs_updater` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_faqs_publication` CHECK ((((`status` = _utf8mb4'published') and (`published_at` is not null)) or (`status` <> _utf8mb4'published'))),
  CONSTRAINT `chk_faqs_status` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'published',_utf8mb4'archived')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fee_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `code` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fee_type` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_settings_public_id` (`public_id`),
  UNIQUE KEY `uq_fee_settings_code` (`code`),
  KEY `idx_fee_settings_type_active` (`fee_type`,`active`,`deleted_at`),
  KEY `idx_fee_settings_creator` (`created_by_user_id`),
  CONSTRAINT `fk_fee_settings_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_fee_settings_type` CHECK ((`fee_type` in (_utf8mb4'membership_application',_utf8mb4'membership_renewal',_utf8mb4'programme_application',_utf8mb4'programme_tuition',_utf8mb4'event_registration',_utf8mb4'other')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `idempotency_keys`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idempotency_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `key_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `request_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'processing',
  `resource_type` varchar(60) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `resource_public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `response_status` smallint unsigned DEFAULT NULL,
  `response_body` json DEFAULT NULL,
  `expires_at` datetime(6) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idempotency_scope_key` (`scope`,`key_hash`),
  KEY `idx_idempotency_expiry` (`expires_at`),
  CONSTRAINT `chk_idempotency_status` CHECK ((`status` in (_utf8mb4'processing',_utf8mb4'completed',_utf8mb4'failed')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `fee_setting_id` bigint unsigned DEFAULT NULL,
  `fee_type` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` smallint unsigned NOT NULL DEFAULT '1',
  `unit_amount_minor` bigint unsigned NOT NULL,
  `line_total_minor` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`,`id`),
  KEY `idx_invoice_items_fee_setting` (`fee_setting_id`),
  KEY `idx_invoice_items_type_invoice` (`fee_type`,`invoice_id`),
  CONSTRAINT `fk_invoice_items_fee_setting` FOREIGN KEY (`fee_setting_id`) REFERENCES `fee_settings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_invoice_items_total` CHECK (((`quantity` > 0) and (`line_total_minor` = (`quantity` * `unit_amount_minor`)))),
  CONSTRAINT `chk_invoice_items_type` CHECK ((`fee_type` in (_ascii'membership_application',_ascii'membership_renewal',_ascii'programme_application',_ascii'programme_tuition',_ascii'event_registration',_ascii'other')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `invoice_number` varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `bill_to_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bill_to_email` varchar(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `source_type` varchar(60) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `source_public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `subtotal_minor` bigint unsigned NOT NULL,
  `total_minor` bigint unsigned NOT NULL,
  `amount_paid_minor` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
  `due_at` datetime(6) DEFAULT NULL,
  `paid_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_public_id` (`public_id`),
  UNIQUE KEY `uq_invoices_number` (`invoice_number`),
  UNIQUE KEY `uq_invoices_source` (`source_type`,`source_public_id`),
  KEY `idx_invoices_user_status` (`user_id`,`status`,`created_at`),
  KEY `idx_invoices_status_due` (`status`,`due_at`),
  KEY `idx_invoices_paid_status_currency` (`paid_at`,`status`,`currency`),
  CONSTRAINT `fk_invoices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_invoices_status` CHECK ((`status` in (_ascii'pending',_ascii'paid',_ascii'cancelled',_ascii'expired',_ascii'refunded'))),
  CONSTRAINT `chk_invoices_totals` CHECK (((`subtotal_minor` = `total_minor`) and (`amount_paid_minor` <= `total_minor`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leadership_assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leadership_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `leadership_position_id` bigint unsigned NOT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `starts_at` date DEFAULT NULL,
  `ends_at` date DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leadership_assignments_person_position` (`person_id`,`leadership_position_id`),
  KEY `idx_leadership_assignments_position_active_order` (`leadership_position_id`,`active`,`display_order`),
  KEY `idx_leadership_assignments_person_active` (`person_id`,`active`),
  KEY `idx_leadership_assignments_dates` (`starts_at`,`ends_at`),
  CONSTRAINT `fk_leadership_assignments_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_leadership_assignments_position` FOREIGN KEY (`leadership_position_id`) REFERENCES `leadership_positions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_leadership_assignments_dates` CHECK (((`ends_at` is null) or (`starts_at` is null) or (`ends_at` >= `starts_at`)))
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leadership_groups`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leadership_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leadership_groups_name` (`name`),
  UNIQUE KEY `uq_leadership_groups_slug` (`slug`),
  KEY `idx_leadership_groups_active_order` (`active`,`display_order`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leadership_positions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leadership_positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `leadership_group_id` bigint unsigned NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leadership_positions_group_name` (`leadership_group_id`,`name`),
  KEY `idx_leadership_positions_group_active_order` (`leadership_group_id`,`active`,`display_order`),
  CONSTRAINT `fk_leadership_positions_group` FOREIGN KEY (`leadership_group_id`) REFERENCES `leadership_groups` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_attempts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `identity_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `attempted_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_identity_time` (`identity_hash`,`attempted_at`),
  KEY `idx_login_attempts_ip_time` (`ip_address`,`attempted_at`),
  KEY `idx_login_attempts_user_time` (`user_id`,`attempted_at`),
  CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `media`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_path` varchar(500) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mime_type` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `size_bytes` bigint unsigned NOT NULL,
  `sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `uploaded_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_public_id` (`public_id`),
  UNIQUE KEY `uq_media_storage_path` (`storage_path`),
  KEY `idx_media_public` (`status`,`mime_type`,`created_at`),
  KEY `idx_media_digest` (`sha256`),
  KEY `fk_media_uploader` (`uploaded_by_user_id`),
  CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_media_status` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'published',_utf8mb4'archived')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `member_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notification_outbox_id` bigint unsigned DEFAULT NULL,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `category` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'general',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_notifications_public_id` (`public_id`),
  UNIQUE KEY `uq_member_notifications_outbox` (`notification_outbox_id`),
  KEY `idx_member_notifications_user_created` (`user_id`,`created_at`,`id`),
  KEY `idx_member_notifications_user_read` (`user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_member_notifications_outbox` FOREIGN KEY (`notification_outbox_id`) REFERENCES `notification_outbox` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_member_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `member_profiles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_profiles` (
  `member_id` bigint unsigned NOT NULL,
  `preferred_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alternate_email` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professional_area` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_role` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biography` text COLLATE utf8mb4_unicode_ci,
  `updated_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`member_id`),
  KEY `idx_member_profiles_area` (`professional_area`),
  KEY `idx_member_profiles_updated_by` (`updated_by_user_id`),
  CONSTRAINT `fk_member_profiles_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_member_profiles_updated_by` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `members`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `membership_grade_id` bigint unsigned NOT NULL,
  `approved_application_id` bigint unsigned NOT NULL,
  `membership_number` varchar(80) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending_activation',
  `joined_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_members_public_id` (`public_id`),
  UNIQUE KEY `uq_members_user` (`user_id`),
  UNIQUE KEY `uq_members_approved_application` (`approved_application_id`),
  UNIQUE KEY `uq_members_membership_number` (`membership_number`),
  KEY `idx_members_grade_status` (`membership_grade_id`,`status`),
  KEY `idx_members_status_expiry` (`status`,`expires_at`),
  KEY `idx_members_joined_status_grade` (`joined_at`,`status`,`membership_grade_id`),
  CONSTRAINT `fk_members_application` FOREIGN KEY (`approved_application_id`) REFERENCES `membership_applications` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_members_grade` FOREIGN KEY (`membership_grade_id`) REFERENCES `membership_grades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_members_status` CHECK ((`status` in (_ascii'pending_activation',_ascii'active',_ascii'suspended',_ascii'lapsed',_ascii'ceased')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_application_documents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_application_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `membership_application_id` bigint unsigned NOT NULL,
  `uploaded_by_user_id` bigint unsigned NOT NULL,
  `document_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_path` varchar(500) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mime_type` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `size_bytes` bigint unsigned NOT NULL,
  `sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membership_documents_public_id` (`public_id`),
  UNIQUE KEY `uq_membership_documents_storage_path` (`storage_path`),
  KEY `idx_membership_documents_application` (`membership_application_id`,`deleted_at`,`created_at`),
  KEY `idx_membership_documents_uploader` (`uploaded_by_user_id`,`created_at`),
  KEY `idx_membership_documents_sha256` (`sha256`),
  CONSTRAINT `fk_membership_documents_application` FOREIGN KEY (`membership_application_id`) REFERENCES `membership_applications` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_membership_documents_uploader` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_applications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `membership_grade_id` bigint unsigned DEFAULT NULL,
  `application_reference` varchar(40) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `status` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `personal_information` json DEFAULT NULL,
  `contact_information` json DEFAULT NULL,
  `professional_details` json DEFAULT NULL,
  `education` json DEFAULT NULL,
  `employment` json DEFAULT NULL,
  `declaration_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declaration_accepted_at` datetime(6) DEFAULT NULL,
  `submitted_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membership_applications_public_id` (`public_id`),
  UNIQUE KEY `uq_membership_applications_reference` (`application_reference`),
  KEY `idx_membership_applications_user_status` (`user_id`,`status`,`updated_at`),
  KEY `idx_membership_applications_grade_status` (`membership_grade_id`,`status`,`submitted_at`),
  KEY `idx_membership_applications_status_submitted` (`status`,`submitted_at`),
  CONSTRAINT `fk_membership_applications_grade` FOREIGN KEY (`membership_grade_id`) REFERENCES `membership_grades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_membership_applications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_membership_applications_status` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'submitted',_utf8mb4'under_review',_utf8mb4'query_raised',_utf8mb4'approved',_utf8mb4'rejected',_utf8mb4'cancelled')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_grades`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_grades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `eligibility` text COLLATE utf8mb4_unicode_ci,
  `benefits` text COLLATE utf8mb4_unicode_ci,
  `application_fee` decimal(12,2) DEFAULT NULL,
  `annual_fee` decimal(12,2) DEFAULT NULL,
  `fee_currency` char(3) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membership_grades_public_id` (`public_id`),
  UNIQUE KEY `uq_membership_grades_name` (`name`),
  KEY `idx_membership_grades_active_order` (`active`,`display_order`,`name`),
  CONSTRAINT `chk_membership_grades_fees` CHECK ((((`application_fee` is null) or (`application_fee` >= 0)) and ((`annual_fee` is null) or (`annual_fee` >= 0))))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `membership_application_id` bigint unsigned NOT NULL,
  `from_status` varchar(30) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `to_status` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_membership_history_application_created` (`membership_application_id`,`created_at`,`id`),
  KEY `idx_membership_history_actor_created` (`changed_by_user_id`,`created_at`),
  CONSTRAINT `fk_membership_history_actor` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_membership_history_application` FOREIGN KEY (`membership_application_id`) REFERENCES `membership_applications` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_membership_history_from_status` CHECK (((`from_status` is null) or (`from_status` in (_utf8mb4'draft',_utf8mb4'submitted',_utf8mb4'under_review',_utf8mb4'query_raised',_utf8mb4'approved',_utf8mb4'rejected',_utf8mb4'cancelled')))),
  CONSTRAINT `chk_membership_history_to_status` CHECK ((`to_status` in (_utf8mb4'draft',_utf8mb4'submitted',_utf8mb4'under_review',_utf8mb4'query_raised',_utf8mb4'approved',_utf8mb4'rejected',_utf8mb4'cancelled')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_renewal_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_renewal_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `membership_renewal_id` bigint unsigned NOT NULL,
  `from_status` varchar(30) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `to_status` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `transition_source` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_renewal_history_renewal_created` (`membership_renewal_id`,`created_at`,`id`),
  KEY `idx_renewal_history_actor` (`changed_by_user_id`,`created_at`),
  CONSTRAINT `fk_renewal_history_actor` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_renewal_history_renewal` FOREIGN KEY (`membership_renewal_id`) REFERENCES `membership_renewals` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_renewal_history_source` CHECK ((`transition_source` in (_utf8mb4'member',_utf8mb4'verified_payment',_utf8mb4'scheduler',_utf8mb4'admin_override'))),
  CONSTRAINT `chk_renewal_history_to` CHECK ((`to_status` in (_utf8mb4'renewal_due',_utf8mb4'invoice_generated',_utf8mb4'payment_pending',_utf8mb4'paid',_utf8mb4'renewed',_utf8mb4'expired')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_renewal_policies`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_renewal_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `membership_grade_id` bigint unsigned NOT NULL,
  `fee_setting_id` bigint unsigned NOT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_value` smallint unsigned NOT NULL,
  `period_unit` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `start_rule` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `renewal_window_days` smallint unsigned NOT NULL DEFAULT '0',
  `grace_days` smallint unsigned NOT NULL DEFAULT '0',
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membership_renewal_policies_public_id` (`public_id`),
  KEY `idx_renewal_policies_grade_effective` (`membership_grade_id`,`active`,`effective_from`,`effective_until`),
  KEY `idx_renewal_policies_fee` (`fee_setting_id`),
  KEY `fk_renewal_policies_creator` (`created_by_user_id`),
  CONSTRAINT `fk_renewal_policies_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_renewal_policies_fee` FOREIGN KEY (`fee_setting_id`) REFERENCES `fee_settings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_renewal_policies_grade` FOREIGN KEY (`membership_grade_id`) REFERENCES `membership_grades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_renewal_policy_dates` CHECK (((`effective_until` is null) or (`effective_until` >= `effective_from`))),
  CONSTRAINT `chk_renewal_policy_period` CHECK (((`period_value` > 0) and (`period_unit` in (_utf8mb4'days',_utf8mb4'months',_utf8mb4'years')))),
  CONSTRAINT `chk_renewal_policy_start` CHECK ((`start_rule` in (_utf8mb4'current_expiry',_utf8mb4'payment_date')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_renewals`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_renewals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `renewal_policy_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `renewal_reference` varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'renewal_due',
  `previous_expiry_date` date DEFAULT NULL,
  `renewed_from_date` date DEFAULT NULL,
  `renewed_until_date` date DEFAULT NULL,
  `period_value` smallint unsigned NOT NULL,
  `period_unit` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `start_rule` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `fee_amount_minor` bigint unsigned NOT NULL,
  `fee_currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `due_at` datetime(6) DEFAULT NULL,
  `paid_at` datetime(6) DEFAULT NULL,
  `renewed_at` datetime(6) DEFAULT NULL,
  `expired_at` datetime(6) DEFAULT NULL,
  `override_by_user_id` bigint unsigned DEFAULT NULL,
  `override_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membership_renewals_public_id` (`public_id`),
  UNIQUE KEY `uq_membership_renewals_reference` (`renewal_reference`),
  UNIQUE KEY `uq_membership_renewals_invoice` (`invoice_id`),
  KEY `idx_membership_renewals_member_status` (`member_id`,`status`,`created_at`),
  KEY `idx_membership_renewals_due` (`status`,`due_at`),
  KEY `fk_membership_renewals_policy` (`renewal_policy_id`),
  KEY `fk_membership_renewals_override` (`override_by_user_id`),
  CONSTRAINT `fk_membership_renewals_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_membership_renewals_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_membership_renewals_override` FOREIGN KEY (`override_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_membership_renewals_policy` FOREIGN KEY (`renewal_policy_id`) REFERENCES `membership_renewal_policies` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_membership_renewals_override` CHECK ((((`override_by_user_id` is null) and (`override_reason` is null)) or ((`override_by_user_id` is not null) and (`override_reason` is not null)))),
  CONSTRAINT `chk_membership_renewals_period` CHECK (((`period_value` > 0) and (`period_unit` in (_utf8mb4'days',_utf8mb4'months',_utf8mb4'years')))),
  CONSTRAINT `chk_membership_renewals_start` CHECK ((`start_rule` in (_utf8mb4'current_expiry',_utf8mb4'payment_date'))),
  CONSTRAINT `chk_membership_renewals_status` CHECK ((`status` in (_utf8mb4'renewal_due',_utf8mb4'invoice_generated',_utf8mb4'payment_pending',_utf8mb4'paid',_utf8mb4'renewed',_utf8mb4'expired')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `membership_review_comments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_review_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `membership_application_id` bigint unsigned NOT NULL,
  `author_user_id` bigint unsigned NOT NULL,
  `comment_type` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'review',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible_to_applicant` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membership_review_comments_public_id` (`public_id`),
  KEY `idx_membership_review_comments_application_created` (`membership_application_id`,`created_at`,`id`),
  KEY `idx_membership_review_comments_author_created` (`author_user_id`,`created_at`),
  CONSTRAINT `fk_membership_review_comments_application` FOREIGN KEY (`membership_application_id`) REFERENCES `membership_applications` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_membership_review_comments_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_membership_review_comments_type` CHECK ((`comment_type` in (_utf8mb4'review',_utf8mb4'query',_utf8mb4'approval',_utf8mb4'rejection')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int unsigned NOT NULL,
  `applied_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrations_migration` (`migration`),
  KEY `idx_migrations_batch` (`batch`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_deliveries`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notification_outbox_id` bigint unsigned NOT NULL,
  `channel` enum('in_app','email','sms') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sent','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_number` smallint unsigned NOT NULL,
  `provider_message_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempted_at` datetime(6) NOT NULL,
  `sent_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_delivery_attempt` (`notification_outbox_id`,`channel`,`attempt_number`),
  KEY `idx_notification_deliveries_status` (`channel`,`status`,`attempted_at`),
  CONSTRAINT `fk_notification_deliveries_outbox` FOREIGN KEY (`notification_outbox_id`) REFERENCES `notification_outbox` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_outbox`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_outbox` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `recipient_email` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `category` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `send_in_app` tinyint(1) NOT NULL DEFAULT '1',
  `send_email` tinyint(1) NOT NULL DEFAULT '1',
  `send_sms` tinyint(1) NOT NULL DEFAULT '0',
  `deduplication_key_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` enum('pending','processing','completed','retry','dead') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempt_count` smallint unsigned NOT NULL DEFAULT '0',
  `available_at` datetime(6) NOT NULL,
  `locked_at` datetime(6) DEFAULT NULL,
  `lock_token` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `processed_at` datetime(6) DEFAULT NULL,
  `last_error` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_outbox_public_id` (`public_id`),
  UNIQUE KEY `uq_notification_outbox_dedupe` (`deduplication_key_hash`),
  KEY `idx_notification_outbox_dispatch` (`status`,`available_at`,`id`),
  KEY `idx_notification_outbox_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_notification_outbox_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `title` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `published_at` datetime(6) DEFAULT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `updated_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_public_id` (`public_id`),
  UNIQUE KEY `uq_pages_slug` (`slug`),
  KEY `idx_pages_public` (`status`,`published_at`),
  KEY `fk_pages_creator` (`created_by_user_id`),
  KEY `fk_pages_updater` (`updated_by_user_id`),
  CONSTRAINT `fk_pages_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pages_updater` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_pages_publication` CHECK ((((`status` = _utf8mb4'published') and (`published_at` is not null)) or (`status` <> _utf8mb4'published'))),
  CONSTRAINT `chk_pages_status` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'published',_utf8mb4'archived')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_tokens_hash` (`token_hash`),
  KEY `idx_password_reset_tokens_user_pending` (`user_id`,`used_at`,`expires_at`),
  KEY `idx_password_reset_tokens_expires_at` (`expires_at`),
  CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `gateway` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `gateway_reference` varchar(120) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `provider_event_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `transaction_type` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `provider_status` varchar(60) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `amount_minor` bigint unsigned DEFAULT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `response_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `processed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_transactions_public_id` (`public_id`),
  UNIQUE KEY `uq_payment_transactions_event` (`gateway`,`provider_event_id`),
  KEY `idx_payment_transactions_reference` (`gateway`,`gateway_reference`,`created_at`),
  KEY `idx_payment_transactions_payment` (`payment_id`,`created_at`),
  CONSTRAINT `fk_payment_transactions_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_payment_transactions_type` CHECK ((`transaction_type` in (_utf8mb4'initialize',_utf8mb4'verify',_utf8mb4'webhook',_utf8mb4'refund',_utf8mb4'manual')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `gateway` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `gateway_reference` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'initiated',
  `verified_at` datetime(6) DEFAULT NULL,
  `paid_at` datetime(6) DEFAULT NULL,
  `failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_public_id` (`public_id`),
  UNIQUE KEY `uq_payments_gateway_reference` (`gateway`,`gateway_reference`),
  KEY `idx_payments_invoice_status` (`invoice_id`,`status`),
  KEY `idx_payments_user_created` (`user_id`,`created_at`),
  KEY `idx_payments_paid_status_currency` (`paid_at`,`status`,`currency`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_payments_status` CHECK ((`status` in (_ascii'initiated',_ascii'pending',_ascii'successful',_ascii'failed',_ascii'reversed',_ascii'refunded')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `people`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `people` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `full_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualifications` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biography` text COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professional_area` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkedin_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_people_public_id` (`public_id`),
  KEY `idx_people_active_order` (`active`,`display_order`,`full_name`),
  KEY `idx_people_professional_area` (`professional_area`),
  KEY `idx_people_email` (`email`),
  KEY `idx_people_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `module` varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_name` (`name`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `posts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `post_type` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `title` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `excerpt` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `featured_media_id` bigint unsigned DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `published_at` datetime(6) DEFAULT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `updated_by_user_id` bigint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_public_id` (`public_id`),
  UNIQUE KEY `uq_posts_type_slug` (`post_type`,`slug`),
  KEY `idx_posts_public` (`post_type`,`status`,`published_at`),
  KEY `idx_posts_featured_media` (`featured_media_id`),
  KEY `fk_posts_creator` (`created_by_user_id`),
  KEY `fk_posts_updater` (`updated_by_user_id`),
  CONSTRAINT `fk_posts_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_posts_featured_media` FOREIGN KEY (`featured_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_updater` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_posts_publication` CHECK ((((`status` = _ascii'published') and (`published_at` is not null)) or (`status` <> _ascii'published'))),
  CONSTRAINT `chk_posts_status` CHECK ((`status` in (_ascii'draft',_ascii'published',_ascii'archived'))),
  CONSTRAINT `chk_posts_type` CHECK ((`post_type` in (_ascii'news',_ascii'announcement',_ascii'resource')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professional_areas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professional_areas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `code` varchar(40) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_professional_areas_public_id` (`public_id`),
  UNIQUE KEY `uq_professional_areas_name` (`name`),
  UNIQUE KEY `uq_professional_areas_slug` (`slug`),
  UNIQUE KEY `uq_professional_areas_code` (`code`),
  KEY `idx_professional_areas_public_order` (`active`,`deleted_at`,`display_order`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `programme_applications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programme_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `programme_id` bigint unsigned NOT NULL,
  `application_reference` varchar(50) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `motivation` text COLLATE utf8mb4_unicode_ci,
  `professional_background` text COLLATE utf8mb4_unicode_ci,
  `highest_qualification` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_organisation` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declaration_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declaration_accepted_at` datetime(6) DEFAULT NULL,
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `decision_note` text COLLATE utf8mb4_unicode_ci,
  `submitted_at` datetime(6) DEFAULT NULL,
  `reviewed_at` datetime(6) DEFAULT NULL,
  `reviewed_by_user_id` bigint unsigned DEFAULT NULL,
  `approved_at` datetime(6) DEFAULT NULL,
  `rejected_at` datetime(6) DEFAULT NULL,
  `withdrawn_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programme_applications_public_id` (`public_id`),
  UNIQUE KEY `uq_programme_applications_user_programme` (`user_id`,`programme_id`),
  UNIQUE KEY `uq_programme_applications_reference` (`application_reference`),
  KEY `idx_programme_applications_status_submitted` (`status`,`submitted_at`),
  KEY `idx_programme_applications_programme_status` (`programme_id`,`status`),
  KEY `idx_programme_applications_reviewer` (`reviewed_by_user_id`),
  KEY `idx_programme_applications_submitted_programme_status` (`submitted_at`,`programme_id`,`status`),
  CONSTRAINT `fk_programme_applications_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_programme_applications_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_programme_applications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_programme_applications_status` CHECK ((`status` in (_ascii'draft',_ascii'submitted',_ascii'review',_ascii'approved',_ascii'rejected',_ascii'enrolled',_ascii'completed',_ascii'withdrawn')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `programme_areas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programme_areas` (
  `programme_id` bigint unsigned NOT NULL,
  `professional_area_id` bigint unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`programme_id`,`professional_area_id`),
  KEY `idx_programme_areas_area_order` (`professional_area_id`,`display_order`,`programme_id`),
  KEY `idx_programme_areas_primary` (`programme_id`,`is_primary`),
  CONSTRAINT `fk_programme_areas_area` FOREIGN KEY (`professional_area_id`) REFERENCES `professional_areas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_programme_areas_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `programme_enrolments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programme_enrolments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `programme_application_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned DEFAULT NULL,
  `programme_id` bigint unsigned NOT NULL,
  `enrolment_number` varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'enrolled',
  `enrolled_by_user_id` bigint unsigned NOT NULL,
  `enrolled_at` datetime(6) NOT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `withdrawn_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programme_enrolments_public_id` (`public_id`),
  UNIQUE KEY `uq_programme_enrolments_application` (`programme_application_id`),
  UNIQUE KEY `uq_programme_enrolments_number` (`enrolment_number`),
  KEY `idx_programme_enrolments_user_status` (`user_id`,`status`),
  KEY `idx_programme_enrolments_member_status` (`member_id`,`status`),
  KEY `idx_programme_enrolments_programme_status` (`programme_id`,`status`),
  KEY `idx_programme_enrolments_actor` (`enrolled_by_user_id`),
  KEY `idx_programme_enrolments_enrolled_programme_status` (`enrolled_at`,`programme_id`,`status`),
  CONSTRAINT `fk_programme_enrolments_actor` FOREIGN KEY (`enrolled_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_programme_enrolments_application` FOREIGN KEY (`programme_application_id`) REFERENCES `programme_applications` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_programme_enrolments_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_programme_enrolments_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_programme_enrolments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_programme_enrolments_status` CHECK ((`status` in (_ascii'enrolled',_ascii'completed',_ascii'withdrawn')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `programme_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programme_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programme_types_public_id` (`public_id`),
  UNIQUE KEY `uq_programme_types_name` (`name`),
  UNIQUE KEY `uq_programme_types_slug` (`slug`),
  KEY `idx_programme_types_public_order` (`active`,`display_order`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `programmes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programmes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `programme_type_id` bigint unsigned NOT NULL,
  `code` varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(200) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `duration` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entry_requirements` text COLLATE utf8mb4_unicode_ci,
  `delivery_mode` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `application_fee` decimal(12,2) DEFAULT NULL,
  `tuition_fee` decimal(12,2) DEFAULT NULL,
  `fee_currency` char(3) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programmes_public_id` (`public_id`),
  UNIQUE KEY `uq_programmes_code` (`code`),
  UNIQUE KEY `uq_programmes_slug` (`slug`),
  KEY `idx_programmes_type_status_order` (`programme_type_id`,`status`,`deleted_at`,`display_order`),
  KEY `idx_programmes_status_order` (`status`,`deleted_at`,`display_order`,`name`),
  CONSTRAINT `fk_programmes_type` FOREIGN KEY (`programme_type_id`) REFERENCES `programme_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_programmes_fees` CHECK ((((`application_fee` is null) or (`application_fee` >= 0)) and ((`tuition_fee` is null) or (`tuition_fee` >= 0)))),
  CONSTRAINT `chk_programmes_status` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'published',_utf8mb4'archived')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `granted_by` bigint unsigned DEFAULT NULL,
  `granted_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_role_permissions_permission_id` (`permission_id`),
  KEY `idx_role_permissions_granted_by` (`granted_by`),
  CONSTRAINT `fk_role_permissions_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`),
  UNIQUE KEY `uq_roles_slug` (`slug`),
  KEY `idx_roles_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `security_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `severity` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'info',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` json DEFAULT NULL,
  `fingerprint` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `occurred_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `resolved_at` datetime(6) DEFAULT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_security_events_user_occurred` (`user_id`,`occurred_at`),
  KEY `idx_security_events_type_occurred` (`event_type`,`occurred_at`),
  KEY `idx_security_events_severity_occurred` (`severity`,`occurred_at`),
  KEY `idx_security_events_fingerprint` (`fingerprint`),
  KEY `idx_security_events_resolution` (`resolved_at`,`severity`),
  KEY `fk_security_events_resolved_by` (`resolved_by`),
  CONSTRAINT `fk_security_events_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_security_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_security_events_severity` CHECK ((`severity` in (_utf8mb4'info',_utf8mb4'low',_utf8mb4'medium',_utf8mb4'high',_utf8mb4'critical')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `session_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_activity_at` datetime(6) NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessions_hash` (`session_hash`),
  KEY `idx_sessions_user_active` (`user_id`,`revoked_at`,`expires_at`),
  KEY `idx_sessions_expires_at` (`expires_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `setting_group` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'organisation',
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_ci,
  `encrypted_value` longtext COLLATE utf8mb4_unicode_ci,
  `value_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `is_sensitive` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `autoload` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_settings_key` (`setting_key`),
  KEY `idx_system_settings_group` (`setting_group`),
  KEY `idx_system_settings_autoload` (`autoload`),
  KEY `idx_system_settings_public` (`is_public`),
  CONSTRAINT `chk_system_settings_sensitive_public` CHECK (((`is_sensitive` = 0) or (`is_public` = 0))),
  CONSTRAINT `chk_system_settings_storage` CHECK ((((`is_sensitive` = 0) and (`encrypted_value` is null)) or ((`is_sensitive` = 1) and (`setting_value` is null)))),
  CONSTRAINT `chk_system_settings_value_type` CHECK ((`value_type` in (_utf8mb4'string',_utf8mb4'integer',_utf8mb4'boolean',_utf8mb4'json')))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `assigned_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `expires_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_user_roles_role_id` (`role_id`),
  KEY `idx_user_roles_assigned_by` (`assigned_by`),
  KEY `idx_user_roles_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_roles_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `email_verified_at` datetime(6) DEFAULT NULL,
  `failed_login_attempts` smallint unsigned NOT NULL DEFAULT '0',
  `locked_until` datetime(6) DEFAULT NULL,
  `last_login_at` datetime(6) DEFAULT NULL,
  `password_changed_at` datetime(6) DEFAULT NULL,
  `remember_token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_public_id` (`public_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_email_verified_at` (`email_verified_at`),
  KEY `idx_users_locked_until` (`locked_until`),
  KEY `idx_users_deleted_at` (`deleted_at`),
  CONSTRAINT `chk_users_status` CHECK ((`status` in (_utf8mb4'pending',_utf8mb4'active',_utf8mb4'suspended',_utf8mb4'disabled')))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-06 18:16:45

-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: aims_nigeria
-- ------------------------------------------------------
-- Server version	8.4.3

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
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `migration`, `batch`, `applied_at`) VALUES (1,'20260904_160000_create_system_settings_table',1,'2026-09-06 16:05:53.196783'),(2,'20260904_161000_create_identity_and_rbac_tables',1,'2026-09-06 16:05:53.878855'),(3,'20260904_162000_create_audit_and_security_tables',1,'2026-09-06 16:05:54.296113'),(4,'20260904_170000_create_authentication_tables',1,'2026-09-06 16:05:54.929028'),(5,'20260904_180000_create_organisational_leadership_tables',1,'2026-09-06 16:05:55.536749'),(6,'20260904_190000_create_membership_foundation_tables',1,'2026-09-06 16:05:56.347164'),(7,'20260904_191000_create_membership_review_comments_table',1,'2026-09-06 16:05:56.475546'),(8,'20260904_192000_create_member_portal_foundation_tables',1,'2026-09-06 16:05:56.762514'),(9,'20260904_200000_create_professional_programme_tables',1,'2026-09-06 16:05:57.560494'),(10,'20260904_210000_create_programme_application_enrolment_tables',1,'2026-09-06 16:05:57.960754'),(11,'20260905_220000_create_event_management_tables',1,'2026-09-06 16:05:58.426895'),(12,'20260905_230000_create_finance_payment_tables',1,'2026-09-06 16:05:59.404736'),(13,'20260905_240000_create_membership_renewal_tables',1,'2026-09-06 16:05:59.921692'),(14,'20260905_250000_create_certificate_tables',1,'2026-09-06 16:06:00.662838'),(15,'20260905_260000_create_cms_tables',1,'2026-09-06 16:06:01.651715'),(16,'20260905_270000_create_notification_tables',1,'2026-09-06 16:06:02.143232'),(17,'20260905_280000_add_reporting_indexes',1,'2026-09-06 16:06:02.761764'),(18,'20260905_290000_create_auth_request_rate_limits',1,'2026-09-06 16:06:02.910240');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` (`id`, `setting_group`, `setting_key`, `setting_value`, `encrypted_value`, `value_type`, `is_sensitive`, `is_public`, `autoload`, `description`, `created_at`, `updated_at`) VALUES (1,'organisation','organisation_name','Association for Information and Management Sciences, Nigeria',NULL,'string',0,1,1,'Official organisation name verified by the master project charter.','2026-09-06 16:06:02.927612','2026-09-06 16:06:02.927612'),(2,'organisation','short_name','AIMS Nigeria',NULL,'string',0,1,1,'Organisation short name verified by the master project charter.','2026-09-06 16:06:02.928329','2026-09-06 16:06:02.928329');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `active`, `created_at`, `updated_at`) VALUES (1,'Super Administrator','super-administrator','Full platform administration.',1,1,'2026-09-06 16:06:02.954169','2026-09-06 16:06:02.954169'),(2,'Membership Administrator','membership-administrator','Manages membership operations.',1,1,'2026-09-06 16:06:02.954798','2026-09-06 16:06:02.954798'),(3,'Membership Reviewer','membership-reviewer','Reviews membership applications.',1,1,'2026-09-06 16:06:02.955406','2026-09-06 16:06:02.955406'),(4,'Programme Administrator','programme-administrator','Manages professional programmes.',1,1,'2026-09-06 16:06:02.955926','2026-09-06 16:06:02.955926'),(5,'Programme Reviewer','programme-reviewer','Reviews submitted programme applications without decision or enrolment authority.',1,1,'2026-09-06 16:06:02.956494','2026-09-06 16:06:02.956494'),(6,'Finance Officer','finance-officer','Manages financial operations.',1,1,'2026-09-06 16:06:02.957134','2026-09-06 16:06:02.957134'),(7,'Event Administrator','event-administrator','Manages events.',1,1,'2026-09-06 16:06:02.957792','2026-09-06 16:06:02.957792'),(8,'Certificate Officer','certificate-officer','Manages certificate issuance and revocation.',1,1,'2026-09-06 16:06:02.958285','2026-09-06 16:06:02.958285'),(9,'Content Manager','content-manager','Manages website and resource content.',1,1,'2026-09-06 16:06:02.958873','2026-09-06 16:06:02.958873'),(10,'Management Viewer','management-viewer','Read-only management reporting access.',1,1,'2026-09-06 16:06:02.959559','2026-09-06 16:06:02.959559'),(11,'Auditor','auditor','Read-only audit and security review access.',1,1,'2026-09-06 16:06:02.960127','2026-09-06 16:06:02.960127');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES (1,'member.view','member','View member records.','2026-09-06 16:06:02.970826','2026-09-06 16:06:02.970826'),(2,'member.create','member','Create member records.','2026-09-06 16:06:02.971452','2026-09-06 16:06:02.971452'),(3,'member.update','member','Update member records.','2026-09-06 16:06:02.971995','2026-09-06 16:06:02.971995'),(4,'member.review','member','Review membership applications and record internal comments.','2026-09-06 16:06:02.972593','2026-09-06 16:06:02.972593'),(5,'member.approve','member','Approve membership applications.','2026-09-06 16:06:02.973166','2026-09-06 16:06:02.973166'),(6,'member.reject','member','Reject membership applications.','2026-09-06 16:06:02.973765','2026-09-06 16:06:02.973765'),(7,'member.query','member','Raise applicant-visible membership application queries.','2026-09-06 16:06:02.974348','2026-09-06 16:06:02.974348'),(8,'member.suspend','member','Suspend memberships.','2026-09-06 16:06:02.974903','2026-09-06 16:06:02.974903'),(9,'member.renewal_override','member','Override membership renewal status with an audited reason.','2026-09-06 16:06:02.975559','2026-09-06 16:06:02.975559'),(10,'member.renewal_policy','member','Configure membership renewal periods, windows and fees.','2026-09-06 16:06:02.976672','2026-09-06 16:06:02.976672'),(11,'programme.view','programme','View programmes and applications.','2026-09-06 16:06:02.977169','2026-09-06 16:06:02.977169'),(12,'programme.create','programme','Create programmes.','2026-09-06 16:06:02.977705','2026-09-06 16:06:02.977705'),(13,'programme.update','programme','Update programmes.','2026-09-06 16:06:02.978182','2026-09-06 16:06:02.978182'),(14,'programme.delete','programme','Archive or remove unpublished programmes and professional areas.','2026-09-06 16:06:02.978778','2026-09-06 16:06:02.978778'),(15,'programme.assign_coordinators','programme','Assign confirmed programme coordinators to programmes and professional areas.','2026-09-06 16:06:02.979392','2026-09-06 16:06:02.979392'),(16,'programme.application_view','programme','View submitted programme applications.','2026-09-06 16:06:02.980093','2026-09-06 16:06:02.980093'),(17,'programme.application_review','programme','Move submitted programme applications into review.','2026-09-06 16:06:02.980588','2026-09-06 16:06:02.980588'),(18,'programme.application_approve','programme','Approve programme applications.','2026-09-06 16:06:02.981040','2026-09-06 16:06:02.981040'),(19,'programme.application_reject','programme','Reject programme applications with a retained reason.','2026-09-06 16:06:02.981473','2026-09-06 16:06:02.981473'),(20,'programme.enrol','programme','Create and manage programme enrolments.','2026-09-06 16:06:02.982034','2026-09-06 16:06:02.982034'),(21,'payment.view','payment','View financial transactions.','2026-09-06 16:06:02.982587','2026-09-06 16:06:02.982587'),(22,'payment.verify','payment','Verify payment transactions.','2026-09-06 16:06:02.983370','2026-09-06 16:06:02.983370'),(23,'payment.refund','payment','Authorize payment refunds.','2026-09-06 16:06:02.983881','2026-09-06 16:06:02.983881'),(24,'certificate.issue','certificate','Issue certificates.','2026-09-06 16:06:02.985124','2026-09-06 16:06:02.985124'),(25,'certificate.revoke','certificate','Revoke certificates.','2026-09-06 16:06:02.985809','2026-09-06 16:06:02.985809'),(26,'certificate.type_manage','certificate','Configure certificate types and validity policies.','2026-09-06 16:06:02.986391','2026-09-06 16:06:02.986391'),(27,'event.manage','event','Manage events and registrations.','2026-09-06 16:06:02.986999','2026-09-06 16:06:02.986999'),(28,'cms.edit','cms','Edit content.','2026-09-06 16:06:02.987592','2026-09-06 16:06:02.987592'),(29,'cms.publish','cms','Publish content.','2026-09-06 16:06:02.988085','2026-09-06 16:06:02.988085'),(30,'report.view','report','View reports.','2026-09-06 16:06:02.988558','2026-09-06 16:06:02.988558'),(31,'report.export','report','Export reports.','2026-09-06 16:06:02.989033','2026-09-06 16:06:02.989033'),(32,'report.finance','report','View and export restricted financial reports.','2026-09-06 16:06:02.989623','2026-09-06 16:06:02.989623'),(33,'admin.manage_users','admin','Manage administrative users.','2026-09-06 16:06:02.990206','2026-09-06 16:06:02.990206'),(34,'admin.manage_roles','admin','Manage roles and permissions.','2026-09-06 16:06:02.990797','2026-09-06 16:06:02.990797'),(35,'system.settings.view','system','View system settings.','2026-09-06 16:06:02.991373','2026-09-06 16:06:02.991373'),(36,'system.settings.update','system','Update system settings.','2026-09-06 16:06:02.992003','2026-09-06 16:06:02.992003'),(37,'audit.view','audit','View the audit trail.','2026-09-06 16:06:02.992575','2026-09-06 16:06:02.992575'),(38,'security.events.view','security','View security events.','2026-09-06 16:06:02.993081','2026-09-06 16:06:02.993081'),(39,'security.events.resolve','security','Resolve security events.','2026-09-06 16:06:02.993648','2026-09-06 16:06:02.993648');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `granted_by`, `granted_at`) VALUES (1,1,NULL,'2026-09-06 16:06:02.994250'),(1,2,NULL,'2026-09-06 16:06:02.994250'),(1,3,NULL,'2026-09-06 16:06:02.994250'),(1,4,NULL,'2026-09-06 16:06:02.994250'),(1,5,NULL,'2026-09-06 16:06:02.994250'),(1,6,NULL,'2026-09-06 16:06:02.994250'),(1,7,NULL,'2026-09-06 16:06:02.994250'),(1,8,NULL,'2026-09-06 16:06:02.994250'),(1,9,NULL,'2026-09-06 16:06:02.994250'),(1,10,NULL,'2026-09-06 16:06:02.994250'),(1,11,NULL,'2026-09-06 16:06:02.994250'),(1,12,NULL,'2026-09-06 16:06:02.994250'),(1,13,NULL,'2026-09-06 16:06:02.994250'),(1,14,NULL,'2026-09-06 16:06:02.994250'),(1,15,NULL,'2026-09-06 16:06:02.994250'),(1,16,NULL,'2026-09-06 16:06:02.994250'),(1,17,NULL,'2026-09-06 16:06:02.994250'),(1,18,NULL,'2026-09-06 16:06:02.994250'),(1,19,NULL,'2026-09-06 16:06:02.994250'),(1,20,NULL,'2026-09-06 16:06:02.994250'),(1,21,NULL,'2026-09-06 16:06:02.994250'),(1,22,NULL,'2026-09-06 16:06:02.994250'),(1,23,NULL,'2026-09-06 16:06:02.994250'),(1,24,NULL,'2026-09-06 16:06:02.994250'),(1,25,NULL,'2026-09-06 16:06:02.994250'),(1,26,NULL,'2026-09-06 16:06:02.994250'),(1,27,NULL,'2026-09-06 16:06:02.994250'),(1,28,NULL,'2026-09-06 16:06:02.994250'),(1,29,NULL,'2026-09-06 16:06:02.994250'),(1,30,NULL,'2026-09-06 16:06:02.994250'),(1,31,NULL,'2026-09-06 16:06:02.994250'),(1,32,NULL,'2026-09-06 16:06:02.994250'),(1,33,NULL,'2026-09-06 16:06:02.994250'),(1,34,NULL,'2026-09-06 16:06:02.994250'),(1,35,NULL,'2026-09-06 16:06:02.994250'),(1,36,NULL,'2026-09-06 16:06:02.994250'),(1,37,NULL,'2026-09-06 16:06:02.994250'),(1,38,NULL,'2026-09-06 16:06:02.994250'),(1,39,NULL,'2026-09-06 16:06:02.994250'),(2,1,NULL,'2026-09-06 16:06:03.014899'),(2,2,NULL,'2026-09-06 16:06:03.015550'),(2,3,NULL,'2026-09-06 16:06:03.016143'),(2,4,NULL,'2026-09-06 16:06:03.016867'),(2,5,NULL,'2026-09-06 16:06:03.017559'),(2,6,NULL,'2026-09-06 16:06:03.018336'),(2,7,NULL,'2026-09-06 16:06:03.019136'),(2,8,NULL,'2026-09-06 16:06:03.019942'),(2,9,NULL,'2026-09-06 16:06:03.021242'),(2,10,NULL,'2026-09-06 16:06:03.022088'),(3,1,NULL,'2026-09-06 16:06:03.022797'),(3,4,NULL,'2026-09-06 16:06:03.023551'),(3,7,NULL,'2026-09-06 16:06:03.024306'),(4,11,NULL,'2026-09-06 16:06:03.025020'),(4,12,NULL,'2026-09-06 16:06:03.025800'),(4,13,NULL,'2026-09-06 16:06:03.026481'),(4,14,NULL,'2026-09-06 16:06:03.027124'),(4,15,NULL,'2026-09-06 16:06:03.027700'),(4,16,NULL,'2026-09-06 16:06:03.028356'),(4,17,NULL,'2026-09-06 16:06:03.029086'),(4,18,NULL,'2026-09-06 16:06:03.029686'),(4,19,NULL,'2026-09-06 16:06:03.030294'),(4,20,NULL,'2026-09-06 16:06:03.031036'),(5,11,NULL,'2026-09-06 16:06:03.031730'),(5,16,NULL,'2026-09-06 16:06:03.032417'),(5,17,NULL,'2026-09-06 16:06:03.033128'),(6,21,NULL,'2026-09-06 16:06:03.033886'),(6,22,NULL,'2026-09-06 16:06:03.034622'),(6,23,NULL,'2026-09-06 16:06:03.035189'),(6,30,NULL,'2026-09-06 16:06:03.035740'),(6,31,NULL,'2026-09-06 16:06:03.036311'),(6,32,NULL,'2026-09-06 16:06:03.036850'),(7,27,NULL,'2026-09-06 16:06:03.037529'),(8,24,NULL,'2026-09-06 16:06:03.038779'),(8,25,NULL,'2026-09-06 16:06:03.039734'),(8,26,NULL,'2026-09-06 16:06:03.040508'),(9,28,NULL,'2026-09-06 16:06:03.041132'),(9,29,NULL,'2026-09-06 16:06:03.041705'),(10,1,NULL,'2026-09-06 16:06:03.042351'),(10,11,NULL,'2026-09-06 16:06:03.042903'),(10,21,NULL,'2026-09-06 16:06:03.043535'),(10,30,NULL,'2026-09-06 16:06:03.044349'),(11,1,NULL,'2026-09-06 16:06:03.045088'),(11,11,NULL,'2026-09-06 16:06:03.045694'),(11,21,NULL,'2026-09-06 16:06:03.046433'),(11,30,NULL,'2026-09-06 16:06:03.047185'),(11,32,NULL,'2026-09-06 16:06:03.047875'),(11,37,NULL,'2026-09-06 16:06:03.048581'),(11,38,NULL,'2026-09-06 16:06:03.049284');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `leadership_groups`
--

LOCK TABLES `leadership_groups` WRITE;
/*!40000 ALTER TABLE `leadership_groups` DISABLE KEYS */;
INSERT INTO `leadership_groups` (`id`, `name`, `slug`, `description`, `display_order`, `active`, `created_at`, `updated_at`) VALUES (1,'Advisory Board','advisory-board',NULL,10,1,'2026-09-06 16:06:03.061906','2026-09-06 16:06:03.061906'),(2,'Governing Board','governing-board',NULL,20,1,'2026-09-06 16:06:03.062532','2026-09-06 16:06:03.062532'),(3,'Management Team','management-team',NULL,30,1,'2026-09-06 16:06:03.063252','2026-09-06 16:06:03.063252'),(4,'Programme Coordinators','programme-coordinators',NULL,40,1,'2026-09-06 16:06:03.063893','2026-09-06 16:06:03.063893');
/*!40000 ALTER TABLE `leadership_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `leadership_positions`
--

LOCK TABLES `leadership_positions` WRITE;
/*!40000 ALTER TABLE `leadership_positions` DISABLE KEYS */;
INSERT INTO `leadership_positions` (`id`, `leadership_group_id`, `name`, `description`, `display_order`, `active`, `created_at`, `updated_at`) VALUES (1,1,'Advisory Board Member',NULL,10,1,'2026-09-06 16:06:03.103083','2026-09-06 16:06:03.103083'),(2,2,'Governing Board Member',NULL,10,1,'2026-09-06 16:06:03.103986','2026-09-06 16:06:03.103986'),(3,3,'Management Team Member',NULL,10,1,'2026-09-06 16:06:03.104879','2026-09-06 16:06:03.104879'),(4,4,'Programme Coordinator',NULL,10,1,'2026-09-06 16:06:03.105598','2026-09-06 16:06:03.105598');
/*!40000 ALTER TABLE `leadership_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `people`
--

LOCK TABLES `people` WRITE;
/*!40000 ALTER TABLE `people` DISABLE KEYS */;
INSERT INTO `people` (`id`, `public_id`, `full_name`, `title`, `qualifications`, `biography`, `photo_path`, `professional_area`, `email`, `linkedin_url`, `display_order`, `active`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'2df7567e-3d2a-4fb9-9023-ec0a7f4cb3bb','Segun Folorunso','Prof',NULL,NULL,NULL,NULL,NULL,NULL,10,1,'2026-09-06 16:06:03.080334','2026-09-06 16:06:03.080334',NULL),(2,'03adff46-f795-4fe4-9840-afa4515c4474','T.A Okeowo','Dr',NULL,NULL,NULL,NULL,NULL,NULL,20,1,'2026-09-06 16:06:03.081171','2026-09-06 16:06:03.081171',NULL),(3,'92aca025-9503-459c-8a0f-c47473769609','Adeyinka Bakare','Dr',NULL,NULL,NULL,NULL,NULL,NULL,30,1,'2026-09-06 16:06:03.081857','2026-09-06 16:06:03.081857',NULL),(4,'184910c2-397c-4c58-9356-30dfb581b0d8','Ezekiel Odinmayo','Engr Dr',NULL,NULL,NULL,NULL,NULL,NULL,40,1,'2026-09-06 16:06:03.082418','2026-09-06 16:06:03.082418',NULL),(5,'eef471e3-6b7b-42a5-88bd-5fc88befb46a','Durosimi Adekunle','Mr',NULL,NULL,NULL,NULL,NULL,NULL,50,1,'2026-09-06 16:06:03.082950','2026-09-06 16:06:03.082950',NULL),(6,'f781a35d-ada2-4bf6-bca6-39ddd5e03730','Adedeji Oyenuga','Prof',NULL,NULL,NULL,NULL,NULL,NULL,60,1,'2026-09-06 16:06:03.083531','2026-09-06 16:06:03.083531',NULL),(7,'5430c743-a42a-4132-9006-e0afa8bcba81','Samuel Adekunle','Dr',NULL,NULL,NULL,NULL,NULL,NULL,70,1,'2026-09-06 16:06:03.084133','2026-09-06 16:06:03.084133',NULL),(8,'9e028a37-6b21-4435-979a-b039c716fba9','Tayo Olatunbosun','Mr',NULL,NULL,NULL,NULL,NULL,NULL,80,1,'2026-09-06 16:06:03.084753','2026-09-06 16:06:03.084753',NULL),(9,'bc2a7663-bcd0-4afa-be76-2aaf3ac91816','Sunday Fiola','Barr',NULL,NULL,NULL,NULL,NULL,NULL,90,1,'2026-09-06 16:06:03.085403','2026-09-06 16:06:03.085403',NULL),(10,'091e5720-b4cb-43bf-90d4-f53fbeedbee2','Akeem Bayewu','Engr',NULL,NULL,NULL,NULL,NULL,NULL,100,1,'2026-09-06 16:06:03.086019','2026-09-06 16:06:03.086019',NULL),(11,'58779074-0e2f-4768-ab38-38688312f440','Adeosun Olayiwola','Dr',NULL,NULL,NULL,NULL,NULL,NULL,110,1,'2026-09-06 16:06:03.086454','2026-09-06 16:06:03.086454',NULL),(12,'6541dd47-ec81-407b-b439-cdddd940ee91','Amusa Nojimu Adetunji','Prof',NULL,NULL,NULL,NULL,NULL,NULL,120,1,'2026-09-06 16:06:03.086947','2026-09-06 16:06:03.086947',NULL),(13,'f9a441f3-9754-4383-a28a-ebf5745a41e3','Lucky','Dr',NULL,NULL,NULL,NULL,NULL,NULL,130,1,'2026-09-06 16:06:03.087583','2026-09-06 16:06:03.087583',NULL),(14,'725dff8a-96da-4e1d-92cf-bbbc2d24eb7f','Awe O. J','Mr',NULL,NULL,NULL,NULL,NULL,NULL,140,1,'2026-09-06 16:06:03.088146','2026-09-06 16:06:03.088146',NULL),(15,'019d8d8e-12c8-4678-b155-0f6167f08df9','Favour Adekunle',NULL,NULL,NULL,NULL,NULL,NULL,NULL,150,1,'2026-09-06 16:06:03.088701','2026-09-06 16:06:03.088701',NULL),(16,'285f52fb-88ca-4ee1-8db2-383a02ed6a57','Faithful','Mr',NULL,NULL,NULL,NULL,NULL,NULL,160,1,'2026-09-06 16:06:03.089355','2026-09-06 16:06:03.089355',NULL),(17,'3b2197e3-a476-4be3-b0d5-005c09bc51d8','Tijani Ademola','Mr',NULL,NULL,NULL,NULL,NULL,NULL,170,1,'2026-09-06 16:06:03.090048','2026-09-06 16:06:03.090048',NULL),(18,'848dd552-cf99-424c-aa9e-9c60f7a50755','Dandy Makpah','Mr',NULL,NULL,NULL,NULL,NULL,NULL,180,1,'2026-09-06 16:06:03.090677','2026-09-06 16:06:03.090677',NULL),(19,'d2d0082b-e224-4c11-91df-6282e107caeb','Koleosho','Mr',NULL,NULL,NULL,NULL,NULL,NULL,190,1,'2026-09-06 16:06:03.091263','2026-09-06 16:06:03.091263',NULL),(20,'fb0968af-78e3-45ba-8981-170a59fc367f','Emmanuel Akinsade','Mr',NULL,NULL,NULL,NULL,NULL,NULL,200,1,'2026-09-06 16:06:03.091721','2026-09-06 16:06:03.091721',NULL),(21,'e686a23f-1f6b-4220-ac3e-56e54588b16e','Wasiu Olalekan','Mr',NULL,NULL,NULL,NULL,NULL,NULL,210,1,'2026-09-06 16:06:03.092448','2026-09-06 16:06:03.092448',NULL),(22,'322e815e-246d-4230-b562-890e7e516ffd','Alleh Segun','Mr',NULL,NULL,NULL,NULL,NULL,NULL,220,1,'2026-09-06 16:06:03.093067','2026-09-06 16:06:03.093067',NULL),(23,'40e1a03d-90c7-43d7-a959-542c63d80535','Fatola','Mr',NULL,NULL,NULL,NULL,NULL,NULL,230,1,'2026-09-06 16:06:03.093677','2026-09-06 16:06:03.093677',NULL),(24,'3931dd8f-2843-4755-83e3-6593d05c5950','Adeboye','Mr',NULL,NULL,NULL,NULL,NULL,NULL,240,1,'2026-09-06 16:06:03.094271','2026-09-06 16:06:03.094271',NULL),(25,'ae4f129e-3410-4827-b982-3e8137c3136c','Dahood','Mr',NULL,NULL,NULL,NULL,NULL,NULL,250,1,'2026-09-06 16:06:03.094843','2026-09-06 16:06:03.094843',NULL),(26,'b42ad830-e086-45b8-8f2d-653e95938e7e','Ishola Kolawole','Mr',NULL,NULL,NULL,NULL,NULL,NULL,260,1,'2026-09-06 16:06:03.095523','2026-09-06 16:06:03.095523',NULL),(27,'416ca635-fc43-4e45-82fd-ca471b4ded62','Oduntan Oluwatoyin','Mr',NULL,NULL,NULL,NULL,NULL,NULL,270,1,'2026-09-06 16:06:03.096202','2026-09-06 16:06:03.096202',NULL),(28,'fe062897-3dd2-4678-b115-732a027e5ae6','Abidoye','Mr',NULL,NULL,NULL,NULL,NULL,NULL,280,1,'2026-09-06 16:06:03.096835','2026-09-06 16:06:03.096835',NULL);
/*!40000 ALTER TABLE `people` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `leadership_assignments`
--

LOCK TABLES `leadership_assignments` WRITE;
/*!40000 ALTER TABLE `leadership_assignments` DISABLE KEYS */;
INSERT INTO `leadership_assignments` (`id`, `person_id`, `leadership_position_id`, `display_order`, `active`, `starts_at`, `ends_at`, `created_at`, `updated_at`) VALUES (1,1,1,10,1,NULL,NULL,'2026-09-06 16:06:03.115668','2026-09-06 16:06:03.115668'),(2,2,1,20,1,NULL,NULL,'2026-09-06 16:06:03.116560','2026-09-06 16:06:03.116560'),(3,3,2,10,1,NULL,NULL,'2026-09-06 16:06:03.117444','2026-09-06 16:06:03.117444'),(4,4,2,20,1,NULL,NULL,'2026-09-06 16:06:03.118319','2026-09-06 16:06:03.118319'),(5,5,2,30,1,NULL,NULL,'2026-09-06 16:06:03.119185','2026-09-06 16:06:03.119185'),(6,6,2,40,1,NULL,NULL,'2026-09-06 16:06:03.120139','2026-09-06 16:06:03.120139'),(7,7,2,50,1,NULL,NULL,'2026-09-06 16:06:03.120974','2026-09-06 16:06:03.120974'),(8,8,2,60,1,NULL,NULL,'2026-09-06 16:06:03.121826','2026-09-06 16:06:03.121826'),(9,9,2,70,1,NULL,NULL,'2026-09-06 16:06:03.122734','2026-09-06 16:06:03.122734'),(10,10,2,80,1,NULL,NULL,'2026-09-06 16:06:03.123780','2026-09-06 16:06:03.123780'),(11,11,2,90,1,NULL,NULL,'2026-09-06 16:06:03.124697','2026-09-06 16:06:03.124697'),(12,12,2,100,1,NULL,NULL,'2026-09-06 16:06:03.125489','2026-09-06 16:06:03.125489'),(13,13,3,10,1,NULL,NULL,'2026-09-06 16:06:03.126043','2026-09-06 16:06:03.126043'),(14,14,3,20,1,NULL,NULL,'2026-09-06 16:06:03.126768','2026-09-06 16:06:03.126768'),(15,9,3,30,1,NULL,NULL,'2026-09-06 16:06:03.127649','2026-09-06 16:06:03.127649'),(16,15,3,40,1,NULL,NULL,'2026-09-06 16:06:03.128713','2026-09-06 16:06:03.128713'),(17,16,3,50,1,NULL,NULL,'2026-09-06 16:06:03.129827','2026-09-06 16:06:03.129827'),(18,17,3,60,1,NULL,NULL,'2026-09-06 16:06:03.130614','2026-09-06 16:06:03.130614'),(19,18,3,70,1,NULL,NULL,'2026-09-06 16:06:03.131186','2026-09-06 16:06:03.131186'),(20,19,3,80,1,NULL,NULL,'2026-09-06 16:06:03.131787','2026-09-06 16:06:03.131787'),(21,20,3,90,1,NULL,NULL,'2026-09-06 16:06:03.132311','2026-09-06 16:06:03.132311'),(22,21,3,100,1,NULL,NULL,'2026-09-06 16:06:03.133079','2026-09-06 16:06:03.133079'),(23,22,3,110,1,NULL,NULL,'2026-09-06 16:06:03.133699','2026-09-06 16:06:03.133699'),(24,23,3,120,1,NULL,NULL,'2026-09-06 16:06:03.134222','2026-09-06 16:06:03.134222'),(25,24,4,10,1,NULL,NULL,'2026-09-06 16:06:03.134978','2026-09-06 16:06:03.134978'),(26,25,4,20,1,NULL,NULL,'2026-09-06 16:06:03.135783','2026-09-06 16:06:03.135783'),(27,26,4,30,1,NULL,NULL,'2026-09-06 16:06:03.136501','2026-09-06 16:06:03.136501'),(28,27,4,40,1,NULL,NULL,'2026-09-06 16:06:03.137376','2026-09-06 16:06:03.137376'),(29,28,4,50,1,NULL,NULL,'2026-09-06 16:06:03.138461','2026-09-06 16:06:03.138461');
/*!40000 ALTER TABLE `leadership_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `membership_grades`
--

LOCK TABLES `membership_grades` WRITE;
/*!40000 ALTER TABLE `membership_grades` DISABLE KEYS */;
INSERT INTO `membership_grades` (`id`, `public_id`, `name`, `abbreviation`, `description`, `eligibility`, `benefits`, `application_fee`, `annual_fee`, `fee_currency`, `display_order`, `active`, `created_at`, `updated_at`) VALUES (1,'5c168e52-935b-4816-8e02-4faf89ebebaf','Fellow',NULL,NULL,NULL,NULL,NULL,NULL,NULL,10,1,'2026-09-06 16:06:03.149468','2026-09-06 16:06:03.149468'),(2,'ab596e3e-66b7-47d9-a110-4eaf756b774d','Member',NULL,NULL,NULL,NULL,NULL,NULL,NULL,20,1,'2026-09-06 16:06:03.150300','2026-09-06 16:06:03.150300'),(3,'6fd2591f-5f48-48c1-9dd0-8c9f1a40ead3','Associate',NULL,NULL,NULL,NULL,NULL,NULL,NULL,30,1,'2026-09-06 16:06:03.152505','2026-09-06 16:06:03.152505');
/*!40000 ALTER TABLE `membership_grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `professional_areas`
--

LOCK TABLES `professional_areas` WRITE;
/*!40000 ALTER TABLE `professional_areas` DISABLE KEYS */;
INSERT INTO `professional_areas` (`id`, `public_id`, `code`, `name`, `slug`, `description`, `display_order`, `active`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'165918a8-c269-4941-bf2a-3eb3635743bf',NULL,'General Management','general-management',NULL,10,1,'2026-09-06 16:06:03.164289','2026-09-06 16:06:03.164289',NULL),(2,'f722d13f-05ca-4fa2-a663-31346055bdd2',NULL,'Marketing','marketing',NULL,20,1,'2026-09-06 16:06:03.165155','2026-09-06 16:06:03.165155',NULL),(3,'759485d3-d37d-4eb0-8cb6-d44c64fe62f6',NULL,'Human Resources','human-resources',NULL,30,1,'2026-09-06 16:06:03.166306','2026-09-06 16:06:03.166306',NULL),(4,'4a2fc608-315b-4ef9-b8a9-faad7ab971a0',NULL,'Finance','finance',NULL,40,1,'2026-09-06 16:06:03.166941','2026-09-06 16:06:03.166941',NULL),(5,'6db5a501-d8d7-4e05-96af-ebd323f3c724',NULL,'Operations','operations',NULL,50,1,'2026-09-06 16:06:03.167563','2026-09-06 16:06:03.167563',NULL),(6,'f619418a-bbde-43f4-9dc2-83e2988ef9c1',NULL,'ICT','ict',NULL,60,1,'2026-09-06 16:06:03.168215','2026-09-06 16:06:03.168215',NULL);
/*!40000 ALTER TABLE `professional_areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `programme_types`
--

LOCK TABLES `programme_types` WRITE;
/*!40000 ALTER TABLE `programme_types` DISABLE KEYS */;
INSERT INTO `programme_types` (`id`, `public_id`, `name`, `slug`, `description`, `display_order`, `active`, `created_at`, `updated_at`) VALUES (1,'ae0df96e-d5db-49c8-9e4b-dcd13673fa28','Diploma','diploma',NULL,10,1,'2026-09-06 16:06:03.176859','2026-09-06 16:06:03.176859'),(2,'6c211caa-3273-4db7-8aae-b323b31ee079','Higher Diploma','higher-diploma',NULL,20,1,'2026-09-06 16:06:03.177499','2026-09-06 16:06:03.177499'),(3,'492b1d92-f912-40cd-942b-e6bf12db1070','Graduate Certificate','graduate-certificate',NULL,30,1,'2026-09-06 16:06:03.178111','2026-09-06 16:06:03.178111'),(4,'a24e27fb-04b3-45cd-91c6-03f37184a93c','Post Graduate Diploma','post-graduate-diploma',NULL,40,1,'2026-09-06 16:06:03.178721','2026-09-06 16:06:03.178721');
/*!40000 ALTER TABLE `programme_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `event_types`
--

LOCK TABLES `event_types` WRITE;
/*!40000 ALTER TABLE `event_types` DISABLE KEYS */;
INSERT INTO `event_types` (`id`, `public_id`, `name`, `slug`, `display_order`, `active`, `created_at`, `updated_at`) VALUES (1,'9a8a52e0-f79b-4a71-8ac5-b44504540701','Induction','induction',10,1,'2026-09-06 16:06:03.189980','2026-09-06 16:06:03.189980'),(2,'9a8a52e0-f79b-4a71-8ac5-b44504540702','Investiture','investiture',20,1,'2026-09-06 16:06:03.190748','2026-09-06 16:06:03.190748'),(3,'9a8a52e0-f79b-4a71-8ac5-b44504540703','Conference','conference',30,1,'2026-09-06 16:06:03.191429','2026-09-06 16:06:03.191429'),(4,'9a8a52e0-f79b-4a71-8ac5-b44504540704','Workshop','workshop',40,1,'2026-09-06 16:06:03.192016','2026-09-06 16:06:03.192016'),(5,'9a8a52e0-f79b-4a71-8ac5-b44504540705','Webinar','webinar',50,1,'2026-09-06 16:06:03.192716','2026-09-06 16:06:03.192716'),(6,'9a8a52e0-f79b-4a71-8ac5-b44504540706','AGM','agm',60,1,'2026-09-06 16:06:03.193319','2026-09-06 16:06:03.193319'),(7,'9a8a52e0-f79b-4a71-8ac5-b44504540707','Training','training',70,1,'2026-09-06 16:06:03.194022','2026-09-06 16:06:03.194022'),(8,'9a8a52e0-f79b-4a71-8ac5-b44504540708','Awards','awards',80,1,'2026-09-06 16:06:03.194558','2026-09-06 16:06:03.194558'),(9,'9a8a52e0-f79b-4a71-8ac5-b44504540709','Other','other',90,1,'2026-09-06 16:06:03.195069','2026-09-06 16:06:03.195069');
/*!40000 ALTER TABLE `event_types` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-06 18:16:46
