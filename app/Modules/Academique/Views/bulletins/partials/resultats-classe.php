<?php
// Usage : include avec $resultatsAnnuels {moyenne1erSemestre, moyenne2emeSemestre,
// moyenneAnnuelle}, $statistiquesClasse {min, max, moyenne}, $estBilanAnnuel
// (bool — bulletin du 2ème semestre uniquement). Le rang annuel n'apparaît pas
// sur le modèle officiel (seul le rang de la période s'affiche, cf.
// moyenne-rang.php) — resultatsAnnuels['rangAnnuel'] reste calculé par le
// moteur mais n'est volontairement pas imprimé ici.
$estBilanAnnuel = $estBilanAnnuel ?? false;
?>
<div class="box-title">Resultats de la classe</div>
<div class="box-content">
    <?php if ($estBilanAnnuel): ?>
    <div class="rc-row"><span>1er semestre :</span> <span><?= fmtMoy($resultatsAnnuels['moyenne1erSemestre'] ?? null) ?></span></div>
    <div class="rc-row"><span>2ème semestre :</span> <span><?= fmtMoy($resultatsAnnuels['moyenne2emeSemestre'] ?? null) ?></span></div>
    <div class="rc-row"><span>Annuelle :</span> <span><?= fmtMoy($resultatsAnnuels['moyenneAnnuelle'] ?? null) ?></span></div>
    <?php endif; ?>
    <div class="rc-row"><span>Plus forte moyenne :</span> <span><?= fmtMoy($statistiquesClasse['max'] ?? null) ?></span></div>
    <div class="rc-row"><span>Plus faible moyenne :</span> <span><?= fmtMoy($statistiquesClasse['min'] ?? null) ?></span></div>
    <div class="rc-row"><span>Moyenne classe :</span> <span><?= fmtMoy($statistiquesClasse['moyenne'] ?? null) ?></span></div>
</div>
