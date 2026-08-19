<?php
/**
 * Bulletin Officiel V1 – CSP La Persévérance.
 *
 * Reproduction fidèle du bulletin papier officiel du Complexe Scolaire Privé
 * La Persévérance — pas encore de moteur de modèles multi-établissements
 * (une seule mise en page, câblée en dur ici). Toutes les données (élève,
 * notes, moyenne, rang, appréciations, absences, QR code...) sont
 * entièrement dynamiques, fournies par BulletinController::imprimer() ;
 * seuls le nom de l'institution et sa mise en page reproduisent le papier.
 * Signatures et cachet restent manuscrits après impression (zones vierges,
 * cf. partials/appreciation-visa.php).
 *
 * Vue volontairement découpée en partials réutilisables (partials/) — ce
 * fichier assemble uniquement la mise en page A4 et le CSS d'impression.
 * Cette séparation (calcul dans BulletinData/BulletinGenerator, présentation
 * ici) permet d'ajouter un futur second modèle sans réécrire la logique
 * métier : il suffirait d'une nouvelle vue consommant le même BulletinData.
 *
 * @var array   $etablissement       {nom, adresse, telephone, email, logo}
 * @var string  $titreBulletin
 * @var bool    $estBilanAnnuel      true seulement pour le bulletin du 2ème semestre —
 *                                   contrôle l'affichage des résultats annuels
 *                                   (moyenne/rang/décision), absents du bilan
 *                                   intermédiaire du 1er semestre.
 * @var string  $eleveNomComplet
 * @var string  $classeNom
 * @var int     $effectif
 * @var string  $anneeScolaire
 * @var string  $dateEdition
 * @var array   $lignesMatieres
 * @var float   $totalCoefficient
 * @var float   $totalMoyenneCoef
 * @var float   $moyenneGenerale
 * @var int     $rangEleve
 * @var int     $nbEleves
 * @var array   $resultatsAnnuels    {moyenne1erSemestre, moyenne2emeSemestre, moyenneAnnuelle,
 *                                   rangAnnuel, nbElevesAnnuel, decisionAnnuelleCode, decisionAnnuelleLabel}
 * @var array   $statistiquesClasse  {min, max, moyenne, ...}
 * @var ?float  $moyenneLitteraire
 * @var ?float  $moyenneScientifique
 * @var ?float  $moyenneAutre
 * @var array   $absences            {justifiees, nonJustifiees}
 * @var ?string $appreciationDirecteur
 * @var string  $qrSvg               SVG autonome (Core\QrCode\QrEncoder)
 * @var string  $verificationToken
 */

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
// Reproduit l'écriture manuscrite/tableur du bulletin papier : pas de décimales
// forcées — 20 s'écrit "20", 12.50 s'écrit "12.5", 186.875 reste "186.875".
function fmtMoy(?float $v): string {
    if ($v === null) return '—';
    $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
}
function ordinal(?int $n): string
{
    if ($n === null || $n <= 0) return '—';
    return $n === 1 ? '1er' : $n . 'ème';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Bulletin — <?= e($eleveNomComplet) ?> — <?= e($titreBulletin) ?></title>
<style>
/*
 * Dimensions fixes en mm/pt calées sur le gabarit papier officiel (photo
 * fournie par l'établissement) — pas de design "libre" : chaque bloc vise à
 * occuper la même proportion de la page A4 que sur le document original.
 * Cadre extérieur de chaque tableau (.box, 1.5pt) volontairement plus épais
 * que la grille interne des cellules (0.75pt), comme sur le papier.
 */
* { box-sizing: border-box; margin: 0; padding: 0; }
/* Le gabarit papier comporte un fin cadre à environ 2 mm du bord A4.
 * La page ne doit jamais dépasser la zone imprimable : avec les anciennes
 * marges de 10/12 mm, le bloc de 210 mm était rogné par certains navigateurs
 * lors de « Enregistrer au format PDF ». */
@page { size: A4 portrait; margin: 2mm; }
html, body { height: 100%; }
body { font-family: 'Times New Roman', Times, serif; font-size: 10.5pt; line-height: 1.25; color: #000; background: #fff; }

@media screen {
    body { background: #ccc; }
    .page { margin: 16px auto; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); }
    .page { margin-top: 56px; }
}
@media print {
    .no-print, .no-print-inline { display: none !important; }
    body { margin: 0; }
    .page { break-inside: avoid; page-break-inside: avoid; }
    table { page-break-inside: avoid; }
}
.no-print { position: fixed; top: 0; left: 0; right: 0; z-index: 50;
    background: #1e293b; padding: 8px 16px; display: flex; gap: 8px; justify-content: center; }
.no-print button { font-family: Arial, sans-serif; font-size: 12px; padding: 6px 14px; border-radius: 6px;
    border: 1px solid #fff; cursor: pointer; }
.no-print .btn-print { background: #fff; color: #1e293b; }
.no-print .btn-close { background: transparent; color: #fff; }

/* .page = pleine page imprimable (277mm = 297 - 2x10mm de marge @page).
 * Le bloc absences/visa reçoit une hauteur minimale fixe généreuse (voir plus
 * bas) plutôt qu'un étirement dynamique (flex) : plus fragile à l'impression
 * (constaté en test réel — chevauchement du QR code), moins fidèle en pratique
 * qu'une valeur fixe calée sur le modèle papier. */
.page { width: 206mm; min-height: 293mm; border: 1.5pt solid #000; padding: 2mm; }

table { border-collapse: collapse; width: 100%; }
.box { border: 1.5pt solid #000; }
.box-title { font-weight: bold; text-align: center; font-size: 10pt; padding: 1.5mm 1mm;
    border-bottom: 0.75pt solid #000; text-transform: uppercase; }

/* En-tête établissement */
.entete { width: 100%; margin-bottom: 1.5mm; }
.entete td { vertical-align: middle; text-align: center; padding: 0.5mm 3mm; }
.entete .logo-cell { width: 27mm; }
.entete .logo-cell img { width: 24mm; height: 24mm; object-fit: contain; border-radius: 50%; }
.entete .logo-placeholder { width: 24mm; height: 24mm; border-radius: 50%; border: 0.75pt solid #000;
    display: inline-flex; align-items: center; justify-content: center; font-size: 8pt; color: #666; }
.h-ministere { font-weight: bold; font-size: 16pt; }
.h-dren { font-size: 10pt; }
.h-etab { font-weight: bold; font-size: 14pt; margin-top: 1mm; }
.h-contact { font-size: 10pt; }

/* Titre */
.titre-bulletin { text-align: center; font-weight: bold; font-size: 17pt; letter-spacing: .6px;
    padding: 2mm 0; margin-bottom: 1.5mm; }

/* Identité */
.identite-table { margin-bottom: 1.5mm; }
.identite-table td { border: 1pt solid #000; padding: 2mm 4mm; font-size: 10.5pt; vertical-align: top; }
.identite-table .col-nom { width: 67%; overflow-wrap: anywhere; }
.identite-table .col-info { width: 33%; line-height: 1.5; overflow-wrap: anywhere; }

/* Table des notes */
.notes-table { margin-bottom: 1.5mm; table-layout: fixed; }
.notes-table th, .notes-table td { border: 0.75pt solid #000; padding: 0.9mm 1.5mm; font-size: 10.5pt; line-height: 1.15; text-align: center; overflow-wrap: anywhere; }
.notes-table th { font-weight: bold; background: transparent; font-size: 9.5pt; }
.notes-table td.discipline { text-align: left; font-weight: 500; }
.notes-table td.appreciation { text-align: left; }
.notes-table td.moy20 { font-weight: bold; }
.notes-table tr.total-row td { font-weight: bold; }

/* Triple bloc : moyenne/rang, résultats classe, mentions conseil —
 * hauteur naturellement identique entre les 3 colonnes (une seule <tr>,
 * les cellules d'une même ligne de tableau HTML partagent toujours la
 * même hauteur = celle de la plus haute), garantie par min-height ci-dessous
 * pour un volume visuel proche du modèle même avec peu de contenu. */
.triple-table { margin-bottom: 1.5mm; }
.triple-table > tbody > tr > td { vertical-align: top; padding: 0; border-right: 0.75pt solid #000; }
.triple-table > tbody > tr > td:last-child { border-right: none; }
.triple-table .col1 { width: 32%; }
.triple-table .col2 { width: 35%; }
.triple-table .col3 { width: 33%; }
.box-content { padding: 2mm 3mm; min-height: 18mm; }
.mr-row { text-align: center; margin: 3mm 0; font-size: 11pt; }
.mr-val { font-size: 14pt; font-weight: bold; }
.rc-row { display: flex; justify-content: space-between; font-size: 10.5pt; margin: 1.2mm 0; gap: 2mm; }
.rc-row span:last-child { font-weight: bold; }
.mentions-list { font-size: 10.5pt; }
.mentions-list .m-row { display: flex; justify-content: space-between; align-items: center; margin: 1.8mm 1mm; }
.checkbox { display: inline-block; width: 3mm; height: 3mm; border: 0.75pt solid #000; }

/* Filières — ligne unique, hauteur généreuse comme sur le modèle */
.filiere-table { margin-bottom: 1.5mm; }
.filiere-table td { border: 0.75pt solid #000; padding: 2mm 2mm; font-size: 10.5pt; text-align: center; overflow-wrap: anywhere; }
.filiere-table td:nth-child(1), .filiere-table td:nth-child(2) { width: 35%; }
.filiere-table td:nth-child(3) { width: 30%; }

/* Bas de page : absences / visa — dernier bloc de la page. Hauteur minimale
 * fixe (60mm), généreuse comme sur le modèle papier, pour occuper la partie
 * basse de la page jusqu'au cadre extérieur sans dépendre d'un étirement
 * dynamique (voir note plus haut sur .page). */
.bottom-table td { border: 0.75pt solid #000; vertical-align: top; padding: 0; height: 46mm; }
.bottom-table td:first-child { border-right: 0.75pt solid #000; }
.bottom-table .col-abs { width: 38%; }
.bottom-table .col-visa { width: 62%; }
.abs-row { font-size: 10.5pt; margin: 3mm 4mm; }
.expulsions-zone { border-top: 0.75pt solid #000; margin: 6mm 4mm 0; min-height: 39mm; }
.direction-appreciation { font-size: 10.5pt; font-style: italic; padding: 3mm 4mm 0; }
.visa-zone { position: relative; padding: 3mm 4mm; min-height: 52mm; }
.qr-block { position: absolute; right: 4mm; bottom: 4mm; text-align: center; }
.qr-block svg { width: 20mm; height: 20mm; }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer / Enregistrer en PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<div class="page">

    <?php include __DIR__ . '/partials/header-etablissement.php'; ?>

    <div class="titre-bulletin"><?= e(mb_strtoupper($titreBulletin, 'UTF-8')) ?></div>

    <?php include __DIR__ . '/partials/identite.php'; ?>

    <?php include __DIR__ . '/partials/tableau-matieres.php'; ?>

    <table class="triple-table box">
        <tr>
            <td class="col1">
                <?php include __DIR__ . '/partials/moyenne-rang.php'; ?>
            </td>
            <td class="col2">
                <?php include __DIR__ . '/partials/resultats-classe.php'; ?>
            </td>
            <td class="col3">
                <?php include __DIR__ . '/partials/mentions-conseil.php'; ?>
            </td>
        </tr>
    </table>

    <?php include __DIR__ . '/partials/moyennes-filiere.php'; ?>

    <table class="bottom-table box">
        <tr>
            <td class="col-abs">
                <?php include __DIR__ . '/partials/absences.php'; ?>
            </td>
            <td class="col-visa">
                <?php include __DIR__ . '/partials/appreciation-visa.php'; ?>
            </td>
        </tr>
    </table>

</div>
</body>
</html>
