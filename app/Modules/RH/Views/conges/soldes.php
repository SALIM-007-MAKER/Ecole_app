<?php
/** @var array $soldes, $employes, $typesConges */
/** @var string $model */
/** @var int $annee */
/** @var int|null $employeId */
/** @var bool $canUpdate */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="<?= BASE_URL ?>/v2/rh/conges" class="hover:text-violet-600">Congés</a>
        <span>/</span><span>Soldes</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Soldes de congés</h1>
    </div>
    <a href="<?= BASE_URL ?>/v2/rh/conges" class="btn btn-secondary">← Retour</a>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-3 gap-6">
    <!-- Colonne gauche : formulaire initialisation solde -->
    <?php if ($canUpdate): ?>
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm col-span-1">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Définir un solde</h2>
      <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/soldes" class="space-y-4">
        <?= \Core\Csrf::field() ?>
        <div>
          <label class="form-label">Employé *</label>
          <select name="employe_id" required class="form-select">
            <option value="">Sélectionner…</option>
            <?php foreach ($employes as $emp): ?>
              <option value="<?= (int)$emp['id'] ?>" <?= $employeId === (int)$emp['id'] ? 'selected' : '' ?>>
                <?= e($emp['nom_complet']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Type de congé *</label>
          <select name="type_conge_id" required class="form-select">
            <option value="">Sélectionner…</option>
            <?php foreach ($typesConges as $t): ?>
              <?php if ($t['debit_solde']): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['libelle']) ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Année *</label>
          <input type="number" name="annee" value="<?= $annee ?>" min="2020" max="2099"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Solde initial (jours) *</label>
          <input type="number" name="solde_initial" value="0" min="0" max="365" step="0.5"
                 class="form-input">
        </div>
        <button type="submit" class="btn btn-primary w-full">
          Enregistrer le solde
        </button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Colonne droite : tableau des soldes -->
    <div class="col-span-<?= $canUpdate ? '2' : '3' ?>">
      <!-- Filtre année / employé -->
      <form method="GET" class="flex gap-3 mb-4">
        <select name="employe_id" class="form-select">
          <option value="">Tous les employés</option>
          <?php foreach ($employes as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= $employeId === (int)$emp['id'] ? 'selected' : '' ?>>
              <?= e($emp['nom_complet']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="number" name="annee" value="<?= $annee ?>" min="2020" max="2099"
               class="form-input w-24">
        <button type="submit" class="btn btn-primary">Filtrer</button>
      </form>

      <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
        <?php if (empty($soldes)): ?>
          <div class="text-center py-12 text-slate-400">
            <p>Aucun solde défini pour ce filtre.</p>
            <?php if ($canUpdate): ?>
            <p class="mt-2 text-sm">Utilisez le formulaire à gauche pour initialiser les soldes.</p>
            <?php endif; ?>
          </div>
        <?php else: ?>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="text-left px-5 py-3 font-medium text-slate-600">Employé</th>
              <th class="text-left px-4 py-3 font-medium text-slate-600">Type</th>
              <th class="text-right px-4 py-3 font-medium text-slate-600">Initial</th>
              <th class="text-right px-4 py-3 font-medium text-slate-600">Pris</th>
              <th class="text-right px-4 py-3 font-medium text-slate-600">En attente</th>
              <th class="text-right px-4 py-3 font-medium text-slate-600">Restant</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php foreach ($soldes as $s): ?>
            <?php
              $restant = $model::soldeRestant($s);
              $initial = (float)$s['solde_initial'];
              $colorClass = $model::soldeColor($restant, $initial);
            ?>
            <tr class="hover:bg-slate-50">
              <td class="px-5 py-3">
                <div class="font-medium text-slate-900"><?= e($s['employe_nom_complet']) ?></div>
                <div class="text-xs text-slate-400"><?= e($s['matricule'] ?? '') ?></div>
              </td>
              <td class="px-4 py-3 text-slate-700"><?= e($s['type_libelle']) ?></td>
              <td class="px-4 py-3 text-right text-slate-700"><?= number_format($initial, 1) ?>j</td>
              <td class="px-4 py-3 text-right text-slate-700"><?= number_format((float)$s['solde_pris'], 1) ?>j</td>
              <td class="px-4 py-3 text-right text-amber-600"><?= number_format((float)$s['solde_en_attente'], 1) ?>j</td>
              <td class="px-4 py-3 text-right font-bold <?= $colorClass ?>"><?= number_format($restant, 1) ?>j</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
