<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\AppreciationBatchDTO;
use App\Modules\Academique\Events\AppreciationMatiereSaisie;
use App\Modules\Academique\Repositories\AppreciationMatiereRepository;
use Core\EventDispatcher;

class AppreciationService
{
    private AppreciationMatiereRepository $repo;

    public function __construct()
    {
        $this->repo = new AppreciationMatiereRepository();
    }

    public function saisirBatch(
        AppreciationBatchDTO $batch,
        int                  $etablissementId,
        ?int                 $profId,
        int                  $userId
    ): array {
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($batch->appreciations as $dto) {
            $errors = $dto->validate();
            if ($errors) {
                $results['errors'][$dto->eleveId] = $errors;
                continue;
            }

            $result = $this->repo->upsert(
                $dto->eleveId,
                $batch->matiereId,
                $batch->periodeId,
                $batch->classeId,
                $etablissementId,
                $dto->texte,
                $profId,
                $userId
            );

            EventDispatcher::dispatch(new AppreciationMatiereSaisie(
                appreciationId: $result['id'],
                eleveId:        $dto->eleveId,
                matiereId:      $batch->matiereId,
                periodeId:      $batch->periodeId,
                created:        $result['created'],
                saisieById:     $userId,
            ));

            $result['created'] ? $results['created']++ : $results['updated']++;
        }

        return $results;
    }
}
