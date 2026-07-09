<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Academique;

use App\Modules\Api\Controllers\ResourceApiController;
use Core\Database;

class ClassementApiController extends ResourceApiController
{
    /** GET /api/v1/classements/classe/{classeId} */
    public function parClasse(string $classeId): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('classements.view');
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $periodeId = (int)($_GET['periode_id'] ?? 0);

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['b.classe_id = ?', 'b.etablissement_id = ?'];
        $bindings   = [(int)$classeId, $ctx->etablissementId];

        if ($periodeId > 0) {
            $conditions[] = 'b.periode_id = ?';
            $bindings[]   = $periodeId;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM bulletins_v2 b $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT b.rang, b.moyenne_generale, b.mention,
                    e.id AS eleve_id, e.nom, e.prenom, e.matricule
             FROM bulletins_v2 b
             JOIN eleves e ON e.id = b.eleve_id
             $where ORDER BY b.rang ASC LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'rang'             => isset($r['rang']) ? (int)$r['rang'] : null,
            'eleve_id'         => (int)$r['eleve_id'],
            'nom'              => $r['nom'],
            'prenom'           => $r['prenom'],
            'matricule'        => $r['matricule'],
            'moyenne_generale' => round((float)$r['moyenne_generale'], 2),
            'mention'          => $r['mention'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, "/api/v1/classements/classe/$classeId");
    }

    /** GET /api/v1/classements/global */
    public function global(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('classements.view');
        $this->checkRateLimit();

        $paging    = $this->parsePagination();
        $annee     = $_GET['annee_scolaire'] ?? null;
        $periodeId = (int)($_GET['periode_id'] ?? 0);

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['b.etablissement_id = ?'];
        $bindings   = [$ctx->etablissementId];

        if ($annee) { $conditions[] = 'b.annee_scolaire = ?'; $bindings[] = $annee; }
        if ($periodeId > 0) { $conditions[] = 'b.periode_id = ?'; $bindings[] = $periodeId; }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM bulletins_v2 b $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT b.rang_general, b.moyenne_generale, b.mention,
                    e.id AS eleve_id, e.nom, e.prenom, e.matricule,
                    c.nom AS classe_nom
             FROM bulletins_v2 b
             JOIN eleves e ON e.id = b.eleve_id
             LEFT JOIN classes c ON c.id = b.classe_id
             $where ORDER BY b.rang_general ASC LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'rang_general'     => isset($r['rang_general']) ? (int)$r['rang_general'] : null,
            'eleve_id'         => (int)$r['eleve_id'],
            'nom'              => $r['nom'],
            'prenom'           => $r['prenom'],
            'matricule'        => $r['matricule'],
            'classe_nom'       => $r['classe_nom'],
            'moyenne_generale' => round((float)$r['moyenne_generale'], 2),
            'mention'          => $r['mention'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/classements/global');
    }
}
