<?php
/** @var array $postes, $pagination, $filters, $departements, $categories, $niveaux, $canCreate, $canUpdate, $canArchive, $canExport */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$categorieColors = [
    'direction'     => 'bg-purple-100 text-purple-800',
    'enseignant'    => 'bg-blue-100 text-blue-800',
    'administratif' => 'bg-slate-100 text-slate-700',
    'support'       => 'bg-amber-100 text-amber-800',
    'technique'     => 'bg-green-100 text-green-800',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Postes — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 3) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8">

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="/v2/rh/organisation" class="hover:text-violet-600">Organisation</a>
        <span>/</span>
        <span>Postes</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Postes & Fonctions</h1>
    </div>
    <div class="flex gap-3">
      <?php if ($canExport): ?>
        <a href="/v2/rh/organisation/export/postes"
           class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm hover:bg-slate-50">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          CSV
        </a>
      <?php endif; ?>
      <a href="/v2/rh/organisation/fonctions"
         class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm hover:bg-slate-50">
        Fonctions
      </a>
      <?php if ($canCreate): ?>
        <a href="/v2/rh/organisation/postes/create"
           class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nouveau poste
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <!-- Filtres -->
  <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
      <input type="text" name="q" value="<?= e($filters->q) ?>" placeholder="Intitulé ou code..."
             class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
    </div>
    <div class="min-w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie</label>
      <select name="categorie"
              class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
        <option value="">Toutes</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= e($cat) ?>" <?= $filters->categorie === $cat ? 'selected' : '' ?>>
            <?= e(ucfirst($cat)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="min-w-48">
      <label class="block text-xs font-medium text-slate-600 mb-1">Département</label>
      <select name="departement_id"
              class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
        <option value="0">Tous</option>
        <?php foreach ($departements as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= $filters->departementId === (int)$d['id'] ? 'selected' : '' ?>>
            <?= e($d['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
      <input type="checkbox" name="archive" value="1" <?= $filters->includeArch ? 'checked' : '' ?> class="rounded text-violet-600">
      Inclure archivés
    </label>
    <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm rounded-lg hover:bg-violet-700">Filtrer</button>
    <a href="/v2/rh/organisation/postes" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm rounded-lg hover:bg-slate-200">Réinitialiser</a>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Intitulé</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Code</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Catégorie</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Niveau</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Département</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Occupants</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Statut</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-600">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($postes)): ?>
          <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucun poste trouvé.</td></tr>
        <?php endif; ?>
        <?php foreach ($postes as $p): ?>
          <?php $archived = $p['deleted_at'] !== null; ?>
          <tr class="hover:bg-slate-50 <?= $archived ? 'opacity-60' : '' ?>">
            <td class="px-4 py-3 font-medium text-slate-800"><?= e($p['intitule']) ?></td>
            <td class="px-4 py-3">
              <span class="font-mono text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded"><?= e($p['code']) ?></span>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $categorieColors[$p['categorie']] ?? 'bg-gray-100 text-gray-700' ?>">
                <?= e(ucfirst($p['categorie'])) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs"><?= e($niveaux[$p['niveau']] ?? 'N' . $p['niveau']) ?></td>
            <td class="px-4 py-3 text-slate-500"><?= $p['departement_nom'] ? e($p['departement_nom']) : '<span class="text-slate-300">—</span>' ?></td>
            <td class="px-4 py-3 text-center text-slate-700">
              <?= (int)$p['nb_occupants'] ?> / <?= (int)$p['nb_occupants_max'] ?>
              <?php if ((int)$p['nb_occupants'] >= (int)$p['nb_occupants_max']): ?>
                <span class="ml-1 text-amber-500" title="Poste complet">●</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-center">
              <?php if ($archived): ?>
                <span class="px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 rounded-full">Archivé</span>
              <?php elseif ($p['actif']): ?>
                <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full">Actif</span>
              <?php else: ?>
                <span class="px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 rounded-full">Inactif</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <?php if ($canUpdate && !$archived): ?>
                  <a href="/v2/rh/organisation/postes/<?= (int)$p['id'] ?>/edit"
                     class="px-2 py-1 text-xs text-violet-600 bg-violet-50 rounded hover:bg-violet-100">Éditer</a>
                <?php endif; ?>
                <?php if ($canArchive && !$archived): ?>
                  <form method="POST" action="/v2/rh/organisation/postes/<?= (int)$p['id'] ?>/archive"
                        onsubmit="return confirm('Archiver ce poste ?')">
                    <?php \Core\Csrf::field(); ?>
                    <button type="submit" class="px-2 py-1 text-xs text-amber-600 bg-amber-50 rounded hover:bg-amber-100">Archiver</button>
                  </form>
                <?php elseif ($canArchive && $archived): ?>
                  <form method="POST" action="/v2/rh/organisation/postes/<?= (int)$p['id'] ?>/restore">
                    <?php \Core\Csrf::field(); ?>
                    <button type="submit" class="px-2 py-1 text-xs text-emerald-600 bg-emerald-50 rounded hover:bg-emerald-100">Restaurer</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pagination['pages'] > 1): ?>
      <div class="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
        <span class="text-sm text-slate-500">Total : <?= $pagination['total'] ?> poste(s)</span>
        <div class="flex gap-1">
          <?php for ($pg = 1; $pg <= $pagination['pages']; $pg++): ?>
            <?php $q = http_build_query(array_merge($_GET, ['page' => $pg])); ?>
            <a href="?<?= $q ?>"
               class="px-3 py-1.5 text-sm rounded-lg <?= $pg === $pagination['page'] ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
              <?= $pg ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
