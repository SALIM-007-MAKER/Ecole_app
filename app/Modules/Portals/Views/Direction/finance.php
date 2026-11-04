<div class="space-y-4">
  <p class="text-sm text-slate-500">Vue d'ensemble financière.</p>
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <?php foreach ([['Factures','/v2/finance/factures','file-minus'],['Paiements','/v2/finance/paiements','credit-card'],['Caisse','/v2/finance/caisse','dollar-sign'],['Impayés','/v2/finance/impayes','alert-triangle'],['Rapports','/v2/finance/rapports','bar-chart-2']] as [$l,$u,$i]): ?>
    <a href="<?= BASE_URL . $u ?>" class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-4 hover:border-indigo-400 transition-colors">
      <i data-lucide="<?= $i ?>" class="w-5 h-5 text-amber-500"></i>
      <span class="text-sm font-medium text-slate-700"><?= $l ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
