<?php $title = 'Ajouter un créneau'; ?>
<?php ob_start(); ?>

<?php $joursLabels = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi']; ?>

<div class="p-6 max-w-2xl mx-auto space-y-6">

  <div class="flex items-center gap-3">
    <a href="/v2/vie-scolaire/emplois-du-temps/<?= $edt['id'] ?>" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Ajouter un créneau</h1>
      <p class="text-slate-500 text-sm mt-1">
        <?= htmlspecialchars($edt['classe_nom']) ?> · <?= htmlspecialchars($edt['annee_scolaire']) ?>
      </p>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
      <?= $_SESSION['flash_error'] ?>
      <?php unset($_SESSION['flash_error']); ?>
    </div>
  <?php endif; ?>

  <!-- Alerte conflits -->
  <div id="conflitAlert" class="hidden bg-orange-50 border border-orange-200 text-orange-800 px-4 py-3 rounded-lg text-sm">
    <div class="flex items-center gap-2 font-medium mb-1">
      <i data-lucide="alert-triangle" class="w-4 h-4"></i> Conflit potentiel détecté
    </div>
    <p class="text-xs">Vérifiez la disponibilité de l'enseignant, de la salle et de la classe avant de soumettre.</p>
  </div>

  <form method="POST" action="/v2/vie-scolaire/emplois-du-temps/<?= $edt['id'] ?>/creneaux"
        class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Jour <span class="text-red-500">*</span></label>
        <select name="jour" required id="selJour"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="">— Jour —</option>
          <?php foreach ($joursLabels as $num => $lib): ?>
            <option value="<?= $num ?>"><?= $lib ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Plage horaire <span class="text-red-500">*</span></label>
        <select name="plage_id" required id="selPlage"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="">— Plage —</option>
          <?php foreach ($plages as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['libelle']) ?> (<?= $p['heure_debut'] ?>–<?= $p['heure_fin'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Matière <span class="text-red-500">*</span></label>
      <select name="matiere_id" required
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="">— Matière —</option>
        <?php foreach ($matieres as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Enseignant <span class="text-red-500">*</span></label>
      <select name="enseignant_id" required id="selEnseignant"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="">— Enseignant —</option>
        <?php foreach ($enseignants as $e): ?>
          <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Salle</label>
      <select name="salle_id" id="selSalle"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="">— Optionnelle —</option>
        <?php foreach ($salles as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nom'] . ' (' . $s['code'] . ')') ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Type de cours</label>
      <select name="type_cours" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="cours">Cours</option>
        <option value="td">TD</option>
        <option value="tp">TP</option>
        <option value="examen">Examen</option>
        <option value="sortie">Sortie</option>
        <option value="permanence">Permanence</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Note (optionnel)</label>
      <input type="text" name="note" maxlength="255"
             class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
             placeholder="Ex : Salle de réserve si labo occupé">
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
        Ajouter le créneau
      </button>
      <a href="/v2/vie-scolaire/emplois-du-temps/<?= $edt['id'] ?>"
         class="border border-slate-300 text-slate-600 hover:bg-slate-50 px-6 py-2 rounded-lg text-sm font-medium transition">
        Annuler
      </a>
    </div>
  </form>

</div>

<script>
  // Avertissement visuel si enseignant/salle/jour/plage changent
  const fields = ['selJour','selPlage','selEnseignant','selSalle'];
  const alert  = document.getElementById('conflitAlert');
  fields.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => {
      const jour  = document.getElementById('selJour').value;
      const plage = document.getElementById('selPlage').value;
      const ens   = document.getElementById('selEnseignant').value;
      if (jour && plage && ens) alert.classList.remove('hidden');
      else alert.classList.add('hidden');
    });
  });
</script>

<?php $content = ob_get_clean(); ?>
<?php include base_path('app/Views/layouts/app.php'); ?>
