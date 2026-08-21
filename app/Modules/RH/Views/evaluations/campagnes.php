<?php
/** @var array $campagnes, $stats */
/** @var int $annee */
/** @var string $model */
/** @var \App\Modules\RH\Evaluations\Policies\EvaluationPolicy $policy */
/** @var array $user */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="<?= BASE_URL ?>/v2/rh/evaluations" class="hover:text-violet-600">Évaluations</a>
        <span>/</span><span>Campagnes</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Campagnes d'évaluation</h1>
    </div>
    <div class="flex gap-3">
      <a href="<?= BASE_URL ?>/v2/rh/evaluations" class="btn btn-secondary">
        Évaluations
      </a>
      <?php if ($policy->canCreate($user)): ?>
      <a href="<?= BASE_URL ?>/v2/rh/evaluations/campagnes/create" class="btn btn-primary">
        + Nouvelle campagne
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

  <!-- Filtre année -->
  <form method="GET" class="flex gap-3 mb-6">
    <input type="number" name="annee" value="<?= $annee ?>" min="2020" max="2099"
           class="form-input w-28">
    <select name="statut" class="form-select">
      <option value="">Tous statuts</option>
      <?php foreach ($model::CAMPAGNE_STATUTS as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= ($_GET['statut'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Filtrer</button>
  </form>

  <?php if (empty($campagnes)): ?>
  <div class="bg-white border border-slate-100 rounded-xl p-12 text-center text-slate-400 shadow-sm">
    Aucune campagne pour ce filtre.
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php foreach ($campagnes as $c): ?>
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-1">
            <span class="font-semibold text-slate-900"><?= e($c['libelle']) ?></span>
            <span class="text-xs font-mono text-slate-400"><?= e($c['code']) ?></span>
            <span class="px-2 py-0.5 rounded text-xs font-medium <?= $model::campagneStatutColor($c['statut']) ?>">
              <?= $model::campagneStatutLabel($c['statut']) ?>
            </span>
          </div>
          <div class="text-sm text-slate-500">
            <?= $model::periodeLabel($c['periode']) ?> <?= (int)$c['annee'] ?>
            · <?= e(date('d/m/Y', strtotime($c['date_debut']))) ?> → <?= e(date('d/m/Y', strtotime($c['date_fin']))) ?>
          </div>
          <div class="flex gap-6 mt-2 text-xs text-slate-400">
            <span><?= (int)$c['nb_evaluations'] ?> évaluation<?= (int)$c['nb_evaluations'] > 1 ? 's' : '' ?></span>
            <span class="text-emerald-600"><?= (int)$c['nb_publiees'] ?> publiée<?= (int)$c['nb_publiees'] > 1 ? 's' : '' ?></span>
            <?php if ($c['date_limite_eval']): ?>
              <span>Limite éval : <?= e(date('d/m/Y', strtotime($c['date_limite_eval']))) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex flex-col gap-2 flex-shrink-0">
          <?php if ($policy->canCreate($user) && $c['statut'] === 'brouillon'): ?>
          <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/campagnes/<?= (int)$c['id'] ?>/activer"
                onsubmit="return confirm('Activer cette campagne ?')">
            <?= \Core\Csrf::field() ?>
            <button type="submit" class="btn btn-success btn-sm w-full">Activer</button>
          </form>
          <?php endif; ?>
          <?php if ($policy->canValidate($user) && $c['statut'] === 'active'): ?>
          <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/campagnes/<?= (int)$c['id'] ?>/cloturer"
                onsubmit="return confirm('Clôturer cette campagne ?')">
            <?= \Core\Csrf::field() ?>
            <button type="submit" class="btn btn-warning btn-sm w-full">Clôturer</button>
          </form>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/v2/rh/evaluations?campagne_id=<?= (int)$c['id'] ?>" class="btn btn-secondary btn-sm w-full">
            Voir évaluations
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
