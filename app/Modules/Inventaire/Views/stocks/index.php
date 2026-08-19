<?php /** @var array $stocks @var array $alertes @var array $emplacements */ ?>
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">État du Stock</h1>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/v2/inventaire/stocks/mouvements" class="border px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-white flex items-center gap-1">
                <i data-lucide="activity" class="w-4 h-4"></i> Journal
            </a>
        </div>
    </div>

    <?php if (!empty($alertes)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-red-500 mt-0.5 shrink-0"></i>
        <div>
            <p class="text-red-700 font-semibold text-sm"><?= count($alertes) ?> article(s) en rupture ou sous seuil d'alerte</p>
            <p class="text-red-600 text-xs mt-1"><?= implode(', ', array_column(array_slice($alertes, 0, 5), 'designation')) ?><?= count($alertes) > 5 ? '…' : '' ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions rapides -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-slate-700 mb-3 text-sm flex items-center gap-2"><i data-lucide="sliders" class="w-4 h-4 text-violet-500"></i> Ajustement de stock</h3>
            <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/stocks/ajuster" class="space-y-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <select name="article_id" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="">— Article —</option>
                    <?php foreach ($stocks as $s): ?>
                    <option value="<?=$s['id']?>"><?= htmlspecialchars($s['reference'].' — '.$s['designation']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="emplacement_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($emplacements as $e): ?>
                    <option value="<?=$e['id']?>"><?= htmlspecialchars($e['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="nouvelle_quantite" step="0.01" min="0" placeholder="Nouvelle quantité" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <input type="text" name="motif" placeholder="Motif ajustement" class="w-full border rounded-lg px-3 py-2 text-sm">
                <button type="submit" class="w-full bg-violet-600 text-white rounded-lg py-2 text-sm font-medium hover:bg-violet-700">Ajuster</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-slate-700 mb-3 text-sm flex items-center gap-2"><i data-lucide="arrow-right-left" class="w-4 h-4 text-violet-500"></i> Transfert entre emplacements</h3>
            <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/stocks/transferer" class="space-y-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <select name="article_id" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="">— Article —</option>
                    <?php foreach ($stocks as $s): ?>
                    <option value="<?=$s['id']?>"><?= htmlspecialchars($s['designation']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select name="source_id" class="border rounded-lg px-2 py-2 text-sm">
                        <option value="">Source</option>
                        <?php foreach ($emplacements as $e): ?><option value="<?=$e['id']?>"><?= htmlspecialchars($e['nom']) ?></option><?php endforeach; ?>
                    </select>
                    <select name="destination_id" class="border rounded-lg px-2 py-2 text-sm">
                        <option value="">Destination</option>
                        <?php foreach ($emplacements as $e): ?><option value="<?=$e['id']?>"><?= htmlspecialchars($e['nom']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <input type="number" name="quantite" step="0.01" min="0.01" placeholder="Quantité" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <button type="submit" class="w-full bg-slate-700 text-white rounded-lg py-2 text-sm font-medium hover:bg-slate-800">Transférer</button>
            </form>
        </div>
    </div>

    <!-- Table stock global -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Réf.</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Désignation</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Disponible</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Réservé</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Seuil</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Valeur</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($stocks as $s): ?>
                <?php $alerte = (float)$s['stock_total'] <= (float)$s['seuil_alerte']; ?>
                <tr class="hover:bg-slate-50 <?= $alerte ? 'bg-red-50' : '' ?>">
                    <td class="px-4 py-3 font-mono text-xs text-slate-400"><?= htmlspecialchars($s['reference']) ?></td>
                    <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($s['designation']) ?></td>
                    <td class="px-4 py-3 text-right <?= $alerte ? 'text-red-600 font-bold' : 'text-slate-800' ?>">
                        <?= number_format((float)$s['stock_total'], 1) ?> <?= htmlspecialchars($s['unite_mesure']) ?>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-400"><?= number_format((float)$s['stock_reserve'], 1) ?></td>
                    <td class="px-4 py-3 text-right text-slate-400"><?= number_format((float)$s['seuil_alerte'], 1) ?></td>
                    <td class="px-4 py-3 text-right text-slate-600">—</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
