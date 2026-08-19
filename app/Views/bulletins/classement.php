<?php
$filters = $filters ?? [];
function clMentionBadge(?string $m): string {
    return match($m) {
        'Très Bien'  => 'bg-emerald-100 text-emerald-700',
        'Bien'       => 'bg-violet-100 text-violet-700',
        'Assez Bien' => 'bg-sky-100 text-sky-700',
        'Passable'   => 'bg-amber-100 text-amber-800',
        default      => 'bg-red-100 text-red-700',
    };
}
function clNoteColor(?float $n): string {
    if ($n === null) return 'text-slate-300';
    if ($n >= 16) return 'text-emerald-600 font-bold';
    if ($n >= 12) return 'text-violet-600';
    if ($n >= 10) return 'text-amber-500';
    return 'text-red-500';
}
$medals    = ['🥇', '🥈', '🥉'];
$medalBg   = ['bg-amber-50 border-amber-200', 'bg-slate-50 border-slate-200', 'bg-orange-50 border-orange-200'];
$medalIcon = ['text-amber-500', 'text-slate-500', 'text-orange-500'];
$medalRing = ['ring-2 ring-amber-300', 'ring-2 ring-slate-300', 'ring-2 ring-orange-300'];
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
            <i data-lucide="trophy" class="w-5 h-5 text-amber-500"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Classement général</h2>
            <?php if ($classe && $periode): ?>
            <p class="text-sm text-slate-500">
                <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
                &mdash; <?= htmlspecialchars($periode->nom . ' ' . ($periode->annee_scolaire ?? ''), ENT_QUOTES) ?>
            </p>
            <?php else: ?>
            <p class="text-sm text-slate-500">Sélectionnez une classe et une période</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if ($classe && $periode): ?>
        <a href="<?= BASE_URL ?>/bulletins/classe?classe_id=<?= $classe->id ?>&periode_id=<?= $periode->id ?>"
           class="btn btn-primary">
            <i data-lucide="table-2" class="w-4 h-4"></i>Résultats détaillés
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/bulletins" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
        </a>
    </div>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" action="<?= BASE_URL ?>/bulletins/classement" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-36">
                <label class="form-label text-xs">Classe</label>
                <select name="classe_id" class="form-input text-sm">
                    <option value="">— Classe —</option>
                    <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl->id ?>"
                        <?= ($filters['classeId'] ?? 0) == $cl->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="form-label text-xs">Période</label>
                <select name="periode_id" class="form-input text-sm">
                    <option value="">— Période —</option>
                    <?php foreach ($periodes as $p): ?>
                    <option value="<?= $p->id ?>"
                        <?= ($filters['periodeId'] ?? 0) == $p->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p->nom . ' — ' . $p->annee_scolaire, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-warning">
                    <i data-lucide="trophy" class="w-4 h-4"></i>Afficher
                </button>
                <a href="<?= BASE_URL ?>/bulletins/classement" class="btn btn-secondary p-2 aspect-square" title="Réinitialiser">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($classement) && $classe && $periode): ?>

<!-- Podium top 3 -->
<?php $top3 = array_slice($classement, 0, 3); ?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    <?php foreach ($top3 as $i => $e): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm border <?= $medalBg[$i] ?? 'bg-white border-slate-100' ?> text-center py-6 px-4 relative overflow-hidden">
        <!-- Position indicator -->
        <div class="absolute top-3 right-3 text-xs font-bold opacity-30 text-slate-600 text-4xl leading-none">
            <?= $i + 1 ?>
        </div>
        <div class="text-4xl mb-3 leading-none"><?= $medals[$i] ?></div>
        <?php if (!empty($e->photo)): ?>
        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($e->photo, ENT_QUOTES) ?>"
             class="w-14 h-14 rounded-full object-cover mx-auto mb-2 <?= $medalRing[$i] ?? '' ?>" alt="">
        <?php else: ?>
        <div class="w-14 h-14 rounded-full bg-white flex items-center justify-center mx-auto mb-2 <?= $medalRing[$i] ?? '' ?>">
            <i data-lucide="user" class="w-7 h-7 text-slate-400"></i>
        </div>
        <?php endif; ?>
        <p class="font-bold text-slate-900 text-base leading-tight">
            <?= htmlspecialchars($e->prenom . ' ' . $e->nom, ENT_QUOTES) ?>
        </p>
        <p class="text-2xl font-bold <?= clNoteColor($e->moyenne_generale !== null ? (float)$e->moyenne_generale : null) ?> mt-2 mb-2">
            <?= $e->moyenne_generale !== null ? number_format($e->moyenne_generale, 2) : '—' ?>
            <span class="text-base text-slate-400 font-normal">/20</span>
        </p>
        <?php if ($e->mention): ?>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= clMentionBadge($e->mention) ?> text-xs">
            <?= htmlspecialchars($e->mention, ENT_QUOTES) ?>
        </span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (count($top3) < 3): ?>
    <?php for ($j = count($top3); $j < 3; $j++): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm border border-dashed border-slate-200 text-center py-6 px-4">
        <div class="text-3xl mb-2 opacity-30"><?= $medals[$j] ?></div>
        <p class="text-sm text-slate-300">—</p>
    </div>
    <?php endfor; ?>
    <?php endif; ?>
</div>

<!-- Tableau complet -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="list-ordered" class="w-4 h-4 text-amber-500"></i>
        <span class="font-semibold text-slate-700">
            Classement complet
        </span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 ml-auto"><?= count($classement) ?> élève(s)</span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead><tr>
                <th class="text-center w-16">Rang</th>
                <th>Élève</th>
                <th class="text-center w-40">Moyenne générale</th>
                <th class="text-center w-32">Mention</th>
                <th class="text-right w-28">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($classement as $i => $e): ?>
            <tr class="hover:bg-slate-50 transition-colors <?= $i < 3 ? ($i === 0 ? 'bg-amber-50/40' : ($i === 1 ? 'bg-slate-50/60' : 'bg-orange-50/40')) : '' ?>">
                <td class="text-center">
                    <?php if ($i === 0): ?>
                    <span class="text-2xl leading-none">🥇</span>
                    <?php elseif ($i === 1): ?>
                    <span class="text-2xl leading-none">🥈</span>
                    <?php elseif ($i === 2): ?>
                    <span class="text-2xl leading-none">🥉</span>
                    <?php else: ?>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 text-slate-500 font-semibold text-xs">
                        <?= $e->rang ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="flex items-center gap-3">
                        <?php if (!empty($e->photo)): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($e->photo, ENT_QUOTES) ?>"
                             class="w-9 h-9 rounded-full object-cover border border-slate-100 shrink-0" alt="">
                        <?php else: ?>
                        <div class="w-9 h-9 rounded-full bg-violet-100 flex items-center justify-center shrink-0">
                            <i data-lucide="user" class="w-4 h-4 text-violet-500"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">
                                <?= htmlspecialchars($e->prenom . ' ' . $e->nom, ENT_QUOTES) ?>
                            </p>
                            <p class="font-mono text-xs text-slate-400">
                                <?= htmlspecialchars($e->matricule ?? '', ENT_QUOTES) ?>
                            </p>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <?php $moy = $e->moyenne_generale !== null ? (float)$e->moyenne_generale : null; ?>
                    <span class="text-lg font-bold <?= clNoteColor($moy) ?>">
                        <?= $moy !== null ? number_format($moy, 2) : '—' ?>
                    </span>
                    <?php if ($moy !== null): ?>
                    <span class="text-slate-400 text-sm">/20</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($e->mention): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= clMentionBadge($e->mention) ?>">
                        <?= htmlspecialchars($e->mention, ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/bulletins/<?= $e->id ?>?classe_id=<?= $classe->id ?>&periode_id=<?= $periode->id ?>"
                           class="btn btn-ghost btn-icon text-emerald-500" title="Voir le bulletin (choix du bulletin officiel S1/S2 sur cette page)">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($classe && $periode): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <span>Aucun résultat pour cette classe et cette période. Assurez-vous que les notes ont été saisies.</span>
</div>
<?php else: ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-14">
    <i data-lucide="trophy" class="w-14 h-14 text-slate-200 mx-auto mb-3"></i>
    <p class="font-semibold text-slate-500 mb-1">Aucun classement disponible</p>
    <p class="text-sm text-slate-400">Sélectionnez une classe et une période pour afficher le classement.</p>
</div>
<?php endif; ?>
