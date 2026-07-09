<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tendance — <?= htmlspecialchars($metrique) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/rapports/kpis?domaine=<?= urlencode($domaine) ?>" class="text-slate-400 hover:text-violet-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-xl font-bold text-slate-800">
            Tendance : <span class="text-violet-600"><?= htmlspecialchars(str_replace('_', ' ', $metrique)) ?></span>
        </h1>
        <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded"><?= htmlspecialchars($domaine) ?></span>
    </div>

    <!-- Métadonnées -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-100 p-4 text-center">
            <div class="text-sm text-slate-500">Tendance</div>
            <?php $t = $analyse['tendance'] ?? 'stable'; ?>
            <div class="text-lg font-bold <?= $t === 'hausse' ? 'text-green-600' : ($t === 'baisse' ? 'text-red-600' : 'text-slate-600') ?>">
                <?= ucfirst($t) ?>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 text-center">
            <div class="text-sm text-slate-500">Variation totale</div>
            <?php $v = $analyse['variation'] ?? null; ?>
            <div class="text-lg font-bold <?= $v > 0 ? 'text-green-600' : ($v < 0 ? 'text-red-600' : 'text-slate-600') ?>">
                <?= $v !== null ? (($v > 0 ? '+' : '') . number_format($v, 1) . '%') : 'N/A' ?>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 text-center">
            <div class="text-sm text-slate-500">Périodes analysées</div>
            <div class="text-lg font-bold text-slate-800"><?= count($analyse['labels'] ?? []) ?></div>
        </div>
    </div>

    <!-- Chart -->
    <?php if (!empty($analyse['chart'])): ?>
    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Évolution sur <?= count($analyse['labels'] ?? []) ?> périodes</h3>
        <div style="height:300px; position:relative;">
            <canvas id="chartTendance"></canvas>
        </div>
    </div>
    <script>
    new Chart(document.getElementById('chartTendance'), <?= json_encode($analyse['chart'], JSON_UNESCAPED_UNICODE) ?>);
    </script>
    <?php endif; ?>

    <!-- Tableau valeurs -->
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-700">Historique</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">Période</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Valeur</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Moy. glissante</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($analyse['labels'] ?? []) as $i => $label): ?>
                <tr class="border-t border-slate-50 hover:bg-slate-50">
                    <td class="px-4 py-2 text-slate-600"><?= htmlspecialchars($label) ?></td>
                    <td class="px-4 py-2 text-right font-medium text-slate-800"><?= number_format((float)($analyse['values'][$i] ?? 0), 2) ?></td>
                    <td class="px-4 py-2 text-right text-violet-600"><?= number_format((float)($analyse['glissante'][$i] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
