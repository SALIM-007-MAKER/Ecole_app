<?php
/** @var array $parents, $old, $errors */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function old(string $k, array $old, mixed $default = ''): string {
    return e((string)($old[$k] ?? $default));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau département — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 3) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-2xl">

  <!-- Fil d'ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
    <a href="/v2/rh/organisation" class="hover:text-violet-600">Organisation</a>
    <span>/</span>
    <a href="/v2/rh/organisation/departements" class="hover:text-violet-600">Départements</a>
    <span>/</span>
    <span class="text-slate-700">Nouveau</span>
  </div>

  <h1 class="text-2xl font-bold text-slate-900 mb-8">Nouveau département</h1>

  <?php if (!empty($errors['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
      <?= e($errors['global']) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/organisation/departements" class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
    <?php \Core\Csrf::field(); ?>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Nom *</label>
        <input type="text" name="nom" value="<?= old('nom', $old) ?>" required maxlength="100"
               placeholder="Ex : Corps Enseignant"
               class="w-full px-3 py-2 text-sm border <?= isset($errors['nom']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
        <?php if (isset($errors['nom'])): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($errors['nom']) ?></p>
        <?php endif; ?>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Code *</label>
        <input type="text" name="code" value="<?= old('code', $old) ?>" required
               placeholder="EX_CODE" pattern="[A-Z0-9_]{2,20}" maxlength="20"
               class="w-full px-3 py-2 text-sm font-mono uppercase border <?= isset($errors['code']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300"
               oninput="this.value=this.value.toUpperCase()">
        <p class="mt-1 text-xs text-slate-400">Majuscules uniquement, 2-20 caractères</p>
        <?php if (isset($errors['code'])): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($errors['code']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
      <textarea name="description" rows="3" placeholder="Description optionnelle du département..."
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300 resize-none"><?= old('description', $old) ?></textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Département parent</label>
        <select name="parent_id"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
          <option value="0">Aucun (racine)</option>
          <?php foreach ($parents as $p): ?>
            <option value="<?= (int)$p['id'] ?>"
                    <?= (($old['parent_id'] ?? 0) == $p['id']) ? 'selected' : '' ?>>
              <?= e($p['nom']) ?> (<?= e($p['code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Code centre de coût</label>
        <input type="text" name="budget_centre" value="<?= old('budget_centre', $old) ?>" maxlength="30"
               placeholder="Ex : CC-ADM-001"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
        <p class="mt-1 text-xs text-slate-400">Optionnel — pour intégration Finance V2</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Ordre d'affichage</label>
        <input type="number" name="ordre_affichage" value="<?= old('ordre_affichage', $old, '0') ?>"
               min="0" max="999"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
        <p class="mt-1 text-xs text-slate-400">0 = premier dans l'organigramme</p>
      </div>
      <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="actif" value="1"
                 <?= ($old['actif'] ?? '1') ? 'checked' : '' ?>
                 class="rounded text-violet-600">
          <span class="text-sm text-slate-700">Département actif</span>
        </label>
      </div>
    </div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="/v2/rh/organisation/departements"
         class="px-5 py-2.5 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Annuler</a>
      <button type="submit" class="px-5 py-2.5 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 font-medium">
        Créer le département
      </button>
    </div>
  </form>

</main>
</body>
</html>
