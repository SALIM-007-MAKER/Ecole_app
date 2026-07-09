<?php
/** @var array $document @var array $versions @var array $historique @var bool $canUpdate @var bool $canArchive @var bool $canTrash @var bool $canShare @var bool $canSign @var bool $canVersion */
$titre = htmlspecialchars($document['titre']);
?>
<div class="p-6 space-y-6">

  <!-- En-tête -->
  <div class="flex items-start justify-between">
    <div>
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800"><?= $titre ?></h1>
        <?php
        $badges = ['actif'=>'bg-green-100 text-green-700','archive'=>'bg-slate-100 text-slate-600','expire'=>'bg-red-100 text-red-700'];
        $badge  = $badges[$document['statut']] ?? 'bg-slate-100 text-slate-600';
        ?>
        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>">
          <?= ucfirst($document['statut']) ?>
        </span>
      </div>
      <p class="text-sm text-slate-500 mt-1">
        Module : <?= htmlspecialchars($document['module_source']) ?> &bull;
        Version <?= $document['version_courante'] ?> &bull;
        <?= htmlspecialchars($document['mime_type'] ?? '') ?>
      </p>
    </div>
    <div class="flex gap-2 flex-wrap justify-end">
      <a href="/v2/documents/<?= $document['id'] ?>/download"
         class="px-3 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm hover:bg-slate-50">
        Télécharger
      </a>
      <?php if ($canShare): ?>
      <a href="/v2/documents/<?= $document['id'] ?>/shares"
         class="px-3 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm hover:bg-slate-50">
        Partager
      </a>
      <?php endif; ?>
      <?php if ($canUpdate): ?>
      <a href="/v2/documents/<?= $document['id'] ?>/edit"
         class="px-3 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
        Modifier
      </a>
      <?php endif; ?>
      <?php if ($canArchive): ?>
      <button onclick="archiver(<?= $document['id'] ?>)"
              class="px-3 py-2 border border-amber-300 text-amber-700 rounded-lg text-sm hover:bg-amber-50">
        Archiver
      </button>
      <?php endif; ?>
      <?php if ($canTrash): ?>
      <button onclick="mettreCorbeille(<?= $document['id'] ?>)"
              class="px-3 py-2 border border-red-300 text-red-700 rounded-lg text-sm hover:bg-red-50">
        Corbeille
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Infos principales -->
    <div class="lg:col-span-2 space-y-4">
      <?php if (!empty($document['description'])): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Description</h2>
        <p class="text-sm text-slate-600"><?= nl2br(htmlspecialchars($document['description'])) ?></p>
      </div>
      <?php endif; ?>

      <!-- Aperçu -->
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Aperçu</h2>
        <a href="/v2/documents/<?= $document['id'] ?>/preview" target="_blank"
           class="block text-center py-12 bg-slate-50 rounded-lg border border-dashed border-slate-300 text-slate-400 hover:text-violet-600 hover:border-violet-300 transition">
          Ouvrir l'aperçu →
        </a>
      </div>

      <!-- Versions -->
      <?php if (!empty($versions)): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold text-slate-700">Versions (<?= count($versions) ?>)</h2>
          <?php if ($canVersion): ?>
          <button onclick="document.getElementById('uploadVersion').click()"
                  class="text-xs text-violet-600 hover:text-violet-800 font-medium">
            + Nouvelle version
          </button>
          <?php endif; ?>
        </div>
        <div class="space-y-2">
          <?php foreach ($versions as $v): ?>
          <div class="flex items-center justify-between text-sm py-1 border-b border-slate-100 last:border-0">
            <span class="font-medium text-slate-700">v<?= $v['numero_version'] ?></span>
            <span class="text-slate-400 text-xs"><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></span>
            <?php if (!empty($v['notes'])): ?>
            <span class="text-slate-500 text-xs italic"><?= htmlspecialchars($v['notes']) ?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Métadonnées -->
    <div class="space-y-4">
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Métadonnées</h2>
        <dl class="space-y-2 text-sm">
          <?php $meta = [
            'Référence'       => $document['reference_externe'] ?? '',
            'Émetteur'        => $document['emetteur'] ?? '',
            'Date émission'   => !empty($document['date_emission']) ? date('d/m/Y', strtotime($document['date_emission'])) : '',
            'Date expiration' => !empty($document['date_expiration']) ? date('d/m/Y', strtotime($document['date_expiration'])) : '',
            'Confidentialité' => ucfirst($document['confidentialite'] ?? ''),
          ];
          foreach ($meta as $label => $val): if (empty($val)) continue; ?>
          <div class="flex justify-between">
            <dt class="text-slate-500"><?= $label ?></dt>
            <dd class="text-slate-700 font-medium"><?= htmlspecialchars($val) ?></dd>
          </div>
          <?php endforeach; ?>
        </dl>
      </div>

      <!-- Historique -->
      <?php if (!empty($historique)): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Historique</h2>
        <div class="space-y-2">
          <?php foreach (array_slice($historique, 0, 5) as $h): ?>
          <div class="text-xs text-slate-500">
            <span class="font-medium text-slate-700"><?= ucfirst($h['action']) ?></span>
            · <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function archiver(id) {
  if (!confirm('Archiver ce document ?')) return;
  fetch(`/v2/documents/${id}/archive`, {method:'POST', headers:{'X-CSRF-Token':document.querySelector('meta[name=csrf-token]')?.content}})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); });
}
function mettreCorbeille(id) {
  if (!confirm('Mettre ce document à la corbeille ?')) return;
  fetch(`/v2/trash/${id}`, {method:'POST', headers:{'X-CSRF-Token':document.querySelector('meta[name=csrf-token]')?.content}})
    .then(r=>r.json()).then(d=>{ if(d.success) window.location='/v2/documents'; });
}
</script>
