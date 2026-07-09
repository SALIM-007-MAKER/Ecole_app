-- ============================================================
-- Migration : rh_007_presences.sql
-- Phase 6.7  : Domaine Présences du personnel — RH V2
-- Auteur     : SCOLARIS V2
-- Date       : 2026-07-02
-- ============================================================
-- Tables : rh_presences, rh_presences_regularisations
-- Compatibilité V1 : zéro collision (préfixe rh_)
-- Soft delete : deleted_at (jamais de DELETE physique)
-- ============================================================

CREATE TABLE IF NOT EXISTS rh_presences (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relations
    employe_id                  INT UNSIGNED NOT NULL          COMMENT 'Employé concerné — FK RESTRICT (présence impossible sans employé)',
    affectation_id              INT UNSIGNED NULL              COMMENT 'Affectation active au moment du pointage — FK SET NULL',

    -- Données de pointage
    date_presence               DATE NOT NULL                  COMMENT 'Date du jour travaillé',
    heure_arrivee               TIME NULL                      COMMENT 'Heure d\'entrée — NULL si absent ou pas encore pointé',
    heure_depart                TIME NULL                      COMMENT 'Heure de sortie — NULL si sortie non encore enregistrée',
    duree_minutes               INT UNSIGNED NULL              COMMENT 'Durée travaillée calculée par AttendanceService (en minutes)',

    -- Statut métier
    statut                      ENUM(
                                    'present',
                                    'absent',
                                    'retard',
                                    'sortie_anticipee',
                                    'mi_temps',
                                    'mission',
                                    'heure_sup'
                                ) NOT NULL DEFAULT 'present'  COMMENT 'Statut de présence du jour',

    -- Mode de pointage
    mode_pointage               ENUM(
                                    'manuel',
                                    'badge',
                                    'qr_code',
                                    'biometrie',
                                    'api_externe'
                                ) NOT NULL DEFAULT 'manuel'   COMMENT 'Mode utilisé pour ce pointage',

    -- Calculs automatiques
    retard_minutes              INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minutes de retard détectées par rapport à heure_reference_arrivee',
    heures_supp_minutes         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minutes en dépassement par rapport à duree_reference_minutes',

    -- Paramètres de référence (personnalisables par pointage)
    heure_reference_arrivee     TIME NOT NULL DEFAULT '08:00:00' COMMENT 'Heure théorique d\'entrée pour le calcul des retards',
    duree_reference_minutes     SMALLINT UNSIGNED NOT NULL DEFAULT 480 COMMENT 'Durée journée théorique en minutes (défaut 8h = 480)',

    -- Informations complémentaires
    motif                       VARCHAR(500) NULL              COMMENT 'Motif renseigné au pointage (mission, formation…)',
    notes                       TEXT NULL                      COMMENT 'Notes libres',

    -- Données source pour badge / biométrie / API
    source_id                   VARCHAR(100) NULL              COMMENT 'Identifiant badge, numéro biométrique ou ID API externe',
    source_data                 JSON NULL                      COMMENT 'Données brutes provenant de la source externe — Préparation V3',

    -- Justification
    justification               TEXT NULL                      COMMENT 'Texte de justification (absence / retard)',
    justifie_par                INT UNSIGNED NULL              COMMENT 'ID utilisateur ayant saisi la justification',
    date_justification          DATETIME NULL,

    -- Validation
    statut_validation           ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
    valide_par                  INT UNSIGNED NULL              COMMENT 'ID utilisateur ayant validé ou rejeté',
    date_validation             DATETIME NULL,
    motif_rejet                 VARCHAR(500) NULL,

    -- Audit
    created_by                  INT UNSIGNED NOT NULL,
    updated_by                  INT UNSIGNED NOT NULL,
    deleted_at                  DATETIME NULL                  COMMENT 'Soft delete — jamais de DELETE physique',
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Contraintes d'intégrité
    CONSTRAINT fk_presence_employe
        FOREIGN KEY (employe_id)
        REFERENCES rh_employes(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_presence_affectation
        FOREIGN KEY (affectation_id)
        REFERENCES rh_affectations(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    -- Index
    INDEX idx_presence_employe_date     (employe_id, date_presence),
    INDEX idx_presence_date             (date_presence),
    INDEX idx_presence_statut           (statut),
    INDEX idx_presence_mode             (mode_pointage),
    INDEX idx_presence_validation       (statut_validation),
    INDEX idx_presence_deleted          (deleted_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Présences journalières du personnel — Phase 6.7 RH V2';


CREATE TABLE IF NOT EXISTS rh_presences_regularisations (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relation
    presence_id                 INT UNSIGNED NOT NULL          COMMENT 'Présence concernée par la régularisation',

    -- Type de régularisation
    type_regularisation         ENUM(
                                    'correction_heure',
                                    'correction_statut',
                                    'justification',
                                    'annulation'
                                ) NOT NULL                    COMMENT 'Nature de la correction apportée',

    -- Diff avant/après
    valeur_ancienne             JSON NULL                      COMMENT 'Snapshot des champs AVANT la régularisation',
    valeur_nouvelle             JSON NULL                      COMMENT 'Snapshot des champs APRÈS la régularisation',

    -- Justification obligatoire
    motif                       VARCHAR(500) NOT NULL          COMMENT 'Motif de la régularisation (obligatoire)',

    -- Qui a effectué la régularisation
    regularise_par              INT UNSIGNED NOT NULL          COMMENT 'ID utilisateur ayant effectué la régularisation',
    regularise_par_nom          VARCHAR(100) NOT NULL          COMMENT 'Nom dénormalisé — conservé même si l\'utilisateur est supprimé',

    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Intégrité
    CONSTRAINT fk_reg_presence
        FOREIGN KEY (presence_id)
        REFERENCES rh_presences(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_reg_presence (presence_id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Régularisations et corrections de présence — Phase 6.7 RH V2';
