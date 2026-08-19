<?php
/** @var array $document @var array $partage */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($document['titre']) ?> — Document partagé</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm max-w-md w-full p-8 text-center">
    <div class="text-4xl mb-4">📄</div>
    <h1 class="text-xl font-bold text-slate-800 mb-1"><?= htmlspecialchars($document['titre']) ?></h1>
    <p class="text-sm text-slate-500 mb-4">
      <?= htmlspecialchars($document['module_source']) ?> · v<?= $document['version_courante'] ?>
    </p>

    <?php if (!empty($document['description'])): ?>
    <p class="text-sm text-slate-600 mb-6 text-left"><?= nl2br(htmlspecialchars($document['description'])) ?></p>
    <?php endif; ?>

    <div class="flex flex-col gap-3">
      <?php if (in_array($partage['permission'], ['telechargement', 'lecture'], true)): ?>
      <a href="<?= BASE_URL ?>/v2/documents/<?= $document['id'] ?>/download?token=<?= htmlspecialchars($partage['token_acces']) ?>"
         class="block px-5 py-3 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">
        Télécharger
      </a>
      <?php endif; ?>
      <?php if ($partage['permission'] === 'lecture'): ?>
      <a href="<?= BASE_URL ?>/v2/documents/<?= $document['id'] ?>/preview?token=<?= htmlspecialchars($partage['token_acces']) ?>"
         target="_blank"
         class="block px-5 py-3 border border-slate-300 text-slate-700 rounded-xl font-medium hover:bg-slate-50 transition">
        Aperçu
      </a>
      <?php endif; ?>
    </div>

    <?php if (!empty($partage['date_expiration'])): ?>
    <p class="text-xs text-slate-400 mt-6">
      Lien valide jusqu'au <?= date('d/m/Y', strtotime($partage['date_expiration'])) ?>
    </p>
    <?php endif; ?>
  </div>

</body>
</html>
