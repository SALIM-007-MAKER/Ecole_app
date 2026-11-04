<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Fragments SQL d'isolation par établissement pour les tables qui n'ont PAS (ou pas encore) de colonne etablissement_id.
 *
 * L'établissement courant est celui de TenantContext, positionné pour toute requête authentifiée par SessionTenantGuard.
 * Sans contexte (scripts CLI, tests unitaires hors HTTP, pages publiques) AUCUN filtre n'est ajouté : comportement historique.
 * Toute requête web authentifiée a un contexte (sinon elle a été refusée avant d'atteindre un contrôleur).
 *
 * Propriété des données Académique (Lot 3, étape 2) :
 *   - évaluations                     : par leur classe          (evaluations.classe_id → classes.etablissement_id)
 *   - notes, historique des notes     : par leur évaluation      (→ classe)
 *   - bulletins                       : par leur classe          (bulletins_v2.classe_id)
 *   - périodes, types d'évaluation, configuration des périodes : colonne etablissement_id (migrations T051-T053).
 *     Tant que la migration n'est pas appliquée à une base, la colonne est absente et ces trois référentiels restent
 *     partagés (comportement historique) : colonneEtablissement() le détecte une fois par requête.
 */
final class TenantScope
{
    /** @var array<string,bool> */
    private static array $colonnes = [];

    public static function id(): ?int
    {
        return TenantContext::current();
    }

    /** Restriction « la classe donnée appartient à l'établissement courant » : ['', []] sans contexte. */
    public static function classe(string $colonneClasse, string $connecteur = 'AND'): array
    {
        $id = self::id();
        if ($id === null) { return ['', []]; }
        return [" {$connecteur} {$colonneClasse} IN (SELECT id FROM classes WHERE etablissement_id = ?)", [$id]];
    }

    /** Restriction « l'évaluation donnée appartient (par sa classe) à l'établissement courant ». */
    public static function evaluation(string $colonneEvaluation, string $connecteur = 'AND'): array
    {
        $id = self::id();
        if ($id === null) { return ['', []]; }
        return [" {$connecteur} {$colonneEvaluation} IN (SELECT ev_t.id FROM evaluations ev_t JOIN classes cl_t ON cl_t.id = ev_t.classe_id WHERE cl_t.etablissement_id = ?)", [$id]];
    }

    /** Restriction sur une colonne etablissement_id directe (si la table la porte déjà) : ['', []] sans contexte ou colonne absente. */
    public static function colonne(string $table, string $expression, string $connecteur = 'AND'): array
    {
        $id = self::id();
        if ($id === null || !self::colonneEtablissement($table)) { return ['', []]; }
        return [" {$connecteur} {$expression} = ?", [$id]];
    }

    /**
     * Restriction d'un référentiel PROPRE à l'établissement (périodes, types) : les lignes non rattachées (etablissement_id NULL,
     * propriétaire non démontré) sont invisibles de tous — fail-closed. Sans colonne (base non migrée) : référentiel partagé.
     */
    public static function referentiel(string $table, string $expression, string $connecteur = 'AND'): array
    {
        return self::colonne($table, $expression, $connecteur);
    }

    /** Restriction « la note donnée appartient à l'établissement courant » (via son évaluation) ; vide sans contexte. */
    public static function restrictionNote(string $colonneNote): string
    {
        return self::id() === null ? '' : " AND {$colonneNote} IN (SELECT n_t.id FROM notes_v2 n_t JOIN evaluations ev_t ON ev_t.id = n_t.evaluation_id JOIN classes cl_t ON cl_t.id = ev_t.classe_id WHERE cl_t.etablissement_id = ?)";
    }

    /** @return list<int> paramètre associé à restrictionNote() */
    public static function parametresNote(): array
    {
        return self::id() === null ? [] : [self::id()];
    }

    // ── Conditions à paramètres NOMMÉS (dépôts qui construisent [where, params]) : [fragment, [':tid_scope' => id]] ou null ──

    // ── Fragments SQL LITTÉRAUX (identifiant entier du contexte inliné : aucun paramètre à transmettre). Sans contexte : « 1 = 1 ». ──

    /** « la colonne désigne un élève de l'établissement courant » (propriété des données financières par l'élève). */
    public static function sqlEleve(string $colonneEleve): string
    {
        $t = self::id();
        return $t === null ? '1 = 1' : "{$colonneEleve} IN (SELECT id FROM eleves WHERE etablissement_id = " . (int)$t . ')';
    }

    /**
     * Clé de séquence de numérotation (finance_sequences.type, 20 car.) : inchangée pour l'établissement par défaut / sans contexte
     * (numéros historiques « FAC-2026-0001 »), « <TYPE>-E<id> » pour les autres (« FAC-E2-2026-0001 ») — compteurs séparés et numéros
     * toujours uniques entre établissements (les index uniques sur `numero` sont globaux).
     */
    public static function cleSequence(string $type): string
    {
        $t = self::id();
        if ($t === null) { return $type; }
        static $defaut = null;
        $defaut ??= (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);
        return $t === $defaut ? $type : substr($type, 0, 20 - strlen('-E' . $t)) . '-E' . $t;
    }

    /** « la colonne désigne un exercice comptable de l'établissement courant » (base non migrée / sans contexte : « 1 = 1 »). */
    public static function sqlExercice(string $colonneExercice): string
    {
        $t = self::id();
        return ($t === null || !self::colonneEtablissement('finance_exercices')) ? '1 = 1' : "{$colonneExercice} IN (SELECT ex_t.id FROM finance_exercices ex_t WHERE ex_t.etablissement_id = " . (int)$t . ')';
    }

    /** Ajoute etablissement_id aux données d'un INSERT dans une table de catalogue qui porte la colonne (sinon : inchangé). */
    public static function avecEtab(string $table, array $data): array
    {
        $t = self::id();
        if ($t !== null && !array_key_exists('etablissement_id', $data) && self::colonneEtablissement($table)) {
            $data['etablissement_id'] = $t;
        }
        return $data;
    }

    /** « la colonne désigne une facture d'un élève de l'établissement courant ». */
    public static function sqlFacture(string $colonneFacture): string
    {
        return self::id() === null ? '1 = 1' : "{$colonneFacture} IN (SELECT ff_t.id FROM finance_factures ff_t WHERE " . self::sqlEleve('ff_t.eleve_id') . ')';
    }

    /** « l'utilisateur désigné par la colonne appartient à l'établissement courant » (colonne ou appartenance active). */
    public static function sqlUtilisateur(string $colonneUtilisateur): string
    {
        $t = self::id();
        return $t === null ? '1 = 1' : "{$colonneUtilisateur} IN (SELECT u_t.id FROM users u_t WHERE u_t.etablissement_id = " . (int)$t
            . ' OR EXISTS (SELECT 1 FROM user_etablissements ue_t WHERE ue_t.user_id = u_t.id AND ue_t.etablissement_id = ' . (int)$t . ' AND ue_t.left_at IS NULL))';
    }

    /** Table portant etablissement_id : « $expression = <courant> » ; base non migrée (colonne absente) ou sans contexte : « 1 = 1 ». */
    public static function sqlEtab(string $table, string $expression): string
    {
        $t = self::id();
        return ($t === null || !self::colonneEtablissement($table)) ? '1 = 1' : "{$expression} = " . (int)$t;
    }

    public static function conditionClasse(string $colonneClasse): ?array
    {
        $id = self::id();
        return $id === null ? null : ["{$colonneClasse} IN (SELECT id FROM classes WHERE etablissement_id = :tid_scope)", [':tid_scope' => $id]];
    }

    /** Ressource créée par un utilisateur de l'établissement courant (ex. activités : cree_par). */
    public static function conditionUtilisateur(string $colonneUtilisateur): ?array
    {
        $id = self::id();
        return $id === null ? null : ["{$colonneUtilisateur} IN (SELECT id FROM users WHERE etablissement_id = :tid_scope)", [':tid_scope' => $id]];
    }

    /** Fragment « l'utilisateur $alias appartient à l'établissement courant » (colonne ou appartenance active) ; paramètres via parametresUtilisateur(). */
    public static function utilisateurs(string $alias, string $connecteur = 'AND'): string
    {
        return self::id() === null ? '' : " {$connecteur} ({$alias}.etablissement_id = ? OR EXISTS (SELECT 1 FROM user_etablissements ue_t WHERE ue_t.user_id = {$alias}.id AND ue_t.etablissement_id = ? AND ue_t.left_at IS NULL))";
    }

    /** @return list<int> */
    public static function parametresUtilisateur(): array
    {
        return self::id() === null ? [] : [self::id(), self::id()];
    }

    /** SELECT simple sur une table portant etablissement_id : « $debut [WHERE etablissement_id = ?] $fin » (sans contexte : non filtré). */
    public static function lister(PDO $pdo, string $debut, string $table, string $fin = ''): array
    {
        [$ts, $tp] = self::colonne($table, 'etablissement_id', 'WHERE');
        $stmt = $pdo->prepare($debut . $ts . ' ' . $fin);
        $stmt->execute($tp);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** SQL littéral (identifiant entier du contexte inliné) listant les enseignants de l'établissement courant. */
    public static function sqlEnseignants(): string
    {
        $t = self::id();
        $cond = $t === null ? '' : " AND (etablissement_id = {$t} OR EXISTS (SELECT 1 FROM user_etablissements ue_t WHERE ue_t.user_id = users.id AND ue_t.etablissement_id = {$t} AND ue_t.left_at IS NULL))";
        return "SELECT id, nom, prenom FROM users WHERE role = 'enseignant'" . $cond . ' ORDER BY nom';
    }

    /** Enseignants (utilisateurs de rôle enseignant) de l'établissement courant. */
    public static function enseignants(\PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT u_t.id, u_t.nom, u_t.prenom FROM users u_t WHERE u_t.role = 'enseignant'" . self::utilisateurs('u_t') . ' ORDER BY u_t.nom');
        $stmt->execute(self::parametresUtilisateur());
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Variantes « sans connecteur » pour Core\Model::ownershipRestriction() : [fragment, paramètres] ou null ──

    public static function restrictionReferentiel(string $table, string $expression = 'etablissement_id'): ?array
    {
        $id = self::id();
        return ($id === null || !self::colonneEtablissement($table)) ? null : ["{$expression} = ?", [$id]];
    }

    public static function restrictionClasse(string $colonneClasse): ?array
    {
        $id = self::id();
        return $id === null ? null : ["{$colonneClasse} IN (SELECT id FROM classes WHERE etablissement_id = ?)", [$id]];
    }

    public static function restrictionEvaluation(string $colonneEvaluation): ?array
    {
        $id = self::id();
        return $id === null ? null : ["{$colonneEvaluation} IN (SELECT ev_t.id FROM evaluations ev_t JOIN classes cl_t ON cl_t.id = ev_t.classe_id WHERE cl_t.etablissement_id = ?)", [$id]];
    }

    /** La table porte-t-elle la colonne etablissement_id dans la base courante ? (mémorisé par requête) */
    public static function colonneEtablissement(string $table): bool
    {
        if (!array_key_exists($table, self::$colonnes)) {
            $stmt = Database::getInstance()->getConnection()->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'etablissement_id'"
            );
            $stmt->execute([$table]);
            self::$colonnes[$table] = (int)$stmt->fetchColumn() > 0;
        }
        return self::$colonnes[$table];
    }

    /** Réservé aux tests (changement de base ou migration en cours de processus). */
    public static function oublierSchema(): void
    {
        self::$colonnes = [];
    }

    /** Colonne etablissement_id à écrire à l'insertion d'une ligne d'un référentiel propre (null si la table ne la porte pas). */
    public static function valeurInsertion(string $table): ?int
    {
        return self::colonneEtablissement($table) ? self::id() : null;
    }
}
