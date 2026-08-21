<?php /** @var array $user */ ?>
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
        <a href="<?= BASE_URL ?>/v2/finance/rapports/dashboard" class="hover:text-violet-600">Finance</a>
        <i data-lucide="chevron-right" class="w-3 h-3"></i>
        <span class="text-slate-700">Rapports</span>
    </div>

    <div class="flex items-start gap-4 mb-8">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="trending-up" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Rapports financiers</h1>
            <p class="text-slate-500 text-sm mt-0.5">Générez et exportez les états financiers de l'établissement</p>
        </div>
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
        <a href="<?= BASE_URL . $c['url'] ?>" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 hover:shadow-md hover:-translate-y-0.5 transition-all group">
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
        <form action="<?= BASE_URL ?>/v2/finance/rapports/export" method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="paiements">Paiements</option>
                    <option value="factures">Factures</option>
                    <option value="impayes">Impayés</option>
                    <option value="caisse">Caisse</option>
                    <option value="analytique">Analytique</option>
                </select>
            </div>
            <div>
                <label class="form-label">Format</label>
                <select name="format" class="form-select">
                    <option value="csv">CSV</option>
                    <option value="excel">Excel</option>
                    <option value="pdf">PDF (impression)</option>
                </select>
            </div>
            <div>
                <label class="form-label">Du</label>
                <input type="date" name="date_debut" class="form-input">
            </div>
            <div>
                <label class="form-label">Au</label>
                <input type="date" name="date_fin" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="download" class="w-4 h-4"></i> Exporter
            </button>
        </form>
    </div>
