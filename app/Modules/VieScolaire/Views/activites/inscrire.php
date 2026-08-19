<?php $title = 'Inscrire un élève — ' . htmlspecialchars($activity['titre']); ?>

<div class="p-6 max-w-xl mx-auto space-y-6">

  <div class="flex items-center gap-3">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>"
       class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
      <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Inscrire un élève</h1>
      <p class="text-slate-500 text-sm mt-1"><?= htmlspecialchars($activity['titre']) ?></p>
    </div>
  </div>


  <!-- KPI capacité -->
  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center gap-6">
    <div class="text-center">
      <div class="text-2xl font-bold text-violet-700"><?= $activity['nb_inscrits'] ?></div>
      <div class="text-xs text-slate-500">Inscrits</div>
    </div>
    <div class="text-center">
      <div class="text-2xl font-bold text-slate-400"><?= $activity['capacite_max'] ?></div>
      <div class="text-xs text-slate-500">Capacité</div>
    </div>
    <?php if ($activity['nb_inscrits'] >= $activity['capacite_max']): ?>
    <div class="bg-amber-100 text-amber-700 text-xs px-3 py-1.5 rounded-lg font-medium">
      Capacité atteinte — l'élève sera en liste d'attente
    </div>
    <?php endif; ?>
  </div>

  <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>/inscrire"
        class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Élève <span class="text-red-500">*</span></label>
      <select name="eleve_id" required
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
        <option value="">— Sélectionner un élève —</option>
        <?php foreach ($eleves as $e): ?>
          <option value="<?= $e['id'] ?>">
            <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
            <?= $e['matricule'] ? '(' . htmlspecialchars($e['matricule']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Note (optionnel)</label>
      <textarea name="note" rows="2" maxlength="500"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"
                placeholder="Remarque particulière…"></textarea>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
        Inscrire
      </button>
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>"
         class="border border-slate-300 text-slate-600 hover:bg-slate-50 px-6 py-2 rounded-lg text-sm font-medium transition">
        Annuler
      </a>
    </div>
  </form>
</div>

