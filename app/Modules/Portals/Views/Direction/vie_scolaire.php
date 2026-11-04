<div class="space-y-4">
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <?php foreach ([['Absences','/v2/vie-scolaire/absences','user-x'],['Présences','/v2/vie-scolaire/presences','check-circle'],['Emplois du temps','/v2/vie-scolaire/emplois-du-temps','calendar'],['Discipline','/v2/vie-scolaire/discipline','shield'],['Activités','/v2/vie-scolaire/activites','zap']] as [$l,$u,$i]): ?>
    <a href="<?= BASE_URL . $u ?>" class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-4 hover:border-indigo-400 transition-colors">
      <i data-lucide="<?= $i ?>" class="w-5 h-5 text-teal-500"></i>
      <span class="text-sm font-medium text-slate-700"><?= $l ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
