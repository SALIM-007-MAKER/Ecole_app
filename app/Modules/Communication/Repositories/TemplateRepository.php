<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class TemplateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_templates
             (code, nom, canal, langue, module_source, sujet, corps_html, corps_texte,
              variables, actif, etablissement_id, created_by)
             VALUES
             (:code, :nom, :canal, :langue, :module, :sujet, :html, :texte,
              :vars, 1, :etab, :by)"
        );
        $st->execute([
            ':code'   => $data['code'],
            ':nom'    => $data['nom'],
            ':canal'  => $data['canal'],
            ':langue' => $data['langue']        ?? 'fr',
            ':module' => $data['module_source'] ?? null,
            ':sujet'  => $data['sujet']         ?? null,
            ':html'   => $data['corps_html']    ?? null,
            ':texte'  => $data['corps_texte']   ?? null,
            ':vars'   => isset($data['variables']) ? json_encode($data['variables']) : null,
            ':etab'   => $data['etablissement_id'] ?? 1,
            ':by'     => $data['created_by']    ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_templates
             SET nom = :nom, sujet = :sujet, corps_html = :html, corps_texte = :texte,
                 variables = :vars, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        return $st->execute([
            ':id'    => $id,
            ':nom'   => $data['nom'],
            ':sujet' => $data['sujet']      ?? null,
            ':html'  => $data['corps_html'] ?? null,
            ':texte' => $data['corps_texte'] ?? null,
            ':vars'  => isset($data['variables']) ? json_encode($data['variables']) : null,
        ]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_templates WHERE id = :id AND deleted_at IS NULL"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByCode(string $code, string $canal, string $langue = 'fr', int $etablissementId = 1): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_templates
             WHERE code = :code AND canal = :canal AND langue = :langue
               AND etablissement_id = :etab AND actif = 1 AND deleted_at IS NULL
             LIMIT 1"
        );
        $st->execute([':code' => $code, ':canal' => $canal, ':langue' => $langue, ':etab' => $etablissementId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(?string $canal = null, ?string $moduleSource = null, int $etablissementId = 1): array
    {
        $where = ['deleted_at IS NULL', 'etablissement_id = :etab'];
        $params = [':etab' => $etablissementId];

        if ($canal !== null) {
            $where[]          = 'canal = :canal';
            $params[':canal'] = $canal;
        }
        if ($moduleSource !== null) {
            $where[]           = 'module_source = :module';
            $params[':module'] = $moduleSource;
        }

        $sql = 'SELECT * FROM com_templates WHERE ' . implode(' AND ', $where) . ' ORDER BY code, canal, langue';
        $st  = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function softDelete(int $id): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_templates SET deleted_at = NOW(), actif = 0 WHERE id = :id"
        );
        return $st->execute([':id' => $id]);
    }
}
