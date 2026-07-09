-- ============================================================
-- COMMUNICATION V2 — Migration com_001
-- Phase 8.2 — 2026-07-03
-- 13 tables : com_notifications, com_threads, com_thread_messages,
--             com_thread_participants, com_templates, com_queue,
--             com_logs, com_groupes, com_groupe_membres,
--             com_preferences, com_push_tokens, com_campagnes,
--             com_campagne_destinataires
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Notifications internes (inbox) ───────────────────────
CREATE TABLE IF NOT EXISTS com_notifications (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    type             VARCHAR(60)  NOT NULL DEFAULT 'info',
    module_source    VARCHAR(30)  NOT NULL,
    entite_type      VARCHAR(60)  NULL,
    entite_id        INT UNSIGNED NULL,
    titre            VARCHAR(255) NOT NULL,
    corps            TEXT         NULL,
    url_action       VARCHAR(500) NULL,
    icone            VARCHAR(60)  NOT NULL DEFAULT 'bell',
    priorite         ENUM('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
    lu               TINYINT(1)   NOT NULL DEFAULT 0,
    lu_at            DATETIME     NULL,
    expire_at        DATETIME     NULL,
    metadata         JSON         NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user       (user_id, lu, created_at DESC),
    INDEX idx_module     (module_source, entite_type, entite_id),
    INDEX idx_etab       (etablissement_id),
    INDEX idx_expire     (expire_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Fils de conversation ─────────────────────────────────
CREATE TABLE IF NOT EXISTS com_threads (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sujet               VARCHAR(255) NOT NULL,
    type                ENUM('direct','groupe','broadcast') NOT NULL DEFAULT 'direct',
    module_source       VARCHAR(30)  NULL,
    entite_type         VARCHAR(60)  NULL,
    entite_id           INT UNSIGNED NULL,
    created_by          INT UNSIGNED NOT NULL,
    archive             TINYINT(1)   NOT NULL DEFAULT 0,
    dernier_message_at  DATETIME     NULL,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at          DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_creator (created_by),
    INDEX idx_etab    (etablissement_id),
    INDEX idx_module  (module_source, entite_type, entite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Messages dans un fil ─────────────────────────────────
CREATE TABLE IF NOT EXISTS com_thread_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    corps       TEXT         NOT NULL,
    type        ENUM('texte','fichier','systeme') NOT NULL DEFAULT 'texte',
    metadata    JSON         NULL,
    deleted_at  DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_tm_thread (thread_id) REFERENCES com_threads(id) ON DELETE CASCADE,
    INDEX idx_thread (thread_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Participants à un fil ────────────────────────────────
CREATE TABLE IF NOT EXISTS com_thread_participants (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    role      ENUM('membre','moderateur') NOT NULL DEFAULT 'membre',
    lu_at     DATETIME     NULL,
    archive   TINYINT(1)   NOT NULL DEFAULT 0,
    joined_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_tp_thread (thread_id) REFERENCES com_threads(id) ON DELETE CASCADE,
    UNIQUE KEY uq_thread_user (thread_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Templates de messages ────────────────────────────────
CREATE TABLE IF NOT EXISTS com_templates (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code             VARCHAR(100) NOT NULL,
    nom              VARCHAR(200) NOT NULL,
    canal            ENUM('email','sms','push','internal') NOT NULL,
    langue           CHAR(2)      NOT NULL DEFAULT 'fr',
    module_source    VARCHAR(30)  NULL,
    sujet            VARCHAR(255) NULL,
    corps_html       MEDIUMTEXT   NULL,
    corps_texte      TEXT         NULL,
    variables        JSON         NULL,
    actif            TINYINT(1)   NOT NULL DEFAULT 1,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at       DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_code_canal_lang (code, canal, langue, etablissement_id),
    INDEX idx_module (module_source),
    INDEX idx_canal  (canal, actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. File d'attente asynchrone ────────────────────────────
CREATE TABLE IF NOT EXISTS com_queue (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    canal               ENUM('email','sms','push') NOT NULL,
    type                VARCHAR(60)  NOT NULL,
    user_id             INT UNSIGNED NULL,
    destinataire_email  VARCHAR(150) NULL,
    destinataire_tel    VARCHAR(20)  NULL,
    push_token          VARCHAR(512) NULL,
    sujet               VARCHAR(255) NULL,
    corps               MEDIUMTEXT   NULL,
    template_id         INT UNSIGNED NULL,
    variables_json      JSON         NULL,
    statut              ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    priorite            ENUM('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
    tentatives          TINYINT      NOT NULL DEFAULT 0,
    max_tentatives      TINYINT      NOT NULL DEFAULT 3,
    planifie_at         DATETIME     NULL,
    traite_at           DATETIME     NULL,
    prochain_essai_at   DATETIME     NULL,
    erreur              TEXT         NULL,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pending   (statut, priorite, planifie_at),
    INDEX idx_retry     (statut, prochain_essai_at),
    INDEX idx_user      (user_id),
    INDEX idx_etab      (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Journal de livraison ─────────────────────────────────
CREATE TABLE IF NOT EXISTS com_logs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id         INT UNSIGNED NULL,
    canal            ENUM('email','sms','push','internal') NOT NULL,
    type             VARCHAR(60)  NOT NULL,
    user_id          INT UNSIGNED NULL,
    destinataire     VARCHAR(200) NULL,
    sujet            VARCHAR(255) NULL,
    statut           ENUM('sent','failed','read','bounced','unsubscribed') NOT NULL,
    module_source    VARCHAR(30)  NULL,
    entite_type      VARCHAR(60)  NULL,
    entite_id        INT UNSIGNED NULL,
    metadata         JSON         NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_canal  (canal, statut, created_at DESC),
    INDEX idx_user   (user_id),
    INDEX idx_module (module_source, entite_type, entite_id),
    INDEX idx_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. Groupes de diffusion ─────────────────────────────────
CREATE TABLE IF NOT EXISTS com_groupes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(150) NOT NULL,
    description      TEXT         NULL,
    type             ENUM('manuel','role','classe','niveau','etablissement') NOT NULL DEFAULT 'manuel',
    criteres         JSON         NULL,
    actif            TINYINT(1)   NOT NULL DEFAULT 1,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at       DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type, actif),
    INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. Membres des groupes (groupes manuels) ─────────────────
CREATE TABLE IF NOT EXISTS com_groupe_membres (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    groupe_id INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    added_by  INT UNSIGNED NOT NULL DEFAULT 0,
    added_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_gm_groupe (groupe_id) REFERENCES com_groupes(id) ON DELETE CASCADE,
    UNIQUE KEY uq_groupe_user (groupe_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. Préférences de notification utilisateur ───────────────
CREATE TABLE IF NOT EXISTS com_preferences (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    type_notification VARCHAR(60)  NOT NULL,
    canal_internal    TINYINT(1)   NOT NULL DEFAULT 1,
    canal_email       TINYINT(1)   NOT NULL DEFAULT 1,
    canal_sms         TINYINT(1)   NOT NULL DEFAULT 0,
    canal_push        TINYINT(1)   NOT NULL DEFAULT 1,
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_type (user_id, type_notification, etablissement_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 11. Tokens push (PWA) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS com_push_tokens (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    token        VARCHAR(512) NOT NULL,
    plateforme   ENUM('web','android','ios') NOT NULL DEFAULT 'web',
    actif        TINYINT(1)   NOT NULL DEFAULT 1,
    last_used_at DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, actif),
    UNIQUE KEY uq_token (token(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 12. Campagnes de communication masse ─────────────────────
CREATE TABLE IF NOT EXISTS com_campagnes (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                  VARCHAR(200) NOT NULL,
    description          TEXT         NULL,
    template_id          INT UNSIGNED NULL,
    canaux               JSON         NOT NULL,
    cible_type           ENUM('groupe','role','classe','niveau','tous') NOT NULL,
    cible_id             INT UNSIGNED NULL,
    statut               ENUM('brouillon','planifiee','en_cours','terminee','annulee') NOT NULL DEFAULT 'brouillon',
    total_destinataires  INT UNSIGNED NOT NULL DEFAULT 0,
    total_envoyes        INT UNSIGNED NOT NULL DEFAULT 0,
    total_echecs         INT UNSIGNED NOT NULL DEFAULT 0,
    planifie_at          DATETIME     NULL,
    lance_at             DATETIME     NULL,
    termine_at           DATETIME     NULL,
    variables_json       JSON         NULL,
    etablissement_id     INT UNSIGNED NOT NULL DEFAULT 1,
    created_by           INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at           DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_statut (statut, planifie_at),
    INDEX idx_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 13. Destinataires de campagne ────────────────────────────
CREATE TABLE IF NOT EXISTS com_campagne_destinataires (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    statut      ENUM('pending','sent','failed','read') NOT NULL DEFAULT 'pending',
    canal       ENUM('email','sms','push','internal')  NOT NULL,
    envoi_at    DATETIME NULL,
    lu_at       DATETIME NULL,
    erreur      TEXT     NULL,
    FOREIGN KEY fk_cd_camp (campagne_id) REFERENCES com_campagnes(id) ON DELETE CASCADE,
    INDEX idx_campagne (campagne_id, statut),
    INDEX idx_user     (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seeds — Templates par défaut ─────────────────────────────
INSERT IGNORE INTO com_templates
    (code, nom, canal, langue, module_source, sujet, corps_html, corps_texte, variables, actif, created_by)
VALUES
-- Bulletin publié — internal
('bulletin_publie', 'Bulletin disponible', 'internal', 'fr', 'academique',
 NULL, NULL,
 'Le bulletin de {{nom_eleve}} pour la période {{periode}} est disponible. Mention : {{mention}}.',
 '["nom_eleve","periode","mention","moyenne","url_bulletin"]', 1, 0),

-- Bulletin publié — email
('bulletin_publie', 'Bulletin disponible', 'email', 'fr', 'academique',
 'Bulletin scolaire de {{nom_eleve}} — {{periode}}',
 '<h2>Bonjour {{prenom}},</h2><p>Le bulletin de <strong>{{nom_eleve}}</strong> pour la période <strong>{{periode}}</strong> est disponible.</p><p>Moyenne générale : <strong>{{moyenne}}/20</strong> — Mention : <strong>{{mention}}</strong></p><p><a href="{{url_bulletin}}">Consulter le bulletin</a></p>',
 'Bonjour {{prenom}}, le bulletin de {{nom_eleve}} pour {{periode}} est disponible. Moyenne : {{moyenne}}/20.',
 '["nom_eleve","periode","mention","moyenne","url_bulletin","prenom"]', 1, 0),

-- Absence élève — internal
('absence_eleve', 'Absence signalée', 'internal', 'fr', 'vie_scolaire',
 NULL, NULL, 'Absence signalée pour {{nom_eleve}} le {{date}} ({{matiere}}).',
 '["nom_eleve","date","matiere","classe"]', 1, 0),

-- Absence élève — SMS
('absence_eleve', 'Absence signalée', 'sms', 'fr', 'vie_scolaire',
 NULL, NULL, 'SCOLARIS: {{nom_eleve}} absent(e) le {{date}}. Contactez l\'école.',
 '["nom_eleve","date"]', 1, 0),

-- Absence élève — push
('absence_eleve', 'Absence signalée', 'push', 'fr', 'vie_scolaire',
 'Absence signalée', NULL, '{{nom_eleve}} est absent(e) le {{date}}.',
 '["nom_eleve","date"]', 1, 0),

-- Facture émise — internal
('facture_emise', 'Nouvelle facture', 'internal', 'fr', 'finance',
 NULL, NULL, 'Une nouvelle facture de {{montant}} DH a été émise. Référence : {{reference}}.',
 '["montant","reference","echeance"]', 1, 0),

-- Facture émise — email
('facture_emise', 'Nouvelle facture', 'email', 'fr', 'finance',
 'Facture {{reference}} — {{montant}} DH',
 '<h2>Bonjour {{prenom}},</h2><p>Une nouvelle facture a été émise à votre nom.</p><p>Montant : <strong>{{montant}} DH</strong><br>Référence : {{reference}}<br>Échéance : {{echeance}}</p>',
 'Bonjour {{prenom}}, facture {{reference}} de {{montant}} DH émise. Échéance : {{echeance}}.',
 '["prenom","montant","reference","echeance"]', 1, 0),

-- Paiement reçu — internal
('paiement_recu', 'Paiement confirmé', 'internal', 'fr', 'finance',
 NULL, NULL, 'Votre paiement de {{montant}} DH a bien été reçu. Reçu n° {{recu}}.',
 '["montant","recu","date"]', 1, 0),

-- Paiement reçu — email
('paiement_recu', 'Paiement confirmé', 'email', 'fr', 'finance',
 'Confirmation de paiement — Reçu {{recu}}',
 '<h2>Paiement reçu</h2><p>Bonjour {{prenom}},</p><p>Nous confirmons la réception de votre paiement de <strong>{{montant}} DH</strong>.<br>Reçu n° : {{recu}}</p>',
 'Paiement de {{montant}} DH confirmé. Reçu n° {{recu}}.',
 '["prenom","montant","recu","date"]', 1, 0),

-- Congé approuvé — internal
('conge_approuve', 'Congé approuvé', 'internal', 'fr', 'rh',
 NULL, NULL, 'Votre demande de congé du {{date_debut}} au {{date_fin}} a été approuvée.',
 '["date_debut","date_fin","type_conge"]', 1, 0),

-- Congé approuvé — push
('conge_approuve', 'Congé approuvé', 'push', 'fr', 'rh',
 'Congé approuvé', NULL, 'Votre congé du {{date_debut}} au {{date_fin}} est approuvé.',
 '["date_debut","date_fin"]', 1, 0),

-- Congé refusé — internal
('conge_rejete', 'Congé refusé', 'internal', 'fr', 'rh',
 NULL, NULL, 'Votre demande de congé a été refusée. Motif : {{motif}}.',
 '["date_debut","date_fin","motif"]', 1, 0),

-- Document partagé — internal
('document_partage', 'Document partagé', 'internal', 'fr', 'documents',
 NULL, NULL, '{{expediteur}} a partagé le document "{{titre}}" avec vous.',
 '["expediteur","titre","url_document"]', 1, 0),

-- Document partagé — email
('document_partage', 'Document partagé', 'email', 'fr', 'documents',
 'Un document a été partagé avec vous : {{titre}}',
 '<h2>Document partagé</h2><p>Bonjour {{prenom}},</p><p><strong>{{expediteur}}</strong> a partagé le document "<strong>{{titre}}</strong>" avec vous.</p><p><a href="{{url_document}}">Accéder au document</a></p>',
 '{{expediteur}} a partagé "{{titre}}" avec vous. Accès : {{url_document}}',
 '["prenom","expediteur","titre","url_document"]', 1, 0),

-- Signature requise — email
('signature_requise', 'Signature électronique requise', 'email', 'fr', 'documents',
 'Action requise : signature du document {{titre}}',
 '<h2>Signature requise</h2><p>Bonjour {{prenom}},</p><p>Votre signature est requise sur le document "<strong>{{titre}}</strong>".</p><p><a href="{{url_signature}}">Signer le document</a></p><p>Expire le : {{expire_at}}</p>',
 'Signature requise sur "{{titre}}". Signez ici : {{url_signature}} (expire le {{expire_at}}).',
 '["prenom","titre","url_signature","expire_at"]', 1, 0),

-- Activité annulée — internal
('activite_annulee', 'Activité annulée', 'internal', 'fr', 'vie_scolaire',
 NULL, NULL, 'L\'activité "{{titre}}" prévue le {{date}} a été annulée. Motif : {{motif}}.',
 '["titre","date","motif"]', 1, 0),

-- Formation disponible — internal
('formation_disponible', 'Nouvelle formation', 'internal', 'fr', 'rh',
 NULL, NULL, 'Une nouvelle formation "{{titre}}" est disponible. Places : {{places_restantes}}.',
 '["titre","places_restantes","date_debut"]', 1, 0),

-- Contrat expirant — internal
('contrat_expire', 'Contrat arrivant à échéance', 'internal', 'fr', 'rh',
 NULL, NULL, 'Votre contrat expire le {{date_fin}}. Contactez les RH pour le renouvellement.',
 '["date_fin","type_contrat"]', 1, 0),

-- Emploi du temps publié — internal
('emploi_du_temps', 'Emploi du temps publié', 'internal', 'fr', 'vie_scolaire',
 NULL, NULL, 'L\'emploi du temps de {{classe}} a été mis à jour pour la semaine du {{semaine}}.',
 '["classe","semaine"]', 1, 0);
