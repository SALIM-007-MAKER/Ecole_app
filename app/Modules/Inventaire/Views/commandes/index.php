<?php /** @var array $commandes @var string|null $statut */ ?>
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Commandes Fournisseurs</h1>
        <a href="<?= BASE_URL ?>/v2/inventaire/commandes/creer"
           class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700 text-sm font-medium">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle commande
        </a>
    </div>

    <!-- Filtres statut -->
    <div class="flex gap-2 mb-6 flex-wrap">
        <?php $statuts = ['' => 'Toutes', 'brouillon' => 'Brouillon', 'validee' => 'Validée', 'envoyee' => 'Envoyée', 'partiellement_recue' => 'Partiellement reçue', 'recue' => 'Reçue', 'annulee' => 'Annulée']; ?>
        <?php foreach ($statuts as $s => $label): ?>
        <a href="<?= BASE_URL ?>/v2/inventaire/commandes<?= $s ? "?statut={$s}" : '' ?>"
           class="px-3 py-1.5 rounded-full text-xs border <?= $statut === ($s ?: null) ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-slate-600 hover:bg-slate-50' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">N° Commande</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Fournisseur</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Date</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Total TTC</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($commandes as $c): ?>
                <?php $colors=['brouillon'=>'slate','validee'=>'blue','envoyee'=>'indigo','partiellement_recue'=>'amber','recue'=>'green','annulee'=>'red']; $col=$colors[$c['statut']]??'slate'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($c['numero']) ?></td>
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($c['fournisseur_nom']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($c['date_commande'])) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                            <?= str_replace('_', ' ', ucfirst($c['statut'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold"><?= number_format((float)$c['total_ttc'], 2) ?> €</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <a href="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$c['id']?>" class="text-violet-600 hover:underline text-xs">Voir</a>
                        <?php if ($c['statut'] === 'brouillon'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$c['id']?>/valider" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="text-blue-600 hover:underline text-xs">Valider</button>
                        </form>
                        <?php endif; ?>
                        <?php if (in_array($c['statut'], ['validee', 'envoyee', 'partiellement_recue'], true)): ?>
                        <a href="<?= BASE_URL ?>/v2/inventaire/commandes/<?=$c['id']?>/reception/creer" class="text-green-600 hover:underline text-xs">Réceptionner</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($commandes)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucune commande.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
