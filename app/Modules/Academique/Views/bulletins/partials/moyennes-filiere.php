<?php
// Usage : include avec $moyenneLitteraire, $moyenneScientifique, $moyenneAutre (?float)
?>
<table class="filiere-table box">
    <tr>
        <td>Moyenne matières littéraires : <strong><?= fmtMoy($moyenneLitteraire) ?></strong></td>
        <td>Moyenne matières scientifiques : <strong><?= fmtMoy($moyenneScientifique) ?></strong></td>
        <td>Moyenne autres matières : <strong><?= fmtMoy($moyenneAutre) ?></strong></td>
    </tr>
</table>
