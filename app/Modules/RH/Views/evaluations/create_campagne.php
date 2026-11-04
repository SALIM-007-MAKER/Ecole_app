<?php
/** @var array $criteres, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function val(string $k, string $def = ''): string { global $old; return htmlspecialchars($old[$k] ?? $def, ENT_QUOTES, 'UTF-8'); }
?>

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
    <a href="<?= BASE_URL ?>/v2/rh/evaluations" class="hover:text-violet-600">Évaluations</a>
    <span>/</span>
    <a href="<?= BASE_URL ?>/v2/rh/evaluations/campagnes" class="hover:text-violet-600">Campagnes</a>
    <span>/</span><span>Nouvelle</span>
  </div>
  <h1 class="text-2xl font-bold text-slate-900 mb-6">Créer une campagne d'évaluation</h1>

  <?php if (!empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/campagnes" class="space-y-6">
    <?= \Core\Csrf::field() ?>

    <!-- Identité -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Identité</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Code *</label>
          <input type="text" name="code" value="<?= val('code') ?>" required placeholder="EVAL-2026-S1"
                 class="form-input <?= isset($errors['code']) ? 'is-invalid' : '' ?>">
          <?php if (isset($errors['code'])): ?><p class="text-red-500 text-xs mt-1"><?= e($errors['code']) ?></p><?php endif; ?>
        </div>
        <div>
          <label class="form-label">Libellé *</label>
          <input type="text" name="libelle" value="<?= val('libelle') ?>" required
                 class="form-input <?= isset($errors['libelle']) ? 'is-invalid' : '' ?>">
        </div>
        <div>
          <label class="form-label">Année *</label>
          <input type="number" name="annee" value="<?= val('annee', date('Y')) ?>" min="2020" max="2099" required
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Période</label>
          <select name="periode" class="form-select">
            <?php foreach (['S1','S2','annuelle','trimestrielle','ad_hoc'] as $p): ?>
              <option value="<?= $p ?>" <?= val('periode','annuelle') === $p ? 'selected' : '' ?>>
                <?= $model::periodeLabel($p) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-span-2">
          <label class="form-label">Description</label>
          <textarea name="description" rows="2"
                    class="form-textarea"><?= val('description') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Dates -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Calendrier</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Date début *</label>
          <input type="date" name="date_debut" value="<?= val('date_debut') ?>" required
                 class="form-input <?= isset($errors['date_debut']) ? 'is-invalid' : '' ?>">
        </div>
        <div>
          <label class="form-label">Date fin *</label>
          <input type="date" name="date_fin" value="<?= val('date_fin') ?>" required
                 class="form-input <?= isset($errors['date_fin']) ? 'is-invalid' : '' ?>">
        </div>
        <div>
          <label class="form-label">Limite auto-évaluation</label>
          <input type="date" name="date_limite_auto_eval" value="<?= val('date_limite_auto_eval') ?>"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Limite évaluation responsable</label>
          <input type="date" name="date_limite_eval" value="<?= val('date_limite_eval') ?>"
                 class="form-input">
        </div>
      </div>
    </div>

    <!-- Critères -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-1">Critères d'évaluation</h2>
      <p class="text-xs text-slate-400 mb-4">Sélectionnez les critères qui s'appliquent à cette campagne.</p>
      <?php
        $byCategorie = [];
        foreach ($criteres as $cr) $byCategorie[$cr['categorie']][] = $cr;
        $catLabels = ['competence'=>'Compétences professionnelles','comportement'=>'Comportementaux','resultat'=>'Résultats','objectif'=>'Objectifs'];
        $typeLabels= ['tous'=>'Tous','enseignant'=>'Enseignants','administratif'=>'Administratifs','comptable'=>'Comptables','direction'=>'Direction'];
      ?>
      <?php foreach ($byCategorie as $cat => $crits): ?>
      <div class="mb-4">
        <h3 class="text-xs font-semibold text-violet-700 uppercase tracking-wide mb-2"><?= e($catLabels[$cat] ?? $cat) ?></h3>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach ($crits as $cr): ?>
          <label class="flex items-center gap-2 p-2 border border-slate-100 rounded-lg hover:bg-slate-50 cursor-pointer">
            <input type="checkbox" name="criteres[]" value="<?= (int)$cr['id'] ?>"
                   <?php $checked = $_POST['criteres'] ?? []; if (in_array((string)$cr['id'], (array)$checked)) echo 'checked'; ?>
                   class="text-violet-600 rounded">
            <span class="text-sm flex-1"><?= e($cr['libelle']) ?></span>
            <span class="text-xs text-slate-400">poids <?= number_format((float)$cr['poids'],1) ?></span>
            <?php if ($cr['type_employe'] !== 'tous'): ?>
              <span class="text-xs bg-violet-100 text-violet-600 px-1.5 py-0.5 rounded"><?= e($typeLabels[$cr['type_employe']] ?? $cr['type_employe']) ?></span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="btn btn-primary">
        Créer la campagne
      </button>
      <a href="<?= BASE_URL ?>/v2/rh/evaluations/campagnes" class="btn btn-secondary">
        Annuler
      </a>
    </div>
  </form>
