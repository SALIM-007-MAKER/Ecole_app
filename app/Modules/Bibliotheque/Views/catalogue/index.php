<?php /** @var array $ouvrages @var object $filters @var array $categories @var array $auteurs @var string $titre */ ?>
<div class="max-w-6xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Catalogue</h1>
    <a href="<?= BASE_URL ?>/v2/bibliotheque/catalogue/create" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">
      + Ajouter un ouvrage
    </a>
  </div>

  <!-- Recherche -->
  <form method="GET" action="<?= BASE_URL ?>/v2/bibliotheque/catalogue" class="bg-white rounded-xl shadow-sm p-4 mb-6 flex gap-3">
    <input type="text" name="terme" value="<?= htmlspecialchars($_GET['terme'] ?? '') ?>"
           placeholder="Titre, auteur, ISBN..."
           class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
    <select name="categorie_id" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
      <option value="">Toutes catégories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= ($_GET['categorie_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['nom']) ?>
        </option>
      <?php endforeach ?>
    </select>
    <select name="disponible_seulement" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
      <option value="0">Tous</option>
      <option value="1" <?= ($_GET['disponible_seulement'] ?? '') === '1' ? 'selected' : '' ?>>Disponibles</option>
    </select>
    <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">Rechercher</button>
  </form>

  <!-- Résultats -->
  <?php $liste = $ouvrages['data'] ?? $ouvrages; ?>
  <?php if (empty($liste)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <p class="text-4xl mb-3">📚</p>
      <p>Aucun ouvrage trouvé</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($liste as $ouvrage): ?>
      <a href="<?= BASE_URL ?>/v2/bibliotheque/catalogue/<?= $ouvrage['id'] ?>"
         class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition border border-slate-100 hover:border-violet-200 block">
        <div class="flex items-start gap-3">
          <div class="w-12 h-16 bg-violet-100 rounded flex items-center justify-center text-violet-600 flex-shrink-0 text-xl">📖</div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-slate-800 text-sm leading-tight truncate"><?= htmlspecialchars($ouvrage['titre']) ?></p>
            <?php if (!empty($ouvrage['auteurs_noms'])): ?>
            <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($ouvrage['auteurs_noms']) ?></p>
            <?php endif ?>
            <?php if (!empty($ouvrage['isbn'])): ?>
            <p class="text-xs text-slate-400 mt-1">ISBN: <?= htmlspecialchars($ouvrage['isbn']) ?></p>
            <?php endif ?>
            <div class="mt-2 flex items-center gap-2">
              <?php $dispo = (int)($ouvrage['exemplaires_disponibles'] ?? 0); ?>
              <span class="text-xs px-2 py-0.5 rounded-full <?= $dispo > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                <?= $dispo > 0 ? $dispo . ' dispo.' : 'Indisponible' ?>
              </span>
              <span class="text-xs text-slate-400"><?= (int)($ouvrage['total_exemplaires'] ?? 0) ?> exemplaires</span>
            </div>
          </div>
        </div>
      </a>
      <?php endforeach ?>
    </div>
    <!-- Pagination -->
    <?php if (!empty($ouvrages['total']) && $ouvrages['total'] > count($liste)): ?>
    <div class="mt-6 flex justify-center gap-2">
      <?php $page = (int)($_GET['page'] ?? 1); ?>
      <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-1 rounded bg-white border text-sm">‹</a><?php endif ?>
      <span class="px-3 py-1 text-sm text-slate-600">Page <?= $page ?></span>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-1 rounded bg-white border text-sm">›</a>
    </div>
    <?php endif ?>
  <?php endif ?>

</div>
