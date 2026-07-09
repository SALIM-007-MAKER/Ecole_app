<div class="space-y-4">
  <p class="text-sm text-slate-500">Résultats académiques et performances.</p>
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <?php foreach ([['Notes','/v2/academique/notes','edit-3'],['Bulletins','/v2/academique/bulletins','file-text'],['Évaluations','/v2/academique/evaluations','clipboard'],['Périodes','/v2/academique/periodes','calendar'],['Rapports','/v2/rapports','bar-chart-2']] as [$l,$u,$i]): ?>
    <a href="<?= $u ?>" class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-4 hover:border-indigo-400 transition-colors">
      <i data-lucide="<?= $i ?>" class="w-5 h-5 text-indigo-500"></i>
      <span class="text-sm font-medium text-slate-700"><?= $l ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
