<?php
/**
 * @var array  $pagination
 * @var object $stats
 * @var \App\Modules\Finance\DTO\ReportFiltersDTO $filters
 * @var array  $user
 */
$fmt = fn(float $v) => number_format($v, 0, ',', ' ') . ' XOF';
$statutBadge = [
    'ouverte'      => 'bg-emerald-100 text-emerald-700',
    'en_activite'  => 'bg-blue-100 text-blue-700',
    'fermee'       => 'bg-slate-100 text-slate-600',
    'en_attente'   => 'bg-amber-100 text-amber-700',
];
?>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <nav class="text-sm text-slate-400 mb-1">
                <a href="<?= BASE_URL ?>/v2/finance/rapports" class="hover:text-violet-600">Rapports</a> / Caisse
            </nav>
            <h1 class="text-2xl font-bold text-slate-800">Rapport de caisse</h1>
        </div>
        <a href="<?= BASE_URL ?>/v2/finance/rapports/export?type=caisse&format=csv&<?= http_build_query($_GET) ?>"
           class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
            <i data-lucide="file-text" class="w-4 h-4"></i> CSV
        </a>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap gap-3 items-end shadow-sm">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
            <select name="statut" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <?php foreach (['ouverte'=>'Ouverte','en_activite'=>'En activité','fermee'=>'Fermée'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filters->statut === $v ? 'selected' : '' ?>><?= $l ?></option>
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
        <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">Filtrer</button>
        <a href="<?= BASE_URL ?>/v2/finance/rapports/caisse" class="text-slate-500 text-sm px-3 py-2">Reset</a>
    </form>

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $cKpis = [
            ['l'=>'Sessions','v'=>number_format($stats->nb_sessions),'c'=>'text-slate-700','icon'=>'archive'],
            ['l'=>'Total recettes','v'=>$fmt((float)$stats->total_recettes),'c'=>'text-emerald-700','icon'=>'trending-up'],
            ['l'=>'Décaissements','v'=>$fmt((float)$stats->total_decaissements),'c'=>'text-red-600','icon'=>'trending-down'],
            ['l'=>'Solde actuel','v'=>$fmt((float)$stats->solde_actuel),'c'=>'text-violet-700','icon'=>'wallet'],
        ];
        foreach ($cKpis as $k):
        ?>
        <div class="bg-white rounded-xl border border-slate-100 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-slate-500"><?= $k['l'] ?></span>
                <i data-lucide="<?= $k['icon'] ?>" class="w-5 h-5 text-slate-400"></i>
            </div>
            <div class="text-2xl font-bold <?= $k['c'] ?>"><?= $k['v'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Sessions -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <span class="font-medium text-slate-700"><?= number_format($pagination['total']) ?> sessions</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Ouverture</th>
                    <th class="px-4 py-3 text-left">Fermeture</th>
                    <th class="px-4 py-3 text-left">Caissier</th>
                    <th class="px-4 py-3 text-right">Fond initial</th>
                    <th class="px-4 py-3 text-right">Recettes</th>
                    <th class="px-4 py-3 text-right">Décaiss.</th>
                    <th class="px-4 py-3 text-right">Solde théo.</th>
                    <th class="px-4 py-3 text-right">Écart</th>
                    <th class="px-4 py-3 text-left">Mvts</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($pagination['items'])): ?>
                <tr><td colspan="10" class="text-center py-10 text-slate-400">Aucune session</td></tr>
                <?php else: ?>
                <?php foreach ($pagination['items'] as $s): $bc = $statutBadge[$s->statut] ?? 'bg-slate-100 text-slate-600'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-600"><?= date('d/m/Y H:i', strtotime($s->date_ouverture)) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= $s->date_fermeture ? date('d/m/Y H:i', strtotime($s->date_fermeture)) : '—' ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($s->caissier_nom ?? '—') ?></td>
                    <td class="px-4 py-3 text-right text-slate-600"><?= $fmt((float)($s->fond_initial ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right text-emerald-600"><?= $fmt((float)($s->total_recettes ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right text-red-600"><?= $fmt((float)($s->total_decaissements ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right font-medium text-slate-700"><?= $fmt((float)($s->solde_theorique ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right <?= abs((float)($s->ecart??0))>0 ? 'text-red-600 font-semibold' : 'text-emerald-600' ?>"><?= $fmt((float)($s->ecart ?? 0)) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= (int)$s->nb_mouvements ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $bc ?>"><?= ucfirst(str_replace('_',' ',$s->statut)) ?></span>
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
