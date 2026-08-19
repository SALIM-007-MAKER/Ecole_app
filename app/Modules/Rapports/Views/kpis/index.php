<div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="bar-chart-2" class="w-7 h-7 text-violet-600"></i>
                Indicateurs clés de performance
            </h1>
            <p class="text-slate-500 text-sm mt-1">Domaine : <strong><?= htmlspecialchars($domaine) ?></strong></p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php foreach (['scolarite','academique','finance','vie_scolaire','rh','bibliotheque','inventaire'] as $d): ?>
            <a href="?domaine=<?= $d ?>"
               class="px-3 py-1.5 text-xs rounded-full border <?= $d === $domaine ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-slate-600 border-slate-200 hover:border-violet-400' ?>">
                <?= str_replace('_', ' ', ucfirst($d)) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Alertes -->
    <?php if (!empty($alertes)): ?>
    <div class="mb-6">
        <?php foreach ($alertes as $a): ?>
        <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-500 flex-shrink-0"></i>
            <span class="text-sm text-amber-800">
                <strong><?= htmlspecialchars(str_replace('_', ' ', ucfirst($a['metrique'] ?? ''))) ?></strong>
                : <?= htmlspecialchars((string)($a['valeur'] ?? '')) ?> — seuil : <?= htmlspecialchars((string)($a['seuil'] ?? '')) ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">
        <?php foreach ($kpis as $kpi): ?>
        <?php
        $tendance  = $kpi['tendance']  ?? 'stable';
        $variation = $kpi['variation'] ?? null;
        $icon = match ($tendance) {
            'hausse' => '<i data-lucide="trending-up"   class="w-4 h-4 text-green-500"></i>',
            'baisse' => '<i data-lucide="trending-down" class="w-4 h-4 text-red-500"></i>',
            default  => '<i data-lucide="minus"         class="w-4 h-4 text-slate-400"></i>',
        };
        $varClass = $tendance === 'hausse' ? 'text-green-600' : ($tendance === 'baisse' ? 'text-red-600' : 'text-slate-500');
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-slate-500"><?= htmlspecialchars($kpi['label'] ?? $kpi['metrique'] ?? '') ?></span>
                <?= $icon ?>
            </div>
            <div class="flex items-end gap-2">
                <span class="text-2xl font-bold text-slate-800"><?= htmlspecialchars((string)($kpi['valeur'] ?? 0)) ?></span>
                <span class="text-sm text-slate-400 mb-0.5"><?= htmlspecialchars($kpi['unite'] ?? '') ?></span>
            </div>
            <?php if ($variation !== null): ?>
            <div class="flex items-center gap-1 text-xs <?= $varClass ?> mt-1">
                <?= $icon ?>
                <span><?= $variation > 0 ? '+' : '' ?><?= number_format((float)$variation, 1) ?>%</span>
            </div>
            <?php endif; ?>
            <a href="tendance?domaine=<?= urlencode($domaine) ?>&metrique=<?= urlencode($kpi['metrique'] ?? '') ?>"
               class="text-xs text-violet-600 hover:underline mt-2 block">Voir tendance →</a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="flex justify-end">
        <form method="post" action="snapshot">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <button class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Capturer snapshots
            </button>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
