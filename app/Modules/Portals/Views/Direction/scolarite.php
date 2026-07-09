<div class="space-y-4">
  <p class="text-sm text-slate-500">Accès rapide aux données de scolarité.</p>
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <?php foreach ([['Élèves','users','/v2/scolarite/eleves'],['Classes','layout','/v2/scolarite/classes'],['Inscriptions','file-plus','/v2/scolarite/inscriptions'],['Familles','home','/v2/scolarite/familles'],['Matières','book','/v2/scolarite/matieres']] as [$l,$i,$u]): ?>
    <a href="<?= $u ?>" class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-4 hover:border-indigo-400 transition-colors">
      <i data-lucide="<?= $i ?>" class="w-5 h-5 text-indigo-500"></i>
      <span class="text-sm font-medium text-slate-700"><?= $l ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
