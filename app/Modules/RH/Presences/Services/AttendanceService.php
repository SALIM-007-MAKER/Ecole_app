<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Services;

use App\Modules\RH\Presences\DTO\AttendanceDTO;
use App\Modules\RH\Presences\DTO\AttendanceFiltersDTO;
use App\Modules\RH\Presences\DTO\RegularisationDTO;
use App\Modules\RH\Presences\Repositories\AttendanceRepository;
use App\Modules\RH\Presences\Events\AttendanceCreated;
use App\Modules\RH\Presences\Events\AttendanceUpdated;
use App\Modules\RH\Presences\Events\AttendanceValidated;
use App\Modules\RH\Presences\Events\AttendanceLateDetected;
use App\Modules\RH\Presences\Events\AttendanceOvertimeDetected;
use Core\EventDispatcher;

class AttendanceService
{
    private AttendanceRepository $repo;

    public function __construct()
    {
        $this->repo = new AttendanceRepository();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function paginate(AttendanceFiltersDTO $f): array
    {
        $total = $this->repo->count($f);
        return [
            'items' => $this->repo->findAll($f),
            'total' => $total,
            'page'  => $f->page,
            'pages' => (int)ceil(max(1, $total) / $f->perPage),
        ];
    }

    public function findById(int $id): array
    {
        $p = $this->repo->findById($id);
        if ($p === null) {
            throw new \RuntimeException('Pointage introuvable.');
        }
        return $p;
    }

    public function findByEmploye(int $employeId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        return $this->repo->findByEmploye($employeId, $dateDebut, $dateFin);
    }

    public function findEnAttente(): array
    {
        return $this->repo->findEnAttente();
    }

    public function statistiques(): array
    {
        return $this->repo->statistiques();
    }

    public function findRegularisations(int $presenceId): array
    {
        return $this->repo->findRegularisations($presenceId);
    }

    public function referentiels(?int $employeId = null): array
    {
        return [
            'employes'     => $this->repo->findEmployes(),
            'affectations' => $this->repo->findAffectationsActives($employeId),
            'departements' => $this->repo->findDepartements(),
        ];
    }

    // ── Création (pointage) ───────────────────────────────────────────────────

    public function creer(AttendanceDTO $dto, int $userId, string $userName): int
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        // Règle : un seul pointage par employé par jour
        if ($this->repo->hasPresenceForDate($dto->employeId, $dto->datePresence)) {
            throw new \RuntimeException(
                'Un pointage existe déjà pour cet employé à cette date. '
                . 'Utilisez la régularisation pour le modifier.'
            );
        }

        // Calculs automatiques
        $dureeMinutes     = null;
        $retardMinutes    = 0;
        $heuresSuppMin    = 0;

        if ($dto->heureArrivee !== null && $dto->heureDepart !== null) {
            $dureeMinutes  = $this->calculerDuree($dto->heureArrivee, $dto->heureDepart);
            $heuresSuppMin = $this->detecterHeuresSup($dureeMinutes, $dto->dureeReferenceMinutes);
        }

        if ($dto->heureArrivee !== null) {
            $retardMinutes = $this->detecterRetard($dto->heureArrivee, $dto->heureReferenceArrivee);
        }

        $data = array_merge($dto->toArray(), [
            'duree_minutes'      => $dureeMinutes,
            'retard_minutes'     => $retardMinutes,
            'heures_supp_minutes'=> $heuresSuppMin,
            'created_by'         => $userId,
            'updated_by'         => $userId,
        ]);

        $id = $this->repo->insert($data);

        EventDispatcher::dispatch(new AttendanceCreated(
            $id, $dto->employeId, $dto->datePresence, $dto->statut, $dto->modePointage, $userId
        ));

        // Événements secondaires : retard et heures supplémentaires
        if ($retardMinutes > 0) {
            EventDispatcher::dispatch(new AttendanceLateDetected(
                $id, $dto->employeId, $dto->datePresence,
                $retardMinutes, $dto->heureArrivee,
                $dto->heureReferenceArrivee, $userId
            ));
        }

        if ($heuresSuppMin > 0) {
            EventDispatcher::dispatch(new AttendanceOvertimeDetected(
                $id, $dto->employeId, $dto->datePresence,
                $heuresSuppMin, $dureeMinutes ?? 0,
                $dto->dureeReferenceMinutes, $userId
            ));
        }

        return $id;
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function modifier(int $id, AttendanceDTO $dto, int $userId): void
    {
        $existing = $this->findById($id);

        if ($existing['statut_validation'] === 'valide') {
            throw new \RuntimeException(
                'Ce pointage est validé. Utilisez la régularisation pour le corriger.'
            );
        }

        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        // Vérifier doublon si la date change
        if ($dto->datePresence !== $existing['date_presence']
            && $this->repo->hasPresenceForDate($dto->employeId, $dto->datePresence, $id)) {
            throw new \RuntimeException('Un pointage existe déjà pour cet employé à cette date.');
        }

        // Recalculer durée et dépassements
        $dureeMinutes  = null;
        $retardMinutes = 0;
        $heuresSuppMin = 0;

        if ($dto->heureArrivee !== null && $dto->heureDepart !== null) {
            $dureeMinutes  = $this->calculerDuree($dto->heureArrivee, $dto->heureDepart);
            $heuresSuppMin = $this->detecterHeuresSup($dureeMinutes, $dto->dureeReferenceMinutes);
        }
        if ($dto->heureArrivee !== null) {
            $retardMinutes = $this->detecterRetard($dto->heureArrivee, $dto->heureReferenceArrivee);
        }

        $new  = array_merge($dto->toArray(), [
            'duree_minutes'       => $dureeMinutes,
            'retard_minutes'      => $retardMinutes,
            'heures_supp_minutes' => $heuresSuppMin,
            'updated_by'          => $userId,
        ]);

        $diff = array_filter($new, fn($v, $k) => ($existing[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);

        if ($diff !== []) {
            $this->repo->update($id, $diff);
            EventDispatcher::dispatch(new AttendanceUpdated(
                $id, (int)$existing['employe_id'], 'modification', $diff, $userId
            ));
        }
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function valider(int $id, string $decision, ?string $motifRejet, int $userId, string $userName): void
    {
        if (!in_array($decision, ['valide', 'rejete'], true)) {
            throw new \InvalidArgumentException('Décision invalide. Valeurs acceptées : valide, rejete.');
        }

        $p = $this->findById($id);

        if ($p['statut_validation'] !== 'en_attente') {
            throw new \RuntimeException('Ce pointage a déjà été traité (validé ou rejeté).');
        }

        if ($decision === 'rejete' && empty($motifRejet)) {
            throw new \InvalidArgumentException('Le motif de rejet est obligatoire.');
        }

        $data = [
            'statut_validation' => $decision,
            'valide_par'        => $userId,
            'date_validation'   => date('Y-m-d H:i:s'),
            'motif_rejet'       => $decision === 'rejete' ? $motifRejet : null,
            'updated_by'        => $userId,
        ];

        $this->repo->update($id, $data);

        EventDispatcher::dispatch(new AttendanceValidated(
            $id, (int)$p['employe_id'], $p['date_presence'], $decision, $motifRejet, $userId
        ));

        EventDispatcher::dispatch(new AttendanceUpdated(
            $id, (int)$p['employe_id'], $decision, $data, $userId
        ));
    }

    // ── Justification ─────────────────────────────────────────────────────────

    public function justifier(int $id, string $justification, int $userId, string $userName): void
    {
        if (trim($justification) === '') {
            throw new \InvalidArgumentException('La justification ne peut pas être vide.');
        }

        $p = $this->findById($id);

        $data = [
            'justification'    => $justification,
            'justifie_par'     => $userId,
            'date_justification'=> date('Y-m-d H:i:s'),
            'updated_by'        => $userId,
        ];

        $this->repo->update($id, $data);

        // Logger la régularisation
        $this->repo->insertRegularisation([
            'presence_id'         => $id,
            'type_regularisation' => 'justification',
            'valeur_ancienne'     => json_encode(['justification' => $p['justification'] ?? null]),
            'valeur_nouvelle'     => json_encode(['justification' => $justification]),
            'motif'               => 'Justification ajoutée',
            'regularise_par'      => $userId,
            'regularise_par_nom'  => $userName,
        ]);

        EventDispatcher::dispatch(new AttendanceUpdated(
            $id, (int)$p['employe_id'], 'justification', $data, $userId
        ));
    }

    // ── Régularisation ────────────────────────────────────────────────────────

    public function regulariser(int $id, RegularisationDTO $dto, int $userId, string $userName): void
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $p       = $this->findById($id);
        $changes = $dto->changesArray();

        if ($dto->typeRegularisation === 'annulation') {
            $this->repo->softDelete($id);
            $this->repo->insertRegularisation([
                'presence_id'         => $id,
                'type_regularisation' => 'annulation',
                'valeur_ancienne'     => json_encode(['statut' => $p['statut']]),
                'valeur_nouvelle'     => json_encode(['deleted_at' => date('Y-m-d H:i:s')]),
                'motif'               => $dto->motif,
                'regularise_par'      => $userId,
                'regularise_par_nom'  => $userName,
            ]);
            EventDispatcher::dispatch(new AttendanceUpdated(
                $id, (int)$p['employe_id'], 'archivage', ['motif' => $dto->motif], $userId
            ));
            return;
        }

        if ($changes !== []) {
            // Recalculer durée si les heures changent
            $heureArrivee = $changes['heure_arrivee'] ?? $p['heure_arrivee'];
            $heureDepart  = $changes['heure_depart']  ?? $p['heure_depart'];

            if ($heureArrivee !== null && $heureDepart !== null) {
                $changes['duree_minutes']      = $this->calculerDuree($heureArrivee, $heureDepart);
                $changes['heures_supp_minutes']= $this->detecterHeuresSup(
                    $changes['duree_minutes'], (int)$p['duree_reference_minutes']
                );
            }
            if ($heureArrivee !== null) {
                $changes['retard_minutes'] = $this->detecterRetard(
                    $heureArrivee, $p['heure_reference_arrivee'] ?? '08:00'
                );
            }

            $changes['updated_by']  = $userId;
            // Repasser en attente après une régularisation (revalider)
            $changes['statut_validation'] = 'en_attente';
            $changes['valide_par']        = null;
            $changes['date_validation']   = null;
            $changes['motif_rejet']       = null;

            $this->repo->update($id, $changes);
        }

        $ancienne = array_intersect_key($p, array_flip(array_keys($changes)));
        $this->repo->insertRegularisation([
            'presence_id'         => $id,
            'type_regularisation' => $dto->typeRegularisation,
            'valeur_ancienne'     => json_encode($ancienne),
            'valeur_nouvelle'     => json_encode($changes),
            'motif'               => $dto->motif,
            'regularise_par'      => $userId,
            'regularise_par_nom'  => $userName,
        ]);

        EventDispatcher::dispatch(new AttendanceUpdated(
            $id, (int)$p['employe_id'], 'regularisation', $changes, $userId
        ));
    }

    // ── Archivage / Restauration ──────────────────────────────────────────────

    public function archiver(int $id, string $motif, int $userId): void
    {
        $p = $this->findById($id);

        $this->repo->softDelete($id);

        EventDispatcher::dispatch(new AttendanceUpdated(
            $id, (int)$p['employe_id'], 'archivage', ['motif' => $motif], $userId
        ));
    }

    public function restaurer(int $id, int $userId): void
    {
        $this->repo->restore($id);

        $p = $this->repo->findById($id) ?? ['employe_id' => 0];

        EventDispatcher::dispatch(new AttendanceUpdated(
            $id, (int)$p['employe_id'], 'restauration', [], $userId
        ));
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exporterCsv(AttendanceFiltersDTO $f): string
    {
        $allFilters = new AttendanceFiltersDTO(
            q: $f->q, statut: $f->statut, statutValidation: $f->statutValidation,
            employeId: $f->employeId, departementId: $f->departementId,
            dateDebut: $f->dateDebut, dateFin: $f->dateFin,
            datePresence: $f->datePresence, mode: $f->mode,
            includeArch: $f->includeArch, page: 1, perPage: 9999
        );
        $rows = $this->repo->findAll($allFilters);

        $bom  = "\xEF\xBB\xBF";
        $cols = ['Employé','Matricule','Date','Arrivée','Départ','Durée (min)','Statut','Mode','Retard (min)','Heures sup (min)','Validation'];
        $lines = [implode(';', $cols)];

        foreach ($rows as $r) {
            $lines[] = implode(';', [
                $r['employe_nom'],
                $r['employe_matricule'],
                $r['date_presence'],
                $r['heure_arrivee']        ?? '',
                $r['heure_depart']         ?? '',
                $r['duree_minutes']        ?? '',
                $r['statut'],
                $r['mode_pointage'],
                $r['retard_minutes']       ?? 0,
                $r['heures_supp_minutes']  ?? 0,
                $r['statut_validation'],
            ]);
        }

        return $bom . implode("\n", $lines);
    }

    // ── Calculs métier (centralisés) ──────────────────────────────────────────

    public function calculerDuree(string $heureArrivee, string $heureDepart): int
    {
        $arrivee = \DateTime::createFromFormat('H:i', substr($heureArrivee, 0, 5));
        $depart  = \DateTime::createFromFormat('H:i', substr($heureDepart,  0, 5));

        if (!$arrivee || !$depart || $depart <= $arrivee) {
            return 0;
        }

        return (int)(($depart->getTimestamp() - $arrivee->getTimestamp()) / 60);
    }

    public function detecterRetard(string $heureArrivee, string $heureReference): int
    {
        $arrivee = \DateTime::createFromFormat('H:i', substr($heureArrivee,  0, 5));
        $ref     = \DateTime::createFromFormat('H:i', substr($heureReference, 0, 5));

        if (!$arrivee || !$ref || $arrivee <= $ref) {
            return 0;
        }

        return (int)(($arrivee->getTimestamp() - $ref->getTimestamp()) / 60);
    }

    public function detecterHeuresSup(int $dureeMinutes, int $dureeReference): int
    {
        return max(0, $dureeMinutes - $dureeReference);
    }
}
