<?php /** @var array $article @var array $mouvements @var array $affectations */ ?>
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="<?= BASE_URL ?>/v2/inventaire/articles" class="text-violet-600 hover:underline text-sm">&larr; Articles</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-1"><?= htmlspecialchars($article['designation']) ?></h1>
            <p class="text-slate-500 text-sm font-mono"><?= htmlspecialchars($article['reference']) ?></p>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/v2/inventaire/articles/<?= $article['id'] ?>/modifier"
               class="inline-flex items-center gap-2 border px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-50">
                <i data-lucide="edit-2" class="w-4 h-4"></i> Modifier
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Infos article -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                    <i data-lucide="package" class="w-4 h-4 text-violet-500"></i> Informations
                </h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">Type</dt><dd class="font-medium"><?= ucfirst($article['type']) ?></dd></div>
                    <div><dt class="text-slate-500">Catégorie</dt><dd class="font-medium"><?= htmlspecialchars($article['categorie_nom'] ?? '—') ?></dd></div>
                    <div><dt class="text-slate-500">Fournisseur</dt><dd class="font-medium"><?= htmlspecialchars($article['fournisseur_nom'] ?? '—') ?></dd></div>
                    <div><dt class="text-slate-500">Unité</dt><dd class="font-medium"><?= htmlspecialchars($article['unite_mesure']) ?></dd></div>
                    <div><dt class="text-slate-500">Valeur unitaire</dt><dd class="font-medium"><?= number_format((float)$article['valeur_unitaire'], 2) ?> €</dd></div>
                    <div><dt class="text-slate-500">Garantie</dt><dd class="font-medium"><?= $article['garantie_mois'] ?> mois</dd></div>
                    <div><dt class="text-slate-500">Code-barres</dt><dd class="font-mono text-xs"><?= htmlspecialchars($article['barcode'] ?? '—') ?></dd></div>
                    <div><dt class="text-slate-500">N° Série</dt><dd class="font-mono text-xs"><?= htmlspecialchars($article['numero_serie'] ?? '—') ?></dd></div>
                </dl>
            </div>

            <!-- Mouvements récents -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-violet-500"></i> Mouvements récents
                </h2>
                <table class="w-full text-sm">
                    <thead><tr class="text-left border-b">
                        <th class="py-2 text-slate-500 font-medium">Date</th>
                        <th class="py-2 text-slate-500 font-medium">Type</th>
                        <th class="py-2 text-slate-500 font-medium text-right">Qté</th>
                        <th class="py-2 text-slate-500 font-medium text-right">Avant</th>
                        <th class="py-2 text-slate-500 font-medium text-right">Après</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php foreach (array_slice($mouvements, 0, 10) as $m): ?>
                        <tr>
                            <td class="py-2 text-slate-500"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
                            <td class="py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100"><?= $m['type'] ?></span></td>
                            <td class="py-2 text-right <?= $m['type'] === 'entree' ? 'text-green-600' : 'text-red-600' ?>"><?= number_format((float)$m['quantite'], 1) ?></td>
                            <td class="py-2 text-right text-slate-400"><?= number_format((float)$m['quantite_avant'], 1) ?></td>
                            <td class="py-2 text-right font-medium"><?= number_format((float)$m['quantite_apres'], 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mouvements)): ?>
                    <tr><td colspan="5" class="py-4 text-center text-slate-400">Aucun mouvement.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-slate-700 mb-4">Stock</h2>
                <div class="text-3xl font-bold text-violet-600 mb-1">
                    <?php
                    $stockTotal = 0;
                    foreach ($stocks as $s) {
                        if ($s['id'] == $article['id']) $stockTotal = (float)$s['stock_total'];
                    }
                    echo number_format($stockTotal, 1);
                    ?>
                </div>
                <p class="text-sm text-slate-500"><?= htmlspecialchars($article['unite_mesure']) ?>(s) disponible(s)</p>
                <?php $enAlerte = $stockTotal <= (float)$article['seuil_alerte']; ?>
                <?php if ($enAlerte): ?>
                <div class="mt-3 flex items-center gap-2 text-red-600 text-sm">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i> Stock sous le seuil d'alerte
                </div>
                <?php endif; ?>
                <div class="mt-4 text-xs text-slate-400">
                    Seuil alerte : <?= $article['seuil_alerte'] ?> | Critique : <?= $article['seuil_critique'] ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-slate-700 mb-4">Actions rapides</h2>
                <div class="space-y-2">
                    <a href="<?= BASE_URL ?>/v2/inventaire/affectations/creer?article_id=<?= $article['id'] ?>"
                       class="block w-full text-center bg-violet-50 text-violet-700 hover:bg-violet-100 rounded-lg px-4 py-2 text-sm font-medium">
                        Affecter cet article
                    </a>
                    <a href="<?= BASE_URL ?>/v2/inventaire/maintenances/creer?article_id=<?= $article['id'] ?>"
                       class="block w-full text-center bg-slate-50 text-slate-700 hover:bg-slate-100 rounded-lg px-4 py-2 text-sm font-medium">
                        Planifier maintenance
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
