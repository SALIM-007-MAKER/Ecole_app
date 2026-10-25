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
