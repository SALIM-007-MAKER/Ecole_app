<div class="space-y-4">
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <?php foreach ([['Employés','/v2/rh/employes','users'],['Contrats','/v2/rh/contrats','file-text'],['Congés','/v2/rh/conges','umbrella'],['Formations','/v2/rh/formations','book-open'],['Évaluations','/v2/rh/evaluations','clipboard']] as [$l,$u,$i]): ?>
    <a href="<?= BASE_URL . $u ?>" class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-4 hover:border-indigo-400 transition-colors">
      <i data-lucide="<?= $i ?>" class="w-5 h-5 text-rose-500"></i>
      <span class="text-sm font-medium text-slate-700"><?= $l ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
