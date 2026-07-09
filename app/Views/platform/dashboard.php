<?php
/**
 * @var array $kpis
 * @var array $queueStats
 * @var array $cacheStats
 * @var array $moduleHealth
 * @var array $tenantsAtRisk
 */
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="layout-dashboard" class="w-5 h-5 text-violet-600"></i>Tableau de bord plateforme
        </h2>
        <p class="text-sm text-slate-500">Vue d'ensemble tous établissements</p>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/platform/dashboard/snapshot">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <button type="submit" class="btn btn-secondary"><i data-lucide="camera" class="w-4 h-4"></i>Enregistrer un instantané</button>
    </form>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['label' => 'Établissements', 'value' => $kpis['total_tenants'], 'icon' => 'building-2'],
        ['label' => 'Actifs', 'value' => $kpis['active_tenants'], 'icon' => 'check-circle'],
        ['label' => 'Utilisateurs', 'value' => $kpis['total_users'], 'icon' => 'users'],
        ['label' => 'Élèves', 'value' => $kpis['total_eleves'], 'icon' => 'graduation-cap'],
        ['label' => 'Enseignants', 'value' => $kpis['total_professeurs'], 'icon' => 'user-check'],
        ['label' => 'Stockage (Go)', 'value' => $kpis['storage_used_gb'], 'icon' => 'hard-drive'],
        ['label' => 'En essai', 'value' => $kpis['trial_tenants'], 'icon' => 'hourglass'],
        ['label' => 'Suspendus', 'value' => $kpis['suspended_tenants'], 'icon' => 'pause-circle'],
    ] as $card): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
        <i data-lucide="<?= $card['icon'] ?>" class="w-4 h-4 text-violet-600 mb-2"></i>
        <p class="text-xl font-bold text-slate-900"><?= htmlspecialchars((string)$card['value'], ENT_QUOTES) ?></p>
        <p class="text-xs text-slate-500"><?= $card['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Files & cache -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="activity" class="w-4 h-4 text-violet-600"></i>Files d'attente &amp; cache — tous établissements
        </div>
        <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4 text-center">
            <div><p class="text-lg font-bold text-slate-900"><?= (int)$queueStats['pending'] ?></p><p class="text-xs text-slate-500">En attente</p></div>
            <div><p class="text-lg font-bold text-slate-900"><?= (int)$queueStats['done'] ?></p><p class="text-xs text-slate-500">Terminées</p></div>
            <div><p class="text-lg font-bold text-slate-900"><?= (int)$queueStats['failed'] ?></p><p class="text-xs text-slate-500">Échouées</p></div>
            <div class="col-span-3 sm:col-span-1"><p class="text-lg font-bold text-slate-900"><?= (int)$cacheStats['keys'] ?></p><p class="text-xs text-slate-500">Clés en cache</p></div>
        </div>
    </div>

    <!-- État des modules -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="boxes" class="w-4 h-4 text-violet-600"></i>État des modules
        </div>
        <div class="p-5 grid grid-cols-2 gap-2 text-sm">
            <?php foreach ($moduleHealth as $mod => $enabled): ?>
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full <?= $enabled ? 'bg-emerald-500' : 'bg-slate-300' ?>"></span>
                <span class="text-slate-700"><?= htmlspecialchars($mod, ENT_QUOTES) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Alertes quotas -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i>Établissements proches ou en dépassement de quota
    </div>
    <?php if (empty($tenantsAtRisk)): ?>
    <div class="p-6 text-sm text-slate-500">Aucune alerte — tous les établissements sont dans leurs quotas.</div>
    <?php else: ?>
    <div class="divide-y divide-slate-100">
        <?php foreach ($tenantsAtRisk as $t): ?>
        <div class="px-5 py-3">
            <a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$t['etablissement_id'] ?>" class="text-sm font-semibold text-violet-700 hover:underline"><?= htmlspecialchars($t['nom'], ENT_QUOTES) ?></a>
            <?php foreach ($t['alerts'] as $a): ?>
            <div class="text-xs <?= $a['level'] === 'critical' ? 'text-red-600' : 'text-amber-600' ?> mt-1"><?= htmlspecialchars($a['message'], ENT_QUOTES) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
