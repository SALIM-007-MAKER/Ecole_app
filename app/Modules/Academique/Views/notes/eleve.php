<?php
$pagination = $pagination ?? ['data'=>[],'total'=>0,'last_page'=>1,'current_page'=>1];
$filters    = $filters    ?? null;
$statuts    = $statuts    ?? [];
$colors     = $colors     ?? [];
$eleve      = $eleve      ?? null;
$user       = $user       ?? [];
?>

<div class="flex items-start justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">Notes</h2>
        <?php if ($eleve): ?>
        <p class="text-sm text-slate-500 mt-0.5">Élève: <?= htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>
    <div class="text-sm text-slate-500">Total: <?= $pagination['total'] ?? 0 ?></div>
</div>

<?php if (empty($pagination['data'])): ?>
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400 shadow-sm">
    <i data-lucide="pen-line" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
    <p class="font-medium">Aucune note trouvée.</p>
    <?php if (!empty($eleve)): ?>
    <a href="<?= BASE_URL ?>/v2/academique/bulletins/<?= $eleve->id ?>/1/imprimer" class="btn btn-primary mt-4">Imprimer bulletin</a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600">Évaluation</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600">Matière</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Note</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Présence</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($pagination['data'] as $note): ?>
            <?php $pct = $note->valeur !== null && (float)$note->note_max > 0 ? round((float)$note->valeur / (float)$note->note_max * 100) : null; ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($note->eval_libelle ?? '—', ENT_QUOTES) ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($note->matiere_nom ?? '—', ENT_QUOTES) ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ((int)$note->est_absent): ?>
                        <span class="text-amber-600 font-medium text-xs">ABS</span>
                    <?php elseif ($note->valeur !== null): ?>
                        <div class="font-bold text-slate-900 font-mono"><?= number_format((float)$note->valeur, 2) ?></div>
                        <?php if ($pct !== null): ?>
                        <div class="w-16 h-1.5 bg-slate-100 rounded-full mx-auto mt-1"><div class="h-1.5 rounded-full <?= $pct >= 50 ? 'bg-emerald-500' : 'bg-red-400' ?>" style="width:<?= $pct ?>%"></div></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center"><?php if ((int)$note->est_absent): ?><span class="inline-flex items-center gap-1 text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">Absent</span><?php else: ?><span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">Présent</span><?php endif; ?></td>
                <td class="px-4 py-3 text-center"><span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-<?= ($colors[$note->statut] ?? 'slate') ?>-100 text-<?= ($colors[$note->statut] ?? 'slate') ?>-700"><?= $statuts[$note->statut] ?? $note->statut ?></span></td>
                <td class="px-4 py-3 text-right">
                    <a href="<?= BASE_URL ?>/v2/academique/notes/<?= $note->id ?>" class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded transition" title="Voir détail"><i data-lucide="eye" class="w-4 h-4"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pagination['last_page'] > 1): ?>
    <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-500">
        <span><?= $pagination['total'] ?> note(s)</span>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="px-3 py-1 rounded <?= $i === $pagination['current_page'] ? 'bg-violet-600 text-white' : 'hover:bg-slate-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
