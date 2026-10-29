-- Jeu de données de référence des tests (démonstration). Schéma + données au niveau de migration T063, 166 tables.
-- Mots de passe : tous les comptes partagent le même hachage de test. Noms, téléphones, adresses et dates de naissance anonymisés (Anonymiseur.php). Aucun secret.
-- Régénération : php tests/Support/fixtures/mettre_a_jour.php (voir tests/Support/fixtures/LISEZMOI.md).

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
DROP TABLE IF EXISTS `annees_scolaires`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `annees_scolaires` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format : 2025-2026',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `libelle` (`libelle`),
  KEY `idx_as_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Référentiel des années scolaires';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `annees_scolaires` WRITE;
/*!40000 ALTER TABLE `annees_scolaires` DISABLE KEYS */;
INSERT INTO `annees_scolaires` VALUES (1,'2024-2025',NULL,NULL,0,'2026-06-30 03:03:43','2026-06-30 03:03:43'),(2,'2025-2026',NULL,NULL,1,'2026-06-30 03:03:43','2026-06-30 03:03:43'),(4,'2026-2027','2026-09-01','2027-06-30',0,'2026-08-14 01:08:13','2026-08-14 01:08:13');
/*!40000 ALTER TABLE `annees_scolaires` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `annonces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `annonces` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` enum('tous','parents','eleves','enseignants','classe','utilisateurs') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tous',
  `classe_id` int unsigned DEFAULT NULL,
  `destinataires_ids` json DEFAULT NULL,
  `publie_par` int unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actif_aud` (`actif`,`audience`),
  KEY `idx_published` (`published_at`),
  KEY `idx_annonce_classe` (`classe_id`),
  CONSTRAINT `fk_annonce_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `annonces` WRITE;
/*!40000 ALTER TABLE `annonces` DISABLE KEYS */;
INSERT INTO `annonces` VALUES (1,'Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','tous',NULL,NULL,NULL,'2026-06-25 10:31:10',1,'2026-06-25 10:31:10','2026-06-25 10:31:10'),(2,'Réunion parents d\'élèves','Une réunion parents-professeurs aura lieu le vendredi 10 octobre à 17h en salle polyvalente.','parents',NULL,NULL,NULL,'2026-06-25 10:31:10',1,'2026-06-25 10:31:10','2026-06-25 10:31:10'),(3,'Examens du premier trimestre','Les examens du premier trimestre se dérouleront du 15 au 19 décembre. Consultez le planning sur le tableau d\'affichage.','eleves',NULL,NULL,NULL,'2026-06-25 10:31:10',1,'2026-06-25 10:31:10','2026-06-25 10:31:10'),(6,'merde','vos maudia','tous',NULL,NULL,1,'2026-07-10 04:12:39',1,'2026-07-10 04:12:39','2026-07-10 04:12:39'),(7,'TOKTOK','Allo Allo','tous',NULL,NULL,1,'2026-07-10 04:57:20',1,'2026-07-10 04:57:20','2026-07-10 04:57:20'),(9,'Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','tous',NULL,NULL,1,'2026-07-10 05:18:41',1,'2026-07-10 05:18:41','2026-07-10 05:18:41');
/*!40000 ALTER TABLE `annonces` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `api_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_uploads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `module` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned NOT NULL,
  `mime_type` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_etab` (`etablissement_id`),
  KEY `idx_etab_active` (`etablissement_id`,`deleted_at`),
  CONSTRAINT `fk63_api_uploads_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `api_uploads` WRITE;
/*!40000 ALTER TABLE `api_uploads` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_uploads` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `appreciations_matiere`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appreciations_matiere` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `professeur_id` int unsigned DEFAULT NULL,
  `texte` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etablissement_id` int unsigned NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appreciation_eleve_matiere_periode` (`eleve_id`,`matiere_id`,`periode_id`),
  KEY `idx_appr_classe_matiere_periode` (`classe_id`,`matiere_id`,`periode_id`),
  KEY `fk_appr_matiere` (`matiere_id`),
  KEY `fk_appr_periode` (`periode_id`),
  KEY `fk63_appreciations_matiere_etablissement_id` (`etablissement_id`),
  CONSTRAINT `fk63_appreciations_matiere_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_appreciations_matiere_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appr_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appr_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appr_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `appreciations_matiere` WRITE;
/*!40000 ALTER TABLE `appreciations_matiere` DISABLE KEYS */;
INSERT INTO `appreciations_matiere` VALUES (3,1,4,90,131,4,'Assez-Bien',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(4,1,6,90,131,7,'Insuffisant',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(5,1,5,90,131,5,'Assez-Bien',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(6,1,1,90,131,1,'Médiocre',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(7,1,187,90,131,6,'Assez-Bien',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(8,1,188,90,131,2,'Très insuffisant',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(9,1,189,90,131,9,'Très Bien',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(10,1,190,90,131,9,'Passable',1,1,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(11,1,4,91,131,4,'Passable',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(12,1,6,91,131,7,'Assez-Bien',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(13,1,5,91,131,5,'Passable',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(14,1,1,91,131,1,'Insuffisant',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(15,1,187,91,131,6,'Passable',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(16,1,188,91,131,2,'Insuffisant',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(17,1,189,91,131,9,'Bien',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(18,1,190,91,131,9,'Très Bien',1,1,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(19,109,4,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(20,110,4,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(21,111,4,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(22,112,4,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(23,113,4,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(24,114,4,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(25,115,4,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(26,116,4,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(27,117,4,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(28,118,4,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(29,119,4,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(30,120,4,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(31,109,1,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(32,110,1,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(33,111,1,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(34,112,1,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(35,113,1,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(36,114,1,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(37,115,1,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(38,116,1,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(39,117,1,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(40,118,1,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(41,119,1,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(42,120,1,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(43,109,5,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(44,110,5,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(45,111,5,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(46,112,5,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(47,113,5,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(48,114,5,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(49,115,5,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(50,116,5,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(51,117,5,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(52,118,5,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(53,119,5,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(54,120,5,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(55,109,6,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(56,110,6,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(57,111,6,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(58,112,6,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(59,113,6,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(60,114,6,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(61,115,6,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(62,116,6,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(63,117,6,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(64,118,6,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(65,119,6,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(66,120,6,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(67,109,187,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(68,110,187,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(69,111,187,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(70,112,187,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(71,113,187,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(72,114,187,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(73,115,187,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(74,116,187,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(75,117,187,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(76,118,187,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(77,119,187,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(78,120,187,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(79,109,188,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(80,110,188,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(81,111,188,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(82,112,188,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(83,113,188,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(84,114,188,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(85,115,188,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(86,116,188,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(87,117,188,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(88,118,188,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(89,119,188,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(90,120,188,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(91,109,189,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(92,110,189,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(93,111,189,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(94,112,189,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(95,113,189,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(96,114,189,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(97,115,189,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(98,116,189,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(99,117,189,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(100,118,189,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(101,119,189,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(102,120,189,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(103,109,190,100,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(104,110,190,100,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(105,111,190,100,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(106,112,190,100,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(107,113,190,100,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(108,114,190,100,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(109,115,190,100,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(110,116,190,100,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(111,117,190,100,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(112,118,190,100,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(113,119,190,100,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(114,120,190,100,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(115,109,4,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(116,110,4,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(117,111,4,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(118,112,4,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(119,113,4,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(120,114,4,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(121,115,4,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(122,116,4,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(123,117,4,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(124,118,4,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(125,119,4,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(126,120,4,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(127,109,1,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(128,110,1,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(129,111,1,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(130,112,1,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(131,113,1,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(132,114,1,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(133,115,1,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(134,116,1,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(135,117,1,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(136,118,1,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(137,119,1,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(138,120,1,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(139,109,5,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(140,110,5,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(141,111,5,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(142,112,5,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(143,113,5,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(144,114,5,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(145,115,5,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(146,116,5,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(147,117,5,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(148,118,5,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(149,119,5,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(150,120,5,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(151,109,6,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(152,110,6,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(153,111,6,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(154,112,6,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(155,113,6,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(156,114,6,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(157,115,6,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(158,116,6,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(159,117,6,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(160,118,6,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(161,119,6,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(162,120,6,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(163,109,187,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(164,110,187,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(165,111,187,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(166,112,187,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(167,113,187,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(168,114,187,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(169,115,187,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(170,116,187,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(171,117,187,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(172,118,187,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(173,119,187,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(174,120,187,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(175,109,188,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(176,110,188,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(177,111,188,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(178,112,188,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(179,113,188,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(180,114,188,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(181,115,188,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(182,116,188,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(183,117,188,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(184,118,188,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(185,119,188,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(186,120,188,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(187,109,189,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(188,110,189,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(189,111,189,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(190,112,189,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(191,113,189,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(192,114,189,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(193,115,189,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(194,116,189,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(195,117,189,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(196,118,189,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(197,119,189,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(198,120,189,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(199,109,190,101,132,1,'Excellent travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(200,110,190,101,132,1,'Très bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(201,111,190,101,132,1,'Bon travail',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(202,112,190,101,132,1,'Assez bien',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(203,113,190,101,132,1,'Efforts réguliers',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(204,114,190,101,132,1,'Résultats corrects',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(205,115,190,101,132,1,'Travail à poursuivre',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(206,116,190,101,132,1,'Doit progresser',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(207,117,190,101,132,1,'Résultats fragiles',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(208,118,190,101,132,1,'Efforts indispensables',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(209,119,190,101,132,1,'Difficultés à combler',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(210,120,190,101,132,1,'Accompagnement nécessaire',1,1,'2026-08-14 02:08:14','2026-08-14 02:08:14');
/*!40000 ALTER TABLE `appreciations_matiere` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL COMMENT 'NULL = action système ou connexion échouée',
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create|update|delete|login|login_failed|logout|export|import|permission_denied',
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'eleves|notes|absences|comptabilite|auth|parametres|...',
  `entite` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom de la table cible',
  `entite_id` int unsigned DEFAULT NULL COMMENT 'PK de l enregistrement affecté',
  `avant` json DEFAULT NULL COMMENT 'État avant (update/delete)',
  `apres` json DEFAULT NULL COMMENT 'État après  (create/update)',
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user` (`user_id`),
  KEY `idx_al_module` (`module`,`action`),
  KEY `idx_al_entite` (`entite`,`entite_id`),
  KEY `idx_al_date` (`created_at`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=852 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal immuable de toutes les actions utilisateurs';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,2,'create','comptabilite','paiements',6,NULL,'{\"montant\": 500000, \"eleve_id\": 2, \"frais_nom\": \"\", \"reference\": \"123456789\", \"paiement_id\": 6, \"mode_paiement\": \"especes\", \"frais_eleve_id\": 0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 00:53:57'),(2,2,'create','comptabilite','paiements',7,NULL,'{\"montant\": 345678, \"eleve_id\": 5, \"frais_nom\": \"\", \"reference\": \"098765\", \"paiement_id\": 7, \"mode_paiement\": \"especes\", \"frais_eleve_id\": 0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-07-07 00:55:57'),(3,1,'create','eleves','eleves',11,NULL,'{\"nom\": \"TESTTENANT\", \"prenom\": \"Migration\", \"eleve_id\": 11, \"classe_id\": 0, \"matricule\": \"2026-0001\", \"from_csv_import\": false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.0.0 Safari/537.36','2026-07-08 05:26:31'),(4,1,'create','comptabilite','paiements',11,NULL,'{\"montant\": 15000, \"eleve_id\": 3, \"frais_nom\": \"\", \"reference\": \"\", \"paiement_id\": 11, \"mode_paiement\": \"especes\", \"frais_eleve_id\": 0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-10 13:09:46'),(5,NULL,'create','comptabilite','paiements',14,NULL,'{\"montant\": 6000, \"eleve_id\": 83, \"frais_nom\": \"Frais test overpay\", \"reference\": \"\", \"paiement_id\": 14, \"mode_paiement\": \"especes\", \"frais_eleve_id\": 22}','::1','curl/8.19.0','2026-07-10 22:33:25'),(6,NULL,'create','comptabilite','paiements',15,NULL,'{\"montant\": 4000, \"eleve_id\": 83, \"frais_nom\": \"Frais test overpay\", \"reference\": \"\", \"paiement_id\": 15, \"mode_paiement\": \"especes\", \"frais_eleve_id\": 22}','::1','curl/8.19.0','2026-07-10 22:33:46'),(7,NULL,'create','absences','absences',NULL,NULL,'{\"date\": \"2026-07-09\", \"motif\": \"Test notif manuelle\", \"statut\": \"absent\", \"session\": \"journee\", \"eleve_id\": 84, \"classe_id\": 1, \"absence_id\": 0}','::1','curl/8.19.0','2026-07-10 22:38:17'),(8,NULL,'create','vie_scolaire','retard',2,NULL,'{\"eleve_id\": 89, \"classe_id\": 1, \"saisie_par\": 169, \"date_retard\": \"2026-07-11\", \"observation\": \"Test verification notif\", \"heure_prevue\": \"08:00\", \"duree_minutes\": 15, \"heure_arrivee\": \"08:15\", \"annee_scolaire\": \"2025-2026\"}','::1','curl/8.19.0','2026-07-11 14:09:40'),(9,NULL,'create','vie_scolaire','retard',3,NULL,'{\"eleve_id\": 89, \"classe_id\": 1, \"saisie_par\": 169, \"date_retard\": \"2026-07-11\", \"observation\": \"Test verification notif\", \"heure_prevue\": \"08:00\", \"duree_minutes\": 15, \"heure_arrivee\": \"08:15\", \"annee_scolaire\": \"2025-2026\"}','::1','curl/8.19.0','2026-07-11 14:18:39'),(10,NULL,'create','finance','frais_type',1,NULL,'{\"nom\": \"Frais Test Verification\", \"code\": \"TESTVERIF\", \"fired_at\": 1783796499.254465, \"periodicite\": \"annuel\", \"created_by_id\": 179, \"frais_type_id\": 1, \"annee_scolaire\": \"2025-2026\", \"montant_defaut\": 5000, \"est_obligatoire\": true}','::1','curl/8.19.0','2026-07-11 19:01:39'),(11,NULL,'create','finance','facture',2,NULL,'{\"id\": 2, \"note\": \"Facture test verification\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0002\", \"statut\": \"brouillon\", \"avoir_id\": null, \"eleve_id\": 1, \"eleve_nom\": \"Prenom19 Nom08\", \"emise_par\": 179, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 20:06:48\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-07-11 20:06:48\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"FinTest2\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-08-31\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2025-2026\", \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": \"Admin\", \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0001\"}','::1','curl/8.19.0','2026-07-11 19:06:48'),(12,NULL,'invoice.created','finance','facture',2,NULL,'{\"numero\": \"FCT-2026-0002\", \"eleve_id\": 1, \"facture_id\": 2, \"created_by_id\": 179, \"montant_total\": 5000, \"annee_scolaire\": \"2025-2026\"}','::1','curl/8.19.0','2026-07-11 19:06:48'),(13,NULL,'update','finance','facture',2,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','::1','curl/8.19.0','2026-07-11 19:07:36'),(14,NULL,'invoice.updated','finance','facture',2,NULL,'{\"numero\": \"FCT-2026-0002\", \"facture_id\": 2, \"updated_by\": 179, \"changed_fields\": {\"statut\": \"emise\"}}','::1','curl/8.19.0','2026-07-11 19:07:36'),(15,NULL,'receipt.generated','finance','recu',1,NULL,'{\"numero\": \"REC-2026-0001\", \"montant\": 5000, \"recu_id\": 1, \"eleve_id\": 1, \"facture_id\": 2, \"paiement_id\": 1, \"generated_by\": 179}','::1','curl/8.19.0','2026-07-11 19:47:34'),(16,NULL,'create','finance','paiement',1,NULL,'{\"note\": \"\", \"montant\": 5000, \"avoir_id\": null, \"facture_id\": 2, \"echeance_id\": null, \"date_paiement\": \"2026-07-11\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','::1','curl/8.19.0','2026-07-11 19:47:34'),(17,NULL,'payment.completed','finance','paiement',1,'{\"statut\": \"initie\"}','{\"numero\": \"PAI-2026-0001\", \"montant\": 5000, \"eleve_id\": 1, \"facture_id\": 2, \"paiement_id\": 1, \"completed_by\": 179, \"mode_paiement\": \"ESP\", \"montant_applique\": 5000, \"nouveau_statut_facture\": \"payee\"}','::1','curl/8.19.0','2026-07-11 19:47:34'),(18,NULL,'create','Finance','ecriture_comptable',1,NULL,'{\"numero\": \"ECR-2026-0001\", \"source\": \"payment_completed\", \"reference\": \"PAI-2026-0001\", \"created_by\": 179, \"ecriture_id\": 1, \"total_debit\": 5000, \"journal_code\": \"CAI\"}','::1','curl/8.19.0','2026-07-11 19:47:34'),(19,NULL,'caisse.ouvrir','finance','session_caisse',1,NULL,'{\"numero\": \"CSE-2026-0001\", \"opened_by\": 179, \"session_id\": 1, \"caissier_id\": 179, \"solde_initial\": 0}','::1','curl/8.19.0','2026-07-11 19:48:15'),(20,1,'create','finance','categorie_frais',8,NULL,'{\"nom\": \"Régularisation — Migration V1→V2\", \"code\": \"REGUL_MIGRATION\", \"icone\": \"file-text\", \"couleur\": \"#f59e0b\", \"description\": \"Réservé aux factures générées automatiquement lors de la migration des données financières V1 vers V2, pour des paiements V1 sans facture d\'origine identifiable.\"}','127.0.0.1',NULL,'2026-07-11 20:08:13'),(21,1,'create','finance','facture',3,NULL,'{\"id\": 3, \"note\": \"Migré depuis frais_eleves V1 #1 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0003\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 1, \"eleve_nom\": \"Prenom19 Nom08\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:14\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-07-11 21:08:14\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-09-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0001\"}','127.0.0.1',NULL,'2026-07-11 20:08:14'),(22,1,'update','finance','facture',3,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:14'),(23,1,'create','finance','paiement',2,NULL,'{\"note\": \"Migré depuis paiements V1 #1\", \"montant\": 5000, \"avoir_id\": null, \"facture_id\": 3, \"echeance_id\": null, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','127.0.0.1',NULL,'2026-07-11 20:08:15'),(24,1,'create','finance','facture',4,NULL,'{\"id\": 4, \"note\": \"Migré depuis frais_eleves V1 #2 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0004\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 2, \"eleve_nom\": \"Prenom05 Nom25\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:15\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-07-11 21:08:15\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-09-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0002\"}','127.0.0.1',NULL,'2026-07-11 20:08:15'),(25,1,'update','finance','facture',4,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:15'),(26,1,'create','finance','paiement',3,NULL,'{\"note\": \"Migré depuis paiements V1 #2\", \"montant\": 5000, \"avoir_id\": null, \"facture_id\": 4, \"echeance_id\": null, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','127.0.0.1',NULL,'2026-07-11 20:08:15'),(27,1,'create','finance','facture',5,NULL,'{\"id\": 5, \"note\": \"Migré depuis frais_eleves V1 #3 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0005\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 3, \"eleve_nom\": \"Prenom15 Nom19\", \"emise_par\": 1, \"classe_nom\": \"B\", \"created_at\": \"2026-07-11 21:08:15\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-07-11 21:08:16\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-09-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0003\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(28,1,'update','finance','facture',5,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(29,1,'create','finance','paiement',4,NULL,'{\"note\": \"Migré depuis paiements V1 #3\", \"montant\": 5000, \"avoir_id\": null, \"facture_id\": 5, \"echeance_id\": null, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(30,1,'create','finance','facture',6,NULL,'{\"id\": 6, \"note\": \"Migré depuis frais_eleves V1 #4 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0006\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 4, \"eleve_nom\": \"Prenom35 Nom13\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:16\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-07-11 21:08:16\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-09-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0004\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(31,1,'update','finance','facture',6,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(32,1,'create','finance','facture',7,NULL,'{\"id\": 7, \"note\": \"Migré depuis frais_eleves V1 #5 le 2026-07-11\", \"niveau\": \"Première\", \"numero\": \"FCT-2026-0007\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 5, \"eleve_nom\": \"Prenom31 Nom14\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:16\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-07-11 21:08:16\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-09-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0005\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(33,1,'update','finance','facture',7,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(34,1,'create','finance','facture',8,NULL,'{\"id\": 8, \"note\": \"Migré depuis frais_eleves V1 #6 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0008\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 6, \"eleve_nom\": \"Prenom28 Nom03\", \"emise_par\": 1, \"classe_nom\": \"B\", \"created_at\": \"2026-07-11 21:08:16\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-07-11 21:08:16\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-09-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0006\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(35,1,'update','finance','facture',8,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:16'),(36,1,'create','finance','facture',9,NULL,'{\"id\": 9, \"note\": \"Migré depuis frais_eleves V1 #8 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0009\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 1, \"eleve_nom\": \"Prenom19 Nom08\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:16\", \"montant_ht\": \"30000.00\", \"updated_at\": \"2026-07-11 21:08:16\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-06-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"30000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0001\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(37,1,'update','finance','facture',9,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(38,1,'create','finance','paiement',5,NULL,'{\"note\": \"Migré depuis paiements V1 #4\", \"montant\": 15000, \"avoir_id\": null, \"facture_id\": 9, \"echeance_id\": null, \"date_paiement\": \"2026-06-20\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(39,1,'create','finance','facture',10,NULL,'{\"id\": 10, \"note\": \"Migré depuis frais_eleves V1 #9 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0010\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 2, \"eleve_nom\": \"Prenom05 Nom25\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:17\", \"montant_ht\": \"30000.00\", \"updated_at\": \"2026-07-11 21:08:17\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-06-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"30000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0002\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(40,1,'update','finance','facture',10,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(41,1,'create','finance','paiement',6,NULL,'{\"note\": \"Migré depuis paiements V1 #5\", \"montant\": 15000, \"avoir_id\": null, \"facture_id\": 10, \"echeance_id\": null, \"date_paiement\": \"2026-06-20\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(42,1,'create','finance','facture',11,NULL,'{\"id\": 11, \"note\": \"Migré depuis frais_eleves V1 #10 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0011\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 3, \"eleve_nom\": \"Prenom15 Nom19\", \"emise_par\": 1, \"classe_nom\": \"B\", \"created_at\": \"2026-07-11 21:08:17\", \"montant_ht\": \"30000.00\", \"updated_at\": \"2026-07-11 21:08:17\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-06-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"30000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0003\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(43,1,'update','finance','facture',11,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:17'),(44,1,'create','finance','facture',12,NULL,'{\"id\": 12, \"note\": \"Migré depuis frais_eleves V1 #11 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0012\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 4, \"eleve_nom\": \"Prenom35 Nom13\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:17\", \"montant_ht\": \"30000.00\", \"updated_at\": \"2026-07-11 21:08:17\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-06-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"30000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0004\"}','127.0.0.1',NULL,'2026-07-11 20:08:18'),(45,1,'update','finance','facture',12,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:18'),(46,1,'create','finance','facture',13,NULL,'{\"id\": 13, \"note\": \"Migré depuis frais_eleves V1 #12 le 2026-07-11\", \"niveau\": \"Première\", \"numero\": \"FCT-2026-0013\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 5, \"eleve_nom\": \"Prenom31 Nom14\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:18\", \"montant_ht\": \"30000.00\", \"updated_at\": \"2026-07-11 21:08:18\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-06-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"30000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0005\"}','127.0.0.1',NULL,'2026-07-11 20:08:18'),(47,1,'update','finance','facture',13,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:18'),(48,1,'create','finance','facture',14,NULL,'{\"id\": 14, \"note\": \"Migré depuis frais_eleves V1 #13 le 2026-07-11\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0014\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 6, \"eleve_nom\": \"Prenom28 Nom03\", \"emise_par\": 1, \"classe_nom\": \"B\", \"created_at\": \"2026-07-11 21:08:18\", \"montant_ht\": \"30000.00\", \"updated_at\": \"2026-07-11 21:08:18\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-06-30\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"30000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0006\"}','127.0.0.1',NULL,'2026-07-11 20:08:18'),(49,1,'update','finance','facture',14,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:19'),(50,1,'create','finance','facture',15,NULL,'{\"id\": 15, \"note\": \"FACTURE DE RÉGULARISATION — créée automatiquement le 2026-07-11 lors de la migration V1→V2 pour rattacher le paiement V1 #6 (élève #2, 500000.00 XOF, 2026-07-07) qui n\'avait aucune facture ni frais d\'origine identifiable dans le système V1. Aucune perte de données : le paiement V1 original reste consultable, inchangé, dans la table `paiements`.\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0015\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 2, \"eleve_nom\": \"Prenom05 Nom25\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:19\", \"montant_ht\": \"500000.00\", \"updated_at\": \"2026-07-11 21:08:19\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-07-07\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"500000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0002\"}','127.0.0.1',NULL,'2026-07-11 20:08:19'),(51,1,'update','finance','facture',15,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:19'),(52,1,'create','finance','paiement',7,NULL,'{\"note\": \"RÉGULARISATION — Migré depuis paiements V1 #6 (sans facture d\'origine V1)\", \"montant\": 500000, \"avoir_id\": null, \"facture_id\": 15, \"echeance_id\": null, \"date_paiement\": \"2026-07-07\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"123456789\"}','127.0.0.1',NULL,'2026-07-11 20:08:19'),(53,1,'create','finance','facture',16,NULL,'{\"id\": 16, \"note\": \"FACTURE DE RÉGULARISATION — créée automatiquement le 2026-07-11 lors de la migration V1→V2 pour rattacher le paiement V1 #7 (élève #5, 345678.00 XOF, 2026-07-07) qui n\'avait aucune facture ni frais d\'origine identifiable dans le système V1. Aucune perte de données : le paiement V1 original reste consultable, inchangé, dans la table `paiements`.\", \"niveau\": \"Première\", \"numero\": \"FCT-2026-0016\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 5, \"eleve_nom\": \"Prenom31 Nom14\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-07-11 21:08:19\", \"montant_ht\": \"345678.00\", \"updated_at\": \"2026-07-11 21:08:19\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-07-07\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"345678.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0005\"}','127.0.0.1',NULL,'2026-07-11 20:08:19'),(54,1,'update','finance','facture',16,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:19'),(55,1,'create','finance','paiement',8,NULL,'{\"note\": \"RÉGULARISATION — Migré depuis paiements V1 #7 (sans facture d\'origine V1)\", \"montant\": 345678, \"avoir_id\": null, \"facture_id\": 16, \"echeance_id\": null, \"date_paiement\": \"2026-07-07\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"098765\"}','127.0.0.1',NULL,'2026-07-11 20:08:20'),(56,1,'create','finance','facture',17,NULL,'{\"id\": 17, \"note\": \"FACTURE DE RÉGULARISATION — créée automatiquement le 2026-07-11 lors de la migration V1→V2 pour rattacher le paiement V1 #11 (élève #3, 15000.00 XOF, 2026-07-10) qui n\'avait aucune facture ni frais d\'origine identifiable dans le système V1. Aucune perte de données : le paiement V1 original reste consultable, inchangé, dans la table `paiements`.\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0017\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 3, \"eleve_nom\": \"Prenom15 Nom19\", \"emise_par\": 1, \"classe_nom\": \"B\", \"created_at\": \"2026-07-11 21:08:20\", \"montant_ht\": \"15000.00\", \"updated_at\": \"2026-07-11 21:08:20\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-07-10\", \"date_emission\": \"2026-07-11\", \"montant_total\": \"15000.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0003\"}','127.0.0.1',NULL,'2026-07-11 20:08:20'),(57,1,'update','finance','facture',17,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','127.0.0.1',NULL,'2026-07-11 20:08:20'),(58,1,'create','finance','paiement',9,NULL,'{\"note\": \"RÉGULARISATION — Migré depuis paiements V1 #11 (sans facture d\'origine V1)\", \"montant\": 15000, \"avoir_id\": null, \"facture_id\": 17, \"echeance_id\": null, \"date_paiement\": \"2026-07-10\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','127.0.0.1',NULL,'2026-07-11 20:08:20'),(59,NULL,'create','finance','facture',18,NULL,'{\"id\": 18, \"note\": \"Test wiring verification\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0018\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 2, \"eleve_nom\": \"Prenom05 Nom25\", \"emise_par\": 188, \"classe_nom\": \"A\", \"created_at\": \"2026-07-12 00:55:18\", \"montant_ht\": \"7500.00\", \"updated_at\": \"2026-07-12 00:55:18\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"WireTest\", \"montant_paye\": \"0.00\", \"date_echeance\": \"2026-08-31\", \"date_emission\": \"2026-07-12\", \"montant_total\": \"7500.00\", \"annee_scolaire\": \"2025-2026\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": \"Admin\", \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0002\"}','::1','curl/8.19.0','2026-07-11 23:55:18'),(60,NULL,'invoice.created','finance','facture',18,NULL,'{\"numero\": \"FCT-2026-0018\", \"eleve_id\": 2, \"facture_id\": 18, \"created_by_id\": 188, \"montant_total\": 7500, \"annee_scolaire\": \"2025-2026\"}','::1','curl/8.19.0','2026-07-11 23:55:18'),(61,NULL,'update','finance','facture',18,'{\"statut\": \"brouillon\"}','{\"statut\": \"emise\"}','::1','curl/8.19.0','2026-07-11 23:55:48'),(62,NULL,'invoice.updated','finance','facture',18,NULL,'{\"numero\": \"FCT-2026-0018\", \"facture_id\": 18, \"updated_by\": 188, \"changed_fields\": {\"statut\": \"emise\"}}','::1','curl/8.19.0','2026-07-11 23:55:48'),(64,NULL,'create','finance','paiement',10,NULL,'{\"note\": \"\", \"montant\": 7500, \"avoir_id\": null, \"facture_id\": 18, \"echeance_id\": null, \"date_paiement\": \"2026-07-12\", \"mode_paiement\": \"ESP\", \"reference_externe\": \"\"}','::1','curl/8.19.0','2026-07-11 23:55:54'),(65,NULL,'payment.completed','finance','paiement',10,'{\"statut\": \"initie\"}','{\"numero\": \"PAI-2026-0010\", \"montant\": 7500, \"eleve_id\": 2, \"facture_id\": 18, \"paiement_id\": 10, \"completed_by\": 188, \"mode_paiement\": \"ESP\", \"montant_applique\": 7500, \"nouveau_statut_facture\": \"payee\"}','::1','curl/8.19.0','2026-07-11 23:55:54'),(66,NULL,'create','Finance','ecriture_comptable',10,NULL,'{\"numero\": \"ECR-2026-0010\", \"source\": \"payment_completed\", \"reference\": \"PAI-2026-0010\", \"created_by\": 188, \"ecriture_id\": 10, \"total_debit\": 7500, \"journal_code\": \"CAI\"}','::1','curl/8.19.0','2026-07-11 23:55:55'),(74,4,'create','Finance','ecriture_comptable',11,NULL,'{\"numero\": \"ECR-2026-0011\", \"source\": \"expense_validated\", \"reference\": \"DEP-2026-0001\", \"created_by\": 4, \"ecriture_id\": 11, \"total_debit\": 30000, \"journal_code\": \"ACH\"}','::1','curl/8.19.0','2026-07-12 01:35:30'),(75,4,'caisse.ouvrir','finance','session_caisse',2,NULL,'{\"numero\": \"CSE-2026-0002\", \"opened_by\": 4, \"session_id\": 2, \"caissier_id\": 4, \"solde_initial\": 100000}','::1','curl/8.19.0','2026-07-12 01:37:41'),(79,4,'caisse.mouvement.create','finance','mouvement_caisse',2,NULL,'{\"sens\": \"debit\", \"type\": \"decaissement\", \"source\": \"decaissement\", \"libelle\": \"Décaissement DEP-2026-0002 — Deuxieme test decaissement\", \"montant\": 15000, \"created_by\": 4, \"session_id\": 2, \"mouvement_id\": 2}','::1','curl/8.19.0','2026-07-12 01:37:57'),(80,4,'create','Finance','ecriture_comptable',12,NULL,'{\"numero\": \"ECR-2026-0012\", \"source\": \"cash_decaissement_manuel\", \"reference\": \"MVT-2\", \"created_by\": 4, \"ecriture_id\": 12, \"total_debit\": 15000, \"journal_code\": \"CAI\"}','::1','curl/8.19.0','2026-07-12 01:37:57'),(81,4,'caisse.balance.update','finance','session_caisse',2,'{\"solde\": 100000}','{\"solde\": 85000}','::1','curl/8.19.0','2026-07-12 01:37:57'),(82,4,'create','Finance','ecriture_comptable',13,NULL,'{\"numero\": \"ECR-2026-0013\", \"source\": \"expense_validated\", \"reference\": \"DEP-2026-0002\", \"created_by\": 4, \"ecriture_id\": 13, \"total_debit\": 15000, \"journal_code\": \"ACH\"}','::1','curl/8.19.0','2026-07-12 01:37:57'),(91,4,'create','Finance','ecriture_comptable',14,NULL,'{\"numero\": \"ECR-2026-0014\", \"source\": \"expense_validated\", \"reference\": \"DEP-2026-0003\", \"created_by\": 4, \"ecriture_id\": 14, \"total_debit\": 75000, \"journal_code\": \"ACH\"}','::1','curl/8.19.0','2026-07-12 01:41:25'),(112,4,'create','Finance','ecriture_comptable',15,NULL,'{\"numero\": \"ECR-2026-0015\", \"source\": \"payment_completed\", \"reference\": \"PAI-2026-0011\", \"created_by\": 4, \"ecriture_id\": 15, \"total_debit\": 20000, \"journal_code\": \"BNQ\"}','::1','curl/8.19.0','2026-07-12 02:52:23'),(113,1,'create','vie_scolaire','retard',4,NULL,'{\"eleve_id\": 1, \"classe_id\": 1, \"saisie_par\": 1, \"date_retard\": \"2026-07-10\", \"observation\": \"Test diagnostic\", \"heure_prevue\": \"08:00\", \"duree_minutes\": 15, \"heure_arrivee\": \"08:15\", \"annee_scolaire\": \"2025-2026\"}','::1','curl/8.19.0','2026-07-12 21:46:01'),(114,1,'create','vie_scolaire','retard',5,NULL,'{\"eleve_id\": 9, \"classe_id\": 1, \"saisie_par\": 1, \"date_retard\": \"2026-07-10\", \"observation\": \"Test dropdown eleve\", \"heure_prevue\": \"08:00\", \"duree_minutes\": 15, \"heure_arrivee\": \"08:15\", \"annee_scolaire\": \"2025-2026\"}','::1','curl/8.19.0','2026-07-12 21:52:04'),(115,1,'create','academique','evaluation',24,NULL,'{\"libelle\": \"TEST_MIGRATION_1785493236\", \"fired_at\": 1785493236.346635, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 24, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 10:20:37'),(116,1,'publier','academique','evaluation',24,NULL,'{\"libelle\": \"TEST_MIGRATION_1785493236\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 10:20:38'),(117,1,'create','academique','note',90,NULL,'{\"valeur\": 14.5, \"eleve_id\": 92, \"evaluation_id\": 24}','127.0.0.1',NULL,'2026-07-31 10:20:38'),(118,1,'publier','academique','evaluation',24,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 10:20:39'),(119,1,'verrouiller','academique','evaluation',24,NULL,'{\"libelle\": \"TEST_MIGRATION_1785493236\"}','127.0.0.1',NULL,'2026-07-31 10:20:39'),(120,1,'verrouiller','academique','evaluation',24,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 10:20:39'),(121,1,'create','academique','evaluation',25,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785493240\", \"fired_at\": 1785493241.977543, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 25, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 10:20:42'),(122,1,'publier','academique','evaluation',25,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785493240\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 10:20:42'),(123,1,'create','academique','note',91,NULL,'{\"valeur\": 17, \"eleve_id\": 92, \"evaluation_id\": 25}','127.0.0.1',NULL,'2026-07-31 10:20:42'),(124,1,'importer','academique','evaluation',25,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 10:20:43'),(125,1,'publier','academique','evaluation',25,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 10:20:43'),(126,1,'create','academique','evaluation',26,NULL,'{\"libelle\": \"TEST_MIGRATION_1785494087\", \"fired_at\": 1785494087.651337, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 26, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 10:34:47'),(127,1,'publier','academique','evaluation',26,NULL,'{\"libelle\": \"TEST_MIGRATION_1785494087\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 10:34:47'),(128,1,'create','academique','note',94,NULL,'{\"valeur\": 14.5, \"eleve_id\": 95, \"evaluation_id\": 26}','127.0.0.1',NULL,'2026-07-31 10:34:48'),(129,1,'publier','academique','evaluation',26,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 10:34:49'),(130,1,'verrouiller','academique','evaluation',26,NULL,'{\"libelle\": \"TEST_MIGRATION_1785494087\"}','127.0.0.1',NULL,'2026-07-31 10:34:50'),(131,1,'verrouiller','academique','evaluation',26,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 10:34:51'),(132,1,'create','academique','evaluation',27,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785494091\", \"fired_at\": 1785494092.198181, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 27, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 10:34:52'),(133,1,'publier','academique','evaluation',27,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785494091\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 10:34:52'),(134,1,'create','academique','note',95,NULL,'{\"valeur\": 17, \"eleve_id\": 95, \"evaluation_id\": 27}','127.0.0.1',NULL,'2026-07-31 10:34:52'),(135,1,'importer','academique','evaluation',27,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 10:34:52'),(136,1,'publier','academique','evaluation',27,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 10:34:53'),(137,1,'create','academique','evaluation',28,NULL,'{\"libelle\": \"TEST_MIGRATION_1785500223\", \"fired_at\": 1785500224.007586, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 28, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(138,1,'publier','academique','evaluation',28,NULL,'{\"libelle\": \"TEST_MIGRATION_1785500223\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(139,1,'create','academique','note',101,NULL,'{\"valeur\": 14.5, \"eleve_id\": 101, \"evaluation_id\": 28}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(140,1,'publier','academique','evaluation',28,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(141,1,'verrouiller','academique','evaluation',28,NULL,'{\"libelle\": \"TEST_MIGRATION_1785500223\"}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(142,1,'verrouiller','academique','evaluation',28,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(143,1,'create','academique','evaluation',29,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785500224\", \"fired_at\": 1785500224.478817, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 29, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(144,1,'publier','academique','evaluation',29,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785500224\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(145,1,'create','academique','note',102,NULL,'{\"valeur\": 17, \"eleve_id\": 101, \"evaluation_id\": 29}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(146,1,'importer','academique','evaluation',29,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(147,1,'publier','academique','evaluation',29,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 12:17:04'),(148,1,'create','academique','evaluation',30,NULL,'{\"libelle\": \"TEST_MIGRATION_1785500263\", \"fired_at\": 1785500263.966446, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 30, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 12:17:43'),(149,1,'publier','academique','evaluation',30,NULL,'{\"libelle\": \"TEST_MIGRATION_1785500263\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 12:17:44'),(150,1,'create','academique','note',103,NULL,'{\"valeur\": 14.5, \"eleve_id\": 102, \"evaluation_id\": 30}','127.0.0.1',NULL,'2026-07-31 12:17:44'),(151,1,'publier','academique','evaluation',30,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 12:17:44'),(152,1,'verrouiller','academique','evaluation',30,NULL,'{\"libelle\": \"TEST_MIGRATION_1785500263\"}','127.0.0.1',NULL,'2026-07-31 12:17:45'),(153,1,'verrouiller','academique','evaluation',30,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 12:17:45'),(154,1,'create','academique','evaluation',31,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785500266\", \"fired_at\": 1785500267.793951, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 31, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 12:17:47'),(155,1,'publier','academique','evaluation',31,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785500266\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 12:17:49'),(156,1,'create','academique','note',104,NULL,'{\"valeur\": 17, \"eleve_id\": 102, \"evaluation_id\": 31}','127.0.0.1',NULL,'2026-07-31 12:17:50'),(157,1,'importer','academique','evaluation',31,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 12:17:50'),(158,1,'publier','academique','evaluation',31,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 12:17:50'),(159,1,'create','academique','evaluation',32,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513769\", \"fired_at\": 1785513769.911547, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 32, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:02:49'),(160,1,'publier','academique','evaluation',32,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513769\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:02:50'),(161,1,'create','academique','note',106,NULL,'{\"valeur\": 14.5, \"eleve_id\": 104, \"evaluation_id\": 32}','127.0.0.1',NULL,'2026-07-31 16:02:50'),(162,1,'publier','academique','evaluation',32,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:02:50'),(163,1,'verrouiller','academique','evaluation',32,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513769\"}','127.0.0.1',NULL,'2026-07-31 16:02:51'),(164,1,'verrouiller','academique','evaluation',32,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:02:52'),(165,1,'create','academique','evaluation',33,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785513772\", \"fired_at\": 1785513773.112602, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 33, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:02:53'),(166,1,'publier','academique','evaluation',33,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785513772\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:02:53'),(167,1,'create','academique','note',107,NULL,'{\"valeur\": 17, \"eleve_id\": 104, \"evaluation_id\": 33}','127.0.0.1',NULL,'2026-07-31 16:02:53'),(168,1,'importer','academique','evaluation',33,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 16:02:53'),(169,1,'publier','academique','evaluation',33,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:02:53'),(170,1,'create','academique','evaluation',34,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513827\", \"fired_at\": 1785513827.449876, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 34, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:03:47'),(171,1,'publier','academique','evaluation',34,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513827\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:03:48'),(172,1,'create','academique','note',108,NULL,'{\"valeur\": 14.5, \"eleve_id\": 105, \"evaluation_id\": 34}','127.0.0.1',NULL,'2026-07-31 16:03:50'),(173,1,'publier','academique','evaluation',34,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:03:54'),(174,1,'verrouiller','academique','evaluation',34,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513827\"}','127.0.0.1',NULL,'2026-07-31 16:04:01'),(175,1,'verrouiller','academique','evaluation',34,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:04:03'),(176,1,'create','academique','evaluation',35,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785513844\", \"fired_at\": 1785513844.81911, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 35, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:04:04'),(177,1,'publier','academique','evaluation',35,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785513844\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:04:05'),(178,1,'create','academique','note',109,NULL,'{\"valeur\": 17, \"eleve_id\": 105, \"evaluation_id\": 35}','127.0.0.1',NULL,'2026-07-31 16:04:06'),(179,1,'importer','academique','evaluation',35,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 16:04:06'),(180,1,'publier','academique','evaluation',35,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:04:07'),(181,1,'create','academique','evaluation',36,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513958\", \"fired_at\": 1785513958.720059, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 36, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:05:58'),(182,1,'publier','academique','evaluation',36,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513958\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:05:58'),(183,1,'create','academique','note',110,NULL,'{\"valeur\": 14.5, \"eleve_id\": 106, \"evaluation_id\": 36}','127.0.0.1',NULL,'2026-07-31 16:05:59'),(184,1,'publier','academique','evaluation',36,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:06:00'),(185,1,'verrouiller','academique','evaluation',36,NULL,'{\"libelle\": \"TEST_MIGRATION_1785513958\"}','127.0.0.1',NULL,'2026-07-31 16:06:01'),(186,1,'verrouiller','academique','evaluation',36,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:06:01'),(187,1,'create','academique','evaluation',37,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785513961\", \"fired_at\": 1785513961.839247, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 37, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:06:01'),(188,1,'publier','academique','evaluation',37,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785513961\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:06:02'),(189,1,'create','academique','note',111,NULL,'{\"valeur\": 17, \"eleve_id\": 106, \"evaluation_id\": 37}','127.0.0.1',NULL,'2026-07-31 16:06:02'),(190,1,'importer','academique','evaluation',37,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 16:06:02'),(191,1,'publier','academique','evaluation',37,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:06:02'),(192,1,'create','academique','evaluation',38,NULL,'{\"libelle\": \"TEST_MIGRATION_1785515598\", \"fired_at\": 1785515598.300759, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 38, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:33:18'),(193,1,'publier','academique','evaluation',38,NULL,'{\"libelle\": \"TEST_MIGRATION_1785515598\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:33:18'),(194,1,'create','academique','note',113,NULL,'{\"valeur\": 14.5, \"eleve_id\": 108, \"evaluation_id\": 38}','127.0.0.1',NULL,'2026-07-31 16:33:18'),(195,1,'publier','academique','evaluation',38,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:33:19'),(196,1,'verrouiller','academique','evaluation',38,NULL,'{\"libelle\": \"TEST_MIGRATION_1785515598\"}','127.0.0.1',NULL,'2026-07-31 16:33:19'),(197,1,'verrouiller','academique','evaluation',38,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:33:19'),(198,1,'create','academique','evaluation',39,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785515599\", \"fired_at\": 1785515600.008457, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 39, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-07-31 16:33:20'),(199,1,'publier','academique','evaluation',39,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1785515599\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-07-31 16:33:20'),(200,1,'create','academique','note',114,NULL,'{\"valeur\": 17, \"eleve_id\": 108, \"evaluation_id\": 39}','127.0.0.1',NULL,'2026-07-31 16:33:20'),(201,1,'importer','academique','evaluation',39,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-07-31 16:33:20'),(202,1,'publier','academique','evaluation',39,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-07-31 16:33:21'),(203,1,'verrouiller','academique','evaluation',61,NULL,'{\"libelle\": \"[TEST BULLETIN 2026-2027 S2] Anglais - examen\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-18 03:43:27'),(204,1,'create','vie_scolaire','retard',6,NULL,'{\"eleve_id\": 3, \"classe_id\": 2, \"saisie_par\": 1, \"date_retard\": \"2026-08-19\", \"observation\": \"Test harnais CLI — pointage retard\", \"heure_prevue\": null, \"duree_minutes\": 12, \"heure_arrivee\": \"23:08:07\", \"annee_scolaire\": \"2024-2025\"}','127.0.0.1',NULL,'2026-08-20 23:08:07'),(205,1,'create','vie_scolaire','absence',8,NULL,'{\"type\": \"absence\", \"eleve_id\": 3, \"classe_id\": 2, \"heure_fin\": null, \"saisie_par\": 1, \"heure_debut\": null, \"observation\": \"Test harnais CLI — pointage absence\", \"date_absence\": \"2026-08-18\", \"duree_heures\": null, \"annee_scolaire\": \"2024-2025\"}','127.0.0.1',NULL,'2026-08-20 23:08:07'),(206,1,'create','vie_scolaire','absence',9,NULL,'{\"type\": \"absence\", \"eleve_id\": 3, \"classe_id\": 2, \"heure_fin\": null, \"saisie_par\": 1, \"heure_debut\": null, \"observation\": \"Test archive\", \"date_absence\": \"2026-08-17\", \"duree_heures\": null, \"annee_scolaire\": \"2024-2025\"}','127.0.0.1',NULL,'2026-08-20 23:09:26'),(207,1,'delete','vie_scolaire','absence',9,'{\"id\": 9, \"type\": \"absence\", \"statut\": \"non_justifiee\", \"eleve_id\": 3, \"classe_id\": 2, \"eleve_nom\": \"Nom19\", \"heure_fin\": null, \"matricule\": \"2024-0003\", \"classe_nom\": \"B\", \"created_at\": \"2026-08-21 00:09:26\", \"deleted_at\": null, \"saisie_par\": 1, \"updated_at\": \"2026-08-21 00:09:26\", \"heure_debut\": null, \"observation\": \"Test archive\", \"date_absence\": \"2026-08-17\", \"duree_heures\": null, \"eleve_prenom\": \"Prenom15\", \"annee_scolaire\": \"2024-2025\", \"saisie_par_nom\": null}',NULL,'127.0.0.1',NULL,'2026-08-20 23:09:26'),(208,1,'create','academique','bulletin',117,NULL,'{\"rang\": 9, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 8.06, \"decision\": \"rattrapage\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:37:54'),(209,1,'create','academique','bulletin',6,NULL,'{\"rang\": 3, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 9.67, \"decision\": \"rattrapage\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 90}','127.0.0.1',NULL,'2026-09-01 08:45:26'),(210,1,'create','academique','bulletin',121,NULL,'{\"rang\": 6, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 0, \"decision\": \"refuse\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 90}','127.0.0.1',NULL,'2026-09-01 08:45:27'),(211,1,'create','academique','bulletin',1,NULL,'{\"rang\": 4, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 9.08, \"decision\": \"rattrapage\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 90}','127.0.0.1',NULL,'2026-09-01 08:45:27'),(212,1,'create','academique','bulletin',4,NULL,'{\"rang\": 1, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"B\", \"moyenne\": 14.64, \"decision\": \"admis\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 90}','127.0.0.1',NULL,'2026-09-01 08:45:27'),(213,1,'create','academique','bulletin',3,NULL,'{\"rang\": 5, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 7.79, \"decision\": \"refuse\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 90}','127.0.0.1',NULL,'2026-09-01 08:45:28'),(214,1,'create','academique','bulletin',2,NULL,'{\"rang\": 2, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"AB\", \"moyenne\": 12.14, \"decision\": \"admis\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 90}','127.0.0.1',NULL,'2026-09-01 08:45:28'),(215,1,'create','academique','bulletin',6,NULL,'{\"rang\": 4, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 8.4, \"decision\": \"rattrapage\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 91}','127.0.0.1',NULL,'2026-09-01 08:45:28'),(216,1,'create','academique','bulletin',121,NULL,'{\"rang\": 6, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 0, \"decision\": \"refuse\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 91}','127.0.0.1',NULL,'2026-09-01 08:45:28'),(217,1,'create','academique','bulletin',1,NULL,'{\"rang\": 3, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 9.52, \"decision\": \"rattrapage\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 91}','127.0.0.1',NULL,'2026-09-01 08:45:29'),(218,1,'create','academique','bulletin',4,NULL,'{\"rang\": 1, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"AB\", \"moyenne\": 13.98, \"decision\": \"admis\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 91}','127.0.0.1',NULL,'2026-09-01 08:45:29'),(219,1,'create','academique','bulletin',3,NULL,'{\"rang\": 5, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 7.36, \"decision\": \"refuse\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 91}','127.0.0.1',NULL,'2026-09-01 08:45:29'),(220,1,'create','academique','bulletin',2,NULL,'{\"rang\": 2, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"AB\", \"moyenne\": 12.02, \"decision\": \"admis\", \"classe_id\": 131, \"nb_eleves\": 6, \"periode_id\": 91}','127.0.0.1',NULL,'2026-09-01 08:45:30'),(221,1,'create','academique','bulletin',117,NULL,'{\"rang\": 9, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 8.01, \"decision\": \"rattrapage\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:31'),(222,1,'create','academique','bulletin',115,NULL,'{\"rang\": 7, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"P\", \"moyenne\": 10.26, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:32'),(223,1,'create','academique','bulletin',116,NULL,'{\"rang\": 8, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 9.02, \"decision\": \"rattrapage\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:32'),(224,1,'create','academique','bulletin',109,NULL,'{\"rang\": 1, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"TB\", \"moyenne\": 17.31, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:33'),(225,1,'create','academique','bulletin',111,NULL,'{\"rang\": 3, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"B\", \"moyenne\": 14.52, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:33'),(226,1,'create','academique','bulletin',119,NULL,'{\"rang\": 11, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 5.61, \"decision\": \"refuse\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:34'),(227,1,'create','academique','bulletin',112,NULL,'{\"rang\": 4, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"AB\", \"moyenne\": 13.51, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:34'),(228,1,'create','academique','bulletin',113,NULL,'{\"rang\": 5, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"AB\", \"moyenne\": 12.34, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:35'),(229,1,'create','academique','bulletin',110,NULL,'{\"rang\": 2, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"B\", \"moyenne\": 15.96, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:36'),(230,1,'create','academique','bulletin',114,NULL,'{\"rang\": 6, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"P\", \"moyenne\": 11.01, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:36'),(231,1,'create','academique','bulletin',118,NULL,'{\"rang\": 10, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 6.94, \"decision\": \"refuse\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:37'),(232,1,'create','academique','bulletin',120,NULL,'{\"rang\": 12, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 4.96, \"decision\": \"refuse\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 100}','127.0.0.1',NULL,'2026-09-01 08:45:37'),(233,1,'create','academique','bulletin',117,NULL,'{\"rang\": 9, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 8.06, \"decision\": \"rattrapage\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:38'),(234,1,'create','academique','bulletin',115,NULL,'{\"rang\": 7, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"P\", \"moyenne\": 10.26, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:39'),(235,1,'create','academique','bulletin',116,NULL,'{\"rang\": 8, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 9.39, \"decision\": \"rattrapage\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:39'),(236,1,'create','academique','bulletin',109,NULL,'{\"rang\": 1, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"TB\", \"moyenne\": 17.67, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:40'),(237,1,'create','academique','bulletin',111,NULL,'{\"rang\": 3, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"B\", \"moyenne\": 14.89, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:40'),(238,1,'create','academique','bulletin',119,NULL,'{\"rang\": 11, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 5.97, \"decision\": \"refuse\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:41'),(239,1,'create','academique','bulletin',112,NULL,'{\"rang\": 4, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"AB\", \"moyenne\": 13.56, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:41'),(240,1,'create','academique','bulletin',113,NULL,'{\"rang\": 5, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"AB\", \"moyenne\": 12.81, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:42'),(241,1,'create','academique','bulletin',110,NULL,'{\"rang\": 2, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"B\", \"moyenne\": 15.96, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:43'),(242,1,'create','academique','bulletin',114,NULL,'{\"rang\": 6, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"P\", \"moyenne\": 11.37, \"decision\": \"admis\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:43'),(243,1,'create','academique','bulletin',118,NULL,'{\"rang\": 10, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 7.41, \"decision\": \"refuse\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:43'),(244,1,'create','academique','bulletin',120,NULL,'{\"rang\": 12, \"token\": \"***\", \"statut\": \"brouillon\", \"mention\": \"INS\", \"moyenne\": 4.96, \"decision\": \"refuse\", \"classe_id\": 132, \"nb_eleves\": 12, \"periode_id\": 101}','127.0.0.1',NULL,'2026-09-01 08:45:44'),(245,1,'create','academique','evaluation',72,NULL,'{\"libelle\": \"TEST_MIGRATION_1792852608\", \"fired_at\": 1792852608.430637, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 72, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(246,1,'publier','academique','evaluation',72,NULL,'{\"libelle\": \"TEST_MIGRATION_1792852608\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(247,1,'create','academique','note',500,NULL,'{\"valeur\": 14.5, \"eleve_id\": 123, \"evaluation_id\": 72}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(248,1,'publier','academique','evaluation',72,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(249,1,'verrouiller','academique','evaluation',72,NULL,'{\"libelle\": \"TEST_MIGRATION_1792852608\"}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(250,1,'verrouiller','academique','evaluation',72,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(251,1,'create','academique','evaluation',73,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792852608\", \"fired_at\": 1792852608.832472, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 73, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(252,1,'publier','academique','evaluation',73,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792852608\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(253,1,'create','academique','note',501,NULL,'{\"valeur\": 17, \"eleve_id\": 123, \"evaluation_id\": 73}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(254,1,'importer','academique','evaluation',73,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(255,1,'publier','academique','evaluation',73,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:36:48'),(256,1,'create','academique','evaluation',74,NULL,'{\"libelle\": \"TEST_MIGRATION_1792853957\", \"fired_at\": 1792853957.179834, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 74, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(257,1,'publier','academique','evaluation',74,NULL,'{\"libelle\": \"TEST_MIGRATION_1792853957\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(258,1,'create','academique','note',502,NULL,'{\"valeur\": 14.5, \"eleve_id\": 126, \"evaluation_id\": 74}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(259,1,'publier','academique','evaluation',74,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(260,1,'verrouiller','academique','evaluation',74,NULL,'{\"libelle\": \"TEST_MIGRATION_1792853957\"}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(261,1,'verrouiller','academique','evaluation',74,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(262,1,'create','academique','evaluation',75,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792853957\", \"fired_at\": 1792853957.615092, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 75, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(263,1,'publier','academique','evaluation',75,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792853957\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(264,1,'create','academique','note',503,NULL,'{\"valeur\": 17, \"eleve_id\": 126, \"evaluation_id\": 75}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(265,1,'importer','academique','evaluation',75,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(266,1,'publier','academique','evaluation',75,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:59:17'),(267,1,'create','academique','evaluation',76,NULL,'{\"libelle\": \"TEST_MIGRATION_1792853964\", \"fired_at\": 1792853964.098792, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 76, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(268,1,'publier','academique','evaluation',76,NULL,'{\"libelle\": \"TEST_MIGRATION_1792853964\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(269,1,'create','academique','note',505,NULL,'{\"valeur\": 14.5, \"eleve_id\": 128, \"evaluation_id\": 76}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(270,1,'publier','academique','evaluation',76,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(271,1,'verrouiller','academique','evaluation',76,NULL,'{\"libelle\": \"TEST_MIGRATION_1792853964\"}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(272,1,'verrouiller','academique','evaluation',76,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(273,1,'create','academique','evaluation',77,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792853964\", \"fired_at\": 1792853964.146192, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 77, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(274,1,'publier','academique','evaluation',77,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792853964\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(275,1,'create','academique','note',506,NULL,'{\"valeur\": 17, \"eleve_id\": 128, \"evaluation_id\": 77}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(276,1,'importer','academique','evaluation',77,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(277,1,'publier','academique','evaluation',77,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 14:59:24'),(278,1,'create','academique','evaluation',78,NULL,'{\"libelle\": \"TEST_MIGRATION_1792859281\", \"fired_at\": 1792859281.34352, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 78, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(279,1,'publier','academique','evaluation',78,NULL,'{\"libelle\": \"TEST_MIGRATION_1792859281\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(280,1,'create','academique','note',510,NULL,'{\"valeur\": 14.5, \"eleve_id\": 132, \"evaluation_id\": 78}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(281,1,'publier','academique','evaluation',78,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(282,1,'verrouiller','academique','evaluation',78,NULL,'{\"libelle\": \"TEST_MIGRATION_1792859281\"}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(283,1,'verrouiller','academique','evaluation',78,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(284,1,'create','academique','evaluation',79,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792859281\", \"fired_at\": 1792859281.417962, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 79, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(285,1,'publier','academique','evaluation',79,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792859281\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(286,1,'create','academique','note',511,NULL,'{\"valeur\": 17, \"eleve_id\": 132, \"evaluation_id\": 79}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(287,1,'importer','academique','evaluation',79,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(288,1,'publier','academique','evaluation',79,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:28:01'),(289,1,'create','academique','evaluation',80,NULL,'{\"libelle\": \"TEST_MIGRATION_1792859805\", \"fired_at\": 1792859805.615762, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 80, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(290,1,'publier','academique','evaluation',80,NULL,'{\"libelle\": \"TEST_MIGRATION_1792859805\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(291,1,'create','academique','note',513,NULL,'{\"valeur\": 14.5, \"eleve_id\": 140, \"evaluation_id\": 80}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(292,1,'publier','academique','evaluation',80,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(293,1,'verrouiller','academique','evaluation',80,NULL,'{\"libelle\": \"TEST_MIGRATION_1792859805\"}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(294,1,'verrouiller','academique','evaluation',80,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(295,1,'create','academique','evaluation',81,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792859805\", \"fired_at\": 1792859805.684398, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 81, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(296,1,'publier','academique','evaluation',81,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792859805\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(297,1,'create','academique','note',514,NULL,'{\"valeur\": 17, \"eleve_id\": 140, \"evaluation_id\": 81}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(298,1,'importer','academique','evaluation',81,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(299,1,'publier','academique','evaluation',81,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:36:45'),(300,1,'create','academique','evaluation',82,NULL,'{\"libelle\": \"TEST_MIGRATION_1792860671\", \"fired_at\": 1792860671.856473, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 82, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(301,1,'publier','academique','evaluation',82,NULL,'{\"libelle\": \"TEST_MIGRATION_1792860671\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(302,1,'create','academique','note',516,NULL,'{\"valeur\": 14.5, \"eleve_id\": 146, \"evaluation_id\": 82}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(303,1,'publier','academique','evaluation',82,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(304,1,'verrouiller','academique','evaluation',82,NULL,'{\"libelle\": \"TEST_MIGRATION_1792860671\"}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(305,1,'verrouiller','academique','evaluation',82,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(306,1,'create','academique','evaluation',83,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792860671\", \"fired_at\": 1792860671.909568, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 83, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(307,1,'publier','academique','evaluation',83,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792860671\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(308,1,'create','academique','note',517,NULL,'{\"valeur\": 17, \"eleve_id\": 146, \"evaluation_id\": 83}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(309,1,'importer','academique','evaluation',83,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(310,1,'publier','academique','evaluation',83,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 16:51:11'),(311,1,'create','academique','evaluation',84,NULL,'{\"libelle\": \"TEST_MIGRATION_1792862329\", \"fired_at\": 1792862329.445165, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 84, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(312,1,'publier','academique','evaluation',84,NULL,'{\"libelle\": \"TEST_MIGRATION_1792862329\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(313,1,'create','academique','note',519,NULL,'{\"valeur\": 14.5, \"eleve_id\": 162, \"evaluation_id\": 84}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(314,1,'publier','academique','evaluation',84,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(315,1,'verrouiller','academique','evaluation',84,NULL,'{\"libelle\": \"TEST_MIGRATION_1792862329\"}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(316,1,'verrouiller','academique','evaluation',84,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(317,1,'create','academique','evaluation',85,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792862329\", \"fired_at\": 1792862329.498028, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 85, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(318,1,'publier','academique','evaluation',85,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792862329\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(319,1,'create','academique','note',520,NULL,'{\"valeur\": 17, \"eleve_id\": 162, \"evaluation_id\": 85}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(320,1,'importer','academique','evaluation',85,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(321,1,'publier','academique','evaluation',85,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 17:18:49'),(322,1,'create','academique','evaluation',86,NULL,'{\"libelle\": \"TEST_MIGRATION_1792885913\", \"fired_at\": 1792885913.989997, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 86, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 23:51:53'),(323,1,'publier','academique','evaluation',86,NULL,'{\"libelle\": \"TEST_MIGRATION_1792885913\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(324,1,'create','academique','note',522,NULL,'{\"valeur\": 14.5, \"eleve_id\": 190, \"evaluation_id\": 86}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(325,1,'publier','academique','evaluation',86,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(326,1,'verrouiller','academique','evaluation',86,NULL,'{\"libelle\": \"TEST_MIGRATION_1792885913\"}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(327,1,'verrouiller','academique','evaluation',86,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(328,1,'create','academique','evaluation',87,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792885914\", \"fired_at\": 1792885914.302495, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 87, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(329,1,'publier','academique','evaluation',87,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792885914\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(330,1,'create','academique','note',523,NULL,'{\"valeur\": 17, \"eleve_id\": 190, \"evaluation_id\": 87}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(331,1,'importer','academique','evaluation',87,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(332,1,'publier','academique','evaluation',87,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-24 23:51:54'),(333,1,'create','academique','evaluation',88,NULL,'{\"libelle\": \"TEST_MIGRATION_1792888065\", \"fired_at\": 1792888065.370673, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 88, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(334,1,'publier','academique','evaluation',88,NULL,'{\"libelle\": \"TEST_MIGRATION_1792888065\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(335,1,'create','academique','note',525,NULL,'{\"valeur\": 14.5, \"eleve_id\": 200, \"evaluation_id\": 88}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(336,1,'publier','academique','evaluation',88,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(337,1,'verrouiller','academique','evaluation',88,NULL,'{\"libelle\": \"TEST_MIGRATION_1792888065\"}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(338,1,'verrouiller','academique','evaluation',88,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(339,1,'create','academique','evaluation',89,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792888065\", \"fired_at\": 1792888065.423379, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 89, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(340,1,'publier','academique','evaluation',89,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792888065\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(341,1,'create','academique','note',526,NULL,'{\"valeur\": 17, \"eleve_id\": 200, \"evaluation_id\": 89}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(342,1,'importer','academique','evaluation',89,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(343,1,'publier','academique','evaluation',89,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:27:45'),(361,1,'imprimer','academique','bulletin',109,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 100}','127.0.0.1','rbac-test-cli','2026-10-25 00:34:13'),(444,7,'imprimer','academique','bulletin',121,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 90}','127.0.0.1','rbac-test-cli','2026-10-25 00:39:44'),(445,6,'imprimer','academique','bulletin',6,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 90}','127.0.0.1','rbac-test-cli','2026-10-25 00:39:46'),(462,1,'create','academique','evaluation',90,NULL,'{\"libelle\": \"TEST_MIGRATION_1792888832\", \"fired_at\": 1792888832.094634, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 90, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(463,1,'publier','academique','evaluation',90,NULL,'{\"libelle\": \"TEST_MIGRATION_1792888832\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(464,1,'create','academique','note',528,NULL,'{\"valeur\": 14.5, \"eleve_id\": 415, \"evaluation_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(465,1,'publier','academique','evaluation',90,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(466,1,'verrouiller','academique','evaluation',90,NULL,'{\"libelle\": \"TEST_MIGRATION_1792888832\"}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(467,1,'verrouiller','academique','evaluation',90,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(468,1,'create','academique','evaluation',91,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792888832\", \"fired_at\": 1792888832.133165, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 91, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(469,1,'publier','academique','evaluation',91,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792888832\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(470,1,'create','academique','note',529,NULL,'{\"valeur\": 17, \"eleve_id\": 415, \"evaluation_id\": 91}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(471,1,'importer','academique','evaluation',91,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(472,1,'publier','academique','evaluation',91,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:40:32'),(545,1,'create','academique','evaluation',92,NULL,'{\"libelle\": \"TEST_MIGRATION_1792889768\", \"fired_at\": 1792889768.83724, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 92, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(546,1,'publier','academique','evaluation',92,NULL,'{\"libelle\": \"TEST_MIGRATION_1792889768\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(547,1,'create','academique','note',531,NULL,'{\"valeur\": 14.5, \"eleve_id\": 568, \"evaluation_id\": 92}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(548,1,'publier','academique','evaluation',92,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(549,1,'verrouiller','academique','evaluation',92,NULL,'{\"libelle\": \"TEST_MIGRATION_1792889768\"}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(550,1,'verrouiller','academique','evaluation',92,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(551,1,'create','academique','evaluation',93,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792889768\", \"fired_at\": 1792889768.890143, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 93, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(552,1,'publier','academique','evaluation',93,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792889768\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(553,1,'create','academique','note',532,NULL,'{\"valeur\": 17, \"eleve_id\": 568, \"evaluation_id\": 93}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(554,1,'importer','academique','evaluation',93,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(555,1,'publier','academique','evaluation',93,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:56:08'),(556,1,'create','academique','evaluation',94,NULL,'{\"libelle\": \"TEST_MIGRATION_1792889784\", \"fired_at\": 1792889784.572732, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 94, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(557,1,'publier','academique','evaluation',94,NULL,'{\"libelle\": \"TEST_MIGRATION_1792889784\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(558,1,'create','academique','note',533,NULL,'{\"valeur\": 14.5, \"eleve_id\": 571, \"evaluation_id\": 94}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(559,1,'publier','academique','evaluation',94,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(560,1,'verrouiller','academique','evaluation',94,NULL,'{\"libelle\": \"TEST_MIGRATION_1792889784\"}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(561,1,'verrouiller','academique','evaluation',94,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(562,1,'create','academique','evaluation',95,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792889784\", \"fired_at\": 1792889784.59996, \"classe_id\": 131, \"matiere_id\": 1, \"created_by_id\": 1, \"evaluation_id\": 95, \"periode_scolaire_id\": 90}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(563,1,'publier','academique','evaluation',95,NULL,'{\"libelle\": \"TEST_MIGRATION_CSV_1792889784\", \"classe_id\": 131}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(564,1,'create','academique','note',534,NULL,'{\"valeur\": 17, \"eleve_id\": 571, \"evaluation_id\": 95}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(565,1,'importer','academique','evaluation',95,NULL,'{\"errors\": 0, \"created\": 1, \"updated\": 0}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(566,1,'publier','academique','evaluation',95,NULL,'{\"count\": 1}','127.0.0.1',NULL,'2026-10-25 00:56:24'),(799,1,'imprimer','academique','bulletin',117,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 100}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 15:56:36'),(800,1,'imprimer','academique','bulletin',117,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 100}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 15:56:50'),(801,1,'modifier_appreciation_directeur','academique','bulletin',117,NULL,'{\"periode_id\": 100, \"jeton_empreinte\": \"56a9601f3ac3\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 15:57:21'),(802,1,'imprimer','academique','bulletin',117,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 100}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 15:57:26'),(803,1,'imprimer','academique','bulletin',117,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 100}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 16:33:21'),(804,1,'imprimer','academique','bulletin',117,NULL,'{\"statut\": \"brouillon\", \"officiel\": false, \"periode_id\": 100}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:22:04'),(805,1,'create','finance','facture',20,NULL,'{\"id\": 20, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0018\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 1, \"eleve_nom\": \"Prenom19 Nom08\", \"emise_par\": 1, \"classe_nom\": \"2nde C1\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0001\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(806,1,'invoice.created','finance','facture',20,NULL,'{\"numero\": \"FCT-2026-0018\", \"eleve_id\": 1, \"facture_id\": 20, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(807,1,'create','finance','facture',21,NULL,'{\"id\": 21, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0019\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 2, \"eleve_nom\": \"Prenom05 Nom25\", \"emise_par\": 1, \"classe_nom\": \"2nde C1\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0002\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(808,1,'invoice.created','finance','facture',21,NULL,'{\"numero\": \"FCT-2026-0019\", \"eleve_id\": 2, \"facture_id\": 21, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(809,1,'create','finance','facture',22,NULL,'{\"id\": 22, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0020\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 3, \"eleve_nom\": \"Prenom15 Nom19\", \"emise_par\": 1, \"classe_nom\": \"2nde C1\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0003\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(810,1,'invoice.created','finance','facture',22,NULL,'{\"numero\": \"FCT-2026-0020\", \"eleve_id\": 3, \"facture_id\": 22, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(811,1,'create','finance','facture',23,NULL,'{\"id\": 23, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0021\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 4, \"eleve_nom\": \"Prenom35 Nom13\", \"emise_par\": 1, \"classe_nom\": \"2nde C1\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0004\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(812,1,'invoice.created','finance','facture',23,NULL,'{\"numero\": \"FCT-2026-0021\", \"eleve_id\": 4, \"facture_id\": 23, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(813,1,'create','finance','facture',24,NULL,'{\"id\": 24, \"note\": \"\", \"niveau\": \"Première\", \"numero\": \"FCT-2026-0022\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 5, \"eleve_nom\": \"Prenom31 Nom14\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0005\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(814,1,'invoice.created','finance','facture',24,NULL,'{\"numero\": \"FCT-2026-0022\", \"eleve_id\": 5, \"facture_id\": 24, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(815,1,'create','finance','facture',25,NULL,'{\"id\": 25, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0023\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 6, \"eleve_nom\": \"Prenom28 Nom03\", \"emise_par\": 1, \"classe_nom\": \"2nde C1\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0006\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(816,1,'invoice.created','finance','facture',25,NULL,'{\"numero\": \"FCT-2026-0023\", \"eleve_id\": 6, \"facture_id\": 25, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(817,1,'create','finance','facture',26,NULL,'{\"id\": 26, \"note\": \"\", \"niveau\": \"Terminale\", \"numero\": \"FCT-2026-0024\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 7, \"eleve_nom\": \"Prenom41 Nom07\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0007\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(818,1,'invoice.created','finance','facture',26,NULL,'{\"numero\": \"FCT-2026-0024\", \"eleve_id\": 7, \"facture_id\": 26, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(819,1,'create','finance','facture',27,NULL,'{\"id\": 27, \"note\": \"\", \"niveau\": \"Première\", \"numero\": \"FCT-2026-0025\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 8, \"eleve_nom\": \"Prenom23 Nom32\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0008\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(820,1,'invoice.created','finance','facture',27,NULL,'{\"numero\": \"FCT-2026-0025\", \"eleve_id\": 8, \"facture_id\": 27, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(821,1,'create','finance','facture',28,NULL,'{\"id\": 28, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0026\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 9, \"eleve_nom\": \"Prenom37 Nom05\", \"emise_par\": 1, \"classe_nom\": \"A\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0009\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(822,1,'invoice.created','finance','facture',28,NULL,'{\"numero\": \"FCT-2026-0026\", \"eleve_id\": 9, \"facture_id\": 28, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(823,1,'create','finance','facture',29,NULL,'{\"id\": 29, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0027\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 10, \"eleve_nom\": \"Prenom39 Nom23\", \"emise_par\": 1, \"classe_nom\": \"B\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0010\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(824,1,'invoice.created','finance','facture',29,NULL,'{\"numero\": \"FCT-2026-0027\", \"eleve_id\": 10, \"facture_id\": 29, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(825,1,'create','finance','facture',30,NULL,'{\"id\": 30, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0028\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 109, \"eleve_nom\": \"Prenom04 Nom17\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-01\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(826,1,'invoice.created','finance','facture',30,NULL,'{\"numero\": \"FCT-2026-0028\", \"eleve_id\": 109, \"facture_id\": 30, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(827,1,'create','finance','facture',31,NULL,'{\"id\": 31, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0029\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 110, \"eleve_nom\": \"Prenom14 Nom26\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-02\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(828,1,'invoice.created','finance','facture',31,NULL,'{\"numero\": \"FCT-2026-0029\", \"eleve_id\": 110, \"facture_id\": 31, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(829,1,'create','finance','facture',32,NULL,'{\"id\": 32, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0030\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 111, \"eleve_nom\": \"Prenom32 Nom18\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-03\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(830,1,'invoice.created','finance','facture',32,NULL,'{\"numero\": \"FCT-2026-0030\", \"eleve_id\": 111, \"facture_id\": 32, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(831,1,'create','finance','facture',33,NULL,'{\"id\": 33, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0031\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 112, \"eleve_nom\": \"Prenom38 Nom21\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-04\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(832,1,'invoice.created','finance','facture',33,NULL,'{\"numero\": \"FCT-2026-0031\", \"eleve_id\": 112, \"facture_id\": 33, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(833,1,'create','finance','facture',34,NULL,'{\"id\": 34, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0032\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 113, \"eleve_nom\": \"Prenom06 Nom24\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-05\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(834,1,'invoice.created','finance','facture',34,NULL,'{\"numero\": \"FCT-2026-0032\", \"eleve_id\": 113, \"facture_id\": 34, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(835,1,'create','finance','facture',35,NULL,'{\"id\": 35, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0033\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 114, \"eleve_nom\": \"Prenom13 Nom28\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-06\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(836,1,'invoice.created','finance','facture',35,NULL,'{\"numero\": \"FCT-2026-0033\", \"eleve_id\": 114, \"facture_id\": 35, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(837,1,'create','finance','facture',36,NULL,'{\"id\": 36, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0034\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 115, \"eleve_nom\": \"Prenom08 Nom02\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-07\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(838,1,'invoice.created','finance','facture',36,NULL,'{\"numero\": \"FCT-2026-0034\", \"eleve_id\": 115, \"facture_id\": 36, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(839,1,'create','finance','facture',37,NULL,'{\"id\": 37, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0035\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 116, \"eleve_nom\": \"Prenom21 Nom10\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-08\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(840,1,'invoice.created','finance','facture',37,NULL,'{\"numero\": \"FCT-2026-0035\", \"eleve_id\": 116, \"facture_id\": 37, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(841,1,'create','finance','facture',38,NULL,'{\"id\": 38, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0036\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 117, \"eleve_nom\": \"Prenom42 Nom01\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-09\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(842,1,'invoice.created','finance','facture',38,NULL,'{\"numero\": \"FCT-2026-0036\", \"eleve_id\": 117, \"facture_id\": 38, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(843,1,'create','finance','facture',39,NULL,'{\"id\": 39, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0037\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 118, \"eleve_nom\": \"Prenom18 Nom30\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-10\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(844,1,'invoice.created','finance','facture',39,NULL,'{\"numero\": \"FCT-2026-0037\", \"eleve_id\": 118, \"facture_id\": 39, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(845,1,'create','finance','facture',40,NULL,'{\"id\": 40, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0038\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 119, \"eleve_nom\": \"Prenom24 Nom20\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-11\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(846,1,'invoice.created','finance','facture',40,NULL,'{\"numero\": \"FCT-2026-0038\", \"eleve_id\": 119, \"facture_id\": 40, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(847,1,'create','finance','facture',41,NULL,'{\"id\": 41, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0039\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 120, \"eleve_nom\": \"Prenom02 Nom34\", \"emise_par\": 1, \"classe_nom\": \"TB-01\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"TESTBUL26-12\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(848,1,'invoice.created','finance','facture',41,NULL,'{\"numero\": \"FCT-2026-0039\", \"eleve_id\": 120, \"facture_id\": 41, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(849,1,'create','finance','facture',42,NULL,'{\"id\": 42, \"note\": \"\", \"niveau\": \"Seconde\", \"numero\": \"FCT-2026-0040\", \"statut\": \"brouillon\", \"origine\": \"operationnelle\", \"avoir_id\": null, \"eleve_id\": 121, \"eleve_nom\": \"Prenom40 Nom04\", \"emise_par\": 1, \"classe_nom\": \"2nde C1\", \"created_at\": \"2026-10-28 22:23:08\", \"montant_ht\": \"5000.00\", \"updated_at\": \"2026-10-28 22:23:08\", \"annulee_par\": null, \"annuleur_nom\": null, \"emetteur_nom\": \"Administrateur\", \"montant_paye\": \"0.00\", \"date_echeance\": null, \"date_emission\": \"2026-10-28\", \"montant_total\": \"5000.00\", \"annee_scolaire\": \"2026-2027\", \"migration_meta\": null, \"montant_remise\": \"0.00\", \"annuleur_prenom\": null, \"date_annulation\": null, \"emetteur_prenom\": null, \"migration_source\": null, \"montant_penalite\": \"0.00\", \"motif_annulation\": null, \"numero_matricule\": \"2024-0011\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(850,1,'invoice.created','finance','facture',42,NULL,'{\"numero\": \"FCT-2026-0040\", \"eleve_id\": 121, \"facture_id\": 42, \"created_by_id\": 1, \"montant_total\": 5000, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08'),(851,1,'invoice.bulk_generated','finance',NULL,NULL,NULL,'{\"contexte\": \"Niveau:\", \"nb_factures\": 23, \"generated_by\": 1, \"annee_scolaire\": \"2026-2027\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 21:23:08');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `audit_orphelins_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_orphelins_archive` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ligne_id` bigint unsigned NOT NULL,
  `donnees` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `archive_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orph_mig` (`migration`,`table_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audit_orphelins_archive` WRITE;
/*!40000 ALTER TABLE `audit_orphelins_archive` DISABLE KEYS */;
INSERT INTO `audit_orphelins_archive` VALUES (1,'T062','notification_logs','DELETE',23,'{\"id\":23,\"user_id\":122,\"trigger_type\":\"annonce\",\"canal\":\"interne\",\"titre\":\"Nouvelle annonce : Annonce test admin\",\"message\":\"Contenu de verification admin\",\"destinataire\":null,\"statut\":\"envoye\",\"erreur\":null,\"created_at\":\"2026-07-10 05:16:40\"}','2026-10-26 19:50:18'),(2,'T062','notification_logs','DELETE',31,'{\"id\":31,\"user_id\":159,\"trigger_type\":\"absence\",\"canal\":\"interne\",\"titre\":\"Absence signalé — 09/07/2026\",\"message\":\"Un absence a été enregistré pour Test EleveNotif2 le 09/07/2026.\",\"destinataire\":null,\"statut\":\"envoye\",\"erreur\":null,\"created_at\":\"2026-07-10 23:38:17\"}','2026-10-26 19:50:18'),(3,'T062','notification_logs','DELETE',32,'{\"id\":32,\"user_id\":159,\"trigger_type\":\"absence\",\"canal\":\"interne\",\"titre\":\"Justification acceptée — 09/07/2026\",\"message\":\"Votre justification pour l\'absence du 09/07/2026 a été acceptée.\",\"destinataire\":null,\"statut\":\"envoye\",\"erreur\":null,\"created_at\":\"2026-07-10 23:39:48\"}','2026-10-26 19:50:18'),(4,'T062','notification_logs','DELETE',33,'{\"id\":33,\"user_id\":168,\"trigger_type\":\"retard\",\"canal\":\"interne\",\"titre\":\"Retard signalé — 11/07/2026\",\"message\":\"Un retard de 15 min a été enregistré le 11/07/2026 (arrivée 08:15).\",\"destinataire\":null,\"statut\":\"envoye\",\"erreur\":null,\"created_at\":\"2026-07-11 15:18:39\"}','2026-10-26 19:50:18'),(5,'T062','notification_logs','DELETE',34,'{\"id\":34,\"user_id\":168,\"trigger_type\":\"discipline\",\"canal\":\"interne\",\"titre\":\"Incident disciplinaire signalé\",\"message\":\"Un incident disciplinaire (moyen) a été signalé concernant votre enfant.\",\"destinataire\":null,\"statut\":\"envoye\",\"erreur\":null,\"created_at\":\"2026-07-11 15:20:07\"}','2026-10-26 19:50:18'),(6,'T062','notification_logs','DELETE',35,'{\"id\":35,\"user_id\":168,\"trigger_type\":\"recompense\",\"canal\":\"interne\",\"titre\":\"Récompense attribuée — félicitations !\",\"message\":\"Une récompense (niveau classe) a été attribuée : Test verification notification recompense\",\"destinataire\":null,\"statut\":\"envoye\",\"erreur\":null,\"created_at\":\"2026-07-11 15:23:23\"}','2026-10-26 19:50:18'),(7,'T062','notification_logs','DELETE',36,'{\"id\":36,\"user_id\":192,\"trigger_type\":\"paiement\",\"canal\":\"interne\",\"titre\":\"Paiement reçu — 7 500 XOF\",\"message\":\"Un paiement de 7 500 XOF a été enregistré pour Prenom05 Nom25 (facture FCT-2026-0018).\",\"destinataire\":null,\"statut\":\"envoye\",\"erreur\":null,\"created_at\":\"2026-07-12 00:55:55\"}','2026-10-26 19:50:18'),(8,'T062','vs_incidents_discipline','DELETE',1,'{\"id\":1,\"dossier_id\":2,\"categorie_id\":2,\"gravite\":\"moyen\",\"description\":\"Test verification notification incident disciplinaire\",\"date_incident\":\"2026-07-11\",\"heure_incident\":null,\"lieu\":null,\"matiere_id\":null,\"signale_par\":169,\"statut\":\"ouvert\",\"piece_jointe\":null,\"deleted_at\":null,\"created_at\":\"2026-07-11 15:20:07\",\"updated_at\":\"2026-07-11 15:20:07\"}','2026-10-26 19:50:18'),(9,'T062','finance_journaux_caisse','NULLIFY',1,'{\"id\":1,\"date_journee\":\"2026-07-11\",\"session_id\":1,\"solde_ouverture\":\"0.00\",\"total_entrees\":\"0.00\",\"total_sorties\":\"0.00\",\"solde_cloture\":\"0.00\",\"statut\":\"ouvert\",\"note\":null,\"created_at\":\"2026-07-11 20:48:15\",\"updated_at\":\"2026-07-11 20:48:15\"}','2026-10-26 19:50:18');
/*!40000 ALTER TABLE `audit_orphelins_archive` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bulletin_tokens_retired`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bulletin_tokens_retired` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `token_sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `bulletin_id` int unsigned DEFAULT NULL,
  `motif` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `retire_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_btr_sha` (`token_sha256`),
  KEY `idx_btr_bulletin` (`bulletin_id`),
  CONSTRAINT `fk_btr_bulletin` FOREIGN KEY (`bulletin_id`) REFERENCES `bulletins_v2` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bulletin_tokens_retired` WRITE;
/*!40000 ALTER TABLE `bulletin_tokens_retired` DISABLE KEYS */;
/*!40000 ALTER TABLE `bulletin_tokens_retired` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bulletins_v2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bulletins_v2` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `verification_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_version` tinyint unsigned NOT NULL DEFAULT '1',
  `moyenne` decimal(5,2) DEFAULT NULL,
  `rang` smallint unsigned DEFAULT NULL,
  `nb_eleves` smallint unsigned DEFAULT NULL,
  `mention_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision` enum('admis','rattrapage','refuse','indeterminate') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'indeterminate',
  `appreciation_pp` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `appreciation_directeur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statut` enum('brouillon','publie','archive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Snapshot BulletinData s├®rialis├®',
  `generated_by` int unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `published_at` datetime DEFAULT NULL,
  `published_by` int unsigned DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`verification_token`),
  UNIQUE KEY `uq_bulletin_eleve_per` (`eleve_id`,`periode_id`),
  KEY `idx_bul_classe_periode` (`classe_id`,`periode_id`),
  KEY `idx_bul_statut` (`statut`),
  KEY `fk_bul_periode` (`periode_id`),
  CONSTRAINT `fk_bul_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bul_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bul_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=841 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bulletins_v2` WRITE;
/*!40000 ALTER TABLE `bulletins_v2` DISABLE KEYS */;
INSERT INTO `bulletins_v2` VALUES (1,1,131,90,'4903826354fc24deb163cbc791c62ac04bb7bdd34aaa5abac467164eeda24394',1,9.08,4,6,'INS','rattrapage','Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.',NULL,'brouillon','{\"eleve_id\":1,\"eleve_nom\":\"Nom08\",\"eleve_prenom\":\"Prenom19\",\"eleve_matricule\":\"2024-0001\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":90,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":12.13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":12.13,\"compo\":12.13,\"moyenne_classe\":9.86,\"rang\":3,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez-Bien\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":17,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":17,\"compo\":17,\"moyenne_classe\":14.17,\"rang\":3,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Passable\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":16,\"compo\":16,\"moyenne_classe\":13,\"rang\":3,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très Bien\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":12.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":12.5,\"compo\":12.5,\"moyenne_classe\":10.5,\"rang\":3,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez-Bien\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":8,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":8,\"compo\":8,\"moyenne_classe\":8.33,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Insuffisant\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":3.75,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":3.75,\"compo\":3.75,\"moyenne_classe\":6.54,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Médiocre\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":6,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":6,\"compo\":6,\"moyenne_classe\":6.92,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Très insuffisant\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":12.38,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":12.38,\"compo\":12.38,\"moyenne_classe\":10.4,\"rang\":3,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez-Bien\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":9.08,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":4,\"nb_eleves\":6,\"moyenne_classe\":8.89,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":14.64,\"moyenne\":8.89,\"ecart_type\":4.56,\"taux_reussite\":33.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 9.08\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"9aa3ab01069ac6542859c4c7f2da4d31a1d93d45c77cb869d9be40a7dbc2d479\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/9aa3ab01069ac6542859c4c7f2da4d31a1d93d45c77cb869d9be40a7dbc2d479\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:27\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":3,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":11.11,\"moyenne_scientifique\":6.66,\"moyenne_autre\":16.5,\"absences\":{\"justifiees\":1,\"nonJustifiees\":1},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:27',NULL,NULL,NULL,NULL,'2026-07-20 01:51:51','2026-10-29 20:04:53'),(12,6,131,91,'a20824e240bae011dc136249b7327e31c749cf01774b61244e9bca233d681de1',1,8.40,4,6,'INS','rattrapage','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.',NULL,'brouillon','{\"eleve_id\":6,\"eleve_nom\":\"Nom03\",\"eleve_prenom\":\"Prenom28\",\"eleve_matricule\":\"2024-0006\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":91,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":9,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9,\"compo\":9,\"moyenne_classe\":8.63,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":15,\"compo\":15,\"moyenne_classe\":13.42,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13,\"compo\":13,\"moyenne_classe\":12.25,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":9.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9.5,\"compo\":9.5,\"moyenne_classe\":9.08,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":8.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":8.5,\"compo\":8.5,\"moyenne_classe\":9.25,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":6,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":6,\"compo\":6,\"moyenne_classe\":6.58,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":7,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":7,\"compo\":7,\"moyenne_classe\":7.67,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":9,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9,\"compo\":9,\"moyenne_classe\":9.08,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":8.4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":4,\"nb_eleves\":6,\"moyenne_classe\":8.55,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":13.98,\"moyenne\":8.55,\"ecart_type\":4.42,\"taux_reussite\":33.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 8.4\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"1d8735a119c205dbee10c97f6e43afccdb46632fe4201f60faa7b2d81ed7a087\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/1d8735a119c205dbee10c97f6e43afccdb46632fe4201f60faa7b2d81ed7a087\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:28\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":4,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":9.07,\"moyenne_scientifique\":7.08,\"moyenne_autre\":14,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:28',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(14,4,131,91,'0c599001fbe9dc887e6a8e7bf45ebce6dd6eb2e4986fb551ebaf37732351d92c',1,13.98,1,6,'AB','admis','Travail correct. Avec plus de régularité, de meilleurs résultats sont à portée.',NULL,'brouillon','{\"eleve_id\":4,\"eleve_nom\":\"Nom13\",\"eleve_prenom\":\"Prenom35\",\"eleve_matricule\":\"2024-0004\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":91,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":14,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":14,\"compo\":14,\"moyenne_classe\":8.63,\"rang\":1,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":18.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":18.5,\"compo\":18.5,\"moyenne_classe\":13.42,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":17.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":17.5,\"compo\":17.5,\"moyenne_classe\":12.25,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":14.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":14.5,\"compo\":14.5,\"moyenne_classe\":9.08,\"rang\":1,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":13.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13.5,\"compo\":13.5,\"moyenne_classe\":9.25,\"rang\":1,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":12,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":12,\"compo\":12,\"moyenne_classe\":6.58,\"rang\":1,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":13.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13.5,\"compo\":13.5,\"moyenne_classe\":7.67,\"rang\":1,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":15,\"compo\":15,\"moyenne_classe\":9.08,\"rang\":1,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":13.98,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":1,\"nb_eleves\":6,\"moyenne_classe\":8.55,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":13.98,\"moyenne\":8.55,\"ecart_type\":4.42,\"taux_reussite\":33.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 13.98\\/20 ≥ 10.\",\"appreciation_pp\":\"Travail correct. Avec plus de régularité, de meilleurs résultats sont à portée.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"8c75bcbbae4d0821e91e7582715f4925be59a5a71b4c627594350ebf47e50a52\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/8c75bcbbae4d0821e91e7582715f4925be59a5a71b4c627594350ebf47e50a52\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:29\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":1,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":14.07,\"moyenne_scientifique\":13.25,\"moyenne_autre\":18,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:29',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(15,3,131,91,'f3bff2d68989fa5eec70053956a1687cf8e48dddf1c60813bc3af5a8cc89dd8b',1,7.36,5,6,'INS','refuse','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.',NULL,'brouillon','{\"eleve_id\":3,\"eleve_nom\":\"Nom19\",\"eleve_prenom\":\"Prenom15\",\"eleve_matricule\":\"2024-0003\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":91,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":7.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":7.5,\"compo\":7.5,\"moyenne_classe\":8.63,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":14,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":14,\"compo\":14,\"moyenne_classe\":13.42,\"rang\":5,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":12,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":12,\"compo\":12,\"moyenne_classe\":12.25,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":8,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":8,\"compo\":8,\"moyenne_classe\":9.08,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":9,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9,\"compo\":9,\"moyenne_classe\":9.25,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":5,\"compo\":5,\"moyenne_classe\":6.58,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":6,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":6,\"compo\":6,\"moyenne_classe\":7.67,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":7.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":7.5,\"compo\":7.5,\"moyenne_classe\":9.08,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":7.36,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":5,\"nb_eleves\":6,\"moyenne_classe\":8.55,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":13.98,\"moyenne\":8.55,\"ecart_type\":4.42,\"taux_reussite\":33.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 7.36\\/20 < 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"4f5c1acf3ad4089e225c81bc9d8befab0fb0691af3292f52318b56e4df63775b\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/4f5c1acf3ad4089e225c81bc9d8befab0fb0691af3292f52318b56e4df63775b\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:29\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":5,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":8.14,\"moyenne_scientifique\":5.96,\"moyenne_autre\":13,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:29',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(16,2,131,91,'3f78e9703cb8d8a0f995b79c3d171bbef4a2de97b8e00f9f1a421d8e0290f0dd',1,12.02,2,6,'AB','admis','Assez bien. La Démo et la régularité dans le travail s\'imposent.',NULL,'brouillon','{\"eleve_id\":2,\"eleve_nom\":\"Nom25\",\"eleve_prenom\":\"Prenom05\",\"eleve_matricule\":\"2024-0002\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":91,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":11.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":11.5,\"compo\":11.5,\"moyenne_classe\":8.63,\"rang\":2,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":17,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":17,\"compo\":17,\"moyenne_classe\":13.42,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":16,\"compo\":16,\"moyenne_classe\":12.25,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":12.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":12.5,\"compo\":12.5,\"moyenne_classe\":9.08,\"rang\":2,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13,\"compo\":13,\"moyenne_classe\":9.25,\"rang\":2,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":10,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":10,\"compo\":10,\"moyenne_classe\":6.58,\"rang\":2,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":11,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":11,\"compo\":11,\"moyenne_classe\":7.67,\"rang\":2,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13,\"compo\":13,\"moyenne_classe\":9.08,\"rang\":2,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":12.02,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":2,\"nb_eleves\":6,\"moyenne_classe\":8.55,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":13.98,\"moyenne\":8.55,\"ecart_type\":4.42,\"taux_reussite\":33.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 12.02\\/20 ≥ 10.\",\"appreciation_pp\":\"Assez bien. La Démo et la régularité dans le travail s\'imposent.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"2e317068f999f2c9df3f258c73f26c0c7c1053e13b36457fbe97affbec31de43\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/2e317068f999f2c9df3f258c73f26c0c7c1053e13b36457fbe97affbec31de43\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:30\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":2,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":12.36,\"moyenne_scientifique\":11.08,\"moyenne_autre\":16.5,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:30',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(17,6,131,90,'ec6362a75cb3468c1cfa8a440bd13b69e8d830381628b53a71cf01ebf8ca8026',1,9.67,3,6,'INS','rattrapage','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.',NULL,'brouillon','{\"eleve_id\":6,\"eleve_nom\":\"Nom03\",\"eleve_prenom\":\"Prenom28\",\"eleve_matricule\":\"2024-0006\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":90,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":10,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":10,\"compo\":10,\"moyenne_classe\":9.86,\"rang\":4,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":16,\"compo\":16,\"moyenne_classe\":14.17,\"rang\":4,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":14,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":14,\"compo\":14,\"moyenne_classe\":13,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":11,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":11,\"compo\":11,\"moyenne_classe\":10.5,\"rang\":4,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":9.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9.5,\"compo\":9.5,\"moyenne_classe\":8.33,\"rang\":3,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":7.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":7.5,\"compo\":7.5,\"moyenne_classe\":6.54,\"rang\":3,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":8,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":8,\"compo\":8,\"moyenne_classe\":6.92,\"rang\":3,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":10.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":10.5,\"compo\":10.5,\"moyenne_classe\":10.4,\"rang\":4,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":9.67,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":3,\"nb_eleves\":6,\"moyenne_classe\":8.89,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":14.64,\"moyenne\":8.89,\"ecart_type\":4.56,\"taux_reussite\":33.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 9.67\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"1645933e1494e9b1e7d98d919297f07b0288e685a5f03d5af59828061aab7a2f\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/1645933e1494e9b1e7d98d919297f07b0288e685a5f03d5af59828061aab7a2f\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:26\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":4,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":10.29,\"moyenne_scientifique\":8.42,\"moyenne_autre\":15,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:26',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(19,4,131,90,'c0a394cf6f174e84d3f45f486692f3e55d87335b130349038d1644d68119eddd',1,14.64,1,6,'B','admis','Satisfaisant. Poursuivez vos efforts pour atteindre l\'excellence.',NULL,'brouillon','{\"eleve_id\":4,\"eleve_nom\":\"Nom13\",\"eleve_prenom\":\"Prenom35\",\"eleve_matricule\":\"2024-0004\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":90,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":15.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":15.5,\"compo\":15.5,\"moyenne_classe\":9.86,\"rang\":1,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":19,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":19,\"compo\":19,\"moyenne_classe\":14.17,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":18,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":18,\"compo\":18,\"moyenne_classe\":13,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":16,\"compo\":16,\"moyenne_classe\":10.5,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":14.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":14.5,\"compo\":14.5,\"moyenne_classe\":8.33,\"rang\":1,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13,\"compo\":13,\"moyenne_classe\":6.54,\"rang\":1,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":12,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":12,\"compo\":12,\"moyenne_classe\":6.92,\"rang\":1,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":16.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":16.5,\"compo\":16.5,\"moyenne_classe\":10.4,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":14.64,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":1,\"nb_eleves\":6,\"moyenne_classe\":8.89,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":14.64,\"moyenne\":8.89,\"ecart_type\":4.56,\"taux_reussite\":33.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 14.64\\/20 ≥ 10.\",\"appreciation_pp\":\"Satisfaisant. Poursuivez vos efforts pour atteindre l\'excellence.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"37d34d91b1fbff9e252aa5f158fdfb5854825bf4cdd4018da05468b5f84e4421\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/37d34d91b1fbff9e252aa5f158fdfb5854825bf4cdd4018da05468b5f84e4421\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:27\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":1,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":15.43,\"moyenne_scientifique\":13.54,\"moyenne_autre\":18.5,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:27',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(20,3,131,90,'f1d1c8b756198166f6e00f793a05492f2995ee8f7f3b962a05e9a1525f7fe0ac',1,7.79,5,6,'INS','refuse','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.',NULL,'brouillon','{\"eleve_id\":3,\"eleve_nom\":\"Nom19\",\"eleve_prenom\":\"Prenom15\",\"eleve_matricule\":\"2024-0003\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":90,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":8.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":8.5,\"compo\":8.5,\"moyenne_classe\":9.86,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":15,\"compo\":15,\"moyenne_classe\":14.17,\"rang\":5,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13,\"compo\":13,\"moyenne_classe\":13,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":9.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9.5,\"compo\":9.5,\"moyenne_classe\":10.5,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":7,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":7,\"compo\":7,\"moyenne_classe\":8.33,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":6,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":6,\"compo\":6,\"moyenne_classe\":6.54,\"rang\":4,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":5.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":5.5,\"compo\":5.5,\"moyenne_classe\":6.92,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":8,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":8,\"compo\":8,\"moyenne_classe\":10.4,\"rang\":5,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":7.79,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":5,\"nb_eleves\":6,\"moyenne_classe\":8.89,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":14.64,\"moyenne\":8.89,\"ecart_type\":4.56,\"taux_reussite\":33.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 7.79\\/20 < 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"aaf34d01608bc5c42d403e4fad291f45df6cb7ce4b933ef5159c64b4a41264e7\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/aaf34d01608bc5c42d403e4fad291f45df6cb7ce4b933ef5159c64b4a41264e7\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:27\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":5,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":8.5,\"moyenne_scientifique\":6.33,\"moyenne_autre\":14,\"absences\":{\"justifiees\":0,\"nonJustifiees\":1},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:27',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(21,2,131,90,'f0e1f3f07f3fcf4773b2f33843f8354793f0fbb336cae4e9dedcae8103173cf1',1,12.14,2,6,'AB','admis','Assez bien. La Démo et la régularité dans le travail s\'imposent.',NULL,'brouillon','{\"eleve_id\":2,\"eleve_nom\":\"Nom25\",\"eleve_prenom\":\"Prenom05\",\"eleve_matricule\":\"2024-0002\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":90,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":13,\"compo\":13,\"moyenne_classe\":9.86,\"rang\":2,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":18,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":18,\"compo\":18,\"moyenne_classe\":14.17,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":17,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":17,\"compo\":17,\"moyenne_classe\":13,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":14,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":14,\"compo\":14,\"moyenne_classe\":10.5,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":11,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":11,\"compo\":11,\"moyenne_classe\":8.33,\"rang\":2,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":9,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9,\"compo\":9,\"moyenne_classe\":6.54,\"rang\":2,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":10,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":10,\"compo\":10,\"moyenne_classe\":6.92,\"rang\":2,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":15,\"compo\":15,\"moyenne_classe\":10.4,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":12.14,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":2,\"nb_eleves\":6,\"moyenne_classe\":8.89,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":14.64,\"moyenne\":8.89,\"ecart_type\":4.56,\"taux_reussite\":33.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 12.14\\/20 ≥ 10.\",\"appreciation_pp\":\"Assez bien. La Démo et la régularité dans le travail s\'imposent.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"de8031429d85fb4a16493a6e399be5af35f10e4c797bfacb3c0679bd679ac6bc\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/de8031429d85fb4a16493a6e399be5af35f10e4c797bfacb3c0679bd679ac6bc\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:28\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":2,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":12.86,\"moyenne_scientifique\":10.83,\"moyenne_autre\":17.5,\"absences\":{\"justifiees\":1,\"nonJustifiees\":1},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:28',NULL,NULL,NULL,NULL,'2026-07-22 03:13:58','2026-10-29 20:04:53'),(56,1,131,91,'173d83813cd876481b9cf9fbbb130e806e059320ac1aa22e37ba1177f3b1266f',1,9.52,3,6,'INS','rattrapage','Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.',NULL,'brouillon','{\"eleve_id\":1,\"eleve_nom\":\"Nom08\",\"eleve_prenom\":\"Prenom19\",\"eleve_matricule\":\"2024-0001\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":91,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":9.75,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":9.75,\"compo\":9.75,\"moyenne_classe\":8.63,\"rang\":3,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Passable\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":16,\"compo\":16,\"moyenne_classe\":13.42,\"rang\":3,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très Bien\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":15,\"compo\":15,\"moyenne_classe\":12.25,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bien\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":10,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":10,\"compo\":10,\"moyenne_classe\":9.08,\"rang\":3,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Passable\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":11.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":11.5,\"compo\":11.5,\"moyenne_classe\":9.25,\"rang\":3,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Assez-Bien\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":6.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":6.5,\"compo\":6.5,\"moyenne_classe\":6.58,\"rang\":3,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Insuffisant\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":8.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":8.5,\"compo\":8.5,\"moyenne_classe\":7.67,\"rang\":3,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Insuffisant\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":10,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":10,\"compo\":10,\"moyenne_classe\":9.08,\"rang\":3,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Passable\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":9.52,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":3,\"nb_eleves\":6,\"moyenne_classe\":8.55,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":13.98,\"moyenne\":8.55,\"ecart_type\":4.42,\"taux_reussite\":33.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 9.52\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"173d83813cd876481b9cf9fbbb130e806e059320ac1aa22e37ba1177f3b1266f\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/173d83813cd876481b9cf9fbbb130e806e059320ac1aa22e37ba1177f3b1266f\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:29\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":3,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":10.36,\"moyenne_scientifique\":8.04,\"moyenne_autre\":15.5,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:29',NULL,NULL,NULL,NULL,'2026-07-22 05:50:46','2026-10-29 20:04:53'),(100,109,132,100,'7e6e0d9c2bb2f12f9a55c6df3880fcb2e61fb8259b96a7f0d432999d498d359b',1,17.31,1,12,'TB','admis','Excellent trimestre. Un travail d\'une grande qualité qui force l\'admiration.',NULL,'brouillon','{\"eleve_id\":109,\"eleve_nom\":\"Nom17\",\"eleve_prenom\":\"Prenom04\",\"eleve_matricule\":\"TESTBUL26-01\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":17.8,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":18.1,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":18,\"compo\":18.1,\"moyenne_classe\":11.06,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":18.52,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":18.82,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":18.72,\"compo\":18.82,\"moyenne_classe\":11.59,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":18.92,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":19.22,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":19.12,\"compo\":19.22,\"moyenne_classe\":11.99,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":16.84,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.14,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.04,\"compo\":17.14,\"moyenne_classe\":10.74,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":17.16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.46,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.36,\"compo\":17.46,\"moyenne_classe\":10.87,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":16.34,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.64,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.54,\"compo\":16.64,\"moyenne_classe\":10.24,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":16.76,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.06,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.96,\"compo\":17.06,\"moyenne_classe\":10.47,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":17.56,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.86,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.76,\"compo\":17.86,\"moyenne_classe\":11.27,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":17.31,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":1,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 17.31\\/20 ≥ 10.\",\"appreciation_pp\":\"Excellent trimestre. Un travail d\'une grande qualité qui force l\'admiration.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"7e6e0d9c2bb2f12f9a55c6df3880fcb2e61fb8259b96a7f0d432999d498d359b\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/7e6e0d9c2bb2f12f9a55c6df3880fcb2e61fb8259b96a7f0d432999d498d359b\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:33\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":1,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":17.41,\"moyenne_scientifique\":16.99,\"moyenne_autre\":18.92,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:33',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(101,110,132,100,'f0d28eaee7318286aba0e0124c9dc9fbdf336708814db74f18e214e428b65341',1,15.96,2,12,'B','admis','Bon travail dans l\'ensemble. Quelques points à approfondir pour progresser encore.',NULL,'brouillon','{\"eleve_id\":110,\"eleve_nom\":\"Nom26\",\"eleve_prenom\":\"Prenom14\",\"eleve_matricule\":\"TESTBUL26-02\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":15.56,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.86,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.76,\"compo\":15.86,\"moyenne_classe\":11.06,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":16.28,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.58,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.48,\"compo\":16.58,\"moyenne_classe\":11.59,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":16.68,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.98,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.88,\"compo\":16.98,\"moyenne_classe\":11.99,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":15.7,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.9,\"compo\":16,\"moyenne_classe\":10.74,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":16.02,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.32,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.22,\"compo\":16.32,\"moyenne_classe\":10.87,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":15.2,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.5,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.4,\"compo\":15.5,\"moyenne_classe\":10.24,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":15.62,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.92,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.82,\"compo\":15.92,\"moyenne_classe\":10.47,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":16.42,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.72,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.62,\"compo\":16.72,\"moyenne_classe\":11.27,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":15.96,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":2,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 15.96\\/20 ≥ 10.\",\"appreciation_pp\":\"Bon travail dans l\'ensemble. Quelques points à approfondir pour progresser encore.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"f0d28eaee7318286aba0e0124c9dc9fbdf336708814db74f18e214e428b65341\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/f0d28eaee7318286aba0e0124c9dc9fbdf336708814db74f18e214e428b65341\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:36\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":2,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":15.95,\"moyenne_scientifique\":15.85,\"moyenne_autre\":16.68,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:36',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(102,111,132,100,'c31319726014f81cb3018531515a7e0391b3a2cf69fe58e642c169e0053b6f4f',1,14.52,3,12,'B','admis','Bon trimestre. Des résultats encourageants qui méritent d\'être consolidés.',NULL,'brouillon','{\"eleve_id\":111,\"eleve_nom\":\"Nom18\",\"eleve_prenom\":\"Prenom32\",\"eleve_matricule\":\"TESTBUL26-03\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":15.12,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.42,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.32,\"compo\":15.42,\"moyenne_classe\":11.06,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":14.74,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.04,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.94,\"compo\":15.04,\"moyenne_classe\":11.59,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":15.14,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.44,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.34,\"compo\":15.44,\"moyenne_classe\":11.99,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":14.16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.46,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.36,\"compo\":14.46,\"moyenne_classe\":10.74,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":14.48,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.78,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.68,\"compo\":14.78,\"moyenne_classe\":10.87,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":13.66,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.96,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.86,\"compo\":13.96,\"moyenne_classe\":10.24,\"rang\":3,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":14.08,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.38,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.28,\"compo\":14.38,\"moyenne_classe\":10.47,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":14.88,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.18,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.08,\"compo\":15.18,\"moyenne_classe\":11.27,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":14.52,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":3,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 14.52\\/20 ≥ 10.\",\"appreciation_pp\":\"Bon trimestre. Des résultats encourageants qui méritent d\'être consolidés.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"c31319726014f81cb3018531515a7e0391b3a2cf69fe58e642c169e0053b6f4f\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/c31319726014f81cb3018531515a7e0391b3a2cf69fe58e642c169e0053b6f4f\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:33\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":3,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":14.73,\"moyenne_scientifique\":14.31,\"moyenne_autre\":15.14,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:33',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(103,112,132,100,'34216df3df686b74bbdd3ba67b64568f199ffb05d120116ec23c02a8343e5977',1,13.51,4,12,'AB','admis','Travail correct. Avec plus de régularité, de meilleurs résultats sont à portée.',NULL,'brouillon','{\"eleve_id\":112,\"eleve_nom\":\"Nom21\",\"eleve_prenom\":\"Prenom38\",\"eleve_matricule\":\"TESTBUL26-04\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":13.58,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.88,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.78,\"compo\":13.88,\"moyenne_classe\":11.06,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":14.3,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.6,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.5,\"compo\":14.6,\"moyenne_classe\":11.59,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":14.7,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.9,\"compo\":15,\"moyenne_classe\":11.99,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":13.72,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.02,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.92,\"compo\":14.02,\"moyenne_classe\":10.74,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":12.94,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.24,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.14,\"compo\":13.24,\"moyenne_classe\":10.87,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":13.22,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.52,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.42,\"compo\":13.52,\"moyenne_classe\":10.24,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":12.54,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.84,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.74,\"compo\":12.84,\"moyenne_classe\":10.47,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":13.34,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.64,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.54,\"compo\":13.64,\"moyenne_classe\":11.27,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":13.51,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":4,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 13.51\\/20 ≥ 10.\",\"appreciation_pp\":\"Travail correct. Avec plus de régularité, de meilleurs résultats sont à portée.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"34216df3df686b74bbdd3ba67b64568f199ffb05d120116ec23c02a8343e5977\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/34216df3df686b74bbdd3ba67b64568f199ffb05d120116ec23c02a8343e5977\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:34\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":4,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":13.66,\"moyenne_scientifique\":13.22,\"moyenne_autre\":14.7,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:34',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(104,113,132,100,'bc22cdec4a5864e810a888add56ad6240710a4383a229c2102f90b8595a17795',1,12.34,5,12,'AB','admis','Assez bien. La Démo et la régularité dans le travail s\'imposent.',NULL,'brouillon','{\"eleve_id\":113,\"eleve_nom\":\"Nom24\",\"eleve_prenom\":\"Prenom06\",\"eleve_matricule\":\"TESTBUL26-05\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":11.94,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.24,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.14,\"compo\":12.24,\"moyenne_classe\":11.06,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":12.66,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.96,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.86,\"compo\":12.96,\"moyenne_classe\":11.59,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":13.06,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.36,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.26,\"compo\":13.36,\"moyenne_classe\":11.99,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":12.08,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.38,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.28,\"compo\":12.38,\"moyenne_classe\":10.74,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":12.4,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.7,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.6,\"compo\":12.7,\"moyenne_classe\":10.87,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":11.58,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.88,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.78,\"compo\":11.88,\"moyenne_classe\":10.24,\"rang\":5,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":12,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.3,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.2,\"compo\":12.3,\"moyenne_classe\":10.47,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":12.8,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.1,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13,\"compo\":13.1,\"moyenne_classe\":11.27,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":12.34,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":5,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 12.34\\/20 ≥ 10.\",\"appreciation_pp\":\"Assez bien. La Démo et la régularité dans le travail s\'imposent.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"bc22cdec4a5864e810a888add56ad6240710a4383a229c2102f90b8595a17795\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/bc22cdec4a5864e810a888add56ad6240710a4383a229c2102f90b8595a17795\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:35\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":5,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":12.33,\"moyenne_scientifique\":12.23,\"moyenne_autre\":13.06,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:35',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(105,114,132,100,'f9f839abc01f767016e4c4d2e1a0f441530c964dc886fbdf2c4797e11bbbb995',1,11.01,6,12,'P','admis','Le minimum requis est atteint. Un engagement plus fort est attendu pour le prochain trimestre.',NULL,'brouillon','{\"eleve_id\":114,\"eleve_nom\":\"Nom28\",\"eleve_prenom\":\"Prenom13\",\"eleve_matricule\":\"TESTBUL26-06\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":11.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.8,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.7,\"compo\":11.8,\"moyenne_classe\":11.06,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":12.22,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.52,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.42,\"compo\":12.52,\"moyenne_classe\":11.59,\"rang\":6,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":12.62,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.92,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.82,\"compo\":12.92,\"moyenne_classe\":11.99,\"rang\":6,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":10.54,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.84,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.74,\"compo\":10.84,\"moyenne_classe\":10.74,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":10.86,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.16,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.06,\"compo\":11.16,\"moyenne_classe\":10.87,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":10.04,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.34,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.24,\"compo\":10.34,\"moyenne_classe\":10.24,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":10.46,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.76,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.66,\"compo\":10.76,\"moyenne_classe\":10.47,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":11.26,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.56,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.46,\"compo\":11.56,\"moyenne_classe\":11.27,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":11.01,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":6,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 11.01\\/20 ≥ 10.\",\"appreciation_pp\":\"Le minimum requis est atteint. Un engagement plus fort est attendu pour le prochain trimestre.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"f9f839abc01f767016e4c4d2e1a0f441530c964dc886fbdf2c4797e11bbbb995\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/f9f839abc01f767016e4c4d2e1a0f441530c964dc886fbdf2c4797e11bbbb995\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:36\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":6,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":11.11,\"moyenne_scientifique\":10.69,\"moyenne_autre\":12.62,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:36',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(106,115,132,100,'1d4376d6d8c0a57480867c87ad516909b12dcb9f80253e10092c0fac510caff5',1,10.26,7,12,'P','admis','Des résultats passables. Un travail plus rigoureux et plus régulier s\'impose.',NULL,'brouillon','{\"eleve_id\":115,\"eleve_nom\":\"Nom02\",\"eleve_prenom\":\"Prenom08\",\"eleve_matricule\":\"TESTBUL26-07\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":9.86,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.16,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.06,\"compo\":10.16,\"moyenne_classe\":11.06,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":10.58,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.88,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.78,\"compo\":10.88,\"moyenne_classe\":11.59,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":10.98,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.28,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.18,\"compo\":11.28,\"moyenne_classe\":11.99,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":10,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.3,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.2,\"compo\":10.3,\"moyenne_classe\":10.74,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":10.32,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.62,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.52,\"compo\":10.62,\"moyenne_classe\":10.87,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":9.5,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.8,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.7,\"compo\":9.8,\"moyenne_classe\":10.24,\"rang\":7,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":9.92,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.22,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.12,\"compo\":10.22,\"moyenne_classe\":10.47,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":10.72,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.02,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.92,\"compo\":11.02,\"moyenne_classe\":11.27,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":10.26,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":7,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 10.26\\/20 ≥ 10.\",\"appreciation_pp\":\"Des résultats passables. Un travail plus rigoureux et plus régulier s\'impose.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"1d4376d6d8c0a57480867c87ad516909b12dcb9f80253e10092c0fac510caff5\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/1d4376d6d8c0a57480867c87ad516909b12dcb9f80253e10092c0fac510caff5\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:31\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":7,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":10.25,\"moyenne_scientifique\":10.15,\"moyenne_autre\":10.98,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:31',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(107,116,132,100,'9cd39b9d98717ffed6965987e6ec2c8a70d16f0f6129b053890ac769d9205e6c',1,9.02,8,12,'INS','rattrapage','Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.',NULL,'brouillon','{\"eleve_id\":116,\"eleve_nom\":\"Nom10\",\"eleve_prenom\":\"Prenom21\",\"eleve_matricule\":\"TESTBUL26-08\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":9.62,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.92,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.82,\"compo\":9.92,\"moyenne_classe\":11.06,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":9.24,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.54,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.44,\"compo\":9.54,\"moyenne_classe\":11.59,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":9.64,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.94,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.84,\"compo\":9.94,\"moyenne_classe\":11.99,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":8.66,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.96,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.86,\"compo\":8.96,\"moyenne_classe\":10.74,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":8.98,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.28,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.18,\"compo\":9.28,\"moyenne_classe\":10.87,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":8.16,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.46,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.36,\"compo\":8.46,\"moyenne_classe\":10.24,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":8.58,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.88,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.78,\"compo\":8.88,\"moyenne_classe\":10.47,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":9.38,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.68,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.58,\"compo\":9.68,\"moyenne_classe\":11.27,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":9.02,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":8,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 9.02\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"9cd39b9d98717ffed6965987e6ec2c8a70d16f0f6129b053890ac769d9205e6c\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/9cd39b9d98717ffed6965987e6ec2c8a70d16f0f6129b053890ac769d9205e6c\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:32\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":8,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":9.23,\"moyenne_scientifique\":8.81,\"moyenne_autre\":9.64,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:32',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(108,117,132,100,'f7a4dae46a46e081a36b7530fde4a7e012dc7ff31c50d5f258485d15b1c0942d',1,8.01,9,12,'INS','rattrapage','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.','je suis fiere de toi','brouillon','{\"eleve_id\":117,\"eleve_nom\":\"Nom01\",\"eleve_prenom\":\"Prenom42\",\"eleve_matricule\":\"TESTBUL26-09\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":8.08,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.38,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.28,\"compo\":8.38,\"moyenne_classe\":11.06,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":8.8,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.1,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9,\"compo\":9.1,\"moyenne_classe\":11.59,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":9.2,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.5,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.4,\"compo\":9.5,\"moyenne_classe\":11.99,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":8.22,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.52,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.42,\"compo\":8.52,\"moyenne_classe\":10.74,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":7.44,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.74,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.64,\"compo\":7.74,\"moyenne_classe\":10.87,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":7.72,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.02,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.92,\"compo\":8.02,\"moyenne_classe\":10.24,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":7.04,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.34,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.24,\"compo\":7.34,\"moyenne_classe\":10.47,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":7.84,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.14,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.04,\"compo\":8.14,\"moyenne_classe\":11.27,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":8.01,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":9,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 8.01\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"f7a4dae46a46e081a36b7530fde4a7e012dc7ff31c50d5f258485d15b1c0942d\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/f7a4dae46a46e081a36b7530fde4a7e012dc7ff31c50d5f258485d15b1c0942d\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:31\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":9,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":8.16,\"moyenne_scientifique\":7.72,\"moyenne_autre\":9.2,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:31',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(109,118,132,100,'9c8d8e8314f1028a649145ef41ce8a02ed4129f91bfce380a9e2fa29b8462ec4',1,6.94,10,12,'INS','refuse','Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.',NULL,'brouillon','{\"eleve_id\":118,\"eleve_nom\":\"Nom30\",\"eleve_prenom\":\"Prenom18\",\"eleve_matricule\":\"TESTBUL26-10\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":6.54,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.84,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.74,\"compo\":6.84,\"moyenne_classe\":11.06,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":7.26,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.56,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.46,\"compo\":7.56,\"moyenne_classe\":11.59,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":7.66,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.96,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.86,\"compo\":7.96,\"moyenne_classe\":11.99,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":6.68,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.98,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.88,\"compo\":6.98,\"moyenne_classe\":10.74,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":7,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.3,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.2,\"compo\":7.3,\"moyenne_classe\":10.87,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":6.18,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.48,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.38,\"compo\":6.48,\"moyenne_classe\":10.24,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":6.6,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.9,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.8,\"compo\":6.9,\"moyenne_classe\":10.47,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":7.4,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.7,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.6,\"compo\":7.7,\"moyenne_classe\":11.27,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":6.94,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":10,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 6.94\\/20 < 10.\",\"appreciation_pp\":\"Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"9c8d8e8314f1028a649145ef41ce8a02ed4129f91bfce380a9e2fa29b8462ec4\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/9c8d8e8314f1028a649145ef41ce8a02ed4129f91bfce380a9e2fa29b8462ec4\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:36\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":10,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":6.93,\"moyenne_scientifique\":6.83,\"moyenne_autre\":7.66,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:36',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(110,119,132,100,'420b8ca23ca0ea4a7895e6bcf09221948b79ec25d7cf9abc0d6fed7b8f575323',1,5.61,11,12,'INS','refuse','Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.',NULL,'brouillon','{\"eleve_id\":119,\"eleve_nom\":\"Nom20\",\"eleve_prenom\":\"Prenom24\",\"eleve_matricule\":\"TESTBUL26-11\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":6.1,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.4,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.3,\"compo\":6.4,\"moyenne_classe\":11.06,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":6.82,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.12,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.02,\"compo\":7.12,\"moyenne_classe\":11.59,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":7.22,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.52,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.42,\"compo\":7.52,\"moyenne_classe\":11.99,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":5.14,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.44,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.34,\"compo\":5.44,\"moyenne_classe\":10.74,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":5.46,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.76,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.66,\"compo\":5.76,\"moyenne_classe\":10.87,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":4.64,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":4.94,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.84,\"compo\":4.94,\"moyenne_classe\":10.24,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":5.06,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.36,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.26,\"compo\":5.36,\"moyenne_classe\":10.47,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":5.86,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.16,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.06,\"compo\":6.16,\"moyenne_classe\":11.27,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":5.61,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":11,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 5.61\\/20 < 10.\",\"appreciation_pp\":\"Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"420b8ca23ca0ea4a7895e6bcf09221948b79ec25d7cf9abc0d6fed7b8f575323\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/420b8ca23ca0ea4a7895e6bcf09221948b79ec25d7cf9abc0d6fed7b8f575323\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:34\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":11,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":5.71,\"moyenne_scientifique\":5.29,\"moyenne_autre\":7.22,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:34',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(111,120,132,100,'9a400b91abb75cb5f83404938ff01f2de508e846fbe8416b1f846b132adc80f5',1,4.96,12,12,'INS','refuse','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.',NULL,'brouillon','{\"eleve_id\":120,\"eleve_nom\":\"Nom34\",\"eleve_prenom\":\"Prenom02\",\"eleve_matricule\":\"TESTBUL26-12\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":100,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":4.56,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":4.86,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.76,\"compo\":4.86,\"moyenne_classe\":11.06,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":5.28,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.58,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.48,\"compo\":5.58,\"moyenne_classe\":11.59,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":5.68,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.98,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.88,\"compo\":5.98,\"moyenne_classe\":11.99,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":4.7,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.9,\"compo\":5,\"moyenne_classe\":10.74,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":5.02,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.32,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.22,\"compo\":5.32,\"moyenne_classe\":10.87,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":4.2,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":4.5,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.4,\"compo\":4.5,\"moyenne_classe\":10.24,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":4.62,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":4.92,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.82,\"compo\":4.92,\"moyenne_classe\":10.47,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":5.42,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.72,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.62,\"compo\":5.72,\"moyenne_classe\":11.27,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":4.96,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":12,\"nb_eleves\":12,\"moyenne_classe\":10.79,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.31,\"moyenne\":10.79,\"ecart_type\":3.87,\"taux_reussite\":58.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 4.96\\/20 < 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"9a400b91abb75cb5f83404938ff01f2de508e846fbe8416b1f846b132adc80f5\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/9a400b91abb75cb5f83404938ff01f2de508e846fbe8416b1f846b132adc80f5\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:37\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":12,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":4.95,\"moyenne_scientifique\":4.85,\"moyenne_autre\":5.68,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:37',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(112,109,132,101,'cd580815039ad49a65e63a932913be567484cb5dd9f3c206683154a33a1fa357',1,17.67,1,12,'TB','admis','Excellent trimestre. Un travail d\'une grande qualité qui force l\'admiration.',NULL,'brouillon','{\"eleve_id\":109,\"eleve_nom\":\"Nom17\",\"eleve_prenom\":\"Prenom04\",\"eleve_matricule\":\"TESTBUL26-01\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":18.27,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":18.57,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":18.47,\"compo\":18.57,\"moyenne_classe\":11.34,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":17.89,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":18.19,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":18.09,\"compo\":18.19,\"moyenne_classe\":11.79,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":18.29,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":18.59,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":18.49,\"compo\":18.59,\"moyenne_classe\":12.19,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":17.31,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.61,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.51,\"compo\":17.61,\"moyenne_classe\":11.02,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":17.63,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.93,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.83,\"compo\":17.93,\"moyenne_classe\":11.07,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":16.81,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.11,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.01,\"compo\":17.11,\"moyenne_classe\":10.52,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":17.23,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.53,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.43,\"compo\":17.53,\"moyenne_classe\":10.67,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":18.03,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":18.33,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":18.23,\"compo\":18.33,\"moyenne_classe\":11.47,\"rang\":1,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Excellent travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":17.67,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":1,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 17.67\\/20 ≥ 10.\",\"appreciation_pp\":\"Excellent trimestre. Un travail d\'une grande qualité qui force l\'admiration.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"cd580815039ad49a65e63a932913be567484cb5dd9f3c206683154a33a1fa357\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/cd580815039ad49a65e63a932913be567484cb5dd9f3c206683154a33a1fa357\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:40\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":1,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":17.88,\"moyenne_scientifique\":17.46,\"moyenne_autre\":18.29,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:40',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(113,110,132,101,'36f70c4443dfec52a3d6d993da867fc1229f639d54bdb31b9dafb7a3105c9663',1,15.96,2,12,'B','admis','Bon travail dans l\'ensemble. Quelques points à approfondir pour progresser encore.',NULL,'brouillon','{\"eleve_id\":110,\"eleve_nom\":\"Nom26\",\"eleve_prenom\":\"Prenom14\",\"eleve_matricule\":\"TESTBUL26-02\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":16.03,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.33,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.23,\"compo\":16.33,\"moyenne_classe\":11.34,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":16.75,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.05,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.95,\"compo\":17.05,\"moyenne_classe\":11.79,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":17.15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":17.45,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":17.35,\"compo\":17.45,\"moyenne_classe\":12.19,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":16.17,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.47,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":16.37,\"compo\":16.47,\"moyenne_classe\":11.02,\"rang\":2,\"mention_code\":\"TB\",\"mention_label\":\"Très Bien\",\"mention_css\":\"emerald\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":15.39,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.69,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.59,\"compo\":15.69,\"moyenne_classe\":11.07,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":15.67,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.97,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.87,\"compo\":15.97,\"moyenne_classe\":10.52,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":14.99,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.29,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.19,\"compo\":15.29,\"moyenne_classe\":10.67,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":15.79,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":16.09,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.99,\"compo\":16.09,\"moyenne_classe\":11.47,\"rang\":2,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Très bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":15.96,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":2,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 15.96\\/20 ≥ 10.\",\"appreciation_pp\":\"Bon travail dans l\'ensemble. Quelques points à approfondir pour progresser encore.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"36f70c4443dfec52a3d6d993da867fc1229f639d54bdb31b9dafb7a3105c9663\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/36f70c4443dfec52a3d6d993da867fc1229f639d54bdb31b9dafb7a3105c9663\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:42\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":2,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":16.11,\"moyenne_scientifique\":15.67,\"moyenne_autre\":17.15,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:42',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(114,111,132,101,'22b732141f6d19ae854c2aca58e9b536aff8e4dcd86aecf1be4d0cd72cb8aeca',1,14.89,3,12,'B','admis','Bon trimestre. Des résultats encourageants qui méritent d\'être consolidés.',NULL,'brouillon','{\"eleve_id\":111,\"eleve_nom\":\"Nom18\",\"eleve_prenom\":\"Prenom32\",\"eleve_matricule\":\"TESTBUL26-03\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":14.49,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.79,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.69,\"compo\":14.79,\"moyenne_classe\":11.34,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":15.21,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.51,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.41,\"compo\":15.51,\"moyenne_classe\":11.79,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":15.61,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.91,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.81,\"compo\":15.91,\"moyenne_classe\":12.19,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":14.63,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.93,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.83,\"compo\":14.93,\"moyenne_classe\":11.02,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":14.95,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.25,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.15,\"compo\":15.25,\"moyenne_classe\":11.07,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":14.13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.43,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.33,\"compo\":14.43,\"moyenne_classe\":10.52,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":14.55,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.85,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.75,\"compo\":14.85,\"moyenne_classe\":10.67,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":15.35,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.65,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.55,\"compo\":15.65,\"moyenne_classe\":11.47,\"rang\":3,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Bon travail\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":14.89,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":3,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 14.89\\/20 ≥ 10.\",\"appreciation_pp\":\"Bon trimestre. Des résultats encourageants qui méritent d\'être consolidés.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"22b732141f6d19ae854c2aca58e9b536aff8e4dcd86aecf1be4d0cd72cb8aeca\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/22b732141f6d19ae854c2aca58e9b536aff8e4dcd86aecf1be4d0cd72cb8aeca\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:40\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":3,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":14.88,\"moyenne_scientifique\":14.78,\"moyenne_autre\":15.61,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:40',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(115,112,132,101,'785cbc8bf0b90ab04093f1b14205709a8ec9a291ebe0f1b708c3d6f2d3cb65db',1,13.56,4,12,'AB','admis','Travail correct. Avec plus de régularité, de meilleurs résultats sont à portée.',NULL,'brouillon','{\"eleve_id\":112,\"eleve_nom\":\"Nom21\",\"eleve_prenom\":\"Prenom38\",\"eleve_matricule\":\"TESTBUL26-04\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":14.05,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.35,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.25,\"compo\":14.35,\"moyenne_classe\":11.34,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":14.77,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.07,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.97,\"compo\":15.07,\"moyenne_classe\":11.79,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":15.17,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":15.47,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":15.37,\"compo\":15.47,\"moyenne_classe\":12.19,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":13.09,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.39,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.29,\"compo\":13.39,\"moyenne_classe\":11.02,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":13.41,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.71,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.61,\"compo\":13.71,\"moyenne_classe\":11.07,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":12.59,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.89,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.79,\"compo\":12.89,\"moyenne_classe\":10.52,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":13.01,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.31,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.21,\"compo\":13.31,\"moyenne_classe\":10.67,\"rang\":4,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":13.81,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":14.11,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":14.01,\"compo\":14.11,\"moyenne_classe\":11.47,\"rang\":4,\"mention_code\":\"B\",\"mention_label\":\"Bien\",\"mention_css\":\"blue\",\"appreciation\":\"Assez bien\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":13.56,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":4,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 13.56\\/20 ≥ 10.\",\"appreciation_pp\":\"Travail correct. Avec plus de régularité, de meilleurs résultats sont à portée.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"785cbc8bf0b90ab04093f1b14205709a8ec9a291ebe0f1b708c3d6f2d3cb65db\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/785cbc8bf0b90ab04093f1b14205709a8ec9a291ebe0f1b708c3d6f2d3cb65db\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:41\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":4,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":13.66,\"moyenne_scientifique\":13.24,\"moyenne_autre\":15.17,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:41',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(116,113,132,101,'3feaa42b9cbd3be2c9ca7bcbfa2a6736d48e535a5dfc6af4742a5f7ddc512b25',1,12.81,5,12,'AB','admis','Assez bien. La Démo et la régularité dans le travail s\'imposent.',NULL,'brouillon','{\"eleve_id\":113,\"eleve_nom\":\"Nom24\",\"eleve_prenom\":\"Prenom06\",\"eleve_matricule\":\"TESTBUL26-05\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":12.41,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.71,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.61,\"compo\":12.71,\"moyenne_classe\":11.34,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":13.13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.43,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.33,\"compo\":13.43,\"moyenne_classe\":11.79,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":13.53,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.83,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.73,\"compo\":13.83,\"moyenne_classe\":12.19,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":12.55,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.85,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.75,\"compo\":12.85,\"moyenne_classe\":11.02,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":12.87,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.17,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.07,\"compo\":13.17,\"moyenne_classe\":11.07,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":12.05,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.35,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.25,\"compo\":12.35,\"moyenne_classe\":10.52,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":12.47,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.77,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.67,\"compo\":12.77,\"moyenne_classe\":10.67,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":13.27,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":13.57,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":13.47,\"compo\":13.57,\"moyenne_classe\":11.47,\"rang\":5,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Efforts réguliers\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":12.81,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":5,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 12.81\\/20 ≥ 10.\",\"appreciation_pp\":\"Assez bien. La Démo et la régularité dans le travail s\'imposent.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"3feaa42b9cbd3be2c9ca7bcbfa2a6736d48e535a5dfc6af4742a5f7ddc512b25\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/3feaa42b9cbd3be2c9ca7bcbfa2a6736d48e535a5dfc6af4742a5f7ddc512b25\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:42\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":5,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":12.8,\"moyenne_scientifique\":12.7,\"moyenne_autre\":13.53,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:42',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(117,114,132,101,'c512a154234b1e7d3d48d425ea1d85551cdb03f2258ffc120107feefb14c9507',1,11.37,6,12,'P','admis','Le minimum requis est atteint. Un engagement plus fort est attendu pour le prochain trimestre.',NULL,'brouillon','{\"eleve_id\":114,\"eleve_nom\":\"Nom28\",\"eleve_prenom\":\"Prenom13\",\"eleve_matricule\":\"TESTBUL26-06\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":11.97,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.27,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.17,\"compo\":12.27,\"moyenne_classe\":11.34,\"rang\":6,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":11.59,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.89,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.79,\"compo\":11.89,\"moyenne_classe\":11.79,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":11.99,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.29,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":12.19,\"compo\":12.29,\"moyenne_classe\":12.19,\"rang\":6,\"mention_code\":\"AB\",\"mention_label\":\"Assez Bien\",\"mention_css\":\"cyan\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":11.01,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.31,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.21,\"compo\":11.31,\"moyenne_classe\":11.02,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":11.33,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.63,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.53,\"compo\":11.63,\"moyenne_classe\":11.07,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":10.51,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.81,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.71,\"compo\":10.81,\"moyenne_classe\":10.52,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":10.93,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.23,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.13,\"compo\":11.23,\"moyenne_classe\":10.67,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":11.73,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":12.03,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.93,\"compo\":12.03,\"moyenne_classe\":11.47,\"rang\":6,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Résultats corrects\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":11.37,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":6,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 11.37\\/20 ≥ 10.\",\"appreciation_pp\":\"Le minimum requis est atteint. Un engagement plus fort est attendu pour le prochain trimestre.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"c512a154234b1e7d3d48d425ea1d85551cdb03f2258ffc120107feefb14c9507\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/c512a154234b1e7d3d48d425ea1d85551cdb03f2258ffc120107feefb14c9507\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:43\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":6,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":11.58,\"moyenne_scientifique\":11.16,\"moyenne_autre\":11.99,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:43',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(118,115,132,101,'a1e491925e01c550142d0bc8616bfb371542f6fbbd91694e7e079374d3ba815e',1,10.26,7,12,'P','admis','Des résultats passables. Un travail plus rigoureux et plus régulier s\'impose.',NULL,'brouillon','{\"eleve_id\":115,\"eleve_nom\":\"Nom02\",\"eleve_prenom\":\"Prenom08\",\"eleve_matricule\":\"TESTBUL26-07\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":10.33,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.63,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.53,\"compo\":10.63,\"moyenne_classe\":11.34,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":11.05,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.35,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.25,\"compo\":11.35,\"moyenne_classe\":11.79,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":11.45,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":11.75,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":11.65,\"compo\":11.75,\"moyenne_classe\":12.19,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":10.47,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.77,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.67,\"compo\":10.77,\"moyenne_classe\":11.02,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":9.69,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.99,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.89,\"compo\":9.99,\"moyenne_classe\":11.07,\"rang\":7,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":9.97,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.27,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.17,\"compo\":10.27,\"moyenne_classe\":10.52,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":9.29,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.59,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.49,\"compo\":9.59,\"moyenne_classe\":10.67,\"rang\":7,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":10.09,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.39,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.29,\"compo\":10.39,\"moyenne_classe\":11.47,\"rang\":7,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Travail à poursuivre\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":10.26,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"admis\":true,\"a_eliminatoire\":false,\"rang\":7,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"admis\",\"decision_motif\":\"Moyenne 10.26\\/20 ≥ 10.\",\"appreciation_pp\":\"Des résultats passables. Un travail plus rigoureux et plus régulier s\'impose.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"a1e491925e01c550142d0bc8616bfb371542f6fbbd91694e7e079374d3ba815e\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/a1e491925e01c550142d0bc8616bfb371542f6fbbd91694e7e079374d3ba815e\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:39\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":7,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":10.41,\"moyenne_scientifique\":9.97,\"moyenne_autre\":11.45,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:39',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(119,116,132,101,'decc6b9e4779da5de7cf249b353b8712b4e5729129f4cbb79fc40a5b19513fec',1,9.39,8,12,'INS','rattrapage','Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.',NULL,'brouillon','{\"eleve_id\":116,\"eleve_nom\":\"Nom10\",\"eleve_prenom\":\"Prenom21\",\"eleve_matricule\":\"TESTBUL26-08\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":8.99,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.29,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.19,\"compo\":9.29,\"moyenne_classe\":11.34,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":9.71,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.01,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.91,\"compo\":10.01,\"moyenne_classe\":11.79,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":10.11,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.41,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.31,\"compo\":10.41,\"moyenne_classe\":12.19,\"rang\":8,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":9.13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.43,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.33,\"compo\":9.43,\"moyenne_classe\":11.02,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":9.45,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.75,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.65,\"compo\":9.75,\"moyenne_classe\":11.07,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":8.63,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.93,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.83,\"compo\":8.93,\"moyenne_classe\":10.52,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":9.05,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.35,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.25,\"compo\":9.35,\"moyenne_classe\":10.67,\"rang\":8,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":9.85,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":10.15,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":10.05,\"compo\":10.15,\"moyenne_classe\":11.47,\"rang\":8,\"mention_code\":\"P\",\"mention_label\":\"Passable\",\"mention_css\":\"amber\",\"appreciation\":\"Doit progresser\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":9.39,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":8,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 9.39\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"decc6b9e4779da5de7cf249b353b8712b4e5729129f4cbb79fc40a5b19513fec\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/decc6b9e4779da5de7cf249b353b8712b4e5729129f4cbb79fc40a5b19513fec\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:39\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":8,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":9.38,\"moyenne_scientifique\":9.28,\"moyenne_autre\":10.11,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:39',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(120,117,132,101,'f82997fb3589734f87360671a638900697bdfae6a9c4314ec949917fd963953a',1,8.06,9,12,'INS','rattrapage','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.',NULL,'brouillon','{\"eleve_id\":117,\"eleve_nom\":\"Nom01\",\"eleve_prenom\":\"Prenom42\",\"eleve_matricule\":\"TESTBUL26-09\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":8.55,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.85,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.75,\"compo\":8.85,\"moyenne_classe\":11.34,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":9.27,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.57,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.47,\"compo\":9.57,\"moyenne_classe\":11.79,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":9.67,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":9.97,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":9.87,\"compo\":9.97,\"moyenne_classe\":12.19,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":7.59,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.89,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.79,\"compo\":7.89,\"moyenne_classe\":11.02,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":7.91,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.21,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.11,\"compo\":8.21,\"moyenne_classe\":11.07,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":7.09,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.39,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.29,\"compo\":7.39,\"moyenne_classe\":10.52,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":7.51,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.81,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.71,\"compo\":7.81,\"moyenne_classe\":10.67,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":8.31,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.61,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.51,\"compo\":8.61,\"moyenne_classe\":11.47,\"rang\":9,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Résultats fragiles\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":8.06,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":9,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"rattrapage\",\"decision_motif\":\"Moyenne 8.06\\/20 entre 8 et 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"f82997fb3589734f87360671a638900697bdfae6a9c4314ec949917fd963953a\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/f82997fb3589734f87360671a638900697bdfae6a9c4314ec949917fd963953a\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:38\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":9,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":8.16,\"moyenne_scientifique\":7.74,\"moyenne_autre\":9.67,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:38',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(121,118,132,101,'5d2934676b5b81853a0cbfbe0b977f00e6a29bcb2dac16a650f3beb111623887',1,7.41,10,12,'INS','refuse','Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.',NULL,'brouillon','{\"eleve_id\":118,\"eleve_nom\":\"Nom30\",\"eleve_prenom\":\"Prenom18\",\"eleve_matricule\":\"TESTBUL26-10\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":7.01,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.31,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.21,\"compo\":7.31,\"moyenne_classe\":11.34,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":7.73,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.03,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.93,\"compo\":8.03,\"moyenne_classe\":11.79,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":8.13,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.43,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.33,\"compo\":8.43,\"moyenne_classe\":12.19,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":7.15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.45,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.35,\"compo\":7.45,\"moyenne_classe\":11.02,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":7.47,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.77,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.67,\"compo\":7.77,\"moyenne_classe\":11.07,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":6.65,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.95,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.85,\"compo\":6.95,\"moyenne_classe\":10.52,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":7.07,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":7.37,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":7.27,\"compo\":7.37,\"moyenne_classe\":10.67,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":7.87,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":8.17,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":8.07,\"compo\":8.17,\"moyenne_classe\":11.47,\"rang\":10,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Efforts indispensables\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":7.41,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":10,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 7.41\\/20 < 10.\",\"appreciation_pp\":\"Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"5d2934676b5b81853a0cbfbe0b977f00e6a29bcb2dac16a650f3beb111623887\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/5d2934676b5b81853a0cbfbe0b977f00e6a29bcb2dac16a650f3beb111623887\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:43\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":10,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":7.4,\"moyenne_scientifique\":7.3,\"moyenne_autre\":8.13,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:43',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(122,119,132,101,'2bf1f826d30ddba1a9a7ca37492c40f6e778ceb10c249d17bad3c2a58422d65c',1,5.97,11,12,'INS','refuse','Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.',NULL,'brouillon','{\"eleve_id\":119,\"eleve_nom\":\"Nom20\",\"eleve_prenom\":\"Prenom24\",\"eleve_matricule\":\"TESTBUL26-11\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":6.57,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.87,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.77,\"compo\":6.87,\"moyenne_classe\":11.34,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":6.19,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.49,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.39,\"compo\":6.49,\"moyenne_classe\":11.79,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":6.59,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.89,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.79,\"compo\":6.89,\"moyenne_classe\":12.19,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":5.61,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.91,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.81,\"compo\":5.91,\"moyenne_classe\":11.02,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":5.93,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.23,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.13,\"compo\":6.23,\"moyenne_classe\":11.07,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":5.11,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.41,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.31,\"compo\":5.41,\"moyenne_classe\":10.52,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":5.53,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.83,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.73,\"compo\":5.83,\"moyenne_classe\":10.67,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":6.33,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.63,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.53,\"compo\":6.63,\"moyenne_classe\":11.47,\"rang\":11,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Difficultés à combler\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":5.97,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":11,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 5.97\\/20 < 10.\",\"appreciation_pp\":\"Résultats préoccupants. Un effort considérable et soutenu est attendu d\'urgence.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"2bf1f826d30ddba1a9a7ca37492c40f6e778ceb10c249d17bad3c2a58422d65c\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/2bf1f826d30ddba1a9a7ca37492c40f6e778ceb10c249d17bad3c2a58422d65c\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:41\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":11,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":6.18,\"moyenne_scientifique\":5.76,\"moyenne_autre\":6.59,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:41',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(123,120,132,101,'ea234cef1fdf1fa88a23e14267c90b0d9b5901cd811d635f8c436b10aa7d36f4',1,4.96,12,12,'INS','refuse','Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.',NULL,'brouillon','{\"eleve_id\":120,\"eleve_nom\":\"Nom34\",\"eleve_prenom\":\"Prenom02\",\"eleve_matricule\":\"TESTBUL26-12\",\"eleve_photo\":null,\"classe_id\":132,\"classe_nom\":\"TB-01\",\"niveau\":\"Seconde\",\"periode_id\":101,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2026-2027\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":5.03,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.33,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.23,\"compo\":5.33,\"moyenne_classe\":11.34,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":5.75,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.05,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.95,\"compo\":6.05,\"moyenne_classe\":11.79,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":6.15,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":6.45,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":6.35,\"compo\":6.45,\"moyenne_classe\":12.19,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":5.17,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.47,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":5.37,\"compo\":5.47,\"moyenne_classe\":11.02,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":4.39,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":4.69,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.59,\"compo\":4.69,\"moyenne_classe\":11.07,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":4.67,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":4.97,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.87,\"compo\":4.97,\"moyenne_classe\":10.52,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":3.99,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":4.29,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.19,\"compo\":4.29,\"moyenne_classe\":10.67,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":4.79,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false},{\"valeur\":5.09,\"note_max\":20,\"coeff_eval\":2,\"est_absent\":false}],\"moyenne\":4.99,\"compo\":5.09,\"moyenne_classe\":11.47,\"rang\":12,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"Accompagnement nécessaire\",\"aEliminatoire\":false,\"nb_notes\":2,\"nb_absents\":0}],\"moyenne_periode\":4.96,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":12,\"nb_eleves\":12,\"moyenne_classe\":11.03,\"statistiques_classe\":{\"count\":12,\"admis\":7,\"echoues\":5,\"min\":4.96,\"max\":17.67,\"moyenne\":11.03,\"ecart_type\":3.88,\"taux_reussite\":58.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 4.96\\/20 < 10.\",\"appreciation_pp\":\"Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"ea234cef1fdf1fa88a23e14267c90b0d9b5901cd811d635f8c436b10aa7d36f4\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/ea234cef1fdf1fa88a23e14267c90b0d9b5901cd811d635f8c436b10aa7d36f4\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:44\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":10.79,\"moyenne2emeSemestre\":11.03,\"moyenneAnnuelle\":10.91,\"rangAnnuel\":12,\"nbElevesAnnuel\":12,\"decisionAnnuelleCode\":\"admis_superieur\",\"decisionAnnuelleLabel\":\"Admis en classe supérieure\"},\"moyenne_litteraire\":5.11,\"moyenne_scientifique\":4.67,\"moyenne_autre\":6.15,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":12,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:44',NULL,NULL,NULL,NULL,'2026-08-14 02:32:25','2026-10-29 20:04:53'),(126,121,131,90,'032591b8af91ddcd257a1268aadce601043a231527f295bf24767bd63ebf1097',1,0.00,6,6,'INS','refuse','Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.',NULL,'brouillon','{\"eleve_id\":121,\"eleve_nom\":\"Nom04\",\"eleve_prenom\":\"Prenom40\",\"eleve_matricule\":\"2024-0011\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":90,\"periode_nom\":\"Deuxième Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":9.86,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":14.17,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":13,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":10.5,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":8.33,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":6.54,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":6.92,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":10.4,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":0,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":6,\"nb_eleves\":6,\"moyenne_classe\":8.89,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":14.64,\"moyenne\":8.89,\"ecart_type\":4.56,\"taux_reussite\":33.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 0\\/20 < 10.\",\"appreciation_pp\":\"Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"032591b8af91ddcd257a1268aadce601043a231527f295bf24767bd63ebf1097\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/032591b8af91ddcd257a1268aadce601043a231527f295bf24767bd63ebf1097\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:26\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":6,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":0,\"moyenne_scientifique\":0,\"moyenne_autre\":0,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:26',NULL,NULL,NULL,NULL,'2026-09-01 09:45:27','2026-10-29 20:04:53'),(132,121,131,91,'92860bd5065eb9276cad370069dca42a3059a2e63d4ef037034f5cb742908346',1,0.00,6,6,'INS','refuse','Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.',NULL,'brouillon','{\"eleve_id\":121,\"eleve_nom\":\"Nom04\",\"eleve_prenom\":\"Prenom40\",\"eleve_matricule\":\"2024-0011\",\"eleve_photo\":null,\"classe_id\":131,\"classe_nom\":\"2nde C1\",\"niveau\":\"Seconde\",\"periode_id\":91,\"periode_nom\":\"Premier Semestre\",\"annee_scolaire\":\"2025-2026\",\"etablissement_nom\":\"La Démo\",\"etablissement_adresse\":\"Niamey, Niger\",\"etablissement_logo\":\"\\/assets\\/img\\/logo.jpeg\",\"lignes_matieres\":[{\"matiere_id\":5,\"matiere_nom\":\"Anglais\",\"coefficient\":2,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":8.63,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":190,\"matiere_nom\":\"Conduite\",\"coefficient\":1,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":13.42,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":189,\"matiere_nom\":\"EPS\",\"coefficient\":1,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":12.25,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":4,\"matiere_nom\":\"Français\",\"coefficient\":3,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":9.08,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":6,\"matiere_nom\":\"Histoire-Géographie\",\"coefficient\":2,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":9.25,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":1,\"matiere_nom\":\"Mathématiques\",\"coefficient\":5,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":6.58,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":188,\"matiere_nom\":\"Physique-Chimie\",\"coefficient\":4,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":7.67,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0},{\"matiere_id\":187,\"matiere_nom\":\"S.V.T\",\"coefficient\":3,\"notes\":[{\"valeur\":null,\"note_max\":20,\"coeff_eval\":1,\"est_absent\":false}],\"moyenne\":0,\"compo\":null,\"moyenne_classe\":9.08,\"rang\":6,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"appreciation\":\"\",\"aEliminatoire\":false,\"nb_notes\":1,\"nb_absents\":0}],\"moyenne_periode\":0,\"mention_code\":\"INS\",\"mention_label\":\"Insuffisant\",\"mention_css\":\"red\",\"admis\":false,\"a_eliminatoire\":false,\"rang\":6,\"nb_eleves\":6,\"moyenne_classe\":8.55,\"statistiques_classe\":{\"count\":6,\"admis\":2,\"echoues\":4,\"min\":0,\"max\":13.98,\"moyenne\":8.55,\"ecart_type\":4.42,\"taux_reussite\":33.33},\"decision\":\"refuse\",\"decision_motif\":\"Moyenne 0\\/20 < 10.\",\"appreciation_pp\":\"Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.\",\"appreciation_directeur\":null,\"signatures\":[{\"role\":\"Professeur Principal\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Directeur(trice)\",\"nom\":\"\",\"date\":\"\",\"signed\":false},{\"role\":\"Parent \\/ Tuteur\",\"nom\":\"\",\"date\":\"\",\"signed\":false}],\"verification_token\":\"92860bd5065eb9276cad370069dca42a3059a2e63d4ef037034f5cb742908346\",\"qr_code_url\":\"\\/v2\\/academique\\/bulletins\\/verify\\/92860bd5065eb9276cad370069dca42a3059a2e63d4ef037034f5cb742908346\",\"statut\":\"brouillon\",\"generated_at\":\"2026-09-01 08:45:28\",\"generated_by_id\":1,\"published_at\":null,\"archived_at\":null,\"resultats_annuels\":{\"moyenne1erSemestre\":8.55,\"moyenne2emeSemestre\":8.89,\"moyenneAnnuelle\":8.72,\"rangAnnuel\":6,\"nbElevesAnnuel\":6,\"decisionAnnuelleCode\":\"reorientation\",\"decisionAnnuelleLabel\":\"Réorientation\"},\"moyenne_litteraire\":0,\"moyenne_scientifique\":0,\"moyenne_autre\":0,\"absences\":{\"justifiees\":0,\"nonJustifiees\":0},\"effectif_classe\":6,\"etablissement_telephone\":\"98 98 63 63 \\/ 90 20 17 91\",\"etablissement_email\":\"contact@ecole.example\"}',1,'2026-09-01 08:45:28',NULL,NULL,NULL,NULL,'2026-09-01 09:45:28','2026-10-29 20:04:53');
/*!40000 ALTER TABLE `bulletins_v2` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `nom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: 1ère AS, 2ème AM',
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: 2024-2025',
  `max_eleves` tinyint unsigned NOT NULL DEFAULT '35',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classe_nom_niveau_annee` (`etablissement_id`,`nom`,`niveau`,`annee_scolaire`),
  KEY `idx_etab` (`etablissement_id`,`annee_scolaire`),
  CONSTRAINT `fk_classes_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=293 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,1,'A','Seconde','2024-2025',35,NULL,'2026-06-25 09:30:33'),(2,1,'B','Seconde','2024-2025',35,NULL,'2026-06-25 09:30:33'),(3,1,'A','Première','2024-2025',35,NULL,'2026-06-25 09:30:33'),(4,1,'A','Terminale','2024-2025',35,NULL,'2026-06-25 09:30:33'),(131,1,'2nde C1','Seconde','2025-2026',45,'Classe simulée pour l\'installation La Démo','2026-07-20 00:51:50'),(132,1,'TB-01','Seconde','2026-2027',30,'TEST-BUL-2026-2027','2026-08-14 01:08:13');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `controles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `controles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('controle','devoir','examen','tp','oral') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'controle',
  `coefficient` decimal(4,2) NOT NULL DEFAULT '1.00',
  `note_max` decimal(5,2) NOT NULL DEFAULT '20.00',
  `matiere_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `date_controle` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ctrl_context` (`classe_id`,`matiere_id`,`periode_id`),
  KEY `fk_ctrl_matiere` (`matiere_id`),
  KEY `fk_ctrl_periode` (`periode_id`),
  CONSTRAINT `fk_ctrl_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctrl_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctrl_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `controles` WRITE;
/*!40000 ALTER TABLE `controles` DISABLE KEYS */;
INSERT INTO `controles` VALUES (1,'Contrôle 1','controle',1.00,20.00,1,1,1,'2025-10-15','2026-06-25 09:39:33'),(2,'Devoir maison','devoir',0.50,20.00,1,1,1,'2025-11-01','2026-06-25 09:39:33'),(3,'Examen T1','examen',2.00,20.00,1,1,1,'2025-12-10','2026-06-25 09:39:33'),(4,'Contrôle 1','controle',1.00,20.00,188,1,1,'2025-10-20','2026-06-25 09:39:33'),(5,'Examen T1','examen',2.00,20.00,188,1,1,'2025-12-12','2026-06-25 09:39:33'),(6,'Contrôle 1','controle',1.00,20.00,4,1,1,'2025-10-18','2026-06-25 09:39:33'),(7,'Examen T1','examen',2.00,20.00,4,1,1,'2025-12-15','2026-06-25 09:39:33'),(8,'Contrôle 1','controle',1.00,20.00,3,1,1,'2025-10-17','2026-06-25 09:39:33'),(9,'Examen T1','examen',2.00,20.00,3,1,1,'2025-12-11','2026-06-25 09:39:33');
/*!40000 ALTER TABLE `controles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `depenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `depenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categorie_id` int unsigned DEFAULT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_depense` date NOT NULL,
  `mode_paiement` enum('especes','cheque','virement','carte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especes',
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `saisi_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_d_date` (`date_depense`),
  KEY `idx_d_categorie` (`categorie_id`),
  KEY `fk_d_user` (`saisi_par`),
  CONSTRAINT `fk_d_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `depenses_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_d_user` FOREIGN KEY (`saisi_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `depenses` WRITE;
/*!40000 ALTER TABLE `depenses` DISABLE KEYS */;
INSERT INTO `depenses` VALUES (1,1,'Salaires du personnel — Juin',120000.00,'2026-06-10','virement',NULL,NULL,1,'2026-06-25 09:39:38','2026-06-25 09:39:38'),(2,2,'Achat fournitures de bureau',3500.00,'2026-06-17','especes',NULL,NULL,1,'2026-06-25 09:39:38','2026-06-25 09:39:38'),(3,4,'Facture électricité',8000.00,'2026-06-22','cheque',NULL,NULL,1,'2026-06-25 09:39:38','2026-06-25 09:39:38'),(4,4,'Internet et téléphonie',2500.00,'2026-06-24','virement',NULL,NULL,1,'2026-06-25 09:39:38','2026-06-25 09:39:38'),(5,3,'construction',1234567.00,'2026-07-10','especes',NULL,NULL,1,'2026-07-10 14:22:46','2026-07-10 14:22:46');
/*!40000 ALTER TABLE `depenses` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `depenses_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `depenses_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6c757d',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `depenses_categories` WRITE;
/*!40000 ALTER TABLE `depenses_categories` DISABLE KEYS */;
INSERT INTO `depenses_categories` VALUES (1,'Salaires et charges','#4e73df'),(2,'Fournitures et matériel','#1cc88a'),(3,'Infrastructure','#36b9cc'),(4,'Services et utilités','#f6c23e'),(5,'Activités pédagogiques','#e74a3b'),(6,'Divers','#858796'),(7,'Salaires et charges','#4e73df'),(8,'Fournitures et matériel','#1cc88a'),(9,'Infrastructure','#36b9cc'),(10,'Services et utilités','#f6c23e'),(11,'Activités pédagogiques','#e74a3b'),(12,'Divers','#858796');
/*!40000 ALTER TABLE `depenses_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `eleves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eleves` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `matricule` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date NOT NULL,
  `sexe` enum('M','F') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classe_id` int unsigned DEFAULT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL COMMENT 'Compte utilisateur de l''élève (users.role=eleve)',
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricule` (`matricule`),
  UNIQUE KEY `idx_eleve_user` (`user_id`),
  KEY `idx_classe` (`classe_id`),
  KEY `idx_nom` (`nom`,`prenom`),
  KEY `idx_eleve_parent` (`parent_id`),
  KEY `idx_eleve_actif` (`actif`),
  KEY `idx_etab` (`etablissement_id`,`classe_id`),
  CONSTRAINT `fk_eleve_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eleve_parent` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eleve_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eleves_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=817 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `eleves` WRITE;
/*!40000 ALTER TABLE `eleves` DISABLE KEYS */;
INSERT INTO `eleves` VALUES (1,1,'2024-0001','Nom08','Prenom19','2006-11-22','M','1 rue de Test','0600000001','Prenom19.Nom08@edu.dz',131,281,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(2,1,'2024-0002','Nom25','Prenom05','2007-05-07','F','2 rue de Test','0600000002','Prenom05.Nom25@edu.dz',131,282,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(3,1,'2024-0003','Nom19','Prenom15','2007-12-02','M','3 rue de Test','0600000003','Prenom15.Nom19@edu.dz',131,283,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(4,1,'2024-0004','Nom13','Prenom35','2007-11-28','F','4 rue de Test','0600000004','Prenom35.Nom13@edu.dz',131,284,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(5,1,'2024-0005','Nom14','Prenom31','2008-07-10','M','5 rue de Test','0600000005','Prenom31.Nom14@edu.dz',3,285,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(6,1,'2024-0006','Nom03','Prenom28','2007-11-29','F','6 rue de Test','0600000006','Prenom28.Nom03@edu.dz',131,6,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(7,1,'2024-0007','Nom07','Prenom41','2008-08-12','M','7 rue de Test','0600000007','Prenom41.Nom07@edu.dz',4,286,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(8,1,'2024-0008','Nom32','Prenom23','2008-04-27','F','8 rue de Test','0600000008','Prenom23.Nom32@edu.dz',3,287,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(9,1,'2024-0009','Nom05','Prenom37','2007-01-23','M','9 rue de Test','0600000009','Prenom37.Nom05@edu.dz',1,288,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(10,1,'2024-0010','Nom23','Prenom39','2008-05-26','F','10 rue de Test','0600000010','Prenom39.Nom23@edu.dz',2,289,NULL,NULL,1,'2026-06-25 09:30:43','2026-10-29 19:04:55'),(109,1,'TESTBUL26-01','Nom17','Prenom04','2008-12-15','F','109 rue de Test','0600000109','testbul1@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(110,1,'TESTBUL26-02','Nom26','Prenom14','2009-02-22','M','110 rue de Test','0600000110','testbul2@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(111,1,'TESTBUL26-03','Nom18','Prenom32','2009-04-29','F','111 rue de Test','0600000111','testbul3@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(112,1,'TESTBUL26-04','Nom21','Prenom38','2009-07-07','M','112 rue de Test','0600000112','testbul4@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(113,1,'TESTBUL26-05','Nom24','Prenom06','2009-09-13','F','113 rue de Test','0600000113','testbul5@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(114,1,'TESTBUL26-06','Nom28','Prenom13','2009-01-25','M','114 rue de Test','0600000114','testbul6@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(115,1,'TESTBUL26-07','Nom02','Prenom08','2009-04-03','F','115 rue de Test','0600000115','testbul7@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(116,1,'TESTBUL26-08','Nom10','Prenom21','2009-06-11','M','116 rue de Test','0600000116','testbul8@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(117,1,'TESTBUL26-09','Nom01','Prenom42','2009-08-19','F','117 rue de Test','0600000117','testbul9@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(118,1,'TESTBUL26-10','Nom30','Prenom18','2009-10-26','M','118 rue de Test','0600000118','testbul10@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(119,1,'TESTBUL26-11','Nom20','Prenom24','2010-01-03','F','119 rue de Test','0600000119','testbul11@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(120,1,'TESTBUL26-12','Nom34','Prenom02','2010-03-12','M','120 rue de Test','0600000120','testbul12@example.test',132,NULL,NULL,NULL,1,'2026-08-14 01:08:13','2026-10-29 19:04:55'),(121,1,'2024-0011','Nom04','Prenom40','2009-09-16','M',NULL,NULL,'eleve@ecole.dz',131,NULL,7,NULL,1,'2026-08-21 11:09:42','2026-10-29 19:04:55');
/*!40000 ALTER TABLE `eleves` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `enseignements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enseignements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `professeur_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ens` (`professeur_id`,`matiere_id`,`classe_id`,`annee_scolaire`),
  KEY `fk_ens_matiere` (`matiere_id`),
  KEY `fk_ens_classe` (`classe_id`),
  KEY `idx_etab` (`etablissement_id`,`classe_id`),
  CONSTRAINT `fk_ens_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ens_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ens_prof` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enseignements_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `enseignements` WRITE;
/*!40000 ALTER TABLE `enseignements` DISABLE KEYS */;
INSERT INTO `enseignements` VALUES (1,1,1,1,1,'2024-2025'),(2,1,2,188,1,'2024-2025'),(3,1,3,3,1,'2024-2025'),(4,1,4,4,1,'2024-2025'),(5,1,5,5,1,'2024-2025'),(6,1,7,6,1,'2024-2025'),(7,1,1,1,2,'2024-2025'),(8,1,2,188,2,'2024-2025'),(9,1,3,3,2,'2024-2025'),(10,1,1,1,3,'2024-2025'),(11,1,4,4,3,'2024-2025'),(12,1,8,8,3,'2024-2025'),(13,1,9,1,4,'2026-2027'),(14,1,9,1,2,'2026-2027'),(15,1,4,4,131,'2025-2026'),(16,1,7,6,131,'2025-2026'),(17,1,5,5,131,'2025-2026'),(18,1,1,1,131,'2025-2026'),(19,1,6,187,131,'2025-2026'),(20,1,2,188,131,'2025-2026'),(21,1,9,189,131,'2025-2026'),(22,1,9,190,131,'2025-2026'),(23,1,1,4,132,'2026-2027'),(24,1,1,1,132,'2026-2027'),(25,1,1,5,132,'2026-2027'),(26,1,1,6,132,'2026-2027'),(27,1,1,187,132,'2026-2027'),(28,1,1,188,132,'2026-2027'),(29,1,1,189,132,'2026-2027'),(30,1,1,190,132,'2026-2027');
/*!40000 ALTER TABLE `enseignements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `etab_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etab_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etabperm_code` (`code`),
  KEY `idx_etabperm_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=301 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `etab_permissions` WRITE;
/*!40000 ALTER TABLE `etab_permissions` DISABLE KEYS */;
INSERT INTO `etab_permissions` VALUES (1,'eleves.view','Eleves view','eleves','view'),(2,'eleves.create','Eleves create','eleves','create'),(3,'eleves.edit','Eleves edit','eleves','edit'),(4,'eleves.delete','Eleves delete','eleves','delete'),(5,'enseignants.view','Enseignants view','enseignants','view'),(6,'enseignants.create','Enseignants create','enseignants','create'),(7,'enseignants.edit','Enseignants edit','enseignants','edit'),(8,'enseignants.delete','Enseignants delete','enseignants','delete'),(9,'classes.view','Classes view','classes','view'),(10,'classes.create','Classes create','classes','create'),(11,'classes.edit','Classes edit','classes','edit'),(12,'classes.delete','Classes delete','classes','delete'),(13,'matieres.view','Matieres view','matieres','view'),(14,'matieres.create','Matieres create','matieres','create'),(15,'matieres.edit','Matieres edit','matieres','edit'),(16,'matieres.delete','Matieres delete','matieres','delete'),(17,'notes.view','Notes view','notes','view'),(18,'notes.create','Notes create','notes','create'),(19,'notes.edit','Notes edit','notes','edit'),(20,'notes.delete','Notes delete','notes','delete'),(21,'notes.view_own','Notes view own','notes','view_own'),(22,'absences.view','Absences view','absences','view'),(23,'absences.create','Absences create','absences','create'),(24,'absences.edit','Absences edit','absences','edit'),(25,'absences.view_own','Absences view own','absences','view_own'),(26,'bulletins.view','Bulletins view','bulletins','view'),(27,'comptabilite.view','Comptabilite view','comptabilite','view'),(28,'comptabilite.create','Comptabilite create','comptabilite','create'),(29,'comptabilite.edit','Comptabilite edit','comptabilite','edit'),(30,'emploi_du_temps.view','Emploi du temps view','emploi_du_temps','view'),(31,'emploi_du_temps.create','Emploi du temps create','emploi_du_temps','create'),(32,'emploi_du_temps.edit','Emploi du temps edit','emploi_du_temps','edit'),(33,'annonces.view','Annonces view','annonces','view'),(34,'annonces.create','Annonces create','annonces','create'),(35,'annonces.edit','Annonces edit','annonces','edit'),(36,'annonces.delete','Annonces delete','annonces','delete'),(37,'notifications.manage','Notifications manage','notifications','manage'),(38,'users.view','Users view','users','view'),(39,'users.create','Users create','users','create'),(40,'users.edit','Users edit','users','edit'),(41,'users.delete','Users delete','users','delete'),(42,'rapports.view','Rapports view','rapports','view'),(43,'finance.dashboard.view','Finance dashboard view','finance','view'),(44,'finance.frais.view','Finance frais view','finance','view'),(45,'finance.frais.manage','Finance frais manage','finance','manage'),(46,'finance.frais.admin','Finance frais admin','finance','admin'),(47,'finance.factures.view','Finance factures view','finance','view'),(48,'finance.factures.create','Finance factures create','finance','create'),(49,'finance.factures.masse','Finance factures masse','finance','masse'),(50,'finance.factures.emettre','Finance factures emettre','finance','emettre'),(51,'finance.factures.annuler','Finance factures annuler','finance','annuler'),(52,'finance.factures.remise','Finance factures remise','finance','remise'),(53,'finance.paiements.view','Finance paiements view','finance','view'),(54,'finance.paiements.create','Finance paiements create','finance','create'),(55,'finance.paiements.annuler','Finance paiements annuler','finance','annuler'),(56,'finance.paiements.trop_percu','Finance paiements trop percu','finance','trop_percu'),(57,'finance.decaissements.view','Finance decaissements view','finance','view'),(58,'finance.decaissements.create','Finance decaissements create','finance','create'),(59,'finance.decaissements.valider','Finance decaissements valider','finance','valider'),(60,'finance.decaissements.approuver','Finance decaissements approuver','finance','approuver'),(61,'finance.decaissements.rejeter','Finance decaissements rejeter','finance','rejeter'),(62,'finance.caisse.view','Finance caisse view','finance','view'),(63,'finance.caisse.ouvrir','Finance caisse ouvrir','finance','ouvrir'),(64,'finance.caisse.fermer','Finance caisse fermer','finance','fermer'),(65,'finance.caisse.rapprocher','Finance caisse rapprocher','finance','rapprocher'),(66,'finance.comptabilite.view','Finance comptabilite view','finance','view'),(67,'finance.comptabilite.saisir','Finance comptabilite saisir','finance','saisir'),(68,'finance.comptabilite.exercice','Finance comptabilite exercice','finance','exercice'),(69,'finance.rapports.view','Finance rapports view','finance','view'),(70,'finance.rapports.export','Finance rapports export','finance','export'),(71,'eleves.view.own','Eleves view own','eleves','own'),(72,'inscriptions.view','Inscriptions view','inscriptions','view'),(73,'inscriptions.create','Inscriptions create','inscriptions','create'),(74,'inscriptions.update','Inscriptions update','inscriptions','update'),(75,'familles.view','Familles view','familles','view'),(76,'familles.manage','Familles manage','familles','manage'),(77,'academique.periodes.view','Academique periodes view','academique','view'),(78,'academique.periodes.manage','Academique periodes manage','academique','manage'),(79,'academique.evaluations.view','Academique evaluations view','academique','view'),(80,'academique.evaluations.manage','Academique evaluations manage','academique','manage'),(81,'academique.notes.view','Academique notes view','academique','view'),(82,'academique.notes.manage','Academique notes manage','academique','manage'),(83,'academique.moyennes.view','Academique moyennes view','academique','view'),(84,'academique.moyennes.recalcul','Academique moyennes recalcul','academique','recalcul'),(85,'academique.classement.view','Academique classement view','academique','view'),(86,'academique.classement.generer','Academique classement generer','academique','generer'),(87,'academique.bulletin.view','Academique bulletin view','academique','view'),(88,'academique.bulletin.generer','Academique bulletin generer','academique','generer'),(89,'academique.bulletin.publier','Academique bulletin publier','academique','publier'),(90,'academique.analytics.view','Academique analytics view','academique','view'),(91,'attendance.view','Attendance view','attendance','view'),(92,'attendance.create','Attendance create','attendance','create'),(93,'attendance.update','Attendance update','attendance','update'),(94,'attendance.delete','Attendance delete','attendance','delete'),(95,'attendance.justify','Attendance justify','attendance','justify'),(96,'attendance.validate','Attendance validate','attendance','validate'),(97,'attendance.session.view','Attendance session view','attendance','view'),(98,'attendance.session.create','Attendance session create','attendance','create'),(99,'attendance.session.update','Attendance session update','attendance','update'),(100,'attendance.session.validate','Attendance session validate','attendance','validate'),(101,'late.view','Late view','late','view'),(102,'late.create','Late create','late','create'),(103,'late.update','Late update','late','update'),(104,'late.justify','Late justify','late','justify'),(105,'late.validate','Late validate','late','validate'),(106,'late.export','Late export','late','export'),(107,'discipline.view','Discipline view','discipline','view'),(108,'discipline.create','Discipline create','discipline','create'),(109,'discipline.update','Discipline update','discipline','update'),(110,'discipline.validate','Discipline validate','discipline','validate'),(111,'discipline.sanction','Discipline sanction','discipline','sanction'),(112,'discipline.export','Discipline export','discipline','export'),(113,'reward.view','Reward view','reward','view'),(114,'reward.create','Reward create','reward','create'),(115,'reward.update','Reward update','reward','update'),(116,'reward.validate','Reward validate','reward','validate'),(117,'reward.export','Reward export','reward','export'),(118,'timetable.view','Timetable view','timetable','view'),(119,'timetable.create','Timetable create','timetable','create'),(120,'timetable.update','Timetable update','timetable','update'),(121,'timetable.publish','Timetable publish','timetable','publish'),(122,'timetable.export','Timetable export','timetable','export'),(123,'activity.view','Activity view','activity','view'),(124,'activity.create','Activity create','activity','create'),(125,'activity.update','Activity update','activity','update'),(126,'activity.validate','Activity validate','activity','validate'),(127,'activity.publish','Activity publish','activity','publish'),(128,'activity.export','Activity export','activity','export'),(129,'employee.view','Employee view','employee','view'),(130,'employee.create','Employee create','employee','create'),(131,'employee.update','Employee update','employee','update'),(132,'employee.archive','Employee archive','employee','archive'),(133,'employee.restore','Employee restore','employee','restore'),(134,'employee.export','Employee export','employee','export'),(135,'teacher.view','Teacher view','teacher','view'),(136,'teacher.create','Teacher create','teacher','create'),(137,'teacher.update','Teacher update','teacher','update'),(138,'teacher.assign','Teacher assign','teacher','assign'),(139,'teacher.export','Teacher export','teacher','export'),(140,'organization.view','Organization view','organization','view'),(141,'organization.create','Organization create','organization','create'),(142,'organization.update','Organization update','organization','update'),(143,'organization.archive','Organization archive','organization','archive'),(144,'organization.export','Organization export','organization','export'),(145,'contract.view','Contract view','contract','view'),(146,'contract.create','Contract create','contract','create'),(147,'contract.update','Contract update','contract','update'),(148,'contract.renew','Contract renew','contract','renew'),(149,'contract.terminate','Contract terminate','contract','terminate'),(150,'contract.archive','Contract archive','contract','archive'),(151,'contract.export','Contract export','contract','export'),(152,'assignment.view','Assignment view','assignment','view'),(153,'assignment.create','Assignment create','assignment','create'),(154,'assignment.update','Assignment update','assignment','update'),(155,'assignment.archive','Assignment archive','assignment','archive'),(156,'assignment.export','Assignment export','assignment','export'),(157,'rh.presence.view','Rh presence view','rh','view'),(158,'rh.presence.create','Rh presence create','rh','create'),(159,'rh.presence.update','Rh presence update','rh','update'),(160,'rh.presence.validate','Rh presence validate','rh','validate'),(161,'rh.presence.export','Rh presence export','rh','export'),(162,'leave.view','Leave view','leave','view'),(163,'leave.create','Leave create','leave','create'),(164,'leave.update','Leave update','leave','update'),(165,'leave.approve','Leave approve','leave','approve'),(166,'leave.reject','Leave reject','leave','reject'),(167,'leave.cancel','Leave cancel','leave','cancel'),(168,'leave.export','Leave export','leave','export'),(169,'evaluation.view','Evaluation view','evaluation','view'),(170,'evaluation.create','Evaluation create','evaluation','create'),(171,'evaluation.update','Evaluation update','evaluation','update'),(172,'evaluation.validate','Evaluation validate','evaluation','validate'),(173,'evaluation.publish','Evaluation publish','evaluation','publish'),(174,'evaluation.export','Evaluation export','evaluation','export'),(175,'training.view','Training view','training','view'),(176,'training.create','Training create','training','create'),(177,'training.update','Training update','training','update'),(178,'training.enroll','Training enroll','training','enroll'),(179,'training.validate','Training validate','training','validate'),(180,'training.export','Training export','training','export'),(181,'training.manage_catalog','Training manage catalog','training','manage_catalog'),(182,'hr_document.view','Hr document view','hr_document','view'),(183,'hr_document.create','Hr document create','hr_document','create'),(184,'hr_document.update','Hr document update','hr_document','update'),(185,'hr_document.archive','Hr document archive','hr_document','archive'),(186,'hr_document.export','Hr document export','hr_document','export'),(187,'document.view','Document view','document','view'),(188,'document.view_all','Document view all','document','view_all'),(189,'document.view_secret','Document view secret','document','view_secret'),(190,'document.create','Document create','document','create'),(191,'document.update','Document update','document','update'),(192,'document.version','Document version','document','version'),(193,'document.archive','Document archive','document','archive'),(194,'document.delete','Document delete','document','delete'),(195,'document.restore','Document restore','document','restore'),(196,'document.share','Document share','document','share'),(197,'document.export','Document export','document','export'),(198,'document.sign','Document sign','document','sign'),(199,'document.admin','Document admin','document','admin'),(200,'folder.create','Folder create','folder','create'),(201,'folder.delete','Folder delete','folder','delete'),(202,'communication.view','Communication view','communication','view'),(203,'communication.send','Communication send','communication','send'),(204,'communication.broadcast','Communication broadcast','communication','broadcast'),(205,'communication.manage_templates','Communication manage templates','communication','manage_templates'),(206,'communication.manage_groups','Communication manage groups','communication','manage_groups'),(207,'communication.campaign','Communication campaign','communication','campaign'),(208,'communication.admin','Communication admin','communication','admin'),(209,'communication.view_all','Communication view all','communication','view_all'),(210,'biblio.view','Biblio view','biblio','view'),(211,'biblio.search','Biblio search','biblio','search'),(212,'biblio.borrow','Biblio borrow','biblio','borrow'),(213,'biblio.reserve','Biblio reserve','biblio','reserve'),(214,'biblio.manage_loans','Biblio manage loans','biblio','manage_loans'),(215,'biblio.manage_catalogue','Biblio manage catalogue','biblio','manage_catalogue'),(216,'biblio.manage_exemplaires','Biblio manage exemplaires','biblio','manage_exemplaires'),(217,'biblio.manage_penalties','Biblio manage penalties','biblio','manage_penalties'),(218,'biblio.inventory','Biblio inventory','biblio','inventory'),(219,'biblio.analytics','Biblio analytics','biblio','analytics'),(220,'biblio.admin','Biblio admin','biblio','admin'),(221,'inventaire.view','Inventaire view','inventaire','view'),(222,'inventaire.create','Inventaire create','inventaire','create'),(223,'inventaire.edit','Inventaire edit','inventaire','edit'),(224,'inventaire.delete','Inventaire delete','inventaire','delete'),(225,'inventaire.stock.view','Inventaire stock view','inventaire','view'),(226,'inventaire.stock.manage','Inventaire stock manage','inventaire','manage'),(227,'inventaire.commande.view','Inventaire commande view','inventaire','view'),(228,'inventaire.commande.create','Inventaire commande create','inventaire','create'),(229,'inventaire.commande.validate','Inventaire commande validate','inventaire','validate'),(230,'inventaire.commande.receive','Inventaire commande receive','inventaire','receive'),(231,'inventaire.affectation.view','Inventaire affectation view','inventaire','view'),(232,'inventaire.affectation.create','Inventaire affectation create','inventaire','create'),(233,'inventaire.affectation.return','Inventaire affectation return','inventaire','return'),(234,'inventaire.affectation.lost','Inventaire affectation lost','inventaire','lost'),(235,'inventaire.maintenance.view','Inventaire maintenance view','inventaire','view'),(236,'inventaire.maintenance.create','Inventaire maintenance create','inventaire','create'),(237,'inventaire.maintenance.edit','Inventaire maintenance edit','inventaire','edit'),(238,'inventaire.physique.view','Inventaire physique view','inventaire','view'),(239,'inventaire.physique.create','Inventaire physique create','inventaire','create'),(240,'inventaire.physique.count','Inventaire physique count','inventaire','count'),(241,'inventaire.physique.close','Inventaire physique close','inventaire','close'),(242,'inventaire.reports','Inventaire reports','inventaire','reports'),(243,'portal.admin.access','Portal admin access','portal','access'),(244,'portal.direction.access','Portal direction access','portal','access'),(245,'portal.enseignant.access','Portal enseignant access','portal','access'),(246,'portal.eleve.access','Portal eleve access','portal','access'),(247,'portal.parent.access','Portal parent access','portal','access'),(248,'portal.comptabilite.access','Portal comptabilite access','portal','access'),(249,'portal.rh.access','Portal rh access','portal','access'),(250,'rapports.dashboard.direction','Rapports dashboard direction','rapports','direction'),(251,'rapports.dashboard.administration','Rapports dashboard administration','rapports','administration'),(252,'rapports.dashboard.scolarite','Rapports dashboard scolarite','rapports','scolarite'),(253,'rapports.dashboard.academique','Rapports dashboard academique','rapports','academique'),(254,'rapports.dashboard.finance','Rapports dashboard finance','rapports','finance'),(255,'rapports.dashboard.rh','Rapports dashboard rh','rapports','rh'),(256,'rapports.dashboard.vie_scolaire','Rapports dashboard vie scolaire','rapports','vie_scolaire'),(257,'rapports.dashboard.bibliotheque','Rapports dashboard bibliotheque','rapports','bibliotheque'),(258,'rapports.dashboard.inventaire','Rapports dashboard inventaire','rapports','inventaire'),(259,'rapports.kpis.voir','Rapports kpis voir','rapports','voir'),(260,'rapports.exporter','Rapports exporter','rapports','exporter'),(261,'rapports.planifier','Rapports planifier','rapports','planifier'),(262,'rapports.api.analytics','Rapports api analytics','rapports','analytics'),(263,'emploi_du_temps.view_own','Emploi du temps view own','emploi_du_temps','view_own'),(264,'employee.view.own','Employee view own','employee','own'),(265,'absences.justify','Absences justify','absences','justify'),(266,'comptabilite.view_own','Comptabilite view own','comptabilite','view_own'),(267,'finance.paiements.view.own','Finance paiements view own','finance','own'),(268,'branding.view','Voir les paramètres de branding','branding','view'),(269,'branding.update','Modifier les paramètres de branding','branding','update'),(270,'domains.view','Voir les domaines personnalisés','domains','view'),(271,'domains.manage','Gérer les domaines personnalisés','domains','manage'),(272,'quota.view','Voir les quotas et la consommation de stockage','quota','view'),(273,'monitoring.view','Voir le monitoring cache/files d\'attente','monitoring','view'),(274,'settings.general.view','Voir la configuration générale','settings','view'),(275,'settings.general.update','Modifier la configuration générale','settings','update'),(276,'settings.academique.view','Voir l\'organisation académique','settings','view'),(277,'settings.academique.update','Modifier l\'organisation académique','settings','update'),(278,'settings.notation.view','Voir le système de notation','settings','view'),(279,'settings.notation.update','Modifier le système de notation','settings','update'),(280,'settings.finances.view','Voir la configuration financière','settings','view'),(281,'settings.finances.update','Modifier la configuration financière','settings','update'),(282,'settings.documents.view','Voir la configuration des documents','settings','view'),(283,'settings.documents.update','Modifier la configuration des documents','settings','update'),(284,'settings.notifications.view','Voir la configuration des notifications','settings','view'),(285,'settings.notifications.update','Modifier la configuration des notifications','settings','update'),(286,'settings.securite.view','Voir les paramètres de sécurité','settings','view'),(287,'settings.securite.update','Modifier les paramètres de sécurité','settings','update'),(288,'settings.sauvegarde.view','Voir les préférences de sauvegarde','settings','view'),(289,'settings.sauvegarde.update','Modifier les préférences de sauvegarde','settings','update'),(290,'settings.avance.view','Voir les paramètres avancés','settings','view'),(291,'settings.avance.update','Modifier les paramètres avancés','settings','update'),(292,'academique.conduite.manage','Gérer la Conduite (évaluations, notes, appréciations)','academique','manage'),(293,'academique.bulletin.admin','Administrer les bulletins (générer, publier, archiver, appréciation du directeur)','academique','admin'),(294,'academique.evaluations.admin','Administrer les évaluations','academique','admin'),(295,'academique.notes.admin','Administrer les notes','academique','admin'),(296,'academique.periodes.admin','Administrer les périodes (réouverture, modèle par défaut, déverrouillage)','academique','admin'),(297,'academique.types_evaluations.view','Consulter les types d\'évaluation','academique','view'),(298,'academique.types_evaluations.manage','Gérer les types d\'évaluation','academique','manage');
/*!40000 ALTER TABLE `etab_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `etab_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etab_role_permissions` (
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `granted_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_etabrp_perm` (`permission_id`),
  KEY `fk_etabrp_granter` (`granted_by`),
  CONSTRAINT `fk_etabrp_granter` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_etabrp_perm` FOREIGN KEY (`permission_id`) REFERENCES `etab_permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_etabrp_role` FOREIGN KEY (`role_id`) REFERENCES `etab_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `etab_role_permissions` WRITE;
/*!40000 ALTER TABLE `etab_role_permissions` DISABLE KEYS */;
INSERT INTO `etab_role_permissions` VALUES (1,1,'2026-07-08 10:51:05',NULL),(1,2,'2026-07-08 10:51:06',NULL),(1,3,'2026-07-08 10:51:06',NULL),(1,4,'2026-07-08 10:51:06',NULL),(1,5,'2026-07-08 10:51:06',NULL),(1,6,'2026-07-08 10:51:06',NULL),(1,7,'2026-07-08 10:51:06',NULL),(1,8,'2026-07-08 10:51:06',NULL),(1,9,'2026-07-08 10:51:06',NULL),(1,10,'2026-07-08 10:51:06',NULL),(1,11,'2026-07-08 10:51:06',NULL),(1,12,'2026-07-08 10:51:06',NULL),(1,13,'2026-07-08 10:51:06',NULL),(1,14,'2026-07-08 10:51:06',NULL),(1,15,'2026-07-08 10:51:06',NULL),(1,16,'2026-07-08 10:51:06',NULL),(1,17,'2026-07-08 10:51:06',NULL),(1,18,'2026-07-08 10:51:06',NULL),(1,19,'2026-07-08 10:51:06',NULL),(1,20,'2026-07-08 10:51:06',NULL),(1,21,'2026-07-08 10:51:06',NULL),(1,22,'2026-07-08 10:51:06',NULL),(1,23,'2026-07-08 10:51:06',NULL),(1,24,'2026-07-08 10:51:06',NULL),(1,25,'2026-07-08 10:51:06',NULL),(1,26,'2026-07-08 10:51:06',NULL),(1,27,'2026-07-08 10:51:06',NULL),(1,28,'2026-07-08 10:51:06',NULL),(1,29,'2026-07-08 10:51:06',NULL),(1,30,'2026-07-08 10:51:06',NULL),(1,31,'2026-07-08 10:51:06',NULL),(1,32,'2026-07-08 10:51:06',NULL),(1,33,'2026-07-08 10:51:06',NULL),(1,34,'2026-07-08 10:51:06',NULL),(1,35,'2026-07-08 10:51:06',NULL),(1,36,'2026-07-08 10:51:06',NULL),(1,37,'2026-07-08 10:51:06',NULL),(1,38,'2026-07-08 10:51:06',NULL),(1,39,'2026-07-08 10:51:06',NULL),(1,40,'2026-07-08 10:51:06',NULL),(1,41,'2026-07-08 10:51:06',NULL),(1,42,'2026-07-08 10:51:06',NULL),(1,43,'2026-07-08 10:51:06',NULL),(1,44,'2026-07-08 10:51:06',NULL),(1,45,'2026-07-08 10:51:06',NULL),(1,46,'2026-07-08 10:51:06',NULL),(1,47,'2026-07-08 10:51:06',NULL),(1,48,'2026-07-08 10:51:06',NULL),(1,49,'2026-07-08 10:51:06',NULL),(1,50,'2026-07-08 10:51:06',NULL),(1,51,'2026-07-08 10:51:06',NULL),(1,52,'2026-07-08 10:51:06',NULL),(1,53,'2026-07-08 10:51:06',NULL),(1,54,'2026-07-08 10:51:06',NULL),(1,55,'2026-07-08 10:51:06',NULL),(1,56,'2026-07-08 10:51:06',NULL),(1,57,'2026-07-08 10:51:06',NULL),(1,58,'2026-07-08 10:51:06',NULL),(1,59,'2026-07-08 10:51:06',NULL),(1,60,'2026-07-08 10:51:06',NULL),(1,61,'2026-07-08 10:51:06',NULL),(1,62,'2026-07-08 10:51:06',NULL),(1,63,'2026-07-08 10:51:06',NULL),(1,64,'2026-07-08 10:51:06',NULL),(1,65,'2026-07-08 10:51:06',NULL),(1,66,'2026-07-08 10:51:06',NULL),(1,67,'2026-07-08 10:51:06',NULL),(1,68,'2026-07-08 10:51:06',NULL),(1,69,'2026-07-08 10:51:06',NULL),(1,70,'2026-07-08 10:51:06',NULL),(1,71,'2026-07-08 10:51:06',NULL),(1,72,'2026-07-08 10:51:06',NULL),(1,73,'2026-07-08 10:51:07',NULL),(1,74,'2026-07-08 10:51:07',NULL),(1,75,'2026-07-08 10:51:07',NULL),(1,76,'2026-07-08 10:51:07',NULL),(1,77,'2026-07-08 10:51:07',NULL),(1,78,'2026-07-08 10:51:07',NULL),(1,79,'2026-07-08 10:51:07',NULL),(1,80,'2026-07-08 10:51:07',NULL),(1,81,'2026-07-08 10:51:07',NULL),(1,82,'2026-07-08 10:51:07',NULL),(1,83,'2026-07-08 10:51:07',NULL),(1,84,'2026-07-08 10:51:07',NULL),(1,85,'2026-07-08 10:51:07',NULL),(1,86,'2026-07-08 10:51:07',NULL),(1,87,'2026-07-08 10:51:07',NULL),(1,88,'2026-07-08 10:51:07',NULL),(1,89,'2026-07-08 10:51:07',NULL),(1,90,'2026-07-08 10:51:07',NULL),(1,91,'2026-07-08 10:51:07',NULL),(1,92,'2026-07-08 10:51:07',NULL),(1,93,'2026-07-08 10:51:07',NULL),(1,94,'2026-07-08 10:51:07',NULL),(1,95,'2026-07-08 10:51:07',NULL),(1,96,'2026-07-08 10:51:07',NULL),(1,97,'2026-07-08 10:51:07',NULL),(1,98,'2026-07-08 10:51:07',NULL),(1,99,'2026-07-08 10:51:07',NULL),(1,100,'2026-07-08 10:51:07',NULL),(1,101,'2026-07-08 10:51:07',NULL),(1,102,'2026-07-08 10:51:07',NULL),(1,103,'2026-07-08 10:51:07',NULL),(1,104,'2026-07-08 10:51:07',NULL),(1,105,'2026-07-08 10:51:07',NULL),(1,106,'2026-07-08 10:51:07',NULL),(1,107,'2026-07-08 10:51:07',NULL),(1,108,'2026-07-08 10:51:07',NULL),(1,109,'2026-07-08 10:51:07',NULL),(1,110,'2026-07-08 10:51:07',NULL),(1,111,'2026-07-08 10:51:07',NULL),(1,112,'2026-07-08 10:51:07',NULL),(1,113,'2026-07-08 10:51:07',NULL),(1,114,'2026-07-08 10:51:07',NULL),(1,115,'2026-07-08 10:51:07',NULL),(1,116,'2026-07-08 10:51:07',NULL),(1,117,'2026-07-08 10:51:07',NULL),(1,118,'2026-07-08 10:51:07',NULL),(1,119,'2026-07-08 10:51:07',NULL),(1,120,'2026-07-08 10:51:07',NULL),(1,121,'2026-07-08 10:51:07',NULL),(1,122,'2026-07-08 10:51:07',NULL),(1,123,'2026-07-08 10:51:07',NULL),(1,124,'2026-07-08 10:51:08',NULL),(1,125,'2026-07-08 10:51:08',NULL),(1,126,'2026-07-08 10:51:08',NULL),(1,127,'2026-07-08 10:51:08',NULL),(1,128,'2026-07-08 10:51:08',NULL),(1,129,'2026-07-08 10:51:08',NULL),(1,130,'2026-07-08 10:51:08',NULL),(1,131,'2026-07-08 10:51:08',NULL),(1,132,'2026-07-08 10:51:08',NULL),(1,133,'2026-07-08 10:51:08',NULL),(1,134,'2026-07-08 10:51:08',NULL),(1,135,'2026-07-08 10:51:08',NULL),(1,136,'2026-07-08 10:51:08',NULL),(1,137,'2026-07-08 10:51:08',NULL),(1,138,'2026-07-08 10:51:08',NULL),(1,139,'2026-07-08 10:51:08',NULL),(1,140,'2026-07-08 10:51:08',NULL),(1,141,'2026-07-08 10:51:08',NULL),(1,142,'2026-07-08 10:51:08',NULL),(1,143,'2026-07-08 10:51:08',NULL),(1,144,'2026-07-08 10:51:08',NULL),(1,145,'2026-07-08 10:51:08',NULL),(1,146,'2026-07-08 10:51:08',NULL),(1,147,'2026-07-08 10:51:08',NULL),(1,148,'2026-07-08 10:51:08',NULL),(1,149,'2026-07-08 10:51:08',NULL),(1,150,'2026-07-08 10:51:08',NULL),(1,151,'2026-07-08 10:51:08',NULL),(1,152,'2026-07-08 10:51:08',NULL),(1,153,'2026-07-08 10:51:08',NULL),(1,154,'2026-07-08 10:51:08',NULL),(1,155,'2026-07-08 10:51:08',NULL),(1,156,'2026-07-08 10:51:08',NULL),(1,157,'2026-07-08 10:51:08',NULL),(1,158,'2026-07-08 10:51:08',NULL),(1,159,'2026-07-08 10:51:08',NULL),(1,160,'2026-07-08 10:51:08',NULL),(1,161,'2026-07-08 10:51:08',NULL),(1,162,'2026-07-08 10:51:08',NULL),(1,163,'2026-07-08 10:51:08',NULL),(1,164,'2026-07-08 10:51:08',NULL),(1,165,'2026-07-08 10:51:08',NULL),(1,166,'2026-07-08 10:51:08',NULL),(1,167,'2026-07-08 10:51:08',NULL),(1,168,'2026-07-08 10:51:08',NULL),(1,169,'2026-07-08 10:51:08',NULL),(1,170,'2026-07-08 10:51:08',NULL),(1,171,'2026-07-08 10:51:08',NULL),(1,172,'2026-07-08 10:51:08',NULL),(1,173,'2026-07-08 10:51:08',NULL),(1,174,'2026-07-08 10:51:08',NULL),(1,175,'2026-07-08 10:51:08',NULL),(1,176,'2026-07-08 10:51:08',NULL),(1,177,'2026-07-08 10:51:08',NULL),(1,178,'2026-07-08 10:51:08',NULL),(1,179,'2026-07-08 10:51:08',NULL),(1,180,'2026-07-08 10:51:08',NULL),(1,181,'2026-07-08 10:51:08',NULL),(1,182,'2026-07-08 10:51:08',NULL),(1,183,'2026-07-08 10:51:08',NULL),(1,184,'2026-07-08 10:51:08',NULL),(1,185,'2026-07-08 10:51:08',NULL),(1,186,'2026-07-08 10:51:08',NULL),(1,187,'2026-07-08 10:51:08',NULL),(1,188,'2026-07-08 10:51:08',NULL),(1,189,'2026-07-08 10:51:09',NULL),(1,190,'2026-07-08 10:51:09',NULL),(1,191,'2026-07-08 10:51:09',NULL),(1,192,'2026-07-08 10:51:09',NULL),(1,193,'2026-07-08 10:51:09',NULL),(1,194,'2026-07-08 10:51:09',NULL),(1,195,'2026-07-08 10:51:09',NULL),(1,196,'2026-07-08 10:51:09',NULL),(1,197,'2026-07-08 10:51:09',NULL),(1,198,'2026-07-08 10:51:09',NULL),(1,199,'2026-07-08 10:51:09',NULL),(1,200,'2026-07-08 10:51:09',NULL),(1,201,'2026-07-08 10:51:09',NULL),(1,202,'2026-07-08 10:51:09',NULL),(1,203,'2026-07-08 10:51:09',NULL),(1,204,'2026-07-08 10:51:09',NULL),(1,205,'2026-07-08 10:51:09',NULL),(1,206,'2026-07-08 10:51:09',NULL),(1,207,'2026-07-08 10:51:09',NULL),(1,208,'2026-07-08 10:51:09',NULL),(1,209,'2026-07-08 10:51:09',NULL),(1,210,'2026-07-08 10:51:09',NULL),(1,211,'2026-07-08 10:51:09',NULL),(1,212,'2026-07-08 10:51:09',NULL),(1,213,'2026-07-08 10:51:09',NULL),(1,214,'2026-07-08 10:51:09',NULL),(1,215,'2026-07-08 10:51:09',NULL),(1,216,'2026-07-08 10:51:09',NULL),(1,217,'2026-07-08 10:51:09',NULL),(1,218,'2026-07-08 10:51:09',NULL),(1,219,'2026-07-08 10:51:09',NULL),(1,220,'2026-07-08 10:51:09',NULL),(1,221,'2026-07-08 10:51:09',NULL),(1,222,'2026-07-08 10:51:09',NULL),(1,223,'2026-07-08 10:51:09',NULL),(1,224,'2026-07-08 10:51:09',NULL),(1,225,'2026-07-08 10:51:09',NULL),(1,226,'2026-07-08 10:51:09',NULL),(1,227,'2026-07-08 10:51:09',NULL),(1,228,'2026-07-08 10:51:09',NULL),(1,229,'2026-07-08 10:51:09',NULL),(1,230,'2026-07-08 10:51:09',NULL),(1,231,'2026-07-08 10:51:09',NULL),(1,232,'2026-07-08 10:51:09',NULL),(1,233,'2026-07-08 10:51:09',NULL),(1,234,'2026-07-08 10:51:09',NULL),(1,235,'2026-07-08 10:51:09',NULL),(1,236,'2026-07-08 10:51:09',NULL),(1,237,'2026-07-08 10:51:09',NULL),(1,238,'2026-07-08 10:51:09',NULL),(1,239,'2026-07-08 10:51:09',NULL),(1,240,'2026-07-08 10:51:09',NULL),(1,241,'2026-07-08 10:51:09',NULL),(1,242,'2026-07-08 10:51:09',NULL),(1,243,'2026-07-08 10:51:09',NULL),(1,244,'2026-07-08 10:51:09',NULL),(1,245,'2026-07-08 10:51:09',NULL),(1,246,'2026-07-08 10:51:09',NULL),(1,247,'2026-07-08 10:51:09',NULL),(1,248,'2026-07-08 10:51:09',NULL),(1,249,'2026-07-08 10:51:09',NULL),(1,268,'2026-07-08 12:12:43',NULL),(1,269,'2026-07-08 12:12:43',NULL),(1,274,'2026-07-09 21:51:35',NULL),(1,275,'2026-07-09 21:51:35',NULL),(1,276,'2026-07-10 00:22:18',NULL),(1,277,'2026-07-10 00:22:18',NULL),(1,278,'2026-07-10 00:22:18',NULL),(1,279,'2026-07-10 00:22:18',NULL),(1,280,'2026-07-10 00:22:18',NULL),(1,281,'2026-07-10 00:22:18',NULL),(1,282,'2026-07-10 00:22:18',NULL),(1,283,'2026-07-10 00:22:18',NULL),(1,284,'2026-07-10 00:22:18',NULL),(1,285,'2026-07-10 00:22:18',NULL),(1,286,'2026-07-10 00:22:18',NULL),(1,287,'2026-07-10 00:22:18',NULL),(1,288,'2026-07-10 00:22:18',NULL),(1,289,'2026-07-10 00:22:18',NULL),(1,290,'2026-07-10 00:22:18',NULL),(1,291,'2026-07-10 00:22:18',NULL),(1,292,'2026-10-25 18:01:09',NULL),(1,293,'2026-10-26 09:09:23',NULL),(1,294,'2026-10-26 09:09:23',NULL),(1,295,'2026-10-26 09:09:23',NULL),(1,296,'2026-10-26 09:09:23',NULL),(1,297,'2026-10-26 09:09:23',NULL),(1,298,'2026-10-26 09:09:23',NULL),(2,1,'2026-07-08 10:51:09',NULL),(2,2,'2026-07-08 10:51:09',NULL),(2,3,'2026-07-08 10:51:09',NULL),(2,4,'2026-07-08 10:51:09',NULL),(2,5,'2026-07-08 10:51:09',NULL),(2,6,'2026-07-08 10:51:09',NULL),(2,7,'2026-07-08 10:51:09',NULL),(2,8,'2026-07-08 10:51:09',NULL),(2,9,'2026-07-08 10:51:09',NULL),(2,10,'2026-07-08 10:51:09',NULL),(2,11,'2026-07-08 10:51:09',NULL),(2,12,'2026-07-08 10:51:09',NULL),(2,13,'2026-07-08 10:51:09',NULL),(2,14,'2026-07-08 10:51:09',NULL),(2,15,'2026-07-08 10:51:09',NULL),(2,16,'2026-07-08 10:51:09',NULL),(2,17,'2026-07-08 10:51:09',NULL),(2,18,'2026-07-08 10:51:09',NULL),(2,19,'2026-07-08 10:51:09',NULL),(2,20,'2026-07-08 10:51:09',NULL),(2,22,'2026-07-08 10:51:09',NULL),(2,23,'2026-07-08 10:51:09',NULL),(2,24,'2026-07-08 10:51:09',NULL),(2,26,'2026-07-08 10:51:09',NULL),(2,27,'2026-07-08 10:51:09',NULL),(2,28,'2026-07-08 10:51:09',NULL),(2,29,'2026-07-08 10:51:09',NULL),(2,30,'2026-07-08 10:51:09',NULL),(2,31,'2026-07-08 10:51:09',NULL),(2,32,'2026-07-08 10:51:09',NULL),(2,33,'2026-07-08 10:51:09',NULL),(2,34,'2026-07-08 10:51:09',NULL),(2,35,'2026-07-08 10:51:09',NULL),(2,36,'2026-07-08 10:51:09',NULL),(2,37,'2026-07-08 10:51:09',NULL),(2,38,'2026-07-08 10:51:09',NULL),(2,39,'2026-07-08 10:51:09',NULL),(2,40,'2026-07-08 10:51:09',NULL),(2,42,'2026-07-08 10:51:09',NULL),(2,43,'2026-07-08 10:51:09',NULL),(2,44,'2026-07-08 10:51:09',NULL),(2,45,'2026-07-08 10:51:09',NULL),(2,46,'2026-07-08 10:51:09',NULL),(2,47,'2026-07-08 10:51:09',NULL),(2,48,'2026-07-08 10:51:09',NULL),(2,49,'2026-07-08 10:51:09',NULL),(2,50,'2026-07-08 10:51:09',NULL),(2,51,'2026-07-08 10:51:09',NULL),(2,52,'2026-07-08 10:51:09',NULL),(2,53,'2026-07-08 10:51:09',NULL),(2,54,'2026-07-08 10:51:09',NULL),(2,55,'2026-07-08 10:51:09',NULL),(2,56,'2026-07-08 10:51:09',NULL),(2,57,'2026-07-08 10:51:09',NULL),(2,58,'2026-07-08 10:51:10',NULL),(2,59,'2026-07-08 10:51:10',NULL),(2,60,'2026-07-08 10:51:10',NULL),(2,61,'2026-07-08 10:51:10',NULL),(2,62,'2026-07-08 10:51:10',NULL),(2,63,'2026-07-08 10:51:10',NULL),(2,64,'2026-07-08 10:51:10',NULL),(2,65,'2026-07-08 10:51:10',NULL),(2,66,'2026-07-08 10:51:10',NULL),(2,68,'2026-07-08 10:51:10',NULL),(2,69,'2026-07-08 10:51:10',NULL),(2,70,'2026-07-08 10:51:10',NULL),(2,77,'2026-07-08 10:51:10',NULL),(2,78,'2026-07-08 10:51:10',NULL),(2,79,'2026-07-08 10:51:10',NULL),(2,80,'2026-07-08 10:51:10',NULL),(2,81,'2026-07-08 10:51:10',NULL),(2,82,'2026-07-08 10:51:10',NULL),(2,83,'2026-07-08 10:51:10',NULL),(2,84,'2026-07-08 10:51:10',NULL),(2,85,'2026-07-08 10:51:10',NULL),(2,86,'2026-07-08 10:51:10',NULL),(2,87,'2026-07-08 10:51:10',NULL),(2,88,'2026-07-08 10:51:10',NULL),(2,89,'2026-07-08 10:51:10',NULL),(2,90,'2026-07-08 10:51:10',NULL),(2,91,'2026-07-08 10:51:10',NULL),(2,92,'2026-07-08 10:51:10',NULL),(2,93,'2026-07-08 10:51:10',NULL),(2,94,'2026-07-08 10:51:10',NULL),(2,95,'2026-07-08 10:51:10',NULL),(2,96,'2026-07-08 10:51:10',NULL),(2,97,'2026-07-08 10:51:10',NULL),(2,98,'2026-07-08 10:51:10',NULL),(2,99,'2026-07-08 10:51:10',NULL),(2,100,'2026-07-08 10:51:10',NULL),(2,101,'2026-07-08 10:51:10',NULL),(2,102,'2026-07-08 10:51:10',NULL),(2,103,'2026-07-08 10:51:10',NULL),(2,104,'2026-07-08 10:51:10',NULL),(2,105,'2026-07-08 10:51:10',NULL),(2,106,'2026-07-08 10:51:10',NULL),(2,107,'2026-07-08 10:51:10',NULL),(2,108,'2026-07-08 10:51:10',NULL),(2,109,'2026-07-08 10:51:10',NULL),(2,110,'2026-07-08 10:51:10',NULL),(2,111,'2026-07-08 10:51:10',NULL),(2,112,'2026-07-08 10:51:10',NULL),(2,113,'2026-07-08 10:51:10',NULL),(2,114,'2026-07-08 10:51:10',NULL),(2,115,'2026-07-08 10:51:10',NULL),(2,116,'2026-07-08 10:51:10',NULL),(2,117,'2026-07-08 10:51:10',NULL),(2,118,'2026-07-08 10:51:10',NULL),(2,119,'2026-07-08 10:51:10',NULL),(2,120,'2026-07-08 10:51:10',NULL),(2,121,'2026-07-08 10:51:10',NULL),(2,122,'2026-07-08 10:51:10',NULL),(2,123,'2026-07-08 10:51:10',NULL),(2,124,'2026-07-08 10:51:10',NULL),(2,125,'2026-07-08 10:51:10',NULL),(2,126,'2026-07-08 10:51:10',NULL),(2,127,'2026-07-08 10:51:10',NULL),(2,128,'2026-07-08 10:51:10',NULL),(2,129,'2026-07-08 10:51:10',NULL),(2,130,'2026-07-08 10:51:10',NULL),(2,131,'2026-07-08 10:51:10',NULL),(2,132,'2026-07-08 10:51:10',NULL),(2,133,'2026-07-08 10:51:10',NULL),(2,134,'2026-07-08 10:51:10',NULL),(2,135,'2026-07-08 10:51:10',NULL),(2,136,'2026-07-08 10:51:10',NULL),(2,137,'2026-07-08 10:51:10',NULL),(2,138,'2026-07-08 10:51:10',NULL),(2,139,'2026-07-08 10:51:10',NULL),(2,140,'2026-07-08 10:51:10',NULL),(2,141,'2026-07-08 10:51:10',NULL),(2,142,'2026-07-08 10:51:10',NULL),(2,143,'2026-07-08 10:51:10',NULL),(2,144,'2026-07-08 10:51:10',NULL),(2,145,'2026-07-08 10:51:10',NULL),(2,146,'2026-07-08 10:51:10',NULL),(2,147,'2026-07-08 10:51:10',NULL),(2,148,'2026-07-08 10:51:10',NULL),(2,149,'2026-07-08 10:51:11',NULL),(2,150,'2026-07-08 10:51:11',NULL),(2,151,'2026-07-08 10:51:11',NULL),(2,152,'2026-07-08 10:51:11',NULL),(2,153,'2026-07-08 10:51:11',NULL),(2,154,'2026-07-08 10:51:11',NULL),(2,155,'2026-07-08 10:51:11',NULL),(2,156,'2026-07-08 10:51:11',NULL),(2,157,'2026-07-08 10:51:11',NULL),(2,158,'2026-07-08 10:51:11',NULL),(2,159,'2026-07-08 10:51:11',NULL),(2,160,'2026-07-08 10:51:11',NULL),(2,161,'2026-07-08 10:51:11',NULL),(2,162,'2026-07-08 10:51:11',NULL),(2,163,'2026-07-08 10:51:11',NULL),(2,164,'2026-07-08 10:51:11',NULL),(2,165,'2026-07-08 10:51:11',NULL),(2,166,'2026-07-08 10:51:11',NULL),(2,167,'2026-07-08 10:51:11',NULL),(2,168,'2026-07-08 10:51:11',NULL),(2,169,'2026-07-08 10:51:11',NULL),(2,170,'2026-07-08 10:51:11',NULL),(2,171,'2026-07-08 10:51:11',NULL),(2,172,'2026-07-08 10:51:11',NULL),(2,173,'2026-07-08 10:51:11',NULL),(2,174,'2026-07-08 10:51:11',NULL),(2,175,'2026-07-08 10:51:11',NULL),(2,176,'2026-07-08 10:51:11',NULL),(2,177,'2026-07-08 10:51:11',NULL),(2,178,'2026-07-08 10:51:11',NULL),(2,179,'2026-07-08 10:51:11',NULL),(2,180,'2026-07-08 10:51:11',NULL),(2,181,'2026-07-08 10:51:11',NULL),(2,182,'2026-07-08 10:51:11',NULL),(2,183,'2026-07-08 10:51:11',NULL),(2,184,'2026-07-08 10:51:11',NULL),(2,185,'2026-07-08 10:51:11',NULL),(2,186,'2026-07-08 10:51:11',NULL),(2,187,'2026-07-08 10:51:11',NULL),(2,188,'2026-07-08 10:51:11',NULL),(2,189,'2026-07-08 10:51:11',NULL),(2,190,'2026-07-08 10:51:11',NULL),(2,191,'2026-07-08 10:51:11',NULL),(2,192,'2026-07-08 10:51:11',NULL),(2,193,'2026-07-08 10:51:11',NULL),(2,194,'2026-07-08 10:51:11',NULL),(2,195,'2026-07-08 10:51:11',NULL),(2,196,'2026-07-08 10:51:11',NULL),(2,197,'2026-07-08 10:51:11',NULL),(2,198,'2026-07-08 10:51:11',NULL),(2,199,'2026-07-08 10:51:11',NULL),(2,200,'2026-07-08 10:51:11',NULL),(2,201,'2026-07-08 10:51:11',NULL),(2,202,'2026-07-08 10:51:11',NULL),(2,203,'2026-07-08 10:51:11',NULL),(2,204,'2026-07-08 10:51:11',NULL),(2,205,'2026-07-08 10:51:11',NULL),(2,206,'2026-07-08 10:51:11',NULL),(2,207,'2026-07-08 10:51:11',NULL),(2,208,'2026-07-08 10:51:11',NULL),(2,209,'2026-07-08 10:51:11',NULL),(2,210,'2026-07-08 10:51:11',NULL),(2,211,'2026-07-08 10:51:11',NULL),(2,212,'2026-07-08 10:51:11',NULL),(2,213,'2026-07-08 10:51:11',NULL),(2,214,'2026-07-08 10:51:11',NULL),(2,215,'2026-07-08 10:51:11',NULL),(2,216,'2026-07-08 10:51:11',NULL),(2,217,'2026-07-08 10:51:11',NULL),(2,218,'2026-07-08 10:51:11',NULL),(2,219,'2026-07-08 10:51:11',NULL),(2,221,'2026-07-08 10:51:11',NULL),(2,222,'2026-07-08 10:51:11',NULL),(2,223,'2026-07-08 10:51:11',NULL),(2,225,'2026-07-08 10:51:11',NULL),(2,226,'2026-07-08 10:51:11',NULL),(2,227,'2026-07-08 10:51:11',NULL),(2,228,'2026-07-08 10:51:11',NULL),(2,229,'2026-07-08 10:51:11',NULL),(2,230,'2026-07-08 10:51:11',NULL),(2,231,'2026-07-08 10:51:11',NULL),(2,232,'2026-07-08 10:51:11',NULL),(2,233,'2026-07-08 10:51:11',NULL),(2,234,'2026-07-08 10:51:11',NULL),(2,235,'2026-07-08 10:51:11',NULL),(2,236,'2026-07-08 10:51:11',NULL),(2,237,'2026-07-08 10:51:11',NULL),(2,238,'2026-07-08 10:51:11',NULL),(2,239,'2026-07-08 10:51:11',NULL),(2,240,'2026-07-08 10:51:11',NULL),(2,241,'2026-07-08 10:51:11',NULL),(2,242,'2026-07-08 10:51:11',NULL),(2,244,'2026-07-08 10:51:11',NULL),(2,249,'2026-07-08 10:51:11',NULL),(2,250,'2026-07-08 10:51:11',NULL),(2,251,'2026-07-08 10:51:11',NULL),(2,252,'2026-07-08 10:51:11',NULL),(2,253,'2026-07-08 10:51:11',NULL),(2,254,'2026-07-08 10:51:11',NULL),(2,255,'2026-07-08 10:51:11',NULL),(2,256,'2026-07-08 10:51:11',NULL),(2,257,'2026-07-08 10:51:11',NULL),(2,258,'2026-07-08 10:51:11',NULL),(2,259,'2026-07-08 10:51:11',NULL),(2,260,'2026-07-08 10:51:11',NULL),(2,261,'2026-07-08 10:51:11',NULL),(2,262,'2026-07-08 10:51:11',NULL),(2,268,'2026-07-08 12:12:43',NULL),(2,269,'2026-07-08 12:12:43',NULL),(2,274,'2026-07-09 21:51:35',NULL),(2,275,'2026-07-09 21:51:35',NULL),(2,276,'2026-07-10 00:22:18',NULL),(2,277,'2026-07-10 00:22:18',NULL),(2,278,'2026-07-10 00:22:18',NULL),(2,279,'2026-07-10 00:22:18',NULL),(2,280,'2026-07-10 00:22:18',NULL),(2,281,'2026-07-10 00:22:18',NULL),(2,282,'2026-07-10 00:22:18',NULL),(2,283,'2026-07-10 00:22:18',NULL),(2,284,'2026-07-10 00:22:18',NULL),(2,285,'2026-07-10 00:22:18',NULL),(2,286,'2026-07-10 00:22:18',NULL),(2,287,'2026-07-10 00:22:18',NULL),(2,288,'2026-07-10 00:22:18',NULL),(2,289,'2026-07-10 00:22:18',NULL),(2,290,'2026-07-10 00:22:18',NULL),(2,291,'2026-07-10 00:22:18',NULL),(2,292,'2026-10-25 18:01:09',NULL),(2,293,'2026-10-26 09:09:23',NULL),(2,294,'2026-10-26 09:09:23',NULL),(2,295,'2026-10-26 09:09:23',NULL),(2,296,'2026-10-26 09:09:23',NULL),(2,297,'2026-10-26 09:09:23',NULL),(2,298,'2026-10-26 09:09:23',NULL),(3,1,'2026-07-08 10:51:11',NULL),(3,2,'2026-07-08 10:51:11',NULL),(3,3,'2026-07-08 10:51:11',NULL),(3,9,'2026-07-08 10:51:11',NULL),(3,13,'2026-07-08 10:51:11',NULL),(3,17,'2026-07-08 10:51:11',NULL),(3,22,'2026-07-08 10:51:11',NULL),(3,23,'2026-07-08 10:51:11',NULL),(3,24,'2026-07-08 10:51:11',NULL),(3,26,'2026-07-08 10:51:11',NULL),(3,27,'2026-07-08 10:51:11',NULL),(3,28,'2026-07-08 10:51:11',NULL),(3,30,'2026-07-08 10:51:11',NULL),(3,33,'2026-07-08 10:51:11',NULL),(3,34,'2026-07-08 10:51:11',NULL),(3,35,'2026-07-08 10:51:11',NULL),(3,36,'2026-07-08 10:51:12',NULL),(3,42,'2026-07-08 10:51:12',NULL),(3,43,'2026-07-08 10:51:12',NULL),(3,44,'2026-07-08 10:51:12',NULL),(3,47,'2026-07-08 10:51:12',NULL),(3,48,'2026-07-08 10:51:12',NULL),(3,53,'2026-07-08 10:51:12',NULL),(3,54,'2026-07-08 10:51:12',NULL),(3,57,'2026-07-08 10:51:12',NULL),(3,58,'2026-07-08 10:51:12',NULL),(3,62,'2026-07-08 10:51:12',NULL),(3,63,'2026-07-08 10:51:12',NULL),(3,64,'2026-07-08 10:51:12',NULL),(3,69,'2026-07-08 10:51:12',NULL),(3,91,'2026-07-08 10:51:12',NULL),(3,92,'2026-07-08 10:51:12',NULL),(3,93,'2026-07-08 10:51:12',NULL),(3,95,'2026-07-08 10:51:12',NULL),(3,96,'2026-07-08 10:51:12',NULL),(3,97,'2026-07-08 10:51:12',NULL),(3,98,'2026-07-08 10:51:12',NULL),(3,99,'2026-07-08 10:51:12',NULL),(3,101,'2026-07-08 10:51:12',NULL),(3,102,'2026-07-08 10:51:12',NULL),(3,103,'2026-07-08 10:51:12',NULL),(3,104,'2026-07-08 10:51:12',NULL),(3,105,'2026-07-08 10:51:12',NULL),(3,106,'2026-07-08 10:51:12',NULL),(3,107,'2026-07-08 10:51:12',NULL),(3,108,'2026-07-08 10:51:12',NULL),(3,109,'2026-07-08 10:51:12',NULL),(3,110,'2026-07-08 10:51:12',NULL),(3,111,'2026-07-08 10:51:12',NULL),(3,112,'2026-07-08 10:51:12',NULL),(3,113,'2026-07-08 10:51:12',NULL),(3,114,'2026-07-08 10:51:12',NULL),(3,115,'2026-07-08 10:51:12',NULL),(3,116,'2026-07-08 10:51:12',NULL),(3,117,'2026-07-08 10:51:12',NULL),(3,118,'2026-07-08 10:51:12',NULL),(3,119,'2026-07-08 10:51:12',NULL),(3,120,'2026-07-08 10:51:12',NULL),(3,121,'2026-07-08 10:51:12',NULL),(3,122,'2026-07-08 10:51:12',NULL),(3,123,'2026-07-08 10:51:12',NULL),(3,124,'2026-07-08 10:51:12',NULL),(3,125,'2026-07-08 10:51:12',NULL),(3,126,'2026-07-08 10:51:12',NULL),(3,128,'2026-07-08 10:51:12',NULL),(3,129,'2026-07-08 10:51:12',NULL),(3,134,'2026-07-08 10:51:12',NULL),(3,135,'2026-07-08 10:51:12',NULL),(3,139,'2026-07-08 10:51:12',NULL),(3,140,'2026-07-08 10:51:12',NULL),(3,144,'2026-07-08 10:51:12',NULL),(3,145,'2026-07-08 10:51:12',NULL),(3,151,'2026-07-08 10:51:12',NULL),(3,152,'2026-07-08 10:51:12',NULL),(3,156,'2026-07-08 10:51:12',NULL),(3,157,'2026-07-08 10:51:12',NULL),(3,158,'2026-07-08 10:51:12',NULL),(3,161,'2026-07-08 10:51:12',NULL),(3,162,'2026-07-08 10:51:12',NULL),(3,163,'2026-07-08 10:51:12',NULL),(3,164,'2026-07-08 10:51:12',NULL),(3,166,'2026-07-08 10:51:12',NULL),(3,167,'2026-07-08 10:51:12',NULL),(3,168,'2026-07-08 10:51:12',NULL),(3,169,'2026-07-08 10:51:12',NULL),(3,174,'2026-07-08 10:51:12',NULL),(3,175,'2026-07-08 10:51:12',NULL),(3,176,'2026-07-08 10:51:12',NULL),(3,177,'2026-07-08 10:51:12',NULL),(3,178,'2026-07-08 10:51:12',NULL),(3,180,'2026-07-08 10:51:12',NULL),(3,182,'2026-07-08 10:51:12',NULL),(3,183,'2026-07-08 10:51:12',NULL),(3,184,'2026-07-08 10:51:12',NULL),(3,186,'2026-07-08 10:51:12',NULL),(3,187,'2026-07-08 10:51:12',NULL),(3,188,'2026-07-08 10:51:12',NULL),(3,190,'2026-07-08 10:51:12',NULL),(3,191,'2026-07-08 10:51:12',NULL),(3,192,'2026-07-08 10:51:12',NULL),(3,193,'2026-07-08 10:51:12',NULL),(3,194,'2026-07-08 10:51:12',NULL),(3,195,'2026-07-08 10:51:12',NULL),(3,196,'2026-07-08 10:51:12',NULL),(3,197,'2026-07-08 10:51:12',NULL),(3,200,'2026-07-08 10:51:12',NULL),(3,201,'2026-07-08 10:51:12',NULL),(3,202,'2026-07-08 10:51:12',NULL),(3,203,'2026-07-08 10:51:12',NULL),(3,204,'2026-07-08 10:51:12',NULL),(3,205,'2026-07-08 10:51:12',NULL),(3,206,'2026-07-08 10:51:12',NULL),(3,207,'2026-07-08 10:51:12',NULL),(3,210,'2026-07-08 10:51:12',NULL),(3,211,'2026-07-08 10:51:12',NULL),(3,212,'2026-07-08 10:51:12',NULL),(3,213,'2026-07-08 10:51:12',NULL),(3,214,'2026-07-08 10:51:12',NULL),(3,217,'2026-07-08 10:51:12',NULL),(3,221,'2026-07-08 10:51:12',NULL),(3,222,'2026-07-08 10:51:12',NULL),(3,223,'2026-07-08 10:51:12',NULL),(3,225,'2026-07-08 10:51:12',NULL),(3,227,'2026-07-08 10:51:12',NULL),(3,231,'2026-07-08 10:51:12',NULL),(3,232,'2026-07-08 10:51:12',NULL),(3,233,'2026-07-08 10:51:12',NULL),(3,235,'2026-07-08 10:51:12',NULL),(3,238,'2026-07-08 10:51:12',NULL),(3,240,'2026-07-08 10:51:12',NULL),(3,248,'2026-07-08 10:51:12',NULL),(3,250,'2026-07-08 10:51:12',NULL),(3,252,'2026-07-08 10:51:12',NULL),(3,253,'2026-07-08 10:51:12',NULL),(3,254,'2026-07-08 10:51:12',NULL),(3,256,'2026-07-08 10:51:12',NULL),(3,259,'2026-07-08 10:51:12',NULL),(3,260,'2026-07-08 10:51:12',NULL),(4,1,'2026-07-08 10:51:12',NULL),(4,5,'2026-07-08 10:51:12',NULL),(4,9,'2026-07-08 10:51:12',NULL),(4,13,'2026-07-08 10:51:12',NULL),(4,27,'2026-07-08 10:51:12',NULL),(4,28,'2026-07-08 10:51:12',NULL),(4,29,'2026-07-08 10:51:12',NULL),(4,33,'2026-07-08 10:51:12',NULL),(4,42,'2026-07-08 10:51:12',NULL),(4,43,'2026-07-08 10:51:12',NULL),(4,44,'2026-07-08 10:51:13',NULL),(4,47,'2026-07-08 10:51:13',NULL),(4,50,'2026-07-08 10:51:13',NULL),(4,53,'2026-07-08 10:51:13',NULL),(4,54,'2026-07-08 10:51:13',NULL),(4,55,'2026-07-08 10:51:13',NULL),(4,56,'2026-07-08 10:51:13',NULL),(4,57,'2026-07-08 10:51:13',NULL),(4,59,'2026-07-08 10:51:13',NULL),(4,60,'2026-07-08 10:51:13',NULL),(4,61,'2026-07-12 02:51:47',NULL),(4,62,'2026-07-08 10:51:13',NULL),(4,63,'2026-07-08 10:51:13',NULL),(4,64,'2026-07-08 10:51:13',NULL),(4,65,'2026-07-08 10:51:13',NULL),(4,66,'2026-07-08 10:51:13',NULL),(4,67,'2026-07-08 10:51:13',NULL),(4,69,'2026-07-08 10:51:13',NULL),(4,70,'2026-07-08 10:51:13',NULL),(4,157,'2026-07-08 10:51:13',NULL),(4,161,'2026-07-08 10:51:13',NULL),(4,162,'2026-07-08 10:51:13',NULL),(4,168,'2026-07-08 10:51:13',NULL),(4,169,'2026-07-08 10:51:13',NULL),(4,175,'2026-07-08 10:51:13',NULL),(4,180,'2026-07-08 10:51:13',NULL),(4,182,'2026-07-08 10:51:13',NULL),(4,186,'2026-07-08 10:51:13',NULL),(4,187,'2026-07-08 10:51:13',NULL),(4,197,'2026-07-08 10:51:13',NULL),(4,202,'2026-07-08 10:51:13',NULL),(4,203,'2026-07-08 10:51:13',NULL),(4,210,'2026-07-08 10:51:13',NULL),(4,211,'2026-07-08 10:51:13',NULL),(4,217,'2026-07-08 10:51:13',NULL),(4,219,'2026-07-08 10:51:13',NULL),(4,221,'2026-07-08 10:51:13',NULL),(4,225,'2026-07-08 10:51:13',NULL),(4,227,'2026-07-08 10:51:13',NULL),(4,228,'2026-07-08 10:51:13',NULL),(4,229,'2026-07-08 10:51:13',NULL),(4,230,'2026-07-08 10:51:13',NULL),(4,242,'2026-07-08 10:51:13',NULL),(4,248,'2026-07-08 10:51:13',NULL),(4,250,'2026-07-08 10:51:13',NULL),(4,254,'2026-07-08 10:51:13',NULL),(4,259,'2026-07-08 10:51:13',NULL),(4,260,'2026-07-08 10:51:13',NULL),(5,1,'2026-07-08 10:51:13',NULL),(5,9,'2026-07-08 10:51:13',NULL),(5,13,'2026-07-08 10:51:13',NULL),(5,17,'2026-07-08 10:51:13',NULL),(5,18,'2026-07-08 10:51:13',NULL),(5,19,'2026-07-08 10:51:13',NULL),(5,22,'2026-07-08 10:51:13',NULL),(5,23,'2026-07-08 10:51:13',NULL),(5,24,'2026-07-08 10:51:13',NULL),(5,26,'2026-07-08 10:51:13',NULL),(5,30,'2026-07-08 10:51:13',NULL),(5,33,'2026-07-08 10:51:13',NULL),(5,79,'2026-07-08 10:51:13',NULL),(5,80,'2026-07-08 10:51:13',NULL),(5,81,'2026-07-08 10:51:13',NULL),(5,82,'2026-07-08 10:51:13',NULL),(5,83,'2026-10-26 09:09:23',NULL),(5,85,'2026-10-26 09:09:23',NULL),(5,87,'2026-07-08 10:51:13',NULL),(5,91,'2026-07-08 10:51:13',NULL),(5,92,'2026-07-08 10:51:13',NULL),(5,96,'2026-07-08 10:51:13',NULL),(5,97,'2026-07-08 10:51:13',NULL),(5,98,'2026-07-08 10:51:13',NULL),(5,99,'2026-07-08 10:51:13',NULL),(5,100,'2026-07-08 10:51:13',NULL),(5,101,'2026-07-08 10:51:13',NULL),(5,102,'2026-07-08 10:51:13',NULL),(5,103,'2026-07-08 10:51:13',NULL),(5,105,'2026-07-08 10:51:13',NULL),(5,106,'2026-07-08 10:51:13',NULL),(5,107,'2026-07-08 10:51:13',NULL),(5,108,'2026-07-08 10:51:13',NULL),(5,109,'2026-07-08 10:51:13',NULL),(5,110,'2026-07-08 10:51:13',NULL),(5,112,'2026-07-08 10:51:13',NULL),(5,113,'2026-07-08 10:51:13',NULL),(5,114,'2026-07-08 10:51:13',NULL),(5,115,'2026-07-08 10:51:13',NULL),(5,117,'2026-07-08 10:51:13',NULL),(5,118,'2026-07-08 10:51:13',NULL),(5,119,'2026-07-08 10:51:13',NULL),(5,120,'2026-07-08 10:51:14',NULL),(5,122,'2026-07-08 10:51:14',NULL),(5,123,'2026-07-08 10:51:14',NULL),(5,124,'2026-07-08 10:51:14',NULL),(5,125,'2026-07-08 10:51:14',NULL),(5,128,'2026-07-08 10:51:14',NULL),(5,135,'2026-07-08 10:51:14',NULL),(5,140,'2026-07-08 10:51:14',NULL),(5,145,'2026-07-08 10:51:14',NULL),(5,152,'2026-07-08 10:51:14',NULL),(5,157,'2026-07-08 10:51:14',NULL),(5,162,'2026-07-08 10:51:14',NULL),(5,163,'2026-07-08 10:51:14',NULL),(5,169,'2026-07-08 10:51:14',NULL),(5,175,'2026-07-08 10:51:14',NULL),(5,182,'2026-07-08 10:51:14',NULL),(5,187,'2026-07-08 10:51:14',NULL),(5,190,'2026-07-08 10:51:14',NULL),(5,191,'2026-07-08 10:51:14',NULL),(5,192,'2026-07-08 10:51:14',NULL),(5,196,'2026-07-08 10:51:14',NULL),(5,197,'2026-07-08 10:51:14',NULL),(5,200,'2026-07-08 10:51:14',NULL),(5,202,'2026-07-08 10:51:14',NULL),(5,203,'2026-07-08 10:51:14',NULL),(5,204,'2026-07-08 10:51:14',NULL),(5,210,'2026-07-08 10:51:14',NULL),(5,211,'2026-07-08 10:51:14',NULL),(5,212,'2026-07-08 10:51:14',NULL),(5,213,'2026-07-08 10:51:14',NULL),(5,245,'2026-07-08 10:51:14',NULL),(5,253,'2026-07-08 10:51:14',NULL),(5,256,'2026-07-08 10:51:14',NULL),(5,259,'2026-07-08 10:51:14',NULL),(5,263,'2026-07-08 10:51:13',NULL),(5,264,'2026-07-08 10:51:14',NULL),(6,21,'2026-07-08 10:51:14',NULL),(6,25,'2026-07-08 10:51:14',NULL),(6,33,'2026-07-08 10:51:14',NULL),(6,87,'2026-07-08 10:51:14',NULL),(6,91,'2026-07-08 10:51:14',NULL),(6,95,'2026-07-08 10:51:14',NULL),(6,97,'2026-07-08 10:51:14',NULL),(6,101,'2026-07-08 10:51:14',NULL),(6,104,'2026-07-08 10:51:14',NULL),(6,107,'2026-07-08 10:51:14',NULL),(6,113,'2026-07-08 10:51:14',NULL),(6,118,'2026-07-08 10:51:14',NULL),(6,123,'2026-07-08 10:51:14',NULL),(6,187,'2026-07-08 10:51:14',NULL),(6,202,'2026-07-08 10:51:14',NULL),(6,203,'2026-07-08 10:51:14',NULL),(6,210,'2026-07-08 10:51:14',NULL),(6,211,'2026-07-08 10:51:14',NULL),(6,213,'2026-07-08 10:51:14',NULL),(6,247,'2026-07-08 10:51:14',NULL),(6,263,'2026-07-08 10:51:14',NULL),(6,265,'2026-07-08 10:51:14',NULL),(6,266,'2026-07-08 10:51:14',NULL),(6,267,'2026-07-08 10:51:14',NULL),(7,21,'2026-07-08 10:51:14',NULL),(7,25,'2026-07-08 10:51:14',NULL),(7,33,'2026-07-08 10:51:14',NULL),(7,87,'2026-07-08 10:51:14',NULL),(7,91,'2026-07-08 10:51:14',NULL),(7,97,'2026-07-08 10:51:14',NULL),(7,101,'2026-07-08 10:51:14',NULL),(7,107,'2026-07-08 10:51:14',NULL),(7,113,'2026-07-08 10:51:14',NULL),(7,118,'2026-07-08 10:51:14',NULL),(7,123,'2026-07-08 10:51:14',NULL),(7,187,'2026-07-08 10:51:14',NULL),(7,202,'2026-07-08 10:51:14',NULL),(7,203,'2026-07-08 10:51:14',NULL),(7,210,'2026-07-08 10:51:14',NULL),(7,211,'2026-07-08 10:51:14',NULL),(7,212,'2026-07-08 10:51:14',NULL),(7,213,'2026-07-08 10:51:14',NULL),(7,246,'2026-07-08 10:51:14',NULL),(7,263,'2026-07-08 10:51:14',NULL),(7,267,'2026-07-12 05:42:38',NULL);
/*!40000 ALTER TABLE `etab_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `etab_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etab_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = rôle système global',
  `code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etabrole_scope_code` (`etablissement_id`,`code`),
  KEY `idx_etabrole_etab` (`etablissement_id`),
  CONSTRAINT `fk_etabrole_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `etab_roles` WRITE;
/*!40000 ALTER TABLE `etab_roles` DISABLE KEYS */;
INSERT INTO `etab_roles` VALUES (1,NULL,'admin','Administrateur','Accès total à la plateforme',1,'2026-07-08 05:14:19'),(2,NULL,'directeur','Directeur','Direction de l\'établissement',1,'2026-07-08 05:14:19'),(3,NULL,'secretaire','Secrétaire','Administration scolaire',1,'2026-07-08 05:14:19'),(4,NULL,'comptable','Comptable','Gestion financière',1,'2026-07-08 05:14:20'),(5,NULL,'enseignant','Enseignant','Corps pédagogique',1,'2026-07-08 05:14:20'),(6,NULL,'parent','Parent','Représentant légal de l\'élève',1,'2026-07-08 05:14:20'),(7,NULL,'eleve','Élève','Apprenant',1,'2026-07-08 05:14:20');
/*!40000 ALTER TABLE `etab_roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `etab_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etab_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `section` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` enum('string','integer','boolean','json','datetime') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `modifiable_ui` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etab_setting` (`etablissement_id`,`section`,`cle`),
  KEY `idx_etab_section` (`etablissement_id`,`section`),
  KEY `fk_etabset_updater` (`updated_by`),
  CONSTRAINT `fk_etabset_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_etabset_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=945 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `etab_settings` WRITE;
/*!40000 ALTER TABLE `etab_settings` DISABLE KEYS */;
INSERT INTO `etab_settings` VALUES (1,1,'branding','font_family','Inter','string',1,NULL,'2026-07-08 12:03:32','2026-07-08 12:03:32'),(2,1,'branding','theme_mode','light','string',1,NULL,'2026-07-08 12:03:32','2026-07-08 12:03:32'),(3,1,'branding','login_image_url','','string',1,NULL,'2026-07-08 12:03:32','2026-07-08 12:03:32'),(4,1,'branding','contact_phone','00 00 00 00','string',1,NULL,'2026-07-08 12:03:32','2026-07-20 01:55:09'),(5,1,'branding','contact_email','contact@ecole.example','string',1,NULL,'2026-07-08 12:03:32','2026-07-20 01:55:09'),(6,1,'branding','contact_address','Niamey, Niger','string',1,NULL,'2026-07-08 12:03:32','2026-07-20 01:55:09'),(7,1,'branding','footer_text','© 2026 EduNova — Tous droits réservés','string',1,NULL,'2026-07-08 12:03:32','2026-07-08 12:03:32'),(8,1,'branding','show_breadcrumbs','1','boolean',1,NULL,'2026-07-08 12:03:32','2026-07-08 12:03:32'),(513,1,'academique','annee_scolaire_active','2025-2026','string',1,1,'2026-07-09 21:55:40','2026-07-09 21:55:40'),(514,1,'finance','devise_defaut','XOF','string',1,1,'2026-07-09 21:55:40','2026-07-09 21:55:40'),(564,1,'academique','cycles_actifs','[\"Préscolaire\",\"Primaire\",\"Collège\",\"Lycée\"]','json',1,1,'2026-07-10 02:52:54','2026-07-10 02:52:54'),(565,1,'academique','bareme_max','20','integer',1,1,'2026-07-10 02:52:54','2026-07-10 02:52:54'),(566,1,'documents','prefixe_recu','REC','string',1,1,'2026-07-10 02:52:54','2026-07-10 02:52:54'),(567,1,'securite','password_min_length','8','integer',1,1,'2026-07-10 02:52:54','2026-07-10 02:52:55'),(568,1,'notifications','email_actif','1','boolean',1,1,'2026-07-10 02:52:54','2026-07-10 02:52:54'),(596,1,'academique','bulletin_secret','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','string',1,NULL,'2026-07-22 05:49:21','2026-07-22 05:49:21'),(597,1,'academique','seuil_rattrapage','8','string',1,NULL,'2026-07-22 05:49:37','2026-07-22 05:49:39'),(598,1,'academique','note_passage','10','string',1,NULL,'2026-07-22 05:49:38','2026-07-22 05:49:39');
/*!40000 ALTER TABLE `etab_settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `etablissement_branding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etablissement_branding` (
  `etablissement_id` int unsigned NOT NULL,
  `logo_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_dark_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366f1',
  `secondary_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0ea5e9',
  `app_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EduNova',
  `welcome_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`etablissement_id`),
  CONSTRAINT `fk_etabbrand_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `etablissement_branding` WRITE;
/*!40000 ALTER TABLE `etablissement_branding` DISABLE KEYS */;
INSERT INTO `etablissement_branding` VALUES (1,'/assets/img/logo.jpeg','/assets/img/logo.jpeg','/assets/img/logo.jpeg','#7c3aed','#0ea5e9','La Démo','Bienvenue sur l\'espace de gestion du Complexe Scolaire Privé La Démo.','2026-10-29 20:04:53');
/*!40000 ALTER TABLE `etablissement_branding` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `etablissement_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etablissement_domains` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('custom','subdomain') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'subdomain',
  `verification_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  `ssl_status` enum('pending','issued','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `ssl_expires_at` date DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etabdomain_domain` (`domain`),
  KEY `idx_etabdomain_etab` (`etablissement_id`),
  CONSTRAINT `fk_etabdomain_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=145 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `etablissement_domains` WRITE;
/*!40000 ALTER TABLE `etablissement_domains` DISABLE KEYS */;
/*!40000 ALTER TABLE `etablissement_domains` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `etablissements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etablissements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_court` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_etablissement` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('ecole_primaire','college','lycee','universite','formation_pro','groupe_scolaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pays` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DZ',
  `wilaya` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commune` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_web` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_id` int unsigned DEFAULT NULL,
  `plan_expires_at` date DEFAULT NULL,
  `storage_quota_mb` int unsigned NOT NULL DEFAULT '1024',
  `max_users` smallint unsigned NOT NULL DEFAULT '50',
  `max_eleves` smallint unsigned NOT NULL DEFAULT '500',
  `max_documents` smallint unsigned DEFAULT NULL,
  `max_attachments` smallint unsigned DEFAULT NULL,
  `statut` enum('trial','active','suspended','cancelled','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `trial_ends_at` date DEFAULT NULL,
  `suspended_at` datetime DEFAULT NULL,
  `suspension_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etab_slug` (`slug`),
  UNIQUE KEY `uq_etab_code` (`code_etablissement`),
  KEY `idx_etab_statut` (`statut`),
  KEY `idx_etab_plan` (`plan_id`),
  CONSTRAINT `fk_etab_plan` FOREIGN KEY (`plan_id`) REFERENCES `platform_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=559 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `etablissements` WRITE;
/*!40000 ALTER TABLE `etablissements` DISABLE KEYS */;
INSERT INTO `etablissements` VALUES (1,'edunova-demo','EduNova — Établissement Démo','EduNova',NULL,'lycee','DZ',NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,1024,50,500,NULL,NULL,'active',NULL,NULL,NULL,'2026-07-08 05:14:13','2026-07-08 05:14:13','2026-07-08 05:14:13',NULL);
/*!40000 ALTER TABLE `etablissements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `periode_scolaire_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ periodes_scolaires V2',
  `type_evaluation_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ types_evaluations V2',
  `matiere_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ matieres V1',
  `classe_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ classes V1',
  `enseignant_id` int unsigned DEFAULT NULL COMMENT 'FK ÔåÆ professeurs V1, optionnel',
  `libelle` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Intitul├® de l''├®valuation',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_evaluation` date DEFAULT NULL,
  `coefficient` decimal(4,2) NOT NULL DEFAULT '1.00' COMMENT 'Coefficient sp├®cifique (├®crase le d├®faut du type)',
  `note_max` decimal(5,2) NOT NULL DEFAULT '20.00' COMMENT 'Bar├¿me de cette ├®valuation',
  `statut` enum('brouillon','publiee','verrouillee','archivee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `notes_saisie_ouverte` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'G├®r├® automatiquement par le cycle de vie',
  `verrouille_par` int unsigned DEFAULT NULL,
  `verrouille_le` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ev_periode` (`periode_scolaire_id`),
  KEY `idx_ev_type` (`type_evaluation_id`),
  KEY `idx_ev_matiere` (`matiere_id`),
  KEY `idx_ev_classe` (`classe_id`),
  KEY `idx_ev_enseignant` (`enseignant_id`),
  KEY `idx_ev_statut` (`statut`),
  KEY `idx_ev_date` (`date_evaluation`),
  KEY `idx_ev_classe_per` (`classe_id`,`periode_scolaire_id`),
  CONSTRAINT `fk_ev_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ev_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `professeurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ev_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ev_periode` FOREIGN KEY (`periode_scolaire_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ev_type` FOREIGN KEY (`type_evaluation_id`) REFERENCES `types_evaluations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `evaluations` WRITE;
/*!40000 ALTER TABLE `evaluations` DISABLE KEYS */;
INSERT INTO `evaluations` VALUES (1,90,3,187,131,6,'Examen — S.V.T',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(2,90,3,188,131,2,'Examen — Physique-Chimie',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(3,90,3,189,131,9,'Examen — Education Physique',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(4,90,3,190,131,9,'Examen — Conduite',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(5,90,3,1,131,1,'Examen — Mathématiques',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(6,90,3,4,131,4,'Examen — Français',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(7,90,3,5,131,5,'Examen — Anglais',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(8,90,3,6,131,7,'Examen — Histoire-Géographie',NULL,'2026-06-20',1.00,20.00,'publiee',1,NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(9,91,3,187,131,6,'Examen — S.V.T (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:02','2026-07-20 02:11:02'),(10,91,3,188,131,2,'Examen — Physique-Chimie (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:02','2026-07-20 02:11:02'),(11,91,3,189,131,9,'Examen — Education Physique (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:02','2026-07-20 02:11:02'),(12,91,3,190,131,9,'Examen — Conduite (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(13,91,3,1,131,1,'Examen — Mathématiques (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(14,91,3,4,131,4,'Examen — Français (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(15,91,3,5,131,5,'Examen — Anglais (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(16,91,3,6,131,7,'Examen — Histoire-Géographie (S1)',NULL,'2025-12-10',1.00,20.00,'publiee',0,NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(40,100,2,4,132,1,'[TEST BULLETIN 2026-2027 S1] Français - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(41,100,3,4,132,1,'[TEST BULLETIN 2026-2027 S1] Français - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(42,100,2,1,132,1,'[TEST BULLETIN 2026-2027 S1] Mathématiques - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(43,100,3,1,132,1,'[TEST BULLETIN 2026-2027 S1] Mathématiques - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(44,100,2,5,132,1,'[TEST BULLETIN 2026-2027 S1] Anglais - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(45,100,3,5,132,1,'[TEST BULLETIN 2026-2027 S1] Anglais - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(46,100,2,6,132,1,'[TEST BULLETIN 2026-2027 S1] Histoire-Géographie - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(47,100,3,6,132,1,'[TEST BULLETIN 2026-2027 S1] Histoire-Géographie - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(48,100,2,187,132,1,'[TEST BULLETIN 2026-2027 S1] S.V.T - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(49,100,3,187,132,1,'[TEST BULLETIN 2026-2027 S1] S.V.T - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(50,100,2,188,132,1,'[TEST BULLETIN 2026-2027 S1] Physique-Chimie - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(51,100,3,188,132,1,'[TEST BULLETIN 2026-2027 S1] Physique-Chimie - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(52,100,2,189,132,1,'[TEST BULLETIN 2026-2027 S1] Education Physique - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(53,100,3,189,132,1,'[TEST BULLETIN 2026-2027 S1] Education Physique - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(54,100,2,190,132,1,'[TEST BULLETIN 2026-2027 S1] Conduite - devoir','Évaluation du jeu de données bulletin','2026-11-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(55,100,3,190,132,1,'[TEST BULLETIN 2026-2027 S1] Conduite - examen','Évaluation du jeu de données bulletin','2026-11-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(56,101,2,4,132,1,'[TEST BULLETIN 2026-2027 S2] Français - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(57,101,3,4,132,1,'[TEST BULLETIN 2026-2027 S2] Français - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(58,101,2,1,132,1,'[TEST BULLETIN 2026-2027 S2] Mathématiques - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(59,101,3,1,132,1,'[TEST BULLETIN 2026-2027 S2] Mathématiques - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(60,101,2,5,132,1,'[TEST BULLETIN 2026-2027 S2] Anglais - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(61,101,3,5,132,1,'[TEST BULLETIN 2026-2027 S2] Anglais - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'verrouillee',0,1,'2026-08-18 04:43:27',1,'2026-08-14 02:08:14','2026-08-18 04:43:27'),(62,101,2,6,132,1,'[TEST BULLETIN 2026-2027 S2] Histoire-Géographie - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(63,101,3,6,132,1,'[TEST BULLETIN 2026-2027 S2] Histoire-Géographie - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(64,101,2,187,132,1,'[TEST BULLETIN 2026-2027 S2] S.V.T - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(65,101,3,187,132,1,'[TEST BULLETIN 2026-2027 S2] S.V.T - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(66,101,2,188,132,1,'[TEST BULLETIN 2026-2027 S2] Physique-Chimie - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(67,101,3,188,132,1,'[TEST BULLETIN 2026-2027 S2] Physique-Chimie - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(68,101,2,189,132,1,'[TEST BULLETIN 2026-2027 S2] Education Physique - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(69,101,3,189,132,1,'[TEST BULLETIN 2026-2027 S2] Education Physique - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(70,101,2,190,132,1,'[TEST BULLETIN 2026-2027 S2] Conduite - devoir','Évaluation du jeu de données bulletin','2027-04-12',1.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14'),(71,101,3,190,132,1,'[TEST BULLETIN 2026-2027 S2] Conduite - examen','Évaluation du jeu de données bulletin','2027-04-20',2.00,20.00,'publiee',0,NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:08:14');
/*!40000 ALTER TABLE `evaluations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `familles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `familles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_postal` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ville` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_urgence_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_urgence_telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_urgence_lien` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_familles_nom` (`nom`),
  KEY `idx_familles_actif` (`actif`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `familles` WRITE;
/*!40000 ALTER TABLE `familles` DISABLE KEYS */;
INSERT INTO `familles` VALUES (1,'Famille Nom08','Adresse à compléter',NULL,'Niamey','0600000001','parent.eleve-1@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:18','2026-10-29 20:04:55'),(2,'Famille Nom25','Adresse à compléter',NULL,'Niamey','0600000002','parent.eleve-2@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:18','2026-10-29 20:04:55'),(3,'Famille Nom19','Adresse à compléter',NULL,'Niamey','0600000003','parent.eleve-3@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:18','2026-10-29 20:04:55'),(4,'Famille Nom13','Adresse à compléter',NULL,'Niamey','0600000004','parent.eleve-4@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:18','2026-10-29 20:04:55'),(5,'Famille Nom14','Adresse à compléter',NULL,'Niamey','0600000005','parent.eleve-5@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:18','2026-10-29 20:04:55'),(6,'Famille Nom07','Adresse à compléter',NULL,'Niamey','0600000006','parent.eleve-7@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:18','2026-10-29 20:04:55'),(7,'Famille Nom32','Adresse à compléter',NULL,'Niamey','0600000007','parent.eleve-8@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:19','2026-10-29 20:04:55'),(8,'Famille Nom05','Adresse à compléter',NULL,'Niamey','0600000008','parent.eleve-9@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:19','2026-10-29 20:04:55'),(9,'Famille Nom23','Adresse à compléter',NULL,'Niamey','0600000009','parent.eleve-10@demo.ecole.local',NULL,NULL,NULL,'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',1,NULL,'2026-08-14 01:54:19','2026-10-29 20:04:55');
/*!40000 ALTER TABLE `familles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `familles_eleves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `familles_eleves` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `famille_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `lien_parente` enum('pere','mere','tuteur','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'autre',
  `est_responsable_legal` tinyint(1) NOT NULL DEFAULT '1',
  `est_contact_principal` tinyint(1) NOT NULL DEFAULT '0',
  `est_contact_urgence` tinyint(1) NOT NULL DEFAULT '0',
  `ordre` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_famille_eleve` (`famille_id`,`eleve_id`),
  KEY `idx_fe_eleve` (`eleve_id`),
  KEY `idx_fe_famille` (`famille_id`),
  CONSTRAINT `fk_femb_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_femb_famille` FOREIGN KEY (`famille_id`) REFERENCES `familles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `familles_eleves` WRITE;
/*!40000 ALTER TABLE `familles_eleves` DISABLE KEYS */;
INSERT INTO `familles_eleves` VALUES (1,1,1,'pere',1,1,1,1,'2026-08-14 01:54:18'),(2,2,2,'mere',1,1,1,1,'2026-08-14 01:54:18'),(3,3,3,'pere',1,1,1,1,'2026-08-14 01:54:18'),(4,4,4,'mere',1,1,1,1,'2026-08-14 01:54:18'),(5,5,5,'pere',1,1,1,1,'2026-08-14 01:54:18'),(6,6,7,'pere',1,1,1,1,'2026-08-14 01:54:18'),(7,7,8,'mere',1,1,1,1,'2026-08-14 01:54:19'),(8,8,9,'pere',1,1,1,1,'2026-08-14 01:54:19'),(9,9,10,'mere',1,1,1,1,'2026-08-14 01:54:19');
/*!40000 ALTER TABLE `familles_eleves` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_avoirs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_avoirs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AVO-AAAA-NNNN — immuable',
  `facture_origine_id` int unsigned NOT NULL COMMENT 'Facture annulée à l''origine de cet avoir',
  `eleve_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('emis','utilise','rembourse','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'emis',
  `utilise_sur` int unsigned DEFAULT NULL COMMENT 'facture_id sur laquelle l''avoir a été imputé',
  `emis_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `idx_fa_eleve` (`eleve_id`,`annee_scolaire`),
  KEY `idx_fa_facture` (`facture_origine_id`),
  KEY `fk_fa_emetteur` (`emis_par`),
  CONSTRAINT `fk_fa_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fa_emetteur` FOREIGN KEY (`emis_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fa_facture` FOREIGN KEY (`facture_origine_id`) REFERENCES `finance_factures` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Avoirs (notes de crédit) Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_avoirs` WRITE;
/*!40000 ALTER TABLE `finance_avoirs` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_avoirs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_categories_depenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_categories_depenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifiant court immuable (ex: SALAIRES, FOURNITURES)',
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#64748b',
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'receipt',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cdep_etab_code` (`etablissement_id`,`code`),
  KEY `idx_fcd_actif` (`actif`),
  KEY `idx_cdep_etab` (`etablissement_id`),
  CONSTRAINT `fk_cdep_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catégories de dépenses Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_categories_depenses` WRITE;
/*!40000 ALTER TABLE `finance_categories_depenses` DISABLE KEYS */;
INSERT INTO `finance_categories_depenses` VALUES (1,NULL,'SALAIRES','Salaires et charges',NULL,'#4e73df','users',1,'2026-07-12 00:14:46'),(2,NULL,'FOURNITURES','Fournitures et matériel',NULL,'#1cc88a','package',1,'2026-07-12 00:14:46'),(3,NULL,'INFRA','Infrastructure',NULL,'#36b9cc','building',1,'2026-07-12 00:14:46'),(4,NULL,'SERVICES','Services et utilités',NULL,'#f6c23e','plug',1,'2026-07-12 00:14:46'),(5,NULL,'PEDAGOGIE','Activités pédagogiques',NULL,'#e74a3b','graduation-cap',1,'2026-07-12 00:14:46'),(6,NULL,'DIVERS','Divers',NULL,'#858796','more-horizontal',1,'2026-07-12 00:14:46'),(8,1,'SALAIRES','Salaires et charges',NULL,'#4e73df','users',1,'2026-07-12 00:14:46'),(9,1,'FOURNITURES','Fournitures et matériel',NULL,'#1cc88a','package',1,'2026-07-12 00:14:46'),(10,1,'INFRA','Infrastructure',NULL,'#36b9cc','building',1,'2026-07-12 00:14:46'),(11,1,'SERVICES','Services et utilités',NULL,'#f6c23e','plug',1,'2026-07-12 00:14:46'),(12,1,'PEDAGOGIE','Activités pédagogiques',NULL,'#e74a3b','graduation-cap',1,'2026-07-12 00:14:46'),(13,1,'DIVERS','Divers',NULL,'#858796','more-horizontal',1,'2026-07-12 00:14:46');
/*!40000 ALTER TABLE `finance_categories_depenses` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_categories_frais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_categories_frais` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifiant court immuable (ex: SCOL, TRANSP)',
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366f1',
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'currency-dollar',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfrai_etab_code` (`etablissement_id`,`code`),
  KEY `idx_fcf_actif` (`actif`),
  KEY `idx_cfrai_etab` (`etablissement_id`),
  CONSTRAINT `fk_cfrai_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catégories de frais Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_categories_frais` WRITE;
/*!40000 ALTER TABLE `finance_categories_frais` DISABLE KEYS */;
INSERT INTO `finance_categories_frais` VALUES (1,1,'SCOL','Frais scolaires',NULL,'#6366f1','book-open',1,'2026-07-09 20:40:54'),(2,NULL,'TRANSP','Transport',NULL,'#3b82f6','bus',1,'2026-07-09 20:40:54'),(3,NULL,'CANTINE','Cantine & restauration',NULL,'#10b981','utensils',1,'2026-07-09 20:40:54'),(4,NULL,'ACTIVITES','Activités & sorties',NULL,'#f59e0b','activity',1,'2026-07-09 20:40:54'),(5,NULL,'EXAMEN','Examens & concours',NULL,'#ef4444','file-text',1,'2026-07-09 20:40:54'),(6,1,'ADMIN','Frais administratifs',NULL,'#64748b','file',1,'2026-07-09 20:40:54'),(8,1,'REGUL_MIGRATION','Régularisation — Migration V1→V2','Réservé aux factures générées automatiquement lors de la migration des données financières V1 vers V2, pour des paiements V1 sans facture d\'origine identifiable.','#f59e0b','file-text',1,'2026-07-11 20:08:13'),(9,NULL,'SCOL','Frais scolaires',NULL,'#6366f1','book-open',1,'2026-07-09 20:40:54'),(10,NULL,'ADMIN','Frais administratifs',NULL,'#64748b','file',1,'2026-07-09 20:40:54'),(11,NULL,'REGUL_MIGRATION','Régularisation — Migration V1→V2','Réservé aux factures générées automatiquement lors de la migration des données financières V1 vers V2, pour des paiements V1 sans facture d\'origine identifiable.','#f59e0b','file-text',1,'2026-07-11 20:08:13'),(12,1,'TRANSP','Transport',NULL,'#3b82f6','bus',1,'2026-07-09 20:40:54'),(13,1,'CANTINE','Cantine & restauration',NULL,'#10b981','utensils',1,'2026-07-09 20:40:54'),(14,1,'ACTIVITES','Activités & sorties',NULL,'#f59e0b','activity',1,'2026-07-09 20:40:54'),(15,1,'EXAMEN','Examens & concours',NULL,'#ef4444','file-text',1,'2026-07-09 20:40:54');
/*!40000 ALTER TABLE `finance_categories_frais` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_comptes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_comptes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = propriété non démontrée',
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('actif','passif','charge','produit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `classe` tinyint unsigned NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `niveau` tinyint unsigned NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `systeme` tinyint(1) NOT NULL DEFAULT '0',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fcp_etab_code` (`etablissement_id`,`code`),
  KEY `idx_fc_classe` (`classe`),
  KEY `idx_fc_type` (`type`),
  KEY `idx_fcp_etab` (`etablissement_id`),
  CONSTRAINT `fk_fcp_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_comptes` WRITE;
/*!40000 ALTER TABLE `finance_comptes` DISABLE KEYS */;
INSERT INTO `finance_comptes` VALUES (1,1,'1000','Capitaux propres','passif',1,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(2,1,'1010','Capital','passif',1,NULL,2,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(3,1,'1100','Report à nouveau','passif',1,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(4,1,'1200','Résultat de l\'exercice','passif',1,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(5,1,'1600','Emprunts et dettes','passif',1,NULL,1,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(6,1,'4010','Fournisseurs','passif',4,NULL,1,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(7,1,'4110','Élèves — créances scolarité','actif',4,NULL,1,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(8,1,'4190','Avoirs sur élèves','passif',4,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(9,1,'4500','Subventions à recevoir','actif',4,NULL,1,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(10,1,'5120','Banque','actif',5,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(11,1,'5300','Caisse principale','actif',5,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(12,1,'6000','Comptes de charges','charge',6,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(13,1,'6010','Achats de fournitures scolaires','charge',6,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(14,1,'6100','Services extérieurs','charge',6,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(15,1,'6200','Autres services extérieurs','charge',6,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(16,1,'6300','Impôts et taxes','charge',6,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(17,1,'6400','Charges de personnel','charge',6,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(18,1,'6500','Autres charges de gestion courante','charge',6,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(19,1,'6600','Charges financières','charge',6,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(20,1,'7000','Comptes de produits','produit',7,NULL,1,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(21,1,'7010','Frais de scolarité','produit',7,NULL,2,1,1,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(22,1,'7020','Frais d\'inscription','produit',7,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(23,1,'7030','Frais de cantine','produit',7,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(24,1,'7040','Frais d\'internat','produit',7,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(25,1,'7050','Frais de transport scolaire','produit',7,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(26,1,'7400','Subventions d\'exploitation','produit',7,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16'),(27,1,'7500','Produits divers','produit',7,NULL,2,1,0,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16');
/*!40000 ALTER TABLE `finance_comptes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_decaissements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_decaissements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format DEP-AAAA-NNNN',
  `categorie_id` int unsigned DEFAULT NULL,
  `fournisseur_id` int unsigned DEFAULT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `montant` decimal(12,2) NOT NULL,
  `devise` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF',
  `date_depense` date NOT NULL,
  `date_echeance` date DEFAULT NULL,
  `mode_paiement_id` int unsigned DEFAULT NULL COMMENT 'FK finance_modes_paiement — renseigné au paiement',
  `reference_externe` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statut` enum('brouillon','soumis','valide','approuve','paye','rejete','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `caisse_session_id` int unsigned DEFAULT NULL COMMENT 'Session de caisse imputée si payé en espèces',
  `saisi_par` int unsigned DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `approuve_par` int unsigned DEFAULT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `paye_par` int unsigned DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL,
  `motif_rejet` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `annule_par` int unsigned DEFAULT NULL,
  `date_annulation` datetime DEFAULT NULL,
  `motif_annulation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `origine` enum('operationnelle','migration_v1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operationnelle',
  `migration_source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migration_meta` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `idx_fd_date` (`date_depense`),
  KEY `idx_fd_statut` (`statut`),
  KEY `idx_fd_categorie` (`categorie_id`),
  KEY `idx_fd_fournisseur` (`fournisseur_id`),
  KEY `idx_fd_origine` (`origine`),
  KEY `idx_fd_deleted` (`deleted_at`),
  KEY `fk_fd_mode` (`mode_paiement_id`),
  KEY `fk_fd_caisse` (`caisse_session_id`),
  KEY `fk_fd_saisi` (`saisi_par`),
  KEY `fk_fd_valide` (`valide_par`),
  KEY `fk_fd_approuve` (`approuve_par`),
  KEY `fk_fd_paye` (`paye_par`),
  KEY `fk_fd_annule` (`annule_par`),
  CONSTRAINT `fk_fd_annule` FOREIGN KEY (`annule_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_approuve` FOREIGN KEY (`approuve_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_caisse` FOREIGN KEY (`caisse_session_id`) REFERENCES `finance_sessions_caisse` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `finance_categories_depenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `finance_fournisseurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_mode` FOREIGN KEY (`mode_paiement_id`) REFERENCES `finance_modes_paiement` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_paye` FOREIGN KEY (`paye_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_saisi` FOREIGN KEY (`saisi_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fd_valide` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dépenses/décaissements Finance V2 — workflow brouillon→soumis→valide→approuve→paye';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_decaissements` WRITE;
/*!40000 ALTER TABLE `finance_decaissements` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_decaissements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_echeances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_echeances` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `echeancier_id` int unsigned NOT NULL,
  `numero_ordre` tinyint unsigned NOT NULL,
  `date_echeance` date NOT NULL,
  `montant_du` decimal(12,2) NOT NULL,
  `montant_paye` decimal(12,2) NOT NULL DEFAULT '0.00',
  `statut` enum('en_attente','partiel','paye','en_retard','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `penalite` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fec_date` (`date_echeance`,`statut`),
  KEY `idx_fec_echeancier` (`echeancier_id`),
  CONSTRAINT `fk_fec_echeancier` FOREIGN KEY (`echeancier_id`) REFERENCES `finance_echeanciers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Échéances individuelles des plans de paiement';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_echeances` WRITE;
/*!40000 ALTER TABLE `finance_echeances` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_echeances` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_echeanciers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_echeanciers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facture_id` int unsigned NOT NULL,
  `nb_echeances` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ech_facture` (`facture_id`),
  CONSTRAINT `fk_ech_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='En-têtes d''échéanciers';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_echeanciers` WRITE;
/*!40000 ALTER TABLE `finance_echeanciers` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_echeanciers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_ecritures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_ecritures` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_ecriture` date NOT NULL,
  `journal_id` int unsigned NOT NULL,
  `exercice_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `libelle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('brouillon','valide','extourne','cloture') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'valide',
  `extourne_de` int unsigned DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fec_numero` (`numero`),
  KEY `idx_fec_exercice` (`exercice_id`),
  KEY `idx_fec_periode` (`periode_id`),
  KEY `idx_fec_journal` (`journal_id`),
  KEY `idx_fec_reference` (`reference`),
  KEY `idx_fec_statut` (`statut`),
  KEY `idx_fec_date` (`date_ecriture`),
  CONSTRAINT `fk_fec_exercice` FOREIGN KEY (`exercice_id`) REFERENCES `finance_exercices` (`id`),
  CONSTRAINT `fk_fec_journal` FOREIGN KEY (`journal_id`) REFERENCES `finance_journaux_comptables` (`id`),
  CONSTRAINT `fk_fec_periode` FOREIGN KEY (`periode_id`) REFERENCES `finance_periodes_comptables` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_ecritures` WRITE;
/*!40000 ALTER TABLE `finance_ecritures` DISABLE KEYS */;
INSERT INTO `finance_ecritures` VALUES (2,'ECR-2026-0002','2026-06-15',2,1,10,'Encaissement PAI-2026-0002','PAI-2026-0002','payment_completed','valide',NULL,1,'2026-07-11 21:05:04'),(3,'ECR-2026-0003','2026-06-15',2,1,10,'Encaissement PAI-2026-0003','PAI-2026-0003','payment_completed','valide',NULL,1,'2026-07-11 21:05:04'),(4,'ECR-2026-0004','2026-06-15',2,1,10,'Encaissement PAI-2026-0004','PAI-2026-0004','payment_completed','valide',NULL,1,'2026-07-11 21:05:04'),(5,'ECR-2026-0005','2026-06-20',2,1,10,'Encaissement PAI-2026-0005','PAI-2026-0005','payment_completed','valide',NULL,1,'2026-07-11 21:05:05'),(6,'ECR-2026-0006','2026-06-20',2,1,10,'Encaissement PAI-2026-0006','PAI-2026-0006','payment_completed','valide',NULL,1,'2026-07-11 21:05:05'),(7,'ECR-2026-0007','2026-07-07',2,1,11,'Encaissement PAI-2026-0007','PAI-2026-0007','payment_completed','valide',NULL,1,'2026-07-11 21:05:05'),(8,'ECR-2026-0008','2026-07-07',2,1,11,'Encaissement PAI-2026-0008','PAI-2026-0008','payment_completed','valide',NULL,1,'2026-07-11 21:05:05'),(9,'ECR-2026-0009','2026-07-10',2,1,11,'Encaissement PAI-2026-0009','PAI-2026-0009','payment_completed','valide',NULL,1,'2026-07-11 21:05:05'),(12,'ECR-2026-0012','2026-07-12',2,1,11,'Décaissement DEP-2026-0002 — Deuxieme test decaissement','MVT-2','cash_decaissement_manuel','valide',NULL,4,'2026-07-12 01:37:57');
/*!40000 ALTER TABLE `finance_ecritures` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_exercices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_exercices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = propriété non démontrée',
  `libelle` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `statut` enum('ouvert','cloture','reouvert') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `solde_report` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_cloture` date DEFAULT NULL,
  `cloture_par` int unsigned DEFAULT NULL,
  `ouvert_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fe_statut` (`statut`),
  KEY `idx_fe_date_debut` (`date_debut`),
  KEY `idx_fex_etab` (`etablissement_id`),
  CONSTRAINT `fk_fex_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_exercices` WRITE;
/*!40000 ALTER TABLE `finance_exercices` DISABLE KEYS */;
INSERT INTO `finance_exercices` VALUES (1,1,'Exercice 2025-2026','2025-09-01','2026-08-31','ouvert',0.00,NULL,NULL,NULL,NULL,'2026-07-09 20:40:56','2026-10-28 05:16:16');
/*!40000 ALTER TABLE `finance_exercices` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_factures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_factures` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FCT-AAAA-NNNN — immuable',
  `eleve_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_emission` date NOT NULL DEFAULT (curdate()),
  `date_echeance` date DEFAULT NULL,
  `montant_ht` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ lignes avant remises',
  `montant_remise` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ remises accordées',
  `montant_penalite` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ pénalités appliquées',
  `montant_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'montant_ht - remise + penalite',
  `montant_paye` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Mis à jour par Phase 3.4 (encaissements)',
  `statut` enum('brouillon','emise','partiellement_payee','payee','en_retard','annulee','archive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `origine` enum('operationnelle','migration_v1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operationnelle',
  `migration_source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migration_meta` json DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `emise_par` int unsigned DEFAULT NULL,
  `annulee_par` int unsigned DEFAULT NULL,
  `date_annulation` date DEFAULT NULL,
  `motif_annulation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avoir_id` int unsigned DEFAULT NULL COMMENT 'Avoir émis lors de l''annulation si paiements existants',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `idx_ff_eleve` (`eleve_id`,`annee_scolaire`),
  KEY `idx_ff_statut` (`statut`,`date_echeance`),
  KEY `idx_ff_annee` (`annee_scolaire`),
  KEY `idx_ff_emetteur` (`emise_par`),
  KEY `fk_ff_annuleur` (`annulee_par`),
  KEY `fk_ff_avoir` (`avoir_id`),
  KEY `idx_finance_factures_origine` (`origine`),
  CONSTRAINT `fk_ff_annuleur` FOREIGN KEY (`annulee_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ff_avoir` FOREIGN KEY (`avoir_id`) REFERENCES `finance_avoirs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ff_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ff_emetteur` FOREIGN KEY (`emise_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Factures Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_factures` WRITE;
/*!40000 ALTER TABLE `finance_factures` DISABLE KEYS */;
INSERT INTO `finance_factures` VALUES (3,'FCT-2026-0003',1,'2025-2026','2026-06-25','2026-09-30',5000.00,0.00,0.00,5000.00,5000.00,'payee','migration_v1','frais_eleve:1','{\"migre_le\": \"2026-07-11T20:08:14+00:00\", \"frais_eleve\": {\"id\": 1, \"statut\": \"paye\", \"montant\": \"5000.00\", \"echeance\": \"2026-09-30\", \"eleve_id\": 1, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:39:38\", \"frais_type_id\": 1, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": {\"id\": 1, \"note\": null, \"montant\": \"5000.00\", \"eleve_id\": 1, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 1}, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #1 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:14','2026-07-11 20:08:14'),(4,'FCT-2026-0004',2,'2025-2026','2026-06-25','2026-09-30',5000.00,0.00,0.00,5000.00,5000.00,'payee','migration_v1','frais_eleve:2','{\"migre_le\": \"2026-07-11T20:08:15+00:00\", \"frais_eleve\": {\"id\": 2, \"statut\": \"paye\", \"montant\": \"5000.00\", \"echeance\": \"2026-09-30\", \"eleve_id\": 2, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:39:38\", \"frais_type_id\": 1, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": {\"id\": 2, \"note\": null, \"montant\": \"5000.00\", \"eleve_id\": 2, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 2}, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #2 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:15','2026-07-11 20:08:15'),(5,'FCT-2026-0005',3,'2025-2026','2026-06-25','2026-09-30',5000.00,0.00,0.00,5000.00,5000.00,'payee','migration_v1','frais_eleve:3','{\"migre_le\": \"2026-07-11T20:08:16+00:00\", \"frais_eleve\": {\"id\": 3, \"statut\": \"paye\", \"montant\": \"5000.00\", \"echeance\": \"2026-09-30\", \"eleve_id\": 3, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:39:38\", \"frais_type_id\": 1, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": {\"id\": 3, \"note\": null, \"montant\": \"5000.00\", \"eleve_id\": 3, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 3}, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #3 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:15','2026-07-11 20:08:16'),(6,'FCT-2026-0006',4,'2025-2026','2026-06-25','2026-09-30',5000.00,0.00,0.00,5000.00,0.00,'emise','migration_v1','frais_eleve:4','{\"migre_le\": \"2026-07-11T20:08:16+00:00\", \"frais_eleve\": {\"id\": 4, \"statut\": \"en_attente\", \"montant\": \"5000.00\", \"echeance\": \"2026-09-30\", \"eleve_id\": 4, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:31:06\", \"frais_type_id\": 1, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": null, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #4 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:16','2026-07-11 20:08:16'),(7,'FCT-2026-0007',5,'2025-2026','2026-06-25','2026-09-30',5000.00,0.00,0.00,5000.00,0.00,'emise','migration_v1','frais_eleve:5','{\"migre_le\": \"2026-07-11T20:08:16+00:00\", \"frais_eleve\": {\"id\": 5, \"statut\": \"en_attente\", \"montant\": \"5000.00\", \"echeance\": \"2026-09-30\", \"eleve_id\": 5, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:31:06\", \"frais_type_id\": 1, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": null, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #5 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:16','2026-07-11 20:08:16'),(8,'FCT-2026-0008',6,'2025-2026','2026-06-25','2026-09-30',5000.00,0.00,0.00,5000.00,0.00,'emise','migration_v1','frais_eleve:6','{\"migre_le\": \"2026-07-11T20:08:16+00:00\", \"frais_eleve\": {\"id\": 6, \"statut\": \"en_attente\", \"montant\": \"5000.00\", \"echeance\": \"2026-09-30\", \"eleve_id\": 6, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:31:06\", \"frais_type_id\": 1, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": null, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #6 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:16','2026-07-11 20:08:16'),(9,'FCT-2026-0009',1,'2025-2026','2026-06-25','2026-06-30',30000.00,0.00,0.00,30000.00,15000.00,'partiellement_payee','migration_v1','frais_eleve:8','{\"migre_le\": \"2026-07-11T20:08:17+00:00\", \"frais_eleve\": {\"id\": 8, \"statut\": \"partiel\", \"montant\": \"30000.00\", \"echeance\": \"2026-06-30\", \"eleve_id\": 1, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:39:38\", \"frais_type_id\": 2, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": {\"id\": 4, \"note\": null, \"montant\": \"15000.00\", \"eleve_id\": 1, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-20\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 8}, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #8 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:16','2026-07-11 20:08:17'),(10,'FCT-2026-0010',2,'2025-2026','2026-06-25','2026-06-30',30000.00,0.00,0.00,30000.00,15000.00,'partiellement_payee','migration_v1','frais_eleve:9','{\"migre_le\": \"2026-07-11T20:08:17+00:00\", \"frais_eleve\": {\"id\": 9, \"statut\": \"partiel\", \"montant\": \"30000.00\", \"echeance\": \"2026-06-30\", \"eleve_id\": 2, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:39:38\", \"frais_type_id\": 2, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": {\"id\": 5, \"note\": null, \"montant\": \"15000.00\", \"eleve_id\": 2, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-20\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 9}, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #9 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:17','2026-07-11 20:08:17'),(11,'FCT-2026-0011',3,'2025-2026','2026-06-25','2026-06-30',30000.00,0.00,0.00,30000.00,0.00,'emise','migration_v1','frais_eleve:10','{\"migre_le\": \"2026-07-11T20:08:17+00:00\", \"frais_eleve\": {\"id\": 10, \"statut\": \"en_attente\", \"montant\": \"30000.00\", \"echeance\": \"2026-06-30\", \"eleve_id\": 3, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:31:06\", \"frais_type_id\": 2, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": null, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #10 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:17','2026-07-11 20:08:17'),(12,'FCT-2026-0012',4,'2025-2026','2026-06-25','2026-06-30',30000.00,0.00,0.00,30000.00,0.00,'emise','migration_v1','frais_eleve:11','{\"migre_le\": \"2026-07-11T20:08:18+00:00\", \"frais_eleve\": {\"id\": 11, \"statut\": \"en_attente\", \"montant\": \"30000.00\", \"echeance\": \"2026-06-30\", \"eleve_id\": 4, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:31:06\", \"frais_type_id\": 2, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": null, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #11 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:17','2026-07-11 20:08:18'),(13,'FCT-2026-0013',5,'2025-2026','2026-06-25','2026-06-30',30000.00,0.00,0.00,30000.00,0.00,'emise','migration_v1','frais_eleve:12','{\"migre_le\": \"2026-07-11T20:08:18+00:00\", \"frais_eleve\": {\"id\": 12, \"statut\": \"en_attente\", \"montant\": \"30000.00\", \"echeance\": \"2026-06-30\", \"eleve_id\": 5, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:31:06\", \"frais_type_id\": 2, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": null, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #12 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:18','2026-07-11 20:08:18'),(14,'FCT-2026-0014',6,'2025-2026','2026-06-25','2026-06-30',30000.00,0.00,0.00,30000.00,0.00,'emise','migration_v1','frais_eleve:13','{\"migre_le\": \"2026-07-11T20:08:18+00:00\", \"frais_eleve\": {\"id\": 13, \"statut\": \"en_attente\", \"montant\": \"30000.00\", \"echeance\": \"2026-06-30\", \"eleve_id\": 6, \"created_at\": \"2026-06-25 10:31:06\", \"updated_at\": \"2026-06-25 10:31:06\", \"frais_type_id\": 2, \"annee_scolaire\": \"2025-2026\"}, \"paiement_v1\": null, \"migre_par_user_id\": 1}','Migré depuis frais_eleves V1 #13 le 2026-07-11',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:18','2026-07-11 20:08:19'),(15,'FCT-2026-0015',2,'2025-2026','2026-07-07','2026-07-07',500000.00,0.00,0.00,500000.00,500000.00,'payee','migration_v1','paiement:6','{\"motif\": \"Aucun frais_eleve_id en V1\", \"migre_le\": \"2026-07-11T20:08:19+00:00\", \"migre_par_user_id\": 1, \"paiement_v1_orphelin\": {\"id\": 6, \"note\": null, \"montant\": \"500000.00\", \"eleve_id\": 2, \"reference\": \"123456789\", \"created_at\": \"2026-07-07 01:53:57\", \"encaisse_par\": 2, \"date_paiement\": \"2026-07-07\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": null}}','FACTURE DE RÉGULARISATION — créée automatiquement le 2026-07-11 lors de la migration V1→V2 pour rattacher le paiement V1 #6 (élève #2, 500000.00 XOF, 2026-07-07) qui n\'avait aucune facture ni frais d\'origine identifiable dans le système V1. Aucune perte de données : le paiement V1 original reste consultable, inchangé, dans la table `paiements`.',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:19','2026-07-11 20:08:19'),(16,'FCT-2026-0016',5,'2025-2026','2026-07-07','2026-07-07',345678.00,0.00,0.00,345678.00,345678.00,'payee','migration_v1','paiement:7','{\"motif\": \"Aucun frais_eleve_id en V1\", \"migre_le\": \"2026-07-11T20:08:19+00:00\", \"migre_par_user_id\": 1, \"paiement_v1_orphelin\": {\"id\": 7, \"note\": null, \"montant\": \"345678.00\", \"eleve_id\": 5, \"reference\": \"098765\", \"created_at\": \"2026-07-07 01:55:57\", \"encaisse_par\": 2, \"date_paiement\": \"2026-07-07\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": null}}','FACTURE DE RÉGULARISATION — créée automatiquement le 2026-07-11 lors de la migration V1→V2 pour rattacher le paiement V1 #7 (élève #5, 345678.00 XOF, 2026-07-07) qui n\'avait aucune facture ni frais d\'origine identifiable dans le système V1. Aucune perte de données : le paiement V1 original reste consultable, inchangé, dans la table `paiements`.',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:19','2026-07-11 20:08:20'),(17,'FCT-2026-0017',3,'2025-2026','2026-07-10','2026-07-10',15000.00,0.00,0.00,15000.00,15000.00,'payee','migration_v1','paiement:11','{\"motif\": \"Aucun frais_eleve_id en V1\", \"migre_le\": \"2026-07-11T20:08:20+00:00\", \"migre_par_user_id\": 1, \"paiement_v1_orphelin\": {\"id\": 11, \"note\": null, \"montant\": \"15000.00\", \"eleve_id\": 3, \"reference\": null, \"created_at\": \"2026-07-10 14:09:46\", \"encaisse_par\": 1, \"date_paiement\": \"2026-07-10\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": null}}','FACTURE DE RÉGULARISATION — créée automatiquement le 2026-07-11 lors de la migration V1→V2 pour rattacher le paiement V1 #11 (élève #3, 15000.00 XOF, 2026-07-10) qui n\'avait aucune facture ni frais d\'origine identifiable dans le système V1. Aucune perte de données : le paiement V1 original reste consultable, inchangé, dans la table `paiements`.',1,NULL,NULL,NULL,NULL,'2026-07-11 20:08:20','2026-07-11 20:08:20'),(20,'FCT-2026-0018',1,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(21,'FCT-2026-0019',2,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(22,'FCT-2026-0020',3,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(23,'FCT-2026-0021',4,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(24,'FCT-2026-0022',5,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(25,'FCT-2026-0023',6,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(26,'FCT-2026-0024',7,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(27,'FCT-2026-0025',8,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(28,'FCT-2026-0026',9,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(29,'FCT-2026-0027',10,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(30,'FCT-2026-0028',109,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(31,'FCT-2026-0029',110,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(32,'FCT-2026-0030',111,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(33,'FCT-2026-0031',112,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(34,'FCT-2026-0032',113,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(35,'FCT-2026-0033',114,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(36,'FCT-2026-0034',115,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(37,'FCT-2026-0035',116,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(38,'FCT-2026-0036',117,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(39,'FCT-2026-0037',118,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(40,'FCT-2026-0038',119,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(41,'FCT-2026-0039',120,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08'),(42,'FCT-2026-0040',121,'2026-2027','2026-10-28',NULL,5000.00,0.00,0.00,5000.00,0.00,'brouillon','operationnelle',NULL,NULL,'',1,NULL,NULL,NULL,NULL,'2026-10-28 21:23:08','2026-10-28 21:23:08');
/*!40000 ALTER TABLE `finance_factures` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_fournisseurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_fournisseurs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `iban` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_four_etab_code` (`etablissement_id`,`code`),
  KEY `idx_ff_actif` (`actif`),
  KEY `fk_ff_created_by` (`created_by`),
  KEY `idx_four_etab` (`etablissement_id`),
  CONSTRAINT `fk_ff_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_four_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fournisseurs Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_fournisseurs` WRITE;
/*!40000 ALTER TABLE `finance_fournisseurs` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_fournisseurs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_frais_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_frais_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Code immuable après création (ex: INSCRIPTION_2026)',
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `categorie_id` int unsigned DEFAULT NULL,
  `montant_defaut` decimal(12,2) NOT NULL DEFAULT '0.00',
  `devise` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF' COMMENT 'ISO 4217 : XOF (FCFA), EUR, USD…',
  `periodicite` enum('unique','mensuel','trimestriel','semestriel','annuel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annuel',
  `est_obligatoire` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = frais obligatoire, 0 = optionnel',
  `niveaux_cibles` json DEFAULT NULL COMMENT 'Tableau JSON de niveaux scolaires, NULL = tous niveaux',
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Format AAAA-AAAA, NULL = applicable toutes années',
  `date_limite` date DEFAULT NULL COMMENT 'Date limite de paiement par défaut',
  `peut_avoir_remise` tinyint(1) NOT NULL DEFAULT '1',
  `statut` enum('actif','inactif','archive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ftyp_etab_code` (`etablissement_id`,`code`),
  KEY `idx_fft_categorie` (`categorie_id`),
  KEY `idx_fft_annee` (`annee_scolaire`),
  KEY `idx_fft_statut` (`statut`),
  KEY `idx_fft_obligatoire` (`est_obligatoire`),
  KEY `idx_ftyp_etab` (`etablissement_id`),
  CONSTRAINT `fk_fft_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `finance_categories_frais` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ftyp_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Types de frais Finance V2 — coexiste avec frais_types V1';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_frais_types` WRITE;
/*!40000 ALTER TABLE `finance_frais_types` DISABLE KEYS */;
INSERT INTO `finance_frais_types` VALUES (2,1,'REGUL_MIGRATION','Régularisation — Migration V1→V2','Type technique utilisé uniquement pour les factures de régularisation générées lors de la migration V1→V2.',8,0.00,'XOF','unique',0,NULL,NULL,NULL,0,'actif','2026-07-11 20:08:13','2026-07-11 20:08:13'),(3,1,'V1_FRAISDINSCRIPTION','Frais d\'inscription','Migré depuis frais_types V1 #1.',6,5000.00,'XOF','unique',1,NULL,'2025-2026',NULL,1,'actif','2026-07-11 20:08:14','2026-07-11 20:08:14'),(4,1,'V1_MENSUALITESCOLAIR','Mensualité scolaire','Migré depuis frais_types V1 #2.',1,3000.00,'XOF','mensuel',1,NULL,'2025-2026',NULL,1,'actif','2026-07-11 20:08:14','2026-07-11 20:08:14'),(5,NULL,'REGUL_MIGRATION','Régularisation — Migration V1→V2','Type technique utilisé uniquement pour les factures de régularisation générées lors de la migration V1→V2.',8,0.00,'XOF','unique',0,NULL,NULL,NULL,0,'actif','2026-07-11 20:08:13','2026-07-11 20:08:13'),(6,NULL,'V1_FRAISDINSCRIPTION','Frais d\'inscription','Migré depuis frais_types V1 #1.',6,5000.00,'XOF','unique',1,NULL,'2025-2026',NULL,1,'actif','2026-07-11 20:08:14','2026-07-11 20:08:14'),(7,NULL,'V1_MENSUALITESCOLAIR','Mensualité scolaire','Migré depuis frais_types V1 #2.',1,3000.00,'XOF','mensuel',1,NULL,'2025-2026',NULL,1,'actif','2026-07-11 20:08:14','2026-07-11 20:08:14');
/*!40000 ALTER TABLE `finance_frais_types` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entite_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'frais_type | categorie | tarif',
  `entite_id` int unsigned NOT NULL,
  `action` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create | update | activate | deactivate | archive | delete',
  `avant` json DEFAULT NULL,
  `apres` json DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fhist_entite` (`entite_type`,`entite_id`),
  KEY `idx_fhist_user` (`user_id`),
  KEY `idx_fhist_action` (`action`),
  CONSTRAINT `fk_fhist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique audit Finance — référentiel frais';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_historique` WRITE;
/*!40000 ALTER TABLE `finance_historique` DISABLE KEYS */;
INSERT INTO `finance_historique` VALUES (1,'frais_type',1,'create',NULL,'{\"nom\": \"Frais Test Verification\", \"code\": \"TESTVERIF\", \"devise\": \"XOF\", \"statut\": \"actif\", \"date_limite\": null, \"description\": null, \"periodicite\": \"annuel\", \"categorie_id\": 1, \"annee_scolaire\": \"2025-2026\", \"montant_defaut\": 5000, \"niveaux_cibles\": null, \"est_obligatoire\": 1, \"peut_avoir_remise\": 1}',NULL,'2026-07-11 19:01:39'),(2,'categorie',8,'create',NULL,'{\"nom\": \"Régularisation — Migration V1→V2\", \"code\": \"REGUL_MIGRATION\", \"icone\": \"file-text\", \"couleur\": \"#f59e0b\", \"description\": \"Réservé aux factures générées automatiquement lors de la migration des données financières V1 vers V2, pour des paiements V1 sans facture d\'origine identifiable.\"}',1,'2026-07-11 20:08:13'),(3,'frais_type',2,'create',NULL,'{\"nom\": \"Régularisation — Migration V1→V2\", \"code\": \"REGUL_MIGRATION\", \"devise\": \"XOF\", \"statut\": \"actif\", \"date_limite\": null, \"description\": \"Type technique utilisé uniquement pour les factures de régularisation générées lors de la migration V1→V2.\", \"periodicite\": \"unique\", \"categorie_id\": 8, \"annee_scolaire\": null, \"montant_defaut\": 0, \"niveaux_cibles\": null, \"est_obligatoire\": 0, \"peut_avoir_remise\": 0}',1,'2026-07-11 20:08:13'),(4,'frais_type',3,'create',NULL,'{\"nom\": \"Frais d\'inscription\", \"code\": \"V1_FRAISDINSCRIPTION\", \"devise\": \"XOF\", \"statut\": \"actif\", \"date_limite\": null, \"description\": \"Migré depuis frais_types V1 #1.\", \"periodicite\": \"unique\", \"categorie_id\": 6, \"annee_scolaire\": \"2025-2026\", \"montant_defaut\": 5000, \"niveaux_cibles\": null, \"est_obligatoire\": 1, \"peut_avoir_remise\": 1}',1,'2026-07-11 20:08:14'),(5,'frais_type',4,'create',NULL,'{\"nom\": \"Mensualité scolaire\", \"code\": \"V1_MENSUALITESCOLAIR\", \"devise\": \"XOF\", \"statut\": \"actif\", \"date_limite\": null, \"description\": \"Migré depuis frais_types V1 #2.\", \"periodicite\": \"mensuel\", \"categorie_id\": 1, \"annee_scolaire\": \"2025-2026\", \"montant_defaut\": 3000, \"niveaux_cibles\": null, \"est_obligatoire\": 1, \"peut_avoir_remise\": 1}',1,'2026-07-11 20:08:14');
/*!40000 ALTER TABLE `finance_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_journaux_caisse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_journaux_caisse` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `date_journee` date NOT NULL,
  `session_id` int unsigned DEFAULT NULL,
  `solde_ouverture` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_entrees` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_sorties` decimal(15,2) NOT NULL DEFAULT '0.00',
  `solde_cloture` decimal(15,2) NOT NULL DEFAULT '0.00',
  `statut` enum('ouvert','clos','rapproche') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fjc_date_session` (`date_journee`,`session_id`),
  KEY `fk_fjc_session` (`session_id`),
  KEY `idx_fjc_date` (`date_journee`),
  KEY `idx_fjc_statut` (`statut`),
  CONSTRAINT `fk_fjc_session` FOREIGN KEY (`session_id`) REFERENCES `finance_sessions_caisse` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_journaux_caisse` WRITE;
/*!40000 ALTER TABLE `finance_journaux_caisse` DISABLE KEYS */;
INSERT INTO `finance_journaux_caisse` VALUES (1,'2026-07-11',NULL,0.00,0.00,0.00,0.00,'ouvert',NULL,'2026-07-11 19:48:15','2026-10-26 18:50:18'),(2,'2026-07-12',NULL,100000.00,0.00,0.00,100000.00,'ouvert',NULL,'2026-07-12 01:37:41','2026-07-12 01:37:41');
/*!40000 ALTER TABLE `finance_journaux_caisse` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_journaux_comptables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_journaux_comptables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = propriété non démontrée',
  `code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('vente','achat','caisse','banque','operations_diverses') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fjo_etab_code` (`etablissement_id`,`code`),
  KEY `idx_fjo_etab` (`etablissement_id`),
  CONSTRAINT `fk_fjo_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_journaux_comptables` WRITE;
/*!40000 ALTER TABLE `finance_journaux_comptables` DISABLE KEYS */;
INSERT INTO `finance_journaux_comptables` VALUES (1,1,'VTE','Journal des ventes (scolarité)','vente',1),(2,1,'CAI','Journal de caisse','caisse',1),(3,1,'BNQ','Journal de banque','banque',1),(4,1,'ACH','Journal des achats','achat',1),(5,1,'OD','Journal des opérations diverses','operations_diverses',1);
/*!40000 ALTER TABLE `finance_journaux_comptables` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_justificatifs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_justificatifs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `decaissement_id` int unsigned NOT NULL,
  `nom_fichier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relatif à ROOT_PATH via UploadService',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `taille` int unsigned NOT NULL,
  `uploade_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fj_decaissement` (`decaissement_id`),
  KEY `fk_fj_uploade_par` (`uploade_par`),
  CONSTRAINT `fk_fj_decaissement` FOREIGN KEY (`decaissement_id`) REFERENCES `finance_decaissements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fj_uploade_par` FOREIGN KEY (`uploade_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Justificatifs des décaissements Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_justificatifs` WRITE;
/*!40000 ALTER TABLE `finance_justificatifs` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_justificatifs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_lignes_ecriture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_lignes_ecriture` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ecriture_id` int unsigned NOT NULL,
  `compte_id` int unsigned NOT NULL,
  `libelle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fle_ecriture` (`ecriture_id`),
  KEY `idx_fle_compte` (`compte_id`),
  CONSTRAINT `fk_fle_compte` FOREIGN KEY (`compte_id`) REFERENCES `finance_comptes` (`id`),
  CONSTRAINT `fk_fle_ecriture` FOREIGN KEY (`ecriture_id`) REFERENCES `finance_ecritures` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_lignes_ecriture` WRITE;
/*!40000 ALTER TABLE `finance_lignes_ecriture` DISABLE KEYS */;
INSERT INTO `finance_lignes_ecriture` VALUES (3,2,11,'Encaissement PAI-2026-0002',5000.00,0.00,1,'2026-07-11 21:05:04'),(4,2,21,'Frais scolaires PAI-2026-0002',0.00,5000.00,1,'2026-07-11 21:05:04'),(5,3,11,'Encaissement PAI-2026-0003',5000.00,0.00,1,'2026-07-11 21:05:04'),(6,3,21,'Frais scolaires PAI-2026-0003',0.00,5000.00,1,'2026-07-11 21:05:04'),(7,4,11,'Encaissement PAI-2026-0004',5000.00,0.00,1,'2026-07-11 21:05:04'),(8,4,21,'Frais scolaires PAI-2026-0004',0.00,5000.00,1,'2026-07-11 21:05:04'),(9,5,11,'Encaissement PAI-2026-0005',15000.00,0.00,1,'2026-07-11 21:05:05'),(10,5,21,'Frais scolaires PAI-2026-0005',0.00,15000.00,1,'2026-07-11 21:05:05'),(11,6,11,'Encaissement PAI-2026-0006',15000.00,0.00,1,'2026-07-11 21:05:05'),(12,6,21,'Frais scolaires PAI-2026-0006',0.00,15000.00,1,'2026-07-11 21:05:05'),(13,7,11,'Encaissement PAI-2026-0007',500000.00,0.00,1,'2026-07-11 21:05:05'),(14,7,21,'Frais scolaires PAI-2026-0007',0.00,500000.00,1,'2026-07-11 21:05:05'),(15,8,11,'Encaissement PAI-2026-0008',345678.00,0.00,1,'2026-07-11 21:05:05'),(16,8,21,'Frais scolaires PAI-2026-0008',0.00,345678.00,1,'2026-07-11 21:05:05'),(17,9,11,'Encaissement PAI-2026-0009',15000.00,0.00,1,'2026-07-11 21:05:05'),(18,9,21,'Frais scolaires PAI-2026-0009',0.00,15000.00,1,'2026-07-11 21:05:06'),(23,12,18,'Décaissement DEP-2026-0002 — Deuxieme test decaissement',15000.00,0.00,4,'2026-07-12 01:37:57'),(24,12,11,'Décaissement DEP-2026-0002 — Deuxieme test decaissement',0.00,15000.00,4,'2026-07-12 01:37:57');
/*!40000 ALTER TABLE `finance_lignes_ecriture` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_lignes_facture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_lignes_facture` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facture_id` int unsigned NOT NULL,
  `frais_type_id` int unsigned DEFAULT NULL COMMENT 'NULL = ligne libre sans référence au référentiel',
  `libelle` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` decimal(8,2) NOT NULL DEFAULT '1.00',
  `montant_unitaire` decimal(12,2) NOT NULL,
  `montant_remise` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Remise directe sur cette ligne',
  `montant_total` decimal(12,2) NOT NULL COMMENT '(quantite × unitaire) - remise_ligne',
  `ordre` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flf_facture` (`facture_id`),
  KEY `idx_flf_frais_type` (`frais_type_id`),
  CONSTRAINT `fk_flf_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flf_frais_type` FOREIGN KEY (`frais_type_id`) REFERENCES `finance_frais_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lignes de facture';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_lignes_facture` WRITE;
/*!40000 ALTER TABLE `finance_lignes_facture` DISABLE KEYS */;
INSERT INTO `finance_lignes_facture` VALUES (3,3,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-07-11 20:08:14'),(4,4,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-07-11 20:08:15'),(5,5,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-07-11 20:08:15'),(6,6,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-07-11 20:08:16'),(7,7,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-07-11 20:08:16'),(8,8,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-07-11 20:08:16'),(9,9,4,'Mensualité scolaire',1.00,30000.00,0.00,30000.00,1,'2026-07-11 20:08:16'),(10,10,4,'Mensualité scolaire',1.00,30000.00,0.00,30000.00,1,'2026-07-11 20:08:17'),(11,11,4,'Mensualité scolaire',1.00,30000.00,0.00,30000.00,1,'2026-07-11 20:08:17'),(12,12,4,'Mensualité scolaire',1.00,30000.00,0.00,30000.00,1,'2026-07-11 20:08:17'),(13,13,4,'Mensualité scolaire',1.00,30000.00,0.00,30000.00,1,'2026-07-11 20:08:18'),(14,14,4,'Mensualité scolaire',1.00,30000.00,0.00,30000.00,1,'2026-07-11 20:08:18'),(15,15,2,'RÉGULARISATION — Paiement V1 #6 sans facture d\'origine (généré automatiquement lors de la migration V1→V2 du 2026-07-11)',1.00,500000.00,0.00,500000.00,1,'2026-07-11 20:08:19'),(16,16,2,'RÉGULARISATION — Paiement V1 #7 sans facture d\'origine (généré automatiquement lors de la migration V1→V2 du 2026-07-11)',1.00,345678.00,0.00,345678.00,1,'2026-07-11 20:08:19'),(17,17,2,'RÉGULARISATION — Paiement V1 #11 sans facture d\'origine (généré automatiquement lors de la migration V1→V2 du 2026-07-11)',1.00,15000.00,0.00,15000.00,1,'2026-07-11 20:08:20'),(20,20,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(21,21,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(22,22,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(23,23,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(24,24,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(25,25,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(26,26,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(27,27,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(28,28,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(29,29,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(30,30,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(31,31,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(32,32,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(33,33,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(34,34,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(35,35,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(36,36,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(37,37,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(38,38,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(39,39,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(40,40,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(41,41,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08'),(42,42,3,'Frais d\'inscription',1.00,5000.00,0.00,5000.00,1,'2026-10-28 21:23:08');
/*!40000 ALTER TABLE `finance_lignes_facture` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_modes_paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_modes_paiement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` enum('caisse','banque','avoir') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'caisse',
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'credit-card',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` tinyint NOT NULL DEFAULT '99',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mpaie_etab_code` (`etablissement_id`,`code`),
  KEY `idx_mpaie_etab` (`etablissement_id`),
  CONSTRAINT `fk_mpaie_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modes de paiement disponibles';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_modes_paiement` WRITE;
/*!40000 ALTER TABLE `finance_modes_paiement` DISABLE KEYS */;
INSERT INTO `finance_modes_paiement` VALUES (1,1,'ESP','caisse','Espèces',NULL,'banknote',1,1),(2,NULL,'CHQ','banque','Chèque',NULL,'receipt',1,2),(3,NULL,'VIR','banque','Virement bancaire',NULL,'arrow-right-left',1,3),(4,NULL,'CB','banque','Carte bancaire',NULL,'credit-card',1,4),(5,NULL,'OM','caisse','Orange Money',NULL,'smartphone',1,5),(6,NULL,'WAVE','caisse','Wave',NULL,'waves',1,6),(7,NULL,'MOOV','caisse','Moov Money',NULL,'smartphone',1,7),(8,NULL,'AVOIR','avoir','Avoir / Crédit',NULL,'badge-percent',1,8),(25,NULL,'ESP','caisse','Espèces',NULL,'banknote',1,1),(26,1,'CHQ','banque','Chèque',NULL,'receipt',1,2),(27,1,'VIR','banque','Virement bancaire',NULL,'arrow-right-left',1,3),(28,1,'CB','banque','Carte bancaire',NULL,'credit-card',1,4),(29,1,'OM','caisse','Orange Money',NULL,'smartphone',1,5),(30,1,'WAVE','caisse','Wave',NULL,'waves',1,6),(31,1,'MOOV','caisse','Moov Money',NULL,'smartphone',1,7),(32,1,'AVOIR','avoir','Avoir / Crédit',NULL,'badge-percent',1,8);
/*!40000 ALTER TABLE `finance_modes_paiement` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_mouvements_caisse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_mouvements_caisse` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int unsigned NOT NULL,
  `type` enum('recette','decaissement','correction','ouverture','fermeture','annulation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sens` enum('credit','debit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paiement_id` int unsigned DEFAULT NULL COMMENT 'Lié à finance_paiements si source=paiement',
  `source` enum('manuel','paiement','decaissement','systeme') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manuel',
  `statut` enum('actif','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `annule_par` int unsigned DEFAULT NULL,
  `motif_annulation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_annulation` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_fmc_annule` (`annule_par`),
  KEY `fk_fmc_creator` (`created_by`),
  KEY `idx_fmc_session` (`session_id`),
  KEY `idx_fmc_type` (`type`),
  KEY `idx_fmc_statut` (`statut`),
  KEY `idx_fmc_paiement` (`paiement_id`),
  CONSTRAINT `fk_fmc_annule` FOREIGN KEY (`annule_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fmc_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fmc_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `finance_paiements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fmc_session` FOREIGN KEY (`session_id`) REFERENCES `finance_sessions_caisse` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_mouvements_caisse` WRITE;
/*!40000 ALTER TABLE `finance_mouvements_caisse` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_mouvements_caisse` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_paiements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_paiements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PAI-AAAA-NNNN — immuable',
  `facture_id` int unsigned NOT NULL,
  `mode_paiement_id` int unsigned DEFAULT NULL,
  `montant` decimal(12,2) NOT NULL COMMENT 'Montant brut encaissé',
  `montant_applique` decimal(12,2) NOT NULL COMMENT 'Montant imputé sur la facture (≤ montant)',
  `reference_externe` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N° chèque, réf virement, réf mobile…',
  `date_paiement` date NOT NULL,
  `statut` enum('initie','valide','complete','annule','rembourse') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initie',
  `origine` enum('operationnelle','migration_v1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operationnelle',
  `migration_source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migration_meta` json DEFAULT NULL,
  `echeance_id` int unsigned DEFAULT NULL COMMENT 'Échéance ciblée (nullable = facture entière)',
  `avoir_id` int unsigned DEFAULT NULL COMMENT 'Avoir utilisé comme mode de paiement',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `encaisse_par` int unsigned DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `date_validation` date DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `date_annulation` date DEFAULT NULL,
  `motif_annulation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `idx_fp_facture` (`facture_id`,`statut`),
  KEY `idx_fp_date` (`date_paiement`,`statut`),
  KEY `idx_fp_mode` (`mode_paiement_id`),
  KEY `idx_fp_echeance` (`echeance_id`),
  KEY `fk_fp_avoir` (`avoir_id`),
  KEY `fk_fp_caissier` (`encaisse_par`),
  KEY `fk_fp_valideur` (`valide_par`),
  KEY `fk_fp_annuleur` (`annule_par`),
  KEY `idx_finance_paiements_origine` (`origine`),
  CONSTRAINT `fk_fp_annuleur` FOREIGN KEY (`annule_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fp_avoir` FOREIGN KEY (`avoir_id`) REFERENCES `finance_avoirs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fp_caissier` FOREIGN KEY (`encaisse_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fp_echeance` FOREIGN KEY (`echeance_id`) REFERENCES `finance_echeances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fp_mode` FOREIGN KEY (`mode_paiement_id`) REFERENCES `finance_modes_paiement` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fp_valideur` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fpaie_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paiements Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_paiements` WRITE;
/*!40000 ALTER TABLE `finance_paiements` DISABLE KEYS */;
INSERT INTO `finance_paiements` VALUES (2,'PAI-2026-0002',3,1,5000.00,5000.00,'','2026-06-15','complete','migration_v1','paiement:1','{\"migre_le\": \"2026-07-11T20:08:15+00:00\", \"paiement\": {\"id\": 1, \"note\": null, \"montant\": \"5000.00\", \"eleve_id\": 1, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 1}, \"migre_par_user_id\": 1}',NULL,NULL,'Migré depuis paiements V1 #1',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:14','2026-07-11 20:08:15'),(3,'PAI-2026-0003',4,1,5000.00,5000.00,'','2026-06-15','complete','migration_v1','paiement:2','{\"migre_le\": \"2026-07-11T20:08:15+00:00\", \"paiement\": {\"id\": 2, \"note\": null, \"montant\": \"5000.00\", \"eleve_id\": 2, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 2}, \"migre_par_user_id\": 1}',NULL,NULL,'Migré depuis paiements V1 #2',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:15','2026-07-11 20:08:15'),(4,'PAI-2026-0004',5,1,5000.00,5000.00,'','2026-06-15','complete','migration_v1','paiement:3','{\"migre_le\": \"2026-07-11T20:08:16+00:00\", \"paiement\": {\"id\": 3, \"note\": null, \"montant\": \"5000.00\", \"eleve_id\": 3, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-15\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 3}, \"migre_par_user_id\": 1}',NULL,NULL,'Migré depuis paiements V1 #3',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:16','2026-07-11 20:08:16'),(5,'PAI-2026-0005',9,1,15000.00,15000.00,'','2026-06-20','complete','migration_v1','paiement:4','{\"migre_le\": \"2026-07-11T20:08:17+00:00\", \"paiement\": {\"id\": 4, \"note\": null, \"montant\": \"15000.00\", \"eleve_id\": 1, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-20\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 8}, \"migre_par_user_id\": 1}',NULL,NULL,'Migré depuis paiements V1 #4',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:17','2026-07-11 20:08:17'),(6,'PAI-2026-0006',10,1,15000.00,15000.00,'','2026-06-20','complete','migration_v1','paiement:5','{\"migre_le\": \"2026-07-11T20:08:17+00:00\", \"paiement\": {\"id\": 5, \"note\": null, \"montant\": \"15000.00\", \"eleve_id\": 2, \"reference\": null, \"created_at\": \"2026-06-25 10:39:38\", \"encaisse_par\": 1, \"date_paiement\": \"2026-06-20\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": 9}, \"migre_par_user_id\": 1}',NULL,NULL,'Migré depuis paiements V1 #5',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:17','2026-07-11 20:08:17'),(7,'PAI-2026-0007',15,1,500000.00,500000.00,'123456789','2026-07-07','complete','migration_v1','paiement:6','{\"migre_le\": \"2026-07-11T20:08:19+00:00\", \"migre_par_user_id\": 1, \"paiement_v1_orphelin\": {\"id\": 6, \"note\": null, \"montant\": \"500000.00\", \"eleve_id\": 2, \"reference\": \"123456789\", \"created_at\": \"2026-07-07 01:53:57\", \"encaisse_par\": 2, \"date_paiement\": \"2026-07-07\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": null}}',NULL,NULL,'RÉGULARISATION — Migré depuis paiements V1 #6 (sans facture d\'origine V1)',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:19','2026-07-11 20:08:19'),(8,'PAI-2026-0008',16,1,345678.00,345678.00,'098765','2026-07-07','complete','migration_v1','paiement:7','{\"migre_le\": \"2026-07-11T20:08:20+00:00\", \"migre_par_user_id\": 1, \"paiement_v1_orphelin\": {\"id\": 7, \"note\": null, \"montant\": \"345678.00\", \"eleve_id\": 5, \"reference\": \"098765\", \"created_at\": \"2026-07-07 01:55:57\", \"encaisse_par\": 2, \"date_paiement\": \"2026-07-07\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": null}}',NULL,NULL,'RÉGULARISATION — Migré depuis paiements V1 #7 (sans facture d\'origine V1)',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:20','2026-07-11 20:08:20'),(9,'PAI-2026-0009',17,1,15000.00,15000.00,'','2026-07-10','complete','migration_v1','paiement:11','{\"migre_le\": \"2026-07-11T20:08:20+00:00\", \"migre_par_user_id\": 1, \"paiement_v1_orphelin\": {\"id\": 11, \"note\": null, \"montant\": \"15000.00\", \"eleve_id\": 3, \"reference\": null, \"created_at\": \"2026-07-10 14:09:46\", \"encaisse_par\": 1, \"date_paiement\": \"2026-07-10\", \"mode_paiement\": \"especes\", \"annee_scolaire\": \"2025-2026\", \"frais_eleve_id\": null}}',NULL,NULL,'RÉGULARISATION — Migré depuis paiements V1 #11 (sans facture d\'origine V1)',1,1,'2026-07-11',NULL,NULL,NULL,'2026-07-11 20:08:20','2026-07-11 20:08:20');
/*!40000 ALTER TABLE `finance_paiements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_penalites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_penalites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facture_id` int unsigned NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nb_jours` smallint unsigned DEFAULT NULL COMMENT 'Nombre de jours de retard',
  `applique_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fp_facture` (`facture_id`),
  KEY `fk_fp_user` (`applique_par`),
  CONSTRAINT `fk_fp_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fp_user` FOREIGN KEY (`applique_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pénalités de retard appliquées sur factures';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_penalites` WRITE;
/*!40000 ALTER TABLE `finance_penalites` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_penalites` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_periodes_comptables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_periodes_comptables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exercice_id` int unsigned NOT NULL,
  `numero` tinyint unsigned NOT NULL,
  `libelle` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `statut` enum('ouverte','cloturee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `cloture_par` int unsigned DEFAULT NULL,
  `cloture_le` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fpc_exercice_num` (`exercice_id`,`numero`),
  KEY `idx_fpc_exercice` (`exercice_id`),
  KEY `idx_fpc_dates` (`date_debut`,`date_fin`),
  CONSTRAINT `fk_fpc_exercice` FOREIGN KEY (`exercice_id`) REFERENCES `finance_exercices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_periodes_comptables` WRITE;
/*!40000 ALTER TABLE `finance_periodes_comptables` DISABLE KEYS */;
INSERT INTO `finance_periodes_comptables` VALUES (1,1,1,'Septembre 2025','2025-09-01','2025-09-30','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(2,1,2,'Octobre 2025','2025-10-01','2025-10-31','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(3,1,3,'Novembre 2025','2025-11-01','2025-11-30','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(4,1,4,'Décembre 2025','2025-12-01','2025-12-31','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(5,1,5,'Janvier 2026','2026-01-01','2026-01-31','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(6,1,6,'Février 2026','2026-02-01','2026-02-28','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(7,1,7,'Mars 2026','2026-03-01','2026-03-31','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(8,1,8,'Avril 2026','2026-04-01','2026-04-30','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(9,1,9,'Mai 2026','2026-05-01','2026-05-31','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(10,1,10,'Juin 2026','2026-06-01','2026-06-30','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(11,1,11,'Juillet 2026','2026-07-01','2026-07-31','ouverte',NULL,NULL,'2026-07-09 20:40:56'),(12,1,12,'Août 2026','2026-08-01','2026-08-31','ouverte',NULL,NULL,'2026-07-09 20:40:56');
/*!40000 ALTER TABLE `finance_periodes_comptables` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_recus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_recus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'REC-AAAA-NNNN — immuable',
  `paiement_id` int unsigned NOT NULL,
  `facture_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `date_emission` date NOT NULL DEFAULT (curdate()),
  `emis_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  UNIQUE KEY `uq_recu_paiement` (`paiement_id`),
  KEY `idx_recu_facture` (`facture_id`),
  KEY `idx_recu_eleve` (`eleve_id`),
  KEY `fk_recu_emetteur` (`emis_par`),
  CONSTRAINT `fk_recu_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_recu_emetteur` FOREIGN KEY (`emis_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_recu_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_recu_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `finance_paiements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reçus de paiement Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_recus` WRITE;
/*!40000 ALTER TABLE `finance_recus` DISABLE KEYS */;
INSERT INTO `finance_recus` VALUES (2,'REC-2026-0002',2,3,1,5000.00,'2026-07-11',1,'2026-07-11 20:08:15'),(3,'REC-2026-0003',3,4,2,5000.00,'2026-07-11',1,'2026-07-11 20:08:15'),(4,'REC-2026-0004',4,5,3,5000.00,'2026-07-11',1,'2026-07-11 20:08:16'),(5,'REC-2026-0005',5,9,1,15000.00,'2026-07-11',1,'2026-07-11 20:08:17'),(6,'REC-2026-0006',6,10,2,15000.00,'2026-07-11',1,'2026-07-11 20:08:17'),(7,'REC-2026-0007',7,15,2,500000.00,'2026-07-11',1,'2026-07-11 20:08:19'),(8,'REC-2026-0008',8,16,5,345678.00,'2026-07-11',1,'2026-07-11 20:08:20'),(9,'REC-2026-0009',9,17,3,15000.00,'2026-07-11',1,'2026-07-11 20:08:20');
/*!40000 ALTER TABLE `finance_recus` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_regles_comptables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_regles_comptables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = propriété non démontrée',
  `type_source` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `compte_debit_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `compte_credit_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `journal_code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fre_etab_type_source` (`etablissement_id`,`type_source`),
  KEY `idx_fre_etab` (`etablissement_id`),
  CONSTRAINT `fk_fre_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_regles_comptables` WRITE;
/*!40000 ALTER TABLE `finance_regles_comptables` DISABLE KEYS */;
INSERT INTO `finance_regles_comptables` VALUES (1,1,'payment_esp','Paiement en espèces ou mobile money (ESP/OM/WAVE/MOOV) → Caisse débitée, Produits scolarité crédités','5300','7010','CAI',1),(2,1,'payment_bnq','Paiement bancaire (CHQ/VIR/CB) → Banque débitée, Produits scolarité crédités','5120','7010','BNQ',1),(3,1,'payment_avoir','Paiement par consommation d\'avoir → Avoirs élèves débit, Produits scolarité crédités','4190','7010','OD',1),(4,1,'refund_esp','Remboursement en espèces → Produits scolarité débit, Caisse créditée','7010','5300','CAI',1),(5,1,'refund_bnq','Remboursement bancaire → Produits scolarité débit, Banque créditée','7010','5120','BNQ',1),(6,1,'invoice_cancelled','Facture annulée → Produits débit, Avoirs élèves crédités','7010','4190','OD',1),(7,1,'cash_recette_manuel','Recette manuelle en caisse → Caisse débitée, Produits divers crédités','5300','7500','CAI',1),(8,1,'cash_decaissement_manuel','Décaissement manuel en caisse → Charges débitées, Caisse créditée','6500','5300','CAI',1),(9,1,'expense_validated','Dépense validée → Charges débitées, Fournisseur ou Caisse crédité','6010','4010','ACH',1);
/*!40000 ALTER TABLE `finance_regles_comptables` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_regles_exoneration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_regles_exoneration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type_remise` enum('pourcentage','montant_fixe','exoneration') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pourcentage',
  `valeur` decimal(10,2) NOT NULL DEFAULT '0.00',
  `niveaux` json DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Règles exonération — stub Phase 3.4, enrichi en Phase 3.8';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_regles_exoneration` WRITE;
/*!40000 ALTER TABLE `finance_regles_exoneration` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_regles_exoneration` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_remboursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_remboursements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `paiement_id` int unsigned NOT NULL COMMENT 'Paiement faisant l''objet du remboursement',
  `trop_percu_id` int unsigned DEFAULT NULL COMMENT 'Trop-perçu à l''origine (si applicable)',
  `eleve_id` int unsigned NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `mode_remboursement` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ESP',
  `reference_remboursement` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('initie','valide','complete','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initie',
  `realise_par` int unsigned DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `date_realisation` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rmbrs_paiement` (`paiement_id`),
  KEY `idx_rmbrs_eleve` (`eleve_id`),
  KEY `fk_rmbrs_trop_percu` (`trop_percu_id`),
  KEY `fk_rmbrs_auteur` (`realise_par`),
  KEY `fk_rmbrs_valideur` (`valide_par`),
  CONSTRAINT `fk_rmbrs_auteur` FOREIGN KEY (`realise_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rmbrs_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rmbrs_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `finance_paiements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rmbrs_trop_percu` FOREIGN KEY (`trop_percu_id`) REFERENCES `finance_trop_percus` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rmbrs_valideur` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Remboursements Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_remboursements` WRITE;
/*!40000 ALTER TABLE `finance_remboursements` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_remboursements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_remises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_remises` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facture_id` int unsigned NOT NULL,
  `libelle` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_remise` enum('pourcentage','montant_fixe','exoneration') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'montant_fixe',
  `valeur` decimal(10,2) NOT NULL COMMENT '% ou montant selon type',
  `montant_calcule` decimal(12,2) NOT NULL COMMENT 'Montant effectivement soustrait',
  `regle_exo_id` int unsigned DEFAULT NULL,
  `justificatif` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accordee_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fr_facture` (`facture_id`),
  KEY `fk_fr_regle` (`regle_exo_id`),
  KEY `fk_fr_auteur` (`accordee_par`),
  CONSTRAINT `fk_fr_auteur` FOREIGN KEY (`accordee_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fr_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fr_regle` FOREIGN KEY (`regle_exo_id`) REFERENCES `finance_regles_exoneration` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Remises accordées sur factures';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_remises` WRITE;
/*!40000 ALTER TABLE `finance_remises` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_remises` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_sequences` (
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FCT | AVO | REC | DEP',
  `annee` smallint NOT NULL COMMENT 'Année civile ex: 2026',
  `valeur` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`type`,`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Séquences Finance — incrémentées atomiquement';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_sequences` WRITE;
/*!40000 ALTER TABLE `finance_sequences` DISABLE KEYS */;
INSERT INTO `finance_sequences` VALUES ('CSE',2026,2),('ECR',2026,15),('FCT',2026,40),('PAI',2026,9),('REC',2026,9);
/*!40000 ALTER TABLE `finance_sequences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_sessions_caisse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_sessions_caisse` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CSE-YYYY-NNNN',
  `caissier_id` int unsigned NOT NULL,
  `statut` enum('ouverte','en_activite','fermee','annulee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `date_ouverture` date NOT NULL,
  `heure_ouverture` time NOT NULL,
  `date_fermeture` date DEFAULT NULL,
  `heure_fermeture` time DEFAULT NULL,
  `solde_initial` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_recettes` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_decaissements` decimal(15,2) NOT NULL DEFAULT '0.00',
  `solde_theorique` decimal(15,2) NOT NULL DEFAULT '0.00',
  `solde_reel` decimal(15,2) DEFAULT NULL,
  `ecart` decimal(15,2) DEFAULT NULL,
  `note_ouverture` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `note_fermeture` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ouvert_par` int unsigned NOT NULL,
  `ferme_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `fk_fsc_ouvert` (`ouvert_par`),
  KEY `fk_fsc_ferme` (`ferme_par`),
  KEY `idx_fsc_caissier` (`caissier_id`),
  KEY `idx_fsc_statut` (`statut`),
  KEY `idx_fsc_date` (`date_ouverture`),
  CONSTRAINT `fk_fsc_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fsc_ferme` FOREIGN KEY (`ferme_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fsc_ouvert` FOREIGN KEY (`ouvert_par`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_sessions_caisse` WRITE;
/*!40000 ALTER TABLE `finance_sessions_caisse` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_sessions_caisse` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_tarifs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_tarifs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `frais_type_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL = toutes les classes pour ce frais',
  `classe_id` int unsigned DEFAULT NULL COMMENT 'NULL = tout le niveau (ou tous si niveau aussi NULL)',
  `montant` decimal(12,2) NOT NULL,
  `devise` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarif` (`frais_type_id`,`annee_scolaire`,`niveau`,`classe_id`),
  KEY `idx_tarif_frais` (`frais_type_id`),
  KEY `fk_tarif_classe` (`classe_id`),
  CONSTRAINT `fk_tarif_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tarif_frais_type` FOREIGN KEY (`frais_type_id`) REFERENCES `finance_frais_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifs par niveau/classe — surcharge le montant_defaut';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_tarifs` WRITE;
/*!40000 ALTER TABLE `finance_tarifs` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_tarifs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `finance_trop_percus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_trop_percus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `paiement_id` int unsigned NOT NULL,
  `facture_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `montant_excedent` decimal(12,2) NOT NULL,
  `statut` enum('en_attente','restitue','impute','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `traite_par` int unsigned DEFAULT NULL,
  `date_traitement` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tp_eleve` (`eleve_id`),
  KEY `idx_tp_facture` (`facture_id`),
  KEY `fk_tp_paiement` (`paiement_id`),
  KEY `fk_tp_traiteur` (`traite_par`),
  CONSTRAINT `fk_tp_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tp_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tp_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `finance_paiements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tp_traiteur` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trop-perçus Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `finance_trop_percus` WRITE;
/*!40000 ALTER TABLE `finance_trop_percus` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_trop_percus` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `frais_eleves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frais_eleves` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `frais_type_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `echeance` date DEFAULT NULL,
  `statut` enum('en_attente','partiel','paye') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frais_eleve` (`eleve_id`,`frais_type_id`,`annee_scolaire`),
  KEY `idx_fe_annee` (`annee_scolaire`),
  KEY `idx_fe_statut` (`statut`),
  KEY `fk_fe_type` (`frais_type_id`),
  CONSTRAINT `fk_fe_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fe_type` FOREIGN KEY (`frais_type_id`) REFERENCES `frais_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `frais_eleves` WRITE;
/*!40000 ALTER TABLE `frais_eleves` DISABLE KEYS */;
INSERT INTO `frais_eleves` VALUES (1,1,1,'2025-2026',5000.00,'2026-09-30','paye','2026-06-25 09:31:06','2026-06-25 09:39:38'),(2,2,1,'2025-2026',5000.00,'2026-09-30','paye','2026-06-25 09:31:06','2026-06-25 09:39:38'),(3,3,1,'2025-2026',5000.00,'2026-09-30','paye','2026-06-25 09:31:06','2026-06-25 09:39:38'),(4,4,1,'2025-2026',5000.00,'2026-09-30','en_attente','2026-06-25 09:31:06','2026-06-25 09:31:06'),(5,5,1,'2025-2026',5000.00,'2026-09-30','en_attente','2026-06-25 09:31:06','2026-06-25 09:31:06'),(6,6,1,'2025-2026',5000.00,'2026-09-30','en_attente','2026-06-25 09:31:06','2026-06-25 09:31:06'),(8,1,2,'2025-2026',30000.00,'2026-06-30','partiel','2026-06-25 09:31:06','2026-06-25 09:39:38'),(9,2,2,'2025-2026',30000.00,'2026-06-30','partiel','2026-06-25 09:31:06','2026-06-25 09:39:38'),(10,3,2,'2025-2026',30000.00,'2026-06-30','en_attente','2026-06-25 09:31:06','2026-06-25 09:31:06'),(11,4,2,'2025-2026',30000.00,'2026-06-30','en_attente','2026-06-25 09:31:06','2026-06-25 09:31:06'),(12,5,2,'2025-2026',30000.00,'2026-06-30','en_attente','2026-06-25 09:31:06','2026-06-25 09:31:06'),(13,6,2,'2025-2026',30000.00,'2026-06-30','en_attente','2026-06-25 09:31:06','2026-06-25 09:31:06');
/*!40000 ALTER TABLE `frais_eleves` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `frais_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frais_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `montant_defaut` decimal(10,2) NOT NULL DEFAULT '0.00',
  `periodicite` enum('unique','mensuel','trimestriel','annuel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annuel',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `frais_types` WRITE;
/*!40000 ALTER TABLE `frais_types` DISABLE KEYS */;
INSERT INTO `frais_types` VALUES (1,'Frais d\'inscription','Inscription annuelle',5000.00,'unique',1,'2026-06-25 09:31:06'),(2,'Mensualité scolaire','Frais mensuels d\'enseignement',3000.00,'mensuel',1,'2026-06-25 09:31:06'),(3,'Transport scolaire','Navette domicile-école',2000.00,'mensuel',1,'2026-06-25 09:31:06'),(4,'Cantine scolaire','Repas du midi',1500.00,'mensuel',1,'2026-06-25 09:31:06'),(5,'Activités sportives','EPS et activités extérieures',500.00,'trimestriel',1,'2026-06-25 09:31:06'),(6,'Fournitures scolaires','Manuels et fournitures',2500.00,'annuel',1,'2026-06-25 09:31:06'),(7,'Frais d\'inscription','Inscription annuelle',5000.00,'unique',1,'2026-06-25 09:39:38'),(8,'Mensualité scolaire','Frais mensuels d\'enseignement',3000.00,'mensuel',1,'2026-06-25 09:39:38'),(9,'Transport scolaire','Navette domicile-école',2000.00,'mensuel',1,'2026-06-25 09:39:38'),(10,'Cantine scolaire','Repas du midi',1500.00,'mensuel',1,'2026-06-25 09:39:38'),(11,'Activités sportives','EPS et activités extérieures',500.00,'trimestriel',1,'2026-06-25 09:39:38'),(12,'Fournitures scolaires','Manuels et fournitures',2500.00,'annuel',1,'2026-06-25 09:39:38');
/*!40000 ALTER TABLE `frais_types` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `inscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL COMMENT 'R├®f├®rence vers eleves.id',
  `classe_id` int unsigned DEFAULT NULL COMMENT 'Classe cible (peut ├¬tre NULL si en_attente)',
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ann├®e scolaire au format YYYY-YYYY (ex: 2025-2026)',
  `statut` enum('en_attente','validee','rejetee','annulee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente' COMMENT 'Cycle : en_attente ÔåÆ validee/rejetee ; validee ÔåÆ annulee',
  `motif_rejet` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Renseign├® lors du rejet ou de l''annulation',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observations internes (non visible parent/├®l├¿ve)',
  `inscription_par` int unsigned DEFAULT NULL COMMENT 'ID de l''utilisateur ayant cr├®├® l''inscription',
  `valide_par` int unsigned DEFAULT NULL COMMENT 'ID de l''utilisateur ayant valid├® ou rejet├®',
  `valide_le` datetime DEFAULT NULL COMMENT 'Horodatage de la validation ou du rejet',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_insc_classe` (`classe_id`),
  KEY `idx_insc_eleve` (`eleve_id`),
  KEY `idx_insc_annee` (`annee_scolaire`),
  KEY `idx_insc_statut` (`statut`),
  KEY `idx_insc_eleve_annee` (`eleve_id`,`annee_scolaire`),
  CONSTRAINT `fk_insc_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_insc_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscriptions scolaires V2 ÔÇö une par ├®l├¿ve par ann├®e scolaire (statut actif)';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `inscriptions` WRITE;
/*!40000 ALTER TABLE `inscriptions` DISABLE KEYS */;
INSERT INTO `inscriptions` VALUES (1,1,131,'2025-2026','validee',NULL,NULL,NULL,NULL,NULL,'2026-07-22 02:13:24','2026-07-22 02:13:24'),(2,2,131,'2025-2026','validee',NULL,NULL,NULL,NULL,NULL,'2026-07-22 02:13:24','2026-07-22 02:13:24'),(3,3,131,'2025-2026','validee',NULL,NULL,NULL,NULL,NULL,'2026-07-22 02:13:24','2026-07-22 02:13:24'),(4,4,131,'2025-2026','validee',NULL,NULL,NULL,NULL,NULL,'2026-07-22 02:13:24','2026-07-22 02:13:24'),(5,6,131,'2025-2026','validee',NULL,NULL,NULL,NULL,NULL,'2026-07-22 02:13:24','2026-07-22 02:13:24'),(16,109,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(17,110,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(18,111,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(19,112,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(20,113,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(21,114,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(22,115,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(23,116,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(24,117,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(25,118,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(26,119,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13'),(27,120,132,'2026-2027','validee',NULL,'Jeu de données bulletin S1/S2',1,1,'2026-08-14 02:08:13','2026-08-14 01:08:13','2026-08-14 01:08:13');
/*!40000 ALTER TABLE `inscriptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL,
  `type` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `status` enum('pending','processing','done','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `max_attempts` tinyint unsigned NOT NULL DEFAULT '3',
  `run_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_etab` (`etablissement_id`),
  KEY `idx_status_runat` (`status`,`run_at`),
  CONSTRAINT `fk63_jobs_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=321 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `matieres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matieres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coefficient` decimal(3,1) NOT NULL DEFAULT '1.0',
  `categorie` enum('litteraire','scientifique','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'autre',
  `est_conduite` tinyint unsigned DEFAULT NULL COMMENT '1 = matière de Conduite (note de comportement), NULL = matière ordinaire',
  `volume_horaire` tinyint unsigned NOT NULL DEFAULT '2' COMMENT 'Heures par semaine',
  `responsable_id` int unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matiere_conduite` (`etablissement_id`,`est_conduite`),
  KEY `fk_mat_resp` (`responsable_id`),
  KEY `idx_etab` (`etablissement_id`),
  CONSTRAINT `fk_mat_resp` FOREIGN KEY (`responsable_id`) REFERENCES `professeurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_matieres_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=295 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `matieres` WRITE;
/*!40000 ALTER TABLE `matieres` DISABLE KEYS */;
INSERT INTO `matieres` VALUES (1,1,'Mathématiques',5.0,'scientifique',NULL,5,1,NULL,'2026-06-25 09:30:33'),(3,1,'Arabe',3.0,'litteraire',NULL,5,3,NULL,'2026-06-25 09:30:33'),(4,1,'Français',3.0,'litteraire',NULL,4,4,NULL,'2026-06-25 09:30:33'),(5,1,'Anglais',2.0,'litteraire',NULL,3,5,NULL,'2026-06-25 09:30:33'),(6,1,'Histoire-Géographie',2.0,'litteraire',NULL,2,7,NULL,'2026-06-25 09:30:33'),(7,1,'Sciences Naturelles',3.0,'scientifique',NULL,3,6,NULL,'2026-06-25 09:30:33'),(8,1,'Philosophie',2.0,'litteraire',NULL,2,8,NULL,'2026-06-25 09:30:33'),(187,1,'S.V.T',3.0,'scientifique',NULL,2,NULL,NULL,'2026-07-20 00:51:50'),(188,1,'Physique-Chimie',4.0,'scientifique',NULL,2,NULL,NULL,'2026-07-20 00:51:50'),(189,1,'EPS',1.0,'autre',NULL,2,NULL,NULL,'2026-07-20 00:51:50'),(190,1,'Conduite',1.0,'autre',1,0,NULL,NULL,'2026-07-20 00:51:50');
/*!40000 ALTER TABLE `matieres` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_matieres_conduite_bi` BEFORE INSERT ON `matieres` FOR EACH ROW BEGIN
  IF NEW.est_conduite IS NOT NULL AND (NEW.est_conduite <> 1 OR NEW.coefficient <> 1 OR NEW.volume_horaire <> 0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conduite : le marqueur vaut 1, le coefficient 1 et le volume horaire 0.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_matieres_conduite_bu` BEFORE UPDATE ON `matieres` FOR EACH ROW BEGIN
  IF OLD.est_conduite = 1 THEN
    IF NOT (NEW.est_conduite <=> 1) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conduite : le marqueur ne peut pas etre retire ni modifie.';
    END IF;
    IF NEW.coefficient <> 1 OR NEW.volume_horaire <> 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conduite : coefficient 1 et volume horaire 0 imposes.';
    END IF;
    IF NEW.etablissement_id <> OLD.etablissement_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conduite : changement d etablissement interdit.';
    END IF;
  ELSEIF NEW.est_conduite IS NOT NULL THEN
    IF NEW.est_conduite <> 1 OR NEW.coefficient <> 1 OR NEW.volume_horaire <> 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conduite : le marqueur vaut 1, le coefficient 1 et le volume horaire 0.';
    END IF;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_matieres_conduite_bd` BEFORE DELETE ON `matieres` FOR EACH ROW BEGIN
  IF OLD.est_conduite = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conduite : la matiere de Conduite ne peut pas etre supprimee.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `migrations_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `duration_ms` int unsigned DEFAULT NULL,
  `status` enum('success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations_log` WRITE;
/*!40000 ALTER TABLE `migrations_log` DISABLE KEYS */;
INSERT INTO `migrations_log` VALUES (1,'M001','Migration sécurisée notes → schéma V2 (controle_id)','2026-06-30 03:03:42',34,'success',NULL),(2,'M002','Migration sécurisée absences → schéma V2 (session, type, statut_justif)','2026-06-30 03:03:42',2,'success',NULL),(3,'M003','Créer le référentiel annees_scolaires','2026-06-30 03:03:43',74,'success',NULL),(4,'R001','Tables RBAC V2 (rbac_roles, rbac_role_permissions, rbac_user_roles)','2026-06-30 03:03:44',1033,'success',NULL),(5,'S001','Créer la table audit_logs','2026-06-30 03:03:44',75,'success',NULL),(6,'P001','Correction RBAC — bulletins.view pour eleve et parent','2026-06-30 03:25:03',9,'success',NULL),(7,'T001','Tables Platform (etablissements, platform_plans, platform_operators)','2026-07-08 04:13:56',6024,'success',NULL),(8,'T002','Schéma RBAC multi-tenant (etab_roles, etab_permissions, etab_role_permissions)','2026-07-08 04:14:01',2950,'success',NULL),(9,'T003','Tables appartenance multi-établissements (user_etablissements, user_roles_etab)','2026-07-08 04:14:06',2393,'success',NULL),(10,'T004','Tables configuration tenant (etab_settings, etablissement_branding, etablissement_domains)','2026-07-08 04:14:11',2653,'success',NULL),(11,'T005','Seeds fondation tenant (établissement démo, plans, permissions, rôles)','2026-07-08 04:14:20',7120,'success',NULL),(12,'T006','etablissement_id sur tables V1 (users, classes, eleves, professeurs, matieres, enseignements, notes, absences, periodes)','2026-07-08 05:03:13',42269,'success',NULL),(13,'T007','Peuplement RBAC tenant (etab_role_permissions, user_roles_etab)','2026-07-08 09:51:15',9236,'success',NULL),(15,'T008','Seed branding établissement démo (etablissement_branding + etab_settings)','2026-07-08 11:03:32',291,'success',NULL),(16,'T009','Permissions branding.view / branding.update (etab_permissions, etab_role_permissions)','2026-07-08 11:12:43',65,'success',NULL),(17,'T010','users.dernier_etablissement_id (mémorisation du dernier établissement)','2026-07-08 13:04:44',555,'success',NULL),(18,'T011','etablissement_domains.verification_token (challenge DNS)','2026-07-08 14:37:15',3356,'success',NULL),(19,'T012','Permissions domains.view / domains.manage (etab_permissions, etab_role_permissions)','2026-07-08 14:44:51',150,'success',NULL),(20,'T013','api_uploads (ledger stockage) + etablissements.max_documents/max_attachments','2026-07-08 15:08:16',16078,'success',NULL),(21,'T014','Permission quota.view (etab_permissions, etab_role_permissions)','2026-07-08 15:13:31',107,'success',NULL),(22,'T015','jobs (file d\'attente tenant-scopée)','2026-07-08 16:12:56',448,'success',NULL),(23,'T016','Permission monitoring.view (etab_permissions, etab_role_permissions)','2026-07-08 16:20:53',35,'success',NULL),(24,'T017','etablissements.statut+archived, platform_analytics_snapshots, bootstrap super_admin','2026-07-08 16:45:45',1067,'success',NULL),(25,'T018','platform_backups (§19.4) + platform_restores','2026-07-08 17:15:57',1136,'success',NULL),(26,'T019','Nomenclature des classes — système éducatif nigérien','2026-07-09 16:09:03',26,'success',NULL),(27,'T020','Nomenclature des classes — complément Primaire/Collège (Niger)','2026-07-09 17:09:55',62,'success',NULL),(28,'T021','Périodes scolaires — ENUM trimestre uniquement (Niger)','2026-07-09 18:10:18',240,'success',NULL),(29,'T022','Périodes scolaires V2 — schéma + configuration par défaut Niger','2026-07-09 18:51:33',3877,'success',NULL),(30,'T023','finance_modes_paiement — colonne categorie (caisse/banque)','2026-07-09 20:43:06',69,'success',NULL),(32,'T024','finance_modes_paiement — categorie ENUM + \'avoir\' (corrige T023)','2026-07-09 20:45:59',45,'success',NULL),(33,'T025','Permissions settings.general.* (Configuration générale établissement)','2026-07-09 20:51:35',14,'success',NULL),(34,'T026','Paramètres métier ERP — nouvelles permissions + révocation domaines/quotas/monitoring','2026-07-09 23:22:18',532,'success',NULL),(35,'T027','Annonces — ciblage par classe et par utilisateur','2026-07-10 02:26:59',4784,'success',NULL),(36,'T028','Absences — créneau précis de l\'emploi du temps','2026-07-10 06:28:40',1,'success',NULL),(38,'T029','Absences — correctif créneau + unicité par créneau (multi-créneaux/jour)','2026-07-10 06:43:01',132,'success',NULL),(41,'T030','Réinscription — journal des passages de classe','2026-07-10 22:56:34',8237,'success',NULL),(42,'T031','Suppression table morte etablissement_domains','2026-07-10 23:29:51',78,'success',NULL),(43,'T032','Notifications — types retard/discipline/recompense','2026-07-10 23:40:51',67,'success',NULL),(44,'T033','Finance — colonnes de traçabilité migration V1→V2','2026-07-11 19:58:51',3477,'success',NULL),(45,'T034','Matières — colonne categorie (littéraire/scientifique/autre)','2026-07-16 09:57:03',708,'success',NULL),(46,'T035','Modèle par défaut des périodes — semestre (La Persévérance)','2026-07-21 23:52:54',33,'success',NULL),(47,'T036','Matières — classification initiale littéraire/scientifique','2026-07-22 04:46:36',413,'success',NULL),(48,'M007','Ajout eleves.user_id (lien compte utilisateur élève)','2026-07-30 22:37:02',1507,'success',NULL),(50,'T037','Absences — migration des données V1 (type=absence) vers vs_absences V2','2026-08-20 21:58:38',143,'success',NULL),(51,'T038','Emplois du temps — référentiel salles/créneaux V1 vers V2','2026-08-20 23:27:02',60,'success',NULL),(52,'T039','Retards — reliquat V1 (type=retard, table absences) vers vs_retards V2','2026-08-21 00:03:10',12,'success',NULL),(53,'T040','Correction justification absence migrée (donnée réelle depuis justifications V1)','2026-08-21 00:20:29',36,'success',NULL),(54,'T041','Suppression des tables V1 : absences, justifications, emplois_du_temps, salles, creneaux','2026-08-21 00:23:16',1526,'success',NULL),(55,'T042','Matières — socle des 8 matières (Français, Histoire-Géo, Anglais, Maths, S.V.T, Physique-Chimie, Éducation Physique, Conduite)','2026-08-28 03:16:25',260,'success',NULL),(56,'T043','Matières — renommage « Éducation Physique » → « EPS »','2026-09-01 07:54:52',12,'success',NULL),(57,'T044','Restauration table etablissement_domains (régression T031)','2026-10-24 16:45:46',96,'success',NULL),(58,'T045','Retrait bulletins.view aux rôles système parent et eleve','2026-10-24 17:15:16',5,'success',NULL),(59,'T046','Bulletins : token_version et table bulletin_tokens_retired (schéma additif)','2026-10-25 00:19:16',101,'success',NULL),(60,'T048','Matières — marqueur est_conduite (identification de la Conduite)','2026-10-25 17:01:09',259,'success',NULL),(61,'T049','Permission academique.conduite.manage (admin, directeur)','2026-10-25 17:01:09',6,'success',NULL),(62,'T050','Matières — déclencheurs de protection de la Conduite','2026-10-25 17:01:10',55,'success',NULL),(63,'T059','Permissions académiques manquantes (bulletin.admin, evaluations.admin, notes.admin, periodes.admin, moyennes/classement.view)','2026-10-26 08:09:23',61,'success',NULL),(64,'T060','Tables vs_appels, vs_presences, vs_presences_historique (rattrapage du .sql hors runner)','2026-10-26 18:50:17',233,'success',NULL),(65,'T061','Collations utf8mb4_unicode_ci (7 tables) + 3 index redondants','2026-10-26 18:50:18',647,'success',NULL),(66,'T062','Archive puis retire les lignes orphelines (notification_logs, vs_incidents_discipline, finance_journaux_caisse)','2026-10-26 18:50:18',118,'success',NULL),(67,'T051','Périodes scolaires — colonne etablissement_id et rattachement démontré','2026-10-28 05:16:08',419,'success',NULL),(68,'T052','Types d\'évaluation — colonne etablissement_id, catalogue système et rattachement démontré','2026-10-28 05:16:09',596,'success',NULL),(69,'T053','Configuration des périodes — copie par établissement','2026-10-28 05:16:10',269,'success',NULL),(70,'T054','Vie scolaire — catalogues par établissement (motifs, catégories) : rattachement démontré et copies','2026-10-28 05:16:11',993,'success',NULL),(71,'T055','Vie scolaire — salles et plages horaires par établissement (rattachement démontré par les créneaux)','2026-10-28 05:16:12',338,'success',NULL),(72,'T056','Comptes parents — rôle RBAC et rattachement à l\'établissement (démontré par les enfants)','2026-10-28 05:16:12',78,'success',NULL),(73,'T057','Finance — catalogues par établissement (frais, catégories, modes de paiement, fournisseurs) : rattachement démontré et copies','2026-10-28 05:16:14',1487,'success',NULL),(74,'T058','Finance — comptabilité par établissement (exercices, plan comptable, journaux, règles)','2026-10-28 05:16:16',898,'success',NULL),(77,'T063','Clés étrangères manquantes (47 colonnes *_id), sans orphelin, sans suppression de données','2026-10-28 16:03:49',4794,'success',NULL);
/*!40000 ALTER TABLE `migrations_log` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `moyennes_generales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moyennes_generales` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `moyenne_generale` decimal(5,2) DEFAULT NULL,
  `rang` smallint unsigned DEFAULT NULL,
  `mention` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mg` (`eleve_id`,`classe_id`,`periode_id`),
  KEY `fk_mg_classe` (`classe_id`),
  KEY `fk_mg_periode` (`periode_id`),
  CONSTRAINT `fk_mg_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mg_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mg_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `moyennes_generales` WRITE;
/*!40000 ALTER TABLE `moyennes_generales` DISABLE KEYS */;
/*!40000 ALTER TABLE `moyennes_generales` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `moyennes_matieres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moyennes_matieres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `moyenne` decimal(5,2) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mm` (`eleve_id`,`matiere_id`,`classe_id`,`periode_id`),
  KEY `fk_mm_matiere` (`matiere_id`),
  KEY `fk_mm_classe` (`classe_id`),
  KEY `fk_mm_periode` (`periode_id`),
  CONSTRAINT `fk_mm_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mm_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mm_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mm_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `moyennes_matieres` WRITE;
/*!40000 ALTER TABLE `moyennes_matieres` DISABLE KEYS */;
/*!40000 ALTER TABLE `moyennes_matieres` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `controle_id` int unsigned NOT NULL,
  `note` decimal(5,2) DEFAULT NULL COMMENT 'NULL = non encore saisie',
  `absent` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = absent => compte comme 0',
  `appreciation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note` (`eleve_id`,`controle_id`),
  KEY `fk_note_controle` (`controle_id`),
  KEY `idx_etab` (`etablissement_id`,`eleve_id`),
  CONSTRAINT `fk_note_controle` FOREIGN KEY (`controle_id`) REFERENCES `controles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_note_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES (1,1,1,1,14.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(2,1,1,2,16.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(3,1,1,3,13.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(4,1,1,4,12.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(5,1,1,5,11.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(6,1,1,6,15.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(7,1,1,7,14.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(8,1,1,8,13.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(9,1,1,9,12.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(10,1,2,1,17.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(11,1,2,2,18.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(12,1,2,3,16.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(13,1,2,4,15.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(14,1,2,5,16.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(15,1,2,6,18.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(16,1,2,7,17.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(17,1,2,8,16.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(18,1,2,9,17.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(19,1,4,1,10.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(20,1,4,2,11.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(21,1,4,3,9.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(22,1,4,4,13.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(23,1,4,5,12.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(24,1,4,6,14.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(25,1,4,7,13.50,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(26,1,4,8,11.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03'),(27,1,4,9,10.00,0,NULL,'2026-06-25 09:39:33','2026-07-08 05:03:03');
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notes_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `note_id` int unsigned NOT NULL,
  `valeur_avant` decimal(5,2) DEFAULT NULL,
  `valeur_apres` decimal(5,2) DEFAULT NULL,
  `absent_avant` tinyint(1) NOT NULL DEFAULT '0',
  `absent_apres` tinyint(1) NOT NULL DEFAULT '0',
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned DEFAULT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_note` (`note_id`),
  CONSTRAINT `fk_hist_note` FOREIGN KEY (`note_id`) REFERENCES `notes_v2` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notes_historique` WRITE;
/*!40000 ALTER TABLE `notes_historique` DISABLE KEYS */;
/*!40000 ALTER TABLE `notes_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notes_v2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes_v2` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `valeur` decimal(5,2) DEFAULT NULL COMMENT 'NULL = absent ou non attribu├®e',
  `est_absent` tinyint(1) NOT NULL DEFAULT '0',
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statut` enum('saisie','publiee','verrouillee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'saisie',
  `verrouille_par` int unsigned DEFAULT NULL,
  `verrouille_le` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note_eval_eleve` (`evaluation_id`,`eleve_id`),
  KEY `idx_note_eleve` (`eleve_id`),
  KEY `idx_note_statut` (`statut`),
  KEY `idx_note_created_by` (`created_by`),
  CONSTRAINT `fk_note_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_notev2_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=589 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notes_v2` WRITE;
/*!40000 ALTER TABLE `notes_v2` DISABLE KEYS */;
INSERT INTO `notes_v2` VALUES (1,1,1,12.38,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(2,1,2,15.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(3,1,3,8.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(4,1,4,16.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(5,1,6,10.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(6,2,1,6.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(7,2,2,10.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(8,2,3,5.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(9,2,4,12.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(10,2,6,8.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(11,3,1,16.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(12,3,2,17.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(13,3,3,13.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(14,3,4,18.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(15,3,6,14.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(16,4,1,17.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(17,4,2,18.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(18,4,3,15.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(19,4,4,19.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(20,4,6,16.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(21,5,1,3.75,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(22,5,2,9.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(23,5,3,6.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(24,5,4,13.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(25,5,6,7.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(26,6,1,12.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(27,6,2,14.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(28,6,3,9.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(29,6,4,16.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(30,6,6,11.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(31,7,1,12.13,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(32,7,2,13.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(33,7,3,8.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(34,7,4,15.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(35,7,6,10.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(36,8,1,8.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(37,8,2,11.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(38,8,3,7.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(39,8,4,14.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(40,8,6,9.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 01:51:51','2026-07-20 01:51:51'),(41,9,1,10.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(42,9,2,13.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(43,9,3,7.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(44,9,4,15.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(45,9,6,9.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(46,10,1,8.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(47,10,2,11.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(48,10,3,6.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(49,10,4,13.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(50,10,6,7.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(51,11,1,15.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(52,11,2,16.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(53,11,3,12.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(54,11,4,17.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(55,11,6,13.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(56,12,1,16.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(57,12,2,17.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(58,12,3,14.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(59,12,4,18.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(60,12,6,15.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(61,13,1,6.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(62,13,2,10.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(63,13,3,5.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(64,13,4,12.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(65,13,6,6.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(66,14,1,10.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(67,14,2,12.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(68,14,3,8.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(69,14,4,14.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(70,14,6,9.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(71,15,1,9.75,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(72,15,2,11.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(73,15,3,7.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(74,15,4,14.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(75,15,6,9.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(76,16,1,11.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(77,16,2,13.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(78,16,3,9.00,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(79,16,4,13.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(80,16,6,8.50,0,NULL,'publiee',NULL,NULL,NULL,'2026-07-20 02:11:03','2026-07-20 02:11:03'),(115,40,109,16.84,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(116,40,110,15.70,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(117,40,111,14.16,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(118,40,112,13.72,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(119,40,113,12.08,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(120,40,114,10.54,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(121,40,115,10.00,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(122,40,116,8.66,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(123,40,117,8.22,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(124,40,118,6.68,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(125,40,119,5.14,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(126,40,120,4.70,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(127,41,109,17.14,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(128,41,110,16.00,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(129,41,111,14.46,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(130,41,112,14.02,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(131,41,113,12.38,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(132,41,114,10.84,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(133,41,115,10.30,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(134,41,116,8.96,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(135,41,117,8.52,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(136,41,118,6.98,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(137,41,119,5.44,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(138,41,120,5.00,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(139,42,109,16.34,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(140,42,110,15.20,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(141,42,111,13.66,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(142,42,112,13.22,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(143,42,113,11.58,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(144,42,114,10.04,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(145,42,115,9.50,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(146,42,116,8.16,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(147,42,117,7.72,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(148,42,118,6.18,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(149,42,119,4.64,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(150,42,120,4.20,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(151,43,109,16.64,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:13','2026-08-14 02:29:25'),(152,43,110,15.50,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(153,43,111,13.96,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(154,43,112,13.52,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(155,43,113,11.88,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(156,43,114,10.34,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(157,43,115,9.80,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(158,43,116,8.46,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(159,43,117,8.02,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(160,43,118,6.48,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(161,43,119,4.94,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(162,43,120,4.50,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(163,44,109,17.80,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(164,44,110,15.56,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(165,44,111,15.12,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(166,44,112,13.58,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(167,44,113,11.94,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(168,44,114,11.50,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(169,44,115,9.86,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(170,44,116,9.62,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(171,44,117,8.08,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(172,44,118,6.54,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(173,44,119,6.10,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(174,44,120,4.56,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(175,45,109,18.10,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(176,45,110,15.86,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(177,45,111,15.42,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(178,45,112,13.88,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(179,45,113,12.24,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(180,45,114,11.80,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(181,45,115,10.16,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(182,45,116,9.92,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(183,45,117,8.38,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(184,45,118,6.84,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(185,45,119,6.40,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(186,45,120,4.86,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(187,46,109,17.16,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(188,46,110,16.02,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(189,46,111,14.48,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(190,46,112,12.94,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(191,46,113,12.40,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(192,46,114,10.86,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(193,46,115,10.32,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(194,46,116,8.98,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(195,46,117,7.44,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(196,46,118,7.00,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(197,46,119,5.46,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(198,46,120,5.02,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(199,47,109,17.46,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(200,47,110,16.32,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(201,47,111,14.78,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(202,47,112,13.24,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(203,47,113,12.70,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(204,47,114,11.16,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(205,47,115,10.62,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(206,47,116,9.28,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(207,47,117,7.74,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(208,47,118,7.30,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(209,47,119,5.76,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(210,47,120,5.32,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(211,48,109,17.56,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(212,48,110,16.42,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(213,48,111,14.88,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(214,48,112,13.34,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(215,48,113,12.80,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(216,48,114,11.26,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(217,48,115,10.72,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(218,48,116,9.38,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(219,48,117,7.84,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(220,48,118,7.40,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(221,48,119,5.86,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(222,48,120,5.42,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(223,49,109,17.86,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(224,49,110,16.72,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(225,49,111,15.18,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(226,49,112,13.64,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(227,49,113,13.10,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(228,49,114,11.56,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(229,49,115,11.02,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(230,49,116,9.68,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(231,49,117,8.14,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(232,49,118,7.70,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(233,49,119,6.16,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(234,49,120,5.72,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(235,50,109,16.76,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(236,50,110,15.62,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(237,50,111,14.08,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(238,50,112,12.54,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(239,50,113,12.00,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(240,50,114,10.46,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(241,50,115,9.92,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(242,50,116,8.58,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(243,50,117,7.04,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(244,50,118,6.60,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(245,50,119,5.06,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:25'),(246,50,120,4.62,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(247,51,109,17.06,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(248,51,110,15.92,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(249,51,111,14.38,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(250,51,112,12.84,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(251,51,113,12.30,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(252,51,114,10.76,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(253,51,115,10.22,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(254,51,116,8.88,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(255,51,117,7.34,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(256,51,118,6.90,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(257,51,119,5.36,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(258,51,120,4.92,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(259,52,109,18.92,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(260,52,110,16.68,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(261,52,111,15.14,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(262,52,112,14.70,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(263,52,113,13.06,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(264,52,114,12.62,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(265,52,115,10.98,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(266,52,116,9.64,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(267,52,117,9.20,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(268,52,118,7.66,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(269,52,119,7.22,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(270,52,120,5.68,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(271,53,109,19.22,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(272,53,110,16.98,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(273,53,111,15.44,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(274,53,112,15.00,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(275,53,113,13.36,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(276,53,114,12.92,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(277,53,115,11.28,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(278,53,116,9.94,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(279,53,117,9.50,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(280,53,118,7.96,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(281,53,119,7.52,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(282,53,120,5.98,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(283,54,109,18.52,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(284,54,110,16.28,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(285,54,111,14.74,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(286,54,112,14.30,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(287,54,113,12.66,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(288,54,114,12.22,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(289,54,115,10.58,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(290,54,116,9.24,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(291,54,117,8.80,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(292,54,118,7.26,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(293,54,119,6.82,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(294,54,120,5.28,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(295,55,109,18.82,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(296,55,110,16.58,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(297,55,111,15.04,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(298,55,112,14.60,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(299,55,113,12.96,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(300,55,114,12.52,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(301,55,115,10.88,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(302,55,116,9.54,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(303,55,117,9.10,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(304,55,118,7.56,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(305,55,119,7.12,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(306,55,120,5.58,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(307,56,109,17.31,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(308,56,110,16.17,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(309,56,111,14.63,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(310,56,112,13.09,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(311,56,113,12.55,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(312,56,114,11.01,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(313,56,115,10.47,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(314,56,116,9.13,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(315,56,117,7.59,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(316,56,118,7.15,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(317,56,119,5.61,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(318,56,120,5.17,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(319,57,109,17.61,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(320,57,110,16.47,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(321,57,111,14.93,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(322,57,112,13.39,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(323,57,113,12.85,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(324,57,114,11.31,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(325,57,115,10.77,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(326,57,116,9.43,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(327,57,117,7.89,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(328,57,118,7.45,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(329,57,119,5.91,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(330,57,120,5.47,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(331,58,109,16.81,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(332,58,110,15.67,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(333,58,111,14.13,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(334,58,112,12.59,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(335,58,113,12.05,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(336,58,114,10.51,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(337,58,115,9.97,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(338,58,116,8.63,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(339,58,117,7.09,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(340,58,118,6.65,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(341,58,119,5.11,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(342,58,120,4.67,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(343,59,109,17.11,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(344,59,110,15.97,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(345,59,111,14.43,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(346,59,112,12.89,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(347,59,113,12.35,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(348,59,114,10.81,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(349,59,115,10.27,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(350,59,116,8.93,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(351,59,117,7.39,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(352,59,118,6.95,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(353,59,119,5.41,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(354,59,120,4.97,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(355,60,109,18.27,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(356,60,110,16.03,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(357,60,111,14.49,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(358,60,112,14.05,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(359,60,113,12.41,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(360,60,114,11.97,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(361,60,115,10.33,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(362,60,116,8.99,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(363,60,117,8.55,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(364,60,118,7.01,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(365,60,119,6.57,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(366,60,120,5.03,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(367,61,109,18.57,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(368,61,110,16.33,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(369,61,111,14.79,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(370,61,112,14.35,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(371,61,113,12.71,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(372,61,114,12.27,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(373,61,115,10.63,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(374,61,116,9.29,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(375,61,117,8.85,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(376,61,118,7.31,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(377,61,119,6.87,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(378,61,120,5.33,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(379,62,109,17.63,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(380,62,110,15.39,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(381,62,111,14.95,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(382,62,112,13.41,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(383,62,113,12.87,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(384,62,114,11.33,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(385,62,115,9.69,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(386,62,116,9.45,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(387,62,117,7.91,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(388,62,118,7.47,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(389,62,119,5.93,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(390,62,120,4.39,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(391,63,109,17.93,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(392,63,110,15.69,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(393,63,111,15.25,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(394,63,112,13.71,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(395,63,113,13.17,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(396,63,114,11.63,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(397,63,115,9.99,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(398,63,116,9.75,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(399,63,117,8.21,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(400,63,118,7.77,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(401,63,119,6.23,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(402,63,120,4.69,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(403,64,109,18.03,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(404,64,110,15.79,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(405,64,111,15.35,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(406,64,112,13.81,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(407,64,113,13.27,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(408,64,114,11.73,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(409,64,115,10.09,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(410,64,116,9.85,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(411,64,117,8.31,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(412,64,118,7.87,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(413,64,119,6.33,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(414,64,120,4.79,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(415,65,109,18.33,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(416,65,110,16.09,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(417,65,111,15.65,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(418,65,112,14.11,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(419,65,113,13.57,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(420,65,114,12.03,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(421,65,115,10.39,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(422,65,116,10.15,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(423,65,117,8.61,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(424,65,118,8.17,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(425,65,119,6.63,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(426,65,120,5.09,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(427,66,109,17.23,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(428,66,110,14.99,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(429,66,111,14.55,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(430,66,112,13.01,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(431,66,113,12.47,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(432,66,114,10.93,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(433,66,115,9.29,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(434,66,116,9.05,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(435,66,117,7.51,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(436,66,118,7.07,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(437,66,119,5.53,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(438,66,120,3.99,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(439,67,109,17.53,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(440,67,110,15.29,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(441,67,111,14.85,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(442,67,112,13.31,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(443,67,113,12.77,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(444,67,114,11.23,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(445,67,115,9.59,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(446,67,116,9.35,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(447,67,117,7.81,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(448,67,118,7.37,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(449,67,119,5.83,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(450,67,120,4.29,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(451,68,109,18.29,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(452,68,110,17.15,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(453,68,111,15.61,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(454,68,112,15.17,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(455,68,113,13.53,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(456,68,114,11.99,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(457,68,115,11.45,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(458,68,116,10.11,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(459,68,117,9.67,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(460,68,118,8.13,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(461,68,119,6.59,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(462,68,120,6.15,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(463,69,109,18.59,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(464,69,110,17.45,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(465,69,111,15.91,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(466,69,112,15.47,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(467,69,113,13.83,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(468,69,114,12.29,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(469,69,115,11.75,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(470,69,116,10.41,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(471,69,117,9.97,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(472,69,118,8.43,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(473,69,119,6.89,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(474,69,120,6.45,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(475,70,109,17.89,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(476,70,110,16.75,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(477,70,111,15.21,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(478,70,112,14.77,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(479,70,113,13.13,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(480,70,114,11.59,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(481,70,115,11.05,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(482,70,116,9.71,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(483,70,117,9.27,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(484,70,118,7.73,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(485,70,119,6.19,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(486,70,120,5.75,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(487,71,109,18.19,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(488,71,110,17.05,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(489,71,111,15.51,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(490,71,112,15.07,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(491,71,113,13.43,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(492,71,114,11.89,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(493,71,115,11.35,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(494,71,116,10.01,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(495,71,117,9.57,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(496,71,118,8.03,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(497,71,119,6.49,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26'),(498,71,120,6.05,0,'Jeu de données bulletin','publiee',NULL,NULL,1,'2026-08-14 02:08:14','2026-08-14 02:29:26');
/*!40000 ALTER TABLE `notes_v2` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `trigger_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` enum('interne','email','sms') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `destinataire` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'email ou num├®ro de t├®l├®phone',
  `statut` enum('envoye','echoue','en_attente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'envoye',
  `erreur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_trigger` (`trigger_type`),
  KEY `idx_canal` (`canal`),
  KEY `idx_statut` (`statut`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk63_notification_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
INSERT INTO `notification_logs` VALUES (2,1,'annonce','email','Test de notification — EduNova','qwertyuioiuytrfdsdxcvbnm,.,kmjhgfv','admin@ecole.dz','echoue','Échec mail()','2026-07-10 03:46:21'),(4,1,'annonce','interne','Test de notification — EduNova','vos maudia',NULL,'envoye',NULL,'2026-07-10 04:07:14'),(5,3,'annonce','interne','Nouvelle annonce : merde','vos maudia',NULL,'envoye',NULL,'2026-07-10 04:12:39'),(6,4,'annonce','interne','Nouvelle annonce : merde','vos maudia',NULL,'envoye',NULL,'2026-07-10 04:12:39'),(7,5,'annonce','interne','Nouvelle annonce : merde','vos maudia',NULL,'envoye',NULL,'2026-07-10 04:12:39'),(8,6,'annonce','interne','Nouvelle annonce : merde','vos maudia',NULL,'envoye',NULL,'2026-07-10 04:12:40'),(9,7,'annonce','interne','Nouvelle annonce : merde','vos maudia',NULL,'envoye',NULL,'2026-07-10 04:12:40'),(11,3,'annonce','interne','Nouvelle annonce : TOKTOK','Allo Allo',NULL,'envoye',NULL,'2026-07-10 04:57:20'),(12,4,'annonce','interne','Nouvelle annonce : TOKTOK','Allo Allo',NULL,'envoye',NULL,'2026-07-10 04:57:20'),(13,5,'annonce','interne','Nouvelle annonce : TOKTOK','Allo Allo',NULL,'envoye',NULL,'2026-07-10 04:57:20'),(14,6,'annonce','interne','Nouvelle annonce : TOKTOK','Allo Allo',NULL,'envoye',NULL,'2026-07-10 04:57:20'),(15,7,'annonce','interne','Nouvelle annonce : TOKTOK','Allo Allo',NULL,'envoye',NULL,'2026-07-10 04:57:20'),(16,1,'annonce','interne','Nouvelle annonce : Annonce test admin','Contenu de verification admin',NULL,'envoye',NULL,'2026-07-10 05:16:40'),(17,2,'annonce','interne','Nouvelle annonce : Annonce test admin','Contenu de verification admin',NULL,'envoye',NULL,'2026-07-10 05:16:40'),(18,3,'annonce','interne','Nouvelle annonce : Annonce test admin','Contenu de verification admin',NULL,'envoye',NULL,'2026-07-10 05:16:40'),(19,4,'annonce','interne','Nouvelle annonce : Annonce test admin','Contenu de verification admin',NULL,'envoye',NULL,'2026-07-10 05:16:40'),(20,5,'annonce','interne','Nouvelle annonce : Annonce test admin','Contenu de verification admin',NULL,'envoye',NULL,'2026-07-10 05:16:40'),(21,6,'annonce','interne','Nouvelle annonce : Annonce test admin','Contenu de verification admin',NULL,'envoye',NULL,'2026-07-10 05:16:40'),(22,7,'annonce','interne','Nouvelle annonce : Annonce test admin','Contenu de verification admin',NULL,'envoye',NULL,'2026-07-10 05:16:40'),(24,1,'annonce','interne','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.',NULL,'envoye',NULL,'2026-07-10 05:18:41'),(25,2,'annonce','interne','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.',NULL,'envoye',NULL,'2026-07-10 05:18:41'),(26,3,'annonce','interne','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.',NULL,'envoye',NULL,'2026-07-10 05:18:41'),(27,4,'annonce','interne','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.',NULL,'envoye',NULL,'2026-07-10 05:18:41'),(28,5,'annonce','interne','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.',NULL,'envoye',NULL,'2026-07-10 05:18:41'),(29,6,'annonce','interne','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.',NULL,'envoye',NULL,'2026-07-10 05:18:41'),(30,7,'annonce','interne','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.',NULL,'envoye',NULL,'2026-07-10 05:18:41'),(37,1,'decaissement','interne','Décaissement à valider — DEP-2026-0001','Un décaissement de 30 000 XOF (« Achat fournitures test ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 01:55:16'),(38,4,'decaissement','interne','Décaissement à valider — DEP-2026-0001','Un décaissement de 30 000 XOF (« Achat fournitures test ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 01:55:16'),(39,1,'decaissement','interne','Décaissement à valider — DEP-2026-0002','Un décaissement de 15 000 XOF (« Deuxieme test decaissement ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:33:29'),(40,4,'decaissement','interne','Décaissement à valider — DEP-2026-0002','Un décaissement de 15 000 XOF (« Deuxieme test decaissement ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:33:29'),(41,1,'decaissement','interne','Décaissement à valider — DEP-2026-0003','Un décaissement de 75 000 XOF (« Gros achat mobilier ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:38:55'),(42,4,'decaissement','interne','Décaissement à valider — DEP-2026-0003','Un décaissement de 75 000 XOF (« Gros achat mobilier ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:38:55'),(43,1,'decaissement','interne','Décaissement en attente d\'approbation — DEP-2026-0003','Un décaissement de 75 000 XOF dépasse le seuil d\'approbation et nécessite votre validation.',NULL,'envoye',NULL,'2026-07-12 02:39:32'),(44,2,'decaissement','interne','Décaissement en attente d\'approbation — DEP-2026-0003','Un décaissement de 75 000 XOF dépasse le seuil d\'approbation et nécessite votre validation.',NULL,'envoye',NULL,'2026-07-12 02:39:32'),(45,1,'decaissement','interne','Décaissement approuvé — DEP-2026-0003','Le décaissement « DEP-2026-0003 » a été approuvé et peut désormais être payé.',NULL,'envoye',NULL,'2026-07-12 02:41:09'),(46,4,'decaissement','interne','Décaissement approuvé — DEP-2026-0003','Le décaissement « DEP-2026-0003 » a été approuvé et peut désormais être payé.',NULL,'envoye',NULL,'2026-07-12 02:41:09'),(47,1,'decaissement','interne','Décaissement à valider — DEP-2026-0004','Un décaissement de 10 000 XOF (« Depense a rejeter ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:41:46'),(48,4,'decaissement','interne','Décaissement à valider — DEP-2026-0004','Un décaissement de 10 000 XOF (« Depense a rejeter ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:41:46'),(49,1,'decaissement','interne','Décaissement à valider — DEP-2026-0005','Un décaissement de 8 000 XOF (« Depense a annuler ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:41:48'),(50,4,'decaissement','interne','Décaissement à valider — DEP-2026-0005','Un décaissement de 8 000 XOF (« Depense a annuler ») a été soumis et attend validation.',NULL,'envoye',NULL,'2026-07-12 02:41:48'),(51,6,'paiement','interne','Paiement reçu — 20 000 XOF','Un paiement de 20 000 XOF a été enregistré pour Prenom28 Nom03 (facture FCT-2026-0019).',NULL,'envoye',NULL,'2026-07-12 03:52:24'),(52,6,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (Examen — Mathématiques) a été publiée pour Prenom28 Nom03.',NULL,'envoye',NULL,'2026-07-31 11:30:55'),(53,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (Examen — Mathématiques) a été publiée pour Notif Debug.',NULL,'envoye',NULL,'2026-07-31 11:31:08'),(54,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1785494087) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 11:34:50'),(55,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1785494091) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 11:34:53'),(56,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1785500223) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 13:17:04'),(57,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1785500224) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 13:17:04'),(58,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1785500263) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 13:17:45'),(59,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1785500266) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 13:17:52'),(60,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1785513769) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:02:51'),(61,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1785513772) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:02:53'),(62,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1785513827) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:03:59'),(63,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1785513844) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:04:08'),(64,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1785513958) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:06:00'),(65,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1785513961) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:06:02'),(66,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1785515598) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:33:19'),(67,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1785515599) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-07-31 17:33:21'),(68,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792852608) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 15:36:48'),(69,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792852608) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 15:36:48'),(70,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792853957) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 15:59:17'),(71,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792853957) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 15:59:17'),(72,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792853964) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 15:59:24'),(73,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792853964) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 15:59:24'),(74,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792859281) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 17:28:01'),(75,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792859281) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 17:28:01'),(76,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792859805) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 17:36:45'),(77,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792859805) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 17:36:45'),(78,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792860671) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 17:51:11'),(79,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792860671) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 17:51:11'),(80,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792862329) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 18:18:49'),(81,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792862329) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-24 18:18:49'),(82,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792885913) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 00:51:54'),(83,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792885914) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 00:51:54'),(84,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792888065) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:27:45'),(85,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792888065) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:27:45'),(86,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792888832) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:40:32'),(87,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792888832) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:40:32'),(88,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792889768) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:56:08'),(89,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792889768) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:56:08'),(90,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_1792889784) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:56:24'),(91,1,'note','interne','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (TEST_MIGRATION_CSV_1792889784) a été publiée pour Eleve TestMigration.',NULL,'envoye',NULL,'2026-10-25 01:56:24');
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_preferences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `trigger_type` enum('note','absence','paiement','annonce') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal_interne` tinyint(1) DEFAULT '1',
  `canal_email` tinyint(1) DEFAULT '0',
  `canal_sms` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_trigger` (`user_id`,`trigger_type`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk63_notification_preferences_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notification_preferences` WRITE;
/*!40000 ALTER TABLE `notification_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_preferences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` enum('note','absence','paiement','annonce','info','retard','discipline','recompense') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `lien` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `lu` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_lu` (`user_id`,`lu`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk63_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (3,1,'annonce','Test de notification — EduNova','vos maudia','',1,'2026-07-10 04:07:14'),(4,3,'annonce','Nouvelle annonce : merde','vos maudia','http://localhost/ecole_app/annonces',1,'2026-07-10 04:12:39'),(5,4,'annonce','Nouvelle annonce : merde','vos maudia','http://localhost/ecole_app/annonces',1,'2026-07-10 04:12:39'),(6,5,'annonce','Nouvelle annonce : merde','vos maudia','http://localhost/ecole_app/annonces',1,'2026-07-10 04:12:39'),(7,6,'annonce','Nouvelle annonce : merde','vos maudia','http://localhost/ecole_app/annonces',1,'2026-07-10 04:12:40'),(8,7,'annonce','Nouvelle annonce : merde','vos maudia','http://localhost/ecole_app/annonces',1,'2026-07-10 04:12:40'),(11,3,'annonce','Nouvelle annonce : TOKTOK','Allo Allo','http://localhost/ecole_app/annonces',1,'2026-07-10 04:57:20'),(12,4,'annonce','Nouvelle annonce : TOKTOK','Allo Allo','http://localhost/ecole_app/annonces',1,'2026-07-10 04:57:20'),(13,5,'annonce','Nouvelle annonce : TOKTOK','Allo Allo','http://localhost/ecole_app/annonces',1,'2026-07-10 04:57:20'),(14,6,'annonce','Nouvelle annonce : TOKTOK','Allo Allo','http://localhost/ecole_app/annonces',1,'2026-07-10 04:57:20'),(15,7,'annonce','Nouvelle annonce : TOKTOK','Allo Allo','http://localhost/ecole_app/annonces',1,'2026-07-10 04:57:20'),(21,1,'annonce','Nouvelle annonce : Annonce test admin','Contenu de verification admin','http://localhost/ecole_app/annonces',1,'2026-07-10 05:16:40'),(22,2,'annonce','Nouvelle annonce : Annonce test admin','Contenu de verification admin','http://localhost/ecole_app/annonces',1,'2026-07-10 05:16:40'),(23,3,'annonce','Nouvelle annonce : Annonce test admin','Contenu de verification admin','http://localhost/ecole_app/annonces',1,'2026-07-10 05:16:40'),(24,4,'annonce','Nouvelle annonce : Annonce test admin','Contenu de verification admin','http://localhost/ecole_app/annonces',1,'2026-07-10 05:16:40'),(25,5,'annonce','Nouvelle annonce : Annonce test admin','Contenu de verification admin','http://localhost/ecole_app/annonces',1,'2026-07-10 05:16:40'),(26,6,'annonce','Nouvelle annonce : Annonce test admin','Contenu de verification admin','http://localhost/ecole_app/annonces',1,'2026-07-10 05:16:40'),(27,7,'annonce','Nouvelle annonce : Annonce test admin','Contenu de verification admin','http://localhost/ecole_app/annonces',1,'2026-07-10 05:16:40'),(29,1,'annonce','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','http://localhost/ecole_app/annonces',1,'2026-07-10 05:18:41'),(30,2,'annonce','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','http://localhost/ecole_app/annonces',1,'2026-07-10 05:18:41'),(31,3,'annonce','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','http://localhost/ecole_app/annonces',1,'2026-07-10 05:18:41'),(32,4,'annonce','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','http://localhost/ecole_app/annonces',1,'2026-07-10 05:18:41'),(33,5,'annonce','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','http://localhost/ecole_app/annonces',1,'2026-07-10 05:18:41'),(34,6,'annonce','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','http://localhost/ecole_app/annonces',1,'2026-07-10 05:18:41'),(35,7,'annonce','Nouvelle annonce : Rentrée scolaire 2025-2026','La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.','http://localhost/ecole_app/annonces',1,'2026-07-10 05:18:41'),(59,6,'note','Nouvelle note — Mathématiques','Une nouvelle note en Mathématiques (Examen — Mathématiques) a été publiée pour Prenom28 Nom03.','/ecole_app/parent/notes',1,'2026-07-31 11:30:54');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `paiements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `frais_eleve_id` int unsigned DEFAULT NULL COMMENT 'Frais spécifique concerné (optionnel)',
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` date NOT NULL,
  `mode_paiement` enum('especes','cheque','virement','carte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especes',
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N° de chèque, bordereau virement…',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `encaisse_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_p_date` (`date_paiement`),
  KEY `idx_p_eleve` (`eleve_id`,`annee_scolaire`),
  KEY `idx_p_annee` (`annee_scolaire`),
  KEY `fk_p_frais` (`frais_eleve_id`),
  KEY `fk_p_encaisseur` (`encaisse_par`),
  CONSTRAINT `fk_p_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_p_encaisseur` FOREIGN KEY (`encaisse_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_p_frais` FOREIGN KEY (`frais_eleve_id`) REFERENCES `frais_eleves` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `paiements` WRITE;
/*!40000 ALTER TABLE `paiements` DISABLE KEYS */;
INSERT INTO `paiements` VALUES (1,1,1,'2025-2026',5000.00,'2026-06-15','especes',NULL,NULL,1,'2026-06-25 09:39:38'),(2,2,2,'2025-2026',5000.00,'2026-06-15','especes',NULL,NULL,1,'2026-06-25 09:39:38'),(3,3,3,'2025-2026',5000.00,'2026-06-15','especes',NULL,NULL,1,'2026-06-25 09:39:38'),(4,1,8,'2025-2026',15000.00,'2026-06-20','especes',NULL,NULL,1,'2026-06-25 09:39:38'),(5,2,9,'2025-2026',15000.00,'2026-06-20','especes',NULL,NULL,1,'2026-06-25 09:39:38'),(6,2,NULL,'2025-2026',500000.00,'2026-07-07','especes','123456789',NULL,2,'2026-07-07 00:53:57'),(7,5,NULL,'2025-2026',345678.00,'2026-07-07','especes','098765',NULL,2,'2026-07-07 00:55:57'),(11,3,NULL,'2025-2026',15000.00,'2026-07-10','especes',NULL,NULL,1,'2026-07-10 13:09:46');
/*!40000 ALTER TABLE `paiements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_pr_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `periodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `nom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('trimestre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trimestre',
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format : 2025-2026',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_periode_annee` (`annee_scolaire`),
  KEY `idx_etab` (`etablissement_id`,`annee_scolaire`),
  CONSTRAINT `fk_periodes_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `periodes` WRITE;
/*!40000 ALTER TABLE `periodes` DISABLE KEYS */;
INSERT INTO `periodes` VALUES (1,1,'Trimestre 1','trimestre','2025-2026','2025-09-15','2025-12-20',1,'2026-06-25 09:39:33'),(2,1,'Trimestre 2','trimestre','2025-2026','2026-01-07','2026-03-27',1,'2026-06-25 09:39:33'),(3,1,'Trimestre 3','trimestre','2025-2026','2026-04-05','2026-06-25',1,'2026-06-25 09:39:33');
/*!40000 ALTER TABLE `periodes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `periodes_scolaires`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodes_scolaires` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'Établissement propriétaire ; NULL = propriété non démontrée (invisible, à arbitrer)',
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_periode` enum('trimestre','semestre','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trimestre',
  `numero` tinyint unsigned NOT NULL DEFAULT '1',
  `nom` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('preparation','ouverte','cloturee','archivee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'preparation',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `notes_saisie_ouverte` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` tinyint unsigned NOT NULL DEFAULT '0',
  `verrouille_par` int unsigned DEFAULT NULL,
  `verrouille_le` datetime DEFAULT NULL,
  `genere_automatiquement` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ps_etab_annee_type_num` (`etablissement_id`,`annee_scolaire`,`type_periode`,`numero`),
  KEY `idx_ps_annee` (`annee_scolaire`),
  KEY `idx_ps_statut` (`statut`),
  KEY `idx_ps_active` (`is_active`),
  KEY `idx_ps_ordre` (`annee_scolaire`,`ordre`,`numero`),
  KEY `idx_ps_dates` (`annee_scolaire`,`date_debut`,`date_fin`),
  KEY `idx_ps_etab` (`etablissement_id`),
  CONSTRAINT `fk_ps_etablissement` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=202 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Périodes scolaires V2 — système trimestriel Niger par défaut';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `periodes_scolaires` WRITE;
/*!40000 ALTER TABLE `periodes_scolaires` DISABLE KEYS */;
INSERT INTO `periodes_scolaires` VALUES (90,1,'2025-2026','semestre',2,'Deuxième Semestre','2026-01-05','2026-07-02','ouverte',1,1,2,NULL,NULL,0,NULL,'2026-07-20 01:51:50','2026-07-20 01:51:50'),(91,1,'2025-2026','semestre',1,'Premier Semestre','2025-09-15','2025-12-20','cloturee',0,0,1,NULL,NULL,0,NULL,'2026-07-20 02:11:02','2026-07-20 02:11:02'),(100,1,'2026-2027','semestre',1,'Premier Semestre','2026-09-01','2027-01-31','cloturee',0,1,1,NULL,NULL,0,1,'2026-08-14 02:08:13','2026-08-14 02:08:13'),(101,1,'2026-2027','semestre',2,'Deuxième Semestre','2027-02-01','2027-06-30','ouverte',1,1,2,NULL,NULL,0,1,'2026-08-14 02:08:13','2026-08-14 02:08:13');
/*!40000 ALTER TABLE `periodes_scolaires` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `periodes_scolaires_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodes_scolaires_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = gabarit par défaut',
  `numero` tinyint unsigned NOT NULL,
  `nom_defaut` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mois_debut` tinyint unsigned NOT NULL,
  `jour_debut` tinyint unsigned NOT NULL,
  `mois_fin` tinyint unsigned NOT NULL,
  `jour_fin` tinyint unsigned NOT NULL,
  `ordre` tinyint unsigned NOT NULL DEFAULT '0',
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_psc_etab_numero` (`etablissement_id`,`numero`),
  KEY `idx_psc_etab` (`etablissement_id`),
  CONSTRAINT `fk_psc_etablissement` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modèle par défaut configurable — préconfiguré Niger';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `periodes_scolaires_config` WRITE;
/*!40000 ALTER TABLE `periodes_scolaires_config` DISABLE KEYS */;
INSERT INTO `periodes_scolaires_config` VALUES (1,NULL,1,'Premier semestre',10,1,1,31,1,NULL,'2026-07-22 00:52:54'),(2,NULL,2,'Deuxième semestre',2,1,6,30,2,NULL,'2026-07-22 00:52:54'),(5,1,1,'Premier semestre',10,1,1,31,1,NULL,'2026-10-28 06:16:10'),(6,1,2,'Deuxième semestre',2,1,6,30,2,NULL,'2026-10-28 06:16:10');
/*!40000 ALTER TABLE `periodes_scolaires_config` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `scope` enum('global','own') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
  `is_system` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'eleves.view','Consulter les élèves','eleves','','global',1),(2,'eleves.create','Ajouter un élève','eleves','','global',1),(3,'eleves.edit','Modifier un élève','eleves','','global',1),(4,'eleves.delete','Supprimer un élève','eleves','','global',1),(5,'enseignants.view','Consulter les enseignants','enseignants','','global',1),(6,'enseignants.create','Ajouter un enseignant','enseignants','','global',1),(7,'enseignants.edit','Modifier un enseignant','enseignants','','global',1),(8,'enseignants.delete','Supprimer un enseignant','enseignants','','global',1),(9,'classes.view','Consulter les classes','classes','','global',1),(10,'classes.create','Créer une classe','classes','','global',1),(11,'classes.edit','Modifier une classe','classes','','global',1),(12,'classes.delete','Supprimer une classe','classes','','global',1),(13,'notes.view','Consulter toutes les notes','notes','','global',1),(14,'notes.create','Saisir une note','notes','','global',1),(15,'notes.edit','Modifier une note','notes','','global',1),(16,'notes.delete','Supprimer une note','notes','','global',1),(17,'notes.view_own','Consulter ses propres notes','notes','','global',1),(18,'absences.view','Consulter toutes les absences','absences','','global',1),(19,'absences.create','Enregistrer une absence','absences','','global',1),(20,'absences.edit','Modifier une absence','absences','','global',1),(21,'absences.view_own','Consulter ses propres absences','absences','','global',1),(22,'users.view','Consulter les utilisateurs','users','','global',1),(23,'users.create','Créer un utilisateur','users','','global',1),(24,'users.edit','Modifier un utilisateur','users','','global',1),(25,'users.delete','Supprimer un utilisateur','users','','global',1),(26,'rapports.view','Consulter les rapports','rapports','','global',1),(27,'matieres.view','Voir la liste des matieres','matieres','','global',1),(28,'matieres.create','Ajouter une matiere','matieres','','global',1),(29,'matieres.edit','Modifier une matiere','matieres','','global',1),(30,'matieres.delete','Supprimer une matiere','matieres','','global',1),(33,'eleves.update','Modifier un élève','eleves','update','global',1),(35,'eleves.export','Exporter les élèves','eleves','export','global',1),(39,'enseignants.update','Modifier un enseignant','enseignants','update','global',1),(41,'enseignants.export','Exporter les enseignants','enseignants','export','global',1),(45,'classes.update','Modifier une classe','classes','update','global',1),(50,'matieres.update','Modifier une matière','matieres','update','global',1),(54,'notes.view.own','Voir ses propres notes','notes','view','own',1),(57,'notes.update','Modifier des notes','notes','update','global',1),(59,'notes.export','Exporter les notes','notes','export','global',1),(61,'bulletins.view','Voir les bulletins','bulletins','view','global',1),(62,'bulletins.view.own','Voir son bulletin','bulletins','view','own',1),(63,'bulletins.print','Imprimer un bulletin','bulletins','print','global',1),(64,'bulletins.print.own','Imprimer son bulletin','bulletins','print','own',1),(65,'bulletins.export','Exporter les bulletins','bulletins','export','global',1),(66,'bulletins.approve','Valider les bulletins','bulletins','approve','global',1),(68,'absences.view.own','Voir ses absences','absences','view','own',1),(71,'absences.update','Modifier des absences','absences','update','global',1),(72,'absences.delete','Supprimer des absences','absences','delete','global',1),(73,'absences.export','Exporter les absences','absences','export','global',1),(74,'absences.approve','Valider des justifications','absences','approve','global',1),(75,'absences.approve.own','Justifier sa propre absence','absences','approve','own',1),(77,'absences.justify','Justifier absence (V1)','absences','approve','own',1),(78,'comptabilite.view','Voir la comptabilité','comptabilite','view','global',1),(79,'comptabilite.view.own','Voir ses paiements','comptabilite','view','own',1),(80,'comptabilite.view_own','Voir ses paiements (V1)','comptabilite','view','own',1),(81,'comptabilite.create','Créer un paiement','comptabilite','create','global',1),(82,'comptabilite.update','Modifier un paiement','comptabilite','update','global',1),(83,'comptabilite.delete','Supprimer un paiement','comptabilite','delete','global',1),(84,'comptabilite.export','Exporter la comptabilité','comptabilite','export','global',1),(85,'comptabilite.print','Imprimer les reçus','comptabilite','print','global',1),(86,'comptabilite.print.own','Imprimer son reçu','comptabilite','print','own',1),(87,'comptabilite.edit','Modifier paiement (V1)','comptabilite','update','global',1),(88,'emploi_du_temps.view','Voir l\'emploi du temps','emploi_du_temps','view','global',1),(89,'emploi_du_temps.view.own','Voir son EDT','emploi_du_temps','view','own',1),(90,'emploi_du_temps.view_own','Voir son EDT (V1)','emploi_du_temps','view','own',1),(91,'emploi_du_temps.create','Créer un créneau EDT','emploi_du_temps','create','global',1),(92,'emploi_du_temps.update','Modifier l\'EDT','emploi_du_temps','update','global',1),(93,'emploi_du_temps.delete','Supprimer un créneau EDT','emploi_du_temps','delete','global',1),(94,'emploi_du_temps.print','Imprimer l\'EDT','emploi_du_temps','print','global',1),(95,'emploi_du_temps.print.own','Imprimer son EDT','emploi_du_temps','print','own',1),(96,'emploi_du_temps.edit','Modifier EDT (V1)','emploi_du_temps','update','global',1),(97,'annonces.view','Voir les annonces','annonces','view','global',1),(98,'annonces.create','Créer une annonce','annonces','create','global',1),(99,'annonces.update','Modifier une annonce','annonces','update','global',1),(100,'annonces.delete','Supprimer une annonce','annonces','delete','global',1),(101,'annonces.edit','Modifier annonce (V1)','annonces','update','global',1),(102,'notifications.view','Voir ses notifications','notifications','view','global',1),(103,'notifications.view.own','Voir ses notifications','notifications','view','own',1),(104,'notifications.create','Envoyer une notification','notifications','create','global',1),(105,'notifications.delete','Supprimer des notifications','notifications','delete','global',1),(106,'notifications.manage','Gérer notifications (V1)','notifications','create','global',1),(109,'users.update','Modifier un utilisateur','users','update','global',1),(111,'users.export','Exporter les utilisateurs','users','export','global',1),(112,'users.update.own','Modifier son profil','users','update','own',1),(115,'rapports.export','Exporter les rapports','rapports','export','global',1),(116,'rapports.print','Imprimer les rapports','rapports','print','global',1),(117,'profil.view.own','Voir son profil','profil','view','own',1),(118,'profil.update.own','Modifier son profil','profil','update','own',1),(119,'parametres.view','Voir les paramètres','parametres','view','global',1),(120,'parametres.update','Modifier les paramètres','parametres','update','global',1);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `platform_analytics_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_analytics_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `total_tenants` int unsigned NOT NULL DEFAULT '0',
  `active_tenants` int unsigned NOT NULL DEFAULT '0',
  `total_users` int unsigned NOT NULL DEFAULT '0',
  `total_eleves` int unsigned NOT NULL DEFAULT '0',
  `total_api_calls` bigint unsigned DEFAULT NULL,
  `storage_used_gb` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `top_tenants` json DEFAULT NULL,
  `errors_total` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_snapshot_at` (`snapshot_at`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `platform_analytics_snapshots` WRITE;
/*!40000 ALTER TABLE `platform_analytics_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_analytics_snapshots` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `platform_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_backups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL,
  `type` enum('global','tenant','pre_migration','manual','differential') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('pending','running','success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `storage_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `triggered_by` int unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_etab` (`etablissement_id`),
  KEY `idx_type` (`type`,`statut`),
  KEY `idx_expire` (`expires_at`),
  CONSTRAINT `fk63_platform_backups_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `platform_backups` WRITE;
/*!40000 ALTER TABLE `platform_backups` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_backups` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `platform_operators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_operators` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `niveau` enum('support','admin','super_admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'support',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_user` (`user_id`),
  CONSTRAINT `fk_platform_operator_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `platform_operators` WRITE;
/*!40000 ALTER TABLE `platform_operators` DISABLE KEYS */;
INSERT INTO `platform_operators` VALUES (1,1,'super_admin',1,'2026-07-08 17:45:45');
/*!40000 ALTER TABLE `platform_operators` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `platform_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `max_users` smallint unsigned NOT NULL DEFAULT '50',
  `max_eleves` smallint unsigned NOT NULL DEFAULT '500',
  `storage_quota_mb` int unsigned NOT NULL DEFAULT '1024',
  `api_calls_per_day` int unsigned NOT NULL DEFAULT '10000',
  `modules_inclus` json NOT NULL,
  `prix_mensuel` decimal(10,2) NOT NULL DEFAULT '0.00',
  `prix_annuel` decimal(10,2) NOT NULL DEFAULT '0.00',
  `devise` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DZD',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `platform_plans` WRITE;
/*!40000 ALTER TABLE `platform_plans` DISABLE KEYS */;
INSERT INTO `platform_plans` VALUES (1,'starter','Starter',NULL,20,200,512,2000,'[\"scolarite\", \"academique\", \"finance\"]',2500.00,25000.00,'DZD',1,'2026-07-08 05:14:13'),(2,'pro','Pro',NULL,80,1500,5120,20000,'[\"scolarite\", \"academique\", \"finance\", \"vie_scolaire\", \"rh\"]',6500.00,65000.00,'DZD',1,'2026-07-08 05:14:13'),(3,'enterprise','Enterprise',NULL,500,10000,51200,100000,'[\"scolarite\", \"academique\", \"finance\", \"vie_scolaire\", \"rh\", \"documents\", \"bibliotheque\", \"inventaire\", \"rapports\", \"portals\", \"api\"]',15000.00,150000.00,'DZD',1,'2026-07-08 05:14:13');
/*!40000 ALTER TABLE `platform_plans` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `platform_restores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_restores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` int unsigned NOT NULL,
  `etablissement_id` int unsigned DEFAULT NULL,
  `type` enum('global_verify','tenant_live','selective') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('pending','running','success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rows_restored` int unsigned DEFAULT NULL,
  `target_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `triggered_by` int unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_backup` (`backup_id`),
  KEY `idx_etab` (`etablissement_id`),
  CONSTRAINT `fk63_platform_restores_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `platform_restores` WRITE;
/*!40000 ALTER TABLE `platform_restores` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_restores` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `professeurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professeurs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialite` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_recrutement` date DEFAULT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_prof_user` (`user_id`),
  KEY `idx_etab` (`etablissement_id`,`actif`),
  CONSTRAINT `fk_prof_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_professeurs_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `professeurs` WRITE;
/*!40000 ALTER TABLE `professeurs` DISABLE KEYS */;
INSERT INTO `professeurs` VALUES (1,1,5,'Nom22','Prenom26','Mathématiques','Professeur certifié (PES)','2015-09-01',NULL,NULL,'0600000001','Nom22@ecole.dz',1,'2026-06-25 09:30:46'),(2,1,NULL,'Nom09','Prenom07','Physique-Chimie','Professeur certifié (PES)','2013-09-01',NULL,NULL,'0600000002','Nom09@ecole.dz',1,'2026-06-25 09:30:46'),(3,1,NULL,'Nom15','Prenom01','Langue Arabe','Professeur de l\'enseignement moyen (PEM)','2018-09-01',NULL,NULL,'0600000003','Nom15@ecole.dz',1,'2026-06-25 09:30:46'),(4,1,NULL,'Nom27','Prenom20','Langue Française','Professeur de l\'enseignement moyen (PEM)','2019-09-01',NULL,NULL,'0600000004','Nom27@ecole.dz',1,'2026-06-25 09:30:46'),(5,1,NULL,'Nom12','Prenom34','Langue Anglaise','Professeur certifié (PES)','2020-09-01',NULL,NULL,'0600000005','Nom12@ecole.dz',1,'2026-06-25 09:30:46'),(6,1,NULL,'Nom16','Prenom27','Sciences Naturelles','Maître formateur','2010-09-01',NULL,NULL,'0600000006','Nom16@ecole.dz',1,'2026-06-25 09:30:46'),(7,1,NULL,'Nom33','Prenom17','Histoire-Géographie','Professeur contractuel','2022-09-01',NULL,NULL,'0600000007','Nom33@ecole.dz',1,'2026-06-25 09:30:46'),(8,1,NULL,'Nom35','Prenom33','Philosophie','Maître assistant','2021-09-01',NULL,NULL,'0600000008','Nom35@ecole.dz',1,'2026-06-25 09:30:46'),(9,1,NULL,'Nom29','Prenom30','Mathématiques','Professeur contractuel','2026-07-09','9 rue de Test',NULL,'0600000009','Nom29@ecole.example',1,'2026-07-09 15:05:53');
/*!40000 ALTER TABLE `professeurs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `endpoint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `p256dh` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `auth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_endpoint` (`endpoint`(200)),
  CONSTRAINT `fk63_push_subscriptions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `push_subscriptions` WRITE;
/*!40000 ALTER TABLE `push_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `push_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rbac_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rbac_role_permissions` (
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rrp_perm` (`permission_id`),
  CONSTRAINT `fk_rrp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rrp_role` FOREIGN KEY (`role_id`) REFERENCES `rbac_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rbac_role_permissions` WRITE;
/*!40000 ALTER TABLE `rbac_role_permissions` DISABLE KEYS */;
INSERT INTO `rbac_role_permissions` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(1,2),(2,2),(3,2),(1,3),(2,3),(3,3),(1,4),(2,4),(1,5),(2,5),(4,5),(1,6),(2,6),(1,7),(2,7),(1,8),(2,8),(1,9),(2,9),(3,9),(4,9),(5,9),(1,10),(2,10),(1,11),(2,11),(1,12),(2,12),(1,13),(2,13),(3,13),(5,13),(1,14),(2,14),(5,14),(1,15),(2,15),(5,15),(1,16),(2,16),(1,17),(6,17),(7,17),(1,18),(2,18),(3,18),(5,18),(1,19),(2,19),(3,19),(5,19),(1,20),(2,20),(3,20),(5,20),(1,21),(6,21),(7,21),(1,22),(2,22),(1,23),(2,23),(1,24),(2,24),(1,25),(1,26),(2,26),(3,26),(4,26),(1,27),(2,27),(3,27),(4,27),(5,27),(1,28),(2,28),(1,29),(2,29),(1,30),(2,30),(1,33),(2,33),(3,33),(1,35),(2,35),(3,35),(1,39),(2,39),(1,41),(2,41),(1,45),(2,45),(1,50),(2,50),(1,54),(6,54),(7,54),(1,57),(2,57),(5,57),(1,59),(2,59),(1,61),(2,61),(3,61),(5,61),(6,61),(7,61),(1,62),(6,62),(7,62),(1,63),(2,63),(5,63),(6,64),(7,64),(1,65),(2,65),(5,65),(1,66),(2,66),(1,68),(6,68),(7,68),(1,71),(2,71),(3,71),(5,71),(1,72),(2,72),(1,73),(2,73),(3,73),(1,74),(2,74),(3,74),(5,74),(6,75),(6,77),(1,78),(2,78),(3,78),(4,78),(1,79),(4,79),(6,79),(1,80),(4,80),(6,80),(1,81),(2,81),(3,81),(4,81),(1,82),(2,82),(4,82),(1,83),(4,83),(1,84),(2,84),(4,84),(1,85),(2,85),(3,85),(4,85),(6,86),(1,87),(2,87),(4,87),(1,88),(2,88),(3,88),(5,88),(1,89),(5,89),(6,89),(7,89),(1,90),(5,90),(6,90),(7,90),(1,91),(2,91),(1,92),(2,92),(1,93),(2,93),(1,94),(2,94),(3,94),(5,94),(5,95),(6,95),(7,95),(1,96),(2,96),(1,97),(2,97),(3,97),(4,97),(5,97),(6,97),(7,97),(1,98),(2,98),(3,98),(1,99),(2,99),(3,99),(1,100),(2,100),(3,100),(1,101),(2,101),(3,101),(1,102),(2,102),(3,102),(4,102),(5,102),(1,103),(2,103),(3,103),(4,103),(5,103),(6,103),(7,103),(1,104),(2,104),(1,105),(2,105),(1,106),(2,106),(1,109),(2,109),(1,111),(1,112),(2,112),(1,115),(2,115),(3,115),(4,115),(1,116),(2,116),(3,116),(4,116),(1,117),(2,117),(3,117),(4,117),(5,117),(6,117),(7,117),(1,118),(2,118),(3,118),(4,118),(5,118),(6,118),(7,118),(1,119),(1,120);
/*!40000 ALTER TABLE `rbac_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rbac_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rbac_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'admin, directeur, ...',
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_system` tinyint(1) NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rbac_roles` WRITE;
/*!40000 ALTER TABLE `rbac_roles` DISABLE KEYS */;
INSERT INTO `rbac_roles` VALUES (1,'admin','Administrateur','Accès total au système',1,1,1,'2026-06-30 03:03:43'),(2,'directeur','Directeur','Direction de l\'établissement',1,1,2,'2026-06-30 03:03:43'),(3,'secretaire','Secrétaire','Administration scolaire',1,1,3,'2026-06-30 03:03:43'),(4,'comptable','Comptable','Gestion financière',1,1,4,'2026-06-30 03:03:43'),(5,'enseignant','Enseignant','Corps pédagogique',1,1,5,'2026-06-30 03:03:43'),(6,'parent','Parent','Représentant légal de l\'élève',1,1,6,'2026-06-30 03:03:43'),(7,'eleve','Élève','Apprenant',1,1,7,'2026-06-30 03:03:43');
/*!40000 ALTER TABLE `rbac_roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rbac_user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rbac_user_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  `granted_by` int unsigned DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_role` (`user_id`,`role_id`),
  KEY `idx_ur_user` (`user_id`,`actif`),
  KEY `fk_ur_role` (`role_id`),
  KEY `fk_ur_granter` (`granted_by`),
  CONSTRAINT `fk_ur_granter` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `rbac_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rbac_user_roles` WRITE;
/*!40000 ALTER TABLE `rbac_user_roles` DISABLE KEYS */;
INSERT INTO `rbac_user_roles` VALUES (1,1,1,NULL,'2026-06-30 03:03:44',NULL,1),(2,2,2,NULL,'2026-06-30 03:03:44',NULL,1),(3,3,3,NULL,'2026-06-30 03:03:44',NULL,1),(4,4,4,NULL,'2026-06-30 03:03:44',NULL,1),(5,5,5,NULL,'2026-06-30 03:03:44',NULL,1),(6,6,6,NULL,'2026-06-30 03:03:44',NULL,1),(7,7,7,NULL,'2026-06-30 03:03:44',NULL,1),(8,281,6,NULL,'2026-10-28 05:16:12',NULL,1),(9,282,6,NULL,'2026-10-28 05:16:12',NULL,1),(10,283,6,NULL,'2026-10-28 05:16:12',NULL,1),(11,284,6,NULL,'2026-10-28 05:16:12',NULL,1),(12,285,6,NULL,'2026-10-28 05:16:12',NULL,1),(13,286,6,NULL,'2026-10-28 05:16:12',NULL,1),(14,287,6,NULL,'2026-10-28 05:16:12',NULL,1),(15,288,6,NULL,'2026-10-28 05:16:12',NULL,1),(16,289,6,NULL,'2026-10-28 05:16:12',NULL,1);
/*!40000 ALTER TABLE `rbac_user_roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `reinscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reinscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `classe_source_id` int unsigned NOT NULL,
  `classe_destination_id` int unsigned DEFAULT NULL COMMENT 'NULL = sortant / fin de cycle',
  `annee_source` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `annee_destination` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sortant` tinyint(1) NOT NULL DEFAULT '0',
  `executee_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reinsc_eleve` (`eleve_id`),
  KEY `idx_reinsc_etab_annees` (`etablissement_id`,`annee_source`,`annee_destination`),
  KEY `fk_reinsc_classe_src` (`classe_source_id`),
  KEY `fk_reinsc_classe_dst` (`classe_destination_id`),
  KEY `fk_reinsc_user` (`executee_par`),
  CONSTRAINT `fk63_reinscriptions_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_reinsc_classe_dst` FOREIGN KEY (`classe_destination_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_reinsc_classe_src` FOREIGN KEY (`classe_source_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_reinsc_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reinsc_user` FOREIGN KEY (`executee_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `reinscriptions` WRITE;
/*!40000 ALTER TABLE `reinscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `reinscriptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_affectation_matieres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_affectation_matieres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `affectation_id` int unsigned NOT NULL,
  `matiere_id` int unsigned DEFAULT NULL COMMENT 'Réf. table matieres V1',
  `classe_id` int unsigned DEFAULT NULL COMMENT 'Réf. table classes V1',
  `niveau` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex : 6ème, 5ème, Terminale S',
  `heures_hebdo` decimal(4,1) unsigned DEFAULT NULL COMMENT 'Charge hebdomadaire en heures',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_affmat_affect` (`affectation_id`),
  KEY `fk63_rh_affectation_matieres_classe_id` (`classe_id`),
  KEY `fk63_rh_affectation_matieres_matiere_id` (`matiere_id`),
  CONSTRAINT `fk63_rh_affectation_matieres_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk63_rh_affectation_matieres_matiere_id` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affmat_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `rh_affectations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_affectation_matieres` WRITE;
/*!40000 ALTER TABLE `rh_affectation_matieres` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_affectation_matieres` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_affectations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_affectations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `contrat_id` int unsigned DEFAULT NULL COMMENT 'Lien contrat actif — SET NULL si contrat expiré',
  `poste_id` int unsigned DEFAULT NULL,
  `departement_id` int unsigned DEFAULT NULL,
  `service_id` int unsigned DEFAULT NULL,
  `responsable_id` int unsigned DEFAULT NULL COMMENT 'Manager direct (autre employé)',
  `type` enum('principale','secondaire','temporaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'principale',
  `statut` enum('active','terminee','suspendue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL COMMENT 'NULL = durée indéterminée',
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'Réservé réseau V3 — NULL en V2',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_affect_contrat` (`contrat_id`),
  KEY `fk_affect_service` (`service_id`),
  KEY `fk_affect_responsable` (`responsable_id`),
  KEY `idx_affect_employe` (`employe_id`),
  KEY `idx_affect_poste` (`poste_id`),
  KEY `idx_affect_departement` (`departement_id`),
  KEY `idx_affect_statut` (`statut`),
  KEY `idx_affect_deleted` (`deleted_at`),
  KEY `fk63_rh_affectations_etablissement_id` (`etablissement_id`),
  CONSTRAINT `fk63_rh_affectations_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_contrat` FOREIGN KEY (`contrat_id`) REFERENCES `rh_contrats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_departement` FOREIGN KEY (`departement_id`) REFERENCES `rh_departements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_affect_poste` FOREIGN KEY (`poste_id`) REFERENCES `rh_postes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `rh_employes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_service` FOREIGN KEY (`service_id`) REFERENCES `rh_services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_affectations` WRITE;
/*!40000 ALTER TABLE `rh_affectations` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_affectations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_campagne_criteres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_campagne_criteres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campagne_id` int unsigned NOT NULL,
  `critere_id` int unsigned NOT NULL,
  `poids_override` decimal(5,2) DEFAULT NULL,
  `obligatoire` tinyint(1) DEFAULT '1',
  `ordre` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc` (`campagne_id`,`critere_id`),
  KEY `critere_id` (`critere_id`),
  CONSTRAINT `rh_campagne_criteres_ibfk_1` FOREIGN KEY (`campagne_id`) REFERENCES `rh_campagnes_evaluation` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rh_campagne_criteres_ibfk_2` FOREIGN KEY (`critere_id`) REFERENCES `rh_criteres_evaluation` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_campagne_criteres` WRITE;
/*!40000 ALTER TABLE `rh_campagne_criteres` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_campagne_criteres` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_campagnes_evaluation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_campagnes_evaluation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `annee` year NOT NULL,
  `periode` enum('S1','S2','annuelle','trimestrielle','ad_hoc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'annuelle',
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `date_limite_auto_eval` date DEFAULT NULL,
  `date_limite_eval` date DEFAULT NULL,
  `statut` enum('brouillon','active','cloturee','archivee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'brouillon',
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campagne_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_campagnes_evaluation` WRITE;
/*!40000 ALTER TABLE `rh_campagnes_evaluation` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_campagnes_evaluation` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_certifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `organisme_id` int unsigned DEFAULT NULL,
  `duree_validite_mois` int DEFAULT NULL,
  `renouvelable` tinyint(1) DEFAULT '1',
  `formation_id` int unsigned DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certification_code` (`code`),
  KEY `organisme_id` (`organisme_id`),
  KEY `formation_id` (`formation_id`),
  CONSTRAINT `rh_certifications_ibfk_1` FOREIGN KEY (`organisme_id`) REFERENCES `rh_formations_organismes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rh_certifications_ibfk_2` FOREIGN KEY (`formation_id`) REFERENCES `rh_formations_catalogue` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_certifications` WRITE;
/*!40000 ALTER TABLE `rh_certifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_certifications` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_competences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_competences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `categorie` enum('technique','comportementale','management','pedagogique','transversale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'technique',
  `actif` tinyint(1) DEFAULT '1',
  `ordre` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competence_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_competences` WRITE;
/*!40000 ALTER TABLE `rh_competences` DISABLE KEYS */;
INSERT INTO `rh_competences` VALUES (1,'bureautique','Bureautique (Office, G-Suite)',NULL,'technique',1,1,'2026-07-11 16:14:33'),(2,'gestion_projet','Gestion de projet',NULL,'technique',1,2,'2026-07-11 16:14:33'),(3,'comptabilite','Comptabilité & finances',NULL,'technique',1,3,'2026-07-11 16:14:33'),(4,'informatique','Informatique & outils numériques',NULL,'technique',1,4,'2026-07-11 16:14:33'),(5,'legislation_rh','Législation du travail',NULL,'technique',1,5,'2026-07-11 16:14:33'),(6,'pedagogie_active','Pédagogie active',NULL,'pedagogique',1,6,'2026-07-11 16:14:33'),(7,'numerique_educ','Numérique éducatif',NULL,'pedagogique',1,7,'2026-07-11 16:14:33'),(8,'evaluation_eleves','Évaluation des apprentissages',NULL,'pedagogique',1,8,'2026-07-11 16:14:33'),(9,'communication','Communication professionnelle',NULL,'comportementale',1,9,'2026-07-11 16:14:33'),(10,'leadership','Leadership & management d\'équipe',NULL,'comportementale',1,10,'2026-07-11 16:14:33'),(11,'gestion_conflit','Gestion des conflits',NULL,'comportementale',1,11,'2026-07-11 16:14:33'),(12,'service_client','Relation client & accueil',NULL,'comportementale',1,12,'2026-07-11 16:14:33'),(13,'management_rh','Management des ressources humaines',NULL,'management',1,13,'2026-07-11 16:14:33'),(14,'pilotage_perf','Pilotage de la performance',NULL,'management',1,14,'2026-07-11 16:14:33'),(15,'securite','Sécurité & prévention',NULL,'transversale',1,15,'2026-07-11 16:14:33'),(16,'qualite','Qualité & amélioration continue',NULL,'transversale',1,16,'2026-07-11 16:14:33');
/*!40000 ALTER TABLE `rh_competences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_conges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_conges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `type_conge_id` int unsigned NOT NULL,
  `contrat_id` int unsigned DEFAULT NULL,
  `affectation_id` int unsigned DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `duree_jours` decimal(4,1) NOT NULL DEFAULT '0.0' COMMENT 'Jours ouvrables calculés',
  `duree_heures` decimal(5,1) DEFAULT NULL COMMENT 'Pour permissions < 1 journée',
  `statut` enum('brouillon','soumis','approuve','rejete','annule','en_cours','termine') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motif_rejet` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motif_annulation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approuve_par` int unsigned DEFAULT NULL,
  `approuve_par_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `annule_par_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_annulation` datetime DEFAULT NULL,
  `date_retour_effectif` date DEFAULT NULL,
  `commentaire_retour` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `impact_paie` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Préparation Phase 6.12',
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employe` (`employe_id`),
  KEY `idx_dates` (`date_debut`,`date_fin`),
  KEY `idx_statut` (`statut`),
  KEY `idx_deleted` (`deleted_at`),
  KEY `fk_conge_type` (`type_conge_id`),
  KEY `fk_conge_contrat` (`contrat_id`),
  KEY `fk_conge_affectation` (`affectation_id`),
  CONSTRAINT `fk_conge_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `rh_affectations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conge_contrat` FOREIGN KEY (`contrat_id`) REFERENCES `rh_contrats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conge_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_conge_type` FOREIGN KEY (`type_conge_id`) REFERENCES `rh_types_conges` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_conges` WRITE;
/*!40000 ALTER TABLE `rh_conges` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_conges` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_conges_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_conges_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conge_id` int unsigned NOT NULL,
  `statut_avant` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut_apres` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `effectue_par` int unsigned NOT NULL,
  `effectue_par_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conge` (`conge_id`),
  CONSTRAINT `fk_hist_conge` FOREIGN KEY (`conge_id`) REFERENCES `rh_conges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_conges_historique` WRITE;
/*!40000 ALTER TABLE `rh_conges_historique` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_conges_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_conges_justificatifs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_conges_justificatifs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conge_id` int unsigned NOT NULL,
  `nom_fichier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_octets` int unsigned DEFAULT NULL,
  `uploaded_by` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conge` (`conge_id`),
  CONSTRAINT `fk_just_conge` FOREIGN KEY (`conge_id`) REFERENCES `rh_conges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_conges_justificatifs` WRITE;
/*!40000 ALTER TABLE `rh_conges_justificatifs` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_conges_justificatifs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_contacts_urgence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contacts_urgence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `nom_complet` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lien` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ex: conjoint, père, mère, frère, ami',
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone2` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employe` (`employe_id`),
  CONSTRAINT `fk63_rh_contacts_urgence_employe_id` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_contacts_urgence` WRITE;
/*!40000 ALTER TABLE `rh_contacts_urgence` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_contacts_urgence` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_contrat_avenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contrat_avenants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `contrat_id` int unsigned NOT NULL,
  `numero` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Numéro séquentiel par contrat',
  `type` enum('salaire','poste','duree','horaire','teletravail','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'autre',
  `objet` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_effet` date NOT NULL,
  `ancienne_valeur` json DEFAULT NULL,
  `nouvelle_valeur` json DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avenant_numero` (`contrat_id`,`numero`),
  KEY `idx_avenant_contrat` (`contrat_id`),
  KEY `idx_avenant_date_effet` (`date_effet`),
  CONSTRAINT `fk_avenant_contrat` FOREIGN KEY (`contrat_id`) REFERENCES `rh_contrats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_contrat_avenants` WRITE;
/*!40000 ALTER TABLE `rh_contrat_avenants` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_contrat_avenants` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_contrat_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contrat_sequences` (
  `annee` year NOT NULL,
  `seq` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_contrat_sequences` WRITE;
/*!40000 ALTER TABLE `rh_contrat_sequences` DISABLE KEYS */;
INSERT INTO `rh_contrat_sequences` VALUES (2026,0);
/*!40000 ALTER TABLE `rh_contrat_sequences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_contrats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contrats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `poste_id` int unsigned DEFAULT NULL COMMENT 'FK rh_postes.id',
  `departement_id` int unsigned DEFAULT NULL COMMENT 'FK rh_departements.id',
  `numero_contrat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format CNT-YYYY-NNNN',
  `type` enum('cdi','cdd','vacataire','stage','apprentissage','consultant','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cdd',
  `statut` enum('brouillon','actif','suspendu','expire','resilie') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL COMMENT 'NULL = durée indéterminée (CDI)',
  `date_signature` date DEFAULT NULL,
  `salaire_brut` decimal(10,2) unsigned DEFAULT NULL,
  `devise` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DZD',
  `motif_creation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motif_fin` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Renseigné à résiliation/expiration',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `renouvelle_depuis` int unsigned DEFAULT NULL COMMENT 'FK rh_contrats.id — contrat précédent renouvelé',
  `created_by` int unsigned DEFAULT NULL COMMENT 'FK users.id',
  `updated_by` int unsigned DEFAULT NULL COMMENT 'FK users.id',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_contrat` (`numero_contrat`),
  KEY `fk_contrat_poste` (`poste_id`),
  KEY `fk_contrat_departement` (`departement_id`),
  KEY `fk_contrat_renouvelle` (`renouvelle_depuis`),
  KEY `idx_contrat_employe` (`employe_id`),
  KEY `idx_contrat_statut` (`statut`),
  KEY `idx_contrat_type` (`type`),
  KEY `idx_contrat_date_fin` (`date_fin`),
  KEY `idx_contrat_deleted` (`deleted_at`),
  CONSTRAINT `fk_contrat_departement` FOREIGN KEY (`departement_id`) REFERENCES `rh_departements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contrat_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_contrat_poste` FOREIGN KEY (`poste_id`) REFERENCES `rh_postes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contrat_renouvelle` FOREIGN KEY (`renouvelle_depuis`) REFERENCES `rh_contrats` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_contrats` WRITE;
/*!40000 ALTER TABLE `rh_contrats` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_contrats` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_criteres_evaluation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_criteres_evaluation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `categorie` enum('competence','comportement','resultat','objectif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'competence',
  `type_employe` enum('tous','enseignant','administratif','comptable','direction') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'tous',
  `poids` decimal(5,2) DEFAULT '1.00',
  `note_min` tinyint DEFAULT '0',
  `note_max` tinyint DEFAULT '5',
  `actif` tinyint(1) DEFAULT '1',
  `ordre` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_critere_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_criteres_evaluation` WRITE;
/*!40000 ALTER TABLE `rh_criteres_evaluation` DISABLE KEYS */;
INSERT INTO `rh_criteres_evaluation` VALUES (1,'maitrise_poste','Maîtrise du poste',NULL,'competence','tous',2.00,0,5,1,1,'2026-07-11 16:14:25'),(2,'qualite_travail','Qualité du travail',NULL,'competence','tous',2.00,0,5,1,2,'2026-07-11 16:14:25'),(3,'productivite','Productivité',NULL,'resultat','tous',1.50,0,5,1,3,'2026-07-11 16:14:25'),(4,'ponctualite','Ponctualité & assiduité',NULL,'comportement','tous',1.50,0,5,1,4,'2026-07-11 16:14:25'),(5,'esprit_equipe','Esprit d\'équipe',NULL,'comportement','tous',1.00,0,5,1,5,'2026-07-11 16:14:25'),(6,'communication','Communication',NULL,'comportement','tous',1.00,0,5,1,6,'2026-07-11 16:14:25'),(7,'initiative','Initiative & autonomie',NULL,'comportement','tous',1.00,0,5,1,7,'2026-07-11 16:14:25'),(8,'respect_reglement','Respect du règlement',NULL,'comportement','tous',1.00,0,5,1,8,'2026-07-11 16:14:25'),(9,'pedagogie','Qualité pédagogique',NULL,'competence','enseignant',2.00,0,5,1,9,'2026-07-11 16:14:25'),(10,'preparation_cours','Préparation des cours',NULL,'competence','enseignant',1.50,0,5,1,10,'2026-07-11 16:14:25'),(11,'suivi_eleves','Suivi des élèves',NULL,'resultat','enseignant',1.50,0,5,1,11,'2026-07-11 16:14:25'),(12,'resultats_classe','Résultats de la classe',NULL,'resultat','enseignant',2.00,0,5,1,12,'2026-07-11 16:14:25'),(13,'gestion_dossiers','Gestion des dossiers',NULL,'competence','administratif',1.50,0,5,1,9,'2026-07-11 16:14:25'),(14,'accueil_service','Accueil & service',NULL,'comportement','administratif',1.50,0,5,1,10,'2026-07-11 16:14:25'),(15,'rigueur_comptable','Rigueur comptable',NULL,'competence','comptable',2.00,0,5,1,9,'2026-07-11 16:14:25'),(16,'respect_delais','Respect des délais comptables',NULL,'resultat','comptable',1.50,0,5,1,10,'2026-07-11 16:14:25'),(17,'leadership','Leadership & management',NULL,'competence','direction',2.00,0,5,1,9,'2026-07-11 16:14:25'),(18,'prise_decision','Prise de décision',NULL,'resultat','direction',1.50,0,5,1,10,'2026-07-11 16:14:25');
/*!40000 ALTER TABLE `rh_criteres_evaluation` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_departements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_departements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `budget_centre` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Code centre de coût (Finance V2)',
  `responsable_id` int unsigned DEFAULT NULL COMMENT 'FK rh_employes.id — ajouté après import employés',
  `parent_id` int unsigned DEFAULT NULL COMMENT 'Self-référentiel pour sous-départements',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `ordre_affichage` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Tri visuel organigramme',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_departements` WRITE;
/*!40000 ALTER TABLE `rh_departements` DISABLE KEYS */;
INSERT INTO `rh_departements` VALUES (1,'Direction','DIR','Direction générale de l\'établissement',NULL,NULL,NULL,1,0,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(2,'Administration & Secrétariat','ADMIN','Secrétariat, accueil, gestion administrative',NULL,NULL,NULL,1,0,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(3,'Corps Enseignant','ENS','Enseignants toutes matières confondues',NULL,NULL,NULL,1,0,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(4,'Comptabilité & Finance','FIN','Gestion financière et comptable',NULL,NULL,NULL,1,0,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(5,'Vie Scolaire','VS','Surveillants, éducateurs, encadrement élèves',NULL,NULL,NULL,1,0,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(6,'Services Techniques','TECH','Entretien, maintenance, informatique',NULL,NULL,NULL,1,0,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL);
/*!40000 ALTER TABLE `rh_departements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_document_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_document_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `action` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'creer|mettre_a_jour|archiver|restaurer|expirer|refuser',
  `ancien_statut` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_by_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rdoch_document` (`document_id`),
  KEY `idx_rdoch_action` (`action`),
  CONSTRAINT `fk_rdoch_document` FOREIGN KEY (`document_id`) REFERENCES `rh_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal des actions sur les documents RH';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_document_historique` WRITE;
/*!40000 ALTER TABLE `rh_document_historique` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_document_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_document_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `version` tinyint unsigned NOT NULL,
  `reference_externe` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Référence du fichier à cette version',
  `notes_version` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motif de la nouvelle version',
  `created_by` int unsigned NOT NULL,
  `created_by_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_doc_version` (`document_id`,`version`),
  KEY `idx_rdocv_document` (`document_id`),
  CONSTRAINT `fk_rdocv_document` FOREIGN KEY (`document_id`) REFERENCES `rh_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des versions d''un document RH';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_document_versions` WRITE;
/*!40000 ALTER TABLE `rh_document_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_document_versions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `type` enum('contrat_signe','avenant','diplome','certificat','attestation','piece_identite','certificat_medical','autorisation','sanction','recompense','evaluation_signee','certificat_formation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_externe` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Référence DocumentService V2/V3',
  `statut` enum('actif','expire','archive','en_attente','refuse') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `confidentialite` enum('public','confidentiel','secret') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confidentiel',
  `version_courante` tinyint unsigned NOT NULL DEFAULT '1',
  `date_emission` date NOT NULL,
  `date_expiration` date DEFAULT NULL,
  `alerte_jours` int unsigned DEFAULT '30' COMMENT 'Alerte N jours avant expiration',
  `emetteur` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL COMMENT 'Données libres pour intégration V3/API',
  `contrat_id` int unsigned DEFAULT NULL,
  `formation_session_id` int unsigned DEFAULT NULL,
  `evaluation_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `archived_by` int unsigned DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_rdoc_contrat` (`contrat_id`),
  KEY `fk_rdoc_session` (`formation_session_id`),
  KEY `fk_rdoc_eval` (`evaluation_id`),
  KEY `idx_rdoc_employe` (`employe_id`),
  KEY `idx_rdoc_type` (`type`),
  KEY `idx_rdoc_statut` (`statut`),
  KEY `idx_rdoc_expiration` (`date_expiration`),
  KEY `idx_rdoc_confidentialite` (`confidentialite`),
  KEY `idx_rdoc_deleted` (`deleted_at`),
  CONSTRAINT `fk_rdoc_contrat` FOREIGN KEY (`contrat_id`) REFERENCES `rh_contrats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rdoc_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rdoc_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `rh_evaluations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rdoc_session` FOREIGN KEY (`formation_session_id`) REFERENCES `rh_formations_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Références documentaires RH — métadonnées uniquement';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_documents` WRITE;
/*!40000 ALTER TABLE `rh_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_documents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_employe_certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employe_certifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `certification_id` int unsigned NOT NULL,
  `date_obtention` date NOT NULL,
  `date_expiration` date DEFAULT NULL,
  `statut` enum('valide','expiree','a_renouveler') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'valide',
  `session_id` int unsigned DEFAULT NULL,
  `reference_certificat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fichier_certificat` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employe_id` (`employe_id`),
  KEY `certification_id` (`certification_id`),
  KEY `session_id` (`session_id`),
  CONSTRAINT `rh_employe_certifications_ibfk_1` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rh_employe_certifications_ibfk_2` FOREIGN KEY (`certification_id`) REFERENCES `rh_certifications` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rh_employe_certifications_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `rh_formations_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_employe_certifications` WRITE;
/*!40000 ALTER TABLE `rh_employe_certifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_employe_certifications` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_employe_competences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employe_competences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `competence_id` int unsigned NOT NULL,
  `niveau` enum('debutant','intermediaire','avance','expert') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'debutant',
  `date_acquisition` date DEFAULT NULL,
  `session_id` int unsigned DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_par_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emp_comp` (`employe_id`,`competence_id`),
  KEY `competence_id` (`competence_id`),
  KEY `session_id` (`session_id`),
  CONSTRAINT `rh_employe_competences_ibfk_1` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rh_employe_competences_ibfk_2` FOREIGN KEY (`competence_id`) REFERENCES `rh_competences` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rh_employe_competences_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `rh_formations_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_employe_competences` WRITE;
/*!40000 ALTER TABLE `rh_employe_competences` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_employe_competences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_employe_fonctions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employe_fonctions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `fonction_id` int unsigned NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = fonction principale',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employe_fonction_debut` (`employe_id`,`fonction_id`,`date_debut`),
  KEY `idx_ef_employe` (`employe_id`),
  KEY `idx_ef_fonction` (`fonction_id`),
  CONSTRAINT `fk_ef_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ef_fonction` FOREIGN KEY (`fonction_id`) REFERENCES `rh_fonctions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_employe_fonctions` WRITE;
/*!40000 ALTER TABLE `rh_employe_fonctions` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_employe_fonctions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_employes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL COMMENT 'FK users.id — compte système, optionnel',
  `professeur_id` int unsigned DEFAULT NULL COMMENT 'FK professeurs.id — uniquement enseignants V1',
  `matricule` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format YYYY-NNNNN',
  `type_personnel` enum('enseignant','administratif','support','direction','technique') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `lieu_naissance` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` enum('M','F','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationalite` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Algérienne',
  `cni_numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cni_expiration` date DEFAULT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_pro` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_perso` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departement_id` int unsigned DEFAULT NULL COMMENT 'FK rh_departements.id',
  `poste_id` int unsigned DEFAULT NULL COMMENT 'FK rh_postes.id',
  `date_entree` date DEFAULT NULL,
  `date_sortie` date DEFAULT NULL,
  `motif_sortie` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statut` enum('actif','inactif','suspendu','conge','retraite','demissionnaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `diplome` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialite` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notes internes RH (confidentielles)',
  `cree_par_id` int unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricule` (`matricule`),
  KEY `idx_user` (`user_id`),
  KEY `idx_professeur` (`professeur_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_type` (`type_personnel`),
  KEY `idx_departement` (`departement_id`),
  KEY `idx_deleted` (`deleted_at`),
  KEY `idx_email_pro` (`email_pro`),
  CONSTRAINT `fk63_rh_employes_professeur_id` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk63_rh_employes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_employes` WRITE;
/*!40000 ALTER TABLE `rh_employes` DISABLE KEYS */;
INSERT INTO `rh_employes` VALUES (1,5,1,'2026-00001','enseignant','Nom22','Prenom26',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000001','Nom22@ecole.dz',NULL,NULL,NULL,NULL,'2015-09-01',NULL,NULL,'actif',NULL,'Mathématiques',NULL,1,NULL,'2026-07-11 17:57:23','2026-10-29 20:04:55'),(2,NULL,2,'2026-00002','enseignant','Nom09','Prenom07',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000002','Nom09@ecole.dz',NULL,NULL,NULL,NULL,'2013-09-01',NULL,NULL,'actif',NULL,'Physique',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55'),(3,NULL,3,'2026-00003','enseignant','Nom15','Prenom01',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000003','Nom15@ecole.dz',NULL,NULL,NULL,NULL,'2018-09-01',NULL,NULL,'actif',NULL,'Langue Arabe',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55'),(4,NULL,4,'2026-00004','enseignant','Nom27','Prenom20',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000004','Nom27@ecole.dz',NULL,NULL,NULL,NULL,'2019-09-01',NULL,NULL,'actif',NULL,'Langue Française',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55'),(5,NULL,5,'2026-00005','enseignant','Nom12','Prenom34',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000005','Nom12@ecole.dz',NULL,NULL,NULL,NULL,'2020-09-01',NULL,NULL,'actif',NULL,'Langue Anglaise',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55'),(6,NULL,6,'2026-00006','enseignant','Nom16','Prenom27',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000006','Nom16@ecole.dz',NULL,NULL,NULL,NULL,'2010-09-01',NULL,NULL,'actif',NULL,'Sciences Naturelles',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55'),(7,NULL,7,'2026-00007','enseignant','Nom33','Prenom17',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000007','Nom33@ecole.dz',NULL,NULL,NULL,NULL,'2022-09-01',NULL,NULL,'actif',NULL,'Histoire-Géographie',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55'),(8,NULL,8,'2026-00008','enseignant','Nom35','Prenom33',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000008','Nom35@ecole.dz',NULL,NULL,NULL,NULL,'2021-09-01',NULL,NULL,'actif',NULL,'Philosophie',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55'),(9,NULL,9,'2026-00009','enseignant','Nom29','Prenom30',NULL,NULL,NULL,'Algérienne',NULL,NULL,NULL,'0600000009','Nom29@ecole.example',NULL,NULL,NULL,NULL,'2026-07-09',NULL,NULL,'actif',NULL,'Mathématiques',NULL,1,NULL,'2026-07-11 17:57:24','2026-10-29 20:04:55');
/*!40000 ALTER TABLE `rh_employes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_enseignant_matieres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_enseignant_matieres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `enseignant_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `niveaux` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Niveaux séparés par virgule : primaire,moyen,secondaire',
  `priorite` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 = matière principale, 2 = secondaire',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enseignant_matiere` (`enseignant_id`,`matiere_id`),
  KEY `idx_em_matiere` (`matiere_id`),
  CONSTRAINT `fk_em_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `rh_enseignants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_em_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitations matières des enseignants — Phase 6.3';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_enseignant_matieres` WRITE;
/*!40000 ALTER TABLE `rh_enseignant_matieres` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_enseignant_matieres` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_enseignant_qualifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_enseignant_qualifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `enseignant_id` int unsigned NOT NULL,
  `type` enum('diplome','certification','formation','experience','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diplome',
  `intitule` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `organisme` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_obtention` date DEFAULT NULL,
  `date_expiration` date DEFAULT NULL,
  `document_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Réservé pour le module Documents (Phase 6.9)',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_eq_enseignant` (`enseignant_id`),
  KEY `idx_eq_type` (`type`),
  KEY `idx_eq_expiration` (`date_expiration`),
  CONSTRAINT `fk_eq_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `rh_enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Qualifications et certifications des enseignants — Phase 6.3';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_enseignant_qualifications` WRITE;
/*!40000 ALTER TABLE `rh_enseignant_qualifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_enseignant_qualifications` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_enseignants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_enseignants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `statut_pedagogique` enum('titulaire','vacataire','remplacant','stagiaire','contractuel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'titulaire',
  `specialite_principale` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charge_horaire_max` tinyint unsigned NOT NULL DEFAULT '18' COMMENT 'Heures hebdomadaires maximales',
  `charge_horaire_actuelle` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Heures hebdomadaires actuelles (mis à jour lors des affectations)',
  `date_debut_enseignement` date DEFAULT NULL,
  `notes_pedagogiques` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enseignant_employe` (`employe_id`),
  KEY `idx_ens_statut` (`statut_pedagogique`),
  KEY `idx_ens_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_rh_ens_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profils pédagogiques des enseignants — Phase 6.3';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_enseignants` WRITE;
/*!40000 ALTER TABLE `rh_enseignants` DISABLE KEYS */;
INSERT INTO `rh_enseignants` VALUES (1,1,'titulaire','Mathématiques',18,0,'2015-09-01','Importé automatiquement depuis professeurs V1 (id=1) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(2,2,'titulaire','Physique',18,0,'2013-09-01','Importé automatiquement depuis professeurs V1 (id=2) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(3,3,'titulaire','Langue Arabe',18,0,'2018-09-01','Importé automatiquement depuis professeurs V1 (id=3) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(4,4,'titulaire','Langue Française',18,0,'2019-09-01','Importé automatiquement depuis professeurs V1 (id=4) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(5,5,'titulaire','Langue Anglaise',18,0,'2020-09-01','Importé automatiquement depuis professeurs V1 (id=5) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(6,6,'titulaire','Sciences Naturelles',18,0,'2010-09-01','Importé automatiquement depuis professeurs V1 (id=6) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(7,7,'titulaire','Histoire-Géographie',18,0,'2022-09-01','Importé automatiquement depuis professeurs V1 (id=7) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(8,8,'titulaire','Philosophie',18,0,'2021-09-01','Importé automatiquement depuis professeurs V1 (id=8) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24'),(9,9,'titulaire','Mathématiques',18,0,'2026-07-09','Importé automatiquement depuis professeurs V1 (id=9) le 2026-07-11',NULL,'2026-07-11 17:57:24','2026-07-11 17:57:24');
/*!40000 ALTER TABLE `rh_enseignants` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_evaluation_criteres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_evaluation_criteres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `critere_id` int unsigned NOT NULL,
  `note_auto_eval` decimal(4,2) DEFAULT NULL,
  `note_evaluateur` decimal(4,2) DEFAULT NULL,
  `commentaire_auto_eval` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `commentaire_evaluateur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ec` (`evaluation_id`,`critere_id`),
  KEY `critere_id` (`critere_id`),
  CONSTRAINT `rh_evaluation_criteres_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `rh_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rh_evaluation_criteres_ibfk_2` FOREIGN KEY (`critere_id`) REFERENCES `rh_criteres_evaluation` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_evaluation_criteres` WRITE;
/*!40000 ALTER TABLE `rh_evaluation_criteres` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_evaluation_criteres` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_evaluation_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_evaluation_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `statut_avant` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut_apres` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `effectue_par` int unsigned NOT NULL,
  `effectue_par_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evaluation_id` (`evaluation_id`),
  CONSTRAINT `rh_evaluation_historique_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `rh_evaluations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_evaluation_historique` WRITE;
/*!40000 ALTER TABLE `rh_evaluation_historique` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_evaluation_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campagne_id` int unsigned NOT NULL,
  `employe_id` int unsigned NOT NULL,
  `affectation_id` int unsigned DEFAULT NULL,
  `evaluateur_id` int unsigned DEFAULT NULL,
  `evaluateur_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('brouillon','en_auto_evaluation','en_evaluation','soumise','validee','publiee','archivee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'brouillon',
  `score_auto_eval` decimal(5,2) DEFAULT NULL,
  `score_evaluateur` decimal(5,2) DEFAULT NULL,
  `score_final` decimal(5,2) DEFAULT NULL,
  `mention` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire_auto_eval` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `commentaire_evaluateur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `commentaire_validation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_auto_eval` datetime DEFAULT NULL,
  `date_evaluation` datetime DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `date_publication` datetime DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_par_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publie_par` int unsigned DEFAULT NULL,
  `publie_par_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eval` (`campagne_id`,`employe_id`),
  KEY `employe_id` (`employe_id`),
  KEY `affectation_id` (`affectation_id`),
  CONSTRAINT `rh_evaluations_ibfk_1` FOREIGN KEY (`campagne_id`) REFERENCES `rh_campagnes_evaluation` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rh_evaluations_ibfk_2` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rh_evaluations_ibfk_3` FOREIGN KEY (`affectation_id`) REFERENCES `rh_affectations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_evaluations` WRITE;
/*!40000 ALTER TABLE `rh_evaluations` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_evaluations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_fonctions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_fonctions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `niveau` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1=opérationnel 2=encadrement 3=direction',
  `perimetre` enum('etablissement','departement','service','transversal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transversal',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_fonction_actif` (`actif`),
  KEY `idx_fonction_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_fonctions` WRITE;
/*!40000 ALTER TABLE `rh_fonctions` DISABLE KEYS */;
INSERT INTO `rh_fonctions` VALUES (1,'Chef de département','CHEF_DEPT',NULL,3,'departement',1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(2,'Responsable de service','RESP_SVC',NULL,2,'service',1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(3,'Coordinateur pédagogique','COORD_PED',NULL,2,'transversal',1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(4,'Tuteur','TUTEUR',NULL,1,'transversal',1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(5,'Délégué du personnel','DEL_PERS',NULL,2,'etablissement',1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(6,'Référent sécurité','REF_SEC',NULL,2,'etablissement',1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(7,'Référent numérique','REF_NUM',NULL,1,'transversal',1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59');
/*!40000 ALTER TABLE `rh_fonctions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_formation_competences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formation_competences` (
  `formation_id` int unsigned NOT NULL,
  `competence_id` int unsigned NOT NULL,
  PRIMARY KEY (`formation_id`,`competence_id`),
  KEY `competence_id` (`competence_id`),
  CONSTRAINT `rh_formation_competences_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `rh_formations_catalogue` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rh_formation_competences_ibfk_2` FOREIGN KEY (`competence_id`) REFERENCES `rh_competences` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_formation_competences` WRITE;
/*!40000 ALTER TABLE `rh_formation_competences` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_formation_competences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_formations_catalogue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_catalogue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` enum('interne','externe','e_learning','certification','coaching','seminaire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'interne',
  `duree_heures` decimal(6,1) NOT NULL DEFAULT '0.0',
  `niveau` enum('debutant','intermediaire','avance','expert') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'debutant',
  `modalite` enum('presentiel','distanciel','hybride') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'presentiel',
  `organisme_id` int unsigned DEFAULT NULL,
  `formateur_principal` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cout_unitaire` decimal(10,2) DEFAULT '0.00',
  `devise` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'XAF',
  `max_participants` tinyint DEFAULT '20',
  `prerequis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `objectifs` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) DEFAULT '1',
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_formation_code` (`code`),
  KEY `organisme_id` (`organisme_id`),
  CONSTRAINT `rh_formations_catalogue_ibfk_1` FOREIGN KEY (`organisme_id`) REFERENCES `rh_formations_organismes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_formations_catalogue` WRITE;
/*!40000 ALTER TABLE `rh_formations_catalogue` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_formations_catalogue` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_formations_inscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_inscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int unsigned NOT NULL,
  `employe_id` int unsigned NOT NULL,
  `statut` enum('inscrit','confirme','present','absent','valide','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'inscrit',
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_confirmation` datetime DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `note_evaluation` decimal(4,2) DEFAULT NULL,
  `commentaire_evaluation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attestation_delivree` tinyint(1) DEFAULT '0',
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inscription` (`session_id`,`employe_id`),
  KEY `employe_id` (`employe_id`),
  CONSTRAINT `rh_formations_inscriptions_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `rh_formations_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rh_formations_inscriptions_ibfk_2` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_formations_inscriptions` WRITE;
/*!40000 ALTER TABLE `rh_formations_inscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_formations_inscriptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_formations_organismes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_organismes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('interne','externe','certifiant','universite') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'externe',
  `contact_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_tel` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_web` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pays` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ville` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_organisme_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_formations_organismes` WRITE;
/*!40000 ALTER TABLE `rh_formations_organismes` DISABLE KEYS */;
INSERT INTO `rh_formations_organismes` VALUES (1,'INTERNE','Formation interne établissement','interne',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-07-11 16:14:33','2026-07-11 16:14:33');
/*!40000 ALTER TABLE `rh_formations_organismes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_formations_presences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_presences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `inscription_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `present` tinyint(1) DEFAULT '1',
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_presence` (`inscription_id`,`date`),
  CONSTRAINT `rh_formations_presences_ibfk_1` FOREIGN KEY (`inscription_id`) REFERENCES `rh_formations_inscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_formations_presences` WRITE;
/*!40000 ALTER TABLE `rh_formations_presences` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_formations_presences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_formations_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_sessions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `formation_id` int unsigned NOT NULL,
  `code_session` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lieu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `heure_debut` time DEFAULT '08:00:00',
  `heure_fin` time DEFAULT '17:00:00',
  `statut` enum('planifiee','ouverte','en_cours','terminee','annulee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'planifiee',
  `max_participants` tinyint DEFAULT '20',
  `nb_inscrits` tinyint DEFAULT '0',
  `formateur_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cout_total` decimal(10,2) DEFAULT '0.00',
  `financeur` enum('etablissement','organisme','personnel','mixte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'etablissement',
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_code` (`code_session`),
  KEY `formation_id` (`formation_id`),
  CONSTRAINT `rh_formations_sessions_ibfk_1` FOREIGN KEY (`formation_id`) REFERENCES `rh_formations_catalogue` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_formations_sessions` WRITE;
/*!40000 ALTER TABLE `rh_formations_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_formations_sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_historique_affectations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_historique_affectations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `affectation_id` int unsigned NOT NULL,
  `type_changement` enum('creation','modification','transfert','suspension','reactivation','cloture','archivage') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ancienne_valeur` json DEFAULT NULL,
  `nouvelle_valeur` json DEFAULT NULL,
  `motif` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifie_par` int unsigned NOT NULL,
  `modifie_par_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_affhist_affect` (`affectation_id`),
  CONSTRAINT `fk_haffect_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `rh_affectations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_historique_affectations` WRITE;
/*!40000 ALTER TABLE `rh_historique_affectations` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_historique_affectations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_historique_org`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_historique_org` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type_entite` enum('departement','service','poste','fonction','affectation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entite_id` int unsigned NOT NULL,
  `action` enum('creation','modification','archivage','restauration','affectation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ancienne_valeur` json DEFAULT NULL,
  `nouvelle_valeur` json DEFAULT NULL,
  `modifie_par_id` int unsigned DEFAULT NULL COMMENT 'FK users.id',
  `modifie_par_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dénormalisé pour audit pérenne',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_horg_entite` (`type_entite`,`entite_id`),
  KEY `idx_horg_action` (`action`),
  KEY `idx_horg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_historique_org` WRITE;
/*!40000 ALTER TABLE `rh_historique_org` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_historique_org` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_plans_developpement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_plans_developpement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `employe_id` int unsigned NOT NULL,
  `objectif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ressources` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `echeance` date DEFAULT NULL,
  `statut` enum('en_cours','realise','abandonne') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_cours',
  `progression` tinyint DEFAULT '0',
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evaluation_id` (`evaluation_id`),
  KEY `employe_id` (`employe_id`),
  CONSTRAINT `rh_plans_developpement_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `rh_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rh_plans_developpement_ibfk_2` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_plans_developpement` WRITE;
/*!40000 ALTER TABLE `rh_plans_developpement` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_plans_developpement` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_postes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_postes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `intitule` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `departement_id` int unsigned DEFAULT NULL,
  `service_id` int unsigned DEFAULT NULL COMMENT 'FK rh_services.id — optionnel',
  `categorie` enum('enseignant','administratif','support','direction','technique') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'administratif',
  `niveau` tinyint NOT NULL DEFAULT '1' COMMENT '1=junior 2=confirmé 3=senior 4=chef',
  `nb_occupants_max` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Nb maximal de titulaires simultanés',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_departement` (`departement_id`),
  KEY `idx_categorie` (`categorie`),
  KEY `idx_actif` (`actif`),
  KEY `fk_poste_service` (`service_id`),
  CONSTRAINT `fk_poste_service` FOREIGN KEY (`service_id`) REFERENCES `rh_services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_postes` WRITE;
/*!40000 ALTER TABLE `rh_postes` DISABLE KEYS */;
INSERT INTO `rh_postes` VALUES (1,'Directeur(trice)','DIR',NULL,NULL,'direction',4,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(2,'Directeur(trice) adjoint(e)','DIR_ADJ',NULL,NULL,'direction',3,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(3,'Secrétaire de direction','SEC_DIR',NULL,NULL,'administratif',2,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(4,'Secrétaire','SEC',NULL,NULL,'administratif',1,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(5,'Comptable','COMPTA',NULL,NULL,'administratif',2,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(6,'Professeur certifié (PES)','ENS_PES',NULL,NULL,'enseignant',3,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(7,'Professeur de l\'enseignement moyen (PEM)','ENS_PEM',NULL,NULL,'enseignant',2,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(8,'Professeur contractuel','ENS_CONT',NULL,NULL,'enseignant',1,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(9,'Vacataire','ENS_VAC',NULL,NULL,'enseignant',1,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(10,'Maître formateur','ENS_MF',NULL,NULL,'enseignant',4,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(11,'Surveillant général','SURV',NULL,NULL,'support',2,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(12,'Agent d\'entretien','ENTRET',NULL,NULL,'support',1,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL),(13,'Informaticien','INFOR',NULL,NULL,'technique',2,1,NULL,1,'2026-07-11 16:06:50','2026-07-11 16:06:50',NULL);
/*!40000 ALTER TABLE `rh_postes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_presences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_presences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL COMMENT 'Employé concerné — FK RESTRICT (présence impossible sans employé)',
  `affectation_id` int unsigned DEFAULT NULL COMMENT 'Affectation active au moment du pointage — FK SET NULL',
  `date_presence` date NOT NULL COMMENT 'Date du jour travaillé',
  `heure_arrivee` time DEFAULT NULL COMMENT 'Heure d''entrée — NULL si absent ou pas encore pointé',
  `heure_depart` time DEFAULT NULL COMMENT 'Heure de sortie — NULL si sortie non encore enregistrée',
  `duree_minutes` int unsigned DEFAULT NULL COMMENT 'Durée travaillée calculée par AttendanceService (en minutes)',
  `statut` enum('present','absent','retard','sortie_anticipee','mi_temps','mission','heure_sup') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present' COMMENT 'Statut de présence du jour',
  `mode_pointage` enum('manuel','badge','qr_code','biometrie','api_externe') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manuel' COMMENT 'Mode utilisé pour ce pointage',
  `retard_minutes` int unsigned NOT NULL DEFAULT '0' COMMENT 'Minutes de retard détectées par rapport à heure_reference_arrivee',
  `heures_supp_minutes` int unsigned NOT NULL DEFAULT '0' COMMENT 'Minutes en dépassement par rapport à duree_reference_minutes',
  `heure_reference_arrivee` time NOT NULL DEFAULT '08:00:00' COMMENT 'Heure théorique d''entrée pour le calcul des retards',
  `duree_reference_minutes` smallint unsigned NOT NULL DEFAULT '480' COMMENT 'Durée journée théorique en minutes (défaut 8h = 480)',
  `motif` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motif renseigné au pointage (mission, formation…)',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notes libres',
  `source_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Identifiant badge, numéro biométrique ou ID API externe',
  `source_data` json DEFAULT NULL COMMENT 'Données brutes provenant de la source externe — Préparation V3',
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Texte de justification (absence / retard)',
  `justifie_par` int unsigned DEFAULT NULL COMMENT 'ID utilisateur ayant saisi la justification',
  `date_justification` datetime DEFAULT NULL,
  `statut_validation` enum('en_attente','valide','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `valide_par` int unsigned DEFAULT NULL COMMENT 'ID utilisateur ayant validé ou rejeté',
  `date_validation` datetime DEFAULT NULL,
  `motif_rejet` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete — jamais de DELETE physique',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_presence_affectation` (`affectation_id`),
  KEY `idx_presence_employe_date` (`employe_id`,`date_presence`),
  KEY `idx_presence_date` (`date_presence`),
  KEY `idx_presence_statut` (`statut`),
  KEY `idx_presence_mode` (`mode_pointage`),
  KEY `idx_presence_validation` (`statut_validation`),
  KEY `idx_presence_deleted` (`deleted_at`),
  CONSTRAINT `fk_presence_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `rh_affectations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_presence_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Présences journalières du personnel — Phase 6.7 RH V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_presences` WRITE;
/*!40000 ALTER TABLE `rh_presences` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_presences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_presences_regularisations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_presences_regularisations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `presence_id` int unsigned NOT NULL COMMENT 'Présence concernée par la régularisation',
  `type_regularisation` enum('correction_heure','correction_statut','justification','annulation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nature de la correction apportée',
  `valeur_ancienne` json DEFAULT NULL COMMENT 'Snapshot des champs AVANT la régularisation',
  `valeur_nouvelle` json DEFAULT NULL COMMENT 'Snapshot des champs APRÈS la régularisation',
  `motif` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Motif de la régularisation (obligatoire)',
  `regularise_par` int unsigned NOT NULL COMMENT 'ID utilisateur ayant effectué la régularisation',
  `regularise_par_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom dénormalisé — conservé même si l''utilisateur est supprimé',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reg_presence` (`presence_id`),
  CONSTRAINT `fk_reg_presence` FOREIGN KEY (`presence_id`) REFERENCES `rh_presences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Régularisations et corrections de présence — Phase 6.7 RH V2';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_presences_regularisations` WRITE;
/*!40000 ALTER TABLE `rh_presences_regularisations` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_presences_regularisations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `departement_id` int unsigned NOT NULL,
  `nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `responsable_id` int unsigned DEFAULT NULL COMMENT 'FK rh_employes.id',
  `ordre_affichage` tinyint unsigned NOT NULL DEFAULT '0',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_service_departement` (`departement_id`),
  KEY `idx_service_actif` (`actif`),
  KEY `idx_service_deleted` (`deleted_at`),
  CONSTRAINT `fk_service_departement` FOREIGN KEY (`departement_id`) REFERENCES `rh_departements` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_services` WRITE;
/*!40000 ALTER TABLE `rh_services` DISABLE KEYS */;
INSERT INTO `rh_services` VALUES (1,1,'Direction Pédagogique','DIR_PED','Supervision pédagogique globale',NULL,0,1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(2,2,'Scolarité','SCOL','Inscriptions, dossiers élèves, suivi',NULL,0,1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(3,2,'Accueil & Communication','ACCUEIL','Réception, correspondance, affichage',NULL,0,1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(4,4,'Comptabilité Générale','COMPTA_G','Tenue des comptes, bilans, clôtures',NULL,0,1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(5,4,'Recouvrement','RECOUVR','Suivi paiements, impayés, relances',NULL,0,1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(6,6,'Maintenance','MAINT','Entretien des locaux et équipements',NULL,0,1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59'),(7,6,'Informatique','INFO','Parc informatique, réseau, logiciels',NULL,0,1,NULL,'2026-07-11 16:12:59','2026-07-11 16:12:59');
/*!40000 ALTER TABLE `rh_services` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_soldes_conges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_soldes_conges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `type_conge_id` int unsigned NOT NULL,
  `annee` year NOT NULL,
  `solde_initial` decimal(5,1) NOT NULL DEFAULT '0.0',
  `solde_pris` decimal(5,1) NOT NULL DEFAULT '0.0',
  `solde_en_attente` decimal(5,1) NOT NULL DEFAULT '0.0',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employe_type_annee` (`employe_id`,`type_conge_id`,`annee`),
  KEY `fk_solde_type_conge` (`type_conge_id`),
  CONSTRAINT `fk_solde_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_solde_type_conge` FOREIGN KEY (`type_conge_id`) REFERENCES `rh_types_conges` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_soldes_conges` WRITE;
/*!40000 ALTER TABLE `rh_soldes_conges` DISABLE KEYS */;
/*!40000 ALTER TABLE `rh_soldes_conges` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rh_types_conges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_types_conges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `duree_max_jours` smallint unsigned DEFAULT NULL COMMENT 'NULL = illimité',
  `is_paye` tinyint(1) NOT NULL DEFAULT '1',
  `necessite_justificatif` tinyint(1) NOT NULL DEFAULT '0',
  `necessite_approbation` tinyint(1) NOT NULL DEFAULT '1',
  `debit_solde` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = ne décompte pas le solde annuel',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rh_types_conges` WRITE;
/*!40000 ALTER TABLE `rh_types_conges` DISABLE KEYS */;
INSERT INTO `rh_types_conges` VALUES (1,'annuel','Congé annuel',NULL,30,1,0,1,1,1,1,'2026-07-11 16:13:16',NULL),(2,'maladie','Congé maladie',NULL,NULL,1,1,0,1,1,2,'2026-07-11 16:13:16',NULL),(3,'maternite','Congé maternité',NULL,98,1,1,0,0,1,3,'2026-07-11 16:13:16',NULL),(4,'paternite','Congé paternité',NULL,10,1,0,1,0,1,4,'2026-07-11 16:13:16',NULL),(5,'exceptionnel','Congé exceptionnel',NULL,5,1,1,1,1,1,5,'2026-07-11 16:13:16',NULL),(6,'permission','Permission d\'absence',NULL,1,1,0,1,1,1,6,'2026-07-11 16:13:16',NULL),(7,'mission','Mission',NULL,NULL,1,0,1,0,1,7,'2026-07-11 16:13:16',NULL),(8,'recuperation','Récupération',NULL,NULL,1,0,1,1,1,8,'2026-07-11 16:13:16',NULL),(9,'absence_injustifiee','Absence injustifiée',NULL,NULL,0,0,0,1,1,9,'2026-07-11 16:13:16',NULL);
/*!40000 ALTER TABLE `rh_types_conges` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role` enum('admin','directeur','secretaire','comptable','enseignant','parent','eleve') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`role`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES ('admin',1),('directeur',1),('secretaire',1),('comptable',1),('enseignant',1),('admin',2),('directeur',2),('secretaire',2),('admin',3),('directeur',3),('secretaire',3),('admin',4),('directeur',4),('admin',5),('directeur',5),('comptable',5),('admin',6),('directeur',6),('admin',7),('directeur',7),('admin',8),('directeur',8),('admin',9),('directeur',9),('secretaire',9),('comptable',9),('enseignant',9),('admin',10),('directeur',10),('admin',11),('directeur',11),('admin',12),('directeur',12),('admin',13),('directeur',13),('enseignant',13),('admin',14),('directeur',14),('enseignant',14),('admin',15),('directeur',15),('enseignant',15),('admin',16),('directeur',16),('admin',17),('directeur',17),('parent',17),('eleve',17),('admin',18),('directeur',18),('secretaire',18),('enseignant',18),('admin',19),('directeur',19),('secretaire',19),('enseignant',19),('admin',20),('directeur',20),('secretaire',20),('enseignant',20),('admin',21),('directeur',21),('parent',21),('eleve',21),('admin',22),('directeur',22),('admin',23),('directeur',23),('admin',24),('directeur',24),('admin',25),('admin',26),('directeur',26),('secretaire',26),('comptable',26),('admin',27),('directeur',27),('secretaire',27),('comptable',27),('enseignant',27),('admin',28),('directeur',28),('admin',29),('directeur',29),('admin',30),('directeur',30);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `t056_parents_rattaches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `t056_parents_rattaches` (
  `user_id` int unsigned NOT NULL,
  `etablissement_id` int unsigned NOT NULL,
  `ue_id` bigint unsigned DEFAULT NULL,
  `rbac_id` int unsigned DEFAULT NULL,
  `ure_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `fk63_t056_parents_rattaches_etablissement_id` (`etablissement_id`),
  CONSTRAINT `fk63_t056_parents_rattaches_etablissement_id` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_t056_parents_rattaches_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `t056_parents_rattaches` WRITE;
/*!40000 ALTER TABLE `t056_parents_rattaches` DISABLE KEYS */;
INSERT INTO `t056_parents_rattaches` VALUES (281,1,80,8,123,'2026-10-28 06:16:12'),(282,1,81,9,124,'2026-10-28 06:16:12'),(283,1,82,10,125,'2026-10-28 06:16:12'),(284,1,83,11,126,'2026-10-28 06:16:12'),(285,1,84,12,127,'2026-10-28 06:16:12'),(286,1,85,13,128,'2026-10-28 06:16:12'),(287,1,86,14,129,'2026-10-28 06:16:12'),(288,1,87,15,130,'2026-10-28 06:16:12'),(289,1,88,16,131,'2026-10-28 06:16:12');
/*!40000 ALTER TABLE `t056_parents_rattaches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `types_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `types_evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'Établissement propriétaire ; NULL = catalogue système ou propriété non démontrée',
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cl├® immuable, correspond au type V1 pour les types syst├¿me',
  `nom` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `coefficient_defaut` decimal(4,2) NOT NULL DEFAULT '1.00' COMMENT 'Coefficient sugg├®r├® lors de la cr├®ation d''une ├®valuation',
  `note_max_defaut` decimal(5,2) NOT NULL DEFAULT '20.00' COMMENT 'Bar├¿me sugg├®r├® (note maximale)',
  `est_eliminatoire` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = note en dessous du seuil entra├«ne ├®limination automatique',
  `seuil_eliminatoire` decimal(5,2) DEFAULT NULL COMMENT 'Note sur note_max_defaut d├®clenchant l''├®limination',
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Couleur hex #RRGGBB pour l''UI',
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom ic├┤ne Lucide',
  `ordre` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Ordre d''affichage dans les s├®lecteurs',
  `actif` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = d├®sactiv├® (n''appara├«t plus dans les listes)',
  `est_archive` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = archiv├® d├®finitivement, non r├®activable',
  `est_systeme` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = type V1 prot├®g├®, requiert r├┤le admin pour modifications majeures',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_te_etab_code` (`etablissement_id`,`code`),
  KEY `idx_te_actif` (`actif`),
  KEY `idx_te_archive` (`est_archive`),
  KEY `idx_te_ordre` (`ordre`),
  KEY `idx_te_systeme` (`est_systeme`),
  KEY `idx_te_etab` (`etablissement_id`),
  CONSTRAINT `fk_te_etablissement` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `types_evaluations` WRITE;
/*!40000 ALTER TABLE `types_evaluations` DISABLE KEYS */;
INSERT INTO `types_evaluations` VALUES (1,NULL,'controle','Contr├┤le','Contr├┤le de cours classique',1.00,20.00,0,NULL,'#6366f1','file-text',1,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(2,1,'devoir','Devoir surveill├®','Devoir en classe sous surveillance',1.00,20.00,0,NULL,'#8b5cf6','pencil',2,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(3,1,'examen','Examen','Examen de fin de p├®riode ou semestriel',2.00,20.00,0,NULL,'#ef4444','graduation-cap',3,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(4,NULL,'tp','Travaux Pratiques','TP en salle de TP ou laboratoire',1.00,20.00,0,NULL,'#10b981','flask-conical',4,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(5,NULL,'oral','Oral','├ëvaluation orale individuelle',1.00,20.00,0,NULL,'#f59e0b','mic',5,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(6,NULL,'devoir','Devoir surveill├®','Devoir en classe sous surveillance',1.00,20.00,0,NULL,'#8b5cf6','pencil',2,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(7,NULL,'examen','Examen','Examen de fin de p├®riode ou semestriel',2.00,20.00,0,NULL,'#ef4444','graduation-cap',3,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(8,1,'controle','Contr├┤le','Contr├┤le de cours classique',1.00,20.00,0,NULL,'#6366f1','file-text',1,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(9,1,'tp','Travaux Pratiques','TP en salle de TP ou laboratoire',1.00,20.00,0,NULL,'#10b981','flask-conical',4,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23'),(10,1,'oral','Oral','├ëvaluation orale individuelle',1.00,20.00,0,NULL,'#f59e0b','mic',5,1,0,1,NULL,'2026-07-20 01:44:23','2026-07-20 01:44:23');
/*!40000 ALTER TABLE `types_evaluations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_etablissements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_etablissements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `etablissement_id` int unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` datetime DEFAULT NULL,
  `invited_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_userEtab` (`user_id`,`etablissement_id`),
  KEY `idx_ue_user` (`user_id`),
  KEY `idx_ue_etab` (`etablissement_id`),
  KEY `fk_ue_inviter` (`invited_by`),
  CONSTRAINT `fk_ue_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ue_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ue_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_etablissements` WRITE;
/*!40000 ALTER TABLE `user_etablissements` DISABLE KEYS */;
INSERT INTO `user_etablissements` VALUES (1,1,1,1,'2026-07-08 10:51:15',NULL,NULL),(2,2,1,1,'2026-07-08 10:51:15',NULL,NULL),(3,3,1,1,'2026-07-08 10:51:15',NULL,NULL),(4,4,1,1,'2026-07-08 10:51:15',NULL,NULL),(5,5,1,1,'2026-07-08 10:51:15',NULL,NULL),(6,6,1,1,'2026-07-08 10:51:15',NULL,NULL),(7,7,1,1,'2026-07-08 10:51:15',NULL,NULL),(80,281,1,1,'2026-10-28 06:16:12',NULL,NULL),(81,282,1,1,'2026-10-28 06:16:12',NULL,NULL),(82,283,1,1,'2026-10-28 06:16:12',NULL,NULL),(83,284,1,1,'2026-10-28 06:16:12',NULL,NULL),(84,285,1,1,'2026-10-28 06:16:12',NULL,NULL),(85,286,1,1,'2026-10-28 06:16:12',NULL,NULL),(86,287,1,1,'2026-10-28 06:16:12',NULL,NULL),(87,288,1,1,'2026-10-28 06:16:12',NULL,NULL),(88,289,1,1,'2026-10-28 06:16:12',NULL,NULL);
/*!40000 ALTER TABLE `user_etablissements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_roles_etab`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles_etab` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `etablissement_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_userRoleEtab` (`user_id`,`etablissement_id`,`role_id`),
  KEY `idx_ure_user` (`user_id`),
  KEY `idx_ure_etab` (`etablissement_id`),
  KEY `idx_ure_role` (`role_id`),
  KEY `fk_ure_assigner` (`assigned_by`),
  CONSTRAINT `fk_ure_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ure_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ure_role` FOREIGN KEY (`role_id`) REFERENCES `etab_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ure_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_roles_etab` WRITE;
/*!40000 ALTER TABLE `user_roles_etab` DISABLE KEYS */;
INSERT INTO `user_roles_etab` VALUES (1,1,1,1,'2026-07-08 10:51:14',NULL),(2,2,1,2,'2026-07-08 10:51:14',NULL),(3,3,1,3,'2026-07-08 10:51:14',NULL),(4,4,1,4,'2026-07-08 10:51:15',NULL),(5,5,1,5,'2026-07-08 10:51:15',NULL),(6,6,1,6,'2026-07-08 10:51:15',NULL),(7,7,1,7,'2026-07-08 10:51:15',NULL),(123,281,1,6,'2026-10-28 06:16:12',NULL),(124,282,1,6,'2026-10-28 06:16:12',NULL),(125,283,1,6,'2026-10-28 06:16:12',NULL),(126,284,1,6,'2026-10-28 06:16:12',NULL),(127,285,1,6,'2026-10-28 06:16:12',NULL),(128,286,1,6,'2026-10-28 06:16:12',NULL),(129,287,1,6,'2026-10-28 06:16:12',NULL),(130,288,1,6,'2026-10-28 06:16:12',NULL),(131,289,1,6,'2026-10-28 06:16:12',NULL);
/*!40000 ALTER TABLE `user_roles_etab` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `dernier_etablissement_id` int unsigned DEFAULT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','directeur','secretaire','comptable','enseignant','parent','eleve') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'secretaire',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `derniere_connexion` datetime DEFAULT NULL,
  `reset_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_etab` (`etablissement_id`,`role`),
  KEY `fk_users_dernier_etab` (`dernier_etablissement_id`),
  CONSTRAINT `fk_users_dernier_etab` FOREIGN KEY (`dernier_etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=511 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,1,'Administrateur',NULL,'admin@ecole.dz',NULL,NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','admin',1,'2026-06-25 09:30:33','2026-10-28 21:21:41','2026-10-28 22:21:41',NULL,NULL),(2,1,1,'Nom08','Prenom18','directeur@ecole.dz',NULL,NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','directeur',1,'2026-06-25 09:30:39','2026-10-29 19:04:55','2026-10-26 01:52:15',NULL,NULL),(3,1,1,'Nom06','Prenom09','secretaire@ecole.dz','',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','secretaire',1,'2026-06-25 09:30:39','2026-10-29 19:04:55','2026-10-26 01:55:54',NULL,NULL),(4,1,1,'Nom19','Prenom29','comptable@ecole.dz',NULL,NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','comptable',1,'2026-06-25 09:30:39','2026-10-29 19:04:55','2026-10-26 01:57:04',NULL,NULL),(5,1,1,'Nom11','Prenom03','enseignant@ecole.dz',NULL,NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','enseignant',1,'2026-06-25 09:30:39','2026-10-29 19:04:55','2026-10-26 01:58:12',NULL,NULL),(6,1,1,'Nom31','Prenom27','parent@ecole.dz',NULL,NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-06-25 09:30:39','2026-10-29 19:04:55','2026-10-26 02:03:18',NULL,NULL),(7,1,1,'Nom04','Prenom40','eleve@ecole.dz',NULL,NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','eleve',1,'2026-06-25 09:30:39','2026-10-29 19:04:55','2026-10-26 02:04:39',NULL,NULL),(281,1,NULL,'Nom08','Nom26','parent.eleve-1@demo.ecole.local','0600000281',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:18','2026-10-29 19:04:55',NULL,NULL,NULL),(282,1,NULL,'Nom25','Prenom25','parent.eleve-2@demo.ecole.local','0600000282',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:18','2026-10-29 19:04:55',NULL,NULL,NULL),(283,1,NULL,'Nom19','Prenom29','parent.eleve-3@demo.ecole.local','0600000283',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:18','2026-10-29 19:04:55',NULL,NULL,NULL),(284,1,NULL,'Nom13','Prenom11','parent.eleve-4@demo.ecole.local','0600000284',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:18','2026-10-29 19:04:55',NULL,NULL,NULL),(285,1,NULL,'Nom14','Prenom16','parent.eleve-5@demo.ecole.local','0600000285',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:18','2026-10-29 19:04:55',NULL,NULL,NULL),(286,1,NULL,'Nom07','Prenom36','parent.eleve-7@demo.ecole.local','0600000286',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:18','2026-10-29 19:04:55',NULL,NULL,NULL),(287,1,NULL,'Nom32','Prenom10','parent.eleve-8@demo.ecole.local','0600000287',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:19','2026-10-29 19:04:55',NULL,NULL,NULL),(288,1,NULL,'Nom05','Prenom12','parent.eleve-9@demo.ecole.local','0600000288',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:19','2026-10-29 19:04:55',NULL,NULL,NULL),(289,1,NULL,'Nom23','Prenom22','parent.eleve-10@demo.ecole.local','0600000289',NULL,'$2y$10$uO4Y17gnIJoMI0EMKkVw9.T2la1qZfUlytr4bJkf4UGC0etBMFrHu','parent',1,'2026-08-14 00:54:19','2026-10-29 19:04:55',NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_absences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_absence` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `duree_heures` decimal(4,2) DEFAULT NULL COMMENT 'Calculé ou saisi manuellement',
  `type` enum('absence','retard','dispense') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'absence',
  `statut` enum('non_justifiee','en_attente','justifiee','refusee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non_justifiee',
  `saisie_par` int unsigned NOT NULL,
  `observation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_abs_eleve_date` (`eleve_id`,`date_absence`),
  KEY `idx_abs_classe_date` (`classe_id`,`date_absence`),
  KEY `idx_abs_statut` (`statut`,`date_absence`),
  KEY `idx_abs_annee` (`annee_scolaire`,`classe_id`),
  KEY `idx_abs_soft_delete` (`deleted_at`),
  CONSTRAINT `fk63_vs_absences_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_absences_eleve_id` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_absences` WRITE;
/*!40000 ALTER TABLE `vs_absences` DISABLE KEYS */;
INSERT INTO `vs_absences` VALUES (3,1,1,'2024-2025','2026-06-22',NULL,NULL,NULL,'absence','non_justifiee',5,NULL,NULL,'2026-06-25 10:39:36','2026-08-20 22:58:38'),(4,2,1,'2024-2025','2026-06-22',NULL,NULL,NULL,'absence','non_justifiee',5,NULL,NULL,'2026-06-25 10:39:36','2026-08-20 22:58:38'),(5,3,2,'2024-2025','2026-06-22',NULL,NULL,NULL,'absence','non_justifiee',5,NULL,NULL,'2026-06-25 10:39:36','2026-08-20 22:58:38'),(6,1,1,'2024-2025','2026-06-18',NULL,NULL,NULL,'absence','justifiee',1,NULL,NULL,'2026-06-25 10:39:36','2026-08-20 22:58:38'),(7,2,1,'2024-2025','2026-06-18',NULL,NULL,NULL,'absence','justifiee',1,NULL,NULL,'2026-06-25 10:39:36','2026-08-20 22:58:38');
/*!40000 ALTER TABLE `vs_absences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_activite_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#8B5CF6',
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_activite_etab_code` (`etablissement_id`,`code`),
  KEY `idx_activite_etab` (`etablissement_id`),
  CONSTRAINT `fk_activite_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_activite_categories` WRITE;
/*!40000 ALTER TABLE `vs_activite_categories` DISABLE KEYS */;
INSERT INTO `vs_activite_categories` VALUES (1,NULL,'Club','club','#8B5CF6','users',1),(2,NULL,'Association','association','#3B82F6','heart-handshake',1),(3,NULL,'Événement','evenement','#10B981','calendar-days',1),(4,NULL,'Sortie pédagogique','sortie','#F59E0B','map-pin',1),(5,NULL,'Compétition','competition','#EF4444','trophy',1),(6,NULL,'Cérémonie','ceremonie','#6366F1','star',1),(7,NULL,'Conférence','conference','#14B8A6','mic',1),(8,NULL,'Atelier','atelier','#F97316','wrench',1),(9,1,'Club','club','#8B5CF6','users',1),(10,1,'Association','association','#3B82F6','heart-handshake',1),(11,1,'Événement','evenement','#10B981','calendar-days',1),(12,1,'Sortie pédagogique','sortie','#F59E0B','map-pin',1),(13,1,'Compétition','competition','#EF4444','trophy',1),(14,1,'Cérémonie','ceremonie','#6366F1','star',1),(15,1,'Conférence','conference','#14B8A6','mic',1),(16,1,'Atelier','atelier','#F97316','wrench',1);
/*!40000 ALTER TABLE `vs_activite_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_activite_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_classes` (
  `activite_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  PRIMARY KEY (`activite_id`,`classe_id`),
  KEY `fk_vs_actcl_classe` (`classe_id`),
  CONSTRAINT `fk_vs_actcl_activite` FOREIGN KEY (`activite_id`) REFERENCES `vs_activites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vs_actcl_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_activite_classes` WRITE;
/*!40000 ALTER TABLE `vs_activite_classes` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_activite_classes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_activite_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `activite_id` int unsigned NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `data_json` json DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vs_acthist_activite` (`activite_id`),
  KEY `idx_vs_acthist_action` (`action`),
  CONSTRAINT `fk_vs_acthist_activite` FOREIGN KEY (`activite_id`) REFERENCES `vs_activites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_activite_historique` WRITE;
/*!40000 ALTER TABLE `vs_activite_historique` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_activite_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_activite_inscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_inscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `activite_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `statut` enum('inscrit','liste_attente','annule','present','absent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inscrit',
  `date_inscription` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `inscrit_par` int unsigned DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `annule_le` datetime DEFAULT NULL,
  `motif_annulation` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vs_inscr_eleve_activite` (`activite_id`,`eleve_id`,`deleted_at`),
  KEY `idx_vs_inscr_statut` (`statut`),
  KEY `idx_vs_inscr_eleve` (`eleve_id`),
  KEY `idx_vs_inscr_deleted` (`deleted_at`),
  CONSTRAINT `fk63_vs_activite_inscriptions_eleve_id` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_vs_inscr_activite` FOREIGN KEY (`activite_id`) REFERENCES `vs_activites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_activite_inscriptions` WRITE;
/*!40000 ALTER TABLE `vs_activite_inscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_activite_inscriptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_activite_responsables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_responsables` (
  `activite_id` int unsigned NOT NULL,
  `enseignant_id` int unsigned NOT NULL,
  PRIMARY KEY (`activite_id`,`enseignant_id`),
  KEY `fk_vs_actresp_enseignant` (`enseignant_id`),
  CONSTRAINT `fk_vs_actresp_activite` FOREIGN KEY (`activite_id`) REFERENCES `vs_activites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vs_actresp_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_activite_responsables` WRITE;
/*!40000 ALTER TABLE `vs_activite_responsables` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_activite_responsables` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_activites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categorie_id` int unsigned NOT NULL,
  `titre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `lieu` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_activite` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `capacite_max` smallint unsigned NOT NULL DEFAULT '30',
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('brouillon','publie','en_cours','termine','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `organisateur_id` int unsigned DEFAULT NULL COMMENT 'user_id organisateur',
  `cree_par` int unsigned NOT NULL,
  `publie_par` int unsigned DEFAULT NULL,
  `publie_le` datetime DEFAULT NULL,
  `termine_le` datetime DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `annule_le` datetime DEFAULT NULL,
  `motif_annulation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vs_act_categorie` (`categorie_id`),
  KEY `idx_vs_act_date` (`date_activite`),
  KEY `idx_vs_act_statut` (`statut`),
  KEY `idx_vs_act_annee` (`annee_scolaire`),
  KEY `idx_vs_act_deleted` (`deleted_at`),
  CONSTRAINT `fk_vs_act_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `vs_activite_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_activites` WRITE;
/*!40000 ALTER TABLE `vs_activites` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_activites` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_appels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_appels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `classe_id` int unsigned NOT NULL,
  `matiere_id` int unsigned DEFAULT NULL,
  `enseignant_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_appel` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `type_appel` enum('journalier','seance') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'journalier',
  `statut` enum('brouillon','valide') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `observation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appel_classe_date_heure` (`classe_id`,`date_appel`,`heure_debut`),
  KEY `idx_appel_classe_date` (`classe_id`,`date_appel`),
  KEY `idx_appel_enseignant` (`enseignant_id`,`date_appel`),
  KEY `idx_appel_statut` (`statut`,`date_appel`),
  KEY `idx_appel_annee` (`annee_scolaire`,`classe_id`),
  KEY `idx_appel_soft_delete` (`deleted_at`),
  KEY `fk63_vs_appels_matiere_id` (`matiere_id`),
  CONSTRAINT `fk63_vs_appels_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_appels_enseignant_id` FOREIGN KEY (`enseignant_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_appels_matiere_id` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_appels` WRITE;
/*!40000 ALTER TABLE `vs_appels` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_appels` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_appels_discipline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_appels_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sanction_id` int unsigned NOT NULL,
  `dossier_id` int unsigned NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `piece_jointe` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('depose','examine','accepte','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'depose',
  `depose_par` int unsigned NOT NULL,
  `depose_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `examine_par` int unsigned DEFAULT NULL,
  `examine_le` datetime DEFAULT NULL,
  `decision` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appel_sanction` (`sanction_id`),
  KEY `idx_appel_dossier` (`dossier_id`,`statut`),
  CONSTRAINT `fk63_vs_appels_discipline_dossier_id` FOREIGN KEY (`dossier_id`) REFERENCES `vs_dossiers_discipline` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_appels_discipline` WRITE;
/*!40000 ALTER TABLE `vs_appels_discipline` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_appels_discipline` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_discipline_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_discipline_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gravite_defaut` enum('mineur','moyen','grave','tres_grave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mineur',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_discipli_etab_code` (`etablissement_id`,`code`),
  KEY `idx_discipli_etab` (`etablissement_id`),
  CONSTRAINT `fk_discipli_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_discipline_categories` WRITE;
/*!40000 ALTER TABLE `vs_discipline_categories` DISABLE KEYS */;
INSERT INTO `vs_discipline_categories` VALUES (1,NULL,'VIOLENCE','Violence physique',NULL,'grave',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(2,NULL,'INCIVILITE','Incivilité / Irrespect',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(3,NULL,'FRAUDE','Fraude / Triche',NULL,'grave',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(4,NULL,'PERTURBATION','Perturbation de cours',NULL,'mineur',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(5,NULL,'ABSENTEISME','Absentéisme répété',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(6,NULL,'DEGRADATION','Dégradation de matériel',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(7,NULL,'HARCELEMENT','Harcèlement / Intimidation',NULL,'tres_grave',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(8,NULL,'RETARDS','Retards répétés',NULL,'mineur',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(9,NULL,'REFUS','Refus d\'obtempérer',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(10,NULL,'AUTRE','Autre comportement',NULL,'mineur',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(11,1,'VIOLENCE','Violence physique',NULL,'grave',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(12,1,'INCIVILITE','Incivilité / Irrespect',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(13,1,'FRAUDE','Fraude / Triche',NULL,'grave',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(14,1,'PERTURBATION','Perturbation de cours',NULL,'mineur',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(15,1,'ABSENTEISME','Absentéisme répété',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(16,1,'DEGRADATION','Dégradation de matériel',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(17,1,'HARCELEMENT','Harcèlement / Intimidation',NULL,'tres_grave',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(18,1,'RETARDS','Retards répétés',NULL,'mineur',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(19,1,'REFUS','Refus d\'obtempérer',NULL,'moyen',1,'2026-07-10 17:17:21','2026-07-10 17:17:21'),(20,1,'AUTRE','Autre comportement',NULL,'mineur',1,'2026-07-10 17:17:21','2026-07-10 17:17:21');
/*!40000 ALTER TABLE `vs_discipline_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_dossiers_discipline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_dossiers_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('ouvert','en_cours','clos','appel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `nb_incidents` smallint unsigned NOT NULL DEFAULT '0',
  `note_interne` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cree_par` int unsigned NOT NULL,
  `clos_par` int unsigned DEFAULT NULL,
  `clos_le` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dossier_eleve_annee` (`eleve_id`,`annee_scolaire`),
  KEY `idx_dossier_classe_annee` (`classe_id`,`annee_scolaire`),
  KEY `idx_dossier_statut` (`statut`,`deleted_at`),
  CONSTRAINT `fk63_vs_dossiers_discipline_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_dossiers_discipline_eleve_id` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_dossiers_discipline` WRITE;
/*!40000 ALTER TABLE `vs_dossiers_discipline` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_dossiers_discipline` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_edt_creneaux`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_creneaux` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `emploi_du_temps_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `enseignant_id` int unsigned NOT NULL,
  `salle_id` int unsigned DEFAULT NULL,
  `jour` tinyint unsigned NOT NULL COMMENT '1=Lundi 6=Samedi',
  `plage_id` tinyint unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_cours` enum('cours','td','tp','sport','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cours',
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_creneau_edt` (`emploi_du_temps_id`),
  KEY `idx_creneau_enseignant` (`enseignant_id`,`jour`,`plage_id`,`annee_scolaire`),
  KEY `idx_creneau_salle` (`salle_id`,`jour`,`plage_id`,`annee_scolaire`),
  KEY `idx_creneau_classe` (`classe_id`,`jour`,`plage_id`,`annee_scolaire`),
  KEY `fk63_vs_edt_creneaux_matiere_id` (`matiere_id`),
  KEY `fk63_vs_edt_creneaux_plage_id` (`plage_id`),
  CONSTRAINT `fk63_vs_edt_creneaux_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_edt_creneaux_enseignant_id` FOREIGN KEY (`enseignant_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_edt_creneaux_matiere_id` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_edt_creneaux_plage_id` FOREIGN KEY (`plage_id`) REFERENCES `vs_edt_plages_horaires` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_edt_creneaux_salle_id` FOREIGN KEY (`salle_id`) REFERENCES `vs_edt_salles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_edt_creneaux` WRITE;
/*!40000 ALTER TABLE `vs_edt_creneaux` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_edt_creneaux` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_edt_plages_horaires`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_plages_horaires` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'Établissement propriétaire ; NULL = propriété non démontrée (invisible, à arbitrer)',
  `libelle` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `ordre` tinyint unsigned NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_plage_etab` (`etablissement_id`),
  CONSTRAINT `fk_plage_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_edt_plages_horaires` WRITE;
/*!40000 ALTER TABLE `vs_edt_plages_horaires` DISABLE KEYS */;
INSERT INTO `vs_edt_plages_horaires` VALUES (10,1,'Cours 1','08:00:00','09:00:00',1,1),(11,1,'Cours 2','09:00:00','10:00:00',2,1),(12,1,'Récréation','10:00:00','10:30:00',3,1),(13,1,'Cours 3','10:30:00','11:30:00',4,1),(14,1,'Cours 4','11:30:00','12:30:00',5,1),(15,1,'Pause midi','12:30:00','13:30:00',6,1),(16,1,'Cours 5','13:30:00','14:30:00',7,1),(17,1,'Cours 6','14:30:00','15:30:00',8,1),(18,1,'Pause','15:30:00','15:45:00',9,1),(19,1,'Cours 7','15:45:00','16:45:00',10,1),(20,1,'Cours 8','16:45:00','17:45:00',11,1);
/*!40000 ALTER TABLE `vs_edt_plages_horaires` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_edt_remplacements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_remplacements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `creneau_id` int unsigned NOT NULL,
  `date_remplacement` date NOT NULL,
  `enseignant_absent_id` int unsigned NOT NULL,
  `remplacant_id` int unsigned DEFAULT NULL,
  `matiere_remplacement_id` int unsigned DEFAULT NULL,
  `salle_remplacement_id` int unsigned DEFAULT NULL,
  `statut` enum('planifie','confirme','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planifie',
  `motif_absence` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cree_par` int unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_remplacement` (`creneau_id`,`date_remplacement`),
  KEY `idx_remplacement_date` (`date_remplacement`),
  KEY `idx_remplacement_absent` (`enseignant_absent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_edt_remplacements` WRITE;
/*!40000 ALTER TABLE `vs_edt_remplacements` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_edt_remplacements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_edt_salles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_salles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'Établissement propriétaire ; NULL = propriété non démontrée (invisible, à arbitrer)',
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacite` smallint unsigned DEFAULT '30',
  `type` enum('cours','labo','sport','informatique','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cours',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salle_etab_code` (`etablissement_id`,`code`),
  KEY `idx_salle_etab` (`etablissement_id`),
  CONSTRAINT `fk_salle_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_edt_salles` WRITE;
/*!40000 ALTER TABLE `vs_edt_salles` DISABLE KEYS */;
INSERT INTO `vs_edt_salles` VALUES (1,1,'Salle A01','SALLEA01',35,'cours',1,'2026-08-20 23:27:02'),(2,1,'Salle A02','SALLEA02',35,'cours',1,'2026-08-20 23:27:02'),(3,1,'Salle A03','SALLEA03',35,'cours',1,'2026-08-20 23:27:02'),(4,1,'Salle B01','SALLEB01',30,'cours',1,'2026-08-20 23:27:02'),(5,1,'Salle B02','SALLEB02',30,'cours',1,'2026-08-20 23:27:02'),(6,1,'Labo Sciences','LABOSCIENCES',24,'labo',1,'2026-08-20 23:27:02'),(7,1,'Salle Info','SALLEINFO',30,'informatique',1,'2026-08-20 23:27:02'),(8,1,'Amphithéâtre','AMPHITHTRE',80,'autre',1,'2026-08-20 23:27:02');
/*!40000 ALTER TABLE `vs_edt_salles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_edt_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `emploi_du_temps_id` int unsigned NOT NULL,
  `version` smallint unsigned NOT NULL,
  `snapshot` json NOT NULL,
  `motif` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_version_edt` (`emploi_du_temps_id`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_edt_versions` WRITE;
/*!40000 ALTER TABLE `vs_edt_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_edt_versions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_emplois_du_temps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_emplois_du_temps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `periode_id` int unsigned DEFAULT NULL,
  `semaine_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `statut` enum('brouillon','publie','archive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `publie_par` int unsigned DEFAULT NULL,
  `publie_le` timestamp NULL DEFAULT NULL,
  `cree_par` int unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_edt_classe` (`classe_id`,`annee_scolaire`),
  KEY `idx_edt_statut` (`statut`),
  KEY `fk63_vs_emplois_du_temps_periode_id` (`periode_id`),
  CONSTRAINT `fk63_vs_emplois_du_temps_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_emplois_du_temps_periode_id` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_emplois_du_temps` WRITE;
/*!40000 ALTER TABLE `vs_emplois_du_temps` DISABLE KEYS */;
INSERT INTO `vs_emplois_du_temps` VALUES (2,131,'2026-2027',90,'standard',1,'brouillon',NULL,NULL,2,NULL,'2026-10-26 00:52:56','2026-10-26 00:52:56');
/*!40000 ALTER TABLE `vs_emplois_du_temps` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_incidents_discipline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_incidents_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dossier_id` int unsigned NOT NULL,
  `categorie_id` int unsigned NOT NULL,
  `gravite` enum('mineur','moyen','grave','tres_grave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_incident` date NOT NULL,
  `heure_incident` time DEFAULT NULL,
  `lieu` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matiere_id` int unsigned DEFAULT NULL COMMENT 'Matière concernée, si applicable',
  `signale_par` int unsigned NOT NULL,
  `statut` enum('ouvert','traite','classe') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `piece_jointe` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incident_dossier` (`dossier_id`,`statut`),
  KEY `idx_incident_date` (`date_incident`),
  KEY `idx_incident_gravite` (`gravite`),
  KEY `fk63_vs_incidents_discipline_matiere_id` (`matiere_id`),
  CONSTRAINT `fk63_vs_incidents_discipline_dossier_id` FOREIGN KEY (`dossier_id`) REFERENCES `vs_dossiers_discipline` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_incidents_discipline_matiere_id` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_incidents_discipline` WRITE;
/*!40000 ALTER TABLE `vs_incidents_discipline` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_incidents_discipline` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_justifications_absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_justifications_absences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `absence_id` int unsigned NOT NULL,
  `motif_id` int unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fichier_justificatif` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chemin relatif via UploadService',
  `statut` enum('en_attente','validee','refusee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `soumis_par` int unsigned NOT NULL,
  `soumis_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `motif_refus` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_justif_absence` (`absence_id`),
  KEY `idx_justif_statut` (`statut`,`soumis_le`),
  KEY `idx_justif_valide_par` (`valide_par`),
  KEY `fk_justif_motif` (`motif_id`),
  CONSTRAINT `fk_justif_absence` FOREIGN KEY (`absence_id`) REFERENCES `vs_absences` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_justif_motif` FOREIGN KEY (`motif_id`) REFERENCES `vs_motifs_absence` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_justifications_absences` WRITE;
/*!40000 ALTER TABLE `vs_justifications_absences` DISABLE KEYS */;
INSERT INTO `vs_justifications_absences` VALUES (1,6,1,'Maladie — certificat médical fourni',NULL,'validee',6,'2026-06-25 10:39:36',1,'2026-06-25 10:39:36',NULL,'2026-08-20 22:58:38','2026-08-21 01:20:29'),(2,7,NULL,'Justification migrée depuis absences V1 (id=8)',NULL,'validee',1,'2026-06-25 10:39:36',1,'2026-06-25 10:39:36',NULL,'2026-08-20 22:58:38','2026-08-20 22:58:38');
/*!40000 ALTER TABLE `vs_justifications_absences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_justifications_retards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_justifications_retards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `retard_id` int unsigned NOT NULL,
  `motif_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Motif textuel libre (ou concat avec motif_code)',
  `fichier_justificatif` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('en_attente','validee','refusee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `soumis_par` int unsigned NOT NULL,
  `soumis_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `motif_refus` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_justif_retard` (`retard_id`),
  KEY `idx_justif_statut` (`statut`),
  CONSTRAINT `fk63_vs_justifications_retards_retard_id` FOREIGN KEY (`retard_id`) REFERENCES `vs_retards` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_justifications_retards` WRITE;
/*!40000 ALTER TABLE `vs_justifications_retards` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_justifications_retards` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_motifs_absence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_motifs_absence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` enum('maladie','familial','transport','scolaire','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `necessite_justificatif` tinyint(1) NOT NULL DEFAULT '1',
  `compte_comme_absence` tinyint(1) NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_motifsab_etab_code` (`etablissement_id`,`code`),
  KEY `idx_motifsab_etab` (`etablissement_id`),
  CONSTRAINT `fk_motifsab_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_motifs_absence` WRITE;
/*!40000 ALTER TABLE `vs_motifs_absence` DISABLE KEYS */;
INSERT INTO `vs_motifs_absence` VALUES (1,1,'MALADIE','Maladie','maladie',1,1,1,'2026-07-10 17:17:08'),(2,NULL,'MEDECIN','Rendez-vous médical','maladie',1,1,1,'2026-07-10 17:17:08'),(3,NULL,'DEUIL','Deuil familial','familial',0,0,1,'2026-07-10 17:17:08'),(4,NULL,'FAMILLE','Raison familiale','familial',1,1,1,'2026-07-10 17:17:08'),(5,NULL,'TRANSPORT','Problème de transport','transport',0,1,1,'2026-07-10 17:17:08'),(6,NULL,'ACT_SCOLAIRE','Activité scolaire','scolaire',0,0,1,'2026-07-10 17:17:08'),(7,NULL,'AUTRE','Autre motif','autre',1,1,1,'2026-07-10 17:17:08'),(8,NULL,'MALADIE','Maladie','maladie',1,1,1,'2026-07-10 17:17:08'),(9,1,'MEDECIN','Rendez-vous médical','maladie',1,1,1,'2026-07-10 17:17:08'),(10,1,'DEUIL','Deuil familial','familial',0,0,1,'2026-07-10 17:17:08'),(11,1,'FAMILLE','Raison familiale','familial',1,1,1,'2026-07-10 17:17:08'),(12,1,'TRANSPORT','Problème de transport','transport',0,1,1,'2026-07-10 17:17:08'),(13,1,'ACT_SCOLAIRE','Activité scolaire','scolaire',0,0,1,'2026-07-10 17:17:08'),(14,1,'AUTRE','Autre motif','autre',1,1,1,'2026-07-10 17:17:08');
/*!40000 ALTER TABLE `vs_motifs_absence` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_presences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_presences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `appel_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `statut` enum('present','absent','retard','dispense','sortie_anticipee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `heure_arrivee` time DEFAULT NULL,
  `retard_minutes` smallint unsigned DEFAULT NULL,
  `observation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `absence_id` int unsigned DEFAULT NULL,
  `saisie_par` int unsigned NOT NULL,
  `modifie_par` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_presence_appel_eleve` (`appel_id`,`eleve_id`),
  KEY `idx_presence_eleve_appel` (`eleve_id`,`appel_id`),
  KEY `idx_presence_statut` (`statut`),
  KEY `idx_presence_absence` (`absence_id`),
  CONSTRAINT `fk63_vs_presences_eleve_id` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_presence_absence` FOREIGN KEY (`absence_id`) REFERENCES `vs_absences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_presence_appel` FOREIGN KEY (`appel_id`) REFERENCES `vs_appels` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_presences` WRITE;
/*!40000 ALTER TABLE `vs_presences` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_presences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_presences_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_presences_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `presence_id` int unsigned NOT NULL,
  `appel_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `ancien_statut` enum('present','absent','retard','dispense','sortie_anticipee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nouveau_statut` enum('present','absent','retard','dispense','sortie_anticipee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_presence` (`presence_id`),
  KEY `idx_hist_appel` (`appel_id`),
  KEY `idx_hist_eleve` (`eleve_id`),
  CONSTRAINT `fk63_vs_presences_historique_appel_id` FOREIGN KEY (`appel_id`) REFERENCES `vs_appels` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_presences_historique_eleve_id` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_presences_historique` WRITE;
/*!40000 ALTER TABLE `vs_presences_historique` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_presences_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_recompense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_recompense_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée',
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#6366f1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recompen_etab_code` (`etablissement_id`,`code`),
  KEY `idx_recompen_etab` (`etablissement_id`),
  CONSTRAINT `fk_recompen_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_recompense_categories` WRITE;
/*!40000 ALTER TABLE `vs_recompense_categories` DISABLE KEYS */;
INSERT INTO `vs_recompense_categories` VALUES (1,NULL,'Félicitations','felicitations','Félicitations pour comportement ou résultats excellents','#16a34a',1,'2026-07-10 16:17:28'),(2,NULL,'Encouragements','encouragements','Encouragements pour efforts notables','#2563eb',1,'2026-07-10 16:17:28'),(3,NULL,'Tableau d\'honneur','tableau_honneur','Inscription au tableau d\'honneur de l\'établissement','#7c3aed',1,'2026-07-10 16:17:28'),(4,NULL,'Excellence académique','excellence_academique','Distinction pour résultats académiques exceptionnels','#d97706',1,'2026-07-10 16:17:28'),(5,NULL,'Bon comportement','bon_comportement','Récompense pour comportement exemplaire','#0891b2',1,'2026-07-10 16:17:28'),(6,NULL,'Esprit citoyen','esprit_citoyen','Reconnaissance d\'un acte civique ou solidaire','#65a30d',1,'2026-07-10 16:17:28'),(7,NULL,'Participation exceptionnelle','participation_exceptionnelle','Valorisation d\'une implication remarquable','#e11d48',1,'2026-07-10 16:17:28'),(8,NULL,'Autre','autre','Autre type de distinction','#64748b',1,'2026-07-10 16:17:28'),(9,1,'Félicitations','felicitations','Félicitations pour comportement ou résultats excellents','#16a34a',1,'2026-07-10 16:17:28'),(10,1,'Encouragements','encouragements','Encouragements pour efforts notables','#2563eb',1,'2026-07-10 16:17:28'),(11,1,'Tableau d\'honneur','tableau_honneur','Inscription au tableau d\'honneur de l\'établissement','#7c3aed',1,'2026-07-10 16:17:28'),(12,1,'Excellence académique','excellence_academique','Distinction pour résultats académiques exceptionnels','#d97706',1,'2026-07-10 16:17:28'),(13,1,'Bon comportement','bon_comportement','Récompense pour comportement exemplaire','#0891b2',1,'2026-07-10 16:17:28'),(14,1,'Esprit citoyen','esprit_citoyen','Reconnaissance d\'un acte civique ou solidaire','#65a30d',1,'2026-07-10 16:17:28'),(15,1,'Participation exceptionnelle','participation_exceptionnelle','Valorisation d\'une implication remarquable','#e11d48',1,'2026-07-10 16:17:28'),(16,1,'Autre','autre','Autre type de distinction','#64748b',1,'2026-07-10 16:17:28');
/*!40000 ALTER TABLE `vs_recompense_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_recompenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_recompenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie_id` int unsigned NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` enum('classe','etablissement','academique') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'classe',
  `statut` enum('attribuee','validee','revoquee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attribuee',
  `date_attribution` date NOT NULL,
  `attribue_par` int unsigned NOT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` timestamp NULL DEFAULT NULL,
  `revoque_par` int unsigned DEFAULT NULL,
  `revoque_le` timestamp NULL DEFAULT NULL,
  `motif_revocation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `piece_jointe` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`,`annee_scolaire`),
  KEY `idx_classe` (`classe_id`,`annee_scolaire`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date` (`date_attribution`),
  CONSTRAINT `fk63_vs_recompenses_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_recompenses_eleve_id` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_recompenses` WRITE;
/*!40000 ALTER TABLE `vs_recompenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_recompenses` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_recompenses_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_recompenses_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `recompense_id` int unsigned NOT NULL,
  `ancien_statut` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recompense` (`recompense_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_recompenses_historique` WRITE;
/*!40000 ALTER TABLE `vs_recompenses_historique` DISABLE KEYS */;
INSERT INTO `vs_recompenses_historique` VALUES (1,2,NULL,'attribuee','Attribution initiale',169,'2026-07-11 14:23:22');
/*!40000 ALTER TABLE `vs_recompenses_historique` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_retards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_retards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_retard` date NOT NULL,
  `heure_prevue` time DEFAULT NULL COMMENT 'Heure de début attendue (peut être NULL si appel journalier sans heure)',
  `heure_arrivee` time NOT NULL COMMENT 'Heure réelle d''arrivée',
  `duree_minutes` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Calculé : heure_arrivee - heure_prevue en minutes',
  `statut` enum('non_justifie','en_attente','justifie','refuse') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non_justifie',
  `appel_id` int unsigned DEFAULT NULL COMMENT 'FK vs_appels — NULL si saisie manuelle',
  `presence_id` int unsigned DEFAULT NULL COMMENT 'FK vs_presences — NULL si saisie manuelle',
  `saisie_par` int unsigned NOT NULL,
  `observation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_retard_eleve_date` (`eleve_id`,`date_retard`),
  KEY `idx_retard_classe_date` (`classe_id`,`date_retard`),
  KEY `idx_retard_statut` (`statut`,`deleted_at`),
  KEY `idx_retard_annee` (`annee_scolaire`,`classe_id`),
  KEY `idx_retard_presence` (`presence_id`),
  KEY `idx_retard_soft_delete` (`deleted_at`),
  KEY `fk63_vs_retards_appel_id` (`appel_id`),
  CONSTRAINT `fk63_vs_retards_appel_id` FOREIGN KEY (`appel_id`) REFERENCES `vs_appels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk63_vs_retards_classe_id` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk63_vs_retards_eleve_id` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_retards` WRITE;
/*!40000 ALTER TABLE `vs_retards` DISABLE KEYS */;
INSERT INTO `vs_retards` VALUES (7,1,1,'2024-2025','2026-06-24','08:00:00','08:20:00',20,'non_justifie',NULL,NULL,5,'Transport (migré depuis absences V1 id=4, heure reconstituée)',NULL,'2026-06-25 10:39:36','2026-08-21 01:03:10'),(8,2,1,'2024-2025','2026-06-24','08:00:00','08:20:00',20,'non_justifie',NULL,NULL,5,'Transport (migré depuis absences V1 id=5, heure reconstituée)',NULL,'2026-06-25 10:39:36','2026-08-21 01:03:10');
/*!40000 ALTER TABLE `vs_retards` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_sanctions_discipline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_sanctions_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dossier_id` int unsigned NOT NULL,
  `incident_id` int unsigned DEFAULT NULL COMMENT 'NULL = sanction portant sur le dossier global',
  `type_sanction` enum('avertissement','blame','exclusion_cours','exclusion_temp','exclusion_def','travaux','conseil','mesure_educative') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_sanction` date NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `duree_jours` smallint unsigned DEFAULT NULL,
  `statut` enum('prononcee','effective','executee','levee','appelee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prononcee',
  `prononce_par` int unsigned NOT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sanction_dossier` (`dossier_id`,`statut`),
  KEY `idx_sanction_date` (`date_sanction`),
  CONSTRAINT `fk63_vs_sanctions_discipline_dossier_id` FOREIGN KEY (`dossier_id`) REFERENCES `vs_dossiers_discipline` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_sanctions_discipline` WRITE;
/*!40000 ALTER TABLE `vs_sanctions_discipline` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_sanctions_discipline` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `vs_sanctions_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_sanctions_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sanction_id` int unsigned NOT NULL,
  `ancien_statut` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nouveau_statut` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sh_sanction` (`sanction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `vs_sanctions_historique` WRITE;
/*!40000 ALTER TABLE `vs_sanctions_historique` DISABLE KEYS */;
/*!40000 ALTER TABLE `vs_sanctions_historique` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

