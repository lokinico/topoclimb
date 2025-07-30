# 🗄️ STRUCTURE BASE DE DONNÉES PRODUCTION - TopoclimbCH

**Date:** 30 juillet 2025  
**Objectif:** Documenter la structure exacte de votre base MySQL de production  

---

## 🚨 PROBLÈME IDENTIFIÉ PAR GEMINI

**Analyse des modifications récentes (2 semaines) :**

### ❌ **CAUSE RACINE : Incohérence champs base de données**

1. **Code AuthService.php (ligne 79) cherche :**
   ```php
   SELECT * FROM users WHERE mail = ? AND actif = 1 LIMIT 1
   ```

2. **Mais script de recréation database créé :**
   ```sql
   email VARCHAR(255) UNIQUE NOT NULL,
   ```

3. **RÉSULTAT :** Le code cherche `mail` mais la base a `email` !

### 📋 **MODIFICATIONS QUI ONT CASSÉ LE SYSTÈME**

- **Commit 99b79e6** : "correction urgente email → mail"  
- **Commit 969d7c0** : "AuthService.php avec requête EXACTE"  
- **Problème** : Ces commits ont changé le code pour utiliser `mail` sans adapter la base

---

## 📊 POUR OBTENIR VOTRE STRUCTURE EXACTE

**Exécutez sur votre serveur MySQL :**

### 1️⃣ **Structure table users**
```sql
DESCRIBE users;
```

### 2️⃣ **Création table users**
```sql
SHOW CREATE TABLE users;
```

### 3️⃣ **Export complet structure**
```sql
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users'
ORDER BY ORDINAL_POSITION;
```

---

## 📝 COLLEZ ICI VOTRE RÉSULTAT

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : mer. 30 juil. 2025 à 12:00
-- Version du serveur : 10.11.9-MariaDB
-- Version de PHP : 8.3.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `sh139940_`
--

-- --------------------------------------------------------

--
-- Structure de la table `auth_logs`
--

CREATE TABLE `auth_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('login','logout','failed_login','password_reset') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_alerts`
--

CREATE TABLE `climbing_alerts` (
  `id` int(11) NOT NULL,
  `alert_type_id` int(11) NOT NULL,
  `entity_type` enum('country','region','site','sector','route') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `viewed_count` int(11) DEFAULT 0,
  `reported_resolved_count` int(11) DEFAULT 0,
  `resolved_date` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_alert_confirmations`
--

CREATE TABLE `climbing_alert_confirmations` (
  `id` int(11) NOT NULL,
  `alert_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `confirmation_type` enum('viewed','confirmed','reported_resolved','disputed') NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_alert_types`
--

CREATE TABLE `climbing_alert_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `severity` enum('info','warning','danger','critical') NOT NULL DEFAULT 'info',
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_books`
--

CREATE TABLE `climbing_books` (
  `id` int(11) NOT NULL,
  `region_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_book_routes`
--

CREATE TABLE `climbing_book_routes` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `page_number` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_book_sectors`
--

CREATE TABLE `climbing_book_sectors` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_complete` tinyint(1) DEFAULT 1 COMMENT 'Toutes les voies du secteur incluses',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_checklist_items`
--

CREATE TABLE `climbing_checklist_items` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `icon` varchar(50) DEFAULT NULL,
  `equipment_type_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_checklist_templates`
--

CREATE TABLE `climbing_checklist_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('equipment','safety','preparation','other') NOT NULL DEFAULT 'equipment',
  `climbing_type` enum('sport','trad','boulder','multipitch','alpine','indoor','general') NOT NULL DEFAULT 'general',
  `is_public` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `copy_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_condition_reports`
--

CREATE TABLE `climbing_condition_reports` (
  `id` int(11) NOT NULL,
  `entity_type` enum('site','sector','route') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `condition_status` enum('excellent','good','average','poor','dangerous','closed') NOT NULL,
  `moisture_level` enum('dry','slightly_damp','wet','very_wet') DEFAULT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `crowd_level` enum('empty','quiet','moderate','crowded','very_crowded') DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_countries`
--

CREATE TABLE `climbing_countries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` char(2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_difficulty_conversions`
--

CREATE TABLE `climbing_difficulty_conversions` (
  `id` int(11) NOT NULL,
  `from_grade_id` int(11) NOT NULL,
  `to_grade_id` int(11) NOT NULL,
  `is_approximate` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_difficulty_grades`
--

CREATE TABLE `climbing_difficulty_grades` (
  `id` int(11) NOT NULL,
  `system_id` int(11) NOT NULL,
  `value` varchar(10) NOT NULL,
  `numerical_value` decimal(5,2) NOT NULL,
  `sort_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_difficulty_systems`
--

CREATE TABLE `climbing_difficulty_systems` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_entity_tags`
--

CREATE TABLE `climbing_entity_tags` (
  `id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `entity_type` enum('country','region','site','sector','route') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_equipment_categories`
--

CREATE TABLE `climbing_equipment_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_equipment_kits`
--

CREATE TABLE `climbing_equipment_kits` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_equipment_kit_items`
--

CREATE TABLE `climbing_equipment_kit_items` (
  `id` int(11) NOT NULL,
  `kit_id` int(11) NOT NULL,
  `equipment_type_id` int(11) NOT NULL,
  `quantity` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_equipment_recommendations`
--

CREATE TABLE `climbing_equipment_recommendations` (
  `id` int(11) NOT NULL,
  `entity_type` enum('site','sector','route') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `equipment_type_id` int(11) NOT NULL,
  `quantity` varchar(20) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_equipment_types`
--

CREATE TABLE `climbing_equipment_types` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_events`
--

CREATE TABLE `climbing_events` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('sortie','formation','compétition','nettoyage','autre') NOT NULL DEFAULT 'sortie',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `location_name` varchar(100) DEFAULT NULL,
  `coordinates_lat` decimal(10,8) DEFAULT NULL,
  `coordinates_lng` decimal(11,8) DEFAULT NULL,
  `difficulty_min` varchar(10) DEFAULT NULL,
  `difficulty_max` varchar(10) DEFAULT NULL,
  `difficulty_system_id` int(11) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `is_private` tinyint(1) DEFAULT 0,
  `registration_deadline` datetime DEFAULT NULL,
  `status` enum('draft','published','cancelled','completed') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_event_discussions`
--

CREATE TABLE `climbing_event_discussions` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_announcement` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_event_locations`
--

CREATE TABLE `climbing_event_locations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `entity_type` enum('site','sector','route') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_event_participants`
--

CREATE TABLE `climbing_event_participants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('organizer','co-organizer','participant','waiting','declined') NOT NULL DEFAULT 'participant',
  `status` enum('registered','confirmed','cancelled','attended','no-show') NOT NULL DEFAULT 'registered',
  `registration_date` datetime DEFAULT current_timestamp(),
  `invite_code` varchar(50) DEFAULT NULL,
  `can_bring_guests` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_event_shared_equipment`
--

CREATE TABLE `climbing_event_shared_equipment` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `equipment_type_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `provided_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_exposures`
--

CREATE TABLE `climbing_exposures` (
  `id` int(11) NOT NULL,
  `code` varchar(2) NOT NULL,
  `name` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_media`
--

CREATE TABLE `climbing_media` (
  `id` int(11) NOT NULL,
  `media_type` enum('image','pdf','video','topo') NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `storage_type` enum('local','s3','cloudinary','other') DEFAULT 'local',
  `original_filename` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_media_annotations`
--

CREATE TABLE `climbing_media_annotations` (
  `id` int(11) NOT NULL,
  `media_id` int(11) NOT NULL,
  `annotation_type` enum('route','point','area','text','arrow') NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `svg_data` text NOT NULL,
  `link_type` varchar(50) DEFAULT NULL,
  `link_id` int(11) DEFAULT NULL,
  `style_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`style_data`)),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_media_relationships`
--

CREATE TABLE `climbing_media_relationships` (
  `id` int(11) NOT NULL,
  `media_id` int(11) NOT NULL,
  `entity_type` enum('country','region','site','sector','route','user','event') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `relationship_type` enum('main','gallery','topo','profile','cover','other') NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `coordinates_x` decimal(6,3) DEFAULT NULL,
  `coordinates_y` decimal(6,3) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_media_tags`
--

CREATE TABLE `climbing_media_tags` (
  `id` int(11) NOT NULL,
  `media_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_months`
--

CREATE TABLE `climbing_months` (
  `id` int(11) NOT NULL,
  `month_number` tinyint(4) NOT NULL,
  `name` varchar(20) NOT NULL,
  `short_name` varchar(3) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_regions`
--

CREATE TABLE `climbing_regions` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `coordinates_lat` decimal(10,8) DEFAULT NULL,
  `coordinates_lng` decimal(11,8) DEFAULT NULL,
  `altitude` int(11) DEFAULT NULL,
  `best_season` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_routes`
--

CREATE TABLE `climbing_routes` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `number` int(11) NOT NULL,
  `difficulty` varchar(10) DEFAULT NULL COMMENT 'Stocké comme chaîne pour compatibilité avec différents systèmes',
  `difficulty_system_id` int(11) NOT NULL DEFAULT 1,
  `beauty` enum('0','1','2','3','4','5') NOT NULL DEFAULT '0',
  `style` enum('sport','trad','mix','boulder','aid','ice','other') DEFAULT NULL,
  `length` decimal(6,2) DEFAULT NULL COMMENT 'Longueur en mètres',
  `equipment` enum('poor','adequate','good','excellent') DEFAULT NULL,
  `rappel` varchar(50) DEFAULT NULL COMMENT 'Type de rappel',
  `comment` text DEFAULT NULL,
  `legacy_topo_item` varchar(100) DEFAULT NULL COMMENT 'Pour compatibilité',
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_seasonal_closures`
--

CREATE TABLE `climbing_seasonal_closures` (
  `id` int(11) NOT NULL,
  `entity_type` enum('site','sector','route') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `closure_type` enum('seasonal','maintenance','nature_protection','landowner','other') NOT NULL,
  `reason` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `recurrence_type` enum('none','yearly','monthly') DEFAULT 'none',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_sectors`
--

CREATE TABLE `climbing_sectors` (
  `id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `region_id` int(11) DEFAULT NULL,
  `site_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'Ancien item',
  `description` text DEFAULT NULL,
  `access_info` text DEFAULT NULL COMMENT 'Ancien Accès',
  `color` varchar(20) DEFAULT '#FF0000',
  `access_time` int(11) DEFAULT NULL COMMENT 'Temps d''accès en minutes',
  `altitude` int(11) DEFAULT NULL COMMENT 'Altitude en mètres',
  `approach` text DEFAULT NULL COMMENT 'Ancien Marche',
  `height` decimal(6,2) DEFAULT NULL COMMENT 'Hauteur en mètres',
  `parking_info` varchar(255) DEFAULT NULL COMMENT 'Ancien parc',
  `coordinates_lat` decimal(10,8) DEFAULT NULL,
  `coordinates_lng` decimal(11,8) DEFAULT NULL,
  `coordinates_swiss_e` varchar(100) DEFAULT NULL COMMENT 'Ancien coordCH',
  `coordinates_swiss_n` varchar(100) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_sector_exposures`
--

CREATE TABLE `climbing_sector_exposures` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `exposure_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_sector_months`
--

CREATE TABLE `climbing_sector_months` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `month_id` int(11) NOT NULL,
  `quality` enum('excellent','good','average','poor','avoid') NOT NULL DEFAULT 'good',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_sites`
--

CREATE TABLE `climbing_sites` (
  `id` int(11) NOT NULL,
  `region_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'Ancien book/topo_item',
  `description` text DEFAULT NULL,
  `coordinates_lat` decimal(10,8) DEFAULT NULL,
  `coordinates_lng` decimal(11,8) DEFAULT NULL,
  `altitude` int(11) DEFAULT NULL,
  `access_info` text DEFAULT NULL,
  `year` varchar(4) DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_tags`
--

CREATE TABLE `climbing_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `category` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_user_checklists`
--

CREATE TABLE `climbing_user_checklists` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `based_on_template_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `entity_type` enum('site','sector','route') DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `climbing_user_checklist_items`
--

CREATE TABLE `climbing_user_checklist_items` (
  `id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `is_checked` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `original_item_id` int(11) DEFAULT NULL,
  `equipment_type_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` varchar(300) DEFAULT NULL,
  `route_id` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parking`
--

CREATE TABLE `parking` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `capacite` int(11) DEFAULT NULL,
  `coordonnees_gps` varchar(100) DEFAULT NULL,
  `coord_ch_ancien_e` int(11) DEFAULT NULL COMMENT 'Coordonnées suisses ancien format (E)',
  `coord_ch_ancien_n` int(11) DEFAULT NULL COMMENT 'Coordonnées suisses ancien format (N)',
  `coord_ch_nouveau_e` decimal(9,3) DEFAULT NULL COMMENT 'Coordonnées suisses nouveau format (E)',
  `coord_ch_nouveau_n` decimal(9,3) DEFAULT NULL COMMENT 'Coordonnées suisses nouveau format (N)',
  `type_surface` varchar(50) DEFAULT NULL,
  `gratuit` tinyint(1) DEFAULT 1,
  `tarif_horaire` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parking_secteur`
--

CREATE TABLE `parking_secteur` (
  `parking_id` int(11) NOT NULL,
  `secteur_id` int(11) NOT NULL,
  `distance_metres` int(11) DEFAULT NULL COMMENT 'Distance à pied entre le parking et le secteur en mètres',
  `temps_marche` int(11) DEFAULT NULL COMMENT 'Temps de marche estimé en minutes',
  `difficulte_acces` varchar(50) DEFAULT NULL COMMENT 'Facile, Moyen, Difficile',
  `instructions` text DEFAULT NULL COMMENT 'Instructions particulières pour accéder au secteur depuis ce parking'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `route`
--

CREATE TABLE `route` (
  `route_id` int(11) NOT NULL,
  `topo_item` varchar(100) DEFAULT NULL,
  `num` varchar(100) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `diff` varchar(100) DEFAULT NULL,
  `beau` varchar(11) DEFAULT NULL,
  `style` varchar(11) DEFAULT NULL COMMENT '1= Dalle\r\n2= Vertical\r\n3= Dévers\r\n4= Toit\r\n5= Trav\r\n6= Section combinées\r\n',
  `longueur` varchar(100) DEFAULT NULL,
  `equip` varchar(11) DEFAULT NULL COMMENT '1 : Mauvais\r\n2: Engagé\r\n3: Voie bien équipé tous les 3-4m\r\n4: Très bien équipé 2-3m',
  `rapp` varchar(100) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `secteur_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `secteur`
--

CREATE TABLE `secteur` (
  `id` int(11) NOT NULL,
  `item` varchar(100) DEFAULT NULL,
  `secteur` varchar(100) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `image` varchar(100) DEFAULT NULL,
  `image2` varchar(100) DEFAULT NULL,
  `image3` varchar(100) DEFAULT NULL,
  `coordinates` varchar(100) DEFAULT NULL,
  `coordCH` varchar(100) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `parc` varchar(100) DEFAULT NULL,
  `Accès` text NOT NULL,
  `accesstime` varchar(20) NOT NULL COMMENT 'min',
  `altitude` varchar(10) NOT NULL COMMENT 'en m',
  `Exposition` varchar(100) DEFAULT NULL,
  `periode` varchar(100) DEFAULT NULL,
  `book` varchar(50) DEFAULT NULL,
  `topo_item` varchar(100) DEFAULT NULL,
  `topo_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `topo`
--

CREATE TABLE `topo` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `annee` int(11) DEFAULT NULL,
  `editeur` varchar(255) DEFAULT NULL,
  `pays` varchar(255) DEFAULT NULL,
  `book` varchar(255) DEFAULT NULL,
  `isbn` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `ville` varchar(255) NOT NULL,
  `mail` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `autorisation` varchar(255) NOT NULL DEFAULT '3',
  `username` varchar(100) NOT NULL,
  `reset_token` varchar(20) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `date_registered` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_ascents`
--

CREATE TABLE `user_ascents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `topo_item` varchar(50) NOT NULL,
  `route_name` varchar(255) NOT NULL,
  `difficulty` varchar(50) NOT NULL,
  `ascent_type` varchar(50) NOT NULL,
  `climbing_type` varchar(50) NOT NULL,
  `with_user` varchar(255) DEFAULT NULL,
  `ascent_date` date NOT NULL,
  `quality_rating` tinyint(1) DEFAULT NULL,
  `difficulty_comment` varchar(50) DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `comment` text DEFAULT NULL,
  `favorite` tinyint(1) DEFAULT 0,
  `style` varchar(255) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_authorization_logs`
--

CREATE TABLE `user_authorization_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `old_authorization` varchar(1) DEFAULT NULL,
  `new_authorization` varchar(1) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_routes`
--

CREATE TABLE `user_routes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `topo_item` varchar(100) NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `difficulty` varchar(10) NOT NULL,
  `ascent_type` enum('flash','onsight','redpoint') NOT NULL,
  `favori` tinyint(1) DEFAULT 0,
  `style` varchar(20) DEFAULT NULL,
  `note` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `weather_cache`
--

CREATE TABLE `weather_cache` (
  `id` int(11) NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `data` longtext NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `auth_logs`
--
ALTER TABLE `auth_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `climbing_alerts`
--
ALTER TABLE `climbing_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alert_type_id` (`alert_type_id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `start_date` (`start_date`,`end_date`),
  ADD KEY `idx_alerts_entity` (`entity_type`,`entity_id`);

--
-- Index pour la table `climbing_alert_confirmations`
--
ALTER TABLE `climbing_alert_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `alert_id` (`alert_id`,`user_id`,`confirmation_type`);

--
-- Index pour la table `climbing_alert_types`
--
ALTER TABLE `climbing_alert_types`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `climbing_books`
--
ALTER TABLE `climbing_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `region_id` (`region_id`);

--
-- Index pour la table `climbing_book_routes`
--
ALTER TABLE `climbing_book_routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_book_route` (`book_id`,`route_id`),
  ADD KEY `idx_book_routes` (`book_id`),
  ADD KEY `idx_route_books` (`route_id`);

--
-- Index pour la table `climbing_book_sectors`
--
ALTER TABLE `climbing_book_sectors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_book_sector` (`book_id`,`sector_id`),
  ADD KEY `idx_book_sectors` (`book_id`),
  ADD KEY `idx_sector_books` (`sector_id`);

--
-- Index pour la table `climbing_checklist_items`
--
ALTER TABLE `climbing_checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `equipment_type_id` (`equipment_type_id`),
  ADD KEY `category` (`category`);

--
-- Index pour la table `climbing_checklist_templates`
--
ALTER TABLE `climbing_checklist_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`),
  ADD KEY `climbing_type` (`climbing_type`),
  ADD KEY `is_public` (`is_public`),
  ADD KEY `is_featured` (`is_featured`);

--
-- Index pour la table `climbing_condition_reports`
--
ALTER TABLE `climbing_condition_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`),
  ADD KEY `condition_status` (`condition_status`),
  ADD KEY `created_at` (`created_at`);

--
-- Index pour la table `climbing_countries`
--
ALTER TABLE `climbing_countries`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `climbing_difficulty_conversions`
--
ALTER TABLE `climbing_difficulty_conversions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `from_grade_id` (`from_grade_id`,`to_grade_id`),
  ADD KEY `to_grade_id` (`to_grade_id`);

--
-- Index pour la table `climbing_difficulty_grades`
--
ALTER TABLE `climbing_difficulty_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `system_id` (`system_id`,`value`);

--
-- Index pour la table `climbing_difficulty_systems`
--
ALTER TABLE `climbing_difficulty_systems`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `climbing_entity_tags`
--
ALTER TABLE `climbing_entity_tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tag_id` (`tag_id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`);

--
-- Index pour la table `climbing_equipment_categories`
--
ALTER TABLE `climbing_equipment_categories`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `climbing_equipment_kits`
--
ALTER TABLE `climbing_equipment_kits`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `climbing_equipment_kit_items`
--
ALTER TABLE `climbing_equipment_kit_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kit_id` (`kit_id`,`equipment_type_id`),
  ADD KEY `equipment_type_id` (`equipment_type_id`);

--
-- Index pour la table `climbing_equipment_recommendations`
--
ALTER TABLE `climbing_equipment_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_type` (`entity_type`,`entity_id`,`equipment_type_id`),
  ADD KEY `equipment_type_id` (`equipment_type_id`);

--
-- Index pour la table `climbing_equipment_types`
--
ALTER TABLE `climbing_equipment_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Index pour la table `climbing_events`
--
ALTER TABLE `climbing_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `difficulty_system_id` (`difficulty_system_id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `start_datetime` (`start_datetime`),
  ADD KEY `status` (`status`);

--
-- Index pour la table `climbing_event_discussions`
--
ALTER TABLE `climbing_event_discussions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Index pour la table `climbing_event_locations`
--
ALTER TABLE `climbing_event_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_id` (`event_id`,`entity_type`,`entity_id`);

--
-- Index pour la table `climbing_event_participants`
--
ALTER TABLE `climbing_event_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_id` (`event_id`,`user_id`);

--
-- Index pour la table `climbing_event_shared_equipment`
--
ALTER TABLE `climbing_event_shared_equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `equipment_type_id` (`equipment_type_id`),
  ADD KEY `provided_by_user_id` (`provided_by_user_id`);

--
-- Index pour la table `climbing_exposures`
--
ALTER TABLE `climbing_exposures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `climbing_media`
--
ALTER TABLE `climbing_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `media_type` (`media_type`),
  ADD KEY `is_public` (`is_public`),
  ADD KEY `is_featured` (`is_featured`);

--
-- Index pour la table `climbing_media_annotations`
--
ALTER TABLE `climbing_media_annotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `media_id` (`media_id`),
  ADD KEY `annotation_type` (`annotation_type`);

--
-- Index pour la table `climbing_media_relationships`
--
ALTER TABLE `climbing_media_relationships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `media_id` (`media_id`,`entity_type`,`entity_id`,`relationship_type`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`);

--
-- Index pour la table `climbing_media_tags`
--
ALTER TABLE `climbing_media_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `media_id` (`media_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Index pour la table `climbing_months`
--
ALTER TABLE `climbing_months`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `month_number` (`month_number`);

--
-- Index pour la table `climbing_regions`
--
ALTER TABLE `climbing_regions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_region_country` (`country_id`),
  ADD KEY `fk_region_created_by` (`created_by`),
  ADD KEY `fk_region_updated_by` (`updated_by`);

--
-- Index pour la table `climbing_routes`
--
ALTER TABLE `climbing_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_route_created_by` (`created_by`),
  ADD KEY `fk_route_updated_by` (`updated_by`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_beauty` (`beauty`),
  ADD KEY `idx_style` (`style`),
  ADD KEY `fk_difficulty_system` (`difficulty_system_id`),
  ADD KEY `idx_routes_difficulty` (`difficulty`),
  ADD KEY `idx_routes_sector_difficulty` (`sector_id`,`difficulty`);

--
-- Index pour la table `climbing_seasonal_closures`
--
ALTER TABLE `climbing_seasonal_closures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`),
  ADD KEY `start_date` (`start_date`,`end_date`),
  ADD KEY `closure_type` (`closure_type`);

--
-- Index pour la table `climbing_sectors`
--
ALTER TABLE `climbing_sectors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sector_created_by` (`created_by`),
  ADD KEY `fk_sector_updated_by` (`updated_by`),
  ADD KEY `idx_altitude` (`altitude`),
  ADD KEY `fk_sectors_books` (`book_id`),
  ADD KEY `idx_site_sectors` (`site_id`),
  ADD KEY `fk_sectors_regions` (`region_id`),
  ADD KEY `idx_sectors_coordinates` (`coordinates_lat`,`coordinates_lng`);

--
-- Index pour la table `climbing_sector_exposures`
--
ALTER TABLE `climbing_sector_exposures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sector_id` (`sector_id`,`exposure_id`),
  ADD KEY `idx_primary_exposure` (`sector_id`,`is_primary`),
  ADD KEY `idx_exposure_id` (`exposure_id`);

--
-- Index pour la table `climbing_sector_months`
--
ALTER TABLE `climbing_sector_months`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sector_id` (`sector_id`,`month_id`),
  ADD KEY `idx_quality` (`sector_id`,`quality`),
  ADD KEY `idx_month_id` (`month_id`);

--
-- Index pour la table `climbing_sites`
--
ALTER TABLE `climbing_sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_site_region` (`region_id`),
  ADD KEY `fk_site_created_by` (`created_by`),
  ADD KEY `fk_site_updated_by` (`updated_by`);

--
-- Index pour la table `climbing_tags`
--
ALTER TABLE `climbing_tags`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `climbing_user_checklists`
--
ALTER TABLE `climbing_user_checklists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `based_on_template_id` (`based_on_template_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`);

--
-- Index pour la table `climbing_user_checklist_items`
--
ALTER TABLE `climbing_user_checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checklist_id` (`checklist_id`),
  ADD KEY `original_item_id` (`original_item_id`),
  ADD KEY `equipment_type_id` (`equipment_type_id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_checked` (`is_checked`);

--
-- Index pour la table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `parking`
--
ALTER TABLE `parking`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `parking_secteur`
--
ALTER TABLE `parking_secteur`
  ADD PRIMARY KEY (`parking_id`,`secteur_id`),
  ADD KEY `secteur_id` (`secteur_id`);

--
-- Index pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `route`
--
ALTER TABLE `route`
  ADD PRIMARY KEY (`route_id`),
  ADD KEY `fk_route_secteur` (`secteur_id`);

--
-- Index pour la table `secteur`
--
ALTER TABLE `secteur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_book` (`book`),
  ADD KEY `fk_secteur_topo` (`topo_id`);

--
-- Index pour la table `topo`
--
ALTER TABLE `topo`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_autorisation` (`autorisation`);

--
-- Index pour la table `user_ascents`
--
ALTER TABLE `user_ascents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_route` (`user_id`,`route_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `topo_item` (`topo_item`),
  ADD KEY `ascent_date` (`ascent_date`),
  ADD KEY `quality_rating` (`quality_rating`),
  ADD KEY `favorite` (`favorite`);

--
-- Index pour la table `user_authorization_logs`
--
ALTER TABLE `user_authorization_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_auth_log` (`user_id`),
  ADD KEY `idx_changed_by` (`changed_by`);

--
-- Index pour la table `user_routes`
--
ALTER TABLE `user_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_routes_ibfk_1` (`user_id`);

--
-- Index pour la table `weather_cache`
--
ALTER TABLE `weather_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cache_key` (`cache_key`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `idx_weather_cache_expiry` (`expires_at`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `auth_logs`
--
ALTER TABLE `auth_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_alerts`
--
ALTER TABLE `climbing_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_alert_confirmations`
--
ALTER TABLE `climbing_alert_confirmations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_alert_types`
--
ALTER TABLE `climbing_alert_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_books`
--
ALTER TABLE `climbing_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_book_routes`
--
ALTER TABLE `climbing_book_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_book_sectors`
--
ALTER TABLE `climbing_book_sectors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_checklist_items`
--
ALTER TABLE `climbing_checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_checklist_templates`
--
ALTER TABLE `climbing_checklist_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_condition_reports`
--
ALTER TABLE `climbing_condition_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_countries`
--
ALTER TABLE `climbing_countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_difficulty_conversions`
--
ALTER TABLE `climbing_difficulty_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_difficulty_grades`
--
ALTER TABLE `climbing_difficulty_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_difficulty_systems`
--
ALTER TABLE `climbing_difficulty_systems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_entity_tags`
--
ALTER TABLE `climbing_entity_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_equipment_categories`
--
ALTER TABLE `climbing_equipment_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_equipment_kits`
--
ALTER TABLE `climbing_equipment_kits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_equipment_kit_items`
--
ALTER TABLE `climbing_equipment_kit_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_equipment_recommendations`
--
ALTER TABLE `climbing_equipment_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_equipment_types`
--
ALTER TABLE `climbing_equipment_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_events`
--
ALTER TABLE `climbing_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_event_discussions`
--
ALTER TABLE `climbing_event_discussions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_event_locations`
--
ALTER TABLE `climbing_event_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_event_participants`
--
ALTER TABLE `climbing_event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_event_shared_equipment`
--
ALTER TABLE `climbing_event_shared_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_exposures`
--
ALTER TABLE `climbing_exposures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_media`
--
ALTER TABLE `climbing_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_media_annotations`
--
ALTER TABLE `climbing_media_annotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_media_relationships`
--
ALTER TABLE `climbing_media_relationships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_media_tags`
--
ALTER TABLE `climbing_media_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_months`
--
ALTER TABLE `climbing_months`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_regions`
--
ALTER TABLE `climbing_regions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_routes`
--
ALTER TABLE `climbing_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_seasonal_closures`
--
ALTER TABLE `climbing_seasonal_closures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_sectors`
--
ALTER TABLE `climbing_sectors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_sector_exposures`
--
ALTER TABLE `climbing_sector_exposures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_sector_months`
--
ALTER TABLE `climbing_sector_months`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_sites`
--
ALTER TABLE `climbing_sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_tags`
--
ALTER TABLE `climbing_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_user_checklists`
--
ALTER TABLE `climbing_user_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `climbing_user_checklist_items`
--
ALTER TABLE `climbing_user_checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parking`
--
ALTER TABLE `parking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `route`
--
ALTER TABLE `route`
  MODIFY `route_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `secteur`
--
ALTER TABLE `secteur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `topo`
--
ALTER TABLE `topo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_ascents`
--
ALTER TABLE `user_ascents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_authorization_logs`
--
ALTER TABLE `user_authorization_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_routes`
--
ALTER TABLE `user_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `weather_cache`
--
ALTER TABLE `weather_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `auth_logs`
--
ALTER TABLE `auth_logs`
  ADD CONSTRAINT `auth_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `climbing_alerts`
--
ALTER TABLE `climbing_alerts`
  ADD CONSTRAINT `climbing_alerts_ibfk_1` FOREIGN KEY (`alert_type_id`) REFERENCES `climbing_alert_types` (`id`);

--
-- Contraintes pour la table `climbing_alert_confirmations`
--
ALTER TABLE `climbing_alert_confirmations`
  ADD CONSTRAINT `climbing_alert_confirmations_ibfk_1` FOREIGN KEY (`alert_id`) REFERENCES `climbing_alerts` (`id`);

--
-- Contraintes pour la table `climbing_books`
--
ALTER TABLE `climbing_books`
  ADD CONSTRAINT `climbing_books_ibfk_1` FOREIGN KEY (`region_id`) REFERENCES `climbing_regions` (`id`);

--
-- Contraintes pour la table `climbing_book_routes`
--
ALTER TABLE `climbing_book_routes`
  ADD CONSTRAINT `climbing_book_routes_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `climbing_books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `climbing_book_routes_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `climbing_routes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `climbing_book_sectors`
--
ALTER TABLE `climbing_book_sectors`
  ADD CONSTRAINT `climbing_book_sectors_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `climbing_books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `climbing_book_sectors_ibfk_2` FOREIGN KEY (`sector_id`) REFERENCES `climbing_sectors` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `climbing_checklist_items`
--
ALTER TABLE `climbing_checklist_items`
  ADD CONSTRAINT `climbing_checklist_items_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `climbing_checklist_templates` (`id`),
  ADD CONSTRAINT `climbing_checklist_items_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `climbing_equipment_types` (`id`);

--
-- Contraintes pour la table `climbing_difficulty_conversions`
--
ALTER TABLE `climbing_difficulty_conversions`
  ADD CONSTRAINT `climbing_difficulty_conversions_ibfk_1` FOREIGN KEY (`from_grade_id`) REFERENCES `climbing_difficulty_grades` (`id`),
  ADD CONSTRAINT `climbing_difficulty_conversions_ibfk_2` FOREIGN KEY (`to_grade_id`) REFERENCES `climbing_difficulty_grades` (`id`);

--
-- Contraintes pour la table `climbing_difficulty_grades`
--
ALTER TABLE `climbing_difficulty_grades`
  ADD CONSTRAINT `climbing_difficulty_grades_ibfk_1` FOREIGN KEY (`system_id`) REFERENCES `climbing_difficulty_systems` (`id`);

--
-- Contraintes pour la table `climbing_entity_tags`
--
ALTER TABLE `climbing_entity_tags`
  ADD CONSTRAINT `climbing_entity_tags_ibfk_1` FOREIGN KEY (`tag_id`) REFERENCES `climbing_tags` (`id`);

--
-- Contraintes pour la table `climbing_equipment_kit_items`
--
ALTER TABLE `climbing_equipment_kit_items`
  ADD CONSTRAINT `climbing_equipment_kit_items_ibfk_1` FOREIGN KEY (`kit_id`) REFERENCES `climbing_equipment_kits` (`id`),
  ADD CONSTRAINT `climbing_equipment_kit_items_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `climbing_equipment_types` (`id`);

--
-- Contraintes pour la table `climbing_equipment_recommendations`
--
ALTER TABLE `climbing_equipment_recommendations`
  ADD CONSTRAINT `climbing_equipment_recommendations_ibfk_1` FOREIGN KEY (`equipment_type_id`) REFERENCES `climbing_equipment_types` (`id`);

--
-- Contraintes pour la table `climbing_equipment_types`
--
ALTER TABLE `climbing_equipment_types`
  ADD CONSTRAINT `climbing_equipment_types_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `climbing_equipment_categories` (`id`);

--
-- Contraintes pour la table `climbing_events`
--
ALTER TABLE `climbing_events`
  ADD CONSTRAINT `climbing_events_ibfk_1` FOREIGN KEY (`difficulty_system_id`) REFERENCES `climbing_difficulty_systems` (`id`);

--
-- Contraintes pour la table `climbing_event_discussions`
--
ALTER TABLE `climbing_event_discussions`
  ADD CONSTRAINT `climbing_event_discussions_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `climbing_events` (`id`),
  ADD CONSTRAINT `climbing_event_discussions_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `climbing_event_discussions` (`id`);

--
-- Contraintes pour la table `climbing_event_locations`
--
ALTER TABLE `climbing_event_locations`
  ADD CONSTRAINT `climbing_event_locations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `climbing_events` (`id`);

--
-- Contraintes pour la table `climbing_event_participants`
--
ALTER TABLE `climbing_event_participants`
  ADD CONSTRAINT `climbing_event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `climbing_events` (`id`);

--
-- Contraintes pour la table `climbing_event_shared_equipment`
--
ALTER TABLE `climbing_event_shared_equipment`
  ADD CONSTRAINT `climbing_event_shared_equipment_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `climbing_events` (`id`),
  ADD CONSTRAINT `climbing_event_shared_equipment_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `climbing_equipment_types` (`id`);

--
-- Contraintes pour la table `climbing_media_annotations`
--
ALTER TABLE `climbing_media_annotations`
  ADD CONSTRAINT `climbing_media_annotations_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `climbing_media` (`id`);

--
-- Contraintes pour la table `climbing_media_relationships`
--
ALTER TABLE `climbing_media_relationships`
  ADD CONSTRAINT `climbing_media_relationships_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `climbing_media` (`id`);

--
-- Contraintes pour la table `climbing_media_tags`
--
ALTER TABLE `climbing_media_tags`
  ADD CONSTRAINT `climbing_media_tags_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `climbing_media` (`id`),
  ADD CONSTRAINT `climbing_media_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `climbing_tags` (`id`);

--
-- Contraintes pour la table `climbing_regions`
--
ALTER TABLE `climbing_regions`
  ADD CONSTRAINT `fk_region_country` FOREIGN KEY (`country_id`) REFERENCES `climbing_countries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_region_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_region_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `climbing_routes`
--
ALTER TABLE `climbing_routes`
  ADD CONSTRAINT `fk_difficulty_system` FOREIGN KEY (`difficulty_system_id`) REFERENCES `climbing_difficulty_systems` (`id`),
  ADD CONSTRAINT `fk_route_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_route_sector` FOREIGN KEY (`sector_id`) REFERENCES `climbing_sectors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_route_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `climbing_sectors`
--
ALTER TABLE `climbing_sectors`
  ADD CONSTRAINT `fk_sector_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sector_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sectors_regions` FOREIGN KEY (`region_id`) REFERENCES `climbing_regions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sectors_sites` FOREIGN KEY (`site_id`) REFERENCES `climbing_sites` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `climbing_sector_exposures`
--
ALTER TABLE `climbing_sector_exposures`
  ADD CONSTRAINT `climbing_sector_exposures_ibfk_1` FOREIGN KEY (`sector_id`) REFERENCES `climbing_sectors` (`id`),
  ADD CONSTRAINT `climbing_sector_exposures_ibfk_2` FOREIGN KEY (`exposure_id`) REFERENCES `climbing_exposures` (`id`);

--
-- Contraintes pour la table `climbing_sector_months`
--
ALTER TABLE `climbing_sector_months`
  ADD CONSTRAINT `climbing_sector_months_ibfk_1` FOREIGN KEY (`sector_id`) REFERENCES `climbing_sectors` (`id`),
  ADD CONSTRAINT `climbing_sector_months_ibfk_2` FOREIGN KEY (`month_id`) REFERENCES `climbing_months` (`id`);

--
-- Contraintes pour la table `climbing_sites`
--
ALTER TABLE `climbing_sites`
  ADD CONSTRAINT `fk_site_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_site_region` FOREIGN KEY (`region_id`) REFERENCES `climbing_regions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_site_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `climbing_user_checklists`
--
ALTER TABLE `climbing_user_checklists`
  ADD CONSTRAINT `climbing_user_checklists_ibfk_1` FOREIGN KEY (`based_on_template_id`) REFERENCES `climbing_checklist_templates` (`id`),
  ADD CONSTRAINT `climbing_user_checklists_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `climbing_events` (`id`);

--
-- Contraintes pour la table `climbing_user_checklist_items`
--
ALTER TABLE `climbing_user_checklist_items`
  ADD CONSTRAINT `climbing_user_checklist_items_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `climbing_user_checklists` (`id`),
  ADD CONSTRAINT `climbing_user_checklist_items_ibfk_2` FOREIGN KEY (`original_item_id`) REFERENCES `climbing_checklist_items` (`id`),
  ADD CONSTRAINT `climbing_user_checklist_items_ibfk_3` FOREIGN KEY (`equipment_type_id`) REFERENCES `climbing_equipment_types` (`id`);

--
-- Contraintes pour la table `parking_secteur`
--
ALTER TABLE `parking_secteur`
  ADD CONSTRAINT `parking_secteur_ibfk_1` FOREIGN KEY (`parking_id`) REFERENCES `parking` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parking_secteur_ibfk_2` FOREIGN KEY (`secteur_id`) REFERENCES `secteur` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `route`
--
ALTER TABLE `route`
  ADD CONSTRAINT `fk_route_secteur` FOREIGN KEY (`secteur_id`) REFERENCES `secteur` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `secteur`
--
ALTER TABLE `secteur`
  ADD CONSTRAINT `fk_secteur_topo` FOREIGN KEY (`topo_id`) REFERENCES `topo` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `topo`
--
ALTER TABLE `topo`
  ADD CONSTRAINT `topo_ibfk_1` FOREIGN KEY (`book`) REFERENCES `secteur` (`book`);

--
-- Contraintes pour la table `user_authorization_logs`
--
ALTER TABLE `user_authorization_logs`
  ADD CONSTRAINT `fk_auth_log_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_auth_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_routes`
--
ALTER TABLE `user_routes`
  ADD CONSTRAINT `user_routes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

## 🔧 SOLUTION SELON VOTRE STRUCTURE

Une fois que vous aurez collé votre structure, je pourrai :

1. ✅ **Identifier le nom exact** de la colonne email (email vs mail)
2. ✅ **Corriger AuthService.php** avec le bon nom de colonne  
3. ✅ **Restaurer** le système d'authentification qui fonctionnait avant

---

## 🔗 LIEN AVEC CLAUDE.MD

Cette structure sera ajoutée à `/home/nibaechl/topoclimb/CLAUDE.md` section :

```markdown
## 🗄️ STRUCTURE BASE DE DONNÉES DE PRODUCTION

### Table users (structure exacte)
[Structure à compléter selon votre export]

### Configuration AuthService
- Colonne email: [à déterminer]  
- Colonne password: password_hash
- Colonne actif: actif

### Requête authentification correcte
```php
$result = $this->db->fetchOne("SELECT * FROM users WHERE [COLONNE_EMAIL] = ? AND actif = 1 LIMIT 1", [$email]);
```

---

**🎯 OBJECTIF :** Réparer le système qui fonctionnait avant en utilisant VOS champs exacts, sans plus de complications.