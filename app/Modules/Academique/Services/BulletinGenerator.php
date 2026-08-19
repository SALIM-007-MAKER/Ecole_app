<?php

namespace App\Modules\Academique\Services;

use Core\EventDispatcher;
use App\Modules\Academique\Contracts\BulletinGeneratorInterface;
use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\DTO\BulletinSummary;
use App\Modules\Academique\DTO\RankingResultDTO;
use App\Modules\Academique\Events\BulletinGenerated;
use App\Modules\Academique\Events\BulletinPublished;
use App\Modules\Academique\Events\BulletinArchived;
use App\Modules\Academique\Repositories\AppreciationMatiereRepository;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Modules\VieScolaire\Absences\Repositories\AbsenceRepository;
use App\Services\AuditService;

/**
 * Moteur de génération des bulletins — source unique de vérité.
 *
 * RÈGLES D'OR :
 *   • Aucun calcul de moyenne — AcademicCalculationService uniquement
 *   • Aucun classement interne — RankingEngine uniquement
 *   • Toutes les générations journalisées via AuditService
 *   • Un bulletin = un snapshot immuable à l'instant T
 */
class BulletinGenerator implements BulletinGeneratorInterface
{
    // Pool d'appréciations par mention (déterministe via eleve_id)
    private const APPRECIATIONS = [
        'TB' => [
            "Félicitations pour ces résultats remarquables. Continuez sur cette lancée exemplaire.",
            "Excellent trimestre. Un travail d'une grande qualité qui force l'admiration.",
            "Des résultats brillants qui témoignent d'une rigueur et d'un sérieux exemplaires.",
        ],
        'B' => [
            "Bon trimestre. Des résultats encourageants qui méritent d'être consolidés.",
            "Satisfaisant. Poursuivez vos efforts pour atteindre l'excellence.",
            "Bon travail dans l'ensemble. Quelques points à approfondir pour progresser encore.",
        ],
        'AB' => [
            "Des résultats assez satisfaisants. Un effort soutenu permettrait d'aller encore plus loin.",
            "Travail correct. Avec plus de régularité, de meilleurs résultats sont à portée.",
            "Assez bien. La persévérance et la régularité dans le travail s'imposent.",
        ],
        'P' => [
            "Le minimum requis est atteint. Un engagement plus fort est attendu pour le prochain trimestre.",
            "Des résultats passables. Un travail plus rigoureux et plus régulier s'impose.",
            "Passable. Des efforts supplémentaires sont nécessaires pour améliorer ce bilan.",
        ],
        'INS' => [
            "Résultats insuffisants. Un travail de fond est nécessaire pour combler les lacunes identifiées.",
            "Des difficultés persistantes. Une remise à niveau sérieuse et un suivi renforcé sont indispensables.",
            "Résultats préoccupants. Un effort considérable et soutenu est attendu d'urgence.",
        ],
    ];

    private PeriodeScolaireRepository      $periodeRepo;
    private AbsenceRepository              $absenceRepo;
    private AppreciationMatiereRepository  $apprRepo;

    /** classeId:periodeId:matiereId => RankingResultDTO — mémoïsation par requête, voir moyenneEtRangParMatiere(). */
    private array $matiereClassementCache = [];

    public function __construct(
        private AcademicCalculationService $calculator,
        private RankingEngine              $rankingEngine,
        private BulletinRepository         $repo,
        private float                      $seuilRattrapage = 8.0,
        private float                      $notePassage     = 10.0,
        private string                     $appKey          = 'ecole_app_v2',
        ?PeriodeScolaireRepository         $periodeRepo     = null,
        ?AbsenceRepository                 $absenceRepo     = null,
        ?AppreciationMatiereRepository     $apprRepo        = null,
    ) {
        $this->periodeRepo = $periodeRepo ?? new PeriodeScolaireRepository();
        $this->absenceRepo = $absenceRepo ?? new AbsenceRepository();
        $this->apprRepo    = $apprRepo    ?? new AppreciationMatiereRepository();
    }

    // ─────────────────────────────────────────────────────────────────
    //  API publique
    // ─────────────────────────────────────────────────────────────────

    /**
     * Expose le RankingEngine déjà configuré par BulletinEngineFactory (mêmes
     * note_passage/seuils que ceux utilisés pour générer les bulletins) — pour
     * les écrans de consultation (résultats classe, classement, moyennes) qui
     * ont besoin du même moteur sans dupliquer sa configuration par
     * établissement. Voir Academique\Controllers\ResultatsController.
     */
    public function getRankingEngine(): RankingEngine
    {
        return $this->rankingEngine;
    }

    public function genererBulletin(int $eleveId, int $periodeId, int $userId): BulletinData
    {
        [$eleve, $periode, $etab] = $this->fetchContext($eleveId, $periodeId);
        $classeId = (int)$eleve->classe_id;

        $rows         = $this->repo->notesEleveParPeriode($eleveId, $classeId, $periodeId);
        $matieresData = $this->groupNotesByMatiere($rows);

        // Classement entier de la classe — une seule fois
        $classement       = $this->rankingEngine->classementClasse($classeId, $periodeId);
        $resultatsAnnuels = $this->resultatsClasseEtAnnuels($classeId, $periode->annee_scolaire, $eleveId);

        $bulletin = $this->buildBulletinData(
            $eleveId, $periodeId, $userId,
            $matieresData, $eleve, $periode, $etab,
            $classement, $resultatsAnnuels, preview: false
        );
        $bulletin = $this->preserveAppreciationDirecteur($bulletin);

        // Persistance
        $this->repo->saveBulletin($bulletin);

        // Événement + audit
        EventDispatcher::dispatch(new BulletinGenerated(
            eleveId          : $eleveId,
            classeId         : $classeId,
            periodeId        : $periodeId,
            verificationToken: $bulletin->verificationToken,
            moyenne          : $bulletin->moyennePeriode,
            mentionCode      : $bulletin->mentionCode,
            rang             : $bulletin->rang,
            nbEleves         : $bulletin->nbEleves,
            decision         : $bulletin->decision,
            statut           : $bulletin->statut,
            generatedById    : $userId,
        ));

        return $bulletin;
    }

    public function genererBulletinsClasse(int $classeId, int $periodeId, int $userId): array
    {
        // Classement calculé une seule fois pour toute la classe
        $classement = $this->rankingEngine->classementClasse($classeId, $periodeId);
        $etab       = $this->repo->infoEtablissement();
        $periode    = $this->repo->infoPeriode($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable : $periodeId");
        }

        $eleveIds = $this->repo->elevesParClasse($classeId);
        $bulletins = [];

        foreach ($eleveIds as $eleveId) {
            try {
                $eleve = $this->repo->infoEleve((int)$eleveId);
                if (!$eleve) continue;

                // Rang/décision annuels dépendent de l'élève — recalculés par élève
                // (le classement annuel lui-même est peu coûteux, classes de petite taille).
                $resultatsAnnuels = $this->resultatsClasseEtAnnuels($classeId, $periode->annee_scolaire, (int)$eleveId);

                $rows         = $this->repo->notesEleveParPeriode((int)$eleveId, $classeId, $periodeId);
                $matieresData = $this->groupNotesByMatiere($rows);

                $bulletin = $this->buildBulletinData(
                    (int)$eleveId, $periodeId, $userId,
                    $matieresData, $eleve, $periode, $etab,
                    $classement, $resultatsAnnuels, preview: false
                );
                $bulletin = $this->preserveAppreciationDirecteur($bulletin);

                $this->repo->saveBulletin($bulletin);

                EventDispatcher::dispatch(new BulletinGenerated(
                    eleveId          : (int)$eleveId,
                    classeId         : $classeId,
                    periodeId        : $periodeId,
                    verificationToken: $bulletin->verificationToken,
                    moyenne          : $bulletin->moyennePeriode,
                    mentionCode      : $bulletin->mentionCode,
                    rang             : $bulletin->rang,
                    nbEleves         : $bulletin->nbEleves,
                    decision         : $bulletin->decision,
                    statut           : $bulletin->statut,
                    generatedById    : $userId,
                ));

                $bulletins[] = $bulletin;
            } catch (\Throwable) {
                // Isole les erreurs d'un élève pour ne pas bloquer les autres
                continue;
            }
        }

        return $bulletins;
    }

    /**
     * Reporte l'appréciation du chef d'établissement déjà persistée (si elle
     * existe) sur un bulletin fraîchement reconstruit. Sans cela, régénérer
     * un bulletin (ex : après correction d'une note) écraserait silencieusement
     * une appréciation de direction déjà saisie, puisque buildBulletinData()
     * part toujours de `appreciationDirecteur: null` — voir
     * BulletinController::appreciationForm()/updateAppreciation().
     */
    private function preserveAppreciationDirecteur(BulletinData $bulletin): BulletinData
    {
        $existing = $this->repo->findByEleveEtPeriode($bulletin->eleveId, $bulletin->periodeId);
        $appreciation = $existing['appreciation_directeur'] ?? null;

        return ($appreciation !== null && $appreciation !== '')
            ? $bulletin->withAppreciationDirecteur($appreciation)
            : $bulletin;
    }

    public function previewBulletin(int $eleveId, int $periodeId): BulletinData
    {
        [$eleve, $periode, $etab] = $this->fetchContext($eleveId, $periodeId);
        $classeId = (int)$eleve->classe_id;

        $rows         = $this->repo->notesEleveParPeriode($eleveId, $classeId, $periodeId);
        $matieresData = $this->groupNotesByMatiere($rows);
        $classement   = $this->rankingEngine->classementClasse($classeId, $periodeId);
        $resultatsAnnuels = $this->resultatsClasseEtAnnuels($classeId, $periode->annee_scolaire, $eleveId);

        // Pas de persistance, pas d'événement
        return $this->buildBulletinData(
            $eleveId, $periodeId, 0,
            $matieresData, $eleve, $periode, $etab,
            $classement, $resultatsAnnuels, preview: true
        );
    }

    public function publierBulletin(string $verificationToken, int $userId): BulletinData
    {
        $row = $this->repo->findByToken($verificationToken);
        if (!$row) {
            throw new \RuntimeException("Bulletin introuvable : $verificationToken");
        }
        if ($row['statut'] === 'archive') {
            throw new \RuntimeException("Impossible de publier un bulletin archivé.");
        }

        $publishedAt = date('Y-m-d H:i:s');
        $this->repo->updateStatut($verificationToken, 'publie', $userId, publishedAt: $publishedAt);

        $bulletin = BulletinData::fromArray(json_decode($row['data_json'], true) ?? [])
            ->withStatut('publie', publishedAt: $publishedAt);

        EventDispatcher::dispatch(new BulletinPublished(
            eleveId          : $bulletin->eleveId,
            classeId         : $bulletin->classeId,
            periodeId        : $bulletin->periodeId,
            verificationToken: $verificationToken,
            publishedAt      : $publishedAt,
            publishedById    : $userId,
        ));

        return $bulletin;
    }

    public function archiverBulletin(string $verificationToken, int $userId): BulletinData
    {
        $row = $this->repo->findByToken($verificationToken);
        if (!$row) {
            throw new \RuntimeException("Bulletin introuvable : $verificationToken");
        }
        if ($row['statut'] !== 'publie') {
            throw new \RuntimeException("Seul un bulletin publié peut être archivé.");
        }

        $archivedAt = date('Y-m-d H:i:s');
        $this->repo->updateStatut($verificationToken, 'archive', $userId, archivedAt: $archivedAt);

        $bulletin = BulletinData::fromArray(json_decode($row['data_json'], true) ?? [])
            ->withStatut('archive', archivedAt: $archivedAt);

        EventDispatcher::dispatch(new BulletinArchived(
            eleveId          : $bulletin->eleveId,
            classeId         : $bulletin->classeId,
            periodeId        : $bulletin->periodeId,
            verificationToken: $verificationToken,
            archivedAt       : $archivedAt,
            archivedById     : $userId,
        ));

        return $bulletin;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Export
    // ─────────────────────────────────────────────────────────────────

    public function exportHtml(BulletinData $b): string
    {
        $esc = fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        // Lignes de matières
        $lignesHtml = '';
        foreach ($b->lignesMatieres as $ligne) {
            $notesConcatinees = implode(' / ', array_map(
                fn($n) => $n['est_absent'] ? 'Abs' : number_format((float)($n['valeur'] ?? 0), 2),
                $ligne['notes'] ?? []
            ));
            $lignesHtml .= sprintf(
                '<tr>
                    <td class="mat-nom">%s</td>
                    <td class="center">%s</td>
                    <td class="center notes-cell">%s</td>
                    <td class="center moy-cell"><strong>%s</strong></td>
                    <td class="center"><span class="badge-%s">%s</span></td>
                    <td class="appr-cell">%s</td>
                </tr>',
                $esc($ligne['matiere_nom']),
                $esc($ligne['coefficient']),
                $esc($notesConcatinees),
                number_format($ligne['moyenne'] ?? 0, 2),
                $esc($ligne['mention_css'] ?? 'slate'),
                $esc($ligne['mention_code'] ?? ''),
                $esc($ligne['appreciation'] ?? ''),
            );
        }

        $decisionLabel = match ($b->decision) {
            'admis'        => 'ADMIS(E)',
            'rattrapage'   => 'ADMIS(E) AU RATTRAPAGE',
            'refuse'       => 'NON ADMIS(E)',
            'indeterminate'=> 'EN ATTENTE',
            default        => strtoupper($b->decision),
        };

        $statsHtml = '';
        if (!empty($b->statistiquesClasse)) {
            $s = $b->statistiquesClasse;
            $statsHtml = sprintf(
                '<div class="stats-bar">
                    <span>Moy. classe : <strong>%s/20</strong></span>
                    <span>Min : <strong>%s</strong></span>
                    <span>Max : <strong>%s</strong></span>
                    <span>Taux réussite : <strong>%s%%</strong></span>
                </div>',
                number_format((float)($s['moyenne'] ?? 0), 2),
                number_format((float)($s['min'] ?? 0), 2),
                number_format((float)($s['max'] ?? 0), 2),
                number_format((float)($s['taux_reussite'] ?? 0), 1),
            );
        }

        $logoHtml = $b->etablissementLogo
            ? '<img src="' . $esc($b->etablissementLogo) . '" alt="logo" class="logo">'
            : '<div class="logo-placeholder">📚</div>';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Bulletin — {$esc($b->eleveNom)} {$esc($b->elevePrenom)} — {$esc($b->periodeNom)}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
@page { size: A4 portrait; margin: 10mm; }
.page { width: 100%; max-width: 794px; margin: 0 auto; padding: 12px; }
/* Header */
.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #6d28d9; padding-bottom: 8px; margin-bottom: 10px; }
.header-etab { flex: 1; }
.etab-nom { font-size: 16px; font-weight: 700; color: #6d28d9; }
.etab-addr { color: #64748b; font-size: 10px; margin-top: 2px; }
.logo { height: 60px; object-fit: contain; }
.logo-placeholder { font-size: 40px; }
/* Titre */
.titre { text-align: center; font-size: 14px; font-weight: 700; color: #6d28d9; margin: 8px 0; letter-spacing: 1px; }
/* Identité élève */
.identite { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 8px; border-radius: 4px; margin-bottom: 10px; }
.id-label { font-size: 9px; color: #64748b; text-transform: uppercase; }
.id-val { font-weight: 600; }
/* Tableau matières */
table { width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 8px; }
th { background: #6d28d9; color: #fff; padding: 5px 4px; text-align: center; font-size: 10px; }
th.left { text-align: left; }
td { padding: 4px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
tr:nth-child(even) td { background: #fafafa; }
.center { text-align: center; }
.mat-nom { font-weight: 500; }
.moy-cell { color: #6d28d9; font-size: 12px; }
.notes-cell { color: #64748b; }
.appr-cell { color: #475569; font-style: italic; font-size: 10px; }
/* Badges mention */
.badge-emerald { background: #d1fae5; color: #065f46; padding: 1px 5px; border-radius: 9px; font-size: 9px; font-weight: 700; }
.badge-blue    { background: #dbeafe; color: #1e40af; padding: 1px 5px; border-radius: 9px; font-size: 9px; font-weight: 700; }
.badge-cyan    { background: #cffafe; color: #164e63; padding: 1px 5px; border-radius: 9px; font-size: 9px; font-weight: 700; }
.badge-amber   { background: #fef3c7; color: #92400e; padding: 1px 5px; border-radius: 9px; font-size: 9px; font-weight: 700; }
.badge-red     { background: #fee2e2; color: #991b1b; padding: 1px 5px; border-radius: 9px; font-size: 9px; font-weight: 700; }
.badge-slate   { background: #f1f5f9; color: #475569; padding: 1px 5px; border-radius: 9px; font-size: 9px; font-weight: 700; }
/* Résumé */
.resume { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin: 8px 0; }
.resume-card { text-align: center; border: 1px solid #e2e8f0; padding: 6px; border-radius: 4px; }
.resume-label { font-size: 9px; color: #64748b; text-transform: uppercase; }
.resume-val { font-size: 14px; font-weight: 700; color: #6d28d9; }
/* Stats */
.stats-bar { background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 8px; border-radius: 4px; display: flex; gap: 16px; font-size: 10px; margin-bottom: 8px; }
/* Appréciation */
.appreciation { border-left: 3px solid #6d28d9; padding: 6px 10px; background: #faf5ff; margin-bottom: 8px; font-style: italic; }
.appr-label { font-size: 9px; color: #6d28d9; text-transform: uppercase; font-style: normal; font-weight: 600; }
/* Décision */
.decision { text-align: center; padding: 8px; font-size: 14px; font-weight: 700; border-radius: 4px; margin-bottom: 10px; }
.decision-admis    { background: #d1fae5; color: #065f46; }
.decision-rattrapage{ background: #fef3c7; color: #92400e; }
.decision-refuse   { background: #fee2e2; color: #991b1b; }
.decision-other    { background: #f1f5f9; color: #475569; }
/* Signatures */
.signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 10px; }
.sig-box { border: 1px solid #e2e8f0; padding: 8px; border-radius: 4px; text-align: center; min-height: 60px; }
.sig-label { font-size: 9px; color: #64748b; text-transform: uppercase; font-weight: 600; }
/* QR / Vérification */
.verification { text-align: center; margin-top: 8px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>
<div class="page">
  <!-- Header établissement -->
  <div class="header">
    <div class="header-etab">
      <div class="etab-nom">{$esc($b->etablissementNom)}</div>
      <div class="etab-addr">{$esc($b->etablissementAdresse ?? '')}</div>
    </div>
    {$logoHtml}
  </div>

  <!-- Titre -->
  <div class="titre">BULLETIN DE NOTES — {$esc(strtoupper($b->periodeNom))} — {$esc($b->anneeScolaire)}</div>

  <!-- Identité élève -->
  <div class="identite">
    <div><div class="id-label">Nom & Prénom</div><div class="id-val">{$esc($b->eleveNom)} {$esc($b->elevePrenom)}</div></div>
    <div><div class="id-label">Matricule</div><div class="id-val">{$esc($b->eleveMatricule)}</div></div>
    <div><div class="id-label">Classe</div><div class="id-val">{$esc($b->classeNom)}</div></div>
  </div>

  <!-- Tableau des matières -->
  <table>
    <thead>
      <tr>
        <th class="left" style="width:22%">Matière</th>
        <th style="width:6%">Coeff</th>
        <th style="width:24%">Notes obtenues</th>
        <th style="width:9%">Moy/20</th>
        <th style="width:9%">Mention</th>
        <th class="left">Appréciation</th>
      </tr>
    </thead>
    <tbody>
      {$lignesHtml}
    </tbody>
  </table>

  <!-- Résumé -->
  <div class="resume">
    <div class="resume-card"><div class="resume-label">Moyenne</div><div class="resume-val">{$esc(number_format($b->moyennePeriode, 2))}/20</div></div>
    <div class="resume-card"><div class="resume-label">Rang</div><div class="resume-val">{$esc($b->rang)}/{$esc($b->nbEleves)}</div></div>
    <div class="resume-card"><div class="resume-label">Mention</div><div class="resume-val">{$esc($b->mentionLabel)}</div></div>
    <div class="resume-card"><div class="resume-label">Période</div><div class="resume-val">{$esc($b->periodeNom)}</div></div>
  </div>

  <!-- Statistiques classe -->
  {$statsHtml}

  <!-- Appréciation générale PP -->
  <div class="appreciation">
    <div class="appr-label">Appréciation du professeur principal</div>
    {$esc($b->appreciationPp)}
  </div>

  <!-- Appréciation directeur -->
HTML
. ($b->appreciationDirecteur ? <<<HTML
  <div class="appreciation">
    <div class="appr-label">Appréciation du directeur</div>
    {$esc($b->appreciationDirecteur)}
  </div>
HTML
    : '')
. <<<HTML

  <!-- Décision -->
  <div class="decision decision-{$esc(str_replace(['admis','rattrapage','refuse'],['admis','rattrapage','refuse'],$b->decision))}">
    DÉCISION : {$esc($decisionLabel)}
    <div style="font-size:10px;font-weight:normal;margin-top:2px">{$esc($b->decisionMotif)}</div>
  </div>

  <!-- Signatures -->
  <div class="signatures">
    <div class="sig-box"><div class="sig-label">Professeur Principal</div></div>
    <div class="sig-box"><div class="sig-label">Directeur(trice)</div></div>
    <div class="sig-box"><div class="sig-label">Parent / Tuteur</div></div>
  </div>

  <!-- Vérification -->
  <div class="verification">
    Vérifier l'authenticité de ce bulletin : {$esc($b->qrCodeUrl)}<br>
    Token : {$esc($b->verificationToken)} — Généré le {$esc($b->generatedAt)}
  </div>
</div>
</body>
</html>
HTML;
    }

    public function toApiPayload(BulletinData $bulletin): array
    {
        $payload          = $bulletin->toArray();
        $payload['_links'] = [
            'self'   => '/v2/academique/bulletins/' . $bulletin->verificationToken,
            'verify' => $bulletin->qrCodeUrl,
            'html'   => '/v2/academique/bulletins/' . $bulletin->verificationToken . '/html',
        ];
        return $payload;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Construction interne
    // ─────────────────────────────────────────────────────────────────

    /**
     * Noyau de construction du BulletinData.
     * Partagé par genererBulletin, genererBulletinsClasse, previewBulletin.
     */
    private function buildBulletinData(
        int              $eleveId,
        int              $periodeId,
        int              $userId,
        array            $matieresData,
        object           $eleve,
        object           $periode,
        array            $etab,
        RankingResultDTO $classement,
        array            $resultatsAnnuels,
        bool             $preview,
    ): BulletinData {
        // 1 — AcademicCalculationService : SEULE source des moyennes
        $calcResult    = $this->calculator->calculerBulletin($matieresData, true, $this->seuilRattrapage);
        $periodeMoy    = $calcResult['periode']['moyenne'];
        $aEliminatoire = $calcResult['periode']['aEliminatoire'];
        $mention       = $calcResult['mention'];
        $decision      = $calcResult['decision'];

        // 2 — RankingEngine : SEULE source du classement
        $eleveRanking = $this->findEleveInRanking($classement, $eleveId);
        $rang         = $eleveRanking['rang']     ?? 0;
        $nbEleves     = $classement->nbTotal;
        $statsClasse  = $classement->statistiques;
        $moyClasse    = $classement->getMoyenneClasse();

        // 3 — Lignes matières (+ moyenne de classe / rang par matière, bulletin V1 papier)
        $rangsParMatiere = $this->moyenneEtRangParMatiere(
            (int)$eleve->classe_id, $periodeId, $eleveId, array_keys($matieresData)
        );
        $lignes = $this->buildLignesMatieres($matieresData, $calcResult['matieres'], $rangsParMatiere, $eleveId, $periodeId);

        // 4 — Appréciation générale (déterministe via eleve_id)
        $appreciationPp = $this->genererAppreciation($mention->getCode(), $eleve->id, $aEliminatoire);

        // 4bis — Moyennes par filière (littéraire/scientifique/autre) et absences
        // de la période (bulletin V1 papier — cf. BulletinController::imprimer)
        $moyennesFiliere = $this->moyennesParFiliere($matieresData, $calcResult['matieres']);
        $absences        = ($periode->date_debut && $periode->date_fin)
            ? $this->absenceRepo->countByEleveAndDateRange($eleveId, $periode->date_debut, $periode->date_fin)
            : ['justifiees' => 0, 'nonJustifiees' => 0];

        // 5 — Token de vérification (stable pour le même élève+période)
        $token = $this->generateVerificationToken($eleveId, $periodeId);
        $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $qrUrl   = $baseUrl . '/v2/academique/bulletins/verify/' . $token;

        return new BulletinData(
            eleveId              : $eleveId,
            eleveNom             : $eleve->nom,
            elevePrenom          : $eleve->prenom,
            eleveMatricule       : $eleve->matricule ?? '',
            elevePhoto           : $eleve->photo     ?? null,
            classeId             : (int)$eleve->classe_id,
            classeNom            : $eleve->classe_nom,
            niveau               : $eleve->niveau,
            periodeId            : $periodeId,
            periodeNom           : $periode->nom,
            anneeScolaire        : $periode->annee_scolaire,
            etablissementNom     : $etab['nom'],
            etablissementAdresse : $etab['adresse'],
            etablissementLogo    : $etab['logo'],
            etablissementTelephone: $etab['telephone'] ?? null,
            etablissementEmail   : $etab['email']      ?? null,
            lignesMatieres       : $lignes,
            moyennePeriode       : $periodeMoy->isEmpty() ? 0.0 : $periodeMoy->getValue(),
            mentionCode          : $mention->getCode(),
            mentionLabel         : $mention->getLabel(),
            mentionCss           : $mention->getCssColor(),
            admis                : $mention->isAdmis(),
            aEliminatoire        : $aEliminatoire,
            rang                 : $rang,
            nbEleves             : $nbEleves,
            moyenneClasse        : $moyClasse,
            statistiquesClasse   : $statsClasse,
            decision             : $decision['decision']   ?? 'indeterminate',
            decisionMotif        : $decision['motif']      ?? '',
            appreciationPp       : $appreciationPp,
            appreciationDirecteur: null,
            signatures           : $this->buildSignatures(),
            verificationToken    : $token,
            qrCodeUrl            : $qrUrl,
            statut               : $preview ? 'brouillon' : 'brouillon',
            generatedAt          : date('Y-m-d H:i:s'),
            generatedById        : $userId,
            publishedAt          : null,
            archivedAt           : null,
            resultatsAnnuels     : $resultatsAnnuels,
            moyenneLitteraire    : $moyennesFiliere['litteraire']   ?? null,
            moyenneScientifique  : $moyennesFiliere['scientifique'] ?? null,
            moyenneAutre         : $moyennesFiliere['autre']        ?? null,
            absences             : $absences,
            effectifClasse       : $this->repo->effectifClasse((int)$eleve->classe_id),
        );
    }

    /**
     * Regroupe les lignes de notes brutes par matière
     * dans le format attendu par AcademicCalculationService::calculerBulletin.
     */
    public function groupNotesByMatiere(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $row = (array)$row;
            $mid = (int)$row['matiere_id'];

            if (!isset($grouped[$mid])) {
                $grouped[$mid] = [
                    'matiere_id'  => $mid,
                    'matiere_nom' => $row['matiere_nom'] ?? '',
                    'coefficient' => (float)($row['coeff_matiere'] ?? 1.0),
                    'categorie'   => $row['matiere_categorie'] ?? 'autre',
                    'notes'       => [],
                ];
            }

            $grouped[$mid]['notes'][] = [
                'valeur'             => isset($row['valeur']) ? (float)$row['valeur'] : null,
                'note_max'           => (float)($row['note_max']   ?? 20.0),
                'coefficient'        => (float)($row['coeff_eval'] ?? 1.0),
                'est_absent'         => (bool)($row['est_absent']  ?? false),
                'est_eliminatoire'   => (bool)($row['est_eliminatoire'] ?? false),
                'seuil_eliminatoire' => isset($row['seuil_eliminatoire'])
                    ? (float)$row['seuil_eliminatoire'] : null,
                'type_code'          => $row['type_evaluation_code'] ?? null,
            ];
        }
        return $grouped;
    }

    /**
     * Construit les lignes matières enrichies (avec mention et appréciation par matière).
     *
     * @param array $rangsParMatiere  matiereId => {moyenneClasse: ?float, rang: ?int}
     *                                (bulletin V1 papier, cf. moyenneEtRangParMatiere())
     */
    private function buildLignesMatieres(
        array $matieresData,
        array $calcMatieres,
        array $rangsParMatiere = [],
        int   $eleveId = 0,
        int   $periodeId = 0,
    ): array {
        $lignes = [];
        foreach ($matieresData as $mid => $data) {
            $calcM = $calcMatieres[$mid] ?? null;
            if (!$calcM) continue;

            $moyMatiere = $calcM['moyenne'];
            $mention    = $this->calculator->mention($moyMatiere);
            // Saisie manuelle par le professeur (app/Modules/Academique/Controllers/
            // AppreciationController.php) — jamais générée automatiquement : une
            // cellule vide signale au professeur qu'il reste à la remplir.
            $apprMat    = $this->apprRepo->find($eleveId, $mid, $periodeId) ?? '';

            $lignes[] = [
                'matiere_id'      => $mid,
                'matiere_nom'     => $data['matiere_nom'] ?? '',
                'coefficient'     => $data['coefficient'] ?? 1.0,
                'notes'           => array_map(fn($n) => [
                    'valeur'      => $n['valeur'],
                    'note_max'    => $n['note_max'],
                    'coeff_eval'  => $n['coefficient'],
                    'est_absent'  => $n['est_absent'],
                ], $data['notes']),
                'moyenne'         => $moyMatiere->isEmpty() ? null : $moyMatiere->getValue(),
                'compo'           => $this->extraireNoteComposition($data['notes']),
                'moyenne_classe'  => $rangsParMatiere[$mid]['moyenneClasse'] ?? null,
                'rang'            => $rangsParMatiere[$mid]['rang']          ?? null,
                'mention_code'    => $mention->getCode(),
                'mention_label'   => $mention->getLabel(),
                'mention_css'     => $mention->getCssColor(),
                'appreciation'    => $apprMat,
                'aEliminatoire'   => $calcM['aEliminatoire'],
                'nb_notes'        => $calcM['nbNotes'],
                'nb_absents'      => $calcM['nbAbsents'],
            ];
        }
        return $lignes;
    }

    /**
     * Note de composition d'une matière (colonne « Compo » du bulletin V1
     * papier) — moyenne des évaluations de type 'examen' (TypeEvaluationModel::
     * V1_CODES), ramenées /20 via AcademicCalculationService. Retourne null
     * si la matière n'a aucune évaluation de ce type sur la période (aucune
     * formule de moyenne réinventée : noteRameneeSur20() uniquement).
     */
    private function extraireNoteComposition(array $notes): ?float
    {
        $valeurs = [];
        foreach ($notes as $n) {
            if (($n['type_code'] ?? null) !== 'examen') continue;
            if (($n['est_absent'] ?? false) || $n['valeur'] === null) continue;
            $valeurs[] = $this->calculator->noteRameneeSur20((float)$n['valeur'], (float)$n['note_max']);
        }
        if (empty($valeurs)) return null;
        return $this->calculator->arrondir(array_sum($valeurs) / count($valeurs));
    }

    /**
     * Moyenne de classe et rang de l'élève pour chaque matière (colonnes
     * « Moy. Classe » / « Rang » du bulletin V1 papier). Délègue à
     * RankingEngine::classementMatiere() — aucun calcul ici. Le résultat par
     * (classe, période, matière) est mémorisé en mémoire pour la durée de la
     * requête : appelé une fois par élève, il évite de recalculer le même
     * classement de matière pour chaque élève d'une génération de classe
     * (genererBulletinsClasse).
     *
     * @return array matiereId => {moyenneClasse: ?float, rang: ?int}
     */
    private function moyenneEtRangParMatiere(int $classeId, int $periodeId, int $eleveId, array $matiereIds): array
    {
        $result = [];
        foreach ($matiereIds as $matiereId) {
            $cacheKey = "{$classeId}:{$periodeId}:{$matiereId}";
            if (!isset($this->matiereClassementCache[$cacheKey])) {
                $this->matiereClassementCache[$cacheKey] =
                    $this->rankingEngine->classementMatiere($matiereId, $periodeId, $classeId);
            }
            $classement = $this->matiereClassementCache[$cacheKey];

            $rang = null;
            foreach ($classement->rankings as $entry) {
                if ((int)$entry['eleve_id'] === $eleveId) {
                    $rang = $entry['rang'];
                    break;
                }
            }

            $result[$matiereId] = [
                'moyenneClasse' => $classement->getMoyenneClasse(),
                'rang'          => $rang,
            ];
        }
        return $result;
    }

    /**
     * Moyennes de l'élève regroupées par filière de matière (littéraire/
     * scientifique/autre — matieres.categorie, cf. T034). Réutilise
     * exclusivement AcademicCalculationService::moyennePeriode() — aucune
     * formule de moyenne réinventée ici (règle d'or du module).
     *
     * @param array $matieresData  Même format que groupNotesByMatiere().
     * @param array $calcMatieres  $calcResult['matieres'] de calculerBulletin().
     * @return array{litteraire: ?float, scientifique: ?float, autre: ?float}
     */
    private function moyennesParFiliere(array $matieresData, array $calcMatieres): array
    {
        $groupes = ['litteraire' => [], 'scientifique' => [], 'autre' => []];

        foreach ($matieresData as $matiereId => $data) {
            $calcM = $calcMatieres[$matiereId] ?? null;
            if (!$calcM) continue;

            $categorie = $data['categorie'] ?? 'autre';
            if (!isset($groupes[$categorie])) $categorie = 'autre';

            $groupes[$categorie][] = [
                'moyenne'     => $calcM['moyenne'],
                'coefficient' => (float)($data['coefficient'] ?? 1.0),
            ];
        }

        $result = [];
        foreach ($groupes as $categorie => $matieres) {
            $moy = $this->calculator->moyennePeriode($matieres);
            $result[$categorie] = $moy['moyenne']->isEmpty() ? null : $moy['moyenne']->getValue();
        }

        return $result;
    }

    /**
     * Résultats de classe 1er/2e semestre + moyenne annuelle, pour les
     * établissements fonctionnant en semestres (type_periode='semestre',
     * cf. PeriodeScolaireDTO::typeLabels()). Délègue entièrement à
     * RankingEngine — aucun calcul de moyenne ici. Retourne des valeurs
     * null si les 2 semestres de l'année ne sont pas encore disponibles
     * (établissement en trimestres, ou année en cours non complète) — dans
     * ce cas rang/décision annuels restent également null (bulletin de
     * bilan intermédiaire, S1).
     *
     * @return array{
     *     moyenne1erSemestre: ?float, moyenne2emeSemestre: ?float, moyenneAnnuelle: ?float,
     *     rangAnnuel: ?int, nbElevesAnnuel: ?int,
     *     decisionAnnuelleCode: ?string, decisionAnnuelleLabel: ?string
     * }
     */
    public function resultatsClasseEtAnnuels(int $classeId, string $anneeScolaire, int $eleveId): array
    {
        $semestres = $this->periodeRepo->findByAnneeEtType($anneeScolaire, 'semestre');

        $sem1 = null;
        $sem2 = null;
        foreach ($semestres as $s) {
            if ((int)$s->numero === 1) $sem1 = $s;
            if ((int)$s->numero === 2) $sem2 = $s;
        }

        $moy1 = null;
        $moy2 = null;
        $moyAnnuelle  = null;
        $rangAnnuel   = null;
        $nbElevesAnnuel = null;
        $decisionCode  = null;
        $decisionLabel = null;

        if ($sem1 !== null) {
            $moy1 = $this->rankingEngine->classementClasse($classeId, (int)$sem1->id)->getMoyenneClasse();
        }
        if ($sem2 !== null) {
            $moy2 = $this->rankingEngine->classementClasse($classeId, (int)$sem2->id)->getMoyenneClasse();
        }
        if ($sem1 !== null && $sem2 !== null) {
            $classementAnnuel = $this->rankingEngine
                ->classementGeneral($classeId, [(int)$sem1->id, (int)$sem2->id]);
            $moyAnnuelle = $classementAnnuel->getMoyenneClasse();

            $eleveAnnuel  = $this->findEleveInRanking($classementAnnuel, $eleveId);
            $rangAnnuel   = isset($eleveAnnuel['rang']) ? (int)$eleveAnnuel['rang'] : null;
            $nbElevesAnnuel = $classementAnnuel->nbTotal;

            if ($moyAnnuelle !== null) {
                [$decisionCode, $decisionLabel] = $this->decisionAnnuelle((float)$moyAnnuelle);
            }
        }

        return [
            'moyenne1erSemestre'    => $moy1        !== null ? (float)$moy1        : null,
            'moyenne2emeSemestre'   => $moy2        !== null ? (float)$moy2        : null,
            'moyenneAnnuelle'       => $moyAnnuelle !== null ? (float)$moyAnnuelle : null,
            'rangAnnuel'            => $rangAnnuel,
            'nbElevesAnnuel'        => $nbElevesAnnuel,
            'decisionAnnuelleCode'  => $decisionCode,
            'decisionAnnuelleLabel' => $decisionLabel,
        ];
    }

    /**
     * Décision du conseil de classe en fin d'année, mêmes seuils que la
     * décision par période (AcademicCalculationService::prepareDecision()) :
     * seuil de passage $this->notePassage (10.0 par défaut), seuil de
     * rattrapage $this->seuilRattrapage (8.0 par défaut) — appliqués ici à
     * la moyenne ANNUELLE plutôt que par période. Les deux seuils sont
     * configurables par établissement (Paramètres > Notation), voir
     * BulletinEngineFactory.
     *
     * @return array{0: string, 1: string} [code, label]
     */
    private function decisionAnnuelle(float $moyenneAnnuelle): array
    {
        if ($moyenneAnnuelle >= $this->notePassage) {
            return ['admis_superieur', 'Admis en classe supérieure'];
        }
        if ($moyenneAnnuelle >= $this->seuilRattrapage) {
            return ['reorientation', 'Réorientation'];
        }
        return ['redouble', 'Redouble'];
    }

    /**
     * Retrouve les données de classement d'un élève dans un RankingResultDTO.
     */
    private function findEleveInRanking(RankingResultDTO $classement, int $eleveId): array
    {
        foreach ($classement->rankings as $entry) {
            if ((int)$entry['eleve_id'] === $eleveId) {
                return $entry;
            }
        }
        return [];
    }

    /**
     * Génère une appréciation déterministe (stable entre re-générations).
     * L'index est basé sur $seed pour éviter la variation aléatoire.
     */
    private function genererAppreciation(string $mentionCode, int $seed, bool $aEliminatoire): string
    {
        if ($aEliminatoire) {
            return "Note éliminatoire détectée. Un travail intensif et un suivi renforcé sont indispensables.";
        }
        $pool = self::APPRECIATIONS[$mentionCode] ?? self::APPRECIATIONS['P'];
        return $pool[$seed % count($pool)];
    }

    /**
     * Token SHA-256 stable pour un couple (eleve_id, periode_id).
     */
    public function generateVerificationToken(int $eleveId, int $periodeId): string
    {
        return hash('sha256', "bulletin:{$eleveId}:{$periodeId}:{$this->appKey}");
    }

    private function buildSignatures(): array
    {
        return [
            ['role' => 'Professeur Principal', 'nom' => '', 'date' => '', 'signed' => false],
            ['role' => 'Directeur(trice)',      'nom' => '', 'date' => '', 'signed' => false],
            ['role' => 'Parent / Tuteur',       'nom' => '', 'date' => '', 'signed' => false],
        ];
    }

    /**
     * Charge le contexte commun (élève, période, établissement).
     * @return array{0: object, 1: object, 2: array}
     */
    private function fetchContext(int $eleveId, int $periodeId): array
    {
        $eleve = $this->repo->infoEleve($eleveId);
        if (!$eleve) {
            throw new \RuntimeException("Élève introuvable : $eleveId");
        }
        $periode = $this->repo->infoPeriode($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable : $periodeId");
        }
        $etab = $this->repo->infoEtablissement();
        return [$eleve, $periode, $etab];
    }
}
