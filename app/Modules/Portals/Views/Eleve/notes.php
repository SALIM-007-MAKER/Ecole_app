<?php /** @var array $notes */ ?>
<div class="space-y-4">
  <?php if (empty($notes)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="star" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Aucune note disponible.</p></div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Matière</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Évaluation</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Note</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($notes as $n): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($n['matiere_nom'] ?? '') ?></td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($n['eval_titre'] ?? '') ?></td>
          <td class="px-4 py-3">
            <span class="font-bold text-blue-700"><?= number_format((float)($n['valeur'] ?? 0), 2) ?></span>
            <span class="text-slate-400">/ <?= (int)($n['bareme'] ?? 20) ?></span>
          </td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars($n['date_evaluation'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
