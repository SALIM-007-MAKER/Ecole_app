<?php
// Usage : include avec $lignesMatieres (BulletinData::$lignesMatieres, enrichi
// de 'compo', 'moyenne_classe', 'rang' par BulletinGenerator)
$totalCoef    = 0.0;
$totalMoyCoef = 0.0;
foreach ($lignesMatieres as $l) {
    $totalCoef += (float)($l['coefficient'] ?? 0);
    if (($l['moyenne'] ?? null) !== null) {
        $totalMoyCoef += (float)$l['moyenne'] * (float)($l['coefficient'] ?? 0);
    }
}
?>
<table class="notes-table box">
    <thead>
        <tr>
            <th style="width:20%">Discipline</th>
            <th style="width:6%">Coef</th>
            <th style="width:12%">Moy. Classe</th>
            <th style="width:8%">Compo</th>
            <th style="width:9%">Moy/20</th>
            <th style="width:10%">Moy. Coef</th>
            <th style="width:7%">Rang</th>
            <th>Appréciations des Professeurs</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lignesMatieres as $l): ?>
        <tr>
            <td class="discipline"><?= e($l['matiere_nom']) ?></td>
            <td><?= e((string)$l['coefficient']) ?></td>
            <td><?= fmtMoy($l['moyenne_classe'] ?? null) ?></td>
            <td><?= fmtMoy($l['compo'] ?? null) ?></td>
            <td class="moy20"><?= fmtMoy($l['moyenne'] ?? null) ?></td>
            <td><?= ($l['moyenne'] ?? null) !== null ? fmtMoy((float)$l['moyenne'] * (float)$l['coefficient']) : '—' ?></td>
            <td><?= ordinal($l['rang'] ?? null) ?></td>
            <td class="appreciation"><?= e($l['appreciation']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td class="discipline">Total</td>
            <td><?= fmtMoy($totalCoef) ?></td>
            <td></td>
            <td></td>
            <td></td>
            <td><?= fmtMoy($totalMoyCoef) ?></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>
