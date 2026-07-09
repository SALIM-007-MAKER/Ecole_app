<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\Repositories\Analytics\ScolariteAnalyticsRepository;
use App\Modules\Rapports\Repositories\Analytics\AcademiqueAnalyticsRepository;
use App\Modules\Rapports\Repositories\Analytics\FinanceAnalyticsRepository;
use App\Modules\Rapports\Repositories\Analytics\VieScolaireAnalyticsRepository;
use App\Modules\Rapports\Repositories\Analytics\RHAnalyticsRepository;
use App\Modules\Rapports\Repositories\Analytics\BibliothequeAnalyticsRepository;
use App\Modules\Rapports\Repositories\Analytics\InventaireAnalyticsRepository;

class DataAggregatorService
{
    private ScolariteAnalyticsRepository   $scolarite;
    private AcademiqueAnalyticsRepository  $academique;
    private FinanceAnalyticsRepository     $finance;
    private VieScolaireAnalyticsRepository $vieScolaire;
    private RHAnalyticsRepository          $rh;
    private BibliothequeAnalyticsRepository $bibliotheque;
    private InventaireAnalyticsRepository  $inventaire;

    public function __construct()
    {
        $this->scolarite   = new ScolariteAnalyticsRepository();
        $this->academique  = new AcademiqueAnalyticsRepository();
        $this->finance     = new FinanceAnalyticsRepository();
        $this->vieScolaire = new VieScolaireAnalyticsRepository();
        $this->rh          = new RHAnalyticsRepository();
        $this->bibliotheque = new BibliothequeAnalyticsRepository();
        $this->inventaire  = new InventaireAnalyticsRepository();
    }

    public function getForDomaine(string $domaine, int $etablissementId, array $filters = []): array
    {
        return match ($domaine) {
            'scolarite'   => $this->getScolarite($etablissementId, $filters),
            'academique'  => $this->getAcademique($etablissementId, $filters),
            'finance'     => $this->getFinance($etablissementId, $filters),
            'vie_scolaire' => $this->getVieScolaire($etablissementId, $filters),
            'rh'          => $this->getRH($etablissementId, $filters),
            'bibliotheque' => $this->getBibliotheque($etablissementId),
            'inventaire'  => $this->getInventaire($etablissementId, $filters),
            default       => [],
        };
    }

    public function getScolarite(int $etab, array $f = []): array
    {
        $as = $f['annee_scolaire'] ?? '';
        return [
            'counters'           => $this->scolarite->counters($etab, $as),
            'effectifs_classes'  => $this->scolarite->effectifsParClasse($etab, $as),
            'repartition_genre'  => $this->scolarite->repartitionGenre($etab, $as),
            'taux_remplissage'   => $this->scolarite->tauxRemplissageClasses($etab, $as),
            'evolution_inscriptions' => $this->scolarite->evolutionInscriptions($etab),
        ];
    }

    public function getAcademique(int $etab, array $f = []): array
    {
        $pid = (int)($f['periode_id'] ?? 0);
        $cid = isset($f['classe_id']) ? (int)$f['classe_id'] : null;
        return [
            'counters'               => $this->academique->counters($etab),
            'moyennes_classes'       => $this->academique->moyenneGeneraleParClasse($etab, $pid),
            'distrib_mentions'       => $this->academique->distribMentions($etab, $pid),
            'taux_reussite_matieres' => $this->academique->tauxReussiteParMatiere($etab, $pid),
            'evolution_moyennes'     => $this->academique->evolutionMoyennesParPeriode($etab, $cid),
        ];
    }

    public function getFinance(int $etab, array $f = []): array
    {
        $dd = $f['date_debut'] ?? '';
        $df = $f['date_fin']   ?? '';
        return [
            'counters'           => $this->finance->counters($etab),
            'recettes_par_mois'  => $this->finance->recettesParMois($etab),
            'factures_impayees'  => $this->finance->facturesImpayees($etab),
            'taux_recouvrement'  => $this->finance->tauxRecouvrement($etab, $dd, $df),
            'mouvements_caisse'  => $this->finance->mouvementsCaisse($etab, $dd, $df),
        ];
    }

    public function getVieScolaire(int $etab, array $f = []): array
    {
        $dd = $f['date_debut'] ?? '';
        $df = $f['date_fin']   ?? '';
        return [
            'counters'             => $this->vieScolaire->counters($etab, $dd, $df),
            'absences_par_classe'  => $this->vieScolaire->absencesParClasse($etab, $dd, $df),
            'retards_par_classe'   => $this->vieScolaire->retardsParClasse($etab, $dd, $df),
            'incidents_par_type'   => $this->vieScolaire->incidentsParType($etab, $dd, $df),
            'evolution_absences'   => $this->vieScolaire->evolutionAbsences($etab),
        ];
    }

    public function getRH(int $etab, array $f = []): array
    {
        $dd = $f['date_debut'] ?? '';
        $df = $f['date_fin']   ?? '';
        return [
            'counters'            => $this->rh->counters($etab),
            'effectifs_dept'      => $this->rh->effectifsParDepartement($etab),
            'taux_presence'       => $this->rh->tauxPresence($etab, $dd, $df),
            'conges_par_type'     => $this->rh->congesParType($etab, $dd, $df),
            'scores_evaluations'  => $this->rh->scoresEvaluations($etab),
            'contrats_expirant'   => $this->rh->contratsExpirant($etab),
        ];
    }

    public function getBibliotheque(int $etab): array
    {
        return [
            'counters'              => $this->bibliotheque->counters($etab),
            'emprunts_par_mois'     => $this->bibliotheque->empruntsParMois($etab),
            'ouvrages_populaires'   => $this->bibliotheque->ouvragesLesPlusEmpruntes($etab),
            'taux_retour'           => $this->bibliotheque->tauxRetour($etab),
            'emprunts_en_retard'    => $this->bibliotheque->empruntsEnRetard($etab),
        ];
    }

    public function getInventaire(int $etab, array $f = []): array
    {
        $dd = $f['date_debut'] ?? '';
        $df = $f['date_fin']   ?? '';
        return [
            'counters'              => $this->inventaire->counters($etab),
            'valeur_stock'          => $this->inventaire->valeurTotaleStock($etab),
            'alertes_stock'         => $this->inventaire->articlesEnAlerteStock($etab),
            'commandes_par_statut'  => $this->inventaire->commandesParStatut($etab),
            'couts_maintenance'     => $this->inventaire->coutMaintenanceParArticle($etab, $dd, $df),
        ];
    }
}
