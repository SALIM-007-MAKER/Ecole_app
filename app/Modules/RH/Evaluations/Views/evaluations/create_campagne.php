<?php
/** @var array $criteres, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function val(string $k, string $def = ''): string { global $old; return htmlspecialchars($old[$k] ?? $def, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouvelle campagne — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-4xl">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
    <a href="/v2/rh/evaluations" class="hover:text-violet-600">Évaluations</a>
    <span>/</span>
    <a href="/v2/rh/evaluations/campagnes" class="hover:text-violet-600">Campagnes</a>
    <span>/</span><span>Nouvelle</span>
  </div>
  <h1 class="text-2xl font-bold text-slate-900 mb-6">Créer une campagne d'évaluation</h1>

  <?php if (!empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/evaluations/campagnes" class="space-y-6">
    <?= \Core\Csrf::field() ?>

    <!-- Identité -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Identité</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Code *</label>
          <input type="text" name="code" value="<?= val('code') ?>" required placeholder="EVAL-2026-S1"
                 class="w-full border <?= isset($errors['code']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
          <?php if (isset($errors['code'])): ?><p class="text-red-500 text-xs mt-1"><?= e($errors['code']) ?></p><?php endif; ?>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Libellé *</label>
          <input type="text" name="libelle" value="<?= val('libelle') ?>" required
                 class="w-full border <?= isset($errors['libelle']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Année *</label>
          <input type="number" name="annee" value="<?= val('annee', date('Y')) ?>" min="2020" max="2099" required
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Période</label>
          <select name="periode" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <?php foreach (['S1','S2','annuelle','trimestrielle','ad_hoc'] as $p): ?>
              <option value="<?= $p ?>" <?= val('periode','annuelle') === $p ? 'selected' : '' ?>>
                <?= $model::periodeLabel($p) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
          <textarea name="description" rows="2"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none"><?= val('description') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Dates -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Calendrier</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Date début *</label>
          <input type="date" name="date_debut" value="<?= val('date_debut') ?>" required
                 class="w-full border <?= isset($errors['date_debut']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Date fin *</label>
          <input type="date" name="date_fin" value="<?= val('date_fin') ?>" required
                 class="w-full border <?= isset($errors['date_fin']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Limite auto-évaluation</label>
          <input type="date" name="date_limite_auto_eval" value="<?= val('date_limite_auto_eval') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Limite évaluation responsable</label>
          <input type="date" name="date_limite_eval" value="<?= val('date_limite_eval') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
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
      <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
        Créer la campagne
      </button>
      <a href="/v2/rh/evaluations/campagnes" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
        Annuler
      </a>
    </div>
  </form>
</main>
</body>
</html>
