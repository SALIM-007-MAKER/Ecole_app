<?php
/** @var array $conge, $refs, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function val(string $k, string $def = ''): string {
    global $old, $conge;
    return htmlspecialchars($old[$k] ?? $conge[$k] ?? $def, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier la demande — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-3xl">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
    <a href="/v2/rh/conges" class="hover:text-violet-600">Congés</a>
    <span>/</span>
    <a href="/v2/rh/conges/<?= (int)$conge['id'] ?>" class="hover:text-violet-600"><?= e($conge['type_libelle'] ?? '') ?></a>
    <span>/</span><span>Modifier</span>
  </div>
  <h1 class="text-2xl font-bold text-slate-900 mb-6">Modifier la demande</h1>

  <?php if (!empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>
  <?php if (!empty($errors) && empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
    <?php foreach ($errors as $k => $err): ?>
      <?php if ($k !== 'global'): ?><div><?= e(is_array($err) ? implode(', ', $err) : $err) ?></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/conges/<?= (int)$conge['id'] ?>" class="space-y-6">
    <?= \Core\Csrf::field() ?>

    <!-- Employé (lecture seule) -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Employé</h2>
      <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
        <div class="font-medium text-slate-800"><?= e($conge['employe_nom_complet'] ?? '—') ?></div>
        <div class="text-slate-400 text-sm"><?= e($conge['employe_matricule'] ?? '') ?></div>
      </div>
      <input type="hidden" name="employe_id" value="<?= (int)$conge['employe_id'] ?>">
      <input type="hidden" name="affectation_id" value="<?= (int)($conge['affectation_id'] ?? 0) ?>">
    </div>

    <!-- Type & Période -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Type & Période</h2>
      <div class="grid grid-cols-3 gap-4">
        <div class="col-span-3 md:col-span-1">
          <label class="block text-sm font-medium text-slate-700 mb-1">Type de congé *</label>
          <select name="type_conge_id" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <?php foreach ($refs['typesConges'] as $t): ?>
              <option value="<?= (int)$t['id'] ?>"
                      <?= (int)val('type_conge_id') === (int)$t['id'] ? 'selected' : '' ?>>
                <?= e($t['libelle']) ?><?= $t['duree_max_jours'] ? ' (max '.$t['duree_max_jours'].'j)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Date de début *</label>
          <input type="date" name="date_debut" required value="<?= val('date_debut') ?>" id="d-debut"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Date de fin *</label>
          <input type="date" name="date_fin" required value="<?= val('date_fin') ?>" id="d-fin"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
      </div>
      <div class="mt-3 p-3 bg-slate-50 rounded-lg text-xs text-slate-500">
        Durée actuelle : <strong><?= e($model::formatDuree((float)$conge['duree_jours'])) ?></strong>
        — sera recalculée à la sauvegarde
      </div>
    </div>

    <!-- Motif -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Motif</h2>
      <textarea name="motif" rows="3"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none"
                placeholder="Motif (optionnel)"><?= val('motif') ?></textarea>
    </div>

    <div class="flex gap-3">
      <button type="submit"
              class="px-6 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
        Enregistrer les modifications
      </button>
      <a href="/v2/rh/conges/<?= (int)$conge['id'] ?>"
         class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50 transition-colors">
        Annuler
      </a>
    </div>
  </form>
</main>
</body>
</html>
