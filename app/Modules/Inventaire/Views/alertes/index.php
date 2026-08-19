<?php /** @var array $alertes @var int $count */ ?>
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Alertes</h1>
            <p class="text-slate-500 text-sm"><?= $count ?> alerte(s) active(s)</p>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/alertes/scanner">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="inline-flex items-center gap-2 border px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-white">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Scanner les stocks
            </button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Article</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Type</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Valeur actuelle</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Seuil</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($alertes as $a): ?>
                <?php $statColors=['active'=>'red','acquittee'=>'amber','resolue'=>'green']; $col=$statColors[$a['statut']]??'slate'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium"><?= htmlspecialchars($a['designation']) ?></div>
                        <div class="text-xs font-mono text-slate-400"><?= htmlspecialchars($a['reference']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-xs"><?= str_replace('_',' ',$a['type']) ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-red-600"><?= number_format((float)($a['valeur_actuelle'] ?? 0), 1) ?></td>
                    <td class="px-4 py-3 text-right text-slate-400"><?= number_format((float)($a['valeur_seuil'] ?? 0), 1) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                            <?= ucfirst($a['statut']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($a['statut'] === 'active'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/alertes/<?=$a['id']?>/acquitter" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="text-amber-600 hover:underline text-xs">Acquitter</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($alertes)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucune alerte.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
