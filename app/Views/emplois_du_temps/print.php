<?php
$creneaux = $creneaux ?? [];
$grid     = $grid     ?? [];
$jours    = $jours    ?? \App\Models\EmploiDuTempsModel::JOURS;
$label    = $label    ?? '';
$annee    = $annee    ?? '';

$joursActifs = array_keys($jours);
// N'afficher que les jours avec des cours
$joursActifs = array_filter($joursActifs, fn($j) => !empty($grid[$j]));
$joursActifs = !empty($joursActifs) ? array_values($joursActifs) : [1,2,3,4,5];
?>
<style>
.edt-print-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
.edt-print-table th { background: #343a40; color: #fff; padding: 6px 8px; text-align: center; border: 1px solid #343a40; }
.edt-print-table td { border: 1px solid #dee2e6; padding: 3px; vertical-align: top; }
.edt-time-p { text-align: center; color: #6c757d; font-size: 8pt; white-space: nowrap; background: #f8f9fa; min-width: 65px; }
.edt-seance-p { border-radius: 4px; padding: 4px 6px; margin-bottom: 2px; font-size: 8.5pt; }
.edt-pause-p { background: #f0f0f0; text-align: center; color: #6c757d; font-style: italic; font-size: 8pt; }
.print-title { font-size: 14pt; font-weight: bold; margin-bottom: 4px; }
.print-sub { font-size: 9pt; color: #555; margin-bottom: 14px; }
</style>

<div class="print-title">Emploi du Temps — <?= htmlspecialchars($label, ENT_QUOTES) ?></div>
<div class="print-sub">Généré le <?= date('d/m/Y à H:i') ?></div>

<table class="edt-print-table">
    <thead>
        <tr>
            <th style="width:65px">Horaire</th>
            <?php foreach ($joursActifs as $jour): ?>
            <th><?= htmlspecialchars($jours[$jour], ENT_QUOTES) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($creneaux as $cr):
        $isPause = $cr->type !== 'cours';
    ?>
    <?php if ($isPause): ?>
    <tr>
        <td class="edt-time-p">
            <?= substr($cr->heure_debut,0,5) ?><br><?= substr($cr->heure_fin,0,5) ?>
        </td>
        <td colspan="<?= count($joursActifs) ?>" class="edt-pause-p">
            <?= htmlspecialchars($cr->nom, ENT_QUOTES) ?>
        </td>
    </tr>
    <?php else: ?>
    <tr>
        <td class="edt-time-p">
            <strong style="font-size:7.5pt"><?= htmlspecialchars($cr->nom, ENT_QUOTES) ?></strong><br>
            <?= substr($cr->heure_debut,0,5) ?><br>
            <span style="color:#aaa">↓</span><br>
            <?= substr($cr->heure_fin,0,5) ?>
        </td>
        <?php foreach ($joursActifs as $jour): ?>
        <td style="min-height:50px">
            <?php $seances = $grid[$jour][$cr->id] ?? []; ?>
            <?php foreach ($seances as $s):
                $color  = \App\Models\EmploiDuTempsModel::getColor($s->matiere_id, $s->couleur);
                $hexRaw = ltrim($color, '#');
                [$r,$g,$b] = [hexdec(substr($hexRaw,0,2)), hexdec(substr($hexRaw,2,2)), hexdec(substr($hexRaw,4,2))];
                $txt = ((0.299*$r + 0.587*$g + 0.114*$b) > 180) ? '#111' : '#fff';
            ?>
            <div class="edt-seance-p" style="background:<?= $color ?>;color:<?= $txt ?>">
                <strong><?= htmlspecialchars($s->matiere_nom, ENT_QUOTES) ?></strong><br>
                <?= htmlspecialchars($s->prof_fullname, ENT_QUOTES) ?><br>
                <?php if ($s->salle_nom): ?>
                <em style="font-size:7.5pt"><?= htmlspecialchars($s->salle_nom, ENT_QUOTES) ?></em>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </td>
        <?php endforeach; ?>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>

<div style="margin-top:16px;font-size:7.5pt;color:#999;text-align:center;border-top:1px solid #dee2e6;padding-top:8px;">
    Emploi du temps généré par ecole_app — <?= date('d/m/Y H:i:s') ?>
</div>
