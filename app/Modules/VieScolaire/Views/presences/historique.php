<?php
$session    = $session    ?? [];
$historique = $historique ?? [];

$statutLabels = [
    'present'          => 'Présent',
    'absent'           => 'Absent',
    'retard'           => 'Retard',
    'dispense'         => 'Dispensé',
    'sortie_anticipee' => 'Sortie anticipée',
];
$statutColors = [
    'present'          => 'emerald',
    'absent'           => 'red',
    'retard'           => 'amber',
    'dispense'         => 'sky',
    'sortie_anticipee' => 'orange',
];
?>

<div class="flex items-center gap-4 mb-6">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>"
       class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à l'appel
    </a>
    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="history" class="w-5 h-5 text-violet-600"></i>
        Historique des corrections
    </h2>
</div>

<div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-5 text-sm text-slate-600">
    Appel du <span class="font-medium text-slate-900"><?= date('d/m/Y', strtotime($session['date_appel'])) ?></span>
    — <span class="font-medium text-slate-900"><?= htmlspecialchars($session['classe_nom'] ?? '', ENT_QUOTES) ?></span>
    — <span class="font-medium text-slate-900"><?= htmlspecialchars($session['enseignant_nom'] ?? '', ENT_QUOTES) ?></span>
</div>

<?php if (empty($historique)): ?>
<div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
    <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p class="text-sm">Aucune correction enregistrée pour cette session.</p>
</div>
<?php else: ?>
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <span class="text-sm font-medium text-slate-700">
            <?= count($historique) ?> correction<?= count($historique) > 1 ? 's' : '' ?> enregistrée<?= count($historique) > 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Avant</th>
                    <th class="px-2 py-3"></th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Après</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Motif</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Modifié par</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($historique as $h):
                $aColor = $statutColors[$h['ancien_statut']] ?? 'slate';
                $nColor = $statutColors[$h['nouveau_statut']] ?? 'slate';
                $aLabel = $statutLabels[$h['ancien_statut']] ?? $h['ancien_statut'];
                $nLabel = $statutLabels[$h['nouveau_statut']] ?? $h['nouveau_statut'];
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-900">
                    <?= htmlspecialchars($h['eleve_nom'], ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $aColor ?>-100 text-<?= $aColor ?>-700">
                        <?= htmlspecialchars($aLabel, ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-2 py-3 text-slate-400">
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $nColor ?>-100 text-<?= $nColor ?>-700">
                        <?= htmlspecialchars($nLabel, ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs max-w-xs truncate">
                    <?= htmlspecialchars($h['motif'] ?? '—', ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">
                    <?= htmlspecialchars($h['modifie_par_nom'] ?? '', ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">
                    <?= date('d/m/Y H:i', strtotime($h['modifie_le'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
