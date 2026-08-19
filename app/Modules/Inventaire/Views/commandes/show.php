<?php /** @var array $commande */ ?>
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/v2/inventaire/commandes" class="text-slate-500 hover:text-slate-700">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 font-mono"><?= htmlspecialchars($commande['numero']) ?></h1>
                <?php
                $colors = ['brouillon'=>'slate','validee'=>'blue','envoyee'=>'indigo','partiellement_recue'=>'amber','recue'=>'green','annulee'=>'red'];
                $col = $colors[$commande['statut']] ?? 'slate';
                ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                    <?= str_replace('_', ' ', ucfirst($commande['statut'])) ?>
                </span>
            </div>
        </div>
        <div class="flex gap-2">
            <?php if ($commande['statut'] === 'brouillon'): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$commande['id']?>/valider">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Valider
                </button>
            </form>
            <?php endif; ?>
            <?php if (in_array($commande['statut'], ['validee','envoyee','partiellement_recue'], true)): ?>
            <a href="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$commande['id']?>/reception/creer"
               class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700">
                <i data-lucide="package-check" class="w-4 h-4"></i> Réceptionner
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-slate-500 mb-1">Fournisseur</p>
            <p class="font-semibold text-slate-800"><?= htmlspecialchars($commande['fournisseur_nom'] ?? '—') ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-slate-500 mb-1">Date commande</p>
            <p class="font-semibold text-slate-800"><?= date('d/m/Y', strtotime($commande['date_commande'])) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-slate-500 mb-1">Total TTC</p>
            <p class="text-xl font-bold text-violet-700"><?= number_format((float)$commande['total_ttc'], 2) ?> €</p>
        </div>
    </div>

    <?php if (!empty($commande['lignes'])): ?>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-4 py-3 border-b">
            <h2 class="font-semibold text-slate-700">Lignes de commande</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Article</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Commandé</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Reçu</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">PU HT</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Total HT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($commande['lignes'] as $l): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <p class="font-medium"><?= htmlspecialchars($l['designation'] ?? '—') ?></p>
                        <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($l['reference'] ?? '') ?></p>
                    </td>
                    <td class="px-4 py-3 text-right"><?= $l['quantite_commandee'] ?></td>
                    <td class="px-4 py-3 text-right text-green-700"><?= $l['quantite_recue'] ?? 0 ?></td>
                    <td class="px-4 py-3 text-right"><?= number_format((float)$l['prix_unitaire_ht'], 2) ?> €</td>
                    <td class="px-4 py-3 text-right font-semibold"><?= number_format((float)$l['total_ht'], 2) ?> €</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm p-8 text-center text-slate-400 mb-6">Aucune ligne.</div>
    <?php endif; ?>

    <?php if (!empty($commande['notes'])): ?>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-slate-500 mb-1">Notes</p>
        <p class="text-sm text-slate-700"><?= nl2br(htmlspecialchars($commande['notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>
<script>lucide.createIcons();</script>
