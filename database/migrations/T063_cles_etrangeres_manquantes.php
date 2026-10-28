<?php

/**
 * T063 — Clés étrangères manquantes (audit BD : colonnes *_id protégées seulement par le code applicatif)
 *
 * Ajoute 47 clés étrangères sur des colonnes qui référencent un enregistrement parent sans contrainte : vs_* (absences, retards, appels,
 * discipline, récompenses, emploi du temps), appreciations_matiere, rh_*, notifications, etablissement_id de plusieurs tables techniques.
 * Chaque clé n'est posée que si : aucune clé n'existe déjà sur la colonne, les types sont identiques, et AUCUNE ligne orpheline n'existe
 * (sinon la migration s'arrête avant de rien poser pour cette colonne et la signale — aucune donnée n'est jamais supprimée ni modifiée).
 *
 * Règle de suppression (alignée sur les clés existantes) : RESTRICT pour une référence obligatoire (le parent ne peut pas disparaître
 * tant que des lignes l'utilisent), SET NULL si la colonne est nullable, CASCADE uniquement pour les données personnelles d'un
 * utilisateur (notifications, préférences, abonnements push, journal d'envoi) et la table de traçabilité T056.
 * Non traité volontairement : vs_activite_historique.user_id (historique à conserver à la suppression d'un compte) et
 * appreciations_matiere.professeur_id (les valeurs existent à la fois comme utilisateurs et comme professeurs : cible non démontrée).
 * ATTENTION : vs_appels.enseignant_id et vs_edt_creneaux.enseignant_id désignent un UTILISATEUR (users.id), pas un professeur.
 *
 * Idempotente. NON APPLIQUÉE À LA BASE RÉELLE sans sauvegarde vérifiée. Retour arrière : retire exactement les clés (et index) posés ici.
 */

$T063_CLES = [
    // table, colonne, parent
    ['api_uploads', 'etablissement_id', 'etablissements'],
    ['appreciations_matiere', 'classe_id', 'classes'],
    ['appreciations_matiere', 'etablissement_id', 'etablissements'],
    ['jobs', 'etablissement_id', 'etablissements'],
    ['notification_logs', 'user_id', 'users', 'CASCADE'],
    ['notification_preferences', 'user_id', 'users', 'CASCADE'],
    ['notifications', 'user_id', 'users', 'CASCADE'],
    ['platform_backups', 'etablissement_id', 'etablissements'],
    ['platform_restores', 'etablissement_id', 'etablissements'],
    ['push_subscriptions', 'user_id', 'users', 'CASCADE'],
    ['reinscriptions', 'etablissement_id', 'etablissements'],
    ['rh_affectation_matieres', 'classe_id', 'classes'],
    ['rh_affectation_matieres', 'matiere_id', 'matieres'],
    ['rh_affectations', 'etablissement_id', 'etablissements'],
    ['rh_contacts_urgence', 'employe_id', 'rh_employes'],
    ['rh_employes', 'professeur_id', 'professeurs'],
    ['rh_employes', 'user_id', 'users'],
    ['t056_parents_rattaches', 'etablissement_id', 'etablissements'],
    ['t056_parents_rattaches', 'user_id', 'users', 'CASCADE'],
    ['vs_absences', 'classe_id', 'classes'],
    ['vs_absences', 'eleve_id', 'eleves'],
    ['vs_activite_inscriptions', 'eleve_id', 'eleves'],
    ['vs_appels', 'classe_id', 'classes'],
    ['vs_appels', 'enseignant_id', 'users'],   // utilisateur (le code joint users), pas professeurs
    ['vs_appels', 'matiere_id', 'matieres'],
    ['vs_appels_discipline', 'dossier_id', 'vs_dossiers_discipline'],
    ['vs_dossiers_discipline', 'classe_id', 'classes'],
    ['vs_dossiers_discipline', 'eleve_id', 'eleves'],
    ['vs_edt_creneaux', 'classe_id', 'classes'],
    ['vs_edt_creneaux', 'enseignant_id', 'users'],   // utilisateur (le code joint users), pas professeurs
    ['vs_edt_creneaux', 'matiere_id', 'matieres'],
    ['vs_edt_creneaux', 'plage_id', 'vs_edt_plages_horaires'],
    ['vs_edt_creneaux', 'salle_id', 'vs_edt_salles'],
    ['vs_emplois_du_temps', 'classe_id', 'classes'],
    ['vs_emplois_du_temps', 'periode_id', 'periodes_scolaires'],
    ['vs_incidents_discipline', 'dossier_id', 'vs_dossiers_discipline'],
    ['vs_incidents_discipline', 'matiere_id', 'matieres'],
    ['vs_justifications_retards', 'retard_id', 'vs_retards'],
    ['vs_presences', 'eleve_id', 'eleves'],
    ['vs_presences_historique', 'appel_id', 'vs_appels'],
    ['vs_presences_historique', 'eleve_id', 'eleves'],
    ['vs_recompenses', 'classe_id', 'classes'],
    ['vs_recompenses', 'eleve_id', 'eleves'],
    ['vs_retards', 'appel_id', 'vs_appels'],
    ['vs_retards', 'classe_id', 'classes'],
    ['vs_retards', 'eleve_id', 'eleves'],
    ['vs_sanctions_discipline', 'dossier_id', 'vs_dossiers_discipline'],
];

$T063_NOM = static function (string $table, string $col): string {
    $n = 'fk63_' . $table . '_' . $col;
    return strlen($n) <= 60 ? $n : substr($n, 0, 50) . '_' . substr(md5($n), 0, 8);
};

return [
    'id'         => 'T063',
    'name'       => 'Clés étrangères manquantes (47 colonnes *_id), sans orphelin, sans suppression de données',
    'reversible' => true,

    'run' => function (PDO $pdo) use ($T063_CLES, $T063_NOM): void {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        // Alias en minuscules : MySQL 8 renvoie les colonnes d'information_schema en MAJUSCULES selon le client (lanceur ≠ tests).
        $info = $pdo->prepare('SELECT column_type AS column_type, is_nullable AS is_nullable FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
        $existe = $pdo->prepare('SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL');
        $pose = 0; $deja = 0; $ignore = [];
        foreach ($T063_CLES as $c) {
            [$t, $col, $parent] = $c;
            $regle = $c[3] ?? null;
            $info->execute([$db, $t, $col]); $enfant = $info->fetch(PDO::FETCH_ASSOC);
            $info->execute([$db, $parent, 'id']); $pk = $info->fetch(PDO::FETCH_ASSOC);
            if (!$enfant || !$pk) { $ignore[] = "$t.$col (table ou colonne absente)"; continue; }
            $existe->execute([$db, $t, $col]);
            if ((int)$existe->fetchColumn() > 0) { $deja++; continue; }
            if ($enfant['column_type'] !== $pk['column_type']) { $ignore[] = "$t.$col (types différents : {$enfant['column_type']} / {$pk['column_type']})"; continue; }
            $orph = (int)$pdo->query("SELECT COUNT(*) FROM `$t` a WHERE a.`$col` IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `$parent` b WHERE b.id = a.`$col`)")->fetchColumn();
            if ($orph > 0) { $ignore[] = "$t.$col ($orph ligne(s) orpheline(s) : à traiter avant)"; continue; }
            $regle = $regle ?? ($enfant['is_nullable'] === 'YES' ? 'SET NULL' : 'RESTRICT');
            $pdo->exec("ALTER TABLE `$t` ADD CONSTRAINT `" . $T063_NOM($t, $col) . "` FOREIGN KEY (`$col`) REFERENCES `$parent` (`id`) ON DELETE $regle");
            $pose++;
        }
        echo "    T063 : $pose clé(s) posée(s), $deja déjà présente(s), " . count($ignore) . " ignorée(s)\n";
        foreach ($ignore as $i) { echo "      ignorée : $i\n"; }
    },

    'rollback' => function (PDO $pdo) use ($T063_CLES, $T063_NOM): void {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $fk = $pdo->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = \'FOREIGN KEY\'');
        $ix = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?');
        foreach ($T063_CLES as $c) {
            $nom = $T063_NOM($c[0], $c[1]);
            $fk->execute([$db, $c[0], $nom]);
            if ((int)$fk->fetchColumn() > 0) { $pdo->exec("ALTER TABLE `{$c[0]}` DROP FOREIGN KEY `$nom`"); }
            $ix->execute([$db, $c[0], $nom]);
            if ((int)$ix->fetchColumn() > 0) { $pdo->exec("ALTER TABLE `{$c[0]}` DROP INDEX `$nom`"); }
        }
    },
];
