<?php
/** @var array $document @var string|null $preview */
$titre = 'Aperçu — ' . htmlspecialchars($document['titre']);
$mime  = $document['mime_type'] ?? '';
$isImage = str_starts_with($mime, 'image/');
$isPdf   = $mime === 'application/pdf';
?>
<div class="p-6 space-y-4">

  <div class="flex items-center justify-between">
    <div>
      <a href="/v2/documents/<?= $document['id'] ?>" class="text-sm text-violet-600 hover:text-violet-800 font-medium">← Retour</a>
      <h1 class="text-xl font-bold text-slate-800 mt-1"><?= htmlspecialchars($document['titre']) ?></h1>
      <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($mime) ?> · v<?= $document['version_courante'] ?></p>
    </div>
    <a href="/v2/documents/<?= $document['id'] ?>/download"
       class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
      Télécharger
    </a>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <?php if ($isImage && $preview !== null): ?>
      <div class="flex items-center justify-center p-4 bg-slate-50">
        <img src="data:<?= htmlspecialchars($mime) ?>;base64,<?= base64_encode($preview) ?>"
             alt="<?= htmlspecialchars($document['titre']) ?>"
             class="max-w-full max-h-[70vh] object-contain rounded shadow">
      </div>
    <?php elseif ($isPdf): ?>
      <div class="h-[75vh]">
        <iframe src="/v2/documents/file/<?= urlencode(basename($document['chemin_stockage'])) ?>"
                class="w-full h-full border-0"
                title="<?= htmlspecialchars($document['titre']) ?>">
        </iframe>
      </div>
    <?php else: ?>
      <div class="flex flex-col items-center justify-center py-20 text-slate-400">
        <svg class="w-16 h-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm">Aperçu non disponible pour ce type de fichier.</p>
        <p class="text-xs mt-1"><?= htmlspecialchars($document['extension'] ?? '') ?></p>
      </div>
    <?php endif; ?>
  </div>

</div>
