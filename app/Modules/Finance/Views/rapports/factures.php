<?php
/**
 * @var array  $pagination
 * @var object $stats
 * @var float  $tauxRecouvrement
 * @var array  $classes, $niveaux, $anneesSco
 * @var \App\Modules\Finance\DTO\ReportFiltersDTO $filters
 * @var array  $user
 */
$fmt = fn(float $v) => number_format($v, 0, ',', ' ') . ' XOF';
$statutBadge = [
    'emise'               => 'bg-blue-100 text-blue-700',
    'partiellement_payee' => 'bg-amber-100 text-amber-700',
    'payee'               => 'bg-emerald-100 text-emerald-700',
    'en_retard'           => 'bg-red-100 text-red-700',
    'annulee'             => 'bg-slate-100 text-slate-500',
];
?>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <nav class="text-sm text-slate-400 mb-1">
                <a href="<?= BASE_URL ?>/v2/finance/rapports" class="hover:text-violet-600">Rapports</a> / Factures
            </nav>
            <h1 class="text-2xl font-bold text-slate-800">Rapport des factures</h1>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/v2/finance/rapports/export?type=factures&format=csv&<?= http_build_query($_GET) ?>"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4"></i> CSV
            </a>
            <a href="<?= BASE_URL ?>/v2/finance/rapports/export?type=factures&format=excel&<?= http_build_query($_GET) ?>"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="table" class="w-4 h-4"></i> Excel
            </a>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap gap-3 items-end shadow-sm">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
            <select name="statut" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <?php foreach (['emise'=>'Émise','partiellement_payee'=>'Part. payée','payee'=>'Payée','en_retard'=>'En retard','annulee'=>'Annulée'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filters->statut === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
            <select name="annee_scolaire" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Toutes</option>
                <?php foreach ($anneesSco as $as): ?>
                <option value="<?= $as ?>" <?= $filters->anneeScolaire === $as ? 'selected' : '' ?>><?= $as ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classe</label>
            <select name="classe_id" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Toutes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c->id ?>" <?= $filters->classeId === $c->id ? 'selected' : '' ?>><?= htmlspecialchars($c->nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut) ?>" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
            <input type="date" name="date_fin" value="<?= htmlspecialchars($filters->dateFin) ?>" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
            <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>" placeholder="N° facture, élève…" class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-44">
        </div>
        <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">Filtrer</button>
        <a href="<?= BASE_URL ?>/v2/finance/rapports/factures" class="text-slate-500 text-sm px-3 py-2 hover:text-slate-700">Reset</a>
    </form>

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <?php
        $statsCards = [
            ['v'=>$stats->nb_total,        'l'=>'Total',         'c'=>'text-slate-700'],
            ['v'=>$stats->nb_emises,        'l'=>'Émises',        'c'=>'text-blue-600'],
            ['v'=>$stats->nb_partielles,    'l'=>'Part. payées',  'c'=>'text-amber-600'],
            ['v'=>$stats->nb_payees,        'l'=>'Payées',        'c'=>'text-emerald-600'],
            ['v'=>$stats->nb_retard,        'l'=>'En retard',     'c'=>'text-red-600'],
        ];
        foreach ($statsCards as $sc):
        ?>
        <div class="bg-white rounded-xl border border-slate-100 p-4 text-center shadow-sm">
            <div class="text-2xl font-bold <?= $sc['c'] ?>"><?= number_format($sc['v']) ?></div>
            <div class="text-xs text-slate-500 mt-1"><?= $sc['l'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
            <div class="text-xl font-bold text-slate-700"><?= $fmt((float)$stats->total_emis) ?></div>
            <div class="text-xs text-slate-500 mt-1">Total émis</div>
        </div>
        <div class="bg-white rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
            <div class="text-xl font-bold text-emerald-700"><?= $fmt((float)$stats->total_encaisse) ?></div>
            <div class="text-xs text-emerald-600 mt-1">Encaissé</div>
        </div>
        <div class="bg-white rounded-xl border border-amber-200 bg-amber-50 p-4 text-center">
            <div class="text-xl font-bold text-amber-700"><?= $fmt((float)$stats->total_restant) ?></div>
            <div class="text-xs text-amber-600 mt-1">Restant — Tx: <?= $tauxRecouvrement ?>%</div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <span class="font-medium text-slate-700"><?= number_format($pagination['total']) ?> factures</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">N° Facture</th>
                    <th class="px-4 py-3 text-left">Élève</th>
                    <th class="px-4 py-3 text-left">Classe</th>
                    <th class="px-4 py-3 text-left">Année</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Payé</th>
                    <th class="px-4 py-3 text-right">Restant</th>
                    <th class="px-4 py-3 text-left">Émission</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($pagination['items'])): ?>
                <tr><td colspan="9" class="text-center py-10 text-slate-400">Aucune facture trouvée</td></tr>
                <?php else: ?>
                <?php foreach ($pagination['items'] as $f): $bc = $statutBadge[$f->statut] ?? 'bg-slate-100 text-slate-600'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-violet-700"><?= htmlspecialchars($f->numero) ?></td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($f->eleve_nom) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($f->eleve_matricule) ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($f->classe_nom) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($f->annee_scolaire) ?></td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= $fmt((float)$f->montant_total) ?></td>
                    <td class="px-4 py-3 text-right text-emerald-600"><?= $fmt((float)$f->montant_paye) ?></td>
                    <td class="px-4 py-3 text-right <?= (float)$f->montant_restant > 0 ? 'text-red-600 font-medium' : 'text-slate-400' ?>"><?= $fmt((float)$f->montant_restant) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($f->date_emission)) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $bc ?>"><?= ucfirst(str_replace('_', ' ', $f->statut)) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-500">Page <?= $pagination['page'] ?> / <?= $pagination['total_pages'] ?></span>
            <div class="flex gap-2">
                <?php if ($pagination['page'] > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] - 1])) ?>" class="px-3 py-1 border border-slate-200 rounded text-sm">← Préc.</a>
                <?php endif; ?>
                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] + 1])) ?>" class="px-3 py-1 border border-slate-200 rounded text-sm">Suiv. →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
