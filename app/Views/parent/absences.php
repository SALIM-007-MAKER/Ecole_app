<?php
$enfants  = $enfants  ?? [];
$enfant   = $enfant   ?? null;
$absences = $absences ?? [];
$stats    = $stats    ?? [];
$mois     = $mois     ?? '';

function pAbsTypeBadge(string $t): string {
    return match($t) {
        'absence' => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">Absence</span>',
        'retard'  => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">Retard</span>',
        default   => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">'.htmlspecialchars($t,ENT_QUOTES).'</span>',
    };
}
function pAbsJustBadge(bool $j): string {
    return $j
        ? '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700">Justifiée</span>'
        : '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">Non justifiée</span>';
}
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
            <i data-lucide="calendar-x" class="w-5 h-5 text-red-500"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Absences</h2>
            <?php if ($enfant): ?>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($enfant->prenom . ' ' . $enfant->nom, ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/parent/dashboard" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Tableau de bord
    </a>
</div>

<!-- Selectors -->
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
            <input type="month" name="mois" value="<?= htmlspecialchars($mois, ENT_QUOTES) ?>"
                   class="form-input text-sm w-auto" onchange="this.form.submit()">
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
    <p class="text-sm text-slate-400 mt-1">Choisissez un enfant pour consulter ses absences.</p>
</div>
<?php else: ?>

<!-- Stats KPI -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <?php
    $kpis = [
        ['val'=>(int)($stats->total ?? 0),     'label'=>'Total',         'icon'=>'calendar-x',    'bg'=>'bg-slate-100',    'ic'=>'text-slate-500'],
        ['val'=>(int)($stats->absences ?? 0),   'label'=>'Absences',      'icon'=>'user-x',        'bg'=>'bg-red-100',      'ic'=>'text-red-500'],
        ['val'=>(int)($stats->retards ?? 0),    'label'=>'Retards',       'icon'=>'clock',         'bg'=>'bg-amber-100',    'ic'=>'text-amber-600'],
        ['val'=>(int)($stats->justifiees ?? 0), 'label'=>'Justifiées',    'icon'=>'check-circle',  'bg'=>'bg-emerald-100',  'ic'=>'text-emerald-600'],
    ];
    foreach ($kpis as $k): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-5 p-4 text-center">
            <div class="w-10 h-10 rounded-xl <?= $k['bg'] ?> flex items-center justify-center mx-auto mb-3">
                <i data-lucide="<?= $k['icon'] ?>" class="w-5 h-5 <?= $k['ic'] ?>"></i>
            </div>
            <p class="text-2xl font-black text-slate-900"><?= $k['val'] ?></p>
            <p class="text-xs text-slate-400 mt-0.5"><?= $k['label'] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <div class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center">
            <i data-lucide="list" class="w-3.5 h-3.5 text-red-500"></i>
        </div>
        <span class="font-semibold text-slate-700">Historique des absences</span>
        <?php if (!empty($absences)): ?>
        <span class="ml-auto inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= count($absences) ?> entrée<?= count($absences) > 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-sm">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Matière</th>
                    <th>Créneau</th>
                    <th>Statut</th>
                    <th>Motif</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($absences)): ?>
            <tr>
                <td colspan="6" class="py-12 text-center">
                    <div class="flex flex-col items-center gap-2">
                        <i data-lucide="check-circle" class="w-8 h-8 text-emerald-300"></i>
                        <p class="text-slate-400 font-medium">Aucune absence sur cette période</p>
                    </div>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($absences as $a): ?>
            <tr>
                <td class="font-semibold text-slate-800"><?= date('d/m/Y', strtotime($a->date_absence)) ?></td>
                <td><?= pAbsTypeBadge($a->type ?? 'absence') ?></td>
                <td class="text-slate-600"><?= htmlspecialchars($a->matiere_nom ?? '-', ENT_QUOTES) ?></td>
                <td class="text-xs text-slate-400"><?= htmlspecialchars($a->creneau ?? '-', ENT_QUOTES) ?></td>
                <td><?= pAbsJustBadge((bool)($a->justifiee ?? false)) ?></td>
                <td class="text-xs text-slate-500 italic"><?= htmlspecialchars($a->motif ?? '-', ENT_QUOTES) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
