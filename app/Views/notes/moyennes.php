<?php
$filters  = $filters  ?? [];
$notes    = $notes    ?? [];
$matieres = $matieres ?? [];

$grille     = [];
$eleveInfos = [];
$matList    = [];

foreach ($matieres as $m) {
    $matList[$m->id] = $m;
}
foreach ($notes as $n) {
    if (!isset($eleveInfos[$n->eleve_id])) {
        $eleveInfos[$n->eleve_id] = [
            'nom'    => $n->nom,
            'prenom' => $n->prenom,
            'moy_gen'=> $n->moyenne_generale,
            'rang'   => $n->rang,
            'mention'=> $n->mention,
        ];
    }
    $grille[$n->eleve_id][$n->matiere_id] = $n->moyenne;
}
uasort($eleveInfos, function($a, $b) {
    if ($a['rang'] === null && $b['rang'] === null) return 0;
    if ($a['rang'] === null) return 1;
    if ($b['rang'] === null) return -1;
    return $a['rang'] <=> $b['rang'];
});

function mentionBadge(?string $m): string {
    return match($m) {
        'Très Bien'  => 'bg-emerald-100 text-emerald-700',
        'Bien'       => 'bg-violet-100 text-violet-700',
        'Assez Bien' => 'bg-sky-100 text-sky-700',
        'Passable'   => 'bg-amber-100 text-amber-800',
        default      => 'bg-red-100 text-red-700',
    };
}
function noteColorClass(?float $n): string {
    if ($n === null) return 'text-slate-300';
    if ($n >= 16) return 'text-emerald-600 font-bold';
    if ($n >= 12) return 'text-violet-600';
    if ($n >= 10) return 'text-amber-500';
    return 'text-red-500';
}
function noteBgClass(?float $n): string {
    if ($n === null) return '';
    if ($n >= 14) return 'bg-emerald-50';
    if ($n >= 10) return 'bg-amber-50';
    return 'bg-red-50';
}
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
            <i data-lucide="table-2" class="w-5 h-5 text-indigo-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Tableau des moyennes</h2>
            <?php if ($classe && $periode): ?>
            <p class="text-sm text-slate-500">
                <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
                &mdash; <?= htmlspecialchars($periode->nom . ' ' . $periode->annee_scolaire, ENT_QUOTES) ?>
            </p>
            <?php else: ?>
            <p class="text-sm text-slate-500">Sélectionnez une classe et une période</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($classe && $periode && !empty($eleveInfos)): ?>
    <a href="<?= BASE_URL ?>/bulletins/classement?classe_id=<?= $classe->id ?>&periode_id=<?= $periode->id ?>"
       class="btn btn-success">
        <i data-lucide="trophy" class="w-4 h-4"></i>Voir le classement
    </a>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" action="<?= BASE_URL ?>/notes/moyennes"
              class="flex flex-wrap items-end gap-3">
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
                <a href="<?= BASE_URL ?>/notes/moyennes" class="btn btn-secondary p-2 aspect-square" title="Réinitialiser">
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
    <p class="text-sm text-slate-400">Sélectionnez une classe et une période pour afficher les moyennes.</p>
</div>
<?php elseif (empty($matList)): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <span>Aucun contrôle n'a encore été créé pour cette classe et cette période.</span>
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
    <span class="ml-auto flex flex-wrap gap-1.5">
        <?php foreach (\App\Models\NoteModel::MENTIONS as $m): ?>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= mentionBadge($m['mention'] ?? null) ?> text-xs">
            <?= $m['label'] ?> (≥<?= $m['seuil'] ?>)
        </span>
        <?php endforeach; ?>
    </span>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="users" class="w-4 h-4 text-indigo-600"></i>
        <span class="font-semibold text-slate-700"><?= count($eleveInfos) ?> élève(s) — <?= count($matList) ?> matière(s)</span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-xs border-collapse" style="min-width:800px">
            <thead>
                <tr class="bg-slate-800 text-white">
                    <th class="text-center px-3 py-3 w-14">Rang</th>
                    <th class="px-3 py-3" style="min-width:160px">Élève</th>
                    <?php foreach ($matList as $m): ?>
                    <th class="text-center px-2 py-3" style="min-width:80px">
                        <span class="block truncate max-w-[80px]" title="<?= htmlspecialchars($m->nom, ENT_QUOTES) ?>">
                            <?= htmlspecialchars($m->nom, ENT_QUOTES) ?>
                        </span>
                        <span class="text-slate-400 text-xs font-normal">coef. <?= $m->coefficient ?></span>
                    </th>
                    <?php endforeach; ?>
                    <th class="text-center px-3 py-3 w-28">Moy. Gén.</th>
                    <th class="text-center px-3 py-3 w-28">Mention</th>
                    <th class="text-right px-3 py-3 w-20">Bulletin</th>
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
                    <span class="text-slate-400 font-semibold"><?= $rang ?></span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="font-semibold text-slate-800 px-3 py-2.5">
                    <?= htmlspecialchars($info['prenom'] . ' ' . $info['nom'], ENT_QUOTES) ?>
                </td>
                <?php foreach ($matList as $matId => $m): ?>
                <?php $moy = $grille[$eleveId][$matId] ?? null; ?>
                <td class="text-center px-2 py-2.5 <?= noteColorClass($moy) ?> <?= noteBgClass($moy) ?>">
                    <?= $moy !== null ? number_format($moy, 2) : '—' ?>
                </td>
                <?php endforeach; ?>
                <td class="text-center px-3 py-2.5 font-bold <?= noteColorClass($info['moy_gen'] !== null ? (float)$info['moy_gen'] : null) ?> <?= noteBgClass($info['moy_gen'] !== null ? (float)$info['moy_gen'] : null) ?>">
                    <?= $info['moy_gen'] !== null ? number_format($info['moy_gen'], 2) : '—' ?>
                </td>
                <td class="text-center px-3 py-2.5">
                    <?php if ($info['mention']): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= mentionBadge($info['mention']) ?> text-xs">
                        <?= htmlspecialchars($info['mention'], ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-right px-3 py-2.5">
                    <a href="<?= BASE_URL ?>/bulletins/<?= $eleveId ?>?classe_id=<?= $classe->id ?>&periode_id=<?= $periode->id ?>"
                       class="btn btn-ghost btn-icon text-emerald-500" title="Voir le bulletin">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
