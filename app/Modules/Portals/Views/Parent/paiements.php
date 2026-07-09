<?php /** @var array $factures */ ?>
<div class="space-y-4">
  <?php if (empty($factures)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 text-green-300"></i><p>Aucun paiement en attente.</p></div>
  <?php else: ?>
  <div class="space-y-3">
    <?php foreach ($factures as $f): ?>
    <div class="bg-white border <?= ($f['statut'] ?? '') === 'emise' ? 'border-red-200' : 'border-amber-200' ?> rounded-xl p-4 flex items-center justify-between shadow-sm">
      <div>
        <p class="font-medium text-slate-800"><?= htmlspecialchars($f['numero'] ?? '') ?></p>
        <p class="text-xs text-slate-400"><?= htmlspecialchars($f['enfant_nom'] ?? '') ?> — Échéance : <?= htmlspecialchars($f['date_echeance'] ?? '') ?></p>
      </div>
      <div class="text-right">
        <p class="font-bold text-red-600"><?= number_format((float)($f['reste'] ?? 0), 0, ',', ' ') ?> FCFA</p>
        <p class="text-xs text-slate-400">reste à payer</p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
