<?php
/** @var array $documents @var array $pagination @var object $filters @var array $stats @var array $categories @var bool $canCreate @var bool $canAdmin */
$titre = 'Documents';
?>
<div class="p-6 space-y-6">

  <!-- En-tête -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Documents</h1>
      <p class="text-sm text-slate-500 mt-1">
        <?= number_format($stats['total'] ?? 0) ?> document(s) &bull;
        <?= $stats['total_taille_formatee'] ?? '0 o' ?>
      </p>
    </div>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>/v2/documents/search" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 text-sm">
        Recherche avancée
      </a>
      <?php if ($canCreate): ?>
      <a href="<?= BASE_URL ?>/v2/documents/create" class="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 text-sm font-medium">
        + Nouveau document
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtres -->
  <form method="GET" class="flex flex-wrap gap-3 bg-white border border-slate-200 rounded-xl p-4">
    <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>"
           placeholder="Rechercher…"
           class="flex-1 min-w-48 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-violet-500 focus:border-violet-500">
    <select name="categorie_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
      <option value="">Toutes catégories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $filters->categorieId == $cat['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['libelle']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="statut" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
      <option value="">Tous statuts</option>
      <option value="actif" <?= $filters->statut === 'actif' ? 'selected' : '' ?>>Actif</option>
      <option value="archive" <?= $filters->statut === 'archive' ? 'selected' : '' ?>>Archivé</option>
      <option value="expire" <?= $filters->statut === 'expire' ? 'selected' : '' ?>>Expiré</option>
    </select>
    <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Filtrer</button>
    <a href="<?= BASE_URL ?>/v2/documents" class="px-4 py-2 text-slate-500 hover:text-slate-700 text-sm">Réinitialiser</a>
  </form>

  <!-- Tableau -->
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Document</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Module</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Taille</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Date</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($documents as $doc): ?>
        <tr class="hover:bg-slate-50 transition-colors">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800"><?= htmlspecialchars($doc['titre']) ?></div>
            <?php if (!empty($doc['reference_externe'])): ?>
            <div class="text-xs text-slate-400"><?= htmlspecialchars($doc['reference_externe']) ?></div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($doc['module_source']) ?></td>
          <td class="px-4 py-3">
            <?php
            $badges = ['actif'=>'bg-green-100 text-green-700','archive'=>'bg-slate-100 text-slate-600','expire'=>'bg-red-100 text-red-700','corbeille'=>'bg-red-100 text-red-700'];
            $badge  = $badges[$doc['statut']] ?? 'bg-slate-100 text-slate-600';
            ?>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>">
              <?= ucfirst($doc['statut']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-600">
            <?php
            $o = (int)$doc['taille_octets'];
            echo $o >= 1073741824 ? round($o/1073741824,1).' Go'
               : ($o >= 1048576 ? round($o/1048576,1).' Mo'
               : ($o >= 1024 ? round($o/1024,1).' Ko' : $o.' o'));
            ?>
          </td>
          <td class="px-4 py-3 text-slate-500">
            <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="<?= BASE_URL ?>/v2/documents/<?= $doc['id'] ?>" class="text-violet-600 hover:text-violet-800 font-medium">Voir</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($documents)): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucun document trouvé.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
  <div class="flex justify-center gap-2">
    <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
      <a href="?page=<?= $p ?>"
         class="px-3 py-1 rounded <?= $p == $pagination['page'] ? 'bg-violet-600 text-white' : 'border border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>
