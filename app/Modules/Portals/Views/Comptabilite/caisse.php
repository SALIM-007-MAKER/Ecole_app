<?php /** @var array $journal */ ?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h2 class="text-sm font-semibold text-slate-700">Journal de caisse — <?= date('d/m/Y') ?></h2>
    <a href="/v2/finance/caisse" class="text-xs text-amber-600 hover:underline">Gestion complète →</a>
  </div>
  <?php if (empty($journal)): ?>
  <div class="text-center py-8 text-slate-400"><p>Aucun mouvement aujourd'hui.</p></div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Type</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Libellé</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Montant</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Heure</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($journal as $j): ?>
        <tr>
          <td class="px-4 py-3">
            <span class="<?= ($j['type_mouvement'] ?? '') === 'entree' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> text-xs px-2 py-0.5 rounded-full">
              <?= ($j['type_mouvement'] ?? '') === 'entree' ? '↑ Entrée' : '↓ Sortie' ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($j['libelle'] ?? '') ?></td>
          <td class="px-4 py-3 font-semibold <?= ($j['type_mouvement'] ?? '') === 'entree' ? 'text-green-700' : 'text-red-700' ?>">
            <?= number_format((float)($j['montant'] ?? 0), 0, ',', ' ') ?>
          </td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars(substr((string)($j['created_at'] ?? ''), 11, 5)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
