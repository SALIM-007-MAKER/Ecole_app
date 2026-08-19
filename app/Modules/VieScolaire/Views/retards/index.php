<?php
$title = 'Retards';
?>
<div class="max-w-7xl mx-auto px-4 py-6">

  <!-- En-tête -->
  <div class="flex items-center justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="clock" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Retards</h1>
        <p class="text-slate-500 text-sm mt-0.5">Gestion des retards élèves</p>
      </div>
    </div>
    <div class="flex gap-2 flex-shrink-0">
      <?php if ($policy->canExport($user)): ?>
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/export?<?= http_build_query($_GET) ?>"
         class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors">
        <i data-lucide="download" class="w-4 h-4"></i> Exporter CSV
      </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/statistiques"
         class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors">
        <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Statistiques
      </a>
      <?php if ($policy->canCreate($user)): ?>
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/create"
         class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 text-sm font-medium transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i> Saisir un retard
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtres -->
  <form method="GET" class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <input type="text" name="annee_scolaire" placeholder="Année ex: 2024-2025"
             value="<?= htmlspecialchars($filters->anneeScolaire ?? '') ?>"
             class="form-input">
      <input type="date" name="date_debut"
             value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
             class="form-input">
      <input type="date" name="date_fin"
             value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
             class="form-input">
      <select name="statut" class="form-select">
        <option value="">Tous les statuts</option>
        <option value="non_justifie" <?= $filters->statut === 'non_justifie' ? 'selected' : '' ?>>Non justifié</option>
        <option value="en_attente"   <?= $filters->statut === 'en_attente'   ? 'selected' : '' ?>>En attente</option>
        <option value="justifie"     <?= $filters->statut === 'justifie'     ? 'selected' : '' ?>>Justifié</option>
        <option value="refuse"       <?= $filters->statut === 'refuse'       ? 'selected' : '' ?>>Refusé</option>
      </select>
    </div>
    <div class="flex justify-end mt-4 gap-4 pt-4 border-t border-slate-100">
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards" class="text-sm text-slate-500 hover:text-slate-700">Réinitialiser</a>
      <button type="submit"
              class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
        <i data-lucide="filter" class="w-4 h-4"></i> Filtrer
      </button>
    </div>
  </form>

  <!-- Tableau -->
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <?php if (empty($retards)): ?>
    <div class="py-16 text-center text-slate-400">
      <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3">
        <i data-lucide="clock" class="w-5 h-5"></i>
      </div>
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
        <tr class="hover:bg-slate-50 transition-colors">
          <td class="px-4 py-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                <?= htmlspecialchars(mb_strtoupper(mb_substr(trim($r['eleve_prenom']), 0, 1) . mb_substr(trim($r['eleve_nom']), 0, 1))) ?>
              </div>
              <span class="font-medium text-slate-800">
                <?= htmlspecialchars($r['eleve_prenom'] . ' ' . $r['eleve_nom']) ?>
              </span>
            </div>
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
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $r['id'] ?>"
               class="inline-flex items-center gap-1 text-violet-600 hover:text-violet-800 text-xs font-medium">
              <i data-lucide="eye" class="w-3.5 h-3.5"></i> Voir
            </a>
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
