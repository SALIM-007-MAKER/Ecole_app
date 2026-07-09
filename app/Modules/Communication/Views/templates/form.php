<?php /** @var array|null $template */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Template') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-3xl mx-auto py-8 px-4">
  <div class="flex items-center gap-3 mb-6">
    <a href="/v2/communication/templates" class="text-slate-400 hover:text-slate-600">←</a>
    <h1 class="text-2xl font-bold text-slate-800"><?= isset($template) ? 'Modifier' : 'Créer' ?> un template</h1>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
    <form id="template-form" class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
          <input type="text" name="code" value="<?= htmlspecialchars($template['code'] ?? '') ?>" required
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
          <input type="text" name="nom" value="<?= htmlspecialchars($template['nom'] ?? '') ?>" required
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Canal</label>
          <select name="canal" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            <?php foreach (['internal','email','sms','push'] as $c): ?>
            <option value="<?= $c ?>" <?= ($template['canal'] ?? '') === $c ? 'selected' : '' ?>><?= strtoupper($c) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Module source</label>
          <input type="text" name="module_source" value="<?= htmlspecialchars($template['module_source'] ?? '') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="ex: academique">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Sujet (email)</label>
        <input type="text" name="sujet" value="<?= htmlspecialchars($template['sujet'] ?? '') ?>"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Corps texte (sms/push/interne)</label>
        <textarea name="corps_texte" rows="3"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono"><?= htmlspecialchars($template['corps_texte'] ?? '') ?></textarea>
        <p class="text-xs text-slate-400 mt-1">Variables : <code>{{'{{'}}nom{{'}}'}}</code>, <code>{{'{{'}}date{{'}}'}}</code>, etc.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Corps HTML (email uniquement)</label>
        <textarea name="corps_html" rows="8"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono"><?= htmlspecialchars($template['corps_html'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="w-full bg-violet-600 text-white py-2 rounded-lg font-medium hover:bg-violet-700">
        <?= isset($template) ? 'Enregistrer' : 'Créer' ?>
      </button>
    </form>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const templateId = <?= isset($template) ? $template['id'] : 'null' ?>;

document.getElementById('template-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const url = templateId ? `/v2/communication/templates/${templateId}` : '/v2/communication/templates';
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf },
    body: new URLSearchParams(new FormData(e.target))
  });
  const j = await r.json();
  if (j.success) window.location.href = '/v2/communication/templates';
  else alert('Erreur : ' + (j.errors || []).join(', '));
});
</script>
</body>
</html>
