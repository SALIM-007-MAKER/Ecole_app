<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Gestion des domaines personnalisés par établissement — blueprint §11.
 *
 * Périmètre réellement implémenté (application-layer) :
 *   - Ajout/liste/activation-désactivation/suppression d'un domaine
 *   - Génération de token + vérification DNS (challenge TXT)
 *
 * Explicitement HORS PÉRIMÈTRE de cette classe (infrastructure serveur,
 * voir CUSTOM_DOMAINS_IMPLEMENTATION_REPORT.md §"Limites environnementales") :
 *   - Émission/renouvellement de certificats SSL (ACME/Let's Encrypt)
 *   - Reconfiguration dynamique Nginx/Caddy
 *   - Planification récurrente de la vérification (nécessite Phase 14.9 — queue/cron)
 *
 * Isolation stricte : toute méthode qui modifie un domaine vérifie
 * l'appartenance à l'établissement appelant AVANT toute écriture — jamais
 * de confiance dans un domain_id fourni par la requête seul.
 */
final class DomainVerificationService
{
    private const TXT_PREFIX = 'edunova-verify=';
    private const MAX_DOMAINS_PER_ETAB = 10;

    /** @var callable(string):array */
    private $dnsLookup;

    public function __construct(
        private readonly PDO $pdo,
        ?callable $dnsLookup = null,
    ) {
        // Par défaut : lookup DNS réel (dns_get_record). Injectable pour les
        // tests — aucune vérification DNS réelle n'est possible/pertinente
        // en environnement de développement local (voir rapport §Limites).
        $this->dnsLookup = $dnsLookup ?? static function (string $domain): array {
            $records = @dns_get_record($domain, DNS_TXT);
            return is_array($records) ? $records : [];
        };
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * @return array<int, array{id:int, domain:string, type:string, verified:bool, ssl_status:string, verification_token:?string, created_at:string}>
     */
    public function listForEtablissement(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, domain, type, verified, ssl_status, verification_token, verified_at, created_at
             FROM etablissement_domains WHERE etablissement_id = ? ORDER BY type ASC, created_at ASC"
        );
        $stmt->execute([$etablissementId]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id'                 => (int)$row['id'],
                'domain'             => $row['domain'],
                'type'               => $row['type'],
                'verified'           => (bool)$row['verified'],
                'ssl_status'         => $row['ssl_status'],
                'verification_token' => $row['verification_token'],
                'verified_at'        => $row['verified_at'],
                'created_at'         => $row['created_at'],
            ];
        }
        return $out;
    }

    /**
     * Ajoute un domaine personnalisé pour un établissement (statut initial :
     * non vérifié, en attente de challenge DNS).
     *
     * @throws \InvalidArgumentException si le format est invalide, le domaine
     *         déjà pris (unicité globale — un domaine ne peut appartenir qu'à
     *         un seul établissement), ou la limite par établissement atteinte
     */
    public function addDomain(int $etablissementId, string $domain, string $type = 'custom'): array
    {
        $domain = strtolower(trim($domain));

        if (!$this->isValidHostname($domain)) {
            throw new \InvalidArgumentException("Format de domaine invalide : {$domain}");
        }

        if (!in_array($type, ['custom', 'subdomain'], true)) {
            throw new \InvalidArgumentException("Type de domaine invalide : {$type}");
        }

        $existing = $this->pdo->prepare("SELECT id FROM etablissement_domains WHERE domain = ?");
        $existing->execute([$domain]);
        if ($existing->fetchColumn() !== false) {
            throw new \InvalidArgumentException("Ce domaine est déjà enregistré (par cet établissement ou un autre).");
        }

        $count = (int)$this->countForEtablissement($etablissementId);
        if ($count >= self::MAX_DOMAINS_PER_ETAB) {
            throw new \InvalidArgumentException("Limite de " . self::MAX_DOMAINS_PER_ETAB . " domaines par établissement atteinte.");
        }

        $token = $this->generateToken();

        $stmt = $this->pdo->prepare(
            "INSERT INTO etablissement_domains (etablissement_id, domain, type, verification_token, verified, ssl_status)
             VALUES (?, ?, ?, ?, 0, 'pending')"
        );
        $stmt->execute([$etablissementId, $domain, $type, $token]);
        $domainId = (int)$this->pdo->lastInsertId();

        return [
            'id'                 => $domainId,
            'domain'             => $domain,
            'verification_token' => $token,
            'txt_record_name'    => '_edunova-verify.' . $domain,
            'txt_record_value'   => self::TXT_PREFIX . $token,
        ];
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(16)); // 32 caractères hex
    }

    /**
     * Tente la vérification DNS d'un domaine (challenge TXT). Ne modifie
     * l'établissement demandeur QUE si le domaine lui appartient réellement.
     *
     * @return bool true si le token a été trouvé dans les enregistrements TXT et validé
     */
    public function verify(int $etablissementId, int $domainId): bool
    {
        $row = $this->findOwned($etablissementId, $domainId);
        if ($row === null) {
            return false;
        }

        if ($row['verified']) {
            return true; // déjà vérifié — idempotent
        }

        $recordName = '_edunova-verify.' . $row['domain'];
        $records = ($this->dnsLookup)($recordName);

        $expected = self::TXT_PREFIX . $row['verification_token'];
        $found = false;
        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';
            if (hash_equals($expected, (string)$txt)) {
                $found = true;
                break;
            }
        }

        if ($found) {
            $stmt = $this->pdo->prepare(
                "UPDATE etablissement_domains SET verified = 1, verified_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$domainId]);
        }

        return $found;
    }

    /** Active/désactive un domaine sans le supprimer (conserve l'historique — pas de DELETE physique). */
    public function toggleActive(int $etablissementId, int $domainId, bool $active): bool
    {
        $row = $this->findOwned($etablissementId, $domainId);
        if ($row === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE etablissement_domains SET verified = ? WHERE id = ?");
        $stmt->execute([$active ? 1 : 0, $domainId]);
        return true;
    }

    /** Supprime un domaine — TOUJOURS vérifié contre l'établissement appelant. */
    public function delete(int $etablissementId, int $domainId): bool
    {
        $row = $this->findOwned($etablissementId, $domainId);
        if ($row === null) {
            return false;
        }
        $stmt = $this->pdo->prepare("DELETE FROM etablissement_domains WHERE id = ?");
        $stmt->execute([$domainId]);
        return true;
    }

    public function countForEtablissement(int $etablissementId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM etablissement_domains WHERE etablissement_id = ?");
        $stmt->execute([$etablissementId]);
        return (int)$stmt->fetchColumn();
    }

    /** Valide un format d'hôte strict — rejette avant toute résolution/écriture (défense en profondeur, blueprint §"Sécurité"). */
    public function isValidHostname(string $host): bool
    {
        if ($host === '' || strlen($host) > 253) {
            return false;
        }
        // RFC 1123 : labels alphanumériques + tirets, séparés par des points, TLD final alphabétique
        return preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*\.[a-z]{2,63}$/i', $host) === 1;
    }

    private function findOwned(int $etablissementId, int $domainId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM etablissement_domains WHERE id = ? AND etablissement_id = ?"
        );
        $stmt->execute([$domainId, $etablissementId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
