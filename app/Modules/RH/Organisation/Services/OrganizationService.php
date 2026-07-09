<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Services;

use App\Modules\RH\Organisation\Repositories\OrganizationRepository;

class OrganizationService
{
    private OrganizationRepository $repo;

    public function __construct()
    {
        $this->repo = new OrganizationRepository();
    }

    /** Retourne l'arbre complet pour l'organigramme */
    public function organigramme(): array
    {
        return $this->repo->findDepartementsTree();
    }

    /** Dashboard — compteurs et répartitions */
    public function statistiques(): array
    {
        return $this->repo->statistiques();
    }

    /** Export CSV des postes */
    public function exportPostes(): string
    {
        $filters = new \App\Modules\RH\Organisation\DTO\OrganizationFiltersDTO(perPage: 9999);
        $postes  = $this->repo->findAllPostes($filters);

        $bom  = "\xEF\xBB\xBF";
        $rows = ["Intitulé;Code;Catégorie;Niveau;Département;Service;Nb max;Statut"];
        foreach ($postes as $p) {
            $rows[] = implode(';', [
                $p['intitule'],
                $p['code'],
                $p['categorie'],
                $p['niveau'],
                $p['departement_nom'] ?? '',
                $p['service_nom'] ?? '',
                $p['nb_occupants_max'],
                $p['deleted_at'] ? 'Archivé' : ($p['actif'] ? 'Actif' : 'Inactif'),
            ]);
        }
        return $bom . implode("\n", $rows);
    }

    /** Export CSV des départements */
    public function exportDepartements(): string
    {
        $filters = new \App\Modules\RH\Organisation\DTO\OrganizationFiltersDTO(perPage: 9999);
        $depts   = $this->repo->findAllDepartements($filters);

        $bom  = "\xEF\xBB\xBF";
        $rows = ["Nom;Code;Parent;Responsable;Nb employés;Nb services;Statut"];
        foreach ($depts as $d) {
            $resp = $d['resp_prenom'] ? "{$d['resp_prenom']} {$d['resp_nom']}" : '';
            $rows[] = implode(';', [
                $d['nom'],
                $d['code'],
                $d['parent_nom'] ?? '',
                $resp,
                $d['nb_employes'],
                $d['nb_services'],
                $d['deleted_at'] ? 'Archivé' : ($d['actif'] ? 'Actif' : 'Inactif'),
            ]);
        }
        return $bom . implode("\n", $rows);
    }

    public function historique(string $typeEntite, int $entiteId): array
    {
        return $this->repo->findHistoriqueByEntite($typeEntite, $entiteId);
    }
}
