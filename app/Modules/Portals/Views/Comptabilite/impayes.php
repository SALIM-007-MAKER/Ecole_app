<?php /** @var array $impayes */ ?>
<div class="space-y-4">
  <?php if (empty($impayes)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 text-green-300"></i><p>Aucun impayé.</p></div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Élève</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Facture</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Reste dû</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Échéance</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($impayes as $i): ?>
        <tr class="hover:bg-slate-50 <?= strtotime($i['date_echeance'] ?? 'now') < time() ? 'bg-red-50' : '' ?>">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($i['eleve_nom'] ?? '') ?></td>
          <td class="px-4 py-3 text-xs font-mono text-slate-500"><?= htmlspecialchars($i['numero'] ?? '') ?></td>
          <td class="px-4 py-3 font-bold text-red-600"><?= number_format((float)($i['reste_a_payer'] ?? 0), 0, ',', ' ') ?></td>
          <td class="px-4 py-3 text-xs <?= strtotime($i['date_echeance'] ?? 'now') < time() ? 'text-red-500 font-semibold' : 'text-slate-400' ?>"><?= htmlspecialchars($i['date_echeance'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
