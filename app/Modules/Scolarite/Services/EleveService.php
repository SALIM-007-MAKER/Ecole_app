<?php

namespace App\Modules\Scolarite\Services;

use App\Events\EleveCreated;
use App\Events\ImportCsvCompleted;
use App\Models\EleveModel;
use App\Modules\Scolarite\DTO\EleveDTO;
use App\Modules\Scolarite\Events\EleveUpdated;
use App\Modules\Scolarite\Events\EleveArchived;
use App\Services\AuditService;
use App\Services\UploadService;
use Core\EventDispatcher;

class EleveService
{
    private EleveModel   $model;
    private AuditService $audit;
    private UploadService $upload;

    public function __construct()
    {
        $this->model  = new EleveModel();
        $this->audit  = new AuditService();
        $this->upload = new UploadService();
    }

    public function creer(EleveDTO $dto, ?array $photoFile, int $userId): int
    {
        $data = $dto->toArray();

        if (!empty($photoFile['name']) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $data['photo'] = $this->upload->upload($photoFile, 'photo_eleve', 'eleve_');
            } catch (\RuntimeException) {
                // Silently skip photo — validation errors are caught upstream
            }
        }

        $eleveId = $this->model->insert($data);
        if ($eleveId === 0) {
            throw new \RuntimeException("Erreur lors de la création de l'élève.");
        }

        $this->audit->logCreate($userId, 'scolarite', 'eleve', $eleveId, $data);

        EventDispatcher::dispatch(new EleveCreated(
            eleveId:     $eleveId,
            nom:         $dto->nom,
            prenom:      $dto->prenom,
            matricule:   $dto->matricule,
            classeId:    $dto->classeId ?? 0,
            createdById: $userId,
        ));

        return $eleveId;
    }

    public function modifier(int $eleveId, EleveDTO $dto, ?array $photoFile, int $userId): void
    {
        $avant = $this->model->findById($eleveId);
        $data  = $dto->toArray();

        if (!empty($photoFile['name']) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                if (!empty($avant->photo)) {
                    $this->upload->delete($avant->photo);
                    $legacyPath = ROOT_PATH . '/public/' . $avant->photo;
                    if (is_file($legacyPath)) {
                        @unlink($legacyPath);
                    }
                }
                $data['photo'] = $this->upload->upload($photoFile, 'photo_eleve', 'eleve_');
            } catch (\RuntimeException) {
                unset($data['photo']);
            }
        } else {
            unset($data['photo']);
        }

        $this->model->update($eleveId, $data);

        $changedFields = [];
        if ($avant) {
            foreach ($data as $k => $v) {
                $prev = $avant->$k ?? null;
                if ((string)$prev !== (string)$v) {
                    $changedFields['avant'][$k] = $prev;
                    $changedFields['apres'][$k] = $v;
                }
            }
        }

        $this->audit->logUpdate(
            $userId,
            'scolarite',
            'eleve',
            $eleveId,
            (array)$avant,
            array_merge((array)$avant, $data),
        );

        EventDispatcher::dispatch(new EleveUpdated($eleveId, $userId, $changedFields));
    }

    public function archiver(int $eleveId, int $userId, string $motif = ''): void
    {
        $this->model->update($eleveId, ['actif' => 0]);

        $this->audit->log(
            $userId,
            'archive',
            'scolarite',
            'eleve',
            $eleveId,
            null,
            ['motif' => $motif],
        );

        EventDispatcher::dispatch(new EleveArchived($eleveId, $userId, $motif));
    }

    public function supprimer(int $eleveId, int $userId): void
    {
        $eleve = $this->model->findById($eleveId);
        if ($eleve && !empty($eleve->photo)) {
            $this->upload->delete($eleve->photo);
            $legacyPath = ROOT_PATH . '/public/' . $eleve->photo;
            if (is_file($legacyPath)) {
                @unlink($legacyPath);
            }
        }

        $this->audit->logDelete($userId, 'scolarite', 'eleve', $eleveId, (array)$eleve);
        $this->model->delete($eleveId);
    }

    public function genererMatricule(): string
    {
        return $this->model->generateMatricule();
    }

    public function matriculeUnique(string $matricule, int $excludeId = 0): bool
    {
        return !$this->model->matriculeExists($matricule, $excludeId);
    }

    public function importerCsv(array $rows, array $classeMap, int $userId): array
    {
        $result = $this->model->importFromCsv($rows, $classeMap);

        foreach ($result['ids'] ?? [] as $id => $data) {
            EventDispatcher::dispatch(new EleveCreated(
                eleveId:      (int)$id,
                nom:          $data['nom']       ?? '',
                prenom:       $data['prenom']    ?? '',
                matricule:    $data['matricule'] ?? '',
                classeId:     $data['classe_id'] ?? 0,
                createdById:  $userId,
                fromCsvImport: true,
            ));
        }

        EventDispatcher::dispatch(new ImportCsvCompleted(
            importedById:   $userId,
            totalImported:  $result['imported'] ?? 0,
            totalSkipped:   $result['skipped']  ?? 0,
            filename:       'import_eleves.csv',
            classeId:       0,
            errors:         $result['errors']   ?? [],
        ));

        return $result;
    }
}
