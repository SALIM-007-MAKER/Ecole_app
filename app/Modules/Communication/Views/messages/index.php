<?php /** @var array $threads @var int $page @var int $unread */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Messagerie') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-3xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Messagerie</h1>
    <a href="/v2/messages/create" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">
      + Nouveau message
    </a>
  </div>

  <?php if (empty($threads)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <p>Aucun message</p>
    </div>
  <?php else: ?>
    <div class="space-y-2">
      <?php foreach ($threads as $t): ?>
      <?php
        $isNew = ($t['participant_lu_at'] === null || $t['participant_lu_at'] < ($t['dernier_message_at'] ?? ''));
      ?>
      <a href="/v2/messages/<?= $t['id'] ?>"
         class="block bg-white rounded-xl p-4 shadow-sm border hover:border-violet-300 transition <?= $isNew ? 'border-violet-200' : 'border-slate-100' ?>">
        <div class="flex justify-between items-start">
          <div class="flex-1 min-w-0">
            <p class="font-medium text-slate-800 text-sm truncate">
              <?= $isNew ? '<span class="w-2 h-2 bg-violet-500 rounded-full inline-block mr-1"></span>' : '' ?>
              <?= htmlspecialchars($t['sujet']) ?>
            </p>
            <p class="text-slate-400 text-xs mt-1">
              <?= htmlspecialchars(\App\Modules\Communication\Models\ThreadModel::nomAffichage($t, 0)) ?>
            </p>
          </div>
          <div class="text-xs text-slate-400 flex-shrink-0 ml-4">
            <?= $t['dernier_message_at'] ? date('d/m H:i', strtotime($t['dernier_message_at'])) : '' ?>
          </div>
        </div>
      </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
</body>
</html>
