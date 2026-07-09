<?php
$classes     = $classes     ?? [];
$statsGlobal = $statsGlobal ?? [];
$statClasses = $statClasses ?? [];
$tendance    = $tendance    ?? [];
$topAbs      = $topAbs      ?? [];

$totalAbs  = (int)($statsGlobal['total_absences'] ?? 0);
$totalRet  = (int)($statsGlobal['total_retards']  ?? 0);
$nonJust   = (int)($statsGlobal['non_justifiees'] ?? 0);
$justifiees= (int)($statsGlobal['justifiees']     ?? 0);
$tauxJust  = $totalAbs > 0 ? round($justifiees / $totalAbs * 100) : 0;
?>

<!-- â”€â”€ Page header â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="page-icon"
             style="background:#e0f2fe">
            <i data-lucide="bar-chart-2" class="w-5 h-5" style="color:#0284c7"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900" style="letter-spacing:-.03em">
                Statistiques des absences
            </h2>
            <p class="text-sm text-slate-400">Vue analytique globale</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/absences/alertes"
           class="btn btn-outline-danger">
            <i data-lucide="bell" class="w-4 h-4"></i>Alertes
        </a>
        <a href="<?= BASE_URL ?>/absences" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Dashboard
        </a>
    </div>
</div>

<!-- â”€â”€ Stat cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fee2e2;color:#ef4444">
            <i data-lucide="calendar-x" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#dc2626"><?= $totalAbs ?></div>
            <div class="stat-label">Absences totales</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef9c3;color:#ca8a04">
            <i data-lucide="clock" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#d97706"><?= $totalRet ?></div>
            <div class="stat-label">Retards</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fee2e2;color:#dc2626">
            <i data-lucide="x-circle" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#dc2626"><?= $nonJust ?></div>
            <div class="stat-label">Non justifi&eacute;es</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="check-circle" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#059669"><?= $tauxJust ?>%</div>
            <div class="stat-label">Taux de justification</div>
        </div>
    </div>

</div>

<!-- â”€â”€ Contenu principal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    <!-- Absences par classe -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="building-2" class="w-4 h-4" style="color:#7c3aed"></i>
            <span class="font-semibold text-slate-700">Absences par classe</span>
        </div>
        <div class="p-5">
            <?php if (empty($statClasses)): ?>
            <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-8">
                <i data-lucide="bar-chart-2" class="w-10 h-10" style="color:#e4e4ec"></i>
                <p class="text-sm text-slate-400">Aucune donn&eacute;e disponible</p>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php
                $maxAbs = max(array_column((array)$statClasses, 'nb_absences')) ?: 1;
                foreach ($statClasses as $sc):
                    $nb  = (int)$sc->nb_absences;
                    $nj  = (int)$sc->nb_non_justifiees;
                    $pct = round($nb / $maxAbs * 100);
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm font-semibold text-slate-700">
                            <?= htmlspecialchars($sc->niveau . ' ' . $sc->nom, ENT_QUOTES) ?>
                        </span>
                        <div class="flex items-center gap-2">
                            <?php if ($nj > 0): ?>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700"><?= $nj ?>&nbsp;NJ</span>
                            <?php endif; ?>
                            <span class="text-sm font-bold text-slate-600"><?= $nb ?></span>
                        </div>
                    </div>
                    <div class="rounded-full overflow-hidden" style="height:10px;background:#f1f1f7">
                        <div class="h-full rounded-full" style="width:<?= max(3, $pct) ?>%;background:#7c3aed;transition:width .4s ease"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        <?= $sc->nb_eleves_concernes ?> &eacute;l&egrave;ve(s) concern&eacute;(s)
                        &middot; <?= $sc->nb_retards ?? 0 ?> retard(s)
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonne droite -->
    <div class="space-y-5">

        <!-- Tendance 6 semaines -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="trending-up" class="w-4 h-4" style="color:#0ea5e9"></i>
                <span class="font-semibold text-slate-700">Tendance sur 6 semaines</span>
            </div>
            <div class="p-5">
                <?php if (empty($tendance)): ?>
                <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-6">
                    <i data-lucide="trending-up" class="w-8 h-8" style="color:#e4e4ec"></i>
                    <p class="text-sm text-slate-400">Aucune donn&eacute;e</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php
                    $maxT = max(array_column((array)$tendance, 'nb')) ?: 1;
                    foreach ($tendance as $t):
                        $pct   = round((int)$t->nb / $maxT * 100);
                        $debut = date('d/m', strtotime($t->debut_semaine));
                        $fin   = date('d/m', strtotime($t->debut_semaine . ' +6 days'));
                    ?>
                    <div class="flex items-center gap-3">
                        <div class="shrink-0 text-right" style="width:4.5rem">
                            <span class="text-xs font-medium text-slate-500"><?= $debut ?></span><br>
                            <span class="text-slate-400" style="font-size:.65rem">au <?= $fin ?></span>
                        </div>
                        <div class="flex-1 rounded-full overflow-hidden" style="height:10px;background:#f1f1f7">
                            <div class="h-full rounded-full" style="width:<?= max(3, $pct) ?>%;background:#0ea5e9;transition:width .4s ease"></div>
                        </div>
                        <span class="text-sm font-bold text-slate-600" style="width:2rem;text-align:right">
                            <?= $t->nb ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top absents -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="user-x" class="w-4 h-4" style="color:#ef4444"></i>
                <span class="font-semibold text-slate-700">&Eacute;l&egrave;ves les plus absents</span>
            </div>
            <?php if (empty($topAbs)): ?>
            <div class="p-5 text-center py-6">
                <p class="text-sm text-slate-400">Aucune donn&eacute;e disponible</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                    <thead>
                        <tr>
                            <th style="width:2rem">#</th>
                            <th>&#201;l&egrave;ve</th>
                            <th>Classe</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">NJ</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topAbs as $i => $ab): ?>
                    <tr>
                        <td class="text-slate-400 font-medium"><?= $i + 1 ?></td>
                        <td>
                            <p class="font-semibold text-slate-800 leading-tight">
                                <?= htmlspecialchars($ab->eleve_nom, ENT_QUOTES) ?>
                            </p>
                            <p class="mono text-xs text-slate-400">
                                <?= htmlspecialchars($ab->matricule ?? '', ENT_QUOTES) ?>
                            </p>
                        </td>
                        <td class="text-xs text-slate-500">
                            <?= htmlspecialchars($ab->classe_niveau . ' ' . $ab->classe_nom, ENT_QUOTES) ?>
                        </td>
                        <td class="text-center font-bold" style="color:#dc2626">
                            <?= $ab->nb_absences ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$ab->nb_nj > 0): ?>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700"><?= $ab->nb_nj ?></span>
                            <?php else: ?>
                            <span class="text-slate-300">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <a href="<?= BASE_URL ?>/absences/liste?eleve_id=<?= $ab->id ?>"
                               class="btn btn-ghost btn-icon" style="color:#7c3aed"
                               title="Voir les absences">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
