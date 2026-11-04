<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use PDO;

/**
 * Comptes opérateurs de la plateforme — identité PROPRE, sans aucun lien avec `users` (comptes d'établissement).
 *
 * Création, authentification, changement et réinitialisation de mot de passe, activation. Utilisé par le portail (connexion, page
 * « mon mot de passe ») et par le script en ligne de commande `database/create-operator.php` (création du premier opérateur et
 * procédure de récupération d'accès). Toute opération sensible est écrite dans le journal d'audit global.
 *
 * Aucun mot de passe par défaut n'existe : un compte est créé avec un mot de passe qui respecte PlatformPasswordPolicy, ou avec un mot de
 * passe aléatoire affiché une seule fois (changement obligatoire à la première connexion).
 */
final class PlatformOperatorService
{
    public const NIVEAUX = ['support', 'admin', 'super_admin'];

    /** Hachage bcrypt valide d'une valeur aléatoire jetée : sert uniquement à égaliser le temps de réponse d'un compte inconnu. */
    private const HACHAGE_FACTICE = '$2y$10$MTju1JDKeaI8kuXPdddKTeVVMYi9Krii00eeVcnN8xEZUbAUfh3c.';

    /** Acteur « système » pour les opérations faites en ligne de commande (accès serveur). */
    public const ACTEUR_CLI = ['type' => 'system', 'id' => null, 'email' => 'cli', 'niveau' => 'system'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * Crée un opérateur indépendant.
     *
     * @param ?string $motDePasse null = mot de passe aléatoire généré (retourné une seule fois, changement obligatoire à la 1re connexion)
     * @param ?array  $acteur     acteur d'audit (ACTEUR_CLI en ligne de commande) ; null = opérateur connecté
     * @return array{id:int, email:string, password:?string}  `password` n'est renseigné que s'il a été généré
     * @throws \InvalidArgumentException
     */
    public function creer(string $email, string $nom, string $niveau = 'super_admin', ?string $motDePasse = null, ?array $acteur = null): array
    {
        $email = mb_strtolower(trim($email));
        $nom = trim($nom);
        $erreurs = [];
        if ($email === '' || mb_strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'Adresse e-mail invalide.';
        }
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 150) {
            $erreurs[] = 'Le nom doit contenir entre 2 et 150 caractères.';
        }
        if (!in_array($niveau, self::NIVEAUX, true)) {
            $erreurs[] = 'Niveau invalide (support, admin ou super_admin).';
        }
        $genere = null;
        if ($motDePasse === null || $motDePasse === '') {
            $motDePasse = $genere = PlatformPasswordPolicy::generer();
        } else {
            $erreurs = array_merge($erreurs, PlatformPasswordPolicy::valider($motDePasse, $email, $nom));
        }
        if ($erreurs === [] && $this->trouverParEmail($email) !== null) {
            $erreurs[] = 'Un opérateur avec cette adresse e-mail existe déjà.';
        }
        if ($erreurs !== []) {
            throw new \InvalidArgumentException(implode(' ', $erreurs));
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_operators (user_id, email, nom, password_hash, must_change_password, password_changed_at, niveau, actif)
             VALUES (NULL, ?, ?, ?, ?, NOW(), ?, 1)'
        );
        $stmt->execute([$email, $nom, password_hash($motDePasse, PASSWORD_DEFAULT), $genere !== null ? 1 : 0, $niveau]);
        $id = (int)$this->pdo->lastInsertId();

        PlatformAuditService::make($this->pdo)->record('operator.created', [
            'actor' => $acteur ?? $this->acteurConnecte(), 'entity_type' => 'platform_operator', 'entity_id' => $id,
            'apres' => ['email' => $email, 'nom' => $nom, 'niveau' => $niveau, 'mot_de_passe_genere' => $genere !== null],
        ]);
        return ['id' => $id, 'email' => $email, 'password' => $genere];
    }

    /**
     * Vérifie les identifiants. Jamais d'indication sur la cause de l'échec (compte inconnu, désactivé, sans mot de passe, mauvais mot
     * de passe) : un hachage factice est vérifié si le compte n'existe pas, pour ne pas révéler l'existence d'un compte par le temps de réponse.
     *
     * @return array{id:int, email:string, nom:string, niveau:string, must_change:bool}|null
     */
    public function authentifier(string $email, string $motDePasse): ?array
    {
        $op = $this->trouverParEmail(mb_strtolower(trim($email)));
        $hash = ($op !== null && (int)$op['actif'] === 1 && !empty($op['password_hash'])) ? (string)$op['password_hash'] : null;
        // Hachage factice (bcrypt) : même coût de calcul quand le compte n'existe pas.
        $ok = password_verify($motDePasse, $hash ?? self::HACHAGE_FACTICE);
        if ($hash === null || !$ok || $op === null) {
            return null;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->pdo->prepare('UPDATE platform_operators SET password_hash = ? WHERE id = ?')->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $op['id']]);
        }
        $this->pdo->prepare('UPDATE platform_operators SET last_login_at = NOW() WHERE id = ?')->execute([$op['id']]);
        return [
            'id' => (int)$op['id'], 'email' => (string)$op['email'], 'nom' => (string)($op['nom'] ?? ''),
            'niveau' => (string)$op['niveau'], 'must_change' => (int)$op['must_change_password'] === 1,
        ];
    }

    /**
     * Changement de mot de passe par l'opérateur lui-même (ancien mot de passe exigé).
     *
     * @return list<string> erreurs (vide = changement effectué)
     */
    public function changerMotDePasse(int $id, string $actuel, string $nouveau, string $confirmation): array
    {
        $op = $this->trouverParId($id);
        if ($op === null || (int)$op['actif'] !== 1 || empty($op['password_hash']) || !password_verify($actuel, (string)$op['password_hash'])) {
            PlatformAuditService::make($this->pdo)->record('operator.password_change_failed', [
                'entity_type' => 'platform_operator', 'entity_id' => $id, 'resultat' => 'failure',
            ]);
            return ['Le mot de passe actuel est incorrect.'];
        }
        $erreurs = PlatformPasswordPolicy::valider($nouveau, (string)$op['email'], (string)($op['nom'] ?? ''));
        if ($nouveau !== $confirmation) {
            $erreurs[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
        }
        if (hash_equals($actuel, $nouveau)) {
            $erreurs[] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
        }
        if ($erreurs !== []) {
            return $erreurs;
        }
        $this->pdo->prepare(
            'UPDATE platform_operators SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?'
        )->execute([password_hash($nouveau, PASSWORD_DEFAULT), $id]);
        PlatformAuditService::make($this->pdo)->record('operator.password_changed', ['entity_type' => 'platform_operator', 'entity_id' => $id]);
        PlatformSecurityNotifier::notifier($id, PlatformSecurityNotifier::MOT_DE_PASSE_MODIFIE, [], $this->pdo);
        return [];
    }

    /**
     * PROCÉDURE DE RÉCUPÉRATION D'ACCÈS (accès serveur requis) : nouveau mot de passe pour un opérateur existant, changement obligatoire à la
     * prochaine connexion. Réactive aussi le compte s'il est désactivé.
     *
     * @return array{id:int, email:string, password:?string}
     * @throws \InvalidArgumentException
     */
    public function reinitialiserMotDePasse(string $email, ?string $motDePasse = null, ?array $acteur = null): array
    {
        $op = $this->trouverParEmail(mb_strtolower(trim($email)));
        if ($op === null) {
            throw new \InvalidArgumentException('Aucun opérateur indépendant avec cette adresse e-mail.');
        }
        $genere = null;
        if ($motDePasse === null || $motDePasse === '') {
            $motDePasse = $genere = PlatformPasswordPolicy::generer();
        } else {
            $erreurs = PlatformPasswordPolicy::valider($motDePasse, (string)$op['email'], (string)($op['nom'] ?? ''));
            if ($erreurs !== []) {
                throw new \InvalidArgumentException(implode(' ', $erreurs));
            }
        }
        $this->pdo->prepare(
            'UPDATE platform_operators SET password_hash = ?, must_change_password = 1, password_changed_at = NOW(), actif = 1 WHERE id = ?'
        )->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $op['id']]);
        PlatformAuditService::make($this->pdo)->record('operator.password_reset', [
            'actor' => $acteur ?? $this->acteurConnecte(), 'entity_type' => 'platform_operator', 'entity_id' => (int)$op['id'],
            'avant' => ['actif' => (int)$op['actif']], 'apres' => ['actif' => 1, 'changement_obligatoire' => true, 'mot_de_passe_genere' => $genere !== null],
        ]);
        PlatformSecurityNotifier::notifier((int)$op['id'], PlatformSecurityNotifier::MOT_DE_PASSE_REINITIALISE, [], $this->pdo);
        return ['id' => (int)$op['id'], 'email' => (string)$op['email'], 'password' => $genere];
    }

    /** Active ou désactive un opérateur indépendant (désactiver = révoquer l'accès sans supprimer le compte ni son historique). */
    public function definirActif(string $email, bool $actif, ?array $acteur = null): bool
    {
        $op = $this->trouverParEmail(mb_strtolower(trim($email)));
        if ($op === null) {
            return false;
        }
        $this->pdo->prepare('UPDATE platform_operators SET actif = ? WHERE id = ?')->execute([$actif ? 1 : 0, $op['id']]);
        PlatformAuditService::make($this->pdo)->record($actif ? 'operator.activated' : 'operator.deactivated', [
            'actor' => $acteur ?? $this->acteurConnecte(), 'entity_type' => 'platform_operator', 'entity_id' => (int)$op['id'],
            'avant' => ['actif' => (int)$op['actif']], 'apres' => ['actif' => $actif ? 1 : 0],
        ]);
        return true;
    }

    /** Nombre d'opérateurs indépendants actifs pouvant se connecter (mot de passe propre défini). */
    public function compterUtilisables(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM platform_operators WHERE actif = 1 AND password_hash IS NOT NULL AND email IS NOT NULL")->fetchColumn();
    }

    public function trouverParEmail(string $email): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM platform_operators WHERE email = ?');
        $s->execute([$email]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function trouverParId(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM platform_operators WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array{type:string, id:?int, email:?string, niveau:?string}|null */
    private function acteurConnecte(): ?array
    {
        $op = PlatformAuth::current();
        return $op === null ? self::ACTEUR_CLI : ['type' => 'operator', 'id' => (int)$op['id'], 'email' => (string)$op['email'], 'niveau' => (string)$op['niveau']];
    }
}
