<?php /** @var array $absences */ ?>
<div class="space-y-4">
  <?php if (empty($absences)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 text-green-300"></i><p>Aucune absence enregistrée.</p></div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Motif</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Statut</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($absences as $a): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($a['date_absence'] ?? '') ?></td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($a['motif'] ?? '—') ?></td>
          <td class="px-4 py-3">
            <?php if ($a['justifiee'] ?? false): ?>
            <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Justifiée</span>
            <?php else: ?>
            <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">Non justifiée</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
