<?php /** @var array $inventaire @var array $lignes */ ?>
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/v2/inventaire/inventaires-physiques" class="text-slate-500 hover:text-slate-700">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($inventaire['libelle'] ?? 'Inventaire') ?></h1>
                <p class="text-sm text-slate-500"><?= date('d/m/Y', strtotime($inventaire['date_inventaire'])) ?></p>
            </div>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/inventaires-physiques/<?=$inventaire['id']?>/cloture"
              onsubmit="return confirm('Clôturer et appliquer les ajustements de stock ?')">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700">
                <i data-lucide="check-square" class="w-4 h-4"></i> Clôturer l'inventaire
            </button>
        </form>
    </div>

    <?php
    $total   = count($lignes);
    $comptes = count(array_filter($lignes, fn($l) => $l['statut'] === 'compte'));
    $pct     = $total > 0 ? round($comptes / $total * 100) : 0;
    ?>
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-slate-700">Progression</span>
            <span class="text-sm font-bold text-violet-700"><?= $comptes ?>/<?= $total ?> (<?= $pct ?>%)</span>
        </div>
        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-2 bg-violet-500 rounded-full transition-all" style="width:<?=$pct?>%"></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Article</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Emplacement</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Stock théorique</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Qté comptée</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Écart</th>
                    <th class="text-center px-4 py-3 font-semibold text-slate-600">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($lignes as $l): ?>
                <?php
                $comptes_val = $l['quantite_comptee'] !== null ? (float)$l['quantite_comptee'] : null;
                $theorique   = (float)($l['quantite_theorique'] ?? 0);
                $ecart       = $comptes_val !== null ? $comptes_val - $theorique : null;
                $ok          = $l['statut'] === 'compte';
                ?>
                <tr class="hover:bg-slate-50" id="ligne-<?=$l['id']?>">
                    <td class="px-4 py-3">
                        <p class="font-medium"><?= htmlspecialchars($l['designation'] ?? '—') ?></p>
                        <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($l['reference'] ?? '') ?></p>
                    </td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($l['emplacement_nom'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right"><?= $theorique ?></td>
                    <td class="px-4 py-3 text-right">
                        <?php if (!$ok): ?>
                        <div class="flex items-center justify-end gap-2">
                            <input type="number" id="qte-<?=$l['id']?>" min="0" step="0.01"
                                   class="w-20 border border-slate-300 rounded px-2 py-1 text-sm text-right focus:ring-1 focus:ring-violet-500"
                                   placeholder="0">
                            <button onclick="saisir(<?=$l['id']?>)"
                                    class="bg-violet-600 text-white px-2 py-1 rounded text-xs hover:bg-violet-700">
                                <i data-lucide="check" class="w-3 h-3"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="font-semibold"><?= $comptes_val ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($ecart !== null): ?>
                            <span class="font-semibold <?= $ecart < 0 ? 'text-red-600' : ($ecart > 0 ? 'text-blue-600' : 'text-green-600') ?>">
                                <?= ($ecart >= 0 ? '+' : '') . $ecart ?>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($ok): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">
                            <i data-lucide="check" class="w-3 h-3 mr-1"></i>Compté
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-500">En attente</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($lignes)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucun article dans cet inventaire.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function saisir(ligneId) {
    const qteInput = document.getElementById('qte-' + ligneId);
    const qte = parseFloat(qteInput.value);
    if (isNaN(qte) || qte < 0) { alert('Quantité invalide'); return; }

    fetch('<?= BASE_URL ?>/v2/inventaire/inventaires-physiques/<?=$inventaire['id']?>/saisir', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= urlencode($_SESSION['csrf_token'] ?? '') ?>&ligne_id=' + ligneId + '&quantite_comptee=' + qte,
    })
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); else alert('Erreur lors de la saisie'); })
    .catch(() => alert('Erreur réseau'));
}
lucide.createIcons();
</script>
