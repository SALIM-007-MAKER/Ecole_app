<?php
$filters    = $filters    ?? [];
$matieres   = $matieres   ?? [];
$grille     = $grille     ?? [];
$eleveInfos = $eleveInfos ?? [];

function gcMentionBadge(?string $m): string {
    return match($m) {
        'Très Bien'  => 'bg-emerald-100 text-emerald-700',
        'Bien'       => 'bg-violet-100 text-violet-700',
        'Assez Bien' => 'bg-sky-100 text-sky-700',
        'Passable'   => 'bg-amber-100 text-amber-800',
        default      => 'bg-red-100 text-red-700',
    };
}
function gcNoteColor(?float $n): string {
    if ($n === null) return 'text-slate-300';
    if ($n >= 16) return 'text-emerald-600 font-bold';
    if ($n >= 12) return 'text-violet-600';
    if ($n >= 10) return 'text-amber-500';
    return 'text-red-500';
}
function gcNoteBg(?float $n): string {
    if ($n === null) return '';
    if ($n >= 14) return 'bg-emerald-50';
    if ($n >= 10) return 'bg-amber-50';
    return 'bg-red-50';
}
uasort($eleveInfos, function($a, $b) {
    if ($a['rang'] === null && $b['rang'] === null) return 0;
    if ($a['rang'] === null) return 1;
    if ($b['rang'] === null) return -1;
    return $a['rang'] <=> $b['rang'];
});
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
            <i data-lucide="table-2" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Résultats de la classe</h2>
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
        <a href="<?= BASE_URL ?>/bulletins/classement?classe_id=<?= $classe->id ?>&periode_id=<?= $periode->id ?>"
           class="btn btn-warning">
            <i data-lucide="trophy" class="w-4 h-4"></i>Classement
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
        <form method="GET" action="<?= BASE_URL ?>/bulletins/classe" class="flex flex-wrap items-end gap-3">
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
                <button class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>Afficher
                </button>
                <a href="<?= BASE_URL ?>/bulletins/classe" class="btn btn-secondary p-2 aspect-square" title="Réinitialiser">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (!$classe || !$periode): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-14">
    <i data-lucide="table-2" class="w-14 h-14 text-slate-200 mx-auto mb-3"></i>
    <p class="font-semibold text-slate-500 mb-1">Aucune donnée à afficher</p>
    <p class="text-sm text-slate-400">Sélectionnez une classe et une période pour afficher les résultats.</p>
</div>
<?php elseif (empty($matieres)): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <span>Aucun contrôle créé pour cette classe et période. Créez d'abord des contrôles et saisissez les notes.</span>
</div>
<?php else: ?>

<!-- Légende niveaux de notes -->
<div class="flex flex-wrap items-center gap-3 mb-3">
    <span class="text-xs text-slate-500 font-medium">Légende :</span>
    <span class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
        <span class="inline-block w-3 h-3 rounded-sm bg-emerald-100 border border-emerald-300"></span>≥ 14 — Bien
    </span>
    <span class="flex items-center gap-1.5 text-xs font-semibold text-amber-500">
        <span class="inline-block w-3 h-3 rounded-sm bg-amber-100 border border-amber-300"></span>10–13 — Passable
    </span>
    <span class="flex items-center gap-1.5 text-xs font-semibold text-red-500">
        <span class="inline-block w-3 h-3 rounded-sm bg-red-100 border border-red-300"></span>&lt; 10 — Insuffisant
    </span>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="users" class="w-4 h-4 text-violet-600"></i>
        <span class="font-semibold text-slate-700">
            <?= count($eleveInfos) ?> élève(s) &mdash; <?= count($matieres) ?> matière(s)
        </span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 border-collapse text-xs" style="min-width:900px;font-size:.82rem">
            <thead>
                <tr class="bg-slate-800 text-white">
                    <th class="text-center px-3 py-3 w-14">Rang</th>
                    <th class="px-3 py-3" style="min-width:140px">Élève</th>
                    <?php foreach ($matieres as $m): ?>
                    <th class="text-center px-2 py-3" style="min-width:68px">
                        <span class="block truncate max-w-[64px]"
                              title="<?= htmlspecialchars($m->nom, ENT_QUOTES) ?>">
                            <?= htmlspecialchars(mb_substr($m->nom, 0, 6), ENT_QUOTES) ?>.
                        </span>
                        <span class="text-slate-400 text-xs font-normal">c.<?= $m->coefficient ?></span>
                    </th>
                    <?php endforeach; ?>
                    <th class="text-center px-3 py-3 w-24">Moy. Gén.</th>
                    <th class="text-center px-3 py-3 w-28">Mention</th>
                    <th class="text-right px-3 py-3 w-24">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($eleveInfos as $eleveId => $info): ?>
            <?php $rang = $info['rang']; ?>
            <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100">
                <td class="text-center px-3 py-2.5">
                    <?php if ($rang === 1): ?>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-600 font-bold text-xs">1</span>
                    <?php elseif ($rang === 2): ?>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 text-slate-600 font-bold text-xs">2</span>
                    <?php elseif ($rang === 3): ?>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-orange-100 text-orange-600 font-bold text-xs">3</span>
                    <?php elseif ($rang !== null): ?>
                    <span class="text-slate-500 font-semibold"><?= $rang ?></span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="font-semibold text-slate-800 px-3 py-2.5">
                    <?= htmlspecialchars($info['prenom'] . ' ' . $info['nom'], ENT_QUOTES) ?>
                </td>
                <?php foreach ($matieres as $m): ?>
                <?php $moy = $grille[$eleveId][$m->id] ?? null; ?>
                <td class="text-center px-2 py-2.5 <?= gcNoteColor($moy) ?> <?= gcNoteBg($moy) ?>">
                    <?= $moy !== null ? number_format($moy, 2) : '—' ?>
                </td>
                <?php endforeach; ?>
                <td class="text-center px-3 py-2.5 font-bold <?= gcNoteColor($info['moyenne_generale'] !== null ? (float)$info['moyenne_generale'] : null) ?> <?= gcNoteBg($info['moyenne_generale'] !== null ? (float)$info['moyenne_generale'] : null) ?>">
                    <?= $info['moyenne_generale'] !== null ? number_format($info['moyenne_generale'], 2) : '—' ?>
                </td>
                <td class="text-center px-3 py-2.5">
                    <?php if ($info['mention']): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= gcMentionBadge($info['mention']) ?> text-xs">
                        <?= htmlspecialchars($info['mention'], ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-right px-3 py-2.5">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/bulletins/<?= $eleveId ?>?classe_id=<?= $classe->id ?>&periode_id=<?= $periode->id ?>"
                           class="btn btn-ghost btn-icon text-emerald-500" title="Voir le bulletin">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/bulletins/print/<?= $eleveId ?>?classe_id=<?= $classe->id ?>&periode_id=<?= $periode->id ?>"
                           target="_blank" class="btn btn-ghost btn-icon text-violet-500" title="Imprimer">
                            <i data-lucide="printer" class="w-4 h-4"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
