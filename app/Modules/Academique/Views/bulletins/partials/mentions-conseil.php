<?php
// Usage : include sans variable — cases volontairement vierges (cochage
// manuscrit par le conseil des professeurs après impression).
$mentions = ['Félicitations', 'Encouragements', "Tableau d'honneur", 'Avertissement', 'Blâme'];
?>
<div class="box-title">Mentions du conseil<br>des professeurs</div>
<div class="box-content mentions-list">
    <?php foreach ($mentions as $m): ?>
        <div class="m-row"><span><?= e($m) ?></span> <span class="checkbox"></span></div>
    <?php endforeach; ?>
</div>
