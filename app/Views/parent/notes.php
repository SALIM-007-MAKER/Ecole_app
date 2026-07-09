<?php
$enfants   = $enfants   ?? [];
$enfant    = $enfant    ?? null;
$periodes  = $periodes  ?? [];
$periodeId = $periodeId ?? null;
$notes     = $notes     ?? [];
$matieres  = $matieres  ?? [];

function pNoteMoyBadge(float $m): string {
    if ($m >= 16) return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700">Excellent</span>';
    if ($m >= 14) return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700">Très bien</span>';
    if ($m >= 12) return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700">Bien</span>';
    if ($m >= 10) return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">Passable</span>';
    return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">Insuffisant</span>';
}
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
            <i data-lucide="file-text" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Notes</h2>
            <?php if ($enfant): ?>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($enfant->prenom . ' ' . $enfant->nom, ENT_QUOTES) ?> — <?= htmlspecialchars($enfant->classe_nom ?? '', ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/parent/dashboard" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Tableau de bord
    </a>
</div>

<!-- Filters -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="p-5 p-4">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 text-sm text-slate-500 font-medium">
                <i data-lucide="filter" class="w-4 h-4"></i>Filtres :
            </div>
            <select name="enfant_id" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Choisir un enfant</option>
                <?php foreach ($enfants as $e): ?>
                <option value="<?= $e->id ?>" <?= $enfant && $enfant->id == $e->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e->prenom . ' ' . $e->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($enfant): ?>
            <select name="periode_id" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Toutes les périodes</option>
                <?php foreach ($periodes as $p): ?>
                <option value="<?= $p->id ?>" <?= $periodeId == $p->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <a href="<?= BASE_URL ?>/parent/bulletin?enfant_id=<?= $enfant->id ?><?= $periodeId ? '&periode_id='.$periodeId : '' ?>"
               class="btn btn-outline ml-auto">
                <i data-lucide="file-badge" class="w-4 h-4"></i>Bulletin PDF
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!$enfant): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="user" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Sélectionnez un enfant</p>
    <p class="text-sm text-slate-400 mt-1">Choisissez un enfant dans le filtre ci-dessus pour afficher ses notes.</p>
</div>
<?php elseif (empty($notes)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="inbox" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Aucune note disponible</p>
    <p class="text-sm text-slate-400 mt-1">Aucune note enregistrée pour cette période.</p>
</div>
<?php else: ?>
<!-- Group notes by matière -->
<?php
$byMat = [];
foreach ($notes as $n) {
    $mid = $n->matiere_id ?? 0;
    $byMat[$mid]['nom']    = $n->matiere_nom ?? '-';
    $byMat[$mid]['notes'][] = $n;
}
?>
<div class="space-y-4">
    <?php foreach ($byMat as $mat):
        $matNotes  = array_map(fn($n) => (float)$n->note, $mat['notes']);
        $moyMat    = count($matNotes) ? round(array_sum($matNotes) / count($matNotes), 2) : 0;
    ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center">
                <i data-lucide="book-open" class="w-3.5 h-3.5 text-violet-600"></i>
            </div>
            <span class="font-semibold text-slate-700"><?= htmlspecialchars($mat['nom'], ENT_QUOTES) ?></span>
            <div class="ml-auto flex items-center gap-2">
                <span class="text-sm font-bold <?= $moyMat >= 10 ? 'text-emerald-600' : 'text-red-500' ?>">
                    Moy. : <?= number_format($moyMat, 2, ',', '') ?>
                </span>
                <?= pNoteMoyBadge($moyMat) ?>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-sm">
                <thead>
                    <tr>
                        <th>Contrôle</th>
                        <th class="text-center">Note</th>
                        <th class="text-center">Barème</th>
                        <th class="text-center">Coeff.</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mat['notes'] as $n): ?>
                <tr>
                    <td class="text-slate-700 font-medium"><?= htmlspecialchars($n->controle_nom ?? 'Contrôle', ENT_QUOTES) ?></td>
                    <td class="text-center font-bold <?= (float)$n->note >= (float)($n->bareme ?? 20) / 2 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= number_format((float)$n->note, 2, ',', '') ?>
                    </td>
                    <td class="text-center text-slate-400">/ <?= number_format((float)($n->bareme ?? 20), 0, ',', '') ?></td>
                    <td class="text-center text-slate-400"><?= (float)($n->coefficient ?? 1) ?>×</td>
                    <td class="text-slate-400 text-xs"><?= !empty($n->date_controle) ? date('d/m/Y', strtotime($n->date_controle)) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
