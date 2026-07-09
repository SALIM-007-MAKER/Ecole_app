<?php $title = 'Activités scolaires'; ?>
<?php ob_start(); ?>

<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Activités scolaires</h1>
      <p class="text-slate-500 text-sm mt-1">Clubs, événements, sorties, compétitions…</p>
    </div>
    <div class="flex gap-2">
      <?php if (in_array('activity.export', $user['permissions'] ?? [])): ?>
        <a href="/v2/vie-scolaire/activites/export<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>"
           class="inline-flex items-center gap-1 border border-slate-300 text-slate-600 hover:bg-slate-50 px-3 py-2 rounded-lg text-sm transition">
          <i data-lucide="download" class="w-4 h-4"></i> Export
        </a>
      <?php endif; ?>
      <?php if (in_array('activity.create', $user['permissions'] ?? [])): ?>
        <a href="/v2/vie-scolaire/activites/create"
           class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
          <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle activité
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
      <?= htmlspecialchars($_SESSION['flash_success']) ?>
      <?php unset($_SESSION['flash_success']); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
      <?= $_SESSION['flash_error'] ?>
      <?php unset($_SESSION['flash_error']); ?>
    </div>
  <?php endif; ?>

  <!-- Filtres -->
  <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-xs text-slate-500 mb-1">Catégorie</label>
      <select name="categorie_id" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Toutes</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= ($filters->categorieId == $cat['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Statut</label>
      <select name="statut" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Tous</option>
        <option value="brouillon"  <?= ($filters->statut === 'brouillon')  ? 'selected' : '' ?>>Brouillon</option>
        <option value="publie"     <?= ($filters->statut === 'publie')     ? 'selected' : '' ?>>Publié</option>
        <option value="en_cours"   <?= ($filters->statut === 'en_cours')   ? 'selected' : '' ?>>En cours</option>
        <option value="termine"    <?= ($filters->statut === 'termine')    ? 'selected' : '' ?>>Terminé</option>
        <option value="annule"     <?= ($filters->statut === 'annule')     ? 'selected' : '' ?>>Annulé</option>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Année scolaire</label>
      <select name="annee_scolaire" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Toutes</option>
        <?php foreach ($annees as $a): ?>
          <option value="<?= $a['annee_scolaire'] ?>" <?= ($filters->anneeScolaire === $a['annee_scolaire']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['annee_scolaire']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Du</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($filters->dateFrom ?? '') ?>"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Au</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($filters->dateTo ?? '') ?>"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm transition">
      Filtrer
    </button>
  </form>

  <!-- Grille d'activités -->
  <?php if (empty($data)): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
      <i data-lucide="calendar-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
      <p>Aucune activité trouvée.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($data as $act): ?>
        <?php
          $statutCls = match($act['statut']) {
            'publie'   => 'bg-green-100 text-green-700',
            'en_cours' => 'bg-blue-100 text-blue-700',
            'termine'  => 'bg-slate-100 text-slate-500',
            'annule'   => 'bg-red-100 text-red-700',
            default    => 'bg-yellow-100 text-yellow-700',
          };
          $statutLib = match($act['statut']) {
            'publie'  => 'Publié', 'en_cours' => 'En cours',
            'termine' => 'Terminé', 'annule' => 'Annulé', default => 'Brouillon',
          };
          $pct = $act['capacite_max'] > 0
            ? min(100, round($act['nb_inscrits'] / $act['capacite_max'] * 100))
            : 0;
        ?>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden hover:shadow-md transition">
          <div class="h-1.5" style="background-color: <?= htmlspecialchars($act['categorie_couleur']) ?>"></div>
          <div class="p-4 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <div>
                <span class="text-xs font-medium text-slate-400"><?= htmlspecialchars($act['categorie_nom']) ?></span>
                <h3 class="font-semibold text-slate-800 leading-tight"><?= htmlspecialchars($act['titre']) ?></h3>
              </div>
              <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statutCls ?>">
                <?= $statutLib ?>
              </span>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-500">
              <span class="flex items-center gap-1">
                <i data-lucide="calendar" class="w-3 h-3"></i>
                <?= date('d/m/Y', strtotime($act['date_activite'])) ?>
              </span>
              <span class="flex items-center gap-1">
                <i data-lucide="clock" class="w-3 h-3"></i>
                <?= htmlspecialchars($act['heure_debut']) ?> – <?= htmlspecialchars($act['heure_fin']) ?>
              </span>
            </div>
            <?php if ($act['lieu']): ?>
              <p class="text-xs text-slate-500 flex items-center gap-1">
                <i data-lucide="map-pin" class="w-3 h-3"></i>
                <?= htmlspecialchars($act['lieu']) ?>
              </p>
            <?php endif; ?>
            <!-- Jauge d'inscription -->
            <div>
              <div class="flex justify-between text-xs text-slate-500 mb-1">
                <span><?= $act['nb_inscrits'] ?> / <?= $act['capacite_max'] ?> inscrits</span>
                <span><?= $pct ?>%</span>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-1.5">
                <div class="h-1.5 rounded-full <?= $pct >= 100 ? 'bg-red-500' : 'bg-violet-500' ?>"
                     style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <a href="/v2/vie-scolaire/activites/<?= $act['id'] ?>"
               class="block w-full text-center text-sm font-medium text-violet-600 hover:text-violet-800 pt-1">
              Voir le détail →
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex justify-center gap-2 mt-4">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?page=<?= $p ?>&categorie_id=<?= $filters->categorieId ?>&statut=<?= $filters->statut ?>&annee_scolaire=<?= $filters->anneeScolaire ?>"
           class="px-3 py-1 rounded-lg text-sm border <?= ($p == $page) ? 'bg-violet-600 text-white border-violet-600' : 'border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php $content = ob_get_clean(); ?>
<?php include base_path('app/Views/layouts/app.php'); ?>
