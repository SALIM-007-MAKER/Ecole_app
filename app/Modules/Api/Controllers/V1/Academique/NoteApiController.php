<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Academique;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Exceptions\ValidationException;
use App\Modules\Api\Resources\Academique\NoteResource;
use Core\Database;

class NoteApiController extends ResourceApiController
{
    /** GET /api/v1/notes */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('notes.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $filters = $this->parseFilters(['eleve_id', 'matiere_id', 'classe_id', 'periode_id', 'annee_scolaire']);
        $sort    = $this->parseSort(['date_evaluation', 'valeur', 'created_at'], 'date_evaluation');

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['n.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];
        foreach ($filters['conditions'] as $c) { $conditions[] = $c; }
        array_push($bindings, ...$filters['bindings']);

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM notes n $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT n.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, m.nom AS matiere_nom
             FROM notes n
             JOIN eleves e ON e.id = n.eleve_id
             JOIN matieres m ON m.id = n.matiere_id
             $where ORDER BY $sort LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->respondCollection(
            NoteResource::collection($rows, $ctx),
            $total,
            $paging,
            '/api/v1/notes'
        );
    }

    /** GET /api/v1/notes/{id} */
    public function show(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('notes.view');
        $row = $this->findOne((int)$id, $ctx->etablissementId);
        $this->apiSuccess((new NoteResource($row))->toArray($ctx));
    }

    /** POST /api/v1/notes/batch — saisie en masse */
    public function batch(): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('notes.create');
        $body = $this->body();

        if (empty($body['notes']) || !is_array($body['notes'])) {
            throw new ValidationException(['notes' => 'Le tableau de notes est requis.']);
        }

        $pdo      = Database::getInstance()->getConnection();
        $inserted = 0;
        $errors   = [];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO notes (eleve_id, matiere_id, periode_id, classe_id, valeur, bareme,
                 type_evaluation_id, date_evaluation, appreciation, annee_scolaire, etablissement_id, created_by, created_at)
                 VALUES (:eid, :mid, :pid, :cid, :val, :bar, :teid, :dat, :app, :ann, :etab, :by, NOW())
                 ON DUPLICATE KEY UPDATE valeur=:val2, updated_at=NOW()'
            );

            foreach ($body['notes'] as $i => $note) {
                if (!isset($note['eleve_id'], $note['matiere_id'], $note['valeur'])) {
                    $errors[] = "Index $i: eleve_id, matiere_id, valeur requis.";
                    continue;
                }
                $stmt->execute([
                    ':eid'  => (int)$note['eleve_id'],
                    ':mid'  => (int)$note['matiere_id'],
                    ':pid'  => isset($note['periode_id']) ? (int)$note['periode_id'] : null,
                    ':cid'  => isset($note['classe_id']) ? (int)$note['classe_id'] : null,
                    ':val'  => (float)$note['valeur'],
                    ':val2' => (float)$note['valeur'],
                    ':bar'  => (float)($note['bareme'] ?? 20),
                    ':teid' => isset($note['type_evaluation_id']) ? (int)$note['type_evaluation_id'] : null,
                    ':dat'  => $note['date_evaluation'] ?? date('Y-m-d'),
                    ':app'  => $note['appreciation'] ?? null,
                    ':ann'  => $note['annee_scolaire'] ?? null,
                    ':etab' => $ctx->etablissementId,
                    ':by'   => $ctx->userId,
                ]);
                $inserted++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->apiSuccess([
            'inserted' => $inserted,
            'errors'   => $errors,
        ]);
    }

    private function findOne(int $id, int $etab): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT n.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, m.nom AS matiere_nom
             FROM notes n JOIN eleves e ON e.id=n.eleve_id JOIN matieres m ON m.id=n.matiere_id
             WHERE n.id=:id AND n.etablissement_id=:etab LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':etab' => $etab]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('Note');
        return $row;
    }
}
