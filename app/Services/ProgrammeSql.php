<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Fragments SQL pour lire le coefficient et le volume horaire D'UNE MATIÈRE DANS UNE CLASSE (programme de la classe).
 *
 * Valeur effective = celle du programme de la classe (classe_matieres) si la matière y figure, sinon celle de la matière
 * (matieres.coefficient / volume_horaire, valeurs par défaut). Une classe sans programme donne donc EXACTEMENT les résultats
 * d'avant ; seule une ligne de programme change la valeur, pour cette classe uniquement.
 *
 * Tant que la table classe_matieres n'existe pas (migration T075 non appliquée), les fragments reviennent aux colonnes de la matière :
 * les requêtes restent valides (une requête qui référence une table absente ferait échouer — ou, dans les dépôts qui absorbent les
 * erreurs, renverrait silencieusement une liste vide).
 *
 * Utilisation :
 *   "SELECT " . ProgrammeSql::coefficient('m') . " AS coeff_matiere ... JOIN matieres m ON m.id = ev.matiere_id" . ProgrammeSql::jointure('ev.classe_id', 'm') . " ..."
 */
final class ProgrammeSql
{
    /** Alias de la table du programme dans les requêtes (évite les collisions avec les alias existants). */
    public const ALIAS = 'cmp';

    /** @var array<string,bool> */
    private static array $colonnes = [];

    public static function actif(): bool
    {
        return ProgrammeClasseService::disponible();
    }

    /**
     * Expression SQL du coefficient COMPTÉ dans les moyennes générales et les classements ($matiere = alias de la table matieres).
     * Une matière FACULTATIVE (classe_matieres.obligatoire = 0) compte pour 0 : elle garde sa propre moyenne mais n'a aucun poids dans la
     * moyenne générale ni dans le classement. À utiliser dans tous les calculs ; pour AFFICHER le coefficient, voir coefficientDeclare().
     */
    public static function coefficient(string $matiere = 'm', string $alias = self::ALIAS): string
    {
        if (!self::actif()) {
            return "{$matiere}.coefficient";
        }
        return "CASE WHEN {$alias}.obligatoire = 0 THEN 0 ELSE COALESCE({$alias}.coefficient, {$matiere}.coefficient) END";
    }

    /** Expression SQL du coefficient DÉCLARÉ (valeur du programme, ou de la matière) — pour l'affichage, indépendamment du caractère facultatif. */
    public static function coefficientDeclare(string $matiere = 'm', string $alias = self::ALIAS): string
    {
        return self::actif() ? "COALESCE({$alias}.coefficient, {$matiere}.coefficient)" : "{$matiere}.coefficient";
    }

    /** Expression SQL « matière facultative dans cette classe » (0 ou 1) ; toujours 0 sans programme. */
    public static function facultative(string $alias = self::ALIAS): string
    {
        return self::actif() ? "COALESCE(1 - {$alias}.obligatoire, 0)" : '0';
    }

    /**
     * Expression SQL du libellé à AFFICHER pour une matière : son nom d'affichage s'il est défini (T076), sinon son nom de référence.
     * Le nom de référence (`nom`) reste la clé fonctionnelle : unicité, imports, recherches.
     */
    public static function nom(string $matiere = 'm'): string
    {
        return self::colonneMatiere('nom_affichage') ? "COALESCE(NULLIF(TRIM({$matiere}.nom_affichage), ''), {$matiere}.nom)" : "{$matiere}.nom";
    }

    /** La colonne existe-t-elle dans `matieres` (migration T076 appliquée) ? */
    public static function colonneMatiere(string $colonne): bool
    {
        if (!isset(self::$colonnes[$colonne])) {
            $s = \Core\Database::getInstance()->getConnection()->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matieres' AND COLUMN_NAME = ?"
            );
            $s->execute([$colonne]);
            self::$colonnes[$colonne] = (int)$s->fetchColumn() > 0;
        }
        return self::$colonnes[$colonne];
    }

    /** Pour les tests : oublie les détections mémorisées. */
    public static function oublier(): void
    {
        self::$colonnes = [];
    }

    /** Expression SQL du volume horaire effectif. */
    public static function volume(string $matiere = 'm', string $alias = self::ALIAS): string
    {
        return self::actif() ? "COALESCE({$alias}.volume_horaire, {$matiere}.volume_horaire)" : "{$matiere}.volume_horaire";
    }

    /**
     * Jointure sur le programme de la classe ($classe = expression SQL de l'identifiant de classe : « ev.classe_id », « e.classe_id »,
     * « :classe_prog »…). Chaîne vide tant que la table n'existe pas. LEFT JOIN : une matière sans ligne de programme reste dans le résultat.
     */
    public static function jointure(string $classe, string $matiere = 'm', string $alias = self::ALIAS): string
    {
        return self::actif() ? " LEFT JOIN classe_matieres {$alias} ON {$alias}.classe_id = {$classe} AND {$alias}.matiere_id = {$matiere}.id " : ' ';
    }
}
