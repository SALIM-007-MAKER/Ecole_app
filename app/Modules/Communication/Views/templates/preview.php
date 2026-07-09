<?php /** @var array $template @var array $rendered */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Aperçu template</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-3xl mx-auto py-8 px-4">
  <div class="flex items-center gap-3 mb-6">
    <a href="/v2/communication/templates/<?= $template['id'] ?>" class="text-slate-400 hover:text-slate-600">←</a>
    <h1 class="text-2xl font-bold text-slate-800">Aperçu : <?= htmlspecialchars($template['nom']) ?></h1>
  </div>

  <?php if ($rendered['sujet']): ?>
  <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 mb-4">
    <p class="text-xs text-slate-500 mb-1">Sujet</p>
    <p class="font-medium text-slate-800"><?= htmlspecialchars($rendered['sujet']) ?></p>
  </div>
  <?php endif ?>

  <?php if ($rendered['corps_html']): ?>
  <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-100 mb-4">
    <p class="text-xs text-slate-500 mb-3">Rendu HTML</p>
    <div class="prose max-w-none"><?= $rendered['corps_html'] ?></div>
  </div>
  <?php endif ?>

  <?php if ($rendered['corps_texte']): ?>
  <div class="bg-slate-100 rounded-xl p-4 mb-4">
    <p class="text-xs text-slate-500 mb-1">Texte brut / SMS / Push</p>
    <pre class="text-sm text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars($rendered['corps_texte']) ?></pre>
  </div>
  <?php endif ?>
</div>
</body>
</html>
