<?php /** @var array $stats, string $mois */ ?>
<div class="space-y-4">
  <form method="GET" class="flex items-center gap-3">
    <label class="text-sm text-slate-600">Mois :</label>
    <input type="month" name="mois" value="<?= htmlspecialchars($mois) ?>" class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
    <button type="submit" class="bg-amber-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-amber-700 transition-colors">Appliquer</button>
  </form>

  <?php $totalMois = array_sum(array_column($stats, 'total')); ?>
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
    <p class="text-sm font-semibold text-amber-700">Total encaissé — <?= htmlspecialchars($mois) ?></p>
    <p class="text-3xl font-bold text-amber-800 mt-1"><?= number_format($totalMois, 0, ',', ' ') ?> <span class="text-base font-normal text-amber-600">FCFA</span></p>
  </div>

  <?php if (!empty($stats)): ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Mode de paiement</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Nb transactions</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Total</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($stats as $s): ?>
        <tr><td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($s['mode_paiement'] ?? '') ?></td><td class="px-4 py-3 text-slate-500"><?= (int)($s['nb'] ?? 0) ?></td><td class="px-4 py-3 font-semibold text-slate-800"><?= number_format((float)($s['total'] ?? 0), 0, ',', ' ') ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <a href="<?= BASE_URL ?>/v2/rapports/finance" class="inline-flex items-center gap-2 text-sm text-amber-600 hover:underline">
    <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Rapports financiers avancés
  </a>
</div>
