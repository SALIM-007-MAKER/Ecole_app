<?php
$currentUser = \Core\Session::getUser();
$stats   = $stats   ?? [];
$alertes = $alertes ?? [];
$topAbs  = $topAbs  ?? [];
$tendance= $tendance?? [];

$totalAbs  = (int)($stats['total_absences'] ?? 0);
$nonJust   = (int)($stats['non_justifiees'] ?? 0);
$enAttente = (int)($stats['en_attente']     ?? 0);
$aujHui    = (int)($stats['aujourd_hui']    ?? 0);
$semaine   = (int)($stats['cette_semaine']  ?? 0);

function absNiveauBadge(int $nb): string {
    if ($nb >= 10) return 'bg-red-100 text-red-700';
    if ($nb >= 5)  return 'bg-amber-100 text-amber-800';
    return 'bg-sky-100 text-sky-700';
}
?>

<!-- â”€â”€ Page header â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="page-icon"
             style="background:#fee2e2">
            <i data-lucide="calendar-x" class="w-5 h-5" style="color:#dc2626"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900" style="letter-spacing:-.03em">
                Absences &mdash; Tableau de bord
            </h2>
            <p class="text-sm text-slate-400"><?= date('d/m/Y') ?> &middot; Vue d&rsquo;ensemble</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (in_array('absences.create', $currentUser['permissions'], true)): ?>
        <a href="<?= BASE_URL ?>/absences/pointage" class="btn btn-primary">
            <i data-lucide="check-square" class="w-4 h-4"></i>Pointage du jour
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/absences/liste" class="btn btn-secondary">
            <i data-lucide="list" class="w-4 h-4"></i>Toutes les absences
        </a>
        <?php if (in_array('absences.view', $currentUser['permissions'], true)): ?>
        <a href="<?= BASE_URL ?>/absences/alertes" class="btn btn-outline-danger">
            <i data-lucide="bell" class="w-4 h-4"></i>Alertes
            <?php if (count($alertes) > 0): ?>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold"
                  style="background:#ef4444;color:#fff;margin-left:.125rem">
                <?= count($alertes) ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- â”€â”€ Stat cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dbeafe;color:#2563eb">
            <i data-lucide="calendar-days" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#2563eb"><?= $aujHui ?></div>
            <div class="stat-label">Aujourd&rsquo;hui</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef9c3;color:#ca8a04">
            <i data-lucide="calendar-range" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#ca8a04"><?= $semaine ?></div>
            <div class="stat-label">Cette semaine</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fee2e2;color:#ef4444">
            <i data-lucide="x-circle" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#dc2626"><?= $nonJust ?></div>
            <div class="stat-label">Non justifi&eacute;es</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ffedd5;color:#ea580c">
            <i data-lucide="hourglass" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#ea580c"><?= $enAttente ?></div>
            <div class="stat-label">En attente</div>
        </div>
    </div>

</div>

<!-- â”€â”€ Main content row â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

    <!-- Alertes card -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900" style="justify-content:space-between">
            <div class="flex items-center gap-2">
                <i data-lucide="bell-ring" class="w-4 h-4" style="color:#ef4444"></i>
                <span class="font-semibold text-slate-700">Alertes &mdash; &ge;&nbsp;3 abs.&nbsp;non justifi&eacute;es</span>
            </div>
            <a href="<?= BASE_URL ?>/absences/alertes"
               class="text-xs font-medium" style="color:#7c3aed">Voir toutes &rarr;</a>
        </div>
        <div class="p-5" style="padding:0">
            <?php if (empty($alertes)): ?>
            <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-12">
                <i data-lucide="shield-check" class="w-12 h-12" style="color:#34d399"></i>
                <p class="text-sm font-medium text-slate-500">Aucune alerte active</p>
                <p class="text-xs text-slate-400">Tous les &eacute;l&egrave;ves sont en r&egrave;gle.</p>
            </div>
            <?php else: ?>
            <ul style="divide-y:divide-slate-100">
                <?php foreach (array_slice($alertes, 0, 8) as $al): ?>
                <li class="flex items-center justify-between px-4 py-3"
                    style="border-bottom:1px solid #f1f1f7">
                    <div>
                        <p class="text-sm font-semibold text-slate-800 leading-tight">
                            <?= htmlspecialchars($al->eleve_nom, ENT_QUOTES) ?>
                        </p>
                        <p class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                            <i data-lucide="building-2" class="w-3 h-3"></i>
                            <?= htmlspecialchars($al->classe_niveau . ' ' . $al->classe_nom, ENT_QUOTES) ?>
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>/absences/liste?eleve_id=<?= $al->id ?>"
                       class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= absNiveauBadge((int)$al->nb_non_justifiees) ?>">
                        <?= $al->nb_non_justifiees ?> abs. NJ
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right column: Top absents + tendance -->
    <div class="space-y-4">

        <!-- Top absences -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="trophy" class="w-4 h-4" style="color:#f59e0b"></i>
                <span class="font-semibold text-slate-700">Top absences</span>
            </div>
            <div class="p-5" style="padding:0">
                <?php if (empty($topAbs)): ?>
                <p class="text-sm text-slate-400 text-center py-6">Aucune donn&eacute;e disponible</p>
                <?php else: ?>
                <ul>
                    <?php foreach ($topAbs as $i => $ab): ?>
                    <li class="flex items-center justify-between px-4 py-2.5"
                        style="border-bottom:1px solid #f1f1f7">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center shrink-0"
                                  style="background:#f1f1f7;color:#52526a">
                                <?= $i + 1 ?>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-800 leading-tight">
                                    <?= htmlspecialchars($ab->eleve_nom, ENT_QUOTES) ?>
                                </p>
                                <p class="text-xs text-slate-400">
                                    <?= htmlspecialchars($ab->classe_niveau . ' ' . $ab->classe_nom, ENT_QUOTES) ?>
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700"><?= $ab->nb_absences ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tendance hebdomadaire -->
        <?php if (!empty($tendance)): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="trending-up" class="w-4 h-4" style="color:#0ea5e9"></i>
                <span class="font-semibold text-slate-700">Tendance hebdomadaire</span>
            </div>
            <div class="p-5 space-y-2.5">
                <?php
                $maxNb = max(array_column((array)$tendance, 'nb')) ?: 1;
                foreach ($tendance as $t):
                    $pct   = round((int)$t->nb / $maxNb * 100);
                    $debut = date('d/m', strtotime($t->debut_semaine));
                ?>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 w-14 shrink-0">S.&nbsp;<?= $debut ?></span>
                    <div class="flex-1 rounded-full overflow-hidden" style="background:#f1f1f7;height:10px">
                        <div class="h-full rounded-full" style="width:<?= max(3, $pct) ?>%;background:#7c3aed"></div>
                    </div>
                    <span class="text-xs font-semibold text-slate-600 w-8 text-right"><?= $t->nb ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- â”€â”€ Actions rapides â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

    <?php if (in_array('absences.create', $currentUser['permissions'], true)): ?>
    <a href="<?= BASE_URL ?>/absences/pointage" class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md p-5 text-center flex flex-col items-center gap-2"
       style="text-decoration:none;cursor:pointer">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
             style="background:#ede9fe">
            <i data-lucide="check-square" class="w-6 h-6" style="color:#7c3aed"></i>
        </div>
        <div>
            <p class="text-sm font-semibold text-slate-800">Pointage</p>
            <p class="text-xs text-slate-400">Saisir les pr&eacute;sences</p>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/absences/create" class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md p-5 text-center flex flex-col items-center gap-2"
       style="text-decoration:none;cursor:pointer">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
             style="background:#dcfce7">
            <i data-lucide="plus-circle" class="w-6 h-6" style="color:#059669"></i>
        </div>
        <div>
            <p class="text-sm font-semibold text-slate-800">Absence manuelle</p>
            <p class="text-xs text-slate-400">Saisir une absence</p>
        </div>
    </a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/absences/stats" class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md p-5 text-center flex flex-col items-center gap-2"
       style="text-decoration:none;cursor:pointer">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
             style="background:#e0f2fe">
            <i data-lucide="bar-chart-2" class="w-6 h-6" style="color:#0284c7"></i>
        </div>
        <div>
            <p class="text-sm font-semibold text-slate-800">Statistiques</p>
            <p class="text-xs text-slate-400">Rapports d&eacute;taill&eacute;s</p>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/absences/alertes" class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md p-5 text-center flex flex-col items-center gap-2"
       style="text-decoration:none;cursor:pointer">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
             style="background:#fee2e2">
            <i data-lucide="bell" class="w-6 h-6" style="color:#dc2626"></i>
        </div>
        <div>
            <p class="text-sm font-semibold text-slate-800">Alertes</p>
            <p class="text-xs text-slate-400">&Eacute;l&egrave;ves &agrave; risque</p>
        </div>
    </a>

</div>
