<?php /** @var array $reservations @var string|null $statut @var string|null $mode @var int $page @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Réservations') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-5xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($titre ?? '') ?></h1>
    <div class="flex gap-2">
      <a href="/v2/bibliotheque/reservations" class="text-sm px-3 py-1.5 rounded-lg border <?= empty($mode) ? 'bg-violet-600 text-white' : 'bg-white border-slate-200 text-slate-600' ?>">Toutes</a>
      <a href="/v2/bibliotheque/reservations/mes" class="text-sm px-3 py-1.5 rounded-lg border <?= ($mode ?? '') === 'mes' ? 'bg-violet-600 text-white' : 'bg-white border-slate-200 text-slate-600' ?>">Mes réservations</a>
    </div>
  </div>

  <?php $liste = $reservations['data'] ?? $reservations; ?>
  <?php if (empty($liste)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <p class="text-4xl mb-3">📅</p>
      <p>Aucune réservation</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($liste as $r): ?>
      <?php $statusColors = ['en_attente'=>'amber','disponible'=>'green','confirmee'=>'blue','annulee'=>'slate','expiree'=>'red']; $c = $statusColors[$r['statut']] ?? 'slate'; ?>
      <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-semibold text-slate-800"><?= htmlspecialchars($r['ouvrage_titre'] ?? '—') ?></p>
            <p class="text-xs text-slate-500 mt-0.5">
              Demandé le <?= date('d/m/Y', strtotime($r['created_at'])) ?>
              <?php if (!empty($r['date_expiration'])): ?> · Expire le <?= date('d/m/Y H:i', strtotime($r['date_expiration'])) ?><?php endif ?>
              <?php if (!empty($r['position_file'])): ?> · Position #<?= $r['position_file'] ?><?php endif ?>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="px-2 py-0.5 rounded-full text-xs bg-<?= $c ?>-100 text-<?= $c ?>-700 capitalize">
              <?= str_replace('_', ' ', $r['statut']) ?>
            </span>
            <?php if ($r['statut'] === 'disponible'): ?>
              <button onclick="confirmer(<?= $r['id'] ?>)" class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700">Confirmer</button>
            <?php endif ?>
            <?php if (in_array($r['statut'], ['en_attente', 'disponible'])): ?>
              <button onclick="annuler(<?= $r['id'] ?>)" class="text-xs text-red-500 hover:underline">Annuler</button>
            <?php endif ?>
          </div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

</div>
<script>
function confirmer(id) {
  fetch('/v2/bibliotheque/reservations/' + id + '/confirmer', {method:'POST'}).then(()=>location.reload());
}
function annuler(id) {
  if (!confirm('Annuler cette réservation ?')) return;
  fetch('/v2/bibliotheque/reservations/' + id + '/annuler', {method:'POST', body:'raison=annulation_user'}).then(()=>location.reload());
}
</script>
</body>
</html>
