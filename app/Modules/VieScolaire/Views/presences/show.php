<?php
$session        = $session        ?? [];
$eleves         = $eleves         ?? [];
$presencesIndex = $presencesIndex ?? [];
$stats          = $stats          ?? [];
$perms          = $perms          ?? [];
$canUpdate      = $canUpdate      ?? false;
$canValidate    = $canValidate    ?? false;
$csrfToken      = \Core\Session::getCsrfToken();

$isValide  = ($session['statut'] ?? '') === 'valide';
$statutColors = [
    'present'          => 'emerald',
    'absent'           => 'red',
    'retard'           => 'amber',
    'dispense'         => 'sky',
    'sortie_anticipee' => 'orange',
];
$statutLabels = [
    'present'          => 'Présent',
    'absent'           => 'Absent',
    'retard'           => 'Retard',
    'dispense'         => 'Dispensé',
    'sortie_anticipee' => 'Sortie anticipée',
];
?>

<!-- En-tête -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences"
           class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors mb-3">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à la liste
        </a>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-check" class="w-5 h-5 text-violet-600"></i>
            Appel — <?= htmlspecialchars($session['classe_nom'] ?? '', ENT_QUOTES) ?>
            — <?= date('d/m/Y', strtotime($session['date_appel'])) ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= htmlspecialchars($session['enseignant_nom'] ?? '', ENT_QUOTES) ?>
            <?php if (!empty($session['matiere_nom'])): ?>
            · <?= htmlspecialchars($session['matiere_nom'], ENT_QUOTES) ?>
            <?php endif; ?>
            <?php if (!empty($session['heure_debut'])): ?>
            · <?= htmlspecialchars($session['heure_debut'], ENT_QUOTES) ?>
            <?php if (!empty($session['heure_fin'])): ?> – <?= htmlspecialchars($session['heure_fin'], ENT_QUOTES) ?><?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($isValide): ?>
        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-700">
            <i data-lucide="check-circle" class="w-4 h-4"></i>Validé
        </span>
        <?php else: ?>
        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium bg-amber-100 text-amber-700">
            <i data-lucide="clock" class="w-4 h-4"></i>Brouillon
        </span>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>/historique"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <i data-lucide="history" class="w-4 h-4"></i>Historique
        </a>
        <?php if (!$isValide && $canValidate): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>/valider"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors">
            <i data-lucide="check" class="w-4 h-4"></i>Valider l'appel
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>
<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<!-- Statistiques de la session -->
<?php if (!empty($stats) && (int)$stats['total'] > 0): ?>
<div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-6">
    <?php
    $cards = [
        ['label' => 'Total',            'val' => $stats['total']             ?? 0, 'color' => 'slate',   'icon' => 'users'],
        ['label' => 'Présents',         'val' => $stats['presents']          ?? 0, 'color' => 'emerald', 'icon' => 'user-check'],
        ['label' => 'Absents',          'val' => $stats['absents']           ?? 0, 'color' => 'red',     'icon' => 'user-x'],
        ['label' => 'Retards',          'val' => $stats['retards']           ?? 0, 'color' => 'amber',   'icon' => 'clock'],
        ['label' => 'Dispensés',        'val' => $stats['dispenses']         ?? 0, 'color' => 'sky',     'icon' => 'shield'],
        ['label' => 'Taux présence',    'val' => ($stats['taux_presence'] ?? 0) . '%', 'color' => 'violet', 'icon' => 'percent'],
    ];
    foreach ($cards as $c): ?>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 text-center">
        <div class="w-8 h-8 rounded-lg bg-<?= $c['color'] ?>-100 flex items-center justify-center mx-auto mb-2">
            <i data-lucide="<?= $c['icon'] ?>" class="w-4 h-4 text-<?= $c['color'] ?>-600"></i>
        </div>
        <p class="text-lg font-bold text-slate-900"><?= $c['val'] ?></p>
        <p class="text-xs text-slate-500"><?= $c['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Formulaire de pointage -->
<?php if (!empty($eleves)): ?>
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <span class="text-sm font-semibold text-slate-700">
            <?= count($eleves) ?> élève<?= count($eleves) > 1 ? 's' : '' ?>
            <?php if (!empty($presencesIndex)): ?>
            <span class="text-slate-400">· <?= count($presencesIndex) ?> pointé<?= count($presencesIndex) > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </span>
        <?php if (!$isValide && $canUpdate): ?>
        <div class="flex gap-2 items-center">
            <span class="text-xs text-slate-500">Tout marquer :</span>
            <button type="button" onclick="setAllStatuts('present')"
                    class="px-2 py-1 text-xs rounded-md bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-medium">Présents</button>
            <button type="button" onclick="setAllStatuts('absent')"
                    class="px-2 py-1 text-xs rounded-md bg-red-100 text-red-700 hover:bg-red-200 font-medium">Absents</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$isValide && $canUpdate): ?>
    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>/pointer" id="appel-form">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 w-8">#</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
                    <?php if (!$isValide && $canUpdate): ?>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Heure / Retard</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Observation</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($eleves as $i => $eleve):
                $pres     = $presencesIndex[$eleve['id']] ?? null;
                $curStatut = $pres['statut'] ?? 'present';
            ?>
            <tr class="hover:bg-slate-50 transition-colors" id="row-<?= $eleve['id'] ?>">
                <td class="px-4 py-3 text-slate-400 text-xs"><?= $i + 1 ?></td>
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-900">
                        <?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom'], ENT_QUOTES) ?>
                    </div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($eleve['matricule'] ?? '', ENT_QUOTES) ?></div>
                </td>
                <td class="px-4 py-3">
                    <?php if (!$isValide && $canUpdate): ?>
                    <select name="presences[<?= $eleve['id'] ?>][statut]"
                            class="statut-select rounded-lg border border-slate-200 text-sm px-2 py-1.5 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none"
                            data-row="<?= $eleve['id'] ?>"
                            onchange="updateRowColor(this)">
                        <?php foreach ($statutLabels as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $curStatut === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lbl, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <?php $col = $statutColors[$curStatut] ?? 'slate'; ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $col ?>-100 text-<?= $col ?>-700">
                        <?= htmlspecialchars($statutLabels[$curStatut] ?? $curStatut, ENT_QUOTES) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <?php if (!$isValide && $canUpdate): ?>
                <td class="px-4 py-3">
                    <div id="heure-block-<?= $eleve['id'] ?>" class="<?= !in_array($curStatut, ['retard','sortie_anticipee']) ? 'hidden' : '' ?> flex gap-2">
                        <input type="time" name="presences[<?= $eleve['id'] ?>][heure_arrivee]"
                               value="<?= htmlspecialchars($pres['heure_arrivee'] ?? '', ENT_QUOTES) ?>"
                               class="w-28 rounded-lg border border-slate-200 text-xs px-2 py-1 focus:ring-2 focus:ring-violet-300 focus:outline-none"
                               placeholder="HH:MM">
                        <input type="number" name="presences[<?= $eleve['id'] ?>][retard_minutes]"
                               value="<?= htmlspecialchars($pres['retard_minutes'] ?? '', ENT_QUOTES) ?>"
                               class="w-16 rounded-lg border border-slate-200 text-xs px-2 py-1 focus:ring-2 focus:ring-violet-300 focus:outline-none"
                               placeholder="min" min="1" max="240">
                    </div>
                </td>
                <td class="px-4 py-3">
                    <input type="text" name="presences[<?= $eleve['id'] ?>][observation]"
                           value="<?= htmlspecialchars($pres['observation'] ?? '', ENT_QUOTES) ?>"
                           class="w-full rounded-lg border border-slate-200 text-xs px-2 py-1 focus:ring-2 focus:ring-violet-300 focus:outline-none"
                           placeholder="Observation…">
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$isValide && $canUpdate): ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center gap-3">
        <button type="submit" form="appel-form"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer le pointage
        </button>
        <?php if ($canValidate): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>/valider"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-300 text-emerald-700 text-sm font-medium hover:bg-emerald-50 transition-colors">
            <i data-lucide="check-circle" class="w-4 h-4"></i>Enregistrer et valider
        </a>
        <?php endif; ?>
    </div>
    </form>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
    <i data-lucide="users" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p class="text-sm">Aucun élève actif trouvé dans cette classe.</p>
</div>
<?php endif; ?>

<script>
function setAllStatuts(statut) {
    document.querySelectorAll('.statut-select').forEach(function(sel) {
        sel.value = statut;
        updateRowColor(sel);
    });
}

function updateRowColor(sel) {
    const eleveId   = sel.getAttribute('data-row');
    const heureBlock = document.getElementById('heure-block-' + eleveId);
    if (heureBlock) {
        if (['retard', 'sortie_anticipee'].includes(sel.value)) {
            heureBlock.classList.remove('hidden');
        } else {
            heureBlock.classList.add('hidden');
        }
    }
}

// Initialisation au chargement
document.querySelectorAll('.statut-select').forEach(function(sel) {
    updateRowColor(sel);
});
</script>
