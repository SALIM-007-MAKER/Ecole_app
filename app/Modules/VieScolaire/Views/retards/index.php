<?php
$titre = 'Retards — Vie Scolaire V2';
ob_start();
?>
<div class="max-w-7xl mx-auto px-4 py-6">

  <!-- En-tête -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Retards</h1>
      <p class="text-slate-500 text-sm mt-1">Gestion des retards élèves</p>
    </div>
    <div class="flex gap-2">
      <?php if ($policy->canExport($user)): ?>
      <a href="/v2/vie-scolaire/retards/export?<?= http_build_query($_GET) ?>"
         class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm">
        <i data-lucide="download" class="w-4 h-4"></i> Exporter CSV
      </a>
      <?php endif; ?>
      <a href="/v2/vie-scolaire/retards/statistiques"
         class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm">
        <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Statistiques
      </a>
      <?php if ($policy->canCreate($user)): ?>
      <a href="/v2/vie-scolaire/retards/create"
         class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 text-sm">
        <i data-lucide="plus" class="w-4 h-4"></i> Saisir un retard
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Flash messages -->
  <?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
    <?= htmlspecialchars($_SESSION['flash_success']) ?>
    <?php unset($_SESSION['flash_success']); ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
    <?= htmlspecialchars($_SESSION['flash_error']) ?>
    <?php unset($_SESSION['flash_error']); ?>
  </div>
  <?php endif; ?>

  <!-- Filtres -->
  <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <input type="text" name="annee_scolaire" placeholder="Année ex: 2024-2025"
             value="<?= htmlspecialchars($filters->anneeScolaire ?? '') ?>"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      <input type="date" name="date_debut"
             value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      <input type="date" name="date_fin"
             value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      <select name="statut" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
        <option value="">Tous les statuts</option>
        <option value="non_justifie" <?= $filters->statut === 'non_justifie' ? 'selected' : '' ?>>Non justifié</option>
        <option value="en_attente"   <?= $filters->statut === 'en_attente'   ? 'selected' : '' ?>>En attente</option>
        <option value="justifie"     <?= $filters->statut === 'justifie'     ? 'selected' : '' ?>>Justifié</option>
        <option value="refuse"       <?= $filters->statut === 'refuse'       ? 'selected' : '' ?>>Refusé</option>
      </select>
    </div>
    <div class="flex justify-end mt-3 gap-2">
      <a href="/v2/vie-scolaire/retards" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-800">Réinitialiser</a>
      <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Filtrer</button>
    </div>
  </form>

  <!-- Tableau -->
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <?php if (empty($retards)): ?>
    <div class="py-16 text-center text-slate-400">
      <i data-lucide="clock" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
      <p class="text-sm">Aucun retard trouvé.</p>
    </div>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Classe</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Date</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Heure arrivée</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Durée</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($retards as $r): ?>
        <?php
          $statutClass = match($r['statut']) {
              'justifie'     => 'bg-green-100 text-green-700',
              'en_attente'   => 'bg-amber-100 text-amber-700',
              'refuse'       => 'bg-red-100 text-red-700',
              default        => 'bg-slate-100 text-slate-600',
          };
          $statutLabel = match($r['statut']) {
              'non_justifie' => 'Non justifié',
              'en_attente'   => 'En attente',
              'justifie'     => 'Justifié',
              'refuse'       => 'Refusé',
              default        => $r['statut'],
          };
        ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800">
            <?= htmlspecialchars($r['eleve_prenom'] . ' ' . $r['eleve_nom']) ?>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($r['classe_nom']) ?></td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($r['date_retard']) ?></td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($r['heure_arrivee']) ?></td>
          <td class="px-4 py-3 text-slate-600"><?= (int)$r['duree_minutes'] ?> min</td>
          <td class="px-4 py-3">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statutClass ?>">
              <?= $statutLabel ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <a href="/v2/vie-scolaire/retards/<?= $r['id'] ?>"
               class="text-violet-600 hover:text-violet-800 text-sm font-medium">Voir</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($lastPage > 1): ?>
  <div class="flex items-center justify-between mt-4 text-sm text-slate-600">
    <span><?= $total ?> retard(s) au total</span>
    <div class="flex gap-1">
      <?php for ($p = 1; $p <= $lastPage; $p++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
         class="px-3 py-1.5 rounded-lg <?= $p === $page ? 'bg-violet-600 text-white' : 'border border-slate-300 hover:bg-slate-50' ?>">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../../Views/layouts/app.php';
