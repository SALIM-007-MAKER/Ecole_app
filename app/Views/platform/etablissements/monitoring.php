<?php
/**
 * @var array $etab
 * @var array $cacheStats {branding:{keys}, rbac:{keys}, dashboard:{keys}, total:{keys}}
 * @var array $queueStats {pending,processing,done,failed,avg_duration_ms}
 * @var array $recentJobs list<{id,type,status,attempts,error,created_at,completed_at}>
 */
$statusBadge = [
    'pending'    => 'badge-secondary',
    'processing' => 'badge-warning',
    'done'       => 'badge-success',
    'failed'     => 'badge-danger',
];
$backUrl = BASE_URL . '/platform/etablissements/' . (int)$etab['id'];
?>

<div class="mb-4">
    <a href="<?= $backUrl ?>" class="text-sm text-slate-500 hover:text-slate-700 inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i><?= htmlspecialchars($etab['nom'], ENT_QUOTES) ?>
    </a>
</div>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="activity" class="w-5 h-5 text-violet-600"></i>
            Cache &amp; Files d'attente
        </h2>
        <p class="text-sm text-slate-500">Supervision de <?= htmlspecialchars($etab['nom'], ENT_QUOTES) ?></p>
    </div>
    <form method="POST" action="<?= $backUrl ?>/monitoring/vider-cache" onsubmit="return confirm('Vider le cache de cet établissement ?');">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <button type="submit" class="btn btn-secondary">
            <i data-lucide="trash-2" class="w-4 h-4"></i>Vider le cache
        </button>
    </form>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-6">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="database-zap" class="w-4 h-4 text-violet-600"></i>Cache — clés actives
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-5">
        <?php foreach (['branding' => 'Branding', 'rbac' => 'RBAC', 'dashboard' => 'Tableaux de bord', 'total' => 'Total'] as $key => $label): ?>
        <div class="text-center">
            <p class="text-2xl font-bold text-slate-900"><?= (int)$cacheStats[$key]['keys'] ?></p>
            <p class="text-xs text-slate-500"><?= $label ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
    <?php foreach (['pending' => ['En attente', 'clock'], 'processing' => ['En cours', 'loader'], 'done' => ['Terminées', 'check-circle'], 'failed' => ['Échouées', 'x-circle']] as $key => [$label, $icon]): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center">
        <i data-lucide="<?= $icon ?>" class="w-4 h-4 text-violet-600 mx-auto mb-1"></i>
        <p class="text-xl font-bold text-slate-900"><?= (int)$queueStats[$key] ?></p>
        <p class="text-xs text-slate-500"><?= $label ?></p>
    </div>
    <?php endforeach; ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center">
        <i data-lucide="timer" class="w-4 h-4 text-violet-600 mx-auto mb-1"></i>
        <p class="text-xl font-bold text-slate-900"><?= $queueStats['avg_duration_ms'] !== null ? (int)round($queueStats['avg_duration_ms']) . ' ms' : '—' ?></p>
        <p class="text-xs text-slate-500">Durée moyenne</p>
    </div>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="list-checks" class="w-4 h-4 text-violet-600"></i>Tâches récentes
    </div>
    <?php if (empty($recentJobs)): ?>
    <div class="p-6 text-sm text-slate-500">Aucune tâche pour le moment.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-left">Type</th>
                    <th class="px-5 py-3 text-left">Statut</th>
                    <th class="px-5 py-3 text-left">Essais</th>
                    <th class="px-5 py-3 text-left">Créée le</th>
                    <th class="px-5 py-3 text-left">Erreur</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($recentJobs as $j): ?>
                <tr>
                    <td class="px-5 py-3 font-mono text-xs text-slate-700"><?= htmlspecialchars(basename(str_replace('\\', '/', $j['type'])), ENT_QUOTES) ?></td>
                    <td class="px-5 py-3"><span class="badge <?= $statusBadge[$j['status']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($j['status'], ENT_QUOTES) ?></span></td>
                    <td class="px-5 py-3 text-slate-600"><?= (int)$j['attempts'] ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars((string)$j['created_at'], ENT_QUOTES) ?></td>
                    <td class="px-5 py-3 text-xs text-red-600"><?= $j['error'] ? htmlspecialchars(substr((string)$j['error'], 0, 80), ENT_QUOTES) : '' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
