<?php /** @var array $presences, string $date */ ?>
<div class="space-y-4">
  <form method="GET" class="flex items-center gap-3">
    <label class="text-sm text-slate-600">Date :</label>
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-rose-500">
    <button type="submit" class="bg-rose-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-rose-700 transition-colors">Filtrer</button>
  </form>

  <?php if (empty($presences)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="users" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Aucune donnée de présence pour cette date.</p></div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Employé</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Poste</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Arrivée</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Statut</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($presences as $p): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($p['employe_nom'] ?? '') ?></td>
          <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($p['poste'] ?? '') ?></td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($p['heure_arrivee'] ?? '—') ?></td>
          <td class="px-4 py-3">
            <?php
            $statut = $p['statut'] ?? '';
            $badge = match($statut) {
                'present' => 'bg-green-100 text-green-700',
                'absent'  => 'bg-red-100 text-red-700',
                'retard'  => 'bg-amber-100 text-amber-700',
                default   => 'bg-slate-100 text-slate-600',
            };
            ?>
            <span class="<?= $badge ?> text-xs px-2 py-0.5 rounded-full capitalize"><?= htmlspecialchars($statut) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
