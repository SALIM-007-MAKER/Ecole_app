<?php
/**
 * @var array  $impayes
 * @var array  $aging
 * @var float  $totalDu
 * @var int    $totalEleves
 * @var array  $classes, $niveaux, $anneesSco
 * @var \App\Modules\Finance\DTO\ReportFiltersDTO $filters
 * @var array  $user
 */
$fmt = fn(float $v) => number_format($v, 0, ',', ' ') . ' XOF';
$agingColor = ['0-30j'=>'emerald','31-60j'=>'amber','61-90j'=>'orange','+90j'=>'red'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Rapport impayés — Finance V2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#7c3aed'}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen">
<?php include BASE_PATH . '/app/Modules/Finance/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <nav class="text-sm text-slate-400 mb-1">
                <a href="/v2/finance/rapports" class="hover:text-violet-600">Rapports</a> / Impayés
            </nav>
            <h1 class="text-2xl font-bold text-slate-800">Rapport des impayés</h1>
        </div>
        <div class="flex gap-2">
            <a href="/v2/finance/rapports/export?type=impayes&format=csv&<?= http_build_query($_GET) ?>"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4"></i> CSV
            </a>
            <a href="/v2/finance/rapports/print?type=impayes&<?= http_build_query($_GET) ?>" target="_blank"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimer
            </a>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap gap-3 items-end shadow-sm">
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
            <label class="block text-xs font-medium text-slate-600 mb-1">Tranche d'âge</label>
            <select name="tranche" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Toutes</option>
                <?php foreach (['0-30'=>'0–30 jours','31-60'=>'31–60 jours','61-90'=>'61–90 jours','+90'=>'Plus de 90 jours'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filters->tranche === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
            <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>" placeholder="Élève, matricule…" class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-44">
        </div>
        <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">Filtrer</button>
        <a href="/v2/finance/rapports/impayes" class="text-slate-500 text-sm px-3 py-2 hover:text-slate-700">Reset</a>
    </form>

    <!-- KPIs -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-red-200 bg-red-50 p-5">
            <div class="text-3xl font-bold text-red-700"><?= $fmt($totalDu) ?></div>
            <div class="text-sm text-red-600 mt-1"><?= count($impayes) ?> factures impayées — <?= $totalEleves ?> élève(s)</div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <?php foreach ($aging as $ag):
                $clr = $agingColor[$ag->tranche] ?? 'slate';
            ?>
            <div class="bg-white rounded-xl border border-<?= $clr ?>-200 p-4 text-center">
                <div class="text-lg font-bold text-<?= $clr ?>-700"><?= $fmt((float)$ag->montant) ?></div>
                <div class="text-xs text-<?= $clr ?>-600 mt-1"><?= $ag->tranche ?> — <?= $ag->nb ?> fact.</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <span class="font-medium text-slate-700"><?= count($impayes) ?> impayé(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Élève</th>
                    <th class="px-4 py-3 text-left">Classe</th>
                    <th class="px-4 py-3 text-left">Facture</th>
                    <th class="px-4 py-3 text-left">Année</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Payé</th>
                    <th class="px-4 py-3 text-right">Restant</th>
                    <th class="px-4 py-3 text-left">Tranche</th>
                    <th class="px-4 py-3 text-right">Retard (j)</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($impayes)): ?>
                <tr><td colspan="9" class="text-center py-10 text-slate-400">Aucun impayé</td></tr>
                <?php else: ?>
                <?php foreach ($impayes as $i):
                    $clr = $agingColor[$i->tranche_age] ?? 'slate';
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($i->eleve_nom) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($i->eleve_matricule) ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($i->classe_nom) ?></td>
                    <td class="px-4 py-3 font-mono text-xs text-violet-700"><?= htmlspecialchars($i->numero) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($i->annee_scolaire) ?></td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= $fmt((float)$i->montant_total) ?></td>
                    <td class="px-4 py-3 text-right text-emerald-600"><?= $fmt((float)$i->montant_paye) ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-red-600"><?= $fmt((float)$i->montant_restant) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $clr ?>-100 text-<?= $clr ?>-700"><?= $i->tranche_age ?></span>
                    </td>
                    <td class="px-4 py-3 text-right <?= (int)$i->jours_retard > 90 ? 'text-red-600 font-bold' : 'text-slate-600' ?>"><?= max(0,(int)$i->jours_retard) ?>j</td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($impayes)): ?>
                <tfoot class="bg-slate-50 font-semibold text-sm border-t border-slate-200">
                <tr>
                    <td colspan="6" class="px-4 py-3 text-slate-600">Total</td>
                    <td class="px-4 py-3 text-right text-red-600"><?= $fmt($totalDu) ?></td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</main>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
