<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Services;

use Core\EventDispatcher;
use App\Modules\RH\Documents\DTO\HRDocumentDTO;
use App\Modules\RH\Documents\Models\HRDocumentModel;
use App\Modules\RH\Documents\Repositories\HRDocumentRepository;
use App\Modules\RH\Documents\Events\HRDocumentCreated;
use App\Modules\RH\Documents\Events\HRDocumentUpdated;
use App\Modules\RH\Documents\Events\HRDocumentExpired;
use App\Modules\RH\Documents\Events\HRDocumentArchived;

class HRDocumentService
{
    private HRDocumentRepository $repo;

    public function __construct()
    {
        $this->repo = new HRDocumentRepository();
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function creerDocument(HRDocumentDTO $dto, int $userId, string $userName): int
    {
        $errors = $dto->validate();
        if ($errors) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $id = $this->repo->insert(array_merge($dto->toArray(), [
            'statut'          => 'actif',
            'version_courante'=> 1,
            'created_by'      => $userId,
        ]));

        $this->repo->insertVersion([
            'document_id'      => $id,
            'version'          => 1,
            'reference_externe'=> $dto->referenceExterne,
            'notes_version'    => $dto->notesVersion ?? 'Création du document',
            'created_by'       => $userId,
            'created_by_nom'   => $userName,
        ]);

        $this->repo->insertHistorique([
            'document_id'   => $id,
            'action'        => 'creer',
            'ancien_statut' => null,
            'nouveau_statut'=> 'actif',
            'notes'         => null,
            'created_by'    => $userId,
            'created_by_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new HRDocumentCreated(
            $id, $dto->employeId, $dto->type, $dto->titre, $dto->confidentialite, $userId
        ));

        return $id;
    }

    // ── Mise à jour (nouvelle version) ────────────────────────────────────────

    public function mettreAJour(int $id, HRDocumentDTO $dto, int $userId, string $userName): void
    {
        $doc = $this->requireDocument($id);

        if (in_array($doc['statut'], ['archive'], true)) {
            throw new \RuntimeException('Un document archivé ne peut pas être modifié.');
        }

        $nouvelleVersion = (int)$doc['version_courante'] + 1;

        $this->repo->update($id, array_merge($dto->toArray(), [
            'version_courante' => $nouvelleVersion,
        ]));

        $this->repo->insertVersion([
            'document_id'      => $id,
            'version'          => $nouvelleVersion,
            'reference_externe'=> $dto->referenceExterne,
            'notes_version'    => $dto->notesVersion ?? 'Mise à jour',
            'created_by'       => $userId,
            'created_by_nom'   => $userName,
        ]);

        $this->repo->insertHistorique([
            'document_id'   => $id,
            'action'        => 'mettre_a_jour',
            'ancien_statut' => $doc['statut'],
            'nouveau_statut'=> $doc['statut'],
            'notes'         => "Version $nouvelleVersion" . ($dto->notesVersion ? ' — ' . $dto->notesVersion : ''),
            'created_by'    => $userId,
            'created_by_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new HRDocumentUpdated(
            $id, (int)$doc['employe_id'], $nouvelleVersion, 'mettre_a_jour', $userId
        ));
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archiverDocument(int $id, int $userId, string $userName, ?string $notes = null): void
    {
        $doc = $this->requireDocument($id);

        if ($doc['statut'] === 'archive') {
            throw new \RuntimeException('Ce document est déjà archivé.');
        }

        $this->repo->updateStatut($id, 'archive', [
            'archived_by' => $userId,
            'archived_at' => date('Y-m-d H:i:s'),
        ]);

        $this->repo->insertHistorique([
            'document_id'   => $id,
            'action'        => 'archiver',
            'ancien_statut' => $doc['statut'],
            'nouveau_statut'=> 'archive',
            'notes'         => $notes,
            'created_by'    => $userId,
            'created_by_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new HRDocumentArchived(
            $id, (int)$doc['employe_id'], $doc['type'], $doc['statut'], $userId
        ));
    }

    // ── Restauration ──────────────────────────────────────────────────────────

    public function restaurerDocument(int $id, int $userId, string $userName): void
    {
        $doc = $this->requireDocument($id);

        if ($doc['statut'] !== 'archive') {
            throw new \RuntimeException('Seul un document archivé peut être restauré.');
        }

        $statut = HRDocumentModel::isExpired($doc['date_expiration']) ? 'expire' : 'actif';

        $this->repo->updateStatut($id, $statut, [
            'archived_by' => null,
            'archived_at' => null,
        ]);

        $this->repo->insertHistorique([
            'document_id'   => $id,
            'action'        => 'restaurer',
            'ancien_statut' => 'archive',
            'nouveau_statut'=> $statut,
            'notes'         => null,
            'created_by'    => $userId,
            'created_by_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new HRDocumentUpdated(
            $id, (int)$doc['employe_id'], (int)$doc['version_courante'], 'restaurer', $userId
        ));
    }

    // ── Vérification expirations (batch cron) ─────────────────────────────────

    public function verifierExpirations(): int
    {
        $docs  = $this->repo->findExpired();
        $count = 0;

        foreach ($docs as $doc) {
            $this->repo->updateStatut((int)$doc['id'], 'expire');
            $this->repo->insertHistorique([
                'document_id'   => (int)$doc['id'],
                'action'        => 'expirer',
                'ancien_statut' => 'actif',
                'nouveau_statut'=> 'expire',
                'notes'         => 'Expiration automatique',
                'created_by'    => 0,
                'created_by_nom'=> 'Système',
            ]);
            EventDispatcher::dispatch(new HRDocumentExpired(
                (int)$doc['id'], (int)$doc['employe_id'],
                $doc['type'], $doc['date_expiration']
            ));
            $count++;
        }

        return $count;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireDocument(int $id): array
    {
        $doc = $this->repo->findById($id);
        if (!$doc) throw new \RuntimeException('Document introuvable.');
        return $doc;
    }
}
