<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\PeriodeScolaireDTO;
use App\Modules\Academique\Events\PeriodeActivated;
use App\Modules\Academique\Events\PeriodeArchived;
use App\Modules\Academique\Events\PeriodeCreated;
use App\Modules\Academique\Events\PeriodeLocked;
use App\Modules\Academique\Events\PeriodeUnlocked;
use App\Modules\Academique\Events\PeriodeUpdated;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class PeriodeScolaireService
{
    private PeriodeScolaireModel      $model;
    private PeriodeScolaireRepository $repo;
    private PeriodeConfigService      $config;
    private AuditService              $audit;

    public function __construct()
    {
        $this->model  = new PeriodeScolaireModel();
        $this->repo   = new PeriodeScolaireRepository();
        $this->config = new PeriodeConfigService();
        $this->audit  = new AuditService();
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function creer(PeriodeScolaireDTO $dto, int $userId, bool $genereAuto = false): int
    {
        $this->validerContraintes($dto);

        $data = array_merge($dto->toArray(), [
            'statut'                 => $dto->statut ?? 'preparation',
            'is_active'              => 0,
            'notes_saisie_ouverte'   => 1,
            'genere_automatiquement' => $genereAuto ? 1 : 0,
            'created_by'             => $userId,
        ]);

        $periodeId = $this->model->insert($data);
        if ($periodeId === 0) {
            throw new \RuntimeException("Erreur lors de la création de la période.");
        }

        EventDispatcher::dispatch(new PeriodeCreated(
            periodeId:    $periodeId,
            nom:          $dto->nom,
            anneeScolaire:$dto->anneeScolaire,
            typePeriode:  $dto->typePeriode,
            numero:       $dto->numero,
            createdById:  $userId,
        ));

        return $periodeId;
    }

    /**
     * Génère les périodes officielles d'une année scolaire à partir du
     * modèle par défaut configurable (PeriodeConfigService — préconfiguré
     * La Persévérance, jamais codé en dur ici). Ignore silencieusement les
     * numéros déjà existants pour cette année (permet une génération
     * partielle après suppression manuelle d'une seule période).
     *
     * @return array{crees: int[], ignores: array<int, string>}
     */
    public function genererParDefaut(string $anneeScolaire, int $userId): array
    {
        if (!preg_match('/^\d{4}-\d{4}$/', $anneeScolaire)) {
            throw new \RuntimeException("Format d'année scolaire invalide : {$anneeScolaire}.");
        }

        $crees   = [];
        $ignores = [];

        foreach ($this->config->calculerDates($anneeScolaire) as $periode) {
            if ($this->repo->existsForAnneeTypeNumero($anneeScolaire, 'semestre', $periode['numero'])) {
                $ignores[] = "{$periode['nom']} (déjà existant pour {$anneeScolaire})";
                continue;
            }

            $dto = new PeriodeScolaireDTO(
                anneeScolaire: $anneeScolaire,
                typePeriode:   'semestre',
                numero:        $periode['numero'],
                nom:           $periode['nom'],
                dateDebut:     $periode['date_debut'],
                dateFin:       $periode['date_fin'],
                ordre:         $periode['ordre'],
            );

            $erreurs = $dto->validate();
            if (!empty($erreurs)) {
                $ignores[] = "{$periode['nom']} (invalide : " . implode(' ', array_merge(...array_values($erreurs))) . ')';
                continue;
            }

            $crees[] = $this->creer($dto, $userId, genereAuto: true);
        }

        return ['crees' => $crees, 'ignores' => $ignores];
    }

    // ─── Modification ────────────────────────────────────────────────────────

    public function modifier(int $periodeId, PeriodeScolaireDTO $dto, int $userId, bool $isAdmin = false): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable (id={$periodeId}).");
        }

        if ($periode->verrouille_par !== null && !$isAdmin) {
            throw new \RuntimeException(
                "La période « {$periode->nom} » est verrouillée. Seul un administrateur peut la modifier."
            );
        }

        if ($periode->statut === 'archivee') {
            throw new \RuntimeException("Impossible de modifier une période archivée.");
        }

        $this->validerContraintes($dto, $periodeId);

        if ($dto->statut !== null && $dto->statut !== $periode->statut && !$isAdmin) {
            throw new \RuntimeException("Seul un administrateur peut modifier directement le statut.");
        }

        $avant = (array)$periode;
        $data  = $dto->toArray();

        $this->model->update($periodeId, $data);

        $changedFields = $this->diff($avant, $data);
        if (!empty($changedFields)) {
            EventDispatcher::dispatch(new PeriodeUpdated(
                periodeId:     $periodeId,
                updatedById:   $userId,
                changedFields: $changedFields,
            ));
        }
    }

    // ─── Validations transverses (chevauchement, bornes, doublons) ──────────

    private function validerContraintes(PeriodeScolaireDTO $dto, int $excludeId = 0): void
    {
        if ($this->repo->existsForAnneeTypeNumero($dto->anneeScolaire, $dto->typePeriode, $dto->numero, $excludeId)) {
            $typeLabel = PeriodeScolaireDTO::typeLabels()[$dto->typePeriode] ?? $dto->typePeriode;
            throw new \RuntimeException(
                "{$typeLabel} {$dto->numero} existe déjà pour l'année {$dto->anneeScolaire}."
            );
        }

        if ($this->repo->nomExists($dto->nom, $dto->anneeScolaire, $excludeId)) {
            throw new \RuntimeException(
                "Une période nommée « {$dto->nom} » existe déjà pour l'année {$dto->anneeScolaire}."
            );
        }

        if ($dto->dateDebut === null || $dto->dateFin === null) {
            return;
        }

        if ($this->repo->existsOverlap($dto->anneeScolaire, $dto->dateDebut, $dto->dateFin, $excludeId)) {
            throw new \RuntimeException(
                "Cette période chevauche une autre période existante de l'année {$dto->anneeScolaire}."
            );
        }

        $bornes = $this->config->bornesAnneeScolaire($dto->anneeScolaire);
        if ($dto->dateDebut < $bornes['min'] || $dto->dateFin > $bornes['max']) {
            throw new \RuntimeException(
                "Les dates doivent être comprises dans l'année scolaire {$dto->anneeScolaire} "
                . "(entre {$bornes['min']} et {$bornes['max']})."
            );
        }
    }

    /**
     * Vérifie que les périodes datées d'une année scolaire s'enchaînent
     * correctement, sans trou entre deux périodes consécutives (ex : un
     * trimestre manquant entre deux autres). Les intervalles avant la
     * première période et après la dernière ne sont volontairement PAS
     * signalés comme des trous : ils correspondent aux vacances d'été et
     * autres périodes hors enseignement, normales pour un calendrier
     * trimestriel Niger (ex : avant le 1er octobre, après le 30 juin).
     *
     * @return array{couverte: bool, bornes: array, gaps: array<int, array{debut:string, fin:string}>}
     */
    public function verifierCouverture(string $anneeScolaire): array
    {
        $bornes  = $this->config->bornesAnneeScolaire($anneeScolaire);
        $periods = $this->repo->findDatedByAnnee($anneeScolaire);

        if (empty($periods)) {
            return ['couverte' => false, 'bornes' => $bornes, 'gaps' => [
                ['debut' => $bornes['min'], 'fin' => $bornes['max']],
            ]];
        }

        $gaps    = [];
        $curseur = null;
        foreach ($periods as $p) {
            if ($curseur !== null && $p->date_debut > $curseur) {
                $gaps[] = ['debut' => $curseur, 'fin' => date('Y-m-d', strtotime($p->date_debut . ' -1 day'))];
            }
            if ($curseur === null || $p->date_fin > $curseur) {
                $curseur = date('Y-m-d', strtotime($p->date_fin . ' +1 day'));
            }
        }

        return ['couverte' => empty($gaps), 'bornes' => $bornes, 'gaps' => $gaps];
    }

    // ─── Activation (période mise en avant pour la saisie rapide) ───────────

    public function activer(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->statut !== 'ouverte') {
            throw new \RuntimeException("Seule une période « Ouverte » peut être activée (statut actuel : {$periode->statut}).");
        }
        if ((int)$periode->is_active === 1) {
            throw new \RuntimeException("Cette période est déjà active.");
        }

        // Garantie : une seule période active par annee_scolaire
        $this->model->deactiverTous($periode->annee_scolaire);
        $this->model->update($periodeId, ['is_active' => 1]);

        EventDispatcher::dispatch(new PeriodeActivated(
            periodeId:     $periodeId,
            nom:           $periode->nom,
            anneeScolaire: $periode->annee_scolaire,
            activatedById: $userId,
        ));
    }

    // ─── Cycle de vie : preparation → ouverte → cloturee → archivee ─────────

    public function ouvrir(int $periodeId, int $userId): void
    {
        $periode = $this->requireUnlocked($periodeId);
        if ($periode->statut !== 'preparation') {
            throw new \RuntimeException(
                "Seule une période « Préparation » peut être ouverte (statut actuel : {$periode->statut})."
            );
        }

        $this->model->update($periodeId, ['statut' => 'ouverte']);

        EventDispatcher::dispatch(new PeriodeUpdated(
            periodeId:     $periodeId,
            updatedById:   $userId,
            changedFields: ['avant' => ['statut' => 'preparation'], 'apres' => ['statut' => 'ouverte']],
        ));
    }

    public function cloturer(int $periodeId, int $userId): void
    {
        $periode = $this->requireUnlocked($periodeId);
        if ($periode->statut !== 'ouverte') {
            throw new \RuntimeException(
                "Seule une période « Ouverte » peut être clôturée (statut actuel : {$periode->statut})."
            );
        }

        $this->model->update($periodeId, [
            'statut'               => 'cloturee',
            'notes_saisie_ouverte' => 0,
        ]);

        EventDispatcher::dispatch(new PeriodeUpdated(
            periodeId:     $periodeId,
            updatedById:   $userId,
            changedFields: [
                'avant' => ['statut' => 'ouverte',   'notes_saisie_ouverte' => 1],
                'apres' => ['statut' => 'cloturee',  'notes_saisie_ouverte' => 0],
            ],
        ));
    }

    public function reouvrir(int $periodeId, int $userId): void
    {
        $periode = $this->requireUnlocked($periodeId);
        if ($periode->statut !== 'cloturee') {
            throw new \RuntimeException(
                "Seule une période « Clôturée » peut être réouverte (statut actuel : {$periode->statut})."
            );
        }

        $this->model->update($periodeId, [
            'statut'               => 'ouverte',
            'notes_saisie_ouverte' => 1,
        ]);

        EventDispatcher::dispatch(new PeriodeUpdated(
            periodeId:     $periodeId,
            updatedById:   $userId,
            changedFields: [
                'avant' => ['statut' => 'cloturee', 'notes_saisie_ouverte' => 0],
                'apres' => ['statut' => 'ouverte',  'notes_saisie_ouverte' => 1],
            ],
        ));
    }

    // ─── Verrouillage (orthogonal au statut, uniquement depuis "cloturee") ──

    public function verrouiller(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->verrouille_par !== null) {
            throw new \RuntimeException("Cette période est déjà verrouillée.");
        }
        if ($periode->statut !== 'cloturee') {
            throw new \RuntimeException("Seule une période « Clôturée » peut être verrouillée.");
        }

        $this->model->update($periodeId, [
            'verrouille_par' => $userId,
            'verrouille_le'  => date('Y-m-d H:i:s'),
        ]);

        EventDispatcher::dispatch(new PeriodeLocked(
            periodeId:  $periodeId,
            nom:        $periode->nom,
            lockedById: $userId,
        ));
    }

    public function deverrouiller(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->verrouille_par === null) {
            throw new \RuntimeException("Cette période n'est pas verrouillée.");
        }

        $this->model->update($periodeId, [
            'verrouille_par' => null,
            'verrouille_le'  => null,
        ]);

        EventDispatcher::dispatch(new PeriodeUnlocked(
            periodeId:    $periodeId,
            nom:          $periode->nom,
            unlockedById: $userId,
        ));
    }

    // ─── Archivage ───────────────────────────────────────────────────────────

    public function archiver(int $periodeId, int $userId): void
    {
        $periode = $this->requireUnlocked($periodeId);
        if ($periode->statut !== 'cloturee') {
            throw new \RuntimeException("Seule une période « Clôturée » peut être archivée.");
        }

        $updates = ['statut' => 'archivee', 'notes_saisie_ouverte' => 0];
        if ((int)$periode->is_active === 1) {
            $updates['is_active'] = 0;
        }

        $this->model->update($periodeId, $updates);

        EventDispatcher::dispatch(new PeriodeArchived(
            periodeId:    $periodeId,
            nom:          $periode->nom,
            anneeScolaire:$periode->annee_scolaire,
            archivedById: $userId,
        ));
    }

    // ─── Helpers privés ────────────────────────────────────────────────────

    private function requireUnlocked(int $periodeId): \stdClass
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->verrouille_par !== null) {
            throw new \RuntimeException(
                "La période « {$periode->nom} » est verrouillée. Déverrouillez-la avant de changer son statut."
            );
        }
        return $periode;
    }

    private function diff(array $avant, array $apres): array
    {
        $changedAvant = [];
        $changedApres = [];

        foreach ($apres as $k => $v) {
            $prevVal = $avant[$k] ?? null;
            if ((string)$prevVal !== (string)$v) {
                $changedAvant[$k] = $prevVal;
                $changedApres[$k] = $v;
            }
        }

        if (empty($changedAvant)) return [];

        return ['avant' => $changedAvant, 'apres' => $changedApres];
    }
}
