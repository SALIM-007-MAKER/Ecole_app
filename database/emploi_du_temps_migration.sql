-- ══════════════════════════════════════════════════════════════════════════════
--  Module Emploi du Temps — Migration SQL
-- ══════════════════════════════════════════════════════════════════════════════

-- Salles (classrooms)
CREATE TABLE IF NOT EXISTS salles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    capacite    SMALLINT UNSIGNED DEFAULT 0,
    type        ENUM('salle_cours','laboratoire','salle_info','gymnase','amphitheatre','autre') DEFAULT 'salle_cours',
    batiment    VARCHAR(50)  DEFAULT NULL,
    description TEXT,
    actif       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Créneaux horaires
CREATE TABLE IF NOT EXISTS creneaux (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(50)  NOT NULL,
    heure_debut TIME         NOT NULL,
    heure_fin   TIME         NOT NULL,
    type        ENUM('cours','pause','recreation','priere') DEFAULT 'cours',
    ordre       TINYINT UNSIGNED DEFAULT 0,
    actif       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emploi du temps principal
CREATE TABLE IF NOT EXISTS emplois_du_temps (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id       INT UNSIGNED NOT NULL,
    matiere_id      INT UNSIGNED NOT NULL,
    professeur_id   INT UNSIGNED NOT NULL,
    salle_id        INT          DEFAULT NULL,
    creneau_id      INT          NOT NULL,
    jour_semaine    TINYINT     NOT NULL COMMENT '1=Lundi 2=Mardi 3=Mercredi 4=Jeudi 5=Vendredi 6=Samedi',
    annee_scolaire  VARCHAR(9)  NOT NULL,
    date_debut      DATE        DEFAULT NULL,
    date_fin        DATE        DEFAULT NULL,
    couleur         VARCHAR(7)  DEFAULT NULL,
    notes           TEXT,
    actif           TINYINT(1)  NOT NULL DEFAULT 1,
    created_by      INT         DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Un cours par créneau par classe
    UNIQUE KEY uk_classe_slot   (classe_id,     creneau_id, jour_semaine, annee_scolaire),
    -- Un enseignant ne peut pas enseigner deux classes en même temps
    UNIQUE KEY uk_prof_slot     (professeur_id, creneau_id, jour_semaine, annee_scolaire),
    CONSTRAINT fk_edt_classe    FOREIGN KEY (classe_id)     REFERENCES classes(id)     ON DELETE CASCADE,
    CONSTRAINT fk_edt_matiere   FOREIGN KEY (matiere_id)    REFERENCES matieres(id)    ON DELETE CASCADE,
    CONSTRAINT fk_edt_prof      FOREIGN KEY (professeur_id) REFERENCES professeurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_edt_salle     FOREIGN KEY (salle_id)      REFERENCES salles(id)      ON DELETE SET NULL,
    CONSTRAINT fk_edt_creneau   FOREIGN KEY (creneau_id)    REFERENCES creneaux(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Créneaux par défaut ───────────────────────────────────────────────────────
INSERT IGNORE INTO creneaux (nom, heure_debut, heure_fin, type, ordre) VALUES
    ('Cours 1',      '08:00:00', '09:00:00', 'cours',     1),
    ('Cours 2',      '09:00:00', '10:00:00', 'cours',     2),
    ('Récréation',   '10:00:00', '10:20:00', 'recreation', 3),
    ('Cours 3',      '10:20:00', '11:20:00', 'cours',     4),
    ('Cours 4',      '11:20:00', '12:20:00', 'cours',     5),
    ('Pause midi',   '12:20:00', '13:30:00', 'pause',     6),
    ('Cours 5',      '13:30:00', '14:30:00', 'cours',     7),
    ('Cours 6',      '14:30:00', '15:30:00', 'cours',     8),
    ('Pause',        '15:30:00', '15:45:00', 'pause',     9),
    ('Cours 7',      '15:45:00', '16:45:00', 'cours',    10),
    ('Cours 8',      '16:45:00', '17:45:00', 'cours',    11);

-- ─── Salles par défaut ─────────────────────────────────────────────────────────
INSERT IGNORE INTO salles (nom, capacite, type, batiment) VALUES
    ('Salle A01',      35, 'salle_cours',   'Bâtiment A'),
    ('Salle A02',      35, 'salle_cours',   'Bâtiment A'),
    ('Salle A03',      35, 'salle_cours',   'Bâtiment A'),
    ('Salle B01',      30, 'salle_cours',   'Bâtiment B'),
    ('Salle B02',      30, 'salle_cours',   'Bâtiment B'),
    ('Labo Sciences',  24, 'laboratoire',   'Bâtiment B'),
    ('Salle Info',     30, 'salle_info',    'Bâtiment C'),
    ('Amphithéâtre',   80, 'amphitheatre',  'Bâtiment A');
