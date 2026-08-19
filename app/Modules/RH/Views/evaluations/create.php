<?php
/** @var array $campagnes, $employes, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function val(string $k, string $def = ''): string { global $old; return htmlspecialchars($old[$k] ?? $def, ENT_QUOTES, 'UTF-8'); }
?>

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/evaluations" class="hover:text-violet-600">Évaluations</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Nouvelle</span>
  </div>
  <div class="flex items-start gap-4 mb-6">
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
      <i data-lucide="star" class="w-5 h-5 text-violet-600"></i>
    </div>
    <h1 class="text-2xl font-bold text-slate-900 pt-2">Créer une évaluation</h1>
  </div>

  <?php if (!empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>

  <?php if (empty($campagnes)): ?>
  <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 mb-6 text-sm">
    Aucune campagne active. <a href="<?= BASE_URL ?>/v2/rh/evaluations/campagnes/create" class="underline">Créer une campagne</a> d'abord.
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations" class="space-y-6">
    <?= \Core\Csrf::field() ?>

    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm space-y-4">
      <div>
        <label class="form-label">Campagne *</label>
        <select name="campagne_id" required
                class="form-select <?= isset($errors['campagne_id']) ? 'is-invalid' : '' ?>">
          <option value="">Sélectionner une campagne…</option>
          <?php foreach ($campagnes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= val('campagne_id') == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['libelle']) ?> (<?= (int)$c['annee'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['campagne_id'])): ?><p class="text-red-500 text-xs mt-1"><?= e($errors['campagne_id']) ?></p><?php endif; ?>
      </div>

      <div>
        <label class="form-label">Employé *</label>
        <select name="employe_id" required
                class="form-select <?= isset($errors['employe_id']) ? 'is-invalid' : '' ?>">
          <option value="">Sélectionner un employé…</option>
          <?php foreach ($employes as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= val('employe_id') == $emp['id'] ? 'selected' : '' ?>>
              <?= e($emp['nom_complet']) ?> — <?= e($emp['matricule'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['employe_id'])): ?><p class="text-red-500 text-xs mt-1"><?= e($errors['employe_id']) ?></p><?php endif; ?>
      </div>

      <div>
        <label class="form-label">Évaluateur (responsable)</label>
        <select name="evaluateur_id"
                class="form-select">
          <option value="">Non assigné</option>
          <?php foreach ($employes as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= val('evaluateur_id') == $emp['id'] ? 'selected' : '' ?>>
              <?= e($emp['nom_complet']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
        Créer l'évaluation
      </button>
      <a href="<?= BASE_URL ?>/v2/rh/evaluations" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
        Annuler
      </a>
    </div>
  </form>
