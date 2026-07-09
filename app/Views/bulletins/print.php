<?php
$b       = $bulletin ?? [];
$mats    = $b['matieres'] ?? [];
$moyGen  = $b['moyenne_generale'] ?? null;
$rang    = $b['rang'] ?? null;
$mention = $b['mention'] ?? null;
$total   = $b['total_eleves'] ?? 0;
$annee   = $periode->annee_scolaire ?? date('Y') . '-' . (date('Y') + 1);

function pnColor(?float $n): string {
    if ($n === null) return '';
    if ($n >= 16) return 'color:#198754;font-weight:bold';
    if ($n >= 12) return 'color:#0d6efd';
    if ($n >= 10) return 'color:#fd7e14';
    return 'color:#dc3545';
}
function pnMentionBorder(?string $m): string {
    return match($m) {
        'Très Bien'  => '#198754',
        'Bien'       => '#0d6efd',
        'Assez Bien' => '#0dcaf0',
        'Passable'   => '#ffc107',
        default      => '#dc3545',
    };
}
?>
<style>
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #222; }
    .bulletin-header { border: 2px solid #0d6efd; border-radius: 8px; padding: 12px 20px; margin-bottom: 16px; }
    .section-title { background: #0d6efd; color: #fff; padding: 4px 12px; font-weight: bold;
                     font-size: 9pt; letter-spacing: .5px; margin: 12px 0 6px; }
    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    th, td { border: 1px solid #dee2e6; padding: 5px 8px; }
    th { background: #f1f3f5; font-weight: bold; text-align: center; }
    .matiere-name { font-weight: bold; }
    .moy-cell { text-align: center; font-weight: bold; font-size: 11pt; }
    .verdict-box { border: 3px solid <?= pnMentionBorder($mention) ?>; border-radius: 8px;
                   text-align: center; padding: 10px; margin-top: 14px; }
    .signature-box { border: 1px solid #adb5bd; padding: 8px 12px; height: 60px;
                     font-size: 8pt; color: #6c757d; }
    .note-ctrl { text-align: center; font-size: 8.5pt; }
    .abs { color: #fd7e14; font-style: italic; }
    @media print { .no-print { display: none !important; } }
</style>

<!-- En-tête établissement ──────────────────────────────────── -->
<div class="bulletin-header">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <div style="font-size:14pt;font-weight:bold;color:#0d6efd">Ecole App</div>
            <div style="font-size:9pt;color:#6c757d">Établissement scolaire — Algérie</div>
            <div style="font-size:9pt;color:#6c757d">Année scolaire : <?= htmlspecialchars($annee, ENT_QUOTES) ?></div>
        </div>
        <div style="text-align:center">
            <div style="font-size:13pt;font-weight:bold;text-transform:uppercase;letter-spacing:1px">
                Bulletin de Notes
            </div>
            <div style="font-size:10pt;color:#0d6efd;font-weight:bold">
                <?= htmlspecialchars($periode->nom ?? '', ENT_QUOTES) ?>
            </div>
        </div>
        <div style="text-align:right;font-size:9pt;color:#6c757d">
            <?php if ($rang !== null && $total > 0): ?>
            <div style="font-size:11pt;font-weight:bold;color:#222">
                Rang : <span style="color:#0d6efd"><?= $rang ?><sup>e</sup> / <?= $total ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Informations élève ─────────────────────────────────────── -->
<div class="section-title">INFORMATIONS DE L'ÉLÈVE</div>
<table style="margin-bottom:10px">
    <tr>
        <td style="width:25%"><strong>Nom & Prénom :</strong></td>
        <td style="width:40%"><?= htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) ?></td>
        <td style="width:15%"><strong>Matricule :</strong></td>
        <td><?= htmlspecialchars($eleve->matricule ?? '', ENT_QUOTES) ?></td>
    </tr>
    <tr>
        <td><strong>Classe :</strong></td>
        <td><?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?></td>
        <td><strong>Sexe :</strong></td>
        <td><?= $eleve->sexe === 'M' ? 'Masculin' : 'Féminin' ?></td>
    </tr>
    <?php if (!empty($eleve->date_naissance)): ?>
    <tr>
        <td><strong>Date de naissance :</strong></td>
        <td><?= date('d/m/Y', strtotime($eleve->date_naissance)) ?></td>
        <td></td><td></td>
    </tr>
    <?php endif; ?>
</table>

<!-- Notes par matière ──────────────────────────────────────── -->
<div class="section-title">RÉSULTATS DÉTAILLÉS</div>
<table>
    <thead>
        <tr>
            <th style="text-align:left;width:22%">Matière</th>
            <th style="width:6%">Coef.</th>
            <th colspan="<?= max(1, count($mats) > 0 ? max(array_map(fn($m) => count($m->controles), $mats)) : 1) ?>">
                Évaluations
            </th>
            <th style="width:10%">Moy. /20</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $maxCtrls = 1;
    foreach ($mats as $m) { $maxCtrls = max($maxCtrls, count($m->controles)); }
    ?>
    <?php foreach ($mats as $mat): ?>
    <tr>
        <td class="matiere-name"><?= htmlspecialchars($mat->nom, ENT_QUOTES) ?></td>
        <td style="text-align:center"><?= $mat->coefficient ?></td>
        <?php foreach ($mat->controles as $ctrl): ?>
        <?php
        $typeL = \App\Models\ControleModel::TYPES[$ctrl->type] ?? $ctrl->type;
        if ($ctrl->absent) {
            $disp = '<span class="abs">Abs.</span>';
        } elseif ($ctrl->note !== null) {
            $disp = '<span style="' . pnColor((float)$ctrl->note / (float)$ctrl->note_max * 20) . '">'
                  . number_format($ctrl->note, 1) . '/' . (int)$ctrl->note_max . '</span>';
        } else {
            $disp = '<span style="color:#adb5bd">—</span>';
        }
        ?>
        <td class="note-ctrl">
            <div style="font-size:7.5pt;color:#6c757d">
                <?= htmlspecialchars($ctrl->libelle, ENT_QUOTES) ?>
                <br>(×<?= $ctrl->ctrl_coef ?>)
            </div>
            <?= $disp ?>
        </td>
        <?php endforeach; ?>
        <?php for ($pad = count($mat->controles); $pad < $maxCtrls; $pad++): ?>
        <td></td>
        <?php endfor; ?>
        <td class="moy-cell" style="<?= pnColor($mat->moyenne_matiere !== null ? (float)$mat->moyenne_matiere : null) ?>">
            <?= $mat->moyenne_matiere !== null ? number_format($mat->moyenne_matiere, 2) : '—' ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <tr style="background:#f8f9fa;font-weight:bold">
        <td colspan="<?= 1 + $maxCtrls + 1 ?>" style="text-align:right;padding-right:12px">
            MOYENNE GÉNÉRALE
        </td>
        <td class="moy-cell" style="font-size:13pt;<?= pnColor($moyGen !== null ? (float)$moyGen : null) ?>">
            <?= $moyGen !== null ? number_format($moyGen, 2) : '—' ?>
        </td>
    </tr>
    </tbody>
</table>

<!-- Verdict ────────────────────────────────────────────────── -->
<div class="verdict-box">
    <div style="font-size:9pt;color:#6c757d;margin-bottom:4px">DÉCISION DU CONSEIL DE CLASSE</div>
    <?php if ($mention): ?>
    <div style="font-size:16pt;font-weight:bold;color:<?= pnMentionBorder($mention) ?>">
        <?= htmlspecialchars($mention, ENT_QUOTES) ?>
    </div>
    <?php else: ?>
    <div style="color:#6c757d">Non calculé</div>
    <?php endif; ?>
    <?php if ($moyGen !== null): ?>
    <div style="font-size:10pt;margin-top:4px">
        Moyenne : <strong><?= number_format($moyGen, 2) ?>/20</strong>
        <?php if ($rang !== null && $total > 0): ?>
        &nbsp;—&nbsp; Rang : <strong><?= $rang ?>e / <?= $total ?></strong>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($b['meilleure_moy']) || !empty($b['moins_bonne_moy'])): ?>
    <div style="font-size:8pt;color:#6c757d;margin-top:6px">
        Moy. classe : min <?= $b['moins_bonne_moy'] !== null ? number_format($b['moins_bonne_moy'], 2) : '—' ?>
        — max <?= $b['meilleure_moy'] !== null ? number_format($b['meilleure_moy'], 2) : '—' ?>
    </div>
    <?php endif; ?>
</div>

<!-- Signatures ─────────────────────────────────────────────── -->
<div class="section-title" style="margin-top:18px">SIGNATURES</div>
<table style="margin-top:6px">
    <tr>
        <td style="width:33%" class="signature-box">
            <div>Le Directeur</div>
        </td>
        <td style="width:34%" class="signature-box">
            <div>Le Professeur Principal</div>
        </td>
        <td style="width:33%" class="signature-box">
            <div>Le Parent / Tuteur</div>
        </td>
    </tr>
</table>

<div style="text-align:center;margin-top:14px;font-size:7.5pt;color:#adb5bd">
    Bulletin généré le <?= date('d/m/Y à H:i') ?> — Ecole App
</div>
