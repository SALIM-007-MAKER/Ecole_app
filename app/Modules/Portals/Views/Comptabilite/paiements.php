<?php /** @var array $paiements, int $page */ ?>
<div class="space-y-4">
  <?php if (empty($paiements)): ?>
  <div class="text-center py-10 text-slate-400"><p>Aucun paiement.</p></div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Élève</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Facture</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Montant</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Mode</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($paiements as $p): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($p['eleve_nom'] ?? '') ?></td>
          <td class="px-4 py-3 text-xs font-mono text-slate-500"><?= htmlspecialchars($p['facture_num'] ?? '') ?></td>
          <td class="px-4 py-3 font-semibold text-green-700"><?= number_format((float)($p['montant'] ?? 0), 0, ',', ' ') ?></td>
          <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($p['mode_paiement'] ?? '') ?></td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars($p['date_paiement'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
