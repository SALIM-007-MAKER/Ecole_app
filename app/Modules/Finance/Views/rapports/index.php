<?php /** @var array $user */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Rapports financiers — Finance V2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#7c3aed'}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen">
<?php include BASE_PATH . '/app/Modules/Finance/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800">Rapports financiers</h1>
        <p class="text-slate-500 mt-1">Générez et exportez les états financiers de l'établissement</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php
        $cards = [
            ['url'=>'/v2/finance/rapports/dashboard', 'icon'=>'layout-dashboard', 'color'=>'violet',
             'title'=>'Tableau de bord','desc'=>'KPIs financiers, trésorerie, évolution mensuelle'],
            ['url'=>'/v2/finance/rapports/paiements', 'icon'=>'credit-card', 'color'=>'emerald',
             'title'=>'Paiements','desc'=>'Historique et statistiques des encaissements'],
            ['url'=>'/v2/finance/rapports/factures',  'icon'=>'file-text',   'color'=>'blue',
             'title'=>'Factures','desc'=>'État de facturation et taux de recouvrement'],
            ['url'=>'/v2/finance/rapports/impayes',   'icon'=>'alert-triangle','color'=>'amber',
             'title'=>'Impayés','desc'=>'Créances en retard et balance âgée'],
            ['url'=>'/v2/finance/rapports/caisse',    'icon'=>'archive',     'color'=>'cyan',
             'title'=>'Caisse','desc'=>'Sessions caisse et mouvements de fonds'],
            ['url'=>'/v2/finance/rapports/analytique','icon'=>'bar-chart-2', 'color'=>'rose',
             'title'=>'Analytique','desc'=>'Comparatifs annuels et projections'],
        ];
        $colorMap = [
            'violet'=>'bg-violet-100 text-violet-700','emerald'=>'bg-emerald-100 text-emerald-700',
            'blue'=>'bg-blue-100 text-blue-700','amber'=>'bg-amber-100 text-amber-700',
            'cyan'=>'bg-cyan-100 text-cyan-700','rose'=>'bg-rose-100 text-rose-700',
        ];
        foreach ($cards as $c):
            $cls = $colorMap[$c['color']] ?? 'bg-slate-100 text-slate-700';
        ?>
        <a href="<?= $c['url'] ?>" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 hover:shadow-md hover:-translate-y-0.5 transition-all group">
            <div class="flex items-center gap-4 mb-4">
                <div class="p-3 rounded-xl <?= $cls ?>">
                    <i data-lucide="<?= $c['icon'] ?>" class="w-6 h-6"></i>
                </div>
                <h2 class="text-lg font-semibold text-slate-800 group-hover:text-violet-700 transition-colors"><?= htmlspecialchars($c['title']) ?></h2>
            </div>
            <p class="text-slate-500 text-sm"><?= htmlspecialchars($c['desc']) ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Export rapide -->
    <div class="mt-10 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Export rapide</h2>
        <form action="/v2/finance/rapports/export" method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                <select name="type" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400">
                    <option value="paiements">Paiements</option>
                    <option value="factures">Factures</option>
                    <option value="impayes">Impayés</option>
                    <option value="caisse">Caisse</option>
                    <option value="analytique">Analytique</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Format</label>
                <select name="format" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400">
                    <option value="csv">CSV</option>
                    <option value="excel">Excel</option>
                    <option value="pdf">PDF (impression)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Du</label>
                <input type="date" name="date_debut" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Au</label>
                <input type="date" name="date_fin" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300">
            </div>
            <button type="submit" class="bg-violet-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors flex items-center gap-2">
                <i data-lucide="download" class="w-4 h-4"></i> Exporter
            </button>
        </form>
    </div>
</main>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
