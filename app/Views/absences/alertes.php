<?php
$alertes = $alertes ?? [];
$seuil   = $seuil   ?? 3;

function absAlNiveau(int $nb): string {
    if ($nb >= 10) return 'danger';
    if ($nb >= 5)  return 'warning';
    return 'info';
}
function absAlLabel(int $nb): string {
    if ($nb >= 10) return 'Critique';
    if ($nb >= 5)  return 'S&eacute;rieux';
    return '&Agrave; surveiller';
}
function absAlBadge(int $nb): string {
    return match(absAlNiveau($nb)) {
        'danger'  => 'bg-red-100 text-red-700',
        'warning' => 'bg-amber-100 text-amber-800',
        default   => 'bg-sky-100 text-sky-700',
    };
}
function absAlIcon(int $nb): string {
    return match(absAlNiveau($nb)) {
        'danger'  => 'octagon-alert',
        'warning' => 'triangle-alert',
        default   => 'info',
    };
}
// Left border color by level
function absAlBorderColor(int $nb): string {
    return match(absAlNiveau($nb)) {
        'danger'  => '#dc2626',
        'warning' => '#d97706',
        default   => '#2563eb',
    };
}
function absAlRowBg(int $nb): string {
    return match(absAlNiveau($nb)) {
        'danger'  => '#fff5f5',
        'warning' => '#fffbeb',
        default   => '#eff6ff',
    };
}
?>

<!-- ── Page header ───────────────────────────────────────────────────────── -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="page-icon"
             style="background:#fee2e2">
            <i data-lucide="bell-ring" class="w-5 h-5" style="color:#dc2626"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900" style="letter-spacing:-.03em">
                Alertes absences
            </h2>
            <p class="text-sm text-slate-400">
                <?= count($alertes) ?> &eacute;l&egrave;ve(s) avec &ge;&nbsp;<?= $seuil ?> absence(s) non justifi&eacute;e(s)
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/absences/stats" class="btn btn-secondary">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i>Statistiques
        </a>
        <a href="<?= BASE_URL ?>/absences" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Dashboard
        </a>
    </div>
</div>

<!-- ── Filtre seuil + Légende ────────────────────────────────────────────── -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">

    <!-- Filtre seuil -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm" style="display:inline-flex;padding:.5rem .75rem;align-items:center;gap:.75rem">
        <i data-lucide="sliders-horizontal" class="w-4 h-4" style="color:#7c3aed;flex-shrink:0"></i>
        <label class="text-sm font-medium text-slate-600 whitespace-nowrap">Seuil d&rsquo;alerte :</label>
        <form method="GET" action="<?= BASE_URL ?>/absences/alertes" style="display:contents">
            <select name="seuil" class="form-input" style="width:auto;padding-top:.375rem;padding-bottom:.375rem"
                    onchange="this.form.submit()">
                <option value="3"  <?= $seuil == 3  ? 'selected' : '' ?>>&ge; 3 abs. NJ (surveillance)</option>
                <option value="5"  <?= $seuil == 5  ? 'selected' : '' ?>>&ge; 5 abs. NJ (s&eacute;rieux)</option>
                <option value="10" <?= $seuil == 10 ? 'selected' : '' ?>>&ge; 10 abs. NJ (critique)</option>
            </select>
        </form>
    </div>

    <!-- Légende niveaux -->
    <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700">
            <i data-lucide="info" class="w-3 h-3"></i>&Agrave; surveiller : 3&ndash;4
        </span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">
            <i data-lucide="triangle-alert" class="w-3 h-3"></i>S&eacute;rieux : 5&ndash;9
        </span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">
            <i data-lucide="octagon-alert" class="w-3 h-3"></i>Critique : &ge;&nbsp;10
        </span>
    </div>

</div>

<?php if (empty($alertes)): ?>
<!-- ── Empty state ───────────────────────────────────────────────────────── -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col items-center justify-center gap-3 text-center text-slate-500" style="padding:5rem 1.5rem">
    <div class="w-16 h-16 rounded-2xl flex items-center justify-center"
         style="background:#dcfce7">
        <i data-lucide="shield-check" class="w-8 h-8" style="color:#059669"></i>
    </div>
    <p class="text-base font-semibold text-slate-700">Aucune alerte active</p>
    <p class="text-sm text-slate-400">
        Tous les &eacute;l&egrave;ves ont moins de <?= $seuil ?> absence(s) non justifi&eacute;e(s).
    </p>
</div>

<?php else: ?>
<!-- ── Cards d'alerte par niveau ─────────────────────────────────────────── -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead>
                <tr>
                    <th style="width:7rem">Niveau</th>
                    <th>&#201;l&egrave;ve</th>
                    <th>Classe</th>
                    <th class="text-center" style="width:6rem">Abs. NJ</th>
                    <th style="width:9rem">Derni&egrave;re abs.</th>
                    <th>Parent / Contact</th>
                    <th class="text-right" style="width:5rem">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alertes as $al): ?>
            <?php $nb = (int)$al->nb_non_justifiees; ?>
            <tr style="background:<?= absAlRowBg($nb) ?>;border-left:3px solid <?= absAlBorderColor($nb) ?>">

                <td>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= absAlBadge($nb) ?>">
                        <i data-lucide="<?= absAlIcon($nb) ?>" class="w-3 h-3"></i>
                        <?= absAlLabel($nb) ?>
                    </span>
                </td>

                <td>
                    <p class="font-semibold text-slate-800 leading-tight">
                        <?= htmlspecialchars($al->eleve_nom, ENT_QUOTES) ?>
                    </p>
                    <p class="mono text-xs text-slate-400">
                        <?= htmlspecialchars($al->matricule ?? '', ENT_QUOTES) ?>
                    </p>
                </td>

                <td class="text-xs text-slate-500">
                    <?= htmlspecialchars($al->classe_niveau . ' ' . $al->classe_nom, ENT_QUOTES) ?>
                </td>

                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= absAlBadge($nb) ?>"
                          style="font-size:.875rem;padding:.25rem .75rem">
                        <?= $nb ?>
                    </span>
                </td>

                <td class="text-xs text-slate-500">
                    <?= $al->derniere_absence
                        ? date('d/m/Y', strtotime($al->derniere_absence))
                        : '&mdash;' ?>
                </td>

                <td>
                    <?php if ($al->parent_nom && trim($al->parent_nom) !== ' '): ?>
                    <p class="text-sm font-semibold text-slate-700 leading-tight">
                        <?= htmlspecialchars(trim($al->parent_nom), ENT_QUOTES) ?>
                    </p>
                    <?php if ($al->parent_telephone): ?>
                    <a href="tel:<?= htmlspecialchars($al->parent_telephone, ENT_QUOTES) ?>"
                       class="text-xs text-slate-400 flex items-center gap-1 mt-0.5 hover:text-violet-600">
                        <i data-lucide="phone" class="w-3 h-3"></i>
                        <?= htmlspecialchars($al->parent_telephone, ENT_QUOTES) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($al->parent_email): ?>
                    <a href="mailto:<?= htmlspecialchars($al->parent_email, ENT_QUOTES) ?>"
                       class="text-xs text-slate-400 flex items-center gap-1 hover:text-violet-600">
                        <i data-lucide="mail" class="w-3 h-3"></i>
                        <?= htmlspecialchars($al->parent_email, ENT_QUOTES) ?>
                    </a>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-slate-300 text-sm">&mdash;</span>
                    <?php endif; ?>
                </td>

                <td class="text-right">
                    <a href="<?= BASE_URL ?>/absences/liste?eleve_id=<?= $al->id ?>"
                       class="btn btn-ghost btn-icon" style="color:#7c3aed"
                       title="Voir les absences">
                        <i data-lucide="list" class="w-4 h-4"></i>
                    </a>
                </td>

            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4" style="justify-content:flex-end">
        <p class="text-xs text-slate-400">
            Total : <strong><?= count($alertes) ?></strong> &eacute;l&egrave;ve(s) en situation d&rsquo;alerte
        </p>
    </div>
</div>
<?php endif; ?>
