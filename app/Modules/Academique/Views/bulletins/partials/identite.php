<?php
// Usage : include avec $eleveNomComplet, $classeNom, $effectif, $anneeScolaire, $dateEdition
?>
<table class="identite-table">
    <tr>
        <td class="col-nom">
            <strong>Nom et Prénom :</strong> <?= e($eleveNomComplet) ?>
        </td>
        <td class="col-info">
            <div><strong>Année Scolaire :</strong> <?= e($anneeScolaire) ?></div>
            <div><strong>Classe :</strong> <?= e($classeNom) ?></div>
            <div><strong>Effectif :</strong> <?= (int)$effectif ?></div>
            <div><strong>Date :</strong> <?= e($dateEdition) ?></div>
        </td>
    </tr>
</table>
