<?php /** @var array $factures, int $page, string $statut */ ?>
<div class="space-y-4">
  <div class="flex items-center gap-3 flex-wrap">
    <?php foreach (['' => 'Toutes', 'emise' => 'Émises', 'partielle' => 'Partielles', 'payee' => 'Payées'] as $s => $label): ?>
    <a href="?statut=<?= $s ?>"
       class="text-sm px-3 py-1.5 rounded-full <?= $statut === $s ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> transition-colors">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($factures)): ?>
  <div class="text-center py-10 text-slate-400"><p>Aucune facture.</p></div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">N°</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Élève</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Montant</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Statut</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Échéance</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($factures as $f): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars($f['numero'] ?? '') ?></td>
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($f['eleve_nom'] ?? '') ?></td>
          <td class="px-4 py-3 font-semibold text-slate-700"><?= number_format((float)($f['montant_total'] ?? 0), 0, ',', ' ') ?></td>
          <td class="px-4 py-3">
            <span class="text-xs px-2 py-0.5 rounded-full <?= ($f['statut'] ?? '') === 'payee' ? 'bg-green-100 text-green-700' : (($f['statut'] ?? '') === 'partielle' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>">
              <?= htmlspecialchars($f['statut'] ?? '') ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars($f['date_echeance'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
