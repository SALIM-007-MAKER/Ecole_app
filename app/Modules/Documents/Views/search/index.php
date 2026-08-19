<?php
/** @var array $results @var int $total @var \App\Modules\Documents\DTO\SearchDTO $dto */
$titre = 'Recherche de documents';
$qVal   = htmlspecialchars($_GET['q'] ?? '');
$modVal = htmlspecialchars($_GET['module_source'] ?? '');
$etVal  = htmlspecialchars($_GET['entite_type'] ?? '');
$dMin   = htmlspecialchars($_GET['date_emission_min'] ?? '');
$dMax   = htmlspecialchars($_GET['date_emission_max'] ?? '');
?>
<div class="p-6 space-y-6">

  <h1 class="text-2xl font-bold text-slate-800">Recherche avancée</h1>

  <form method="GET" class="bg-white border border-slate-200 rounded-xl p-5 space-y-4">
    <div class="flex gap-3">
      <input type="text" name="q" value="<?= $qVal ?>"
             placeholder="Recherche plein texte…"
             class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-violet-500 focus:border-violet-500"
             autofocus>
      <button type="submit" class="px-5 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium">Rechercher</button>
    </div>
    <div class="flex flex-wrap gap-3">
      <input type="text" name="module_source" value="<?= $modVal ?>"
             placeholder="Module source"
             class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-40">
      <input type="text" name="entite_type" value="<?= $etVal ?>"
             placeholder="Type entité"
             class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-40">
      <input type="date" name="date_emission_min" value="<?= $dMin ?>"
             class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
             title="Date émission min">
      <input type="date" name="date_emission_max" value="<?= $dMax ?>"
             class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
             title="Date émission max">
    </div>
  </form>

  <?php if (!empty($dto->query) || !empty($dto->moduleSource)): ?>
  <p class="text-sm text-slate-500"><?= $total ?> résultat(s) trouvé(s)</p>

  <div class="space-y-3">
    <?php foreach ($results as $doc): ?>
    <a href="<?= BASE_URL ?>/v2/documents/<?= $doc['id'] ?>"
       class="block bg-white border border-slate-200 rounded-xl p-4 hover:border-violet-300 hover:shadow-sm transition">
      <div class="flex items-start justify-between">
        <div>
          <div class="font-medium text-slate-800"><?= htmlspecialchars($doc['titre']) ?></div>
          <?php if (!empty($doc['description'])): ?>
          <p class="text-sm text-slate-500 mt-1 line-clamp-2"><?= htmlspecialchars(substr($doc['description'], 0, 150)) ?>…</p>
          <?php endif; ?>
          <div class="flex gap-3 mt-2 text-xs text-slate-400">
            <span><?= htmlspecialchars($doc['module_source']) ?></span>
            <span>v<?= $doc['version_courante'] ?></span>
            <span><?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
          </div>
        </div>
        <span class="text-xs text-slate-400 ml-4 shrink-0"><?= htmlspecialchars($doc['extension'] ?? '') ?></span>
      </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($results)): ?>
    <p class="text-center py-8 text-slate-400">Aucun document ne correspond à votre recherche.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
