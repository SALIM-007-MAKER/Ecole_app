<?php

namespace App\Models;

use Core\Model;

class EleveModel extends Model
{
    protected string $table = 'eleves';
    protected bool $tenantScoped = true;

    // ─── Requête de base avec JOIN ────────────────────────────────────────────

    private function baseFrom(): string
    {
        return "FROM `eleves` e
                LEFT JOIN `classes` c  ON c.id = e.classe_id
                LEFT JOIN `users`   u  ON u.id = e.parent_id";
    }

    private function baseSelect(): string
    {
        return "SELECT e.*,
                c.nom    AS classe_nom,
                c.niveau AS classe_niveau,
                TRIM(CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,''))) AS parent_nom,
                u.telephone AS parent_telephone,
                u.email     AS parent_email";
    }

    // ─── Construction du WHERE dynamique ─────────────────────────────────────

    private function buildWhere(array $filters): array
    {
        $conditions = ["e.etablissement_id = ?"];
        $params     = [$this->tenantId()];

        if (!empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR e.telephone LIKE ?)";
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['classe_id'])) {
            $conditions[] = "e.classe_id = ?";
            $params[] = (int)$filters['classe_id'];
        }

        if (in_array($filters['sexe'] ?? '', ['M', 'F'], true)) {
            $conditions[] = "e.sexe = ?";
            $params[] = $filters['sexe'];
        }

        // Filtre actif : par défaut afficher les actifs
        $actif = $filters['actif'] ?? '1';
        if ($actif !== 'all') {
            $conditions[] = "e.actif = ?";
            $params[] = (int)$actif;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    // ─── Lecture avec détails ─────────────────────────────────────────────────

    public function findWithDetails(int $id): object|false
    {
        return $this->queryOne(
            $this->baseSelect() . ' ' . $this->baseFrom() . ' WHERE e.id = ? AND e.etablissement_id = ?',
            [$id, $this->tenantId()]
        );
    }

    public function paginateFiltered(int $page, int $perPage = 15, array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $from  = $this->baseFrom();
        $order = "ORDER BY e.nom ASC, e.prenom ASC";

        // Comptage
        $countStmt = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Données paginées
        $offset   = ($page - 1) * $perPage;
        $dataStmt = $this->db->prepare(
            $this->baseSelect() . " {$from} {$where} {$order} LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);

        return [
            'data'         => $dataStmt->fetchAll(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    public function findAllFiltered(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare(
            $this->baseSelect() . ' ' . $this->baseFrom() .
            " {$where} ORDER BY e.nom ASC, e.prenom ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByClasse(int $classeId): array
    {
        return $this->query(
            "SELECT * FROM `eleves` WHERE classe_id = ? AND actif = 1 AND etablissement_id = ? ORDER BY nom, prenom",
            [$classeId, $this->tenantId()]
        );
    }

    public function findByParent(int $parentId): array
    {
        return $this->query(
            $this->baseSelect() . ' ' . $this->baseFrom() .
            " WHERE e.parent_id = ? AND e.actif = 1 AND e.etablissement_id = ? ORDER BY e.nom",
            [$parentId, $this->tenantId()]
        );
    }

    public function findByEmail(string $email): object|false
    {
        return $this->queryOne(
            $this->baseSelect() . ' ' . $this->baseFrom() .
            " WHERE e.email = ? AND e.etablissement_id = ? LIMIT 1",
            [$email, $this->tenantId()]
        );
    }

    /** M007 — lien fiable eleves.user_id → users(id), à préférer à findByEmail(). */
    public function findByUserId(int $userId): object|false
    {
        return $this->queryOne(
            $this->baseSelect() . ' ' . $this->baseFrom() .
            " WHERE e.user_id = ? AND e.etablissement_id = ? LIMIT 1",
            [$userId, $this->tenantId()]
        );
    }

    // ─── Statistiques ─────────────────────────────────────────────────────────

    public function countByClasse(): array
    {
        return $this->query(
            "SELECT c.nom AS classe_nom, c.niveau, COUNT(e.id) AS nb
             FROM `classes` c
             LEFT JOIN `eleves` e ON e.classe_id = c.id AND e.actif = 1 AND e.etablissement_id = ?
             WHERE c.etablissement_id = ?
             GROUP BY c.id
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom",
            [$this->tenantId(), $this->tenantId()]
        );
    }

    public function countBySexe(): array
    {
        return $this->query(
            "SELECT sexe, COUNT(*) AS nb FROM `eleves` WHERE actif = 1 AND etablissement_id = ? GROUP BY sexe",
            [$this->tenantId()]
        );
    }

    // ─── Matricule ────────────────────────────────────────────────────────────

    public function generateMatricule(): string
    {
        $year = date('Y');
        $stmt = $this->db->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(matricule, '-', -1) AS UNSIGNED)) AS max_seq
             FROM `eleves` WHERE matricule LIKE ? AND etablissement_id = ?"
        );
        $stmt->execute([$year . '-%', $this->tenantId()]);
        $row  = $stmt->fetch();
        $next = (int)($row->max_seq ?? 0) + 1;
        return sprintf('%s-%04d', $year, $next);
    }

    public function matriculeExists(string $matricule, int $excludeId = 0): bool
    {
        $sql    = "SELECT COUNT(*) FROM `eleves` WHERE matricule = ? AND etablissement_id = ?";
        $params = [$matricule, $this->tenantId()];
        if ($excludeId > 0) {
            $sql    .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ─── Import CSV ───────────────────────────────────────────────────────────

    public function importFromCsv(array $rows, array $classeMap): array
    {
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'ids' => []];

        foreach ($rows as $lineNum => $row) {
            $nom      = trim($row['nom']      ?? '');
            $prenom   = trim($row['prenom']   ?? '');
            $sexe     = strtoupper(trim($row['sexe'] ?? ''));
            $dob      = trim($row['date_naissance'] ?? '');
            $classeNom = trim($row['classe'] ?? '');

            if (empty($nom) || empty($prenom) || !in_array($sexe, ['M', 'F'], true) || empty($dob)) {
                $results['errors'][] = "Ligne {$lineNum} : données obligatoires manquantes (nom, prénom, sexe, date_naissance).";
                $results['skipped']++;
                continue;
            }

            // Normaliser la date
            $date = date_create($dob);
            if (!$date) {
                $results['errors'][] = "Ligne {$lineNum} : date '{$dob}' invalide.";
                $results['skipped']++;
                continue;
            }

            $matricule = trim($row['matricule'] ?? '');
            if (empty($matricule)) {
                $matricule = $this->generateMatricule();
            } elseif ($this->matriculeExists($matricule)) {
                $results['errors'][] = "Ligne {$lineNum} : matricule '{$matricule}' déjà utilisé.";
                $results['skipped']++;
                continue;
            }

            $classeId = !empty($classeNom) && isset($classeMap[$classeNom]) ? $classeMap[$classeNom] : null;

            $newId = $this->insert([
                'matricule'       => $matricule,
                'nom'             => $nom,
                'prenom'          => $prenom,
                'sexe'            => $sexe,
                'date_naissance'  => date_format($date, 'Y-m-d'),
                'adresse'         => trim($row['adresse']   ?? ''),
                'telephone'       => trim($row['telephone'] ?? ''),
                'email'           => trim($row['email']     ?? ''),
                'classe_id'       => $classeId,
                'actif'           => 1,
            ]);

            // Stocker l'ID + données pour le dispatch EleveCreated par ligne
            $results['ids'][$newId] = [
                'nom'       => $nom,
                'prenom'    => $prenom,
                'matricule' => $matricule,
                'classe_id' => $classeId,
            ];

            $results['imported']++;
        }

        return $results;
    }
}
