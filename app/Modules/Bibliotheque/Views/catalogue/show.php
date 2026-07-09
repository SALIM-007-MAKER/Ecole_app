<?php /** @var array $ouvrage @var array $similaires @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? '') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-4xl mx-auto py-8 px-4">

  <div class="mb-4">
    <a href="/v2/bibliotheque/catalogue" class="text-sm text-violet-600 hover:underline">← Retour au catalogue</a>
  </div>

  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-start gap-6">
      <div class="w-24 h-32 bg-violet-100 rounded-lg flex items-center justify-center text-violet-600 text-4xl flex-shrink-0">📖</div>
      <div class="flex-1">
        <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($ouvrage['titre']) ?></h1>
        <?php if (!empty($ouvrage['sous_titre'])): ?>
          <p class="text-slate-500 mt-1"><?= htmlspecialchars($ouvrage['sous_titre']) ?></p>
        <?php endif ?>
        <?php if (!empty($ouvrage['auteurs'])): ?>
          <p class="text-sm text-slate-600 mt-2">
            Auteur(s) : <?= implode(', ', array_map(fn($a) => htmlspecialchars($a['prenom'] . ' ' . $a['nom']), $ouvrage['auteurs'])) ?>
          </p>
        <?php endif ?>
        <?php if (!empty($ouvrage['editeur_nom'])): ?>
          <p class="text-sm text-slate-500 mt-1">Éditeur : <?= htmlspecialchars($ouvrage['editeur_nom']) ?></p>
        <?php endif ?>
        <?php if (!empty($ouvrage['annee_edition'])): ?>
          <p class="text-sm text-slate-500">Année : <?= $ouvrage['annee_edition'] ?></p>
        <?php endif ?>
        <?php if (!empty($ouvrage['isbn'])): ?>
          <p class="text-sm text-slate-400 font-mono mt-1">ISBN : <?= htmlspecialchars($ouvrage['isbn']) ?></p>
        <?php endif ?>

        <div class="mt-4 flex items-center gap-3">
          <?php $dispo = (int)($ouvrage['exemplaires_disponibles'] ?? 0); ?>
          <span class="px-3 py-1 rounded-full text-sm font-medium <?= $dispo > 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' ?>">
            <?= $dispo > 0 ? $dispo . ' exemplaire(s) disponible(s)' : 'Aucun exemplaire disponible' ?>
          </span>
          <?php if ($dispo > 0): ?>
            <button onclick="emprunter()" class="bg-violet-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-violet-700">Emprunter</button>
          <?php else: ?>
            <button onclick="reserver()" class="bg-amber-500 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-amber-600">Réserver</button>
          <?php endif ?>
          <a href="/v2/bibliotheque/exemplaires/<?= $ouvrage['id'] ?>" class="text-sm text-violet-600 hover:underline">Voir exemplaires</a>
        </div>
      </div>
    </div>

    <?php if (!empty($ouvrage['resume'])): ?>
    <div class="mt-6 border-t pt-4">
      <h3 class="font-semibold text-slate-700 mb-2">Résumé</h3>
      <p class="text-slate-600 text-sm leading-relaxed"><?= nl2br(htmlspecialchars($ouvrage['resume'])) ?></p>
    </div>
    <?php endif ?>

    <?php if (!empty($ouvrage['categories'])): ?>
    <div class="mt-4 flex flex-wrap gap-2">
      <?php foreach ($ouvrage['categories'] as $cat): ?>
        <span class="text-xs bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full"><?= htmlspecialchars($cat['nom']) ?></span>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>

  <div class="flex gap-3 mb-4">
    <a href="/v2/bibliotheque/catalogue/<?= $ouvrage['id'] ?>/edit" class="text-sm bg-white border border-slate-200 px-3 py-1.5 rounded-lg hover:border-violet-400">Modifier</a>
    <button onclick="archiver()" class="text-sm bg-white border border-red-200 text-red-600 px-3 py-1.5 rounded-lg hover:border-red-400">Archiver</button>
  </div>

</div>
<script>
function emprunter() { window.location.href = '/v2/bibliotheque/emprunts/create?ouvrage_id=<?= $ouvrage['id'] ?>'; }
function reserver() {
  fetch('/v2/bibliotheque/reservations', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'ouvrage_id=<?= $ouvrage['id'] ?>&csrf_token=' + document.querySelector('meta[name=csrf]')?.content})
    .then(r=>r.json()).then(d=>alert(d.success ? 'Réservation créée' : d.error));
}
function archiver() {
  if (!confirm('Archiver cet ouvrage ?')) return;
  fetch('/v2/bibliotheque/catalogue/<?= $ouvrage['id'] ?>/archive', {method:'POST'}).then(()=>window.location.href='/v2/bibliotheque/catalogue');
}
</script>
</body>
</html>
