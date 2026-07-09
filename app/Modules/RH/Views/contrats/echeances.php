<?php
/** @var array $contrats */
/** @var int $jours */
/** @var string $model */
/** @var bool $canRenew */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Échéances contrats — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8">

  <div class="flex items-start justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="/v2/rh/contrats" class="hover:text-violet-600">Contrats</a>
        <span>/</span><span>Échéances</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Alertes d'échéance</h1>
      <p class="text-sm text-slate-500 mt-1"><?= count($contrats) ?> contrat(s) expirant dans les <?= (int)$jours ?> prochains jours.</p>
    </div>
    <div class="flex gap-3">
      <!-- Filtre horizon -->
      <form method="GET" class="flex items-center gap-2">
        <label class="text-sm text-slate-500">Horizon :</label>
        <?php foreach ([30, 60, 90, 180] as $j): ?>
          <a href="?jours=<?= $j ?>"
             class="px-3 py-1.5 text-xs rounded-lg <?= $jours === $j ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
            <?= $j ?>j
          </a>
        <?php endforeach; ?>
      </form>
      <a href="/v2/rh/contrats" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">← Retour</a>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <?php if (empty($contrats)): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
      <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <p>Aucun contrat n'arrive à échéance dans les <?= (int)$jours ?> prochains jours.</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($contrats as $c):
        $j = (int)$c['jours_restants'];
        if ($j <= 30)      { $bgClass = 'bg-red-50 border-red-200';    $textClass = 'text-red-700';    $badgeClass = 'bg-red-100 text-red-700'; }
        elseif ($j <= 60)  { $bgClass = 'bg-amber-50 border-amber-200'; $textClass = 'text-amber-700'; $badgeClass = 'bg-amber-100 text-amber-700'; }
        else               { $bgClass = 'bg-yellow-50 border-yellow-100'; $textClass = 'text-yellow-700'; $badgeClass = 'bg-yellow-100 text-yellow-700'; }
      ?>
        <div class="<?= $bgClass ?> border rounded-xl p-5 flex items-center gap-6">

          <!-- Compte à rebours -->
          <div class="flex-shrink-0 text-center w-20">
            <span class="text-3xl font-black <?= $textClass ?>"><?= $j ?></span>
            <span class="block text-xs <?= $textClass ?>">jours</span>
          </div>

          <!-- Infos -->
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-1">
              <a href="/v2/rh/contrats/<?= (int)$c['id'] ?>"
                 class="font-mono font-semibold text-violet-700 hover:underline text-sm"><?= e($c['numero_contrat']) ?></a>
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $model::typeColor($c['type']) ?>"><?= e($model::typeLabel($c['type'])) ?></span>
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $badgeClass ?>">J-<?= $j ?></span>
            </div>
            <p class="font-medium text-slate-800"><?= e($c['employe_nom']) ?> <span class="text-slate-400 text-xs"><?= e($c['employe_matricule']) ?></span></p>
            <div class="flex gap-4 text-xs text-slate-500 mt-1">
              <?php if ($c['poste_intitule']): ?><span><?= e($c['poste_intitule']) ?></span><?php endif; ?>
              <?php if ($c['departement_nom']): ?><span>— <?= e($c['departement_nom']) ?></span><?php endif; ?>
              <span>Fin le <strong><?= e(date('d/m/Y', strtotime($c['date_fin']))) ?></strong></span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-2 flex-shrink-0">
            <a href="/v2/rh/contrats/<?= (int)$c['id'] ?>"
               class="px-3 py-1.5 text-xs text-violet-600 bg-white border border-violet-200 rounded-lg hover:bg-violet-50 text-center">
              Voir le contrat
            </a>
            <?php if ($canRenew): ?>
              <button onclick="openRenewModal(<?= (int)$c['id'] ?>, '<?= e($c['numero_contrat']) ?>')"
                      class="px-3 py-1.5 text-xs text-white bg-violet-600 rounded-lg hover:bg-violet-700 text-center">
                Renouveler
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<!-- Modal Renouvellement rapide -->
<div id="modal-renew" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-md shadow-xl p-6">
    <h2 class="text-lg font-bold text-violet-700 mb-1">Renouveler</h2>
    <p id="renew-label" class="text-sm text-slate-500 mb-4"></p>
    <form id="renew-form" method="POST">
      <?= \Core\Csrf::field() ?>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nouvelle date de début *</label>
          <input type="date" name="nouvelle_date_debut" required value="<?= e(date('Y-m-d')) ?>"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nouvelle date de fin <span class="text-slate-400">(vide = CDI)</span></label>
          <input type="date" name="nouvelle_date_fin"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-renew').classList.add('hidden')"
                class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Renouveler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRenewModal(id, numero) {
    document.getElementById('renew-form').action = '/v2/rh/contrats/' + id + '/renouveler';
    document.getElementById('renew-label').textContent = 'Contrat : ' + numero;
    document.getElementById('modal-renew').classList.remove('hidden');
}
</script>
</body>
</html>
