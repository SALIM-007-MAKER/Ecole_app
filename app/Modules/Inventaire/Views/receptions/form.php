<?php /** @var array $commande @var array $emplacements @var string|null $error */ ?>
<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$commande['id']?>" class="text-slate-500 hover:text-slate-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Réception — <?= htmlspecialchars($commande['numero']) ?></h1>
            <p class="text-sm text-slate-500">Fournisseur : <?= htmlspecialchars($commande['fournisseur_nom'] ?? '—') ?></p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$commande['id']?>/reception" class="space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-slate-700">Informations réception</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date réception <span class="text-red-500">*</span></label>
                    <input type="date" name="date_reception" required value="<?= date('Y-m-d') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">N° Bon de livraison</label>
                    <input type="text" name="bon_livraison"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"></textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b">
                <h2 class="font-semibold text-slate-700">Lignes à réceptionner</h2>
            </div>
            <?php $lignes = $commande['lignes'] ?? []; ?>
            <?php if (empty($lignes)): ?>
                <p class="px-4 py-6 text-center text-slate-400">Aucune ligne de commande.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Article</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Commandé</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Déjà reçu</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Qté reçue</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Emplacement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($lignes as $i => $l): ?>
                    <?php
                    $commandee = (float)$l['quantite_commandee'];
                    $recue     = (float)($l['quantite_recue'] ?? 0);
                    $restante  = max(0, $commandee - $recue);
                    ?>
                    <input type="hidden" name="lignes[<?=$i?>][commande_ligne_id]" value="<?=$l['id']?>">
                    <input type="hidden" name="lignes[<?=$i?>][article_id]" value="<?=$l['article_id']?>">
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-medium"><?= htmlspecialchars($l['designation'] ?? '—') ?></p>
                            <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($l['reference'] ?? '') ?></p>
                        </td>
                        <td class="px-4 py-3 text-right"><?= $commandee ?></td>
                        <td class="px-4 py-3 text-right text-slate-500"><?= $recue ?></td>
                        <td class="px-4 py-3 text-right">
                            <input type="number" name="lignes[<?=$i?>][quantite_recue]"
                                   min="0" step="0.01" max="<?= $restante ?>" value="<?= $restante ?>"
                                   class="w-24 border border-slate-300 rounded px-2 py-1 text-sm text-right focus:ring-1 focus:ring-violet-500">
                        </td>
                        <td class="px-4 py-3">
                            <select name="lignes[<?=$i?>][emplacement_id]" required
                                    class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-500">
                                <?php foreach ($emplacements as $e): ?>
                                <option value="<?=$e['id']?>"><?= htmlspecialchars($e['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="flex justify-end gap-3">
            <a href="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$commande['id']?>" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-700">
                <i data-lucide="package-check" class="w-4 h-4 inline mr-1"></i>Valider la réception
            </button>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
