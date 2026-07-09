<?php /** @var array $emprunt @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Emprunt') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-2xl mx-auto py-8 px-4">

  <div class="mb-4">
    <a href="/v2/bibliotheque/emprunts" class="text-sm text-violet-600 hover:underline">← Emprunts</a>
  </div>

  <div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex items-start justify-between mb-6">
      <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($titre ?? '') ?></h1>
      <?php $retard = $emprunt['statut'] === 'en_retard'; ?>
      <span class="px-3 py-1 rounded-full text-sm <?= match($emprunt['statut']) {
        'en_cours' => 'bg-blue-100 text-blue-700',
        'en_retard' => 'bg-red-100 text-red-700',
        'retourne' => 'bg-green-100 text-green-700',
        'perdu' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-100 text-slate-600'
      } ?>">
        <?= ucfirst(str_replace('_', ' ', $emprunt['statut'] ?? '')) ?>
      </span>
    </div>

    <dl class="grid grid-cols-2 gap-4 text-sm">
      <div>
        <dt class="text-slate-500 text-xs uppercase tracking-wide">Emprunteur</dt>
        <dd class="font-medium text-slate-800 mt-0.5"><?= htmlspecialchars(($emprunt['emprunteur_prenom'] ?? '') . ' ' . ($emprunt['emprunteur_nom'] ?? '')) ?></dd>
      </div>
      <div>
        <dt class="text-slate-500 text-xs uppercase tracking-wide">Ouvrage</dt>
        <dd class="font-medium text-slate-800 mt-0.5"><?= htmlspecialchars($emprunt['ouvrage_titre'] ?? '—') ?></dd>
      </div>
      <div>
        <dt class="text-slate-500 text-xs uppercase tracking-wide">N° inventaire</dt>
        <dd class="font-mono text-slate-800 mt-0.5"><?= htmlspecialchars($emprunt['numero_inventaire'] ?? '—') ?></dd>
      </div>
      <div>
        <dt class="text-slate-500 text-xs uppercase tracking-wide">Date emprunt</dt>
        <dd class="text-slate-800 mt-0.5"><?= date('d/m/Y', strtotime($emprunt['date_emprunt'])) ?></dd>
      </div>
      <div>
        <dt class="text-slate-500 text-xs uppercase tracking-wide">Retour prévu</dt>
        <dd class="text-slate-800 mt-0.5 <?= $retard ? 'text-red-600 font-semibold' : '' ?>"><?= date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) ?></dd>
      </div>
      <?php if (!empty($emprunt['date_retour_effectif'])): ?>
      <div>
        <dt class="text-slate-500 text-xs uppercase tracking-wide">Retour effectif</dt>
        <dd class="text-green-700 mt-0.5"><?= date('d/m/Y', strtotime($emprunt['date_retour_effectif'])) ?></dd>
      </div>
      <?php endif ?>
      <div>
        <dt class="text-slate-500 text-xs uppercase tracking-wide">Prolongations</dt>
        <dd class="text-slate-800 mt-0.5"><?= (int)($emprunt['prolongations'] ?? 0) ?> / 2</dd>
      </div>
    </dl>

    <?php if (!empty($emprunt['notes'])): ?>
    <div class="mt-4 border-t pt-4">
      <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Notes</p>
      <p class="text-sm text-slate-700"><?= nl2br(htmlspecialchars($emprunt['notes'])) ?></p>
    </div>
    <?php endif ?>

    <?php if (in_array($emprunt['statut'] ?? '', ['en_cours', 'en_retard'], true)): ?>
    <div class="mt-6 flex gap-3 pt-4 border-t">
      <button onclick="retour()" class="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700">Enregistrer le retour</button>
      <button onclick="prolonger()" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">Prolonger</button>
      <button onclick="declarerPerdu()" class="bg-red-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-red-700">Déclarer perdu</button>
    </div>
    <?php endif ?>
  </div>

</div>
<script>
const id = <?= (int)$emprunt['id'] ?>;
function retour() {
  if (!confirm('Confirmer le retour ?')) return;
  fetch('/v2/bibliotheque/emprunts/' + id + '/retour', {method:'POST'}).then(()=>location.reload());
}
function prolonger() {
  fetch('/v2/bibliotheque/emprunts/' + id + '/prolonger', {method:'POST'})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert(d.error); });
}
function declarerPerdu() {
  if (!confirm('Déclarer cet exemplaire perdu ? Une pénalité sera créée.')) return;
  fetch('/v2/bibliotheque/emprunts/' + id + '/perdu', {method:'POST'}).then(()=>location.reload());
}
</script>
</body>
</html>
