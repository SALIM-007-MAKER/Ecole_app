<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use PDO;

/**
 * Gestion globale des établissements (tenants) pour le portail Super-Admin
 * SaaS — Phase 14.10, MULTI_TENANT_V2_BLUEPRINT.md §25.1.
 *
 * Contrairement à TOUT le reste de l'application, cette classe interroge
 * délibérément `etablissements` SANS filtre par tenant (c'est son rôle :
 * l'opérateur plateforme voit et gère TOUS les établissements). Elle ne
 * touche jamais aux données métier internes d'un établissement (élèves,
 * notes...) — uniquement à la table `etablissements` elle-même et à ses
 * colonnes de cycle de vie/plan.
 *
 * Suppression logique uniquement (`deleted_at`) — jamais de DROP/DELETE
 * physique sur un établissement contenant des données.
 */
final class PlatformEtablissementService
{
    private const STATUTS = ['trial', 'active', 'suspended', 'cancelled', 'archived'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * @param array{search?:string, statut?:string, plan_id?:int} $filters
     * @return list<array<string,mixed>>
     */
    public function search(array $filters = [], int $limit = 100): array
    {
        $where = ['e.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(e.nom LIKE ? OR e.nom_court LIKE ? OR e.slug LIKE ? OR e.code_etablissement LIKE ?)';
            $needle = '%' . $filters['search'] . '%';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        if (!empty($filters['statut']) && in_array($filters['statut'], self::STATUTS, true)) {
            $where[] = 'e.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['plan_id'])) {
            $where[] = 'e.plan_id = ?';
            $params[] = (int)$filters['plan_id'];
        }

        $sql = "SELECT e.*, p.nom AS plan_nom, p.code AS plan_code, p.actif AS plan_actif
                FROM etablissements e
                LEFT JOIN platform_plans p ON p.id = e.plan_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.created_at DESC LIMIT " . max(1, min(500, $limit));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, p.nom AS plan_nom, p.code AS plan_code, p.actif AS plan_actif
             FROM etablissements e LEFT JOIN platform_plans p ON p.id = e.plan_id
             WHERE e.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array{slug:string, nom:string, nom_court:string, type:string, pays?:string, plan_id?:?int} $data
     * @throws \InvalidArgumentException
     */
    public function create(array $data, int $operatorUserId): int
    {
        $slug = strtolower(trim($data['slug'] ?? ''));
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,58}[a-z0-9]$/', $slug)) {
            throw new \InvalidArgumentException('Slug invalide (lettres minuscules, chiffres, tirets, 3-60 caractères).');
        }
        $existing = $this->pdo->prepare("SELECT id FROM etablissements WHERE slug = ?");
        $existing->execute([$slug]);
        if ($existing->fetchColumn() !== false) {
            throw new \InvalidArgumentException("Le slug « {$slug} » est déjà utilisé.");
        }

        $nom = trim($data['nom'] ?? '');
        $nomCourt = trim($data['nom_court'] ?? '') ?: substr($nom, 0, 60);
        if ($nom === '') {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }

        $validTypes = ['ecole_primaire', 'college', 'lycee', 'universite', 'formation_pro', 'groupe_scolaire'];
        $type = in_array($data['type'] ?? '', $validTypes, true) ? $data['type'] : 'lycee';

        if (!empty($data['plan_id'])) {
            $planOk = $this->pdo->prepare('SELECT COUNT(*) FROM platform_plans WHERE id = ? AND actif = 1');
            $planOk->execute([(int)$data['plan_id']]);
            if ((int)$planOk->fetchColumn() === 0) {
                throw new \InvalidArgumentException('Plan introuvable ou archivé : un plan archivé ne peut plus être assigné.');
            }
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut, plan_id, trial_ends_at)
             VALUES (?, ?, ?, ?, ?, 'trial', ?, DATE_ADD(NOW(), INTERVAL 30 DAY))"
        );
        $stmt->execute([
            $slug, $nom, $nomCourt, $type,
            $data['pays'] ?? 'DZ',
            $data['plan_id'] ?? null,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        if (!empty($data['plan_id'])) {
            $this->applyPlanLimits($id, (int)$data['plan_id']);
        }

        Logger::security('PLATFORM_TENANT_CREATED', "etablissement_id={$id} slug={$slug} par operator_id={$operatorUserId}");
        PlatformAuditService::make($this->pdo)->recordPar($operatorUserId, 'tenant.created', [
            'etablissement_id' => $id, 'entity_type' => 'etablissement', 'entity_id' => $id,
            'apres' => ['slug' => $slug, 'nom' => $nom, 'type' => $type, 'plan_id' => $data['plan_id'] ?? null, 'statut' => 'trial'],
        ]);
        return $id;
    }

    public function activate(int $id, int $operatorUserId): bool
    {
        return $this->setStatut($id, 'active', 'PLATFORM_TENANT_ACTIVATED', $operatorUserId);
    }

    public function suspend(int $id, int $operatorUserId, ?string $reason = null): bool
    {
        $avant = $this->statutActuel($id);
        $stmt = $this->pdo->prepare(
            "UPDATE etablissements SET statut = 'suspended', suspended_at = NOW(), suspension_reason = ?
             WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$reason, $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            Logger::security('PLATFORM_TENANT_SUSPENDED', "etablissement_id={$id} raison=" . ($reason ?? '(non précisée)') . " par operator_id={$operatorUserId}");
            PlatformAuditService::make($this->pdo)->recordPar($operatorUserId, 'tenant.suspended', [
                'etablissement_id' => $id, 'entity_type' => 'etablissement', 'entity_id' => $id,
                'avant' => ['statut' => $avant], 'apres' => ['statut' => 'suspended', 'raison' => $reason],
            ]);
        }
        return $ok;
    }

    public function archive(int $id, int $operatorUserId): bool
    {
        return $this->setStatut($id, 'archived', 'PLATFORM_TENANT_ARCHIVED', $operatorUserId);
    }

    public function restore(int $id, int $operatorUserId): bool
    {
        return $this->setStatut($id, 'active', 'PLATFORM_TENANT_RESTORED', $operatorUserId);
    }

    /** Suppression LOGIQUE uniquement (deleted_at) — jamais de DROP/DELETE physique. */
    public function softDelete(int $id, int $operatorUserId): bool
    {
        $avant = $this->statutActuel($id);
        $stmt = $this->pdo->prepare(
            "UPDATE etablissements SET deleted_at = NOW(), statut = 'cancelled' WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            Logger::security('PLATFORM_TENANT_SOFT_DELETED', "etablissement_id={$id} par operator_id={$operatorUserId}");
            PlatformAuditService::make($this->pdo)->recordPar($operatorUserId, 'tenant.deleted', [
                'etablissement_id' => $id, 'entity_type' => 'etablissement', 'entity_id' => $id,
                'avant' => ['statut' => $avant, 'supprime' => false], 'apres' => ['statut' => 'cancelled', 'supprime' => true, 'suppression' => 'logique'],
            ]);
        }
        return $ok;
    }

    /** Assigne un plan à un établissement et copie ses limites (max_users/max_eleves/storage_quota_mb) sur la ligne établissement — clôt le point ouvert en Phase 14.8 (§"Architecture extensible pour les futurs plans"). */
    public function assignPlan(int $etablissementId, int $planId, int $operatorUserId): bool
    {
        $plan = $this->pdo->prepare("SELECT * FROM platform_plans WHERE id = ? AND actif = 1");
        $plan->execute([$planId]);
        $planRow = $plan->fetch(PDO::FETCH_ASSOC);
        if ($planRow === false) {
            return false;
        }

        $avant = $this->limitesActuelles($etablissementId);
        $stmt = $this->pdo->prepare(
            "UPDATE etablissements SET plan_id = ? WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$planId, $etablissementId]);
        if ($stmt->rowCount() === 0) {
            return false;
        }

        $this->applyPlanLimits($etablissementId, $planId, $planRow);

        Logger::security('PLATFORM_TENANT_PLAN_ASSIGNED', "etablissement_id={$etablissementId} plan={$planRow['code']} par operator_id={$operatorUserId}");
        PlatformAuditService::make($this->pdo)->recordPar($operatorUserId, 'tenant.plan_assigned', [
            'etablissement_id' => $etablissementId, 'entity_type' => 'etablissement', 'entity_id' => $etablissementId,
            'avant' => $avant, 'apres' => ['plan_id' => $planId, 'plan_code' => $planRow['code']] + ($this->limitesActuelles($etablissementId) ?? []),
        ]);
        return true;
    }

    private function applyPlanLimits(int $etablissementId, int $planId, ?array $planRow = null): void
    {
        if ($planRow === null) {
            $stmt = $this->pdo->prepare("SELECT * FROM platform_plans WHERE id = ?");
            $stmt->execute([$planId]);
            $planRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($planRow === false) {
                return;
            }
        }

        $upd = $this->pdo->prepare(
            "UPDATE etablissements SET storage_quota_mb = ?, max_users = ?, max_eleves = ? WHERE id = ?"
        );
        $upd->execute([
            (int)$planRow['storage_quota_mb'],
            (int)$planRow['max_users'],
            (int)$planRow['max_eleves'],
            $etablissementId,
        ]);
    }

    /** Statut courant d'un établissement (null s'il n'existe pas) — ancienne valeur du journal d'audit. */
    private function statutActuel(int $id): ?string
    {
        $s = $this->pdo->prepare('SELECT statut FROM etablissements WHERE id = ?');
        $s->execute([$id]);
        $v = $s->fetchColumn();
        return $v === false ? null : (string)$v;
    }

    /** @return array{plan_id:?int, max_users:int, max_eleves:int, storage_quota_mb:int}|null */
    private function limitesActuelles(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT plan_id, max_users, max_eleves, storage_quota_mb FROM etablissements WHERE id = ?');
        $s->execute([$id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : ['plan_id' => $r['plan_id'] !== null ? (int)$r['plan_id'] : null, 'max_users' => (int)$r['max_users'],
                                      'max_eleves' => (int)$r['max_eleves'], 'storage_quota_mb' => (int)$r['storage_quota_mb']];
    }

    private const ACTIONS_AUDIT = [
        'PLATFORM_TENANT_ACTIVATED' => 'tenant.activated',
        'PLATFORM_TENANT_ARCHIVED'  => 'tenant.archived',
        'PLATFORM_TENANT_RESTORED'  => 'tenant.restored',
    ];

    private function setStatut(int $id, string $statut, string $logEvent, int $operatorUserId): bool
    {
        $avant = $this->statutActuel($id);
        $activatedClause = $statut === 'active' ? ", activated_at = COALESCE(activated_at, NOW())" : '';
        $stmt = $this->pdo->prepare(
            "UPDATE etablissements SET statut = ?{$activatedClause} WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$statut, $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            Logger::security($logEvent, "etablissement_id={$id} par operator_id={$operatorUserId}");
            PlatformAuditService::make($this->pdo)->recordPar($operatorUserId, self::ACTIONS_AUDIT[$logEvent] ?? 'tenant.status_changed', [
                'etablissement_id' => $id, 'entity_type' => 'etablissement', 'entity_id' => $id,
                'avant' => ['statut' => $avant], 'apres' => ['statut' => $statut],
            ]);
        }
        return $ok;
    }
}
