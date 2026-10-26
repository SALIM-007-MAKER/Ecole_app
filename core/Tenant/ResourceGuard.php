<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Garde d'appartenance des ressources appelées par identifiant (Lot 3, étapes 2 et 3) — frontière unique, avant le contrôleur.
 *
 * Pour toute requête portant un contexte d'établissement (TenantContext), vérifie que les ressources désignées par
 *   - les paramètres de route ({id}, {eleveId}…), interprétés selon le contrôleur et la méthode,
 *   - les champs de formulaire / de requête dont le nom désigne une ressource (eleve_id, classe_id, salle_id, categorie_id…),
 *   - les clés des tableaux de saisie (presences[eleveId], notes[eleveId]…)
 * appartiennent à l'établissement courant. Une ressource d'un autre établissement — ou sans propriétaire démontré — est traitée
 * comme INEXISTANTE (404, jamais 403 : pas d'oracle d'existence) et l'événement est journalisé.
 *
 * Une ressource qui n'existe pas du tout passe : c'est le contrôleur qui répond « introuvable ».
 * Un référentiel dont la table n'a pas encore la colonne etablissement_id (base non migrée) est partagé : il passe.
 * Toute erreur de résolution (table absente…) refuse la requête (fail-closed).
 *
 * Ce garde complète — il ne remplace pas — les filtres des dépôts : listes, statistiques et exports restent filtrés à la source.
 */
final class ResourceGuard
{
    /** Table → SQL (placeholder unique ?) retournant l'établissement propriétaire, ou null si non applicable (colonne directe). */
    private const PROPRIETAIRE = [
        'eleves'     => 'SELECT etablissement_id FROM eleves WHERE id = ?',
        'classes'    => 'SELECT etablissement_id FROM classes WHERE id = ?',
        'matieres'   => 'SELECT etablissement_id FROM matieres WHERE id = ?',
        'professeurs' => 'SELECT etablissement_id FROM professeurs WHERE id = ?',
        'evaluations' => 'SELECT c.etablissement_id FROM evaluations x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',
        'notes_v2'   => 'SELECT c.etablissement_id FROM notes_v2 n JOIN evaluations x ON x.id = n.evaluation_id JOIN classes c ON c.id = x.classe_id WHERE n.id = ?',
        'vs_absences' => 'SELECT c.etablissement_id FROM vs_absences x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',
        'vs_retards' => 'SELECT c.etablissement_id FROM vs_retards x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',
        'vs_justifications_absences' => 'SELECT c.etablissement_id FROM vs_justifications_absences j JOIN vs_absences x ON x.id = j.absence_id JOIN classes c ON c.id = x.classe_id WHERE j.id = ?',
        'vs_justifications_retards' => 'SELECT c.etablissement_id FROM vs_justifications_retards j JOIN vs_retards x ON x.id = j.retard_id JOIN classes c ON c.id = x.classe_id WHERE j.id = ?',
        'vs_dossiers_discipline' => 'SELECT c.etablissement_id FROM vs_dossiers_discipline x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',
        'vs_incidents_discipline' => 'SELECT c.etablissement_id FROM vs_incidents_discipline i JOIN vs_dossiers_discipline x ON x.id = i.dossier_id JOIN classes c ON c.id = x.classe_id WHERE i.id = ?',
        'vs_sanctions_discipline' => 'SELECT c.etablissement_id FROM vs_sanctions_discipline s JOIN vs_dossiers_discipline x ON x.id = s.dossier_id JOIN classes c ON c.id = x.classe_id WHERE s.id = ?',
        'vs_appels_discipline' => 'SELECT c.etablissement_id FROM vs_appels_discipline a JOIN vs_dossiers_discipline x ON x.id = a.dossier_id JOIN classes c ON c.id = x.classe_id WHERE a.id = ?',
        'vs_recompenses' => 'SELECT c.etablissement_id FROM vs_recompenses x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',
        'vs_emplois_du_temps' => 'SELECT c.etablissement_id FROM vs_emplois_du_temps x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',
        'vs_edt_creneaux' => 'SELECT c.etablissement_id FROM vs_edt_creneaux x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',
        'vs_edt_remplacements' => 'SELECT c.etablissement_id FROM vs_edt_remplacements r JOIN vs_edt_creneaux x ON x.id = r.creneau_id JOIN classes c ON c.id = x.classe_id WHERE r.id = ?',
        'vs_activites' => 'SELECT u.etablissement_id FROM vs_activites x JOIN users u ON u.id = x.cree_par WHERE x.id = ?',
        'vs_activite_inscriptions' => 'SELECT u.etablissement_id FROM vs_activite_inscriptions i JOIN vs_activites x ON x.id = i.activite_id JOIN users u ON u.id = x.cree_par WHERE i.id = ?',
        'vs_appels'  => 'SELECT c.etablissement_id FROM vs_appels x JOIN classes c ON c.id = x.classe_id WHERE x.id = ?',   // séance de présence
        // Finance : propriété par l'élève (factures, paiements, reçus, avoirs, frais V1) ou par l'utilisateur (caisse, décaissements)
        'finance_factures' => 'SELECT e.etablissement_id FROM finance_factures x JOIN eleves e ON e.id = x.eleve_id WHERE x.id = ?',
        'finance_paiements' => 'SELECT e.etablissement_id FROM finance_paiements p JOIN finance_factures x ON x.id = p.facture_id JOIN eleves e ON e.id = x.eleve_id WHERE p.id = ?',
        'finance_recus' => 'SELECT e.etablissement_id FROM finance_recus r JOIN eleves e ON e.id = r.eleve_id WHERE r.id = ?',
        'finance_avoirs' => 'SELECT e.etablissement_id FROM finance_avoirs a JOIN eleves e ON e.id = a.eleve_id WHERE a.id = ?',
        'finance_ecritures' => 'SELECT ex.etablissement_id FROM finance_ecritures x JOIN finance_exercices ex ON ex.id = x.exercice_id WHERE x.id = ?',
        'finance_periodes_comptables' => 'SELECT ex.etablissement_id FROM finance_periodes_comptables x JOIN finance_exercices ex ON ex.id = x.exercice_id WHERE x.id = ?',
        'finance_decaissements' => 'SELECT u.etablissement_id FROM finance_decaissements d JOIN users u ON u.id = d.saisi_par WHERE d.id = ?',
        'finance_sessions_caisse' => 'SELECT u.etablissement_id FROM finance_sessions_caisse s JOIN users u ON u.id = s.caissier_id WHERE s.id = ?',
        'frais_eleves' => 'SELECT e.etablissement_id FROM frais_eleves x JOIN eleves e ON e.id = x.eleve_id WHERE x.id = ?',
        'paiements' => 'SELECT e.etablissement_id FROM paiements x JOIN eleves e ON e.id = x.eleve_id WHERE x.id = ?',
    ];

    /** Référentiels PROPRES à l'établissement : colonne etablissement_id (partagés tant que la colonne est absente). */
    private const REFERENTIELS = [
        'periodes_scolaires', 'types_evaluations', 'periodes_scolaires_config',
        'finance_exercices', 'finance_frais_types', 'finance_categories_frais', 'finance_modes_paiement', 'finance_categories_depenses', 'finance_fournisseurs', 'annonces', 'frais_types',
        'vs_edt_salles', 'vs_edt_plages_horaires', 'vs_motifs_absence', 'vs_discipline_categories', 'vs_recompense_categories', 'vs_activite_categories',
    ];

    /** Nom de champ de requête → table (valable partout sauf surcharge par contrôleur ci-dessous). */
    private const CHAMPS = [
        'eleve_id' => 'eleves', 'classe_id' => 'classes', 'matiere_id' => 'matieres', 'matiere_remplacement_id' => 'matieres',
        'professeur_id' => 'professeurs', 'responsable_id' => 'professeurs',
        'periode_scolaire_id' => 'periodes_scolaires', 'periode_id' => 'periodes_scolaires', 'type_evaluation_id' => 'types_evaluations',
        'salle_id' => 'vs_edt_salles', 'salle_remplacement_id' => 'vs_edt_salles', 'plage_id' => 'vs_edt_plages_horaires',
        'emploi_du_temps_id' => 'vs_emplois_du_temps', 'creneau_id' => 'vs_edt_creneaux',
        'dossier_id' => 'vs_dossiers_discipline', 'incident_id' => 'vs_incidents_discipline', 'sanction_id' => 'vs_sanctions_discipline',
        'facture_id' => 'finance_factures', 'facture_origine_id' => 'finance_factures', 'paiement_id' => 'finance_paiements', 'avoir_id' => 'finance_avoirs',
        'mode_paiement_id' => 'finance_modes_paiement', 'session_id' => 'finance_sessions_caisse', 'frais_eleve_id' => 'frais_eleves',
        'exercice_id' => 'finance_exercices', 'ecriture_id' => 'finance_ecritures',
        'absence_id' => 'vs_absences', 'retard_id' => 'vs_retards', 'activite_id' => 'vs_activites', 'motif_id' => 'vs_motifs_absence',
        'enseignant_id' => 'users', 'remplacant_id' => 'users', 'enseignant_absent_id' => 'users', 'organisateur_id' => 'users', 'parent_id' => 'users',
    ];

    /** Surcharges par contrôleur (nom court) : champs ambigus. */
    private const CHAMPS_PAR_CONTROLEUR = [
        'DisciplineController' => ['categorie_id' => 'vs_discipline_categories'],
        'RewardController'     => ['categorie_id' => 'vs_recompense_categories'],
        'ActivityController'   => ['categorie_id' => 'vs_activite_categories'],
        'EvaluationController' => ['enseignant_id' => 'professeurs'],
        'AppreciationController' => ['professeur_id' => 'professeurs'],
        'EleveController'      => ['parent_id' => 'users', 'user_id' => 'users'],
        'FactureController'    => ['frais_type_id' => 'finance_frais_types', 'frais_type_ids' => 'finance_frais_types', 'fraisTypeIds' => 'finance_frais_types'],
        'FraisController'      => ['categorie_id' => 'finance_categories_frais'],
        'DecaissementController' => ['categorie_id' => 'finance_categories_depenses', 'fournisseur_id' => 'finance_fournisseurs'],
        'FraisEleveController' => ['frais_type_id' => 'frais_types'],
    ];

    /** Tableaux de saisie dont les CLÉS sont des identifiants (presences[eleveId] = …). */
    /** Propriété par jointure sur une colonne ajoutée par migration : tant qu'elle manque, aucune garde (ressource partagée). */
    private const DEPEND_COLONNE = ['finance_ecritures' => 'finance_exercices', 'finance_periodes_comptables' => 'finance_exercices'];

    private const TABLEAUX_PAR_CLE = ['presences' => 'eleves', 'notes' => 'eleves', 'appreciations' => 'eleves', 'eleves' => 'eleves', 'pointages' => 'eleves'];

    /** Paramètres de route par contrôleur : [regex de méthode ou '*'] => [paramètre => table]. */
    private const ROUTES = [
        // Fiches V1 (déjà filtrées par leurs modèles ; garde de frontière en profondeur)
        'EleveController'      => ['*' => ['id' => 'eleves']],
        'ClasseController'     => ['*' => ['id' => 'classes']],
        'MatiereController'    => ['*' => ['id' => 'matieres']],
        'ProfesseurController' => ['*' => ['id' => 'professeurs']],
        'AbsenceController'  => ['*' => ['id' => 'vs_absences']],
        'PresenceController' => ['*' => ['id' => 'vs_appels']],
        'LateController'     => ['*' => ['id' => 'vs_retards']],
        'DisciplineController' => [
            '/^traiterIncident$/' => ['id' => 'vs_incidents_discipline'],
            '/^(validerSanction|leverSanction|appelView|soumettreAppel)$/' => ['id' => 'vs_sanctions_discipline'],
            '/^traiterAppel$/' => ['id' => 'vs_appels_discipline'],
            '*' => ['id' => 'vs_dossiers_discipline'],
        ],
        'RewardController'   => ['*' => ['id' => 'vs_recompenses']],
        'TimetableController' => ['/^(modifierCreneau|supprimerCreneau)$/' => ['id' => 'vs_edt_creneaux'], '*' => ['id' => 'vs_emplois_du_temps']],
        'SalleController'    => ['*' => ['id' => 'vs_edt_salles']],
        'FactureController'  => ['*' => ['id' => 'finance_factures']],
        'PaiementController' => ['/^parFacture$/' => ['factureId' => 'finance_factures'], '*' => ['id' => 'finance_paiements']],
        'FraisController'    => ['/^(updateCategorie|toggleCategorie)$/' => ['id' => 'finance_categories_frais'], '*' => ['id' => 'finance_frais_types']],
        'DecaissementController' => ['/^(updateCategorie|toggleCategorie)$/' => ['id' => 'finance_categories_depenses'], '/^(updateFournisseur|toggleFournisseur)$/' => ['id' => 'finance_fournisseurs'], '*' => ['id' => 'finance_decaissements']],
        'CaisseController'   => ['*' => ['id' => 'finance_sessions_caisse']],
        'ComptabiliteController' => ['/^(showEcriture|extourner)$/' => ['id' => 'finance_ecritures'], '/^(cloturerPeriode)$/' => ['id' => 'finance_periodes_comptables'], '*' => ['id' => 'finance_exercices']],
        'AnnonceController'  => ['*' => ['id' => 'annonces']],
        'PlageHoraireController' => ['*' => ['id' => 'vs_edt_plages_horaires']],
        'ActivityController' => ['/^annulerInscription$/' => ['id' => 'vs_activite_inscriptions'], '*' => ['id' => 'vs_activites']],
        'EvaluationController' => ['*' => ['id' => 'evaluations']],
        'NoteController'     => ['/^(show|update|destroy)$/' => ['id' => 'notes_v2'], '*' => ['id' => 'evaluations']],
        'PeriodeController'  => ['*' => ['id' => 'periodes_scolaires']],
        'TypeEvaluationController' => ['*' => ['id' => 'types_evaluations']],
        'BulletinController' => ['*' => ['eleveId' => 'eleves', 'periodeId' => 'periodes_scolaires']],
        'AppreciationController' => ['*' => ['classeId' => 'classes', 'matiereId' => 'matieres']],
    ];

    /** @var array<string,bool> */
    private static array $colonnes = [];

    /**
     * @param array<string,string> $routeParams paramètres de route
     * @return bool true = requête autorisée à atteindre le contrôleur
     */
    public static function autoriser(string $classeControleur, string $methode, array $routeParams, array $requete): bool
    {
        $tenant = TenantContext::current();
        if ($tenant === null) {
            return true;   // visiteur, CLI, tests hors HTTP : pas de contexte, pas de garde
        }
        $court = ($p = strrpos($classeControleur, '\\')) === false ? $classeControleur : substr($classeControleur, $p + 1);

        try {
            // 1) paramètres de route
            foreach (self::ROUTES[$court] ?? [] as $motif => $params) {
                if ($motif !== '*' && !preg_match($motif, $methode)) { continue; }
                foreach ($params as $nom => $table) {
                    if (isset($routeParams[$nom]) && !self::possede($table, $routeParams[$nom], $tenant)) { return self::refuser($court, $methode, $table, $routeParams[$nom]); }
                }
                break;   // première règle applicable ('*' en dernier)
            }
            // 2) champs de requête
            $champs = (self::CHAMPS_PAR_CONTROLEUR[$court] ?? []) + self::CHAMPS;
            foreach ($requete as $nom => $valeur) {
                if (is_string($nom) && isset($champs[$nom])) {
                    foreach (is_array($valeur) ? $valeur : [$valeur] as $v) {
                        if (!is_array($v) && !self::possede($champs[$nom], $v, $tenant)) { return self::refuser($court, $methode, $champs[$nom], $v); }
                    }
                } elseif (is_string($nom) && isset(self::TABLEAUX_PAR_CLE[$nom]) && is_array($valeur)) {
                    foreach (array_keys($valeur) as $cle) {
                        if (!self::possede(self::TABLEAUX_PAR_CLE[$nom], $cle, $tenant)) { return self::refuser($court, $methode, self::TABLEAUX_PAR_CLE[$nom], $cle); }
                    }
                }
            }
            return true;
        } catch (\Throwable $e) {
            \Core\Logger::security('TENANT_RESOURCE_GUARD_ERROR', $court . '::' . $methode . ' — ' . substr($e->getMessage(), 0, 160));
            return false;   // fail-closed
        }
    }

    /** La ressource appartient-elle à l'établissement ? true si elle n'existe pas (le contrôleur répondra 404) ou si la table est partagée. */
    public static function possede(string $table, mixed $id, int $tenant): bool
    {
        if (!is_scalar($id) || !preg_match('/^\d+$/', (string)$id) || (int)$id <= 0) { return true; }   // vide / non numérique : validation du contrôleur
        $id = (int)$id;
        $pdo = Database::getInstance()->getConnection();

        if ($table === 'users') {
            $stmt = $pdo->prepare('SELECT u.etablissement_id, EXISTS(SELECT 1 FROM user_etablissements ue WHERE ue.user_id = u.id AND ue.etablissement_id = ? AND ue.left_at IS NULL) AS membre FROM users u WHERE u.id = ?');
            $stmt->execute([$tenant, $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false || (int)$row['etablissement_id'] === $tenant || (int)$row['membre'] === 1;
        }
        if (isset(self::DEPEND_COLONNE[$table]) && !self::colonne($pdo, self::DEPEND_COLONNE[$table])) { return true; }   // base non migrée : comptabilité partagée
        if (in_array($table, self::REFERENTIELS, true)) {
            if (!self::colonne($pdo, $table)) { return true; }   // base non migrée : référentiel partagé
            $stmt = $pdo->prepare("SELECT etablissement_id FROM `$table` WHERE id = ?");
        } elseif (isset(self::PROPRIETAIRE[$table])) {
            $stmt = $pdo->prepare(self::PROPRIETAIRE[$table]);
        } else {
            return true;
        }
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row === false) { return true; }   // ressource inexistante
        return $row[0] !== null && (int)$row[0] === $tenant;   // sans propriétaire démontré : refus
    }

    private static function colonne(PDO $pdo, string $table): bool
    {
        return self::$colonnes[$table] ??= TenantScope::colonneEtablissement($table);
    }

    private static function refuser(string $controleur, string $methode, string $table, mixed $id): bool
    {
        \Core\Logger::security('TENANT_RESOURCE_DENIED', sprintf('%s::%s ressource=%s#%s établissement=%s', $controleur, $methode, $table, (string)$id, (string)TenantContext::current()));
        return false;
    }

    /** Réservé aux tests. */
    public static function oublierSchema(): void
    {
        self::$colonnes = [];
    }
}
