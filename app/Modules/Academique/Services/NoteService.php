<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\NoteBatchDTO;
use App\Modules\Academique\DTO\NoteDTO;
use App\Modules\Academique\Events\NoteCreated;
use App\Modules\Academique\Events\NoteImported;
use App\Modules\Academique\Events\NoteLocked;
use App\Modules\Academique\Events\NotePublished;
use App\Modules\Academique\Events\NoteUpdated;
use App\Modules\Academique\Models\EvaluationModel;
use App\Modules\Academique\Models\NoteModel;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Repositories\NoteRepository;
use App\Modules\Academique\ValueObjects\BaremeValue;
use App\Modules\Academique\ValueObjects\NoteValue;
use App\Modules\Scolarite\Repositories\InscriptionRepository;
use Core\EventDispatcher;

class NoteService
{
    private NoteModel              $model;
    private NoteRepository         $repo;
    private EvaluationModel        $evalModel;
    private PeriodeScolaireModel   $periodeModel;
    private InscriptionRepository  $inscriptionRepo;

    public function __construct()
    {
        $this->model           = new NoteModel();
        $this->repo            = new NoteRepository();
        $this->evalModel       = new EvaluationModel();
        $this->periodeModel    = new PeriodeScolaireModel();
        $this->inscriptionRepo = new InscriptionRepository();
    }

    // ─── Saisie batch ────────────────────────────────────────────────────────

    public function saisirBatch(NoteBatchDTO $batch, int $userId): array
    {
        $evaluation = $this->evalModel->findById($batch->evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable.");
        }
        $this->assertSaisieOuverte($evaluation);

        $periode = $this->periodeModel->findById((int)$evaluation->periode_scolaire_id);
        $anneeScolaire = $periode->annee_scolaire ?? null;

        $bareme  = new BaremeValue((float)$evaluation->note_max);
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($batch->notes as $dto) {
            // AN-C-001 : une note ne peut être rattachée qu'à un élève
            // activement inscrit pour l'année scolaire de la période.
            if ($anneeScolaire !== null
                && !$this->inscriptionRepo->findActiveByEleve($dto->eleveId, $anneeScolaire)
            ) {
                $results['errors'][$dto->eleveId] =
                    "Élève non inscrit pour l'année scolaire {$anneeScolaire}.";
                continue;
            }

            try {
                $noteValue = new NoteValue(
                    $dto->estAbsent ? null : $dto->valeur,
                    $bareme
                );
                $this->upsertNote($dto, $noteValue, $evaluation, $userId, $results);
            } catch (\InvalidArgumentException $e) {
                $results['errors'][$dto->eleveId] = $e->getMessage();
            }
        }

        return $results;
    }

    // ─── Modifier une note individuelle ──────────────────────────────────────

    public function modifier(int $noteId, NoteDTO $dto, int $userId): void
    {
        $note = $this->model->findById($noteId);
        if (!$note) {
            throw new \RuntimeException("Note introuvable.");
        }
        if ($note->statut === 'verrouillee') {
            throw new \RuntimeException("Cette note est verrouillée et ne peut pas être modifiée.");
        }

        $evaluation = $this->evalModel->findById($note->evaluation_id);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation associée introuvable.");
        }
        $this->assertSaisieOuverte($evaluation);

        $bareme    = new BaremeValue((float)$evaluation->note_max);
        $noteValue = new NoteValue($dto->estAbsent ? null : $dto->valeur, $bareme);

        $oldValeur    = $note->valeur !== null ? (float)$note->valeur : null;
        $newValeur    = $noteValue->getValue();
        $oldAbsent    = (int)$note->est_absent;
        $newAbsent    = $dto->estAbsent ? 1 : 0;
        $valeurChange = (string)$oldValeur !== (string)$newValeur || $oldAbsent !== $newAbsent;

        if ($valeurChange) {
            $this->repo->insertHistorique(
                $noteId, $oldValeur, $newValeur, $oldAbsent, $newAbsent,
                $dto->commentaire, $userId
            );
        }

        $this->model->update($noteId, [
            'valeur'      => $newValeur,
            'est_absent'  => $newAbsent,
            'commentaire' => $dto->commentaire,
        ]);

        EventDispatcher::dispatch(new NoteUpdated(
            noteId:       $noteId,
            evaluationId: (int)$note->evaluation_id,
            eleveId:      (int)$note->eleve_id,
            oldValeur:    $oldValeur,
            newValeur:    $newValeur,
            updatedById:  $userId,
        ));
    }

    // ─── Publier toutes les notes d'une évaluation ───────────────────────────

    public function publierTout(int $evaluationId, int $userId): int
    {
        $evaluation = $this->evalModel->findById($evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable.");
        }

        $notes = $this->model->findByEvaluation($evaluationId);
        $count = 0;

        foreach ($notes as $note) {
            if ($note->statut === 'saisie') {
                $this->model->update((int)$note->id, ['statut' => 'publiee']);
                $count++;
            }
        }

        if ($count > 0) {
            EventDispatcher::dispatch(new NotePublished(
                evaluationId: $evaluationId,
                count:        $count,
                publishedById:$userId,
            ));
        }

        return $count;
    }

    // ─── Verrouiller toutes les notes d'une évaluation ───────────────────────

    public function verrouillerTout(int $evaluationId, int $userId): int
    {
        $evaluation = $this->evalModel->findById($evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable.");
        }
        if ($evaluation->statut !== 'verrouillee') {
            throw new \RuntimeException(
                "Verrouillez d'abord l'évaluation avant de verrouiller ses notes."
            );
        }

        $notes = $this->model->findByEvaluation($evaluationId);
        $count = 0;

        foreach ($notes as $note) {
            if ($note->statut !== 'verrouillee') {
                $this->model->update((int)$note->id, [
                    'statut'         => 'verrouillee',
                    'verrouille_par' => $userId,
                    'verrouille_le'  => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }
        }

        if ($count > 0) {
            EventDispatcher::dispatch(new NoteLocked(
                evaluationId: $evaluationId,
                count:        $count,
                lockedById:   $userId,
            ));
        }

        return $count;
    }

    // ─── Import CSV ──────────────────────────────────────────────────────────

    public function importerCsv(int $evaluationId, string $csvContent, int $userId): array
    {
        $rows    = array_map('str_getcsv', explode("\n", trim($csvContent)));
        $header  = array_shift($rows); // skip header row

        $batch   = NoteBatchDTO::fromCsvRows($evaluationId, $rows);
        $results = $this->saisirBatch($batch, $userId);

        $total = $results['created'] + $results['updated'];
        if ($total > 0) {
            EventDispatcher::dispatch(new NoteImported(
                evaluationId: $evaluationId,
                created:      $results['created'],
                updated:      $results['updated'],
                errors:       count($results['errors']),
                importedById: $userId,
            ));
        }

        return $results;
    }

    // ─── Privé ───────────────────────────────────────────────────────────────

    private function assertSaisieOuverte(object $evaluation): void
    {
        if (!(int)$evaluation->notes_saisie_ouverte) {
            throw new \RuntimeException(
                "La saisie de notes est fermée pour cette évaluation."
            );
        }
        if ($evaluation->statut === 'verrouillee') {
            throw new \RuntimeException(
                "L'évaluation est verrouillée. Aucune note ne peut être modifiée."
            );
        }
        if ($evaluation->statut === 'archivee') {
            throw new \RuntimeException(
                "L'évaluation est archivée."
            );
        }
    }

    private function upsertNote(
        NoteDTO   $dto,
        NoteValue $noteValue,
        object    $evaluation,
        int       $userId,
        array     &$results
    ): void {
        $existing = $this->model->findByEvaluationEtEleve(
            $dto->evaluationId, $dto->eleveId
        );

        if ($existing) {
            $oldValeur = $existing->valeur !== null ? (float)$existing->valeur : null;
            $newValeur = $noteValue->getValue();
            $oldAbsent = (int)$existing->est_absent;
            $newAbsent = $dto->estAbsent ? 1 : 0;
            $changed   = (string)$oldValeur !== (string)$newValeur
                      || $oldAbsent !== $newAbsent
                      || (string)($existing->commentaire ?? '') !== (string)($dto->commentaire ?? '');

            if (!$changed) return;

            if ((string)$oldValeur !== (string)$newValeur || $oldAbsent !== $newAbsent) {
                $this->repo->insertHistorique(
                    (int)$existing->id,
                    $oldValeur, $newValeur,
                    $oldAbsent, $newAbsent,
                    $dto->commentaire, $userId
                );
            }

            $this->model->update((int)$existing->id, [
                'valeur'      => $newValeur,
                'est_absent'  => $newAbsent,
                'commentaire' => $dto->commentaire,
            ]);

            EventDispatcher::dispatch(new NoteUpdated(
                noteId:       (int)$existing->id,
                evaluationId: $dto->evaluationId,
                eleveId:      $dto->eleveId,
                oldValeur:    $oldValeur,
                newValeur:    $newValeur,
                updatedById:  $userId,
            ));
            $results['updated']++;

        } else {
            $noteId = $this->model->insert([
                'evaluation_id' => $dto->evaluationId,
                'eleve_id'      => $dto->eleveId,
                'valeur'        => $noteValue->getValue(),
                'est_absent'    => $dto->estAbsent ? 1 : 0,
                'commentaire'   => $dto->commentaire,
                'statut'        => 'saisie',
                'created_by'    => $userId,
            ]);

            EventDispatcher::dispatch(new NoteCreated(
                noteId:       $noteId,
                evaluationId: $dto->evaluationId,
                eleveId:      $dto->eleveId,
                valeur:       $noteValue->getValue(),
                createdById:  $userId,
            ));
            $results['created']++;
        }
    }
}
