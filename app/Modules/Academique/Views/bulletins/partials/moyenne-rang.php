<?php
// Usage : include avec $moyenneGenerale (float), $rangEleve (int), $nbEleves (int)
?>
<div class="box-title">Moyenne &amp; rang de l'eleve</div>
<div class="box-content">
    <div class="mr-row">
        En chiffre : <span class="mr-val"><?= fmtMoy($moyenneGenerale) ?></span>
    </div>
    <div class="mr-row">
        Rang : <span class="mr-val"><?= ordinal($rangEleve) ?></span>
    </div>
</div>
