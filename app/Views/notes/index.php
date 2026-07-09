<?php
$perms = (\Core\Session::getUser())['permissions'] ?? [];
function nperm(array $p, string $k): bool { return in_array($k, $p, true); }
$stats   = $stats   ?? ['nb_notes'=>0,'nb_controles'=>0,'nb_bulletins'=>0,'moy_generale'=>null];
$periodes = $periodes ?? [];
$classes  = $classes  ?? [];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
            <i data-lucide="book-open-check" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Notes & Évaluations</h2>
            <p class="text-sm text-slate-500">Contrôles, saisie des notes, bulletins et classements</p>
        </div>
    </div>
    <?php if (nperm($perms, 'notes.create')): ?>
    <a href="<?= BASE_URL ?>/notes/controles/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouveau contrôle
    </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">
            <i data-lucide="hash" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= number_format($stats['nb_notes']) ?></div>
            <div class="stat-label">Notes saisies</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="clipboard-list" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= number_format($stats['nb_controles']) ?></div>
            <div class="stat-label">Contrôles</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="file-text" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= number_format($stats['nb_bulletins']) ?></div>
            <div class="stat-label">Bulletins</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value">
                <?= $stats['moy_generale'] !== null ? number_format($stats['moy_generale'], 2) . '/20' : '—' ?>
            </div>
            <div class="stat-label">Moyenne globale</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Accès rapides -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
            <span class="font-semibold text-slate-700">Accès rapides</span>
        </div>
        <div class="p-5 p-4 space-y-2">
            <?php if (nperm($perms, 'notes.create')): ?>
            <a href="<?= BASE_URL ?>/notes/controles/create"
               class="flex items-center gap-3 p-3 rounded-lg bg-violet-50 hover:bg-violet-100 transition-colors group">
                <div class="w-9 h-9 rounded-lg bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 shrink-0">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-violet-600"></i>
                </div>
                <span class="text-sm font-semibold text-violet-700">Créer un contrôle</span>
                <i data-lucide="chevron-right" class="w-4 h-4 text-violet-400 ml-auto"></i>
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/notes/controles"
               class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors group">
                <div class="w-9 h-9 rounded-lg bg-sky-100 flex items-center justify-center shrink-0">
                    <i data-lucide="list-checks" class="w-4 h-4 text-sky-600"></i>
                </div>
                <span class="text-sm font-semibold text-slate-700">Voir les contrôles</span>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto"></i>
            </a>
            <a href="<?= BASE_URL ?>/notes/moyennes"
               class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors group">
                <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                    <i data-lucide="table-2" class="w-4 h-4 text-indigo-600"></i>
                </div>
                <span class="text-sm font-semibold text-slate-700">Tableau des moyennes</span>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto"></i>
            </a>
            <?php if (nperm($perms, 'bulletins.view')): ?>
            <a href="<?= BASE_URL ?>/bulletins"
               class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors group">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                    <i data-lucide="file-text" class="w-4 h-4 text-emerald-600"></i>
                </div>
                <span class="text-sm font-semibold text-slate-700">Bulletins</span>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Périodes scolaires -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="calendar" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Périodes scolaires</span>
            <?php if (!empty($periodes)): ?>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 ml-auto"><?= count($periodes) ?> période(s)</span>
            <?php endif; ?>
        </div>
        <?php if (empty($periodes)): ?>
        <div class="p-5 flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-12">
            <i data-lucide="calendar-x" class="w-12 h-12 text-slate-200 mx-auto mb-3"></i>
            <p class="font-semibold text-slate-500 mb-1">Aucune période définie</p>
            <p class="text-sm text-slate-400">Configurez les périodes dans les paramètres.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                <thead><tr>
                    <th>Période</th>
                    <th class="text-center">Contrôles</th>
                    <th class="text-center">Notes</th>
                    <th class="text-center">Bulletins</th>
                    <th>Accès classes</th>
                </tr></thead>
                <tbody>
                <?php foreach ($periodes as $p): ?>
                <tr>
                    <td>
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($p->nom, ENT_QUOTES) ?></p>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($p->annee_scolaire, ENT_QUOTES) ?></p>
                    </td>
                    <td class="text-center"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700"><?= $p->nb_controles ?></span></td>
                    <td class="text-center"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700"><?= $p->nb_notes ?></span></td>
                    <td class="text-center"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700"><?= $p->nb_bulletins ?></span></td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($classes as $cl): ?>
                            <a href="<?= BASE_URL ?>/notes/controles?classe_id=<?= $cl->id ?>&periode_id=<?= $p->id ?>"
                               class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors text-xs cursor-pointer">
                                <?= htmlspecialchars($cl->niveau . ' ' . $cl->nom, ENT_QUOTES) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
