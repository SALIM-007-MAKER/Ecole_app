<?php
// Usage : include avec $alertesWidgets = array of alerte widgets
foreach ($alertesWidgets ?? [] as $aw):
    foreach ($aw['alertes'] ?? [] as $alerte):
        $metrique = $alerte['metrique'] ?? '';
        $valeur   = $alerte['valeur']   ?? '';
        $seuil    = $alerte['seuil']    ?? '';
?>
<div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-2">
    <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0"></i>
    <div class="text-sm text-amber-800">
        <strong><?= htmlspecialchars(str_replace('_', ' ', ucfirst($metrique))) ?></strong>
        : valeur actuelle <strong><?= htmlspecialchars((string)$valeur) ?></strong>
        (seuil : <?= htmlspecialchars((string)$seuil) ?>)
    </div>
</div>
<?php
    endforeach;
endforeach;
?>
