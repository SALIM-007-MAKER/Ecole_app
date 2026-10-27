-- SCOLARIS / EduNova — schéma de référence (structure seule) généré depuis une base à jour jusqu'à la migration T062.
-- Contient : toutes les tables, clés, déclencheurs ; données : rôles et permissions RBAC (etab_*) et journal des migrations uniquement.
-- Aucune donnée personnelle, aucun compte. À importer dans une base VIDE (aucun DROP : un import sur une base existante échoue).
-- Voir docs/deploiement/INSTALLATION.md §4.
SET NAMES utf8mb4; SET FOREIGN_KEY_CHECKS=0;

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `annees_scolaires` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format : 2025-2026',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `libelle` (`libelle`),
  KEY `idx_as_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Référentiel des années scolaires';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `annonces` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` enum('tous','parents','eleves','enseignants','classe','utilisateurs') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tous',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_uploads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `module` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_etab` (`etablissement_id`),
  KEY `idx_etab_active` (`etablissement_id`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appreciations_matiere` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `professeur_id` int unsigned DEFAULT NULL,
  `texte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etablissement_id` int unsigned NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appreciation_eleve_matiere_periode` (`eleve_id`,`matiere_id`,`periode_id`),
  KEY `idx_appr_classe_matiere_periode` (`classe_id`,`matiere_id`,`periode_id`),
  KEY `fk_appr_matiere` (`matiere_id`),
  KEY `fk_appr_periode` (`periode_id`),
  CONSTRAINT `fk_appr_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appr_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appr_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL COMMENT 'NULL = action système ou connexion échouée',
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create|update|delete|login|login_failed|logout|export|import|permission_denied',
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'eleves|notes|absences|comptabilite|auth|parametres|...',
  `entite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom de la table cible',
  `entite_id` int unsigned DEFAULT NULL COMMENT 'PK de l enregistrement affecté',
  `avant` json DEFAULT NULL COMMENT 'État avant (update/delete)',
  `apres` json DEFAULT NULL COMMENT 'État après  (create/update)',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user` (`user_id`),
  KEY `idx_al_module` (`module`,`action`),
  KEY `idx_al_entite` (`entite`,`entite_id`),
  KEY `idx_al_date` (`created_at`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal immuable de toutes les actions utilisateurs';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_orphelins_archive` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ligne_id` bigint unsigned NOT NULL,
  `donnees` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `archive_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orph_mig` (`migration`,`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bulletin_tokens_retired` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `token_sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `bulletin_id` int unsigned DEFAULT NULL,
  `motif` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `retire_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_btr_sha` (`token_sha256`),
  KEY `idx_btr_bulletin` (`bulletin_id`),
  CONSTRAINT `fk_btr_bulletin` FOREIGN KEY (`bulletin_id`) REFERENCES `bulletins_v2` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bulletins_v2` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `verification_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_version` tinyint unsigned NOT NULL DEFAULT '1',
  `moyenne` decimal(5,2) DEFAULT NULL,
  `rang` smallint unsigned DEFAULT NULL,
  `nb_eleves` smallint unsigned DEFAULT NULL,
  `mention_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision` enum('admis','rattrapage','refuse','indeterminate') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'indeterminate',
  `appreciation_pp` text COLLATE utf8mb4_unicode_ci,
  `appreciation_directeur` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('brouillon','publie','archive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `data_json` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Snapshot BulletinData s├®rialis├®',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: 1ère AS, 2ème AM',
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: 2024-2025',
  `max_eleves` tinyint unsigned NOT NULL DEFAULT '35',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classe_nom_niveau_annee` (`etablissement_id`,`nom`,`niveau`,`annee_scolaire`),
  KEY `idx_etab` (`etablissement_id`,`annee_scolaire`),
  CONSTRAINT `fk_classes_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `controles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('controle','devoir','examen','tp','oral') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'controle',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `depenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categorie_id` int unsigned DEFAULT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_depense` date NOT NULL,
  `mode_paiement` enum('especes','cheque','virement','carte') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especes',
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `saisi_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_d_date` (`date_depense`),
  KEY `idx_d_categorie` (`categorie_id`),
  KEY `fk_d_user` (`saisi_par`),
  CONSTRAINT `fk_d_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `depenses_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_d_user` FOREIGN KEY (`saisi_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `depenses_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6c757d',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eleves` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `matricule` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date NOT NULL,
  `sexe` enum('M','F') COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classe_id` int unsigned DEFAULT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL COMMENT 'Compte utilisateur de l''élève (users.role=eleve)',
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enseignements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `professeur_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ens` (`professeur_id`,`matiere_id`,`classe_id`,`annee_scolaire`),
  KEY `fk_ens_matiere` (`matiere_id`),
  KEY `fk_ens_classe` (`classe_id`),
  KEY `idx_etab` (`etablissement_id`,`classe_id`),
  CONSTRAINT `fk_ens_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ens_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ens_prof` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enseignements_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etab_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etabperm_code` (`code`),
  KEY `idx_etabperm_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etab_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'NULL = rôle système global',
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etabrole_scope_code` (`etablissement_id`,`code`),
  KEY `idx_etabrole_etab` (`etablissement_id`),
  CONSTRAINT `fk_etabrole_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etab_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `section` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text COLLATE utf8mb4_unicode_ci,
  `type` enum('string','integer','boolean','json','datetime') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etablissement_branding` (
  `etablissement_id` int unsigned NOT NULL,
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_dark_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366f1',
  `secondary_color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0ea5e9',
  `app_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EduNova',
  `welcome_message` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`etablissement_id`),
  CONSTRAINT `fk_etabbrand_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etablissement_domains` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('custom','subdomain') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'subdomain',
  `verification_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  `ssl_status` enum('pending','issued','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `ssl_expires_at` date DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etabdomain_domain` (`domain`),
  KEY `idx_etabdomain_etab` (`etablissement_id`),
  CONSTRAINT `fk_etabdomain_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `etablissements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_court` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_etablissement` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('ecole_primaire','college','lycee','universite','formation_pro','groupe_scolaire') COLLATE utf8mb4_unicode_ci NOT NULL,
  `pays` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DZ',
  `wilaya` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commune` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_web` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_id` int unsigned DEFAULT NULL,
  `plan_expires_at` date DEFAULT NULL,
  `storage_quota_mb` int unsigned NOT NULL DEFAULT '1024',
  `max_users` smallint unsigned NOT NULL DEFAULT '50',
  `max_eleves` smallint unsigned NOT NULL DEFAULT '500',
  `max_documents` smallint unsigned DEFAULT NULL,
  `max_attachments` smallint unsigned DEFAULT NULL,
  `statut` enum('trial','active','suspended','cancelled','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `trial_ends_at` date DEFAULT NULL,
  `suspended_at` datetime DEFAULT NULL,
  `suspension_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `periode_scolaire_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ periodes_scolaires V2',
  `type_evaluation_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ types_evaluations V2',
  `matiere_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ matieres V1',
  `classe_id` int unsigned NOT NULL COMMENT 'FK ÔåÆ classes V1',
  `enseignant_id` int unsigned DEFAULT NULL COMMENT 'FK ÔåÆ professeurs V1, optionnel',
  `libelle` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Intitul├® de l''├®valuation',
  `description` text COLLATE utf8mb4_unicode_ci,
  `date_evaluation` date DEFAULT NULL,
  `coefficient` decimal(4,2) NOT NULL DEFAULT '1.00' COMMENT 'Coefficient sp├®cifique (├®crase le d├®faut du type)',
  `note_max` decimal(5,2) NOT NULL DEFAULT '20.00' COMMENT 'Bar├¿me de cette ├®valuation',
  `statut` enum('brouillon','publiee','verrouillee','archivee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `familles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_postal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_urgence_nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_urgence_telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_urgence_lien` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_familles_nom` (`nom`),
  KEY `idx_familles_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `familles_eleves` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `famille_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `lien_parente` enum('pere','mere','tuteur','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'autre',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_avoirs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AVO-AAAA-NNNN — immuable',
  `facture_origine_id` int unsigned NOT NULL COMMENT 'Facture annulée à l''origine de cet avoir',
  `eleve_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('emis','utilise','rembourse','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'emis',
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_categories_depenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifiant court immuable (ex: SALAIRES, FOURNITURES)',
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#64748b',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'receipt',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_fcd_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catégories de dépenses Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_categories_frais` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifiant court immuable (ex: SCOL, TRANSP)',
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366f1',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'currency-dollar',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_fcf_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catégories de frais Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_comptes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('actif','passif','charge','produit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `classe` tinyint unsigned NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `niveau` tinyint unsigned NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `systeme` tinyint(1) NOT NULL DEFAULT '0',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fc_code` (`code`),
  KEY `idx_fc_classe` (`classe`),
  KEY `idx_fc_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_decaissements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format DEP-AAAA-NNNN',
  `categorie_id` int unsigned DEFAULT NULL,
  `fournisseur_id` int unsigned DEFAULT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `montant` decimal(12,2) NOT NULL,
  `devise` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF',
  `date_depense` date NOT NULL,
  `date_echeance` date DEFAULT NULL,
  `mode_paiement_id` int unsigned DEFAULT NULL COMMENT 'FK finance_modes_paiement — renseigné au paiement',
  `reference_externe` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('brouillon','soumis','valide','approuve','paye','rejete','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `caisse_session_id` int unsigned DEFAULT NULL COMMENT 'Session de caisse imputée si payé en espèces',
  `saisi_par` int unsigned DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `approuve_par` int unsigned DEFAULT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `paye_par` int unsigned DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL,
  `motif_rejet` text COLLATE utf8mb4_unicode_ci,
  `annule_par` int unsigned DEFAULT NULL,
  `date_annulation` datetime DEFAULT NULL,
  `motif_annulation` text COLLATE utf8mb4_unicode_ci,
  `origine` enum('operationnelle','migration_v1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operationnelle',
  `migration_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dépenses/décaissements Finance V2 — workflow brouillon→soumis→valide→approuve→paye';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_echeances` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `echeancier_id` int unsigned NOT NULL,
  `numero_ordre` tinyint unsigned NOT NULL,
  `date_echeance` date NOT NULL,
  `montant_du` decimal(12,2) NOT NULL,
  `montant_paye` decimal(12,2) NOT NULL DEFAULT '0.00',
  `statut` enum('en_attente','partiel','paye','en_retard','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `penalite` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fec_date` (`date_echeance`,`statut`),
  KEY `idx_fec_echeancier` (`echeancier_id`),
  CONSTRAINT `fk_fec_echeancier` FOREIGN KEY (`echeancier_id`) REFERENCES `finance_echeanciers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Échéances individuelles des plans de paiement';
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_ecritures` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_ecriture` date NOT NULL,
  `journal_id` int unsigned NOT NULL,
  `exercice_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `libelle` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('brouillon','valide','extourne','cloture') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'valide',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_exercices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `statut` enum('ouvert','cloture','reouvert') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `solde_report` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` text COLLATE utf8mb4_unicode_ci,
  `date_cloture` date DEFAULT NULL,
  `cloture_par` int unsigned DEFAULT NULL,
  `ouvert_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fe_statut` (`statut`),
  KEY `idx_fe_date_debut` (`date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_factures` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FCT-AAAA-NNNN — immuable',
  `eleve_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_emission` date NOT NULL DEFAULT (curdate()),
  `date_echeance` date DEFAULT NULL,
  `montant_ht` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ lignes avant remises',
  `montant_remise` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ remises accordées',
  `montant_penalite` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Σ pénalités appliquées',
  `montant_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'montant_ht - remise + penalite',
  `montant_paye` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Mis à jour par Phase 3.4 (encaissements)',
  `statut` enum('brouillon','emise','partiellement_payee','payee','en_retard','annulee','archive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `origine` enum('operationnelle','migration_v1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operationnelle',
  `migration_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migration_meta` json DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `emise_par` int unsigned DEFAULT NULL,
  `annulee_par` int unsigned DEFAULT NULL,
  `date_annulation` date DEFAULT NULL,
  `motif_annulation` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Factures Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_fournisseurs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `iban` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_ff_actif` (`actif`),
  KEY `fk_ff_created_by` (`created_by`),
  CONSTRAINT `fk_ff_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fournisseurs Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_frais_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Code immuable après création (ex: INSCRIPTION_2026)',
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `categorie_id` int unsigned DEFAULT NULL,
  `montant_defaut` decimal(12,2) NOT NULL DEFAULT '0.00',
  `devise` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF' COMMENT 'ISO 4217 : XOF (FCFA), EUR, USD…',
  `periodicite` enum('unique','mensuel','trimestriel','semestriel','annuel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annuel',
  `est_obligatoire` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = frais obligatoire, 0 = optionnel',
  `niveaux_cibles` json DEFAULT NULL COMMENT 'Tableau JSON de niveaux scolaires, NULL = tous niveaux',
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Format AAAA-AAAA, NULL = applicable toutes années',
  `date_limite` date DEFAULT NULL COMMENT 'Date limite de paiement par défaut',
  `peut_avoir_remise` tinyint(1) NOT NULL DEFAULT '1',
  `statut` enum('actif','inactif','archive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_fft_categorie` (`categorie_id`),
  KEY `idx_fft_annee` (`annee_scolaire`),
  KEY `idx_fft_statut` (`statut`),
  KEY `idx_fft_obligatoire` (`est_obligatoire`),
  CONSTRAINT `fk_fft_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `finance_categories_frais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Types de frais Finance V2 — coexiste avec frais_types V1';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entite_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'frais_type | categorie | tarif',
  `entite_id` int unsigned NOT NULL,
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create | update | activate | deactivate | archive | delete',
  `avant` json DEFAULT NULL,
  `apres` json DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fhist_entite` (`entite_type`,`entite_id`),
  KEY `idx_fhist_user` (`user_id`),
  KEY `idx_fhist_action` (`action`),
  CONSTRAINT `fk_fhist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique audit Finance — référentiel frais';
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `statut` enum('ouvert','clos','rapproche') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fjc_date_session` (`date_journee`,`session_id`),
  KEY `fk_fjc_session` (`session_id`),
  KEY `idx_fjc_date` (`date_journee`),
  KEY `idx_fjc_statut` (`statut`),
  CONSTRAINT `fk_fjc_session` FOREIGN KEY (`session_id`) REFERENCES `finance_sessions_caisse` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_journaux_comptables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('vente','achat','caisse','banque','operations_diverses') COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fjc_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_justificatifs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `decaissement_id` int unsigned NOT NULL,
  `nom_fichier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relatif à ROOT_PATH via UploadService',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taille` int unsigned NOT NULL,
  `uploade_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fj_decaissement` (`decaissement_id`),
  KEY `fk_fj_uploade_par` (`uploade_par`),
  CONSTRAINT `fk_fj_decaissement` FOREIGN KEY (`decaissement_id`) REFERENCES `finance_decaissements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fj_uploade_par` FOREIGN KEY (`uploade_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Justificatifs des décaissements Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_lignes_ecriture` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ecriture_id` int unsigned NOT NULL,
  `compte_id` int unsigned NOT NULL,
  `libelle` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fle_ecriture` (`ecriture_id`),
  KEY `idx_fle_compte` (`compte_id`),
  CONSTRAINT `fk_fle_compte` FOREIGN KEY (`compte_id`) REFERENCES `finance_comptes` (`id`),
  CONSTRAINT `fk_fle_ecriture` FOREIGN KEY (`ecriture_id`) REFERENCES `finance_ecritures` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_lignes_facture` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facture_id` int unsigned NOT NULL,
  `frais_type_id` int unsigned DEFAULT NULL COMMENT 'NULL = ligne libre sans référence au référentiel',
  `libelle` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lignes de facture';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_modes_paiement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` enum('caisse','banque','avoir') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'caisse',
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'credit-card',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` tinyint NOT NULL DEFAULT '99',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modes de paiement disponibles';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_mouvements_caisse` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int unsigned NOT NULL,
  `type` enum('recette','decaissement','correction','ouverture','fermeture','annulation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sens` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paiement_id` int unsigned DEFAULT NULL COMMENT 'Lié à finance_paiements si source=paiement',
  `source` enum('manuel','paiement','decaissement','systeme') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manuel',
  `statut` enum('actif','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `annule_par` int unsigned DEFAULT NULL,
  `motif_annulation` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_paiements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PAI-AAAA-NNNN — immuable',
  `facture_id` int unsigned NOT NULL,
  `mode_paiement_id` int unsigned DEFAULT NULL,
  `montant` decimal(12,2) NOT NULL COMMENT 'Montant brut encaissé',
  `montant_applique` decimal(12,2) NOT NULL COMMENT 'Montant imputé sur la facture (≤ montant)',
  `reference_externe` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N° chèque, réf virement, réf mobile…',
  `date_paiement` date NOT NULL,
  `statut` enum('initie','valide','complete','annule','rembourse') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initie',
  `origine` enum('operationnelle','migration_v1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operationnelle',
  `migration_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migration_meta` json DEFAULT NULL,
  `echeance_id` int unsigned DEFAULT NULL COMMENT 'Échéance ciblée (nullable = facture entière)',
  `avoir_id` int unsigned DEFAULT NULL COMMENT 'Avoir utilisé comme mode de paiement',
  `note` text COLLATE utf8mb4_unicode_ci,
  `encaisse_par` int unsigned DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `date_validation` date DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `date_annulation` date DEFAULT NULL,
  `motif_annulation` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paiements Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_penalites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facture_id` int unsigned NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_periodes_comptables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exercice_id` int unsigned NOT NULL,
  `numero` tinyint unsigned NOT NULL,
  `libelle` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `statut` enum('ouverte','cloturee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `cloture_par` int unsigned DEFAULT NULL,
  `cloture_le` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fpc_exercice_num` (`exercice_id`,`numero`),
  KEY `idx_fpc_exercice` (`exercice_id`),
  KEY `idx_fpc_dates` (`date_debut`,`date_fin`),
  CONSTRAINT `fk_fpc_exercice` FOREIGN KEY (`exercice_id`) REFERENCES `finance_exercices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_recus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'REC-AAAA-NNNN — immuable',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reçus de paiement Finance V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_regles_comptables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type_source` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `compte_debit_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `compte_credit_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `journal_code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frc_type` (`type_source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_regles_exoneration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type_remise` enum('pourcentage','montant_fixe','exoneration') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pourcentage',
  `valeur` decimal(10,2) NOT NULL DEFAULT '0.00',
  `niveaux` json DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Règles exonération — stub Phase 3.4, enrichi en Phase 3.8';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_remboursements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `paiement_id` int unsigned NOT NULL COMMENT 'Paiement faisant l''objet du remboursement',
  `trop_percu_id` int unsigned DEFAULT NULL COMMENT 'Trop-perçu à l''origine (si applicable)',
  `eleve_id` int unsigned NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `mode_remboursement` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ESP',
  `reference_remboursement` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('initie','valide','complete','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initie',
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_remises` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facture_id` int unsigned NOT NULL,
  `libelle` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_remise` enum('pourcentage','montant_fixe','exoneration') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'montant_fixe',
  `valeur` decimal(10,2) NOT NULL COMMENT '% ou montant selon type',
  `montant_calcule` decimal(12,2) NOT NULL COMMENT 'Montant effectivement soustrait',
  `regle_exo_id` int unsigned DEFAULT NULL,
  `justificatif` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_sequences` (
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FCT | AVO | REC | DEP',
  `annee` smallint NOT NULL COMMENT 'Année civile ex: 2026',
  `valeur` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`type`,`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Séquences Finance — incrémentées atomiquement';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_sessions_caisse` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CSE-YYYY-NNNN',
  `caissier_id` int unsigned NOT NULL,
  `statut` enum('ouverte','en_activite','fermee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
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
  `note_ouverture` text COLLATE utf8mb4_unicode_ci,
  `note_fermeture` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_tarifs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `frais_type_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL = toutes les classes pour ce frais',
  `classe_id` int unsigned DEFAULT NULL COMMENT 'NULL = tout le niveau (ou tous si niveau aussi NULL)',
  `montant` decimal(12,2) NOT NULL,
  `devise` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarif` (`frais_type_id`,`annee_scolaire`,`niveau`,`classe_id`),
  KEY `idx_tarif_frais` (`frais_type_id`),
  KEY `fk_tarif_classe` (`classe_id`),
  CONSTRAINT `fk_tarif_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tarif_frais_type` FOREIGN KEY (`frais_type_id`) REFERENCES `finance_frais_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifs par niveau/classe — surcharge le montant_defaut';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_trop_percus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `paiement_id` int unsigned NOT NULL,
  `facture_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `montant_excedent` decimal(12,2) NOT NULL,
  `statut` enum('en_attente','restitue','impute','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `note` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frais_eleves` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `frais_type_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `echeance` date DEFAULT NULL,
  `statut` enum('en_attente','partiel','paye') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frais_eleve` (`eleve_id`,`frais_type_id`,`annee_scolaire`),
  KEY `idx_fe_annee` (`annee_scolaire`),
  KEY `idx_fe_statut` (`statut`),
  KEY `fk_fe_type` (`frais_type_id`),
  CONSTRAINT `fk_fe_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fe_type` FOREIGN KEY (`frais_type_id`) REFERENCES `frais_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frais_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `montant_defaut` decimal(10,2) NOT NULL DEFAULT '0.00',
  `periodicite` enum('unique','mensuel','trimestriel','annuel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annuel',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL COMMENT 'R├®f├®rence vers eleves.id',
  `classe_id` int unsigned DEFAULT NULL COMMENT 'Classe cible (peut ├¬tre NULL si en_attente)',
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ann├®e scolaire au format YYYY-YYYY (ex: 2025-2026)',
  `statut` enum('en_attente','validee','rejetee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente' COMMENT 'Cycle : en_attente ÔåÆ validee/rejetee ; validee ÔåÆ annulee',
  `motif_rejet` text COLLATE utf8mb4_unicode_ci COMMENT 'Renseign├® lors du rejet ou de l''annulation',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Observations internes (non visible parent/├®l├¿ve)',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscriptions scolaires V2 ÔÇö une par ├®l├¿ve par ann├®e scolaire (statut actif)';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL,
  `type` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `status` enum('pending','processing','done','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `max_attempts` tinyint unsigned NOT NULL DEFAULT '3',
  `run_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_etab` (`etablissement_id`),
  KEY `idx_status_runat` (`status`,`run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matieres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coefficient` decimal(3,1) NOT NULL DEFAULT '1.0',
  `categorie` enum('litteraire','scientifique','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'autre',
  `est_conduite` tinyint unsigned DEFAULT NULL COMMENT '1 = matière de Conduite (note de comportement), NULL = matière ordinaire',
  `volume_horaire` tinyint unsigned NOT NULL DEFAULT '2' COMMENT 'Heures par semaine',
  `responsable_id` int unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matiere_conduite` (`etablissement_id`,`est_conduite`),
  KEY `fk_mat_resp` (`responsable_id`),
  KEY `idx_etab` (`etablissement_id`),
  CONSTRAINT `fk_mat_resp` FOREIGN KEY (`responsable_id`) REFERENCES `professeurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_matieres_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `trg_matieres_conduite_bi` BEFORE INSERT ON `matieres` FOR EACH ROW BEGIN
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
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `trg_matieres_conduite_bu` BEFORE UPDATE ON `matieres` FOR EACH ROW BEGIN
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
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `trg_matieres_conduite_bd` BEFORE DELETE ON `matieres` FOR EACH ROW BEGIN
  IF OLD.est_conduite = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conduite : la matiere de Conduite ne peut pas etre supprimee.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `duration_ms` int unsigned DEFAULT NULL,
  `status` enum('success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `error` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moyennes_generales` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `periode_id` int unsigned NOT NULL,
  `moyenne_generale` decimal(5,2) DEFAULT NULL,
  `rang` smallint unsigned DEFAULT NULL,
  `mention` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `controle_id` int unsigned NOT NULL,
  `note` decimal(5,2) DEFAULT NULL COMMENT 'NULL = non encore saisie',
  `absent` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = absent => compte comme 0',
  `appreciation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note` (`eleve_id`,`controle_id`),
  KEY `fk_note_controle` (`controle_id`),
  KEY `idx_etab` (`etablissement_id`,`eleve_id`),
  CONSTRAINT `fk_note_controle` FOREIGN KEY (`controle_id`) REFERENCES `controles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_note_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `note_id` int unsigned NOT NULL,
  `valeur_avant` decimal(5,2) DEFAULT NULL,
  `valeur_apres` decimal(5,2) DEFAULT NULL,
  `absent_avant` tinyint(1) NOT NULL DEFAULT '0',
  `absent_apres` tinyint(1) NOT NULL DEFAULT '0',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned DEFAULT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_note` (`note_id`),
  CONSTRAINT `fk_hist_note` FOREIGN KEY (`note_id`) REFERENCES `notes_v2` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes_v2` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `valeur` decimal(5,2) DEFAULT NULL COMMENT 'NULL = absent ou non attribu├®e',
  `est_absent` tinyint(1) NOT NULL DEFAULT '0',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('saisie','publiee','verrouillee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'saisie',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `trigger_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` enum('interne','email','sms') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `destinataire` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'email ou num├®ro de t├®l├®phone',
  `statut` enum('envoye','echoue','en_attente') COLLATE utf8mb4_unicode_ci DEFAULT 'envoye',
  `erreur` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_trigger` (`trigger_type`),
  KEY `idx_canal` (`canal`),
  KEY `idx_statut` (`statut`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_preferences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `trigger_type` enum('note','absence','paiement','annonce') COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal_interne` tinyint(1) DEFAULT '1',
  `canal_email` tinyint(1) DEFAULT '0',
  `canal_sms` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_trigger` (`user_id`,`trigger_type`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` enum('note','absence','paiement','annonce','info','retard','discipline','recompense') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `lien` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `lu` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_lu` (`user_id`,`lu`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `frais_eleve_id` int unsigned DEFAULT NULL COMMENT 'Frais spécifique concerné (optionnel)',
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` date NOT NULL,
  `mode_paiement` enum('especes','cheque','virement','carte') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especes',
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N° de chèque, bordereau virement…',
  `note` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_pr_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('trimestre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trimestre',
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format : 2025-2026',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_periode_annee` (`annee_scolaire`),
  KEY `idx_etab` (`etablissement_id`,`annee_scolaire`),
  CONSTRAINT `fk_periodes_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodes_scolaires` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_periode` enum('trimestre','semestre','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trimestre',
  `numero` tinyint unsigned NOT NULL DEFAULT '1',
  `nom` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('preparation','ouverte','cloturee','archivee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'preparation',
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
  UNIQUE KEY `uq_ps_annee_type_num` (`annee_scolaire`,`type_periode`,`numero`),
  KEY `idx_ps_annee` (`annee_scolaire`),
  KEY `idx_ps_statut` (`statut`),
  KEY `idx_ps_active` (`is_active`),
  KEY `idx_ps_ordre` (`annee_scolaire`,`ordre`,`numero`),
  KEY `idx_ps_dates` (`annee_scolaire`,`date_debut`,`date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Périodes scolaires V2 — système trimestriel Niger par défaut';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodes_scolaires_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` tinyint unsigned NOT NULL,
  `nom_defaut` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mois_debut` tinyint unsigned NOT NULL,
  `jour_debut` tinyint unsigned NOT NULL,
  `mois_fin` tinyint unsigned NOT NULL,
  `jour_fin` tinyint unsigned NOT NULL,
  `ordre` tinyint unsigned NOT NULL DEFAULT '0',
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_psc_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modèle par défaut configurable — préconfiguré Niger';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `scope` enum('global','own') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
  `is_system` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_analytics_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_backups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned DEFAULT NULL,
  `type` enum('global','tenant','pre_migration','manual','differential') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('pending','running','success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `storage_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `triggered_by` int unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_etab` (`etablissement_id`),
  KEY `idx_type` (`type`,`statut`),
  KEY `idx_expire` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_operators` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `niveau` enum('support','admin','super_admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'support',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_user` (`user_id`),
  CONSTRAINT `fk_platform_operator_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_users` smallint unsigned NOT NULL DEFAULT '50',
  `max_eleves` smallint unsigned NOT NULL DEFAULT '500',
  `storage_quota_mb` int unsigned NOT NULL DEFAULT '1024',
  `api_calls_per_day` int unsigned NOT NULL DEFAULT '10000',
  `modules_inclus` json NOT NULL,
  `prix_mensuel` decimal(10,2) NOT NULL DEFAULT '0.00',
  `prix_annuel` decimal(10,2) NOT NULL DEFAULT '0.00',
  `devise` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DZD',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_restores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` int unsigned NOT NULL,
  `etablissement_id` int unsigned DEFAULT NULL,
  `type` enum('global_verify','tenant_live','selective') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('pending','running','success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rows_restored` int unsigned DEFAULT NULL,
  `target_info` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `triggered_by` int unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_backup` (`backup_id`),
  KEY `idx_etab` (`etablissement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professeurs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialite` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_recrutement` date DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_prof_user` (`user_id`),
  KEY `idx_etab` (`etablissement_id`,`actif`),
  CONSTRAINT `fk_prof_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_professeurs_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `p256dh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `auth` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_endpoint` (`endpoint`(200))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rbac_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'admin, directeur, ...',
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_system` tinyint(1) NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reinscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `classe_source_id` int unsigned NOT NULL,
  `classe_destination_id` int unsigned DEFAULT NULL COMMENT 'NULL = sortant / fin de cycle',
  `annee_source` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `annee_destination` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sortant` tinyint(1) NOT NULL DEFAULT '0',
  `executee_par` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reinsc_eleve` (`eleve_id`),
  KEY `idx_reinsc_etab_annees` (`etablissement_id`,`annee_source`,`annee_destination`),
  KEY `fk_reinsc_classe_src` (`classe_source_id`),
  KEY `fk_reinsc_classe_dst` (`classe_destination_id`),
  KEY `fk_reinsc_user` (`executee_par`),
  CONSTRAINT `fk_reinsc_classe_dst` FOREIGN KEY (`classe_destination_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_reinsc_classe_src` FOREIGN KEY (`classe_source_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_reinsc_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reinsc_user` FOREIGN KEY (`executee_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_affectation_matieres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `affectation_id` int unsigned NOT NULL,
  `matiere_id` int unsigned DEFAULT NULL COMMENT 'Réf. table matieres V1',
  `classe_id` int unsigned DEFAULT NULL COMMENT 'Réf. table classes V1',
  `niveau` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex : 6ème, 5ème, Terminale S',
  `heures_hebdo` decimal(4,1) unsigned DEFAULT NULL COMMENT 'Charge hebdomadaire en heures',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_affmat_affect` (`affectation_id`),
  CONSTRAINT `fk_affmat_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `rh_affectations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `type` enum('principale','secondaire','temporaire') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'principale',
  `statut` enum('active','terminee','suspendue') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL COMMENT 'NULL = durée indéterminée',
  `etablissement_id` int unsigned DEFAULT NULL COMMENT 'Réservé réseau V3 — NULL en V2',
  `notes` text COLLATE utf8mb4_unicode_ci,
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
  CONSTRAINT `fk_affect_contrat` FOREIGN KEY (`contrat_id`) REFERENCES `rh_contrats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_departement` FOREIGN KEY (`departement_id`) REFERENCES `rh_departements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_affect_poste` FOREIGN KEY (`poste_id`) REFERENCES `rh_postes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `rh_employes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affect_service` FOREIGN KEY (`service_id`) REFERENCES `rh_services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_campagnes_evaluation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `annee` year NOT NULL,
  `periode` enum('S1','S2','annuelle','trimestrielle','ad_hoc') COLLATE utf8mb4_unicode_ci DEFAULT 'annuelle',
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `date_limite_auto_eval` date DEFAULT NULL,
  `date_limite_eval` date DEFAULT NULL,
  `statut` enum('brouillon','active','cloturee','archivee') COLLATE utf8mb4_unicode_ci DEFAULT 'brouillon',
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campagne_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_certifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_competences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `categorie` enum('technique','comportementale','management','pedagogique','transversale') COLLATE utf8mb4_unicode_ci DEFAULT 'technique',
  `actif` tinyint(1) DEFAULT '1',
  `ordre` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competence_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `statut` enum('brouillon','soumis','approuve','rejete','annule','en_cours','termine') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `motif` text COLLATE utf8mb4_unicode_ci,
  `motif_rejet` text COLLATE utf8mb4_unicode_ci,
  `motif_annulation` text COLLATE utf8mb4_unicode_ci,
  `approuve_par` int unsigned DEFAULT NULL,
  `approuve_par_nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `annule_par_nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_annulation` datetime DEFAULT NULL,
  `date_retour_effectif` date DEFAULT NULL,
  `commentaire_retour` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_conges_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conge_id` int unsigned NOT NULL,
  `statut_avant` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut_apres` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `effectue_par` int unsigned NOT NULL,
  `effectue_par_nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conge` (`conge_id`),
  CONSTRAINT `fk_hist_conge` FOREIGN KEY (`conge_id`) REFERENCES `rh_conges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_conges_justificatifs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conge_id` int unsigned NOT NULL,
  `nom_fichier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_octets` int unsigned DEFAULT NULL,
  `uploaded_by` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conge` (`conge_id`),
  CONSTRAINT `fk_just_conge` FOREIGN KEY (`conge_id`) REFERENCES `rh_conges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contacts_urgence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `nom_complet` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lien` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ex: conjoint, père, mère, frère, ami',
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone2` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employe` (`employe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contrat_avenants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `contrat_id` int unsigned NOT NULL,
  `numero` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Numéro séquentiel par contrat',
  `type` enum('salaire','poste','duree','horaire','teletravail','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'autre',
  `objet` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contrat_sequences` (
  `annee` year NOT NULL,
  `seq` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_contrats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `poste_id` int unsigned DEFAULT NULL COMMENT 'FK rh_postes.id',
  `departement_id` int unsigned DEFAULT NULL COMMENT 'FK rh_departements.id',
  `numero_contrat` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format CNT-YYYY-NNNN',
  `type` enum('cdi','cdd','vacataire','stage','apprentissage','consultant','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cdd',
  `statut` enum('brouillon','actif','suspendu','expire','resilie') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL COMMENT 'NULL = durée indéterminée (CDI)',
  `date_signature` date DEFAULT NULL,
  `salaire_brut` decimal(10,2) unsigned DEFAULT NULL,
  `devise` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DZD',
  `motif_creation` text COLLATE utf8mb4_unicode_ci,
  `motif_fin` text COLLATE utf8mb4_unicode_ci COMMENT 'Renseigné à résiliation/expiration',
  `notes` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_criteres_evaluation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `categorie` enum('competence','comportement','resultat','objectif') COLLATE utf8mb4_unicode_ci DEFAULT 'competence',
  `type_employe` enum('tous','enseignant','administratif','comptable','direction') COLLATE utf8mb4_unicode_ci DEFAULT 'tous',
  `poids` decimal(5,2) DEFAULT '1.00',
  `note_min` tinyint DEFAULT '0',
  `note_max` tinyint DEFAULT '5',
  `actif` tinyint(1) DEFAULT '1',
  `ordre` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_critere_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_departements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `budget_centre` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Code centre de coût (Finance V2)',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_document_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'creer|mettre_a_jour|archiver|restaurer|expirer|refuser',
  `ancien_statut` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_by_nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rdoch_document` (`document_id`),
  KEY `idx_rdoch_action` (`action`),
  CONSTRAINT `fk_rdoch_document` FOREIGN KEY (`document_id`) REFERENCES `rh_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal des actions sur les documents RH';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_document_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `version` tinyint unsigned NOT NULL,
  `reference_externe` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Référence du fichier à cette version',
  `notes_version` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motif de la nouvelle version',
  `created_by` int unsigned NOT NULL,
  `created_by_nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_doc_version` (`document_id`,`version`),
  KEY `idx_rdocv_document` (`document_id`),
  CONSTRAINT `fk_rdocv_document` FOREIGN KEY (`document_id`) REFERENCES `rh_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des versions d''un document RH';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `type` enum('contrat_signe','avenant','diplome','certificat','attestation','piece_identite','certificat_medical','autorisation','sanction','recompense','evaluation_signee','certificat_formation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_externe` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Référence DocumentService V2/V3',
  `statut` enum('actif','expire','archive','en_attente','refuse') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `confidentialite` enum('public','confidentiel','secret') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confidentiel',
  `version_courante` tinyint unsigned NOT NULL DEFAULT '1',
  `date_emission` date NOT NULL,
  `date_expiration` date DEFAULT NULL,
  `alerte_jours` int unsigned DEFAULT '30' COMMENT 'Alerte N jours avant expiration',
  `emetteur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employe_certifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `certification_id` int unsigned NOT NULL,
  `date_obtention` date NOT NULL,
  `date_expiration` date DEFAULT NULL,
  `statut` enum('valide','expiree','a_renouveler') COLLATE utf8mb4_unicode_ci DEFAULT 'valide',
  `session_id` int unsigned DEFAULT NULL,
  `reference_certificat` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fichier_certificat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employe_competences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `competence_id` int unsigned NOT NULL,
  `niveau` enum('debutant','intermediaire','avance','expert') COLLATE utf8mb4_unicode_ci DEFAULT 'debutant',
  `date_acquisition` date DEFAULT NULL,
  `session_id` int unsigned DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_par_nom` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employe_fonctions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `fonction_id` int unsigned NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = fonction principale',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employe_fonction_debut` (`employe_id`,`fonction_id`,`date_debut`),
  KEY `idx_ef_employe` (`employe_id`),
  KEY `idx_ef_fonction` (`fonction_id`),
  CONSTRAINT `fk_ef_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ef_fonction` FOREIGN KEY (`fonction_id`) REFERENCES `rh_fonctions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_employes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL COMMENT 'FK users.id — compte système, optionnel',
  `professeur_id` int unsigned DEFAULT NULL COMMENT 'FK professeurs.id — uniquement enseignants V1',
  `matricule` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format YYYY-NNNNN',
  `type_personnel` enum('enseignant','administratif','support','direction','technique') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `lieu_naissance` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` enum('M','F','autre') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationalite` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Algérienne',
  `cni_numero` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cni_expiration` date DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_pro` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_perso` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departement_id` int unsigned DEFAULT NULL COMMENT 'FK rh_departements.id',
  `poste_id` int unsigned DEFAULT NULL COMMENT 'FK rh_postes.id',
  `date_entree` date DEFAULT NULL,
  `date_sortie` date DEFAULT NULL,
  `motif_sortie` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('actif','inactif','suspendu','conge','retraite','demissionnaire') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `diplome` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialite` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Notes internes RH (confidentielles)',
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
  KEY `idx_email_pro` (`email_pro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_enseignant_matieres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `enseignant_id` int unsigned NOT NULL,
  `matiere_id` int unsigned NOT NULL,
  `niveaux` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Niveaux séparés par virgule : primaire,moyen,secondaire',
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_enseignant_qualifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `enseignant_id` int unsigned NOT NULL,
  `type` enum('diplome','certification','formation','experience','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diplome',
  `intitule` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organisme` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_obtention` date DEFAULT NULL,
  `date_expiration` date DEFAULT NULL,
  `document_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Réservé pour le module Documents (Phase 6.9)',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_eq_enseignant` (`enseignant_id`),
  KEY `idx_eq_type` (`type`),
  KEY `idx_eq_expiration` (`date_expiration`),
  CONSTRAINT `fk_eq_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `rh_enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Qualifications et certifications des enseignants — Phase 6.3';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_enseignants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employe_id` int unsigned NOT NULL,
  `statut_pedagogique` enum('titulaire','vacataire','remplacant','stagiaire','contractuel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'titulaire',
  `specialite_principale` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charge_horaire_max` tinyint unsigned NOT NULL DEFAULT '18' COMMENT 'Heures hebdomadaires maximales',
  `charge_horaire_actuelle` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Heures hebdomadaires actuelles (mis à jour lors des affectations)',
  `date_debut_enseignement` date DEFAULT NULL,
  `notes_pedagogiques` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enseignant_employe` (`employe_id`),
  KEY `idx_ens_statut` (`statut_pedagogique`),
  KEY `idx_ens_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_rh_ens_employe` FOREIGN KEY (`employe_id`) REFERENCES `rh_employes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profils pédagogiques des enseignants — Phase 6.3';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_evaluation_criteres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `critere_id` int unsigned NOT NULL,
  `note_auto_eval` decimal(4,2) DEFAULT NULL,
  `note_evaluateur` decimal(4,2) DEFAULT NULL,
  `commentaire_auto_eval` text COLLATE utf8mb4_unicode_ci,
  `commentaire_evaluateur` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ec` (`evaluation_id`,`critere_id`),
  KEY `critere_id` (`critere_id`),
  CONSTRAINT `rh_evaluation_criteres_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `rh_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rh_evaluation_criteres_ibfk_2` FOREIGN KEY (`critere_id`) REFERENCES `rh_criteres_evaluation` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_evaluation_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `statut_avant` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut_apres` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `effectue_par` int unsigned NOT NULL,
  `effectue_par_nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evaluation_id` (`evaluation_id`),
  CONSTRAINT `rh_evaluation_historique_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `rh_evaluations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campagne_id` int unsigned NOT NULL,
  `employe_id` int unsigned NOT NULL,
  `affectation_id` int unsigned DEFAULT NULL,
  `evaluateur_id` int unsigned DEFAULT NULL,
  `evaluateur_nom` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('brouillon','en_auto_evaluation','en_evaluation','soumise','validee','publiee','archivee') COLLATE utf8mb4_unicode_ci DEFAULT 'brouillon',
  `score_auto_eval` decimal(5,2) DEFAULT NULL,
  `score_evaluateur` decimal(5,2) DEFAULT NULL,
  `score_final` decimal(5,2) DEFAULT NULL,
  `mention` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire_auto_eval` text COLLATE utf8mb4_unicode_ci,
  `commentaire_evaluateur` text COLLATE utf8mb4_unicode_ci,
  `commentaire_validation` text COLLATE utf8mb4_unicode_ci,
  `date_auto_eval` datetime DEFAULT NULL,
  `date_evaluation` datetime DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `date_publication` datetime DEFAULT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_par_nom` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publie_par` int unsigned DEFAULT NULL,
  `publie_par_nom` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_fonctions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `niveau` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1=opérationnel 2=encadrement 3=direction',
  `perimetre` enum('etablissement','departement','service','transversal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transversal',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_fonction_actif` (`actif`),
  KEY `idx_fonction_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_catalogue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('interne','externe','e_learning','certification','coaching','seminaire') COLLATE utf8mb4_unicode_ci DEFAULT 'interne',
  `duree_heures` decimal(6,1) NOT NULL DEFAULT '0.0',
  `niveau` enum('debutant','intermediaire','avance','expert') COLLATE utf8mb4_unicode_ci DEFAULT 'debutant',
  `modalite` enum('presentiel','distanciel','hybride') COLLATE utf8mb4_unicode_ci DEFAULT 'presentiel',
  `organisme_id` int unsigned DEFAULT NULL,
  `formateur_principal` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cout_unitaire` decimal(10,2) DEFAULT '0.00',
  `devise` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'XAF',
  `max_participants` tinyint DEFAULT '20',
  `prerequis` text COLLATE utf8mb4_unicode_ci,
  `objectifs` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_inscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int unsigned NOT NULL,
  `employe_id` int unsigned NOT NULL,
  `statut` enum('inscrit','confirme','present','absent','valide','annule') COLLATE utf8mb4_unicode_ci DEFAULT 'inscrit',
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_confirmation` datetime DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `note_evaluation` decimal(4,2) DEFAULT NULL,
  `commentaire_evaluation` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_organismes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('interne','externe','certifiant','universite') COLLATE utf8mb4_unicode_ci DEFAULT 'externe',
  `contact_nom` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_tel` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_web` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pays` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_organisme_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_presences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `inscription_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `present` tinyint(1) DEFAULT '1',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_presence` (`inscription_id`,`date`),
  CONSTRAINT `rh_formations_presences_ibfk_1` FOREIGN KEY (`inscription_id`) REFERENCES `rh_formations_inscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_formations_sessions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `formation_id` int unsigned NOT NULL,
  `code_session` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lieu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `heure_debut` time DEFAULT '08:00:00',
  `heure_fin` time DEFAULT '17:00:00',
  `statut` enum('planifiee','ouverte','en_cours','terminee','annulee') COLLATE utf8mb4_unicode_ci DEFAULT 'planifiee',
  `max_participants` tinyint DEFAULT '20',
  `nb_inscrits` tinyint DEFAULT '0',
  `formateur_nom` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cout_total` decimal(10,2) DEFAULT '0.00',
  `financeur` enum('etablissement','organisme','personnel','mixte') COLLATE utf8mb4_unicode_ci DEFAULT 'etablissement',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_historique_affectations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `affectation_id` int unsigned NOT NULL,
  `type_changement` enum('creation','modification','transfert','suspension','reactivation','cloture','archivage') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ancienne_valeur` json DEFAULT NULL,
  `nouvelle_valeur` json DEFAULT NULL,
  `motif` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifie_par` int unsigned NOT NULL,
  `modifie_par_nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_affhist_affect` (`affectation_id`),
  CONSTRAINT `fk_haffect_affectation` FOREIGN KEY (`affectation_id`) REFERENCES `rh_affectations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_historique_org` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type_entite` enum('departement','service','poste','fonction','affectation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entite_id` int unsigned NOT NULL,
  `action` enum('creation','modification','archivage','restauration','affectation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ancienne_valeur` json DEFAULT NULL,
  `nouvelle_valeur` json DEFAULT NULL,
  `modifie_par_id` int unsigned DEFAULT NULL COMMENT 'FK users.id',
  `modifie_par_nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dénormalisé pour audit pérenne',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_horg_entite` (`type_entite`,`entite_id`),
  KEY `idx_horg_action` (`action`),
  KEY `idx_horg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_plans_developpement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int unsigned NOT NULL,
  `employe_id` int unsigned NOT NULL,
  `objectif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `actions` text COLLATE utf8mb4_unicode_ci,
  `ressources` text COLLATE utf8mb4_unicode_ci,
  `echeance` date DEFAULT NULL,
  `statut` enum('en_cours','realise','abandonne') COLLATE utf8mb4_unicode_ci DEFAULT 'en_cours',
  `progression` tinyint DEFAULT '0',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_postes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `intitule` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departement_id` int unsigned DEFAULT NULL,
  `service_id` int unsigned DEFAULT NULL COMMENT 'FK rh_services.id — optionnel',
  `categorie` enum('enseignant','administratif','support','direction','technique') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'administratif',
  `niveau` tinyint NOT NULL DEFAULT '1' COMMENT '1=junior 2=confirmé 3=senior 4=chef',
  `nb_occupants_max` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Nb maximal de titulaires simultanés',
  `description` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `statut` enum('present','absent','retard','sortie_anticipee','mi_temps','mission','heure_sup') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present' COMMENT 'Statut de présence du jour',
  `mode_pointage` enum('manuel','badge','qr_code','biometrie','api_externe') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manuel' COMMENT 'Mode utilisé pour ce pointage',
  `retard_minutes` int unsigned NOT NULL DEFAULT '0' COMMENT 'Minutes de retard détectées par rapport à heure_reference_arrivee',
  `heures_supp_minutes` int unsigned NOT NULL DEFAULT '0' COMMENT 'Minutes en dépassement par rapport à duree_reference_minutes',
  `heure_reference_arrivee` time NOT NULL DEFAULT '08:00:00' COMMENT 'Heure théorique d''entrée pour le calcul des retards',
  `duree_reference_minutes` smallint unsigned NOT NULL DEFAULT '480' COMMENT 'Durée journée théorique en minutes (défaut 8h = 480)',
  `motif` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motif renseigné au pointage (mission, formation…)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Notes libres',
  `source_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Identifiant badge, numéro biométrique ou ID API externe',
  `source_data` json DEFAULT NULL COMMENT 'Données brutes provenant de la source externe — Préparation V3',
  `justification` text COLLATE utf8mb4_unicode_ci COMMENT 'Texte de justification (absence / retard)',
  `justifie_par` int unsigned DEFAULT NULL COMMENT 'ID utilisateur ayant saisi la justification',
  `date_justification` datetime DEFAULT NULL,
  `statut_validation` enum('en_attente','valide','rejete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `valide_par` int unsigned DEFAULT NULL COMMENT 'ID utilisateur ayant validé ou rejeté',
  `date_validation` datetime DEFAULT NULL,
  `motif_rejet` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_presences_regularisations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `presence_id` int unsigned NOT NULL COMMENT 'Présence concernée par la régularisation',
  `type_regularisation` enum('correction_heure','correction_statut','justification','annulation') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nature de la correction apportée',
  `valeur_ancienne` json DEFAULT NULL COMMENT 'Snapshot des champs AVANT la régularisation',
  `valeur_nouvelle` json DEFAULT NULL COMMENT 'Snapshot des champs APRÈS la régularisation',
  `motif` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Motif de la régularisation (obligatoire)',
  `regularise_par` int unsigned NOT NULL COMMENT 'ID utilisateur ayant effectué la régularisation',
  `regularise_par_nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom dénormalisé — conservé même si l''utilisateur est supprimé',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reg_presence` (`presence_id`),
  CONSTRAINT `fk_reg_presence` FOREIGN KEY (`presence_id`) REFERENCES `rh_presences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Régularisations et corrections de présence — Phase 6.7 RH V2';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `departement_id` int unsigned NOT NULL,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rh_types_conges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role` enum('admin','directeur','secretaire','comptable','enseignant','parent','eleve') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`role`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `types_evaluations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cl├® immuable, correspond au type V1 pour les types syst├¿me',
  `nom` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `coefficient_defaut` decimal(4,2) NOT NULL DEFAULT '1.00' COMMENT 'Coefficient sugg├®r├® lors de la cr├®ation d''une ├®valuation',
  `note_max_defaut` decimal(5,2) NOT NULL DEFAULT '20.00' COMMENT 'Bar├¿me sugg├®r├® (note maximale)',
  `est_eliminatoire` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = note en dessous du seuil entra├«ne ├®limination automatique',
  `seuil_eliminatoire` decimal(5,2) DEFAULT NULL COMMENT 'Note sur note_max_defaut d├®clenchant l''├®limination',
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Couleur hex #RRGGBB pour l''UI',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom ic├┤ne Lucide',
  `ordre` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Ordre d''affichage dans les s├®lecteurs',
  `actif` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = d├®sactiv├® (n''appara├«t plus dans les listes)',
  `est_archive` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = archiv├® d├®finitivement, non r├®activable',
  `est_systeme` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = type V1 prot├®g├®, requiert r├┤le admin pour modifications majeures',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_te_code` (`code`),
  KEY `idx_te_actif` (`actif`),
  KEY `idx_te_archive` (`est_archive`),
  KEY `idx_te_ordre` (`ordre`),
  KEY `idx_te_systeme` (`est_systeme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `etablissement_id` int unsigned NOT NULL,
  `dernier_etablissement_id` int unsigned DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','directeur','secretaire','comptable','enseignant','parent','eleve') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'secretaire',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `derniere_connexion` datetime DEFAULT NULL,
  `reset_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_etab` (`etablissement_id`,`role`),
  KEY `fk_users_dernier_etab` (`dernier_etablissement_id`),
  CONSTRAINT `fk_users_dernier_etab` FOREIGN KEY (`dernier_etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_absences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_absence` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `duree_heures` decimal(4,2) DEFAULT NULL COMMENT 'Calculé ou saisi manuellement',
  `type` enum('absence','retard','dispense') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'absence',
  `statut` enum('non_justifiee','en_attente','justifiee','refusee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non_justifiee',
  `saisie_par` int unsigned NOT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_abs_eleve_date` (`eleve_id`,`date_absence`),
  KEY `idx_abs_classe_date` (`classe_id`,`date_absence`),
  KEY `idx_abs_statut` (`statut`,`date_absence`),
  KEY `idx_abs_annee` (`annee_scolaire`,`classe_id`),
  KEY `idx_abs_soft_delete` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#8B5CF6',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_activite_cat_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `activite_id` int unsigned NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `data_json` json DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vs_acthist_activite` (`activite_id`),
  KEY `idx_vs_acthist_action` (`action`),
  CONSTRAINT `fk_vs_acthist_activite` FOREIGN KEY (`activite_id`) REFERENCES `vs_activites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activite_inscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `activite_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `statut` enum('inscrit','liste_attente','annule','present','absent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inscrit',
  `date_inscription` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `inscrit_par` int unsigned DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `annule_le` datetime DEFAULT NULL,
  `motif_annulation` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vs_inscr_eleve_activite` (`activite_id`,`eleve_id`,`deleted_at`),
  KEY `idx_vs_inscr_statut` (`statut`),
  KEY `idx_vs_inscr_eleve` (`eleve_id`),
  KEY `idx_vs_inscr_deleted` (`deleted_at`),
  CONSTRAINT `fk_vs_inscr_activite` FOREIGN KEY (`activite_id`) REFERENCES `vs_activites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_activites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categorie_id` int unsigned NOT NULL,
  `titre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `lieu` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_activite` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `capacite_max` smallint unsigned NOT NULL DEFAULT '30',
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('brouillon','publie','en_cours','termine','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `organisateur_id` int unsigned DEFAULT NULL COMMENT 'user_id organisateur',
  `cree_par` int unsigned NOT NULL,
  `publie_par` int unsigned DEFAULT NULL,
  `publie_le` datetime DEFAULT NULL,
  `termine_le` datetime DEFAULT NULL,
  `annule_par` int unsigned DEFAULT NULL,
  `annule_le` datetime DEFAULT NULL,
  `motif_annulation` text COLLATE utf8mb4_unicode_ci,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_appels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `classe_id` int unsigned NOT NULL,
  `matiere_id` int unsigned DEFAULT NULL,
  `enseignant_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_appel` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `type_appel` enum('journalier','seance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'journalier',
  `statut` enum('brouillon','valide') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appel_classe_date_heure` (`classe_id`,`date_appel`,`heure_debut`),
  KEY `idx_appel_classe_date` (`classe_id`,`date_appel`),
  KEY `idx_appel_enseignant` (`enseignant_id`,`date_appel`),
  KEY `idx_appel_statut` (`statut`,`date_appel`),
  KEY `idx_appel_annee` (`annee_scolaire`,`classe_id`),
  KEY `idx_appel_soft_delete` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_appels_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sanction_id` int unsigned NOT NULL,
  `dossier_id` int unsigned NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `piece_jointe` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('depose','examine','accepte','rejete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'depose',
  `depose_par` int unsigned NOT NULL,
  `depose_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `examine_par` int unsigned DEFAULT NULL,
  `examine_le` datetime DEFAULT NULL,
  `decision` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appel_sanction` (`sanction_id`),
  KEY `idx_appel_dossier` (`dossier_id`,`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_discipline_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `gravite_defaut` enum('mineur','moyen','grave','tres_grave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mineur',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categorie_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_dossiers_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('ouvert','en_cours','clos','appel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `nb_incidents` smallint unsigned NOT NULL DEFAULT '0',
  `note_interne` text COLLATE utf8mb4_unicode_ci,
  `cree_par` int unsigned NOT NULL,
  `clos_par` int unsigned DEFAULT NULL,
  `clos_le` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dossier_eleve_annee` (`eleve_id`,`annee_scolaire`),
  KEY `idx_dossier_classe_annee` (`classe_id`,`annee_scolaire`),
  KEY `idx_dossier_statut` (`statut`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_cours` enum('cours','td','tp','sport','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cours',
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_creneau_edt` (`emploi_du_temps_id`),
  KEY `idx_creneau_enseignant` (`enseignant_id`,`jour`,`plage_id`,`annee_scolaire`),
  KEY `idx_creneau_salle` (`salle_id`,`jour`,`plage_id`,`annee_scolaire`),
  KEY `idx_creneau_classe` (`classe_id`,`jour`,`plage_id`,`annee_scolaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_plages_horaires` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `ordre` tinyint unsigned NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `statut` enum('planifie','confirme','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planifie',
  `motif_absence` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_salles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacite` smallint unsigned DEFAULT '30',
  `type` enum('cours','labo','sport','informatique','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cours',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_edt_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `emploi_du_temps_id` int unsigned NOT NULL,
  `version` smallint unsigned NOT NULL,
  `snapshot` json NOT NULL,
  `motif` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_version_edt` (`emploi_du_temps_id`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_emplois_du_temps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `periode_id` int unsigned DEFAULT NULL,
  `semaine_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `statut` enum('brouillon','publie','archive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `publie_par` int unsigned DEFAULT NULL,
  `publie_le` timestamp NULL DEFAULT NULL,
  `cree_par` int unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_edt_classe` (`classe_id`,`annee_scolaire`),
  KEY `idx_edt_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_incidents_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dossier_id` int unsigned NOT NULL,
  `categorie_id` int unsigned NOT NULL,
  `gravite` enum('mineur','moyen','grave','tres_grave') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_incident` date NOT NULL,
  `heure_incident` time DEFAULT NULL,
  `lieu` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matiere_id` int unsigned DEFAULT NULL COMMENT 'Matière concernée, si applicable',
  `signale_par` int unsigned NOT NULL,
  `statut` enum('ouvert','traite','classe') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert',
  `piece_jointe` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incident_dossier` (`dossier_id`,`statut`),
  KEY `idx_incident_date` (`date_incident`),
  KEY `idx_incident_gravite` (`gravite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_justifications_absences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `absence_id` int unsigned NOT NULL,
  `motif_id` int unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `fichier_justificatif` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chemin relatif via UploadService',
  `statut` enum('en_attente','validee','refusee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `soumis_par` int unsigned NOT NULL,
  `soumis_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `motif_refus` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_justif_absence` (`absence_id`),
  KEY `idx_justif_statut` (`statut`,`soumis_le`),
  KEY `idx_justif_valide_par` (`valide_par`),
  KEY `fk_justif_motif` (`motif_id`),
  CONSTRAINT `fk_justif_absence` FOREIGN KEY (`absence_id`) REFERENCES `vs_absences` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_justif_motif` FOREIGN KEY (`motif_id`) REFERENCES `vs_motifs_absence` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_justifications_retards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `retard_id` int unsigned NOT NULL,
  `motif_description` text COLLATE utf8mb4_unicode_ci COMMENT 'Motif textuel libre (ou concat avec motif_code)',
  `fichier_justificatif` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('en_attente','validee','refusee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `soumis_par` int unsigned NOT NULL,
  `soumis_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `motif_refus` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_justif_retard` (`retard_id`),
  KEY `idx_justif_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_motifs_absence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` enum('maladie','familial','transport','scolaire','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `necessite_justificatif` tinyint(1) NOT NULL DEFAULT '1',
  `compte_comme_absence` tinyint(1) NOT NULL DEFAULT '1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_presences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `appel_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `statut` enum('present','absent','retard','dispense','sortie_anticipee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `heure_arrivee` time DEFAULT NULL,
  `retard_minutes` smallint unsigned DEFAULT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci,
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
  CONSTRAINT `fk_presence_absence` FOREIGN KEY (`absence_id`) REFERENCES `vs_absences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_presence_appel` FOREIGN KEY (`appel_id`) REFERENCES `vs_appels` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_presences_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `presence_id` int unsigned NOT NULL,
  `appel_id` int unsigned NOT NULL,
  `eleve_id` int unsigned NOT NULL,
  `ancien_statut` enum('present','absent','retard','dispense','sortie_anticipee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nouveau_statut` enum('present','absent','retard','dispense','sortie_anticipee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_presence` (`presence_id`),
  KEY `idx_hist_appel` (`appel_id`),
  KEY `idx_hist_eleve` (`eleve_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_recompense_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#6366f1',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_recompenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie_id` int unsigned NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` enum('classe','etablissement','academique') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'classe',
  `statut` enum('attribuee','validee','revoquee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attribuee',
  `date_attribution` date NOT NULL,
  `attribue_par` int unsigned NOT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` timestamp NULL DEFAULT NULL,
  `revoque_par` int unsigned DEFAULT NULL,
  `revoque_le` timestamp NULL DEFAULT NULL,
  `motif_revocation` text COLLATE utf8mb4_unicode_ci,
  `piece_jointe` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`,`annee_scolaire`),
  KEY `idx_classe` (`classe_id`,`annee_scolaire`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date` (`date_attribution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_recompenses_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `recompense_id` int unsigned NOT NULL,
  `ancien_statut` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recompense` (`recompense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_retards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `eleve_id` int unsigned NOT NULL,
  `classe_id` int unsigned NOT NULL,
  `annee_scolaire` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_retard` date NOT NULL,
  `heure_prevue` time DEFAULT NULL COMMENT 'Heure de début attendue (peut être NULL si appel journalier sans heure)',
  `heure_arrivee` time NOT NULL COMMENT 'Heure réelle d''arrivée',
  `duree_minutes` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Calculé : heure_arrivee - heure_prevue en minutes',
  `statut` enum('non_justifie','en_attente','justifie','refuse') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non_justifie',
  `appel_id` int unsigned DEFAULT NULL COMMENT 'FK vs_appels — NULL si saisie manuelle',
  `presence_id` int unsigned DEFAULT NULL COMMENT 'FK vs_presences — NULL si saisie manuelle',
  `saisie_par` int unsigned NOT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_retard_eleve_date` (`eleve_id`,`date_retard`),
  KEY `idx_retard_classe_date` (`classe_id`,`date_retard`),
  KEY `idx_retard_statut` (`statut`,`deleted_at`),
  KEY `idx_retard_annee` (`annee_scolaire`,`classe_id`),
  KEY `idx_retard_presence` (`presence_id`),
  KEY `idx_retard_soft_delete` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_sanctions_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dossier_id` int unsigned NOT NULL,
  `incident_id` int unsigned DEFAULT NULL COMMENT 'NULL = sanction portant sur le dossier global',
  `type_sanction` enum('avertissement','blame','exclusion_cours','exclusion_temp','exclusion_def','travaux','conseil','mesure_educative') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_sanction` date NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `duree_jours` smallint unsigned DEFAULT NULL,
  `statut` enum('prononcee','effective','executee','levee','appelee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prononcee',
  `prononce_par` int unsigned NOT NULL,
  `valide_par` int unsigned DEFAULT NULL,
  `valide_le` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sanction_dossier` (`dossier_id`,`statut`),
  KEY `idx_sanction_date` (`date_sanction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vs_sanctions_historique` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sanction_id` int unsigned NOT NULL,
  `ancien_statut` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nouveau_statut` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci,
  `modifie_par` int unsigned NOT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sh_sanction` (`sanction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


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

LOCK TABLES `etab_roles` WRITE;
/*!40000 ALTER TABLE `etab_roles` DISABLE KEYS */;
INSERT INTO `etab_roles` (`id`, `etablissement_id`, `code`, `nom`, `description`, `is_system`, `created_at`) VALUES (1,NULL,'admin','Administrateur','Accès total à la plateforme',1,'2026-07-08 05:14:19'),(2,NULL,'directeur','Directeur','Direction de l\'établissement',1,'2026-07-08 05:14:19'),(3,NULL,'secretaire','Secrétaire','Administration scolaire',1,'2026-07-08 05:14:19'),(4,NULL,'comptable','Comptable','Gestion financière',1,'2026-07-08 05:14:20'),(5,NULL,'enseignant','Enseignant','Corps pédagogique',1,'2026-07-08 05:14:20'),(6,NULL,'parent','Parent','Représentant légal de l\'élève',1,'2026-07-08 05:14:20'),(7,NULL,'eleve','Élève','Apprenant',1,'2026-07-08 05:14:20');
/*!40000 ALTER TABLE `etab_roles` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `etab_permissions` WRITE;
/*!40000 ALTER TABLE `etab_permissions` DISABLE KEYS */;
INSERT INTO `etab_permissions` (`id`, `code`, `libelle`, `module`, `action`) VALUES (1,'eleves.view','Eleves view','eleves','view'),(2,'eleves.create','Eleves create','eleves','create'),(3,'eleves.edit','Eleves edit','eleves','edit'),(4,'eleves.delete','Eleves delete','eleves','delete'),(5,'enseignants.view','Enseignants view','enseignants','view'),(6,'enseignants.create','Enseignants create','enseignants','create'),(7,'enseignants.edit','Enseignants edit','enseignants','edit'),(8,'enseignants.delete','Enseignants delete','enseignants','delete'),(9,'classes.view','Classes view','classes','view'),(10,'classes.create','Classes create','classes','create'),(11,'classes.edit','Classes edit','classes','edit'),(12,'classes.delete','Classes delete','classes','delete'),(13,'matieres.view','Matieres view','matieres','view'),(14,'matieres.create','Matieres create','matieres','create'),(15,'matieres.edit','Matieres edit','matieres','edit'),(16,'matieres.delete','Matieres delete','matieres','delete'),(17,'notes.view','Notes view','notes','view'),(18,'notes.create','Notes create','notes','create'),(19,'notes.edit','Notes edit','notes','edit'),(20,'notes.delete','Notes delete','notes','delete'),(21,'notes.view_own','Notes view own','notes','view_own'),(22,'absences.view','Absences view','absences','view'),(23,'absences.create','Absences create','absences','create'),(24,'absences.edit','Absences edit','absences','edit'),(25,'absences.view_own','Absences view own','absences','view_own'),(26,'bulletins.view','Bulletins view','bulletins','view'),(27,'comptabilite.view','Comptabilite view','comptabilite','view'),(28,'comptabilite.create','Comptabilite create','comptabilite','create'),(29,'comptabilite.edit','Comptabilite edit','comptabilite','edit'),(30,'emploi_du_temps.view','Emploi du temps view','emploi_du_temps','view'),(31,'emploi_du_temps.create','Emploi du temps create','emploi_du_temps','create'),(32,'emploi_du_temps.edit','Emploi du temps edit','emploi_du_temps','edit'),(33,'annonces.view','Annonces view','annonces','view'),(34,'annonces.create','Annonces create','annonces','create'),(35,'annonces.edit','Annonces edit','annonces','edit'),(36,'annonces.delete','Annonces delete','annonces','delete'),(37,'notifications.manage','Notifications manage','notifications','manage'),(38,'users.view','Users view','users','view'),(39,'users.create','Users create','users','create'),(40,'users.edit','Users edit','users','edit'),(41,'users.delete','Users delete','users','delete'),(42,'rapports.view','Rapports view','rapports','view'),(43,'finance.dashboard.view','Finance dashboard view','finance','view'),(44,'finance.frais.view','Finance frais view','finance','view'),(45,'finance.frais.manage','Finance frais manage','finance','manage'),(46,'finance.frais.admin','Finance frais admin','finance','admin'),(47,'finance.factures.view','Finance factures view','finance','view'),(48,'finance.factures.create','Finance factures create','finance','create'),(49,'finance.factures.masse','Finance factures masse','finance','masse'),(50,'finance.factures.emettre','Finance factures emettre','finance','emettre'),(51,'finance.factures.annuler','Finance factures annuler','finance','annuler'),(52,'finance.factures.remise','Finance factures remise','finance','remise'),(53,'finance.paiements.view','Finance paiements view','finance','view'),(54,'finance.paiements.create','Finance paiements create','finance','create'),(55,'finance.paiements.annuler','Finance paiements annuler','finance','annuler'),(56,'finance.paiements.trop_percu','Finance paiements trop percu','finance','trop_percu'),(57,'finance.decaissements.view','Finance decaissements view','finance','view'),(58,'finance.decaissements.create','Finance decaissements create','finance','create'),(59,'finance.decaissements.valider','Finance decaissements valider','finance','valider'),(60,'finance.decaissements.approuver','Finance decaissements approuver','finance','approuver'),(61,'finance.decaissements.rejeter','Finance decaissements rejeter','finance','rejeter'),(62,'finance.caisse.view','Finance caisse view','finance','view'),(63,'finance.caisse.ouvrir','Finance caisse ouvrir','finance','ouvrir'),(64,'finance.caisse.fermer','Finance caisse fermer','finance','fermer'),(65,'finance.caisse.rapprocher','Finance caisse rapprocher','finance','rapprocher'),(66,'finance.comptabilite.view','Finance comptabilite view','finance','view'),(67,'finance.comptabilite.saisir','Finance comptabilite saisir','finance','saisir'),(68,'finance.comptabilite.exercice','Finance comptabilite exercice','finance','exercice'),(69,'finance.rapports.view','Finance rapports view','finance','view'),(70,'finance.rapports.export','Finance rapports export','finance','export'),(71,'eleves.view.own','Eleves view own','eleves','own'),(72,'inscriptions.view','Inscriptions view','inscriptions','view'),(73,'inscriptions.create','Inscriptions create','inscriptions','create'),(74,'inscriptions.update','Inscriptions update','inscriptions','update'),(75,'familles.view','Familles view','familles','view'),(76,'familles.manage','Familles manage','familles','manage'),(77,'academique.periodes.view','Academique periodes view','academique','view'),(78,'academique.periodes.manage','Academique periodes manage','academique','manage'),(79,'academique.evaluations.view','Academique evaluations view','academique','view'),(80,'academique.evaluations.manage','Academique evaluations manage','academique','manage'),(81,'academique.notes.view','Academique notes view','academique','view'),(82,'academique.notes.manage','Academique notes manage','academique','manage'),(83,'academique.moyennes.view','Academique moyennes view','academique','view'),(84,'academique.moyennes.recalcul','Academique moyennes recalcul','academique','recalcul'),(85,'academique.classement.view','Academique classement view','academique','view'),(86,'academique.classement.generer','Academique classement generer','academique','generer'),(87,'academique.bulletin.view','Academique bulletin view','academique','view'),(88,'academique.bulletin.generer','Academique bulletin generer','academique','generer'),(89,'academique.bulletin.publier','Academique bulletin publier','academique','publier'),(90,'academique.analytics.view','Academique analytics view','academique','view'),(91,'attendance.view','Attendance view','attendance','view'),(92,'attendance.create','Attendance create','attendance','create'),(93,'attendance.update','Attendance update','attendance','update'),(94,'attendance.delete','Attendance delete','attendance','delete'),(95,'attendance.justify','Attendance justify','attendance','justify'),(96,'attendance.validate','Attendance validate','attendance','validate'),(97,'attendance.session.view','Attendance session view','attendance','view'),(98,'attendance.session.create','Attendance session create','attendance','create'),(99,'attendance.session.update','Attendance session update','attendance','update'),(100,'attendance.session.validate','Attendance session validate','attendance','validate'),(101,'late.view','Late view','late','view'),(102,'late.create','Late create','late','create'),(103,'late.update','Late update','late','update'),(104,'late.justify','Late justify','late','justify'),(105,'late.validate','Late validate','late','validate'),(106,'late.export','Late export','late','export'),(107,'discipline.view','Discipline view','discipline','view'),(108,'discipline.create','Discipline create','discipline','create'),(109,'discipline.update','Discipline update','discipline','update'),(110,'discipline.validate','Discipline validate','discipline','validate'),(111,'discipline.sanction','Discipline sanction','discipline','sanction'),(112,'discipline.export','Discipline export','discipline','export'),(113,'reward.view','Reward view','reward','view'),(114,'reward.create','Reward create','reward','create'),(115,'reward.update','Reward update','reward','update'),(116,'reward.validate','Reward validate','reward','validate'),(117,'reward.export','Reward export','reward','export'),(118,'timetable.view','Timetable view','timetable','view'),(119,'timetable.create','Timetable create','timetable','create'),(120,'timetable.update','Timetable update','timetable','update'),(121,'timetable.publish','Timetable publish','timetable','publish'),(122,'timetable.export','Timetable export','timetable','export'),(123,'activity.view','Activity view','activity','view'),(124,'activity.create','Activity create','activity','create'),(125,'activity.update','Activity update','activity','update'),(126,'activity.validate','Activity validate','activity','validate'),(127,'activity.publish','Activity publish','activity','publish'),(128,'activity.export','Activity export','activity','export'),(129,'employee.view','Employee view','employee','view'),(130,'employee.create','Employee create','employee','create'),(131,'employee.update','Employee update','employee','update'),(132,'employee.archive','Employee archive','employee','archive'),(133,'employee.restore','Employee restore','employee','restore'),(134,'employee.export','Employee export','employee','export'),(135,'teacher.view','Teacher view','teacher','view'),(136,'teacher.create','Teacher create','teacher','create'),(137,'teacher.update','Teacher update','teacher','update'),(138,'teacher.assign','Teacher assign','teacher','assign'),(139,'teacher.export','Teacher export','teacher','export'),(140,'organization.view','Organization view','organization','view'),(141,'organization.create','Organization create','organization','create'),(142,'organization.update','Organization update','organization','update'),(143,'organization.archive','Organization archive','organization','archive'),(144,'organization.export','Organization export','organization','export'),(145,'contract.view','Contract view','contract','view'),(146,'contract.create','Contract create','contract','create'),(147,'contract.update','Contract update','contract','update'),(148,'contract.renew','Contract renew','contract','renew'),(149,'contract.terminate','Contract terminate','contract','terminate'),(150,'contract.archive','Contract archive','contract','archive'),(151,'contract.export','Contract export','contract','export'),(152,'assignment.view','Assignment view','assignment','view'),(153,'assignment.create','Assignment create','assignment','create'),(154,'assignment.update','Assignment update','assignment','update'),(155,'assignment.archive','Assignment archive','assignment','archive'),(156,'assignment.export','Assignment export','assignment','export'),(157,'rh.presence.view','Rh presence view','rh','view'),(158,'rh.presence.create','Rh presence create','rh','create'),(159,'rh.presence.update','Rh presence update','rh','update'),(160,'rh.presence.validate','Rh presence validate','rh','validate'),(161,'rh.presence.export','Rh presence export','rh','export'),(162,'leave.view','Leave view','leave','view'),(163,'leave.create','Leave create','leave','create'),(164,'leave.update','Leave update','leave','update'),(165,'leave.approve','Leave approve','leave','approve'),(166,'leave.reject','Leave reject','leave','reject'),(167,'leave.cancel','Leave cancel','leave','cancel'),(168,'leave.export','Leave export','leave','export'),(169,'evaluation.view','Evaluation view','evaluation','view'),(170,'evaluation.create','Evaluation create','evaluation','create'),(171,'evaluation.update','Evaluation update','evaluation','update'),(172,'evaluation.validate','Evaluation validate','evaluation','validate'),(173,'evaluation.publish','Evaluation publish','evaluation','publish'),(174,'evaluation.export','Evaluation export','evaluation','export'),(175,'training.view','Training view','training','view'),(176,'training.create','Training create','training','create'),(177,'training.update','Training update','training','update'),(178,'training.enroll','Training enroll','training','enroll'),(179,'training.validate','Training validate','training','validate'),(180,'training.export','Training export','training','export'),(181,'training.manage_catalog','Training manage catalog','training','manage_catalog'),(182,'hr_document.view','Hr document view','hr_document','view'),(183,'hr_document.create','Hr document create','hr_document','create'),(184,'hr_document.update','Hr document update','hr_document','update'),(185,'hr_document.archive','Hr document archive','hr_document','archive'),(186,'hr_document.export','Hr document export','hr_document','export'),(187,'document.view','Document view','document','view'),(188,'document.view_all','Document view all','document','view_all'),(189,'document.view_secret','Document view secret','document','view_secret'),(190,'document.create','Document create','document','create'),(191,'document.update','Document update','document','update'),(192,'document.version','Document version','document','version'),(193,'document.archive','Document archive','document','archive'),(194,'document.delete','Document delete','document','delete'),(195,'document.restore','Document restore','document','restore'),(196,'document.share','Document share','document','share'),(197,'document.export','Document export','document','export'),(198,'document.sign','Document sign','document','sign'),(199,'document.admin','Document admin','document','admin'),(200,'folder.create','Folder create','folder','create'),(201,'folder.delete','Folder delete','folder','delete'),(202,'communication.view','Communication view','communication','view'),(203,'communication.send','Communication send','communication','send'),(204,'communication.broadcast','Communication broadcast','communication','broadcast'),(205,'communication.manage_templates','Communication manage templates','communication','manage_templates'),(206,'communication.manage_groups','Communication manage groups','communication','manage_groups'),(207,'communication.campaign','Communication campaign','communication','campaign'),(208,'communication.admin','Communication admin','communication','admin'),(209,'communication.view_all','Communication view all','communication','view_all'),(210,'biblio.view','Biblio view','biblio','view'),(211,'biblio.search','Biblio search','biblio','search'),(212,'biblio.borrow','Biblio borrow','biblio','borrow'),(213,'biblio.reserve','Biblio reserve','biblio','reserve'),(214,'biblio.manage_loans','Biblio manage loans','biblio','manage_loans'),(215,'biblio.manage_catalogue','Biblio manage catalogue','biblio','manage_catalogue'),(216,'biblio.manage_exemplaires','Biblio manage exemplaires','biblio','manage_exemplaires'),(217,'biblio.manage_penalties','Biblio manage penalties','biblio','manage_penalties'),(218,'biblio.inventory','Biblio inventory','biblio','inventory'),(219,'biblio.analytics','Biblio analytics','biblio','analytics'),(220,'biblio.admin','Biblio admin','biblio','admin'),(221,'inventaire.view','Inventaire view','inventaire','view'),(222,'inventaire.create','Inventaire create','inventaire','create'),(223,'inventaire.edit','Inventaire edit','inventaire','edit'),(224,'inventaire.delete','Inventaire delete','inventaire','delete'),(225,'inventaire.stock.view','Inventaire stock view','inventaire','view'),(226,'inventaire.stock.manage','Inventaire stock manage','inventaire','manage'),(227,'inventaire.commande.view','Inventaire commande view','inventaire','view'),(228,'inventaire.commande.create','Inventaire commande create','inventaire','create'),(229,'inventaire.commande.validate','Inventaire commande validate','inventaire','validate'),(230,'inventaire.commande.receive','Inventaire commande receive','inventaire','receive'),(231,'inventaire.affectation.view','Inventaire affectation view','inventaire','view'),(232,'inventaire.affectation.create','Inventaire affectation create','inventaire','create'),(233,'inventaire.affectation.return','Inventaire affectation return','inventaire','return'),(234,'inventaire.affectation.lost','Inventaire affectation lost','inventaire','lost'),(235,'inventaire.maintenance.view','Inventaire maintenance view','inventaire','view'),(236,'inventaire.maintenance.create','Inventaire maintenance create','inventaire','create'),(237,'inventaire.maintenance.edit','Inventaire maintenance edit','inventaire','edit'),(238,'inventaire.physique.view','Inventaire physique view','inventaire','view'),(239,'inventaire.physique.create','Inventaire physique create','inventaire','create'),(240,'inventaire.physique.count','Inventaire physique count','inventaire','count'),(241,'inventaire.physique.close','Inventaire physique close','inventaire','close'),(242,'inventaire.reports','Inventaire reports','inventaire','reports'),(243,'portal.admin.access','Portal admin access','portal','access'),(244,'portal.direction.access','Portal direction access','portal','access'),(245,'portal.enseignant.access','Portal enseignant access','portal','access'),(246,'portal.eleve.access','Portal eleve access','portal','access'),(247,'portal.parent.access','Portal parent access','portal','access'),(248,'portal.comptabilite.access','Portal comptabilite access','portal','access'),(249,'portal.rh.access','Portal rh access','portal','access'),(250,'rapports.dashboard.direction','Rapports dashboard direction','rapports','direction'),(251,'rapports.dashboard.administration','Rapports dashboard administration','rapports','administration'),(252,'rapports.dashboard.scolarite','Rapports dashboard scolarite','rapports','scolarite'),(253,'rapports.dashboard.academique','Rapports dashboard academique','rapports','academique'),(254,'rapports.dashboard.finance','Rapports dashboard finance','rapports','finance'),(255,'rapports.dashboard.rh','Rapports dashboard rh','rapports','rh'),(256,'rapports.dashboard.vie_scolaire','Rapports dashboard vie scolaire','rapports','vie_scolaire'),(257,'rapports.dashboard.bibliotheque','Rapports dashboard bibliotheque','rapports','bibliotheque'),(258,'rapports.dashboard.inventaire','Rapports dashboard inventaire','rapports','inventaire'),(259,'rapports.kpis.voir','Rapports kpis voir','rapports','voir'),(260,'rapports.exporter','Rapports exporter','rapports','exporter'),(261,'rapports.planifier','Rapports planifier','rapports','planifier'),(262,'rapports.api.analytics','Rapports api analytics','rapports','analytics'),(263,'emploi_du_temps.view_own','Emploi du temps view own','emploi_du_temps','view_own'),(264,'employee.view.own','Employee view own','employee','own'),(265,'absences.justify','Absences justify','absences','justify'),(266,'comptabilite.view_own','Comptabilite view own','comptabilite','view_own'),(267,'finance.paiements.view.own','Finance paiements view own','finance','own'),(268,'branding.view','Voir les paramètres de branding','branding','view'),(269,'branding.update','Modifier les paramètres de branding','branding','update'),(270,'domains.view','Voir les domaines personnalisés','domains','view'),(271,'domains.manage','Gérer les domaines personnalisés','domains','manage'),(272,'quota.view','Voir les quotas et la consommation de stockage','quota','view'),(273,'monitoring.view','Voir le monitoring cache/files d\'attente','monitoring','view'),(274,'settings.general.view','Voir la configuration générale','settings','view'),(275,'settings.general.update','Modifier la configuration générale','settings','update'),(276,'settings.academique.view','Voir l\'organisation académique','settings','view'),(277,'settings.academique.update','Modifier l\'organisation académique','settings','update'),(278,'settings.notation.view','Voir le système de notation','settings','view'),(279,'settings.notation.update','Modifier le système de notation','settings','update'),(280,'settings.finances.view','Voir la configuration financière','settings','view'),(281,'settings.finances.update','Modifier la configuration financière','settings','update'),(282,'settings.documents.view','Voir la configuration des documents','settings','view'),(283,'settings.documents.update','Modifier la configuration des documents','settings','update'),(284,'settings.notifications.view','Voir la configuration des notifications','settings','view'),(285,'settings.notifications.update','Modifier la configuration des notifications','settings','update'),(286,'settings.securite.view','Voir les paramètres de sécurité','settings','view'),(287,'settings.securite.update','Modifier les paramètres de sécurité','settings','update'),(288,'settings.sauvegarde.view','Voir les préférences de sauvegarde','settings','view'),(289,'settings.sauvegarde.update','Modifier les préférences de sauvegarde','settings','update'),(290,'settings.avance.view','Voir les paramètres avancés','settings','view'),(291,'settings.avance.update','Modifier les paramètres avancés','settings','update'),(292,'academique.conduite.manage','Gérer la Conduite (évaluations, notes, appréciations)','academique','manage'),(293,'academique.bulletin.admin','Administrer les bulletins (générer, publier, archiver, appréciation du directeur)','academique','admin'),(294,'academique.evaluations.admin','Administrer les évaluations','academique','admin'),(295,'academique.notes.admin','Administrer les notes','academique','admin'),(296,'academique.periodes.admin','Administrer les périodes (réouverture, modèle par défaut, déverrouillage)','academique','admin'),(297,'academique.types_evaluations.view','Consulter les types d\'évaluation','academique','view'),(298,'academique.types_evaluations.manage','Gérer les types d\'évaluation','academique','manage');
/*!40000 ALTER TABLE `etab_permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `etab_role_permissions` WRITE;
/*!40000 ALTER TABLE `etab_role_permissions` DISABLE KEYS */;
INSERT INTO `etab_role_permissions` (`role_id`, `permission_id`, `granted_at`, `granted_by`) VALUES (1,1,'2026-07-08 10:51:05',NULL),(1,2,'2026-07-08 10:51:06',NULL),(1,3,'2026-07-08 10:51:06',NULL),(1,4,'2026-07-08 10:51:06',NULL),(1,5,'2026-07-08 10:51:06',NULL),(1,6,'2026-07-08 10:51:06',NULL),(1,7,'2026-07-08 10:51:06',NULL),(1,8,'2026-07-08 10:51:06',NULL),(1,9,'2026-07-08 10:51:06',NULL),(1,10,'2026-07-08 10:51:06',NULL),(1,11,'2026-07-08 10:51:06',NULL),(1,12,'2026-07-08 10:51:06',NULL),(1,13,'2026-07-08 10:51:06',NULL),(1,14,'2026-07-08 10:51:06',NULL),(1,15,'2026-07-08 10:51:06',NULL),(1,16,'2026-07-08 10:51:06',NULL),(1,17,'2026-07-08 10:51:06',NULL),(1,18,'2026-07-08 10:51:06',NULL),(1,19,'2026-07-08 10:51:06',NULL),(1,20,'2026-07-08 10:51:06',NULL),(1,21,'2026-07-08 10:51:06',NULL),(1,22,'2026-07-08 10:51:06',NULL),(1,23,'2026-07-08 10:51:06',NULL),(1,24,'2026-07-08 10:51:06',NULL),(1,25,'2026-07-08 10:51:06',NULL),(1,26,'2026-07-08 10:51:06',NULL),(1,27,'2026-07-08 10:51:06',NULL),(1,28,'2026-07-08 10:51:06',NULL),(1,29,'2026-07-08 10:51:06',NULL),(1,30,'2026-07-08 10:51:06',NULL),(1,31,'2026-07-08 10:51:06',NULL),(1,32,'2026-07-08 10:51:06',NULL),(1,33,'2026-07-08 10:51:06',NULL),(1,34,'2026-07-08 10:51:06',NULL),(1,35,'2026-07-08 10:51:06',NULL),(1,36,'2026-07-08 10:51:06',NULL),(1,37,'2026-07-08 10:51:06',NULL),(1,38,'2026-07-08 10:51:06',NULL),(1,39,'2026-07-08 10:51:06',NULL),(1,40,'2026-07-08 10:51:06',NULL),(1,41,'2026-07-08 10:51:06',NULL),(1,42,'2026-07-08 10:51:06',NULL),(1,43,'2026-07-08 10:51:06',NULL),(1,44,'2026-07-08 10:51:06',NULL),(1,45,'2026-07-08 10:51:06',NULL),(1,46,'2026-07-08 10:51:06',NULL),(1,47,'2026-07-08 10:51:06',NULL),(1,48,'2026-07-08 10:51:06',NULL),(1,49,'2026-07-08 10:51:06',NULL),(1,50,'2026-07-08 10:51:06',NULL),(1,51,'2026-07-08 10:51:06',NULL),(1,52,'2026-07-08 10:51:06',NULL),(1,53,'2026-07-08 10:51:06',NULL),(1,54,'2026-07-08 10:51:06',NULL),(1,55,'2026-07-08 10:51:06',NULL),(1,56,'2026-07-08 10:51:06',NULL),(1,57,'2026-07-08 10:51:06',NULL),(1,58,'2026-07-08 10:51:06',NULL),(1,59,'2026-07-08 10:51:06',NULL),(1,60,'2026-07-08 10:51:06',NULL),(1,61,'2026-07-08 10:51:06',NULL),(1,62,'2026-07-08 10:51:06',NULL),(1,63,'2026-07-08 10:51:06',NULL),(1,64,'2026-07-08 10:51:06',NULL),(1,65,'2026-07-08 10:51:06',NULL),(1,66,'2026-07-08 10:51:06',NULL),(1,67,'2026-07-08 10:51:06',NULL),(1,68,'2026-07-08 10:51:06',NULL),(1,69,'2026-07-08 10:51:06',NULL),(1,70,'2026-07-08 10:51:06',NULL),(1,71,'2026-07-08 10:51:06',NULL),(1,72,'2026-07-08 10:51:06',NULL),(1,73,'2026-07-08 10:51:07',NULL),(1,74,'2026-07-08 10:51:07',NULL),(1,75,'2026-07-08 10:51:07',NULL),(1,76,'2026-07-08 10:51:07',NULL),(1,77,'2026-07-08 10:51:07',NULL),(1,78,'2026-07-08 10:51:07',NULL),(1,79,'2026-07-08 10:51:07',NULL),(1,80,'2026-07-08 10:51:07',NULL),(1,81,'2026-07-08 10:51:07',NULL),(1,82,'2026-07-08 10:51:07',NULL),(1,83,'2026-07-08 10:51:07',NULL),(1,84,'2026-07-08 10:51:07',NULL),(1,85,'2026-07-08 10:51:07',NULL),(1,86,'2026-07-08 10:51:07',NULL),(1,87,'2026-07-08 10:51:07',NULL),(1,88,'2026-07-08 10:51:07',NULL),(1,89,'2026-07-08 10:51:07',NULL),(1,90,'2026-07-08 10:51:07',NULL),(1,91,'2026-07-08 10:51:07',NULL),(1,92,'2026-07-08 10:51:07',NULL),(1,93,'2026-07-08 10:51:07',NULL),(1,94,'2026-07-08 10:51:07',NULL),(1,95,'2026-07-08 10:51:07',NULL),(1,96,'2026-07-08 10:51:07',NULL),(1,97,'2026-07-08 10:51:07',NULL),(1,98,'2026-07-08 10:51:07',NULL),(1,99,'2026-07-08 10:51:07',NULL),(1,100,'2026-07-08 10:51:07',NULL),(1,101,'2026-07-08 10:51:07',NULL),(1,102,'2026-07-08 10:51:07',NULL),(1,103,'2026-07-08 10:51:07',NULL),(1,104,'2026-07-08 10:51:07',NULL),(1,105,'2026-07-08 10:51:07',NULL),(1,106,'2026-07-08 10:51:07',NULL),(1,107,'2026-07-08 10:51:07',NULL),(1,108,'2026-07-08 10:51:07',NULL),(1,109,'2026-07-08 10:51:07',NULL),(1,110,'2026-07-08 10:51:07',NULL),(1,111,'2026-07-08 10:51:07',NULL),(1,112,'2026-07-08 10:51:07',NULL),(1,113,'2026-07-08 10:51:07',NULL),(1,114,'2026-07-08 10:51:07',NULL),(1,115,'2026-07-08 10:51:07',NULL),(1,116,'2026-07-08 10:51:07',NULL),(1,117,'2026-07-08 10:51:07',NULL),(1,118,'2026-07-08 10:51:07',NULL),(1,119,'2026-07-08 10:51:07',NULL),(1,120,'2026-07-08 10:51:07',NULL),(1,121,'2026-07-08 10:51:07',NULL),(1,122,'2026-07-08 10:51:07',NULL),(1,123,'2026-07-08 10:51:07',NULL),(1,124,'2026-07-08 10:51:08',NULL),(1,125,'2026-07-08 10:51:08',NULL),(1,126,'2026-07-08 10:51:08',NULL),(1,127,'2026-07-08 10:51:08',NULL),(1,128,'2026-07-08 10:51:08',NULL),(1,129,'2026-07-08 10:51:08',NULL),(1,130,'2026-07-08 10:51:08',NULL),(1,131,'2026-07-08 10:51:08',NULL),(1,132,'2026-07-08 10:51:08',NULL),(1,133,'2026-07-08 10:51:08',NULL),(1,134,'2026-07-08 10:51:08',NULL),(1,135,'2026-07-08 10:51:08',NULL),(1,136,'2026-07-08 10:51:08',NULL),(1,137,'2026-07-08 10:51:08',NULL),(1,138,'2026-07-08 10:51:08',NULL),(1,139,'2026-07-08 10:51:08',NULL),(1,140,'2026-07-08 10:51:08',NULL),(1,141,'2026-07-08 10:51:08',NULL),(1,142,'2026-07-08 10:51:08',NULL),(1,143,'2026-07-08 10:51:08',NULL),(1,144,'2026-07-08 10:51:08',NULL),(1,145,'2026-07-08 10:51:08',NULL),(1,146,'2026-07-08 10:51:08',NULL),(1,147,'2026-07-08 10:51:08',NULL),(1,148,'2026-07-08 10:51:08',NULL),(1,149,'2026-07-08 10:51:08',NULL),(1,150,'2026-07-08 10:51:08',NULL),(1,151,'2026-07-08 10:51:08',NULL),(1,152,'2026-07-08 10:51:08',NULL),(1,153,'2026-07-08 10:51:08',NULL),(1,154,'2026-07-08 10:51:08',NULL),(1,155,'2026-07-08 10:51:08',NULL),(1,156,'2026-07-08 10:51:08',NULL),(1,157,'2026-07-08 10:51:08',NULL),(1,158,'2026-07-08 10:51:08',NULL),(1,159,'2026-07-08 10:51:08',NULL),(1,160,'2026-07-08 10:51:08',NULL),(1,161,'2026-07-08 10:51:08',NULL),(1,162,'2026-07-08 10:51:08',NULL),(1,163,'2026-07-08 10:51:08',NULL),(1,164,'2026-07-08 10:51:08',NULL),(1,165,'2026-07-08 10:51:08',NULL),(1,166,'2026-07-08 10:51:08',NULL),(1,167,'2026-07-08 10:51:08',NULL),(1,168,'2026-07-08 10:51:08',NULL),(1,169,'2026-07-08 10:51:08',NULL),(1,170,'2026-07-08 10:51:08',NULL),(1,171,'2026-07-08 10:51:08',NULL),(1,172,'2026-07-08 10:51:08',NULL),(1,173,'2026-07-08 10:51:08',NULL),(1,174,'2026-07-08 10:51:08',NULL),(1,175,'2026-07-08 10:51:08',NULL),(1,176,'2026-07-08 10:51:08',NULL),(1,177,'2026-07-08 10:51:08',NULL),(1,178,'2026-07-08 10:51:08',NULL),(1,179,'2026-07-08 10:51:08',NULL),(1,180,'2026-07-08 10:51:08',NULL),(1,181,'2026-07-08 10:51:08',NULL),(1,182,'2026-07-08 10:51:08',NULL),(1,183,'2026-07-08 10:51:08',NULL),(1,184,'2026-07-08 10:51:08',NULL),(1,185,'2026-07-08 10:51:08',NULL),(1,186,'2026-07-08 10:51:08',NULL),(1,187,'2026-07-08 10:51:08',NULL),(1,188,'2026-07-08 10:51:08',NULL),(1,189,'2026-07-08 10:51:09',NULL),(1,190,'2026-07-08 10:51:09',NULL),(1,191,'2026-07-08 10:51:09',NULL),(1,192,'2026-07-08 10:51:09',NULL),(1,193,'2026-07-08 10:51:09',NULL),(1,194,'2026-07-08 10:51:09',NULL),(1,195,'2026-07-08 10:51:09',NULL),(1,196,'2026-07-08 10:51:09',NULL),(1,197,'2026-07-08 10:51:09',NULL),(1,198,'2026-07-08 10:51:09',NULL),(1,199,'2026-07-08 10:51:09',NULL),(1,200,'2026-07-08 10:51:09',NULL),(1,201,'2026-07-08 10:51:09',NULL),(1,202,'2026-07-08 10:51:09',NULL),(1,203,'2026-07-08 10:51:09',NULL),(1,204,'2026-07-08 10:51:09',NULL),(1,205,'2026-07-08 10:51:09',NULL),(1,206,'2026-07-08 10:51:09',NULL),(1,207,'2026-07-08 10:51:09',NULL),(1,208,'2026-07-08 10:51:09',NULL),(1,209,'2026-07-08 10:51:09',NULL),(1,210,'2026-07-08 10:51:09',NULL),(1,211,'2026-07-08 10:51:09',NULL),(1,212,'2026-07-08 10:51:09',NULL),(1,213,'2026-07-08 10:51:09',NULL),(1,214,'2026-07-08 10:51:09',NULL),(1,215,'2026-07-08 10:51:09',NULL),(1,216,'2026-07-08 10:51:09',NULL),(1,217,'2026-07-08 10:51:09',NULL),(1,218,'2026-07-08 10:51:09',NULL),(1,219,'2026-07-08 10:51:09',NULL),(1,220,'2026-07-08 10:51:09',NULL),(1,221,'2026-07-08 10:51:09',NULL),(1,222,'2026-07-08 10:51:09',NULL),(1,223,'2026-07-08 10:51:09',NULL),(1,224,'2026-07-08 10:51:09',NULL),(1,225,'2026-07-08 10:51:09',NULL),(1,226,'2026-07-08 10:51:09',NULL),(1,227,'2026-07-08 10:51:09',NULL),(1,228,'2026-07-08 10:51:09',NULL),(1,229,'2026-07-08 10:51:09',NULL),(1,230,'2026-07-08 10:51:09',NULL),(1,231,'2026-07-08 10:51:09',NULL),(1,232,'2026-07-08 10:51:09',NULL),(1,233,'2026-07-08 10:51:09',NULL),(1,234,'2026-07-08 10:51:09',NULL),(1,235,'2026-07-08 10:51:09',NULL),(1,236,'2026-07-08 10:51:09',NULL),(1,237,'2026-07-08 10:51:09',NULL),(1,238,'2026-07-08 10:51:09',NULL),(1,239,'2026-07-08 10:51:09',NULL),(1,240,'2026-07-08 10:51:09',NULL),(1,241,'2026-07-08 10:51:09',NULL),(1,242,'2026-07-08 10:51:09',NULL),(1,243,'2026-07-08 10:51:09',NULL),(1,244,'2026-07-08 10:51:09',NULL),(1,245,'2026-07-08 10:51:09',NULL),(1,246,'2026-07-08 10:51:09',NULL),(1,247,'2026-07-08 10:51:09',NULL),(1,248,'2026-07-08 10:51:09',NULL),(1,249,'2026-07-08 10:51:09',NULL),(1,268,'2026-07-08 12:12:43',NULL),(1,269,'2026-07-08 12:12:43',NULL),(1,274,'2026-07-09 21:51:35',NULL),(1,275,'2026-07-09 21:51:35',NULL),(1,276,'2026-07-10 00:22:18',NULL),(1,277,'2026-07-10 00:22:18',NULL),(1,278,'2026-07-10 00:22:18',NULL),(1,279,'2026-07-10 00:22:18',NULL),(1,280,'2026-07-10 00:22:18',NULL),(1,281,'2026-07-10 00:22:18',NULL),(1,282,'2026-07-10 00:22:18',NULL),(1,283,'2026-07-10 00:22:18',NULL),(1,284,'2026-07-10 00:22:18',NULL),(1,285,'2026-07-10 00:22:18',NULL),(1,286,'2026-07-10 00:22:18',NULL),(1,287,'2026-07-10 00:22:18',NULL),(1,288,'2026-07-10 00:22:18',NULL),(1,289,'2026-07-10 00:22:18',NULL),(1,290,'2026-07-10 00:22:18',NULL),(1,291,'2026-07-10 00:22:18',NULL),(1,292,'2026-10-25 18:01:09',NULL),(1,293,'2026-10-26 09:09:23',NULL),(1,294,'2026-10-26 09:09:23',NULL),(1,295,'2026-10-26 09:09:23',NULL),(1,296,'2026-10-26 09:09:23',NULL),(1,297,'2026-10-26 09:09:23',NULL),(1,298,'2026-10-26 09:09:23',NULL),(2,1,'2026-07-08 10:51:09',NULL),(2,2,'2026-07-08 10:51:09',NULL),(2,3,'2026-07-08 10:51:09',NULL),(2,4,'2026-07-08 10:51:09',NULL),(2,5,'2026-07-08 10:51:09',NULL),(2,6,'2026-07-08 10:51:09',NULL),(2,7,'2026-07-08 10:51:09',NULL),(2,8,'2026-07-08 10:51:09',NULL),(2,9,'2026-07-08 10:51:09',NULL),(2,10,'2026-07-08 10:51:09',NULL),(2,11,'2026-07-08 10:51:09',NULL),(2,12,'2026-07-08 10:51:09',NULL),(2,13,'2026-07-08 10:51:09',NULL),(2,14,'2026-07-08 10:51:09',NULL),(2,15,'2026-07-08 10:51:09',NULL),(2,16,'2026-07-08 10:51:09',NULL),(2,17,'2026-07-08 10:51:09',NULL),(2,18,'2026-07-08 10:51:09',NULL),(2,19,'2026-07-08 10:51:09',NULL),(2,20,'2026-07-08 10:51:09',NULL),(2,22,'2026-07-08 10:51:09',NULL),(2,23,'2026-07-08 10:51:09',NULL),(2,24,'2026-07-08 10:51:09',NULL),(2,26,'2026-07-08 10:51:09',NULL),(2,27,'2026-07-08 10:51:09',NULL),(2,28,'2026-07-08 10:51:09',NULL),(2,29,'2026-07-08 10:51:09',NULL),(2,30,'2026-07-08 10:51:09',NULL),(2,31,'2026-07-08 10:51:09',NULL),(2,32,'2026-07-08 10:51:09',NULL),(2,33,'2026-07-08 10:51:09',NULL),(2,34,'2026-07-08 10:51:09',NULL),(2,35,'2026-07-08 10:51:09',NULL),(2,36,'2026-07-08 10:51:09',NULL),(2,37,'2026-07-08 10:51:09',NULL),(2,38,'2026-07-08 10:51:09',NULL),(2,39,'2026-07-08 10:51:09',NULL),(2,40,'2026-07-08 10:51:09',NULL),(2,42,'2026-07-08 10:51:09',NULL),(2,43,'2026-07-08 10:51:09',NULL),(2,44,'2026-07-08 10:51:09',NULL),(2,45,'2026-07-08 10:51:09',NULL),(2,46,'2026-07-08 10:51:09',NULL),(2,47,'2026-07-08 10:51:09',NULL),(2,48,'2026-07-08 10:51:09',NULL),(2,49,'2026-07-08 10:51:09',NULL),(2,50,'2026-07-08 10:51:09',NULL),(2,51,'2026-07-08 10:51:09',NULL),(2,52,'2026-07-08 10:51:09',NULL),(2,53,'2026-07-08 10:51:09',NULL),(2,54,'2026-07-08 10:51:09',NULL),(2,55,'2026-07-08 10:51:09',NULL),(2,56,'2026-07-08 10:51:09',NULL),(2,57,'2026-07-08 10:51:09',NULL),(2,58,'2026-07-08 10:51:10',NULL),(2,59,'2026-07-08 10:51:10',NULL),(2,60,'2026-07-08 10:51:10',NULL),(2,61,'2026-07-08 10:51:10',NULL),(2,62,'2026-07-08 10:51:10',NULL),(2,63,'2026-07-08 10:51:10',NULL),(2,64,'2026-07-08 10:51:10',NULL),(2,65,'2026-07-08 10:51:10',NULL),(2,66,'2026-07-08 10:51:10',NULL),(2,68,'2026-07-08 10:51:10',NULL),(2,69,'2026-07-08 10:51:10',NULL),(2,70,'2026-07-08 10:51:10',NULL),(2,77,'2026-07-08 10:51:10',NULL),(2,78,'2026-07-08 10:51:10',NULL),(2,79,'2026-07-08 10:51:10',NULL),(2,80,'2026-07-08 10:51:10',NULL),(2,81,'2026-07-08 10:51:10',NULL),(2,82,'2026-07-08 10:51:10',NULL),(2,83,'2026-07-08 10:51:10',NULL),(2,84,'2026-07-08 10:51:10',NULL),(2,85,'2026-07-08 10:51:10',NULL),(2,86,'2026-07-08 10:51:10',NULL),(2,87,'2026-07-08 10:51:10',NULL),(2,88,'2026-07-08 10:51:10',NULL),(2,89,'2026-07-08 10:51:10',NULL),(2,90,'2026-07-08 10:51:10',NULL),(2,91,'2026-07-08 10:51:10',NULL),(2,92,'2026-07-08 10:51:10',NULL),(2,93,'2026-07-08 10:51:10',NULL),(2,94,'2026-07-08 10:51:10',NULL),(2,95,'2026-07-08 10:51:10',NULL),(2,96,'2026-07-08 10:51:10',NULL),(2,97,'2026-07-08 10:51:10',NULL),(2,98,'2026-07-08 10:51:10',NULL),(2,99,'2026-07-08 10:51:10',NULL),(2,100,'2026-07-08 10:51:10',NULL),(2,101,'2026-07-08 10:51:10',NULL),(2,102,'2026-07-08 10:51:10',NULL),(2,103,'2026-07-08 10:51:10',NULL),(2,104,'2026-07-08 10:51:10',NULL),(2,105,'2026-07-08 10:51:10',NULL),(2,106,'2026-07-08 10:51:10',NULL),(2,107,'2026-07-08 10:51:10',NULL),(2,108,'2026-07-08 10:51:10',NULL),(2,109,'2026-07-08 10:51:10',NULL),(2,110,'2026-07-08 10:51:10',NULL),(2,111,'2026-07-08 10:51:10',NULL),(2,112,'2026-07-08 10:51:10',NULL),(2,113,'2026-07-08 10:51:10',NULL),(2,114,'2026-07-08 10:51:10',NULL),(2,115,'2026-07-08 10:51:10',NULL),(2,116,'2026-07-08 10:51:10',NULL),(2,117,'2026-07-08 10:51:10',NULL),(2,118,'2026-07-08 10:51:10',NULL),(2,119,'2026-07-08 10:51:10',NULL),(2,120,'2026-07-08 10:51:10',NULL),(2,121,'2026-07-08 10:51:10',NULL),(2,122,'2026-07-08 10:51:10',NULL),(2,123,'2026-07-08 10:51:10',NULL),(2,124,'2026-07-08 10:51:10',NULL),(2,125,'2026-07-08 10:51:10',NULL),(2,126,'2026-07-08 10:51:10',NULL),(2,127,'2026-07-08 10:51:10',NULL),(2,128,'2026-07-08 10:51:10',NULL),(2,129,'2026-07-08 10:51:10',NULL),(2,130,'2026-07-08 10:51:10',NULL),(2,131,'2026-07-08 10:51:10',NULL),(2,132,'2026-07-08 10:51:10',NULL),(2,133,'2026-07-08 10:51:10',NULL),(2,134,'2026-07-08 10:51:10',NULL),(2,135,'2026-07-08 10:51:10',NULL),(2,136,'2026-07-08 10:51:10',NULL),(2,137,'2026-07-08 10:51:10',NULL),(2,138,'2026-07-08 10:51:10',NULL),(2,139,'2026-07-08 10:51:10',NULL),(2,140,'2026-07-08 10:51:10',NULL),(2,141,'2026-07-08 10:51:10',NULL),(2,142,'2026-07-08 10:51:10',NULL),(2,143,'2026-07-08 10:51:10',NULL),(2,144,'2026-07-08 10:51:10',NULL),(2,145,'2026-07-08 10:51:10',NULL),(2,146,'2026-07-08 10:51:10',NULL),(2,147,'2026-07-08 10:51:10',NULL),(2,148,'2026-07-08 10:51:10',NULL),(2,149,'2026-07-08 10:51:11',NULL),(2,150,'2026-07-08 10:51:11',NULL),(2,151,'2026-07-08 10:51:11',NULL),(2,152,'2026-07-08 10:51:11',NULL),(2,153,'2026-07-08 10:51:11',NULL),(2,154,'2026-07-08 10:51:11',NULL),(2,155,'2026-07-08 10:51:11',NULL),(2,156,'2026-07-08 10:51:11',NULL),(2,157,'2026-07-08 10:51:11',NULL),(2,158,'2026-07-08 10:51:11',NULL),(2,159,'2026-07-08 10:51:11',NULL),(2,160,'2026-07-08 10:51:11',NULL),(2,161,'2026-07-08 10:51:11',NULL),(2,162,'2026-07-08 10:51:11',NULL),(2,163,'2026-07-08 10:51:11',NULL),(2,164,'2026-07-08 10:51:11',NULL),(2,165,'2026-07-08 10:51:11',NULL),(2,166,'2026-07-08 10:51:11',NULL),(2,167,'2026-07-08 10:51:11',NULL),(2,168,'2026-07-08 10:51:11',NULL),(2,169,'2026-07-08 10:51:11',NULL),(2,170,'2026-07-08 10:51:11',NULL),(2,171,'2026-07-08 10:51:11',NULL),(2,172,'2026-07-08 10:51:11',NULL),(2,173,'2026-07-08 10:51:11',NULL),(2,174,'2026-07-08 10:51:11',NULL),(2,175,'2026-07-08 10:51:11',NULL),(2,176,'2026-07-08 10:51:11',NULL),(2,177,'2026-07-08 10:51:11',NULL),(2,178,'2026-07-08 10:51:11',NULL),(2,179,'2026-07-08 10:51:11',NULL),(2,180,'2026-07-08 10:51:11',NULL),(2,181,'2026-07-08 10:51:11',NULL),(2,182,'2026-07-08 10:51:11',NULL),(2,183,'2026-07-08 10:51:11',NULL),(2,184,'2026-07-08 10:51:11',NULL),(2,185,'2026-07-08 10:51:11',NULL),(2,186,'2026-07-08 10:51:11',NULL),(2,187,'2026-07-08 10:51:11',NULL),(2,188,'2026-07-08 10:51:11',NULL),(2,189,'2026-07-08 10:51:11',NULL),(2,190,'2026-07-08 10:51:11',NULL),(2,191,'2026-07-08 10:51:11',NULL),(2,192,'2026-07-08 10:51:11',NULL),(2,193,'2026-07-08 10:51:11',NULL),(2,194,'2026-07-08 10:51:11',NULL),(2,195,'2026-07-08 10:51:11',NULL),(2,196,'2026-07-08 10:51:11',NULL),(2,197,'2026-07-08 10:51:11',NULL),(2,198,'2026-07-08 10:51:11',NULL),(2,199,'2026-07-08 10:51:11',NULL),(2,200,'2026-07-08 10:51:11',NULL),(2,201,'2026-07-08 10:51:11',NULL),(2,202,'2026-07-08 10:51:11',NULL),(2,203,'2026-07-08 10:51:11',NULL),(2,204,'2026-07-08 10:51:11',NULL),(2,205,'2026-07-08 10:51:11',NULL),(2,206,'2026-07-08 10:51:11',NULL),(2,207,'2026-07-08 10:51:11',NULL),(2,208,'2026-07-08 10:51:11',NULL),(2,209,'2026-07-08 10:51:11',NULL),(2,210,'2026-07-08 10:51:11',NULL),(2,211,'2026-07-08 10:51:11',NULL),(2,212,'2026-07-08 10:51:11',NULL),(2,213,'2026-07-08 10:51:11',NULL),(2,214,'2026-07-08 10:51:11',NULL),(2,215,'2026-07-08 10:51:11',NULL),(2,216,'2026-07-08 10:51:11',NULL),(2,217,'2026-07-08 10:51:11',NULL),(2,218,'2026-07-08 10:51:11',NULL),(2,219,'2026-07-08 10:51:11',NULL),(2,221,'2026-07-08 10:51:11',NULL),(2,222,'2026-07-08 10:51:11',NULL),(2,223,'2026-07-08 10:51:11',NULL),(2,225,'2026-07-08 10:51:11',NULL),(2,226,'2026-07-08 10:51:11',NULL),(2,227,'2026-07-08 10:51:11',NULL),(2,228,'2026-07-08 10:51:11',NULL),(2,229,'2026-07-08 10:51:11',NULL),(2,230,'2026-07-08 10:51:11',NULL),(2,231,'2026-07-08 10:51:11',NULL),(2,232,'2026-07-08 10:51:11',NULL),(2,233,'2026-07-08 10:51:11',NULL),(2,234,'2026-07-08 10:51:11',NULL),(2,235,'2026-07-08 10:51:11',NULL),(2,236,'2026-07-08 10:51:11',NULL),(2,237,'2026-07-08 10:51:11',NULL),(2,238,'2026-07-08 10:51:11',NULL),(2,239,'2026-07-08 10:51:11',NULL),(2,240,'2026-07-08 10:51:11',NULL),(2,241,'2026-07-08 10:51:11',NULL),(2,242,'2026-07-08 10:51:11',NULL),(2,244,'2026-07-08 10:51:11',NULL),(2,249,'2026-07-08 10:51:11',NULL),(2,250,'2026-07-08 10:51:11',NULL),(2,251,'2026-07-08 10:51:11',NULL),(2,252,'2026-07-08 10:51:11',NULL),(2,253,'2026-07-08 10:51:11',NULL),(2,254,'2026-07-08 10:51:11',NULL),(2,255,'2026-07-08 10:51:11',NULL),(2,256,'2026-07-08 10:51:11',NULL),(2,257,'2026-07-08 10:51:11',NULL),(2,258,'2026-07-08 10:51:11',NULL),(2,259,'2026-07-08 10:51:11',NULL),(2,260,'2026-07-08 10:51:11',NULL),(2,261,'2026-07-08 10:51:11',NULL),(2,262,'2026-07-08 10:51:11',NULL),(2,268,'2026-07-08 12:12:43',NULL),(2,269,'2026-07-08 12:12:43',NULL),(2,274,'2026-07-09 21:51:35',NULL),(2,275,'2026-07-09 21:51:35',NULL),(2,276,'2026-07-10 00:22:18',NULL),(2,277,'2026-07-10 00:22:18',NULL),(2,278,'2026-07-10 00:22:18',NULL),(2,279,'2026-07-10 00:22:18',NULL),(2,280,'2026-07-10 00:22:18',NULL),(2,281,'2026-07-10 00:22:18',NULL),(2,282,'2026-07-10 00:22:18',NULL),(2,283,'2026-07-10 00:22:18',NULL),(2,284,'2026-07-10 00:22:18',NULL),(2,285,'2026-07-10 00:22:18',NULL),(2,286,'2026-07-10 00:22:18',NULL),(2,287,'2026-07-10 00:22:18',NULL),(2,288,'2026-07-10 00:22:18',NULL),(2,289,'2026-07-10 00:22:18',NULL),(2,290,'2026-07-10 00:22:18',NULL),(2,291,'2026-07-10 00:22:18',NULL),(2,292,'2026-10-25 18:01:09',NULL),(2,293,'2026-10-26 09:09:23',NULL),(2,294,'2026-10-26 09:09:23',NULL),(2,295,'2026-10-26 09:09:23',NULL),(2,296,'2026-10-26 09:09:23',NULL),(2,297,'2026-10-26 09:09:23',NULL),(2,298,'2026-10-26 09:09:23',NULL),(3,1,'2026-07-08 10:51:11',NULL),(3,2,'2026-07-08 10:51:11',NULL),(3,3,'2026-07-08 10:51:11',NULL),(3,9,'2026-07-08 10:51:11',NULL),(3,13,'2026-07-08 10:51:11',NULL),(3,17,'2026-07-08 10:51:11',NULL),(3,22,'2026-07-08 10:51:11',NULL),(3,23,'2026-07-08 10:51:11',NULL),(3,24,'2026-07-08 10:51:11',NULL),(3,26,'2026-07-08 10:51:11',NULL),(3,27,'2026-07-08 10:51:11',NULL),(3,28,'2026-07-08 10:51:11',NULL),(3,30,'2026-07-08 10:51:11',NULL),(3,33,'2026-07-08 10:51:11',NULL),(3,34,'2026-07-08 10:51:11',NULL),(3,35,'2026-07-08 10:51:11',NULL),(3,36,'2026-07-08 10:51:12',NULL),(3,42,'2026-07-08 10:51:12',NULL),(3,43,'2026-07-08 10:51:12',NULL),(3,44,'2026-07-08 10:51:12',NULL),(3,47,'2026-07-08 10:51:12',NULL),(3,48,'2026-07-08 10:51:12',NULL),(3,53,'2026-07-08 10:51:12',NULL),(3,54,'2026-07-08 10:51:12',NULL),(3,57,'2026-07-08 10:51:12',NULL),(3,58,'2026-07-08 10:51:12',NULL),(3,62,'2026-07-08 10:51:12',NULL),(3,63,'2026-07-08 10:51:12',NULL),(3,64,'2026-07-08 10:51:12',NULL),(3,69,'2026-07-08 10:51:12',NULL),(3,91,'2026-07-08 10:51:12',NULL),(3,92,'2026-07-08 10:51:12',NULL),(3,93,'2026-07-08 10:51:12',NULL),(3,95,'2026-07-08 10:51:12',NULL),(3,96,'2026-07-08 10:51:12',NULL),(3,97,'2026-07-08 10:51:12',NULL),(3,98,'2026-07-08 10:51:12',NULL),(3,99,'2026-07-08 10:51:12',NULL),(3,101,'2026-07-08 10:51:12',NULL),(3,102,'2026-07-08 10:51:12',NULL),(3,103,'2026-07-08 10:51:12',NULL),(3,104,'2026-07-08 10:51:12',NULL),(3,105,'2026-07-08 10:51:12',NULL),(3,106,'2026-07-08 10:51:12',NULL),(3,107,'2026-07-08 10:51:12',NULL),(3,108,'2026-07-08 10:51:12',NULL),(3,109,'2026-07-08 10:51:12',NULL),(3,110,'2026-07-08 10:51:12',NULL),(3,111,'2026-07-08 10:51:12',NULL),(3,112,'2026-07-08 10:51:12',NULL),(3,113,'2026-07-08 10:51:12',NULL),(3,114,'2026-07-08 10:51:12',NULL),(3,115,'2026-07-08 10:51:12',NULL),(3,116,'2026-07-08 10:51:12',NULL),(3,117,'2026-07-08 10:51:12',NULL),(3,118,'2026-07-08 10:51:12',NULL),(3,119,'2026-07-08 10:51:12',NULL),(3,120,'2026-07-08 10:51:12',NULL),(3,121,'2026-07-08 10:51:12',NULL),(3,122,'2026-07-08 10:51:12',NULL),(3,123,'2026-07-08 10:51:12',NULL),(3,124,'2026-07-08 10:51:12',NULL),(3,125,'2026-07-08 10:51:12',NULL),(3,126,'2026-07-08 10:51:12',NULL),(3,128,'2026-07-08 10:51:12',NULL),(3,129,'2026-07-08 10:51:12',NULL),(3,134,'2026-07-08 10:51:12',NULL),(3,135,'2026-07-08 10:51:12',NULL),(3,139,'2026-07-08 10:51:12',NULL),(3,140,'2026-07-08 10:51:12',NULL),(3,144,'2026-07-08 10:51:12',NULL),(3,145,'2026-07-08 10:51:12',NULL),(3,151,'2026-07-08 10:51:12',NULL),(3,152,'2026-07-08 10:51:12',NULL),(3,156,'2026-07-08 10:51:12',NULL),(3,157,'2026-07-08 10:51:12',NULL),(3,158,'2026-07-08 10:51:12',NULL),(3,161,'2026-07-08 10:51:12',NULL),(3,162,'2026-07-08 10:51:12',NULL),(3,163,'2026-07-08 10:51:12',NULL),(3,164,'2026-07-08 10:51:12',NULL),(3,166,'2026-07-08 10:51:12',NULL),(3,167,'2026-07-08 10:51:12',NULL),(3,168,'2026-07-08 10:51:12',NULL),(3,169,'2026-07-08 10:51:12',NULL),(3,174,'2026-07-08 10:51:12',NULL),(3,175,'2026-07-08 10:51:12',NULL),(3,176,'2026-07-08 10:51:12',NULL),(3,177,'2026-07-08 10:51:12',NULL),(3,178,'2026-07-08 10:51:12',NULL),(3,180,'2026-07-08 10:51:12',NULL),(3,182,'2026-07-08 10:51:12',NULL),(3,183,'2026-07-08 10:51:12',NULL),(3,184,'2026-07-08 10:51:12',NULL),(3,186,'2026-07-08 10:51:12',NULL),(3,187,'2026-07-08 10:51:12',NULL),(3,188,'2026-07-08 10:51:12',NULL),(3,190,'2026-07-08 10:51:12',NULL),(3,191,'2026-07-08 10:51:12',NULL),(3,192,'2026-07-08 10:51:12',NULL),(3,193,'2026-07-08 10:51:12',NULL),(3,194,'2026-07-08 10:51:12',NULL),(3,195,'2026-07-08 10:51:12',NULL),(3,196,'2026-07-08 10:51:12',NULL),(3,197,'2026-07-08 10:51:12',NULL),(3,200,'2026-07-08 10:51:12',NULL),(3,201,'2026-07-08 10:51:12',NULL),(3,202,'2026-07-08 10:51:12',NULL),(3,203,'2026-07-08 10:51:12',NULL),(3,204,'2026-07-08 10:51:12',NULL),(3,205,'2026-07-08 10:51:12',NULL),(3,206,'2026-07-08 10:51:12',NULL),(3,207,'2026-07-08 10:51:12',NULL),(3,210,'2026-07-08 10:51:12',NULL),(3,211,'2026-07-08 10:51:12',NULL),(3,212,'2026-07-08 10:51:12',NULL),(3,213,'2026-07-08 10:51:12',NULL),(3,214,'2026-07-08 10:51:12',NULL),(3,217,'2026-07-08 10:51:12',NULL),(3,221,'2026-07-08 10:51:12',NULL),(3,222,'2026-07-08 10:51:12',NULL),(3,223,'2026-07-08 10:51:12',NULL),(3,225,'2026-07-08 10:51:12',NULL),(3,227,'2026-07-08 10:51:12',NULL),(3,231,'2026-07-08 10:51:12',NULL),(3,232,'2026-07-08 10:51:12',NULL),(3,233,'2026-07-08 10:51:12',NULL),(3,235,'2026-07-08 10:51:12',NULL),(3,238,'2026-07-08 10:51:12',NULL),(3,240,'2026-07-08 10:51:12',NULL),(3,248,'2026-07-08 10:51:12',NULL),(3,250,'2026-07-08 10:51:12',NULL),(3,252,'2026-07-08 10:51:12',NULL),(3,253,'2026-07-08 10:51:12',NULL),(3,254,'2026-07-08 10:51:12',NULL),(3,256,'2026-07-08 10:51:12',NULL),(3,259,'2026-07-08 10:51:12',NULL),(3,260,'2026-07-08 10:51:12',NULL),(4,1,'2026-07-08 10:51:12',NULL),(4,5,'2026-07-08 10:51:12',NULL),(4,9,'2026-07-08 10:51:12',NULL),(4,13,'2026-07-08 10:51:12',NULL),(4,27,'2026-07-08 10:51:12',NULL),(4,28,'2026-07-08 10:51:12',NULL),(4,29,'2026-07-08 10:51:12',NULL),(4,33,'2026-07-08 10:51:12',NULL),(4,42,'2026-07-08 10:51:12',NULL),(4,43,'2026-07-08 10:51:12',NULL),(4,44,'2026-07-08 10:51:13',NULL),(4,47,'2026-07-08 10:51:13',NULL),(4,50,'2026-07-08 10:51:13',NULL),(4,53,'2026-07-08 10:51:13',NULL),(4,54,'2026-07-08 10:51:13',NULL),(4,55,'2026-07-08 10:51:13',NULL),(4,56,'2026-07-08 10:51:13',NULL),(4,57,'2026-07-08 10:51:13',NULL),(4,59,'2026-07-08 10:51:13',NULL),(4,60,'2026-07-08 10:51:13',NULL),(4,61,'2026-07-12 02:51:47',NULL),(4,62,'2026-07-08 10:51:13',NULL),(4,63,'2026-07-08 10:51:13',NULL),(4,64,'2026-07-08 10:51:13',NULL),(4,65,'2026-07-08 10:51:13',NULL),(4,66,'2026-07-08 10:51:13',NULL),(4,67,'2026-07-08 10:51:13',NULL),(4,69,'2026-07-08 10:51:13',NULL),(4,70,'2026-07-08 10:51:13',NULL),(4,157,'2026-07-08 10:51:13',NULL),(4,161,'2026-07-08 10:51:13',NULL),(4,162,'2026-07-08 10:51:13',NULL),(4,168,'2026-07-08 10:51:13',NULL),(4,169,'2026-07-08 10:51:13',NULL),(4,175,'2026-07-08 10:51:13',NULL),(4,180,'2026-07-08 10:51:13',NULL),(4,182,'2026-07-08 10:51:13',NULL),(4,186,'2026-07-08 10:51:13',NULL),(4,187,'2026-07-08 10:51:13',NULL),(4,197,'2026-07-08 10:51:13',NULL),(4,202,'2026-07-08 10:51:13',NULL),(4,203,'2026-07-08 10:51:13',NULL),(4,210,'2026-07-08 10:51:13',NULL),(4,211,'2026-07-08 10:51:13',NULL),(4,217,'2026-07-08 10:51:13',NULL),(4,219,'2026-07-08 10:51:13',NULL),(4,221,'2026-07-08 10:51:13',NULL),(4,225,'2026-07-08 10:51:13',NULL),(4,227,'2026-07-08 10:51:13',NULL),(4,228,'2026-07-08 10:51:13',NULL),(4,229,'2026-07-08 10:51:13',NULL),(4,230,'2026-07-08 10:51:13',NULL),(4,242,'2026-07-08 10:51:13',NULL),(4,248,'2026-07-08 10:51:13',NULL),(4,250,'2026-07-08 10:51:13',NULL),(4,254,'2026-07-08 10:51:13',NULL),(4,259,'2026-07-08 10:51:13',NULL),(4,260,'2026-07-08 10:51:13',NULL),(5,1,'2026-07-08 10:51:13',NULL),(5,9,'2026-07-08 10:51:13',NULL),(5,13,'2026-07-08 10:51:13',NULL),(5,17,'2026-07-08 10:51:13',NULL),(5,18,'2026-07-08 10:51:13',NULL),(5,19,'2026-07-08 10:51:13',NULL),(5,22,'2026-07-08 10:51:13',NULL),(5,23,'2026-07-08 10:51:13',NULL),(5,24,'2026-07-08 10:51:13',NULL),(5,26,'2026-07-08 10:51:13',NULL),(5,30,'2026-07-08 10:51:13',NULL),(5,33,'2026-07-08 10:51:13',NULL),(5,79,'2026-07-08 10:51:13',NULL),(5,80,'2026-07-08 10:51:13',NULL),(5,81,'2026-07-08 10:51:13',NULL),(5,82,'2026-07-08 10:51:13',NULL),(5,83,'2026-10-26 09:09:23',NULL),(5,85,'2026-10-26 09:09:23',NULL),(5,87,'2026-07-08 10:51:13',NULL),(5,91,'2026-07-08 10:51:13',NULL),(5,92,'2026-07-08 10:51:13',NULL),(5,96,'2026-07-08 10:51:13',NULL),(5,97,'2026-07-08 10:51:13',NULL),(5,98,'2026-07-08 10:51:13',NULL),(5,99,'2026-07-08 10:51:13',NULL),(5,100,'2026-07-08 10:51:13',NULL),(5,101,'2026-07-08 10:51:13',NULL),(5,102,'2026-07-08 10:51:13',NULL),(5,103,'2026-07-08 10:51:13',NULL),(5,105,'2026-07-08 10:51:13',NULL),(5,106,'2026-07-08 10:51:13',NULL),(5,107,'2026-07-08 10:51:13',NULL),(5,108,'2026-07-08 10:51:13',NULL),(5,109,'2026-07-08 10:51:13',NULL),(5,110,'2026-07-08 10:51:13',NULL),(5,112,'2026-07-08 10:51:13',NULL),(5,113,'2026-07-08 10:51:13',NULL),(5,114,'2026-07-08 10:51:13',NULL),(5,115,'2026-07-08 10:51:13',NULL),(5,117,'2026-07-08 10:51:13',NULL),(5,118,'2026-07-08 10:51:13',NULL),(5,119,'2026-07-08 10:51:13',NULL),(5,120,'2026-07-08 10:51:14',NULL),(5,122,'2026-07-08 10:51:14',NULL),(5,123,'2026-07-08 10:51:14',NULL),(5,124,'2026-07-08 10:51:14',NULL),(5,125,'2026-07-08 10:51:14',NULL),(5,128,'2026-07-08 10:51:14',NULL),(5,135,'2026-07-08 10:51:14',NULL),(5,140,'2026-07-08 10:51:14',NULL),(5,145,'2026-07-08 10:51:14',NULL),(5,152,'2026-07-08 10:51:14',NULL),(5,157,'2026-07-08 10:51:14',NULL),(5,162,'2026-07-08 10:51:14',NULL),(5,163,'2026-07-08 10:51:14',NULL),(5,169,'2026-07-08 10:51:14',NULL),(5,175,'2026-07-08 10:51:14',NULL),(5,182,'2026-07-08 10:51:14',NULL),(5,187,'2026-07-08 10:51:14',NULL),(5,190,'2026-07-08 10:51:14',NULL),(5,191,'2026-07-08 10:51:14',NULL),(5,192,'2026-07-08 10:51:14',NULL),(5,196,'2026-07-08 10:51:14',NULL),(5,197,'2026-07-08 10:51:14',NULL),(5,200,'2026-07-08 10:51:14',NULL),(5,202,'2026-07-08 10:51:14',NULL),(5,203,'2026-07-08 10:51:14',NULL),(5,204,'2026-07-08 10:51:14',NULL),(5,210,'2026-07-08 10:51:14',NULL),(5,211,'2026-07-08 10:51:14',NULL),(5,212,'2026-07-08 10:51:14',NULL),(5,213,'2026-07-08 10:51:14',NULL),(5,245,'2026-07-08 10:51:14',NULL),(5,253,'2026-07-08 10:51:14',NULL),(5,256,'2026-07-08 10:51:14',NULL),(5,259,'2026-07-08 10:51:14',NULL),(5,263,'2026-07-08 10:51:13',NULL),(5,264,'2026-07-08 10:51:14',NULL),(6,21,'2026-07-08 10:51:14',NULL),(6,25,'2026-07-08 10:51:14',NULL),(6,33,'2026-07-08 10:51:14',NULL),(6,87,'2026-07-08 10:51:14',NULL),(6,91,'2026-07-08 10:51:14',NULL),(6,95,'2026-07-08 10:51:14',NULL),(6,97,'2026-07-08 10:51:14',NULL),(6,101,'2026-07-08 10:51:14',NULL),(6,104,'2026-07-08 10:51:14',NULL),(6,107,'2026-07-08 10:51:14',NULL),(6,113,'2026-07-08 10:51:14',NULL),(6,118,'2026-07-08 10:51:14',NULL),(6,123,'2026-07-08 10:51:14',NULL),(6,187,'2026-07-08 10:51:14',NULL),(6,202,'2026-07-08 10:51:14',NULL),(6,203,'2026-07-08 10:51:14',NULL),(6,210,'2026-07-08 10:51:14',NULL),(6,211,'2026-07-08 10:51:14',NULL),(6,213,'2026-07-08 10:51:14',NULL),(6,247,'2026-07-08 10:51:14',NULL),(6,263,'2026-07-08 10:51:14',NULL),(6,265,'2026-07-08 10:51:14',NULL),(6,266,'2026-07-08 10:51:14',NULL),(6,267,'2026-07-08 10:51:14',NULL),(7,21,'2026-07-08 10:51:14',NULL),(7,25,'2026-07-08 10:51:14',NULL),(7,33,'2026-07-08 10:51:14',NULL),(7,87,'2026-07-08 10:51:14',NULL),(7,91,'2026-07-08 10:51:14',NULL),(7,97,'2026-07-08 10:51:14',NULL),(7,101,'2026-07-08 10:51:14',NULL),(7,107,'2026-07-08 10:51:14',NULL),(7,113,'2026-07-08 10:51:14',NULL),(7,118,'2026-07-08 10:51:14',NULL),(7,123,'2026-07-08 10:51:14',NULL),(7,187,'2026-07-08 10:51:14',NULL),(7,202,'2026-07-08 10:51:14',NULL),(7,203,'2026-07-08 10:51:14',NULL),(7,210,'2026-07-08 10:51:14',NULL),(7,211,'2026-07-08 10:51:14',NULL),(7,212,'2026-07-08 10:51:14',NULL),(7,213,'2026-07-08 10:51:14',NULL),(7,246,'2026-07-08 10:51:14',NULL),(7,263,'2026-07-08 10:51:14',NULL),(7,267,'2026-07-12 05:42:38',NULL);
/*!40000 ALTER TABLE `etab_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `migrations_log` WRITE;
/*!40000 ALTER TABLE `migrations_log` DISABLE KEYS */;
INSERT INTO `migrations_log` (`id`, `migration`, `name`, `applied_at`, `duration_ms`, `status`, `error`) VALUES (1,'M001','Migration sécurisée notes → schéma V2 (controle_id)','2026-06-30 03:03:42',34,'success',NULL),(2,'M002','Migration sécurisée absences → schéma V2 (session, type, statut_justif)','2026-06-30 03:03:42',2,'success',NULL),(3,'M003','Créer le référentiel annees_scolaires','2026-06-30 03:03:43',74,'success',NULL),(4,'R001','Tables RBAC V2 (rbac_roles, rbac_role_permissions, rbac_user_roles)','2026-06-30 03:03:44',1033,'success',NULL),(5,'S001','Créer la table audit_logs','2026-06-30 03:03:44',75,'success',NULL),(6,'P001','Correction RBAC — bulletins.view pour eleve et parent','2026-06-30 03:25:03',9,'success',NULL),(7,'T001','Tables Platform (etablissements, platform_plans, platform_operators)','2026-07-08 04:13:56',6024,'success',NULL),(8,'T002','Schéma RBAC multi-tenant (etab_roles, etab_permissions, etab_role_permissions)','2026-07-08 04:14:01',2950,'success',NULL),(9,'T003','Tables appartenance multi-établissements (user_etablissements, user_roles_etab)','2026-07-08 04:14:06',2393,'success',NULL),(10,'T004','Tables configuration tenant (etab_settings, etablissement_branding, etablissement_domains)','2026-07-08 04:14:11',2653,'success',NULL),(11,'T005','Seeds fondation tenant (établissement démo, plans, permissions, rôles)','2026-07-08 04:14:20',7120,'success',NULL),(12,'T006','etablissement_id sur tables V1 (users, classes, eleves, professeurs, matieres, enseignements, notes, absences, periodes)','2026-07-08 05:03:13',42269,'success',NULL),(13,'T007','Peuplement RBAC tenant (etab_role_permissions, user_roles_etab)','2026-07-08 09:51:15',9236,'success',NULL),(15,'T008','Seed branding établissement démo (etablissement_branding + etab_settings)','2026-07-08 11:03:32',291,'success',NULL),(16,'T009','Permissions branding.view / branding.update (etab_permissions, etab_role_permissions)','2026-07-08 11:12:43',65,'success',NULL),(17,'T010','users.dernier_etablissement_id (mémorisation du dernier établissement)','2026-07-08 13:04:44',555,'success',NULL),(18,'T011','etablissement_domains.verification_token (challenge DNS)','2026-07-08 14:37:15',3356,'success',NULL),(19,'T012','Permissions domains.view / domains.manage (etab_permissions, etab_role_permissions)','2026-07-08 14:44:51',150,'success',NULL),(20,'T013','api_uploads (ledger stockage) + etablissements.max_documents/max_attachments','2026-07-08 15:08:16',16078,'success',NULL),(21,'T014','Permission quota.view (etab_permissions, etab_role_permissions)','2026-07-08 15:13:31',107,'success',NULL),(22,'T015','jobs (file d\'attente tenant-scopée)','2026-07-08 16:12:56',448,'success',NULL),(23,'T016','Permission monitoring.view (etab_permissions, etab_role_permissions)','2026-07-08 16:20:53',35,'success',NULL),(24,'T017','etablissements.statut+archived, platform_analytics_snapshots, bootstrap super_admin','2026-07-08 16:45:45',1067,'success',NULL),(25,'T018','platform_backups (§19.4) + platform_restores','2026-07-08 17:15:57',1136,'success',NULL),(26,'T019','Nomenclature des classes — système éducatif nigérien','2026-07-09 16:09:03',26,'success',NULL),(27,'T020','Nomenclature des classes — complément Primaire/Collège (Niger)','2026-07-09 17:09:55',62,'success',NULL),(28,'T021','Périodes scolaires — ENUM trimestre uniquement (Niger)','2026-07-09 18:10:18',240,'success',NULL),(29,'T022','Périodes scolaires V2 — schéma + configuration par défaut Niger','2026-07-09 18:51:33',3877,'success',NULL),(30,'T023','finance_modes_paiement — colonne categorie (caisse/banque)','2026-07-09 20:43:06',69,'success',NULL),(32,'T024','finance_modes_paiement — categorie ENUM + \'avoir\' (corrige T023)','2026-07-09 20:45:59',45,'success',NULL),(33,'T025','Permissions settings.general.* (Configuration générale établissement)','2026-07-09 20:51:35',14,'success',NULL),(34,'T026','Paramètres métier ERP — nouvelles permissions + révocation domaines/quotas/monitoring','2026-07-09 23:22:18',532,'success',NULL),(35,'T027','Annonces — ciblage par classe et par utilisateur','2026-07-10 02:26:59',4784,'success',NULL),(36,'T028','Absences — créneau précis de l\'emploi du temps','2026-07-10 06:28:40',1,'success',NULL),(38,'T029','Absences — correctif créneau + unicité par créneau (multi-créneaux/jour)','2026-07-10 06:43:01',132,'success',NULL),(41,'T030','Réinscription — journal des passages de classe','2026-07-10 22:56:34',8237,'success',NULL),(42,'T031','Suppression table morte etablissement_domains','2026-07-10 23:29:51',78,'success',NULL),(43,'T032','Notifications — types retard/discipline/recompense','2026-07-10 23:40:51',67,'success',NULL),(44,'T033','Finance — colonnes de traçabilité migration V1→V2','2026-07-11 19:58:51',3477,'success',NULL),(45,'T034','Matières — colonne categorie (littéraire/scientifique/autre)','2026-07-16 09:57:03',708,'success',NULL),(46,'T035','Modèle par défaut des périodes — semestre (La Persévérance)','2026-07-21 23:52:54',33,'success',NULL),(47,'T036','Matières — classification initiale littéraire/scientifique','2026-07-22 04:46:36',413,'success',NULL),(48,'M007','Ajout eleves.user_id (lien compte utilisateur élève)','2026-07-30 22:37:02',1507,'success',NULL),(50,'T037','Absences — migration des données V1 (type=absence) vers vs_absences V2','2026-08-20 21:58:38',143,'success',NULL),(51,'T038','Emplois du temps — référentiel salles/créneaux V1 vers V2','2026-08-20 23:27:02',60,'success',NULL),(52,'T039','Retards — reliquat V1 (type=retard, table absences) vers vs_retards V2','2026-08-21 00:03:10',12,'success',NULL),(53,'T040','Correction justification absence migrée (donnée réelle depuis justifications V1)','2026-08-21 00:20:29',36,'success',NULL),(54,'T041','Suppression des tables V1 : absences, justifications, emplois_du_temps, salles, creneaux','2026-08-21 00:23:16',1526,'success',NULL),(55,'T042','Matières — socle des 8 matières (Français, Histoire-Géo, Anglais, Maths, S.V.T, Physique-Chimie, Éducation Physique, Conduite)','2026-08-28 03:16:25',260,'success',NULL),(56,'T043','Matières — renommage « Éducation Physique » → « EPS »','2026-09-01 07:54:52',12,'success',NULL),(57,'T044','Restauration table etablissement_domains (régression T031)','2026-10-24 16:45:46',96,'success',NULL),(58,'T045','Retrait bulletins.view aux rôles système parent et eleve','2026-10-24 17:15:16',5,'success',NULL),(59,'T046','Bulletins : token_version et table bulletin_tokens_retired (schéma additif)','2026-10-25 00:19:16',101,'success',NULL),(60,'T048','Matières — marqueur est_conduite (identification de la Conduite)','2026-10-25 17:01:09',259,'success',NULL),(61,'T049','Permission academique.conduite.manage (admin, directeur)','2026-10-25 17:01:09',6,'success',NULL),(62,'T050','Matières — déclencheurs de protection de la Conduite','2026-10-25 17:01:10',55,'success',NULL),(63,'T059','Permissions académiques manquantes (bulletin.admin, evaluations.admin, notes.admin, periodes.admin, moyennes/classement.view)','2026-10-26 08:09:23',61,'success',NULL),(64,'T060','Tables vs_appels, vs_presences, vs_presences_historique (rattrapage du .sql hors runner)','2026-10-26 18:50:17',233,'success',NULL),(65,'T061','Collations utf8mb4_unicode_ci (7 tables) + 3 index redondants','2026-10-26 18:50:18',647,'success',NULL),(66,'T062','Archive puis retire les lignes orphelines (notification_logs, vs_incidents_discipline, finance_journaux_caisse)','2026-10-26 18:50:18',118,'success',NULL);
/*!40000 ALTER TABLE `migrations_log` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

SET FOREIGN_KEY_CHECKS=1;
