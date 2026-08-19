<?php
// Variables attendues : $metrics (DashboardMetricsDTO), $titre, $contexte, $icon
// Usage : require __DIR__ . '/_layout.php'; en haut de chaque dashboard
// Puis en bas : require __DIR__ . '/_layout_end.php';
// On extrait les kpis/charts/tables/alertes du DTO
$kpis    = $metrics->kpis    ?? [];
$charts  = $metrics->charts  ?? [];
$tables  = $metrics->tables  ?? [];
$alertes = $metrics->alertes ?? [];

$navItems = [
    ['href' => '/v2/rapports/dashboard/direction',     'label' => 'Direction',     'icon' => 'building-2'],
    ['href' => '/v2/rapports/dashboard/administration','label' => 'Admin.',        'icon' => 'shield'],
    ['href' => '/v2/rapports/dashboard/scolarite',     'label' => 'Scolarité',     'icon' => 'users'],
    ['href' => '/v2/rapports/dashboard/academique',    'label' => 'Académique',    'icon' => 'graduation-cap'],
    ['href' => '/v2/rapports/dashboard/finance',       'label' => 'Finance',       'icon' => 'banknote'],
    ['href' => '/v2/rapports/dashboard/rh',            'label' => 'RH',            'icon' => 'user-check'],
    ['href' => '/v2/rapports/dashboard/vie-scolaire',  'label' => 'Vie scolaire',  'icon' => 'heart'],
    ['href' => '/v2/rapports/dashboard/bibliotheque',  'label' => 'Bibliothèque',  'icon' => 'book-open'],
    ['href' => '/v2/rapports/dashboard/inventaire',    'label' => 'Inventaire',    'icon' => 'package'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titre ?? 'Dashboard') ?> — BI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<!-- Top nav -->
<div class="bg-white border-b border-slate-100 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center gap-1 overflow-x-auto py-2 scrollbar-hide">
            <?php foreach ($navItems as $nav): ?>
            <?php $active = str_ends_with($_SERVER['REQUEST_URI'] ?? '', $nav['href']); ?>
            <a href="<?= $nav['href'] ?>"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm whitespace-nowrap flex-shrink-0
                      <?= $active ? 'bg-violet-100 text-violet-700 font-medium' : 'text-slate-500 hover:bg-slate-100' ?>">
                <i data-lucide="<?= $nav['icon'] ?>" class="w-3.5 h-3.5"></i>
                <?= $nav['label'] ?>
            </a>
            <?php endforeach; ?>
            <div class="ml-auto flex items-center gap-1 flex-shrink-0">
                <a href="<?= BASE_URL ?>/v2/rapports/exports/form" class="px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 rounded-lg flex items-center gap-1">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
                </a>
                <a href="<?= BASE_URL ?>/v2/rapports/kpis" class="px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 rounded-lg flex items-center gap-1">
                    <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i> KPIs
                </a>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- Page header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
                <i data-lucide="<?= htmlspecialchars($icon ?? 'bar-chart-2') ?>" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($titre ?? '') ?></h1>
                <p class="text-xs text-slate-400">Période : <?= htmlspecialchars($metrics->periodeLabel ?? date('Y-m')) ?> — Généré à <?= htmlspecialchars($metrics->genereA ?? '') ?></p>
            </div>
        </div>
    </div>

    <!-- Alertes -->
    <?php foreach ($alertes as $aw): ?>
        <?php foreach ($aw['alertes'] ?? [] as $a): ?>
        <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3 text-sm text-amber-800">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 flex-shrink-0"></i>
            <strong><?= htmlspecialchars(str_replace('_', ' ', ucfirst($a['metrique'] ?? ''))) ?></strong>
            : valeur <?= htmlspecialchars((string)($a['valeur'] ?? '')) ?> (seuil <?= htmlspecialchars((string)($a['seuil'] ?? '')) ?>)
        </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <!-- KPI Row -->
    <?php if (!empty($kpis)): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
        <?php foreach ($kpis as $k): ?>
        <?php
        $t  = $k['tendance']  ?? 'stable';
        $tv = $k['variation'] ?? null;
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
            <div class="text-xs font-medium text-slate-500 truncate"><?= htmlspecialchars($k['label'] ?? $k['metrique'] ?? '') ?></div>
            <div class="text-xl font-bold text-slate-800 mt-1"><?= htmlspecialchars((string)($k['valeur'] ?? 0)) ?></div>
            <?php if (!empty($k['unite'])): ?>
            <div class="text-xs text-slate-400"><?= htmlspecialchars($k['unite']) ?></div>
            <?php endif; ?>
            <?php if ($tv !== null): ?>
            <div class="text-xs mt-1 <?= $t === 'hausse' ? 'text-green-600' : ($t === 'baisse' ? 'text-red-600' : 'text-slate-400') ?>">
                <?= $tv > 0 ? '+' : '' ?><?= number_format((float)$tv, 1) ?>%
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Charts Grid -->
    <?php if (!empty($charts)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <?php foreach ($charts as $c):
            $cid  = 'ch_' . preg_replace('/[^a-z0-9]/i', '_', $c['id'] ?? uniqid());
            $json = json_encode($c['data'] ?? [], JSON_UNESCAPED_UNICODE);
        ?>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <?php if (!empty($c['titre'])): ?>
            <h3 class="text-sm font-semibold text-slate-700 mb-3"><?= htmlspecialchars($c['titre']) ?></h3>
            <?php endif; ?>
            <div style="position:relative;height:200px;">
                <canvas id="<?= $cid ?>"></canvas>
            </div>
        </div>
        <script>(function(){ const c=document.getElementById('<?= $cid ?>'); if(c) new Chart(c,<?= $json ?>); })();</script>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tables -->
    <?php foreach ($tables as $tbl): ?>
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($tbl['titre'] ?? '') ?></h3>
            <?php if (($tbl['total'] ?? 0) > count($tbl['rows'] ?? [])): ?>
            <span class="text-xs text-slate-400"><?= count($tbl['rows'] ?? []) ?> / <?= $tbl['total'] ?></span>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <?php foreach ($tbl['colonnes'] ?? [] as $col): ?>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500"><?= htmlspecialchars($col['label'] ?? $col['key']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($tbl['rows'])): ?>
                <tr><td colspan="<?= count($tbl['colonnes'] ?? []) ?>" class="px-4 py-4 text-center text-slate-400 text-xs">Aucune donnée</td></tr>
                <?php else: ?>
                <?php foreach ($tbl['rows'] as $row): ?>
                <tr class="hover:bg-slate-50">
                    <?php foreach ($tbl['colonnes'] as $col): ?>
                    <td class="px-4 py-2 text-slate-700"><?= htmlspecialchars((string)($row[$col['key']] ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; ?>

</div><!-- /max-w-7xl -->
<script>lucide.createIcons();</script>
</body>
</html>
