<?php $title = 'Nouvelle activité'; ?>

<div class="p-6 max-w-3xl mx-auto space-y-6">

  <div class="flex items-center gap-3">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/activites"
       class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
      <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Nouvelle activité</h1>
      <p class="text-slate-500 text-sm mt-1">Clubs, événements, sorties, compétitions…</p>
    </div>
  </div>


  <!-- Alerte conflits EDT -->
  <div id="conflitEdtAlert" class="hidden bg-orange-50 border border-orange-200 text-orange-800 px-4 py-3 rounded-lg text-sm">
    <div class="flex items-center gap-2 font-medium">
      <i data-lucide="alert-triangle" class="w-4 h-4"></i>
      Conflit potentiel avec l'emploi du temps
    </div>
    <p class="text-xs mt-1">Des cours sont planifiés pour les classes ou responsables sélectionnés à ces horaires. Vous pouvez tout de même créer l'activité.</p>
  </div>

  <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/activites" class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Titre <span class="text-red-500">*</span></label>
        <input type="text" name="titre" value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie <span class="text-red-500">*</span></label>
        <select name="categorie_id" required
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
          <option value="">— Sélectionner —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= (($_POST['categorie_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Année scolaire <span class="text-red-500">*</span></label>
        <input type="text" name="annee_scolaire"
               value="<?= htmlspecialchars($_POST['annee_scolaire'] ?? (date('Y') . '-' . (date('Y')+1))) ?>"
               placeholder="2025-2026" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
        <input type="date" name="date_activite" id="dateActivite"
               value="<?= htmlspecialchars($_POST['date_activite'] ?? '') ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Lieu</label>
        <input type="text" name="lieu" value="<?= htmlspecialchars($_POST['lieu'] ?? '') ?>" maxlength="200"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure de début <span class="text-red-500">*</span></label>
        <input type="time" name="heure_debut" id="heureDebut"
               value="<?= htmlspecialchars($_POST['heure_debut'] ?? '') ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure de fin <span class="text-red-500">*</span></label>
        <input type="time" name="heure_fin" id="heureFin"
               value="<?= htmlspecialchars($_POST['heure_fin'] ?? '') ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Capacité maximale</label>
        <input type="number" name="capacite_max" min="1" max="9999"
               value="<?= htmlspecialchars($_POST['capacite_max'] ?? '30') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Organisateur</label>
        <select name="organisateur_id"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
          <option value="">— Optionnel —</option>
          <?php foreach ($enseignants as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
      <textarea name="description" rows="3"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"
                placeholder="Objectifs, programme, matériel requis…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Classes concernées</label>
      <div class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border border-slate-200 rounded-lg p-3" id="classesBox">
        <?php foreach ($classes as $cl): ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="classe_ids[]" value="<?= $cl['id'] ?>"
                   class="rounded text-violet-600"
                   <?= in_array($cl['id'], (array)($_POST['classe_ids'] ?? [])) ? 'checked' : '' ?>>
            <?= htmlspecialchars($cl['nom']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Responsables (enseignants)</label>
      <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border border-slate-200 rounded-lg p-3">
        <?php foreach ($enseignants as $e): ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="responsable_ids[]" value="<?= $e['id'] ?>"
                   class="rounded text-violet-600"
                   <?= in_array($e['id'], (array)($_POST['responsable_ids'] ?? [])) ? 'checked' : '' ?>>
            <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
        Créer l'activité
      </button>
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/activites"
         class="border border-slate-300 text-slate-600 hover:bg-slate-50 px-6 py-2 rounded-lg text-sm font-medium transition">
        Annuler
      </a>
    </div>
  </form>
</div>

<script>
  // Alerte conflit EDT — déclenchée si date+heures+classes/responsables tous renseignés
  ['dateActivite','heureDebut','heureFin'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', checkConflits);
  });
  document.querySelectorAll('#classesBox input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', checkConflits);
  });

  function checkConflits() {
    const date  = document.getElementById('dateActivite').value;
    const hdeb  = document.getElementById('heureDebut').value;
    const hfin  = document.getElementById('heureFin').value;
    const hasClasses = [...document.querySelectorAll('#classesBox input:checked')].length > 0;
    const alert = document.getElementById('conflitEdtAlert');
    if (date && hdeb && hfin && hasClasses) {
      alert.classList.remove('hidden');
    } else {
      alert.classList.add('hidden');
    }
  }
</script>

