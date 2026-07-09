<?php

namespace App\Modules\RH\Employes\Services;

use App\Modules\RH\Employes\DTO\EmployeeDTO;
use App\Modules\RH\Employes\DTO\EmployeeFiltersDTO;
use App\Modules\RH\Employes\Events\EmployeeArchived;
use App\Modules\RH\Employes\Events\EmployeeCreated;
use App\Modules\RH\Employes\Events\EmployeeRestored;
use App\Modules\RH\Employes\Events\EmployeeUpdated;
use App\Modules\RH\Employes\Repositories\EmployeeRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class EmployeeService
{
    private EmployeeRepository $repo;
    private AuditService       $audit;

    public function __construct()
    {
        $this->repo  = new EmployeeRepository();
        $this->audit = new AuditService();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function findByIdIncludingArchived(int $id): ?array
    {
        return $this->repo->findByIdIncludingArchived($id);
    }

    public function paginate(EmployeeFiltersDTO $filters): array
    {
        $rows  = $this->repo->findAll($filters);
        $total = $this->repo->countAll($filters);

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $filters->page,
            'per_page'  => $filters->perPage,
            'last_page' => (int)ceil($total / max($filters->perPage, 1)),
        ];
    }

    public function statistiques(): array
    {
        return [
            'par_statut'      => $this->repo->countByStatut(),
            'par_type'        => $this->repo->countByType(),
            'par_departement' => $this->repo->countByDepartement(),
            'archives'        => $this->repo->countArchives(),
        ];
    }

    public function departements(): array
    {
        return $this->repo->findDepartements();
    }

    public function postes(?string $categorie = null): array
    {
        return $this->repo->findPostes($categorie);
    }

    public function users(): array
    {
        return $this->repo->findUsers();
    }

    public function contactsUrgence(int $employeId): array
    {
        return $this->repo->findContactsUrgence($employeId);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function creer(EmployeeDTO $dto, array $contacts, int $userId): int
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', array_merge(...array_values($errors))));
        }

        if ($dto->emailPro !== null && $this->repo->emailProExists($dto->emailPro)) {
            throw new \RuntimeException("Cette adresse e-mail professionnelle est déjà utilisée.");
        }

        $matricule = $this->genererMatricule();
        $data      = array_merge($dto->toArray(), [
            'matricule'   => $matricule,
            'cree_par_id' => $userId,
        ]);

        $employeId = $this->repo->insert($data);

        $this->sauvegarderContacts($employeId, $contacts);

        EventDispatcher::dispatch(new EmployeeCreated(
            employeId:    $employeId,
            matricule:    $matricule,
            typePersonnel: $dto->typePersonnel,
            nom:          $dto->nom,
            prenom:       $dto->prenom,
            creeParId:    $userId,
        ));

        return $employeId;
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function modifier(int $id, EmployeeDTO $dto, array $contacts, int $userId): void
    {
        $employe = $this->repo->findById($id);
        if ($employe === null) {
            throw new \RuntimeException("Employé introuvable.");
        }

        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', array_merge(...array_values($errors))));
        }

        if ($dto->emailPro !== null && $this->repo->emailProExists($dto->emailPro, $id)) {
            throw new \RuntimeException("Cette adresse e-mail professionnelle est déjà utilisée par un autre employé.");
        }

        $newData = $dto->toArray();
        $changes = $this->audit->diff($employe, $newData);

        $this->repo->update($id, $newData);
        $this->sauvegarderContacts($id, $contacts);

        EventDispatcher::dispatch(new EmployeeUpdated(
            employeId:    $id,
            matricule:    $employe['matricule'],
            changes:      $changes,
            modifieParId: $userId,
        ));
    }

    // ── Changement de statut ──────────────────────────────────────────────────

    public function changerStatut(int $id, string $statut, int $userId): void
    {
        $employe = $this->repo->findById($id);
        if ($employe === null) {
            throw new \RuntimeException("Employé introuvable.");
        }

        if (!in_array($statut, EmployeeDTO::STATUTS, true)) {
            throw new \InvalidArgumentException("Statut invalide : {$statut}");
        }

        if ($employe['statut'] === $statut) {
            throw new \RuntimeException("L'employé est déjà au statut « {$statut} ».");
        }

        $this->repo->update($id, ['statut' => $statut]);

        EventDispatcher::dispatch(new EmployeeUpdated(
            employeId:    $id,
            matricule:    $employe['matricule'],
            changes:      ['statut' => ['from' => $employe['statut'], 'to' => $statut]],
            modifieParId: $userId,
        ));
    }

    // ── Archivage (soft delete) ───────────────────────────────────────────────

    public function archiver(int $id, int $userId): void
    {
        $employe = $this->repo->findById($id);
        if ($employe === null) {
            throw new \RuntimeException("Employé introuvable ou déjà archivé.");
        }

        $this->repo->softDelete($id);

        EventDispatcher::dispatch(new EmployeeArchived(
            employeId:    $id,
            matricule:    $employe['matricule'],
            nom:          $employe['nom'],
            prenom:       $employe['prenom'],
            archiveParId: $userId,
        ));
    }

    // ── Restauration ─────────────────────────────────────────────────────────

    public function restaurer(int $id, int $userId): void
    {
        $employe = $this->repo->findByIdIncludingArchived($id);
        if ($employe === null) {
            throw new \RuntimeException("Employé introuvable.");
        }
        if ($employe['deleted_at'] === null) {
            throw new \RuntimeException("Cet employé n'est pas archivé.");
        }

        $this->repo->restore($id);

        EventDispatcher::dispatch(new EmployeeRestored(
            employeId:     $id,
            matricule:     $employe['matricule'],
            restaureParId: $userId,
        ));
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exporterCsv(EmployeeFiltersDTO $filters): string
    {
        $filters = new EmployeeFiltersDTO(
            q:          $filters->q,
            statut:     $filters->statut,
            type:       $filters->type,
            departId:   $filters->departId,
            includeArch: $filters->includeArch,
            page:       1,
            perPage:    9999,
        );

        $rows = $this->repo->findAll($filters);

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel
        $csv .= "Matricule,Nom,Prénom,Type,Statut,Département,Poste,Email Pro,Téléphone,Date Entrée\n";

        foreach ($rows as $r) {
            $csv .= implode(',', [
                '"' . str_replace('"', '""', $r['matricule']         ?? '') . '"',
                '"' . str_replace('"', '""', $r['nom']               ?? '') . '"',
                '"' . str_replace('"', '""', $r['prenom']            ?? '') . '"',
                '"' . str_replace('"', '""', $r['type_personnel']    ?? '') . '"',
                '"' . str_replace('"', '""', $r['statut']            ?? '') . '"',
                '"' . str_replace('"', '""', $r['departement_nom']   ?? '') . '"',
                '"' . str_replace('"', '""', $r['poste_intitule']    ?? '') . '"',
                '"' . str_replace('"', '""', $r['email_pro']         ?? '') . '"',
                '"' . str_replace('"', '""', $r['telephone']         ?? '') . '"',
                '"' . str_replace('"', '""', $r['date_entree']       ?? '') . '"',
            ]) . "\n";
        }

        return $csv;
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function genererMatricule(): string
    {
        $annee   = date('Y');
        $dernier = $this->repo->findLastMatricule($annee);
        $num     = $dernier ? ((int)substr($dernier, 5) + 1) : 1;
        return sprintf('%d-%05d', $annee, $num);
    }

    private function sauvegarderContacts(int $employeId, array $contacts): void
    {
        $this->repo->deleteContactsUrgence($employeId);

        foreach ($contacts as $i => $c) {
            $nom = trim($c['nom_complet'] ?? '');
            $tel = trim($c['telephone']   ?? '');
            if ($nom === '' || $tel === '') {
                continue;
            }
            $this->repo->insertContactUrgence([
                'employe_id'  => $employeId,
                'nom_complet' => $nom,
                'lien'        => trim($c['lien']       ?? ''),
                'telephone'   => $tel,
                'telephone2'  => trim($c['telephone2'] ?? '') ?: null,
                'email'       => trim($c['email']      ?? '') ?: null,
                'principal'   => $i === 0 ? 1 : 0,
            ]);
        }
    }
}
