<?php

declare(strict_types=1);

namespace Core\Tenant;

use PDO;

/**
 * Contexte d'établissement (tenant) d'une requête AUTHENTIFIÉE, déduit de la session et revérifié en base.
 *
 * Avant : TenantContext n'était jamais positionné ; les modèles « scopés » retombaient silencieusement sur
 * l'établissement par défaut (config/tenant.php['default_id'], donc l'établissement 1), si bien qu'un administrateur d'un
 * autre établissement voyait et modifiait les données de l'établissement 1.
 *
 * Règle (fail-closed) — l'établissement de la session est accepté si, en base :
 *   1. l'utilisateur existe et son compte est actif ;
 *   2. l'établissement existe, n'est pas supprimé et n'est ni suspendu, ni annulé, ni archivé ;
 *   3. l'utilisateur en est membre actif (user_etablissements, left_at IS NULL) ;
 *      OU — repli V1 identique à celui de la connexion (AuthController) — il n'a JAMAIS eu d'appartenance enregistrée (même
 *      quittée : un ancien membre ne retrouve pas l'accès par ce repli) et
 *      users.etablissement_id désigne exactement cet établissement (les parents créés avant user_etablissements).
 * Sinon : aucun établissement n'est choisi « par défaut » ; la session est invalidée et l'accès refusé.
 *
 * Comptes plateforme (Super-Admin SaaS) : règle DISTINCTE. Ils s'authentifient par PlatformAuth (clé de session
 * `_platform_operator`, jamais `_auth_user`) et opèrent par construction sur plusieurs établissements : les routes /platform/*
 * ne passent pas par ce garde (voir routeSansContexte()) et n'alimentent jamais TenantContext.
 *
 * Requêtes sans utilisateur connecté (pages publiques, connexion) : aucun contexte n'est positionné (comportement inchangé,
 * les modèles scopés gardent leur repli historique — limite documentée du Lot 3).
 */
final class SessionTenantGuard
{
    public const RAISON_OK                    = 'ok';
    public const RAISON_ANONYME               = 'anonyme';
    public const RAISON_UTILISATEUR_INVALIDE  = 'utilisateur_invalide';
    public const RAISON_COMPTE_DESACTIVE      = 'compte_desactive';
    public const RAISON_ETAB_ABSENT           = 'etablissement_absent';
    public const RAISON_ETAB_INTROUVABLE      = 'etablissement_introuvable';
    public const RAISON_ETAB_INDISPONIBLE     = 'etablissement_indisponible';
    public const RAISON_NON_MEMBRE            = 'non_membre';

    private const STATUTS_BLOQUES = ['suspended', 'cancelled', 'archived'];

    private PDO $pdo;
    private TenantRepository $repo;

    public function __construct(PDO $pdo, ?TenantRepository $repo = null)
    {
        $this->pdo  = $pdo;
        $this->repo = $repo ?? new TenantRepository($pdo);
    }

    /**
     * @param array|null $sessionUser contenu de la clé de session `_auth_user` (null = visiteur)
     * @return array{ok:bool, raison:string, etablissement_id:?int, etablissement:?Etablissement}
     */
    public function evaluer(?array $sessionUser): array
    {
        if ($sessionUser === null) {
            return $this->resultat(true, self::RAISON_ANONYME, null, null);
        }

        $userId = (int)($sessionUser['id'] ?? 0);
        if ($userId <= 0) {
            return $this->resultat(false, self::RAISON_UTILISATEUR_INVALIDE, null, null);
        }
        $etabId = (int)($sessionUser['etablissement_id'] ?? 0);
        if ($etabId <= 0) {
            return $this->resultat(false, self::RAISON_ETAB_ABSENT, null, null);
        }

        $stmt = $this->pdo->prepare('SELECT actif, etablissement_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            return $this->resultat(false, self::RAISON_UTILISATEUR_INVALIDE, $etabId, null);
        }
        if ((int)$user['actif'] === 0) {
            return $this->resultat(false, self::RAISON_COMPTE_DESACTIVE, $etabId, null);
        }

        $etablissement = $this->repo->findById($etabId);
        if ($etablissement === null) {
            return $this->resultat(false, self::RAISON_ETAB_INTROUVABLE, $etabId, null);
        }
        if (in_array($etablissement->statut, self::STATUTS_BLOQUES, true)) {
            return $this->resultat(false, self::RAISON_ETAB_INDISPONIBLE, $etabId, null);
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total, SUM(etablissement_id = ? AND left_at IS NULL) AS cet_etab
               FROM user_etablissements WHERE user_id = ?'
        );
        $stmt->execute([$etabId, $userId]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'cet_etab' => 0];
        $membre = (int)($m['cet_etab'] ?? 0) > 0;
        $repliV1 = (int)$m['total'] === 0 && (int)$user['etablissement_id'] === $etabId;
        if (!$membre && !$repliV1) {
            return $this->resultat(false, self::RAISON_NON_MEMBRE, $etabId, null);
        }

        return $this->resultat(true, self::RAISON_OK, $etabId, $etablissement);
    }

    /**
     * Les routes du portail plateforme ne dépendent pas d'un établissement (PlatformAuth, règle distincte).
     * @param string $uri      URI de la requête (avec ou sans query)
     * @param string $basePath sous-répertoire de l'application (ex. /ecole_app), '' à la racine
     */
    public static function routeSansContexte(string $uri, string $basePath = ''): bool
    {
        $path = (string)parse_url($uri, PHP_URL_PATH);
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        $path = '/' . ltrim($path, '/');
        return $path === '/platform' || str_starts_with($path, '/platform/');
    }

    private function resultat(bool $ok, string $raison, ?int $etabId, ?Etablissement $etab): array
    {
        return ['ok' => $ok, 'raison' => $raison, 'etablissement_id' => $etabId, 'etablissement' => $etab];
    }
}
