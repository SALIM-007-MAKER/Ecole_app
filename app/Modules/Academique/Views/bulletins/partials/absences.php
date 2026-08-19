<?php
// Usage : include avec $absences = {justifiees: int, nonJustifiees: int}
?>
<div class="box-title">Absences</div>
<div class="abs-row">Justifiées : <strong><?= (int)($absences['justifiees'] ?? 0) ?></strong></div>
<div class="abs-row">Non Justifiées : <strong><?= (int)($absences['nonJustifiees'] ?? 0) ?></strong></div>
<div class="expulsions-zone">
    <div class="box-title" style="border:none;">Expulsions</div>
</div>
