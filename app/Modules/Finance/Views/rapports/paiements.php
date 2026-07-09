<?php
/**
 * @var array  $pagination   {items, total, page, per_page, total_pages}
 * @var object $sommes
 * @var array  $parClasse
 * @var array  $parMode
 * @var array  $classes
 * @var array  $niveaux
 * @var array  $modes
 * @var array  $anneesSco
 * @var \App\Modules\Finance\DTO\ReportFiltersDTO $filters
 * @var array  $user
 */
$fmt = fn(float $v) => number_format($v, 0, ',', ' ') . ' XOF';
$statutBadge = [
    'complete'   => 'bg-emerald-100 text-emerald-700',
    'valide'     => 'bg-blue-100 text-blue-700',
    'partiel'    => 'bg-amber-100 text-amber-700',
    'annule'     => 'bg-red-100 text-red-700',
    'rembourse'  => 'bg-purple-100 text-purple-700',
    'initie'     => 'bg-slate-100 text-slate-700',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Rapport paiements — Finance V2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#7c3aed'}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen">
<?php include BASE_PATH . '/app/Modules/Finance/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <nav class="text-sm text-slate-400 mb-1">
                <a href="/v2/finance/rapports" class="hover:text-violet-600">Rapports</a> / Paiements
            </nav>
            <h1 class="text-2xl font-bold text-slate-800">Rapport des paiements</h1>
        </div>
        <div class="flex gap-2">
            <a href="/v2/finance/rapports/export?type=paiements&format=csv&<?= http_build_query($_GET) ?>"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4"></i> CSV
            </a>
            <a href="/v2/finance/rapports/export?type=paiements&format=excel&<?= http_build_query($_GET) ?>"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="table" class="w-4 h-4"></i> Excel
            </a>
            <a href="/v2/finance/rapports/print?type=paiements&<?= http_build_query($_GET) ?>" target="_blank"
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
            <label class="block text-xs font-medium text-slate-600 mb-1">Mode paiement</label>
            <select name="mode_paiement" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <?php foreach ($modes as $m): ?>
                <option value="<?= $m->code ?>" <?= $filters->modePaiement === $m->code ? 'selected' : '' ?>><?= htmlspecialchars($m->nom) ?></option>
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
            <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>" placeholder="N° paiement, élève…" class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-44">
        </div>
        <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">Filtrer</button>
        <a href="/v2/finance/rapports/paiements" class="text-slate-500 text-sm px-4 py-2 hover:text-slate-700">Réinitialiser</a>
    </form>

    <!-- Totaux -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
            <div class="text-2xl font-bold text-emerald-700"><?= $fmt((float)$sommes->total_encaisse) ?></div>
            <div class="text-sm text-emerald-600 mt-1">Total encaissé</div>
        </div>
        <div class="bg-white rounded-xl border border-blue-200 bg-blue-50 p-4 text-center">
            <div class="text-2xl font-bold text-blue-700"><?= number_format($sommes->nb_paiements) ?></div>
            <div class="text-sm text-blue-600 mt-1">Paiements</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
            <div class="text-2xl font-bold text-slate-700"><?= $fmt((float)$sommes->moy_paiement) ?></div>
            <div class="text-sm text-slate-500 mt-1">Montant moyen</div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <span class="font-medium text-slate-700"><?= number_format($pagination['total']) ?> paiements trouvés</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">N° Paiement</th>
                    <th class="px-4 py-3 text-left">Élève</th>
                    <th class="px-4 py-3 text-left">Classe</th>
                    <th class="px-4 py-3 text-left">Mode</th>
                    <th class="px-4 py-3 text-left">Facture</th>
                    <th class="px-4 py-3 text-right">Montant</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($pagination['items'])): ?>
                <tr><td colspan="8" class="text-center py-10 text-slate-400">Aucun paiement trouvé</td></tr>
                <?php else: ?>
                <?php foreach ($pagination['items'] as $p): $badgeCls = $statutBadge[$p->statut] ?? 'bg-slate-100 text-slate-600'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-violet-700"><?= htmlspecialchars($p->numero) ?></td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($p->eleve_nom) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($p->eleve_matricule) ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($p->classe_nom) ?></td>
                    <td class="px-4 py-3">
                        <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs font-medium"><?= htmlspecialchars($p->mode_code) ?></span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars($p->facture_numero) ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800"><?= $fmt((float)$p->montant_applique) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($p->date_paiement)) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeCls ?>"><?= ucfirst($p->statut) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-500">Page <?= $pagination['page'] ?> / <?= $pagination['total_pages'] ?></span>
            <div class="flex gap-2">
                <?php if ($pagination['page'] > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] - 1])) ?>" class="px-3 py-1 border border-slate-200 rounded text-sm hover:bg-slate-50">← Préc.</a>
                <?php endif; ?>
                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] + 1])) ?>" class="px-3 py-1 border border-slate-200 rounded text-sm hover:bg-slate-50">Suiv. →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
