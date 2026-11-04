<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Core\Logger;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformSubscriptionService;
use Core\Tenant\TenantAuthContext;
use Core\Tenant\TenantMembershipService;
use PDO;

/**
 * Authentification GLOBALE des utilisateurs d'établissement (connexion commune `/login`).
 *
 * Avant l'authentification, l'établissement de l'utilisateur n'est pas connu : `UserModel::findByEmail()` (limité au tenant par défaut) ne
 * peut donc pas servir. Ce service interroge `users` directement, par PDO, SANS passer par `Core\Model` : les requêtes métier de
 * `UserModel` restent limitées à leur établissement. L'e-mail est unique globalement (index `uq email`).
 *
 * Règles d'établissement et de rôle (décisions validées) :
 *  - appartenance = ligne ACTIVE de `user_etablissements` (left_at IS NULL) vers un établissement non supprimé ;
 *  - repli historique `users.etablissement_id` / `users.role` UNIQUEMENT si l'utilisateur n'a AUCUNE ligne `user_etablissements`
 *    (ni active ni quittée) — un ancien membre ne retrouve jamais l'accès par ce repli ;
 *  - dès qu'une appartenance existe, le rôle vient EXCLUSIVEMENT de `user_roles_etab` pour CET établissement : jamais de repli sur le
 *    rôle global `users.role` (une appartenance sans rôle propre est REFUSÉE et journalisée) ;
 *  - plusieurs rôles dans un même établissement : rôle affiché selon une priorité fixe (indépendante de l'ordre en base), permissions =
 *    réunion de celles de tous ses rôles ; l'anomalie est journalisée ; aucun rôle n'est ajouté ni supprimé automatiquement.
 */
final class GlobalAuthService
{
    /** Durée de validité de l'état « authentifié, établissement à choisir ». */
    public const ATTENTE_CHOIX_SECONDES = 600;

    /** Priorité d'affichage du rôle quand un utilisateur en possède plusieurs dans le même établissement (du plus au moins privilégié). */
    public const PRIORITE_ROLES = ['admin', 'directeur', 'comptable', 'secretaire', 'enseignant', 'parent', 'eleve'];

    public const RAISON_SUSPENDU = 'suspendu';
    public const RAISON_ANNULE = 'annule';
    public const RAISON_ARCHIVE = 'archive';
    public const RAISON_INDISPONIBLE = 'indisponible';
    public const RAISON_ABONNEMENT = 'abonnement_expire';
    public const RAISON_ROLE_MANQUANT = 'role_manquant';

    /** Hachage factice : le temps de réponse d'un e-mail inconnu est celui d'un mot de passe faux (pas d'énumération par le temps). */
    private const HACHAGE_FACTICE = '$2y$10$rnmLCfkZUowz9q63u0x0k.o62eV8PyheUBnR.ZryjCiQQ4/Zby9nq';

    /** @var array<int,bool> */
    private static array $acteurEcole = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(?PDO $pdo = null): self
    {
        return new self($pdo ?? Database::getInstance()->getConnection());
    }

    // ── Identification ────────────────────────────────────────────────────────────────────────────────────────────────────────

    /** Utilisateur par e-mail, tous établissements confondus (null si inconnu). */
    public function lookupByEmail(string $email): ?object
    {
        $s = $this->pdo->prepare('SELECT id, nom, prenom, email, password, role, actif, photo, etablissement_id FROM users WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        $r = $s->fetch(PDO::FETCH_OBJ);
        return $r === false ? null : $r;
    }

    /** Utilisateur par identifiant, tous établissements confondus (null si inconnu). */
    public function lookupById(int $id): ?object
    {
        $s = $this->pdo->prepare('SELECT id, nom, prenom, email, password, role, actif, photo, etablissement_id FROM users WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $r = $s->fetch(PDO::FETCH_OBJ);
        return $r === false ? null : $r;
    }

    /**
     * Vérifie l'e-mail et le mot de passe. Renvoie l'utilisateur, ou null — SANS distinguer e-mail inconnu et mot de passe faux (même
     * durée : un hachage factice est vérifié quand l'e-mail n'existe pas).
     */
    public function authenticate(string $email, string $password): ?object
    {
        $user = $this->lookupByEmail($email);
        if ($user === null) {
            password_verify($password, self::HACHAGE_FACTICE);
            return null;
        }
        return password_verify($password, (string)$user->password) ? $user : null;
    }

    // ── Compte PROPRE de l'utilisateur connecté ───────────────────────────────────────────────────────────────────────────────
    // Nom, e-mail, mot de passe, photo : des données de la PERSONNE, pas de l'établissement. Les méthodes de `UserModel` (limitées à
    // l'établissement courant) échouent en silence pour un membre rattaché à un autre établissement. Ces méthodes n'agissent que sur
    // l'identifiant de la SESSION de l'appelant (jamais sur un identifiant reçu d'une requête).

    /** Toutes les colonnes du compte de l'utilisateur connecté, quel que soit l'établissement actif. */
    public function monCompte(int $userId): ?object
    {
        $s = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $s->execute([$userId]);
        $r = $s->fetch(PDO::FETCH_OBJ);
        return $r === false ? null : $r;
    }

    /** Colonnes du profil modifiables par l'utilisateur lui-même (liste blanche : jamais le rôle, l'établissement, l'état ni le mot de passe). */
    private const PROFIL_MODIFIABLE = ['nom', 'prenom', 'email', 'telephone', 'photo'];

    /** @param array<string,mixed> $data seules les colonnes de la liste blanche sont écrites */
    public function mettreAJourMonProfil(int $userId, array $data): bool
    {
        $data = array_intersect_key($data, array_flip(self::PROFIL_MODIFIABLE));
        if ($data === []) {
            return false;
        }
        $sets = implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", array_keys($data)));
        $s = $this->pdo->prepare("UPDATE users SET {$sets}, updated_at = NOW() WHERE id = ?");
        $s->execute([...array_values($data), $userId]);
        return $s->rowCount() > 0;
    }

    public function changerMonMotDePasse(int $userId, string $hash): bool
    {
        $sets = $this->colonneDateMotDePasse() ? ', password_changed_at = NOW()' : '';
        $s = $this->pdo->prepare("UPDATE users SET password = ?, updated_at = NOW(){$sets} WHERE id = ?");
        $s->execute([$hash, $userId]);
        $ok = $s->rowCount() > 0;
        if ($ok) {
            // Un jeton de réinitialisation encore en attente ne doit pas survivre à un changement de mot de passe.
            $this->pdo->prepare('DELETE FROM password_resets WHERE email = (SELECT email FROM users WHERE id = ?)')->execute([$userId]);
        }
        return $ok;
    }
    /** Dernière connexion, par identifiant seulement (aucun filtre d'établissement : l'établissement n'est pas forcément celui par défaut). */
    public function touchLastLogin(int $userId): void
    {
        $this->pdo->prepare('UPDATE users SET derniere_connexion = NOW() WHERE id = ?')->execute([$userId]);
    }

    // ── Appartenances et rôles ────────────────────────────────────────────────────────────────────────────────────────────────

    /** Nombre de lignes `user_etablissements` de l'utilisateur, actives ou quittées. 0 = compte historique sans appartenance. */
    public function nombreLignesAppartenance(int $userId): int
    {
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM user_etablissements WHERE user_id = ?');
        $s->execute([$userId]);
        return (int)$s->fetchColumn();
    }

    /**
     * Rôle de l'utilisateur dans un établissement, selon les règles ci-dessus.
     *
     * @return array{role:?string, roles:list<string>, legacy:bool}
     *         role = null : appartenance SANS rôle propre (accès à refuser). legacy = true : compte sans aucune appartenance (repli
     *         historique, rôle `users.role`).
     */
    public function resoudreRole(object $user, int $etablissementId): array
    {
        if ($this->nombreLignesAppartenance((int)$user->id) === 0) {
            $role = (string)($user->role ?? '');
            return ['role' => $role !== '' ? $role : null, 'roles' => $role !== '' ? [$role] : [], 'legacy' => true];
        }
        $roles = self::trierRoles((new TenantAuthContext($this->pdo))->roles((int)$user->id, $etablissementId));
        return ['role' => $roles[0] ?? null, 'roles' => $roles, 'legacy' => false];
    }

    /**
     * Permissions du rôle résolu. Hors compte historique, le mode STRICT est actif : aucune permission RBAC globale (`rbac_user_roles`,
     * non liée à un établissement) n'est jamais héritée.
     *
     * @return list<string>
     */
    public function permissions(object $user, int $etablissementId, string $role, bool $legacy): array
    {
        return (new \App\Models\UserModel())->getPermissions($role, (int)$user->id, $etablissementId, !$legacy);
    }

    /**
     * Classe les établissements de l'utilisateur : accessibles, ou bloqués avec la raison.
     *
     * @return array{legacy:bool, accessibles:list<array<string,mixed>>, bloques:list<array<string,mixed>>}
     *         entrées : id, slug, nom, nom_court, is_primary, role, roles, raison (null si accessible)
     */
    public function classer(object $user): array
    {
        $legacy = $this->nombreLignesAppartenance((int)$user->id) === 0;
        if ($legacy) {
            $candidats = [];
            $id = (int)($user->etablissement_id ?? 0);
            if ($id > 0) {
                $s = $this->pdo->prepare('SELECT id, slug, nom, nom_court FROM etablissements WHERE id = ? AND deleted_at IS NULL');
                $s->execute([$id]);
                if (($e = $s->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $candidats[] = ['id' => (int)$e['id'], 'slug' => $e['slug'], 'nom' => $e['nom'], 'nom_court' => $e['nom_court'], 'is_primary' => true];
                }
            }
        } else {
            $candidats = (new TenantMembershipService($this->pdo))->listForUser((int)$user->id);
        }

        $res = ['legacy' => $legacy, 'accessibles' => [], 'bloques' => []];
        foreach ($candidats as $c) {
            $r = $this->resoudreRole($user, (int)$c['id']);
            $c['role'] = $r['role'];
            $c['roles'] = $r['roles'];
            $c['raison'] = $this->raisonBlocage((int)$c['id']) ?? ($r['role'] === null ? self::RAISON_ROLE_MANQUANT : null);
            $res[$c['raison'] === null ? 'accessibles' : 'bloques'][] = $c;
        }
        return $res;
    }

    /** Raison pour laquelle l'établissement n'est pas utilisable (statut, abonnement), ou null. Mêmes règles que SessionTenantGuard. */
    private function raisonBlocage(int $etablissementId): ?string
    {
        $s = $this->pdo->prepare('SELECT statut, deleted_at FROM etablissements WHERE id = ?');
        $s->execute([$etablissementId]);
        $e = $s->fetch(PDO::FETCH_ASSOC);
        if ($e === false || $e['deleted_at'] !== null) {
            return self::RAISON_INDISPONIBLE;
        }
        $raison = match ($e['statut']) {
            'suspended' => self::RAISON_SUSPENDU,
            'cancelled' => self::RAISON_ANNULE,
            'archived'  => self::RAISON_ARCHIVE,
            default     => null,
        };
        if ($raison !== null) {
            return $raison;
        }
        return PlatformSubscriptionService::accesInterdit($this->pdo, $etablissementId) ? self::RAISON_ABONNEMENT : null;
    }

    /** Message destiné à l'utilisateur (identifiants DÉJÀ vérifiés) pour un établissement bloqué. */
    public static function messageBlocage(string $nomEtablissement, string $raison): string
    {
        return match ($raison) {
            self::RAISON_SUSPENDU     => "L'accès à l'établissement « {$nomEtablissement} » est temporairement suspendu.",
            self::RAISON_ANNULE       => "L'établissement « {$nomEtablissement} » a résilié son abonnement : l'accès est fermé.",
            self::RAISON_ARCHIVE      => "L'établissement « {$nomEtablissement} » est archivé : l'accès est fermé.",
            self::RAISON_ABONNEMENT   => "L'abonnement de l'établissement « {$nomEtablissement} » a expiré : un renouvellement est nécessaire.",
            self::RAISON_ROLE_MANQUANT => "Aucun rôle n'est défini pour votre compte dans l'établissement « {$nomEtablissement} ». Contactez l'administrateur.",
            default                   => "L'établissement « {$nomEtablissement} » est indisponible.",
        };
    }

    /**
     * Trie des codes de rôle par priorité fixe ; les codes inconnus viennent après, par ordre alphabétique. Le résultat ne dépend JAMAIS
     * de l'ordre des lignes en base.
     *
     * @param list<string> $codes
     * @return list<string>
     */
    public static function trierRoles(array $codes): array
    {
        $codes = array_values(array_unique(array_map('strval', $codes)));
        usort($codes, static function (string $a, string $b): int {
            $pa = array_search($a, self::PRIORITE_ROLES, true);
            $pb = array_search($b, self::PRIORITE_ROLES, true);
            $pa = $pa === false ? PHP_INT_MAX : $pa;
            $pb = $pb === false ? PHP_INT_MAX : $pb;
            return $pa === $pb ? strcmp($a, $b) : $pa <=> $pb;
        });
        return $codes;
    }

    // ── Mot de passe : politique, réinitialisation, invalidation des sessions ─────────────────────────────────────────────────

    /** Durée de validité d'un lien de réinitialisation (distincte de celle d'une invitation). */
    public const DUREE_JETON_SECONDES = 3600;

    /** @var array<int,bool> */
    private static array $colonneDate = [];

    /** La colonne users.password_changed_at (migration T069) existe-t-elle ? Sinon l'invalidation des sessions est simplement inactive. */
    public function colonneDateMotDePasse(): bool
    {
        $cle = spl_object_id($this->pdo);
        if (self::$colonneDate[$cle] ?? false) {
            return true;
        }
        try {
            $n = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'"
            )->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
        return self::$colonneDate[$cle] = ($n > 0);
    }

    /** Date (horodatage Unix) du dernier changement de mot de passe, ou null (jamais changé depuis T069, ou colonne absente). */
    public function dateMotDePasse(int $userId): ?int
    {
        if (!$this->colonneDateMotDePasse()) {
            return null;
        }
        $s = $this->pdo->prepare('SELECT UNIX_TIMESTAMP(password_changed_at) FROM users WHERE id = ?');
        $s->execute([$userId]);
        $v = $s->fetchColumn();
        return $v === false || $v === null ? null : (int)$v;
    }

    /**
     * Politique de mot de passe d'une PERSONNE (elle ne dépend pas de l'établissement de la requête, qui est inconnu pour un visiteur) :
     *  - administrateur dans au moins un de ses établissements (ou compte historique de rôle admin) : 14 caractères minimum et 3 familles de
     *    caractères (PlatformPasswordPolicy), sans son e-mail ni son nom ;
     *  - sinon : le PLUS ÉLEVÉ des réglages `password_min_length` de ses établissements actifs (8 par défaut).
     *
     * @return array{admin:bool, min:int}
     */
    public function politiqueMotDePasse(object $user): array
    {
        $id = (int)$user->id;
        if ($this->nombreLignesAppartenance($id) === 0) {
            $etabs = (int)($user->etablissement_id ?? 0) > 0 ? [(int)$user->etablissement_id] : [];
            $admin = (string)($user->role ?? '') === 'admin';
        } else {
            $s = $this->pdo->prepare('SELECT ue.etablissement_id FROM user_etablissements ue WHERE ue.user_id = ? AND ue.left_at IS NULL');
            $s->execute([$id]);
            $etabs = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
            $s = $this->pdo->prepare(
                "SELECT COUNT(*) FROM user_roles_etab ur JOIN etab_roles r ON r.id = ur.role_id
                  JOIN user_etablissements ue ON ue.user_id = ur.user_id AND ue.etablissement_id = ur.etablissement_id AND ue.left_at IS NULL
                  WHERE ur.user_id = ? AND r.code = 'admin'"
            );
            $s->execute([$id]);
            $admin = (int)$s->fetchColumn() > 0;
        }
        $min = 8;
        $reglages = \Core\Tenant\SettingsService::make();
        foreach ($etabs as $e) {
            $min = max($min, (int)$reglages->get($e, 'securite', 'password_min_length', 8));
        }
        return ['admin' => $admin, 'min' => $admin ? max($min, \Core\Platform\PlatformPasswordPolicy::LONGUEUR_MIN) : $min];
    }

    /** @return list<string> messages d'erreur (vide = accepté) */
    public function validerMotDePasse(object $user, string $motDePasse): array
    {
        $politique = $this->politiqueMotDePasse($user);
        if ($politique['admin']) {
            $erreurs = \Core\Platform\PlatformPasswordPolicy::valider($motDePasse, (string)$user->email, trim(((string)($user->prenom ?? '')) . ' ' . (string)($user->nom ?? '')));
            if (mb_strlen($motDePasse) < $politique['min'] && mb_strlen($motDePasse) >= \Core\Platform\PlatformPasswordPolicy::LONGUEUR_MIN) {
                $erreurs[] = 'Le mot de passe doit contenir au moins ' . $politique['min'] . ' caractères.';
            }
            return $erreurs;
        }
        return mb_strlen($motDePasse) < $politique['min'] ? ['Le mot de passe doit contenir au moins ' . $politique['min'] . ' caractères.'] : [];
    }

    /** Ligne `password_resets` d'un jeton encore utilisable (non utilisé, non expiré), sans le consommer ; null sinon. Le jeton n'est jamais stocké en clair. */
    public function jetonReinitialisationValide(string $token): ?object
    {
        if ($token === '' || strlen($token) > 200) {
            return null;
        }
        $s = $this->pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1');
        $s->execute([hash('sha256', $token)]);
        $r = $s->fetch(PDO::FETCH_OBJ);
        return $r === false ? null : $r;
    }

    /**
     * Réinitialise le mot de passe avec un jeton, de façon ATOMIQUE : le jeton est consommé par une seule requête conditionnelle
     * (`used = 0 AND expires_at > NOW()`, une ligne affectée exigée) dans la même transaction que le changement du mot de passe — deux
     * requêtes simultanées avec le même lien ne peuvent pas réussir toutes les deux. Les autres jetons de l'adresse sont supprimés.
     *
     * @return ?object l'utilisateur dont le mot de passe a été changé, ou null (jeton invalide, expiré, déjà utilisé, compte désactivé)
     */
    public function reinitialiserMotDePasse(string $token, string $hash): ?object
    {
        $h = hash('sha256', $token);
        $propre = !$this->pdo->inTransaction();
        if ($propre) {
            $this->pdo->beginTransaction();
        }
        try {
            $s = $this->pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ? AND used = 0 AND expires_at > NOW()');
            $s->execute([$h]);
            if ($s->rowCount() !== 1) {
                $propre && $this->pdo->rollBack();
                return null;
            }
            $s = $this->pdo->prepare('SELECT email FROM password_resets WHERE token = ?');
            $s->execute([$h]);
            $email = (string)$s->fetchColumn();
            $user = $this->lookupByEmail($email);
            if ($user === null || (int)$user->actif === 0) {
                $propre && $this->pdo->rollBack();
                return null;
            }
            $sets = $this->colonneDateMotDePasse() ? ', password_changed_at = NOW()' : '';
            $this->pdo->prepare("UPDATE users SET password = ?, updated_at = NOW(){$sets} WHERE id = ?")->execute([$hash, (int)$user->id]);
            $this->pdo->prepare('DELETE FROM password_resets WHERE email = ? AND token <> ?')->execute([$email, $h]);
            $propre && $this->pdo->commit();
            return $user;
        } catch (\Throwable $e) {
            if ($propre && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    // ── Audit global ──────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Écrit un événement `auth.*` dans le journal global (jamais de mot de passe, de jeton ni de secret). Une panne du journal ne bloque
     * pas la connexion : repli sur le fichier de secours du journal global.
     *
     * @param array<string,mixed> $details informations minimales (sans secret)
     */
    public function journaliser(string $action, ?object $user, ?int $etablissementId, string $resultat = 'success', array $details = [], ?string $emailSaisi = null): void
    {
        try {
            $acteur = ['type' => 'anonymous', 'id' => null, 'email' => $user !== null ? (string)$user->email : $emailSaisi, 'niveau' => null];
            if ($user !== null && $this->acteurEcoleDisponible()) {
                $acteur['type'] = 'school_user';
                $acteur['niveau'] = isset($details['role']) ? (string)$details['role'] : null;
            }
            PlatformAuditService::make($this->pdo)->record($action, [
                'actor' => $acteur,
                'etablissement_id' => $etablissementId,
                'entity_type' => $user !== null ? 'user' : null,
                'entity_id' => $user !== null ? (int)$user->id : null,
                'resultat' => $resultat,
                'apres' => $details !== [] ? $details : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Journal global : événement ' . $action . ' non écrit (' . get_class($e) . ')');
        }
    }

    /** La valeur « school_user » existe-t-elle dans l'ENUM `actor_type` (migration T068) ? Sinon l'acteur est enregistré « anonymous ». */
    private function acteurEcoleDisponible(): bool
    {
        $cle = spl_object_id($this->pdo);
        if (self::$acteurEcole[$cle] ?? false) {
            return true;
        }
        try {
            $type = (string)$this->pdo->query(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_audit_logs' AND COLUMN_NAME = 'actor_type'"
            )->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
        return self::$acteurEcole[$cle] = str_contains($type, "'school_user'");
    }
}
