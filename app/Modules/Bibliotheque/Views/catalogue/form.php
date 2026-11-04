<?php /** @var array|null $ouvrage @var array $categories @var array $auteurs @var array $editeurs @var string $titre */ ?>
<div class="max-w-2xl mx-auto py-8 px-4">

  <div class="mb-4">
    <a href="<?= BASE_URL ?>/v2/bibliotheque/catalogue" class="text-sm text-violet-600 hover:underline">← Catalogue</a>
  </div>

  <div class="bg-white rounded-xl shadow-sm p-6">
    <h1 class="text-xl font-bold text-slate-800 mb-6"><?= htmlspecialchars($titre ?? '') ?></h1>

    <?php $action = BASE_URL . ($ouvrage ? '/v2/bibliotheque/catalogue/' . $ouvrage['id'] . '/update' : '/v2/bibliotheque/catalogue'); ?>
    <form method="POST" action="<?= $action ?>" class="space-y-4">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Titre <span class="text-red-500">*</span></label>
          <input type="text" name="titre" required value="<?= htmlspecialchars($ouvrage['titre'] ?? '') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        </div>

        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Sous-titre</label>
          <input type="text" name="sous_titre" value="<?= htmlspecialchars($ouvrage['sous_titre'] ?? '') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">ISBN</label>
          <input type="text" name="isbn" value="<?= htmlspecialchars($ouvrage['isbn'] ?? '') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-violet-400 focus:outline-none">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Année</label>
          <input type="number" name="annee_edition" min="1800" max="2099" value="<?= $ouvrage['annee_edition'] ?? '' ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Éditeur</label>
          <select name="editeur_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
            <option value="">— Sélectionner —</option>
            <?php foreach ($editeurs as $e): ?>
            <option value="<?= $e['id'] ?>" <?= ($ouvrage['editeur_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nom']) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Langue</label>
          <select name="langue" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
            <option value="fr" <?= ($ouvrage['langue'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
            <option value="ar" <?= ($ouvrage['langue'] ?? '') === 'ar' ? 'selected' : '' ?>>Arabe</option>
            <option value="en" <?= ($ouvrage['langue'] ?? '') === 'en' ? 'selected' : '' ?>>Anglais</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
          <select name="type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
            <option value="livre" <?= ($ouvrage['type'] ?? '') === 'livre' ? 'selected' : '' ?>>Livre</option>
            <option value="revue" <?= ($ouvrage['type'] ?? '') === 'revue' ? 'selected' : '' ?>>Revue</option>
            <option value="manuel" <?= ($ouvrage['type'] ?? '') === 'manuel' ? 'selected' : '' ?>>Manuel scolaire</option>
            <option value="dictionnaire" <?= ($ouvrage['type'] ?? '') === 'dictionnaire' ? 'selected' : '' ?>>Dictionnaire</option>
            <option value="autre" <?= ($ouvrage['type'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Cote</label>
          <input type="text" name="cote" value="<?= htmlspecialchars($ouvrage['cote'] ?? '') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-violet-400 focus:outline-none">
        </div>

        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Résumé</label>
          <textarea name="resume" rows="4"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none"><?= htmlspecialchars($ouvrage['resume'] ?? '') ?></textarea>
        </div>

        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Auteurs</label>
          <select name="auteur_ids[]" multiple class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm h-24 focus:ring-2 focus:ring-violet-400 focus:outline-none">
            <?php foreach ($auteurs as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Catégories</label>
          <div class="grid grid-cols-3 gap-2 border border-slate-200 rounded-lg p-3 max-h-32 overflow-y-auto">
            <?php foreach ($categories as $cat): ?>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" name="categorie_ids[]" value="<?= $cat['id'] ?>">
              <?= htmlspecialchars($cat['nom']) ?>
            </label>
            <?php endforeach ?>
          </div>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-violet-700">
          <?= $ouvrage ? 'Enregistrer' : 'Ajouter' ?>
        </button>
        <a href="<?= BASE_URL ?>/v2/bibliotheque/catalogue" class="text-sm text-slate-600 px-4 py-2 rounded-lg border hover:border-slate-400">Annuler</a>
      </div>
    </form>
  </div>

</div>
