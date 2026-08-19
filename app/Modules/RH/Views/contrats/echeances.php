<?php
/** @var array $contrats */
/** @var int $jours */
/** @var string $model */
/** @var bool $canRenew */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$critiques = $attentions = $venir = 0;
foreach ($contrats as $c) {
    $jr = (int)$c['jours_restants'];
    if ($jr <= 30) $critiques++;
    elseif ($jr <= 60) $attentions++;
    else $venir++;
}
?>

  <!-- Fil d'ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="hover:text-violet-600">RH</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <a href="<?= BASE_URL ?>/v2/rh/contrats" class="hover:text-violet-600">Contrats</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <span class="text-slate-700">Échéances</span>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="clock" class="w-5 h-5 text-amber-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Alertes d'échéance</h1>
        <p class="text-sm text-slate-500 mt-0.5"><?= count($contrats) ?> contrat(s) expirant dans les <?= (int)$jours ?> prochains jours</p>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/v2/rh/contrats"
       class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors flex-shrink-0">
      <i data-lucide="arrow-left" class="w-4 h-4"></i>
      Retour aux contrats
    </a>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i><?= e($flash) ?>
    </div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i><?= e($flash) ?>
    </div>
  <?php endif; ?>

  <!-- Compteurs par urgence -->
  <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    <?php
    $cards = [
      ['Critique (≤ 30j)', $critiques,  'text-red-600',   'bg-red-50',   'bg-red-100',   'text-red-600',   'alert-triangle'],
      ['Attention (≤ 60j)', $attentions, 'text-amber-600', 'bg-amber-50', 'bg-amber-100', 'text-amber-600', 'clock'],
      ['À venir',           $venir,      'text-yellow-600','bg-yellow-50','bg-yellow-100','text-yellow-600','calendar'],
    ];
    ?>
    <?php foreach ($cards as [$label, $val, $textColor, $bg, $iconBg, $iconColor, $icon]): ?>
      <div class="<?= $bg ?> rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
        <div class="w-9 h-9 rounded-lg <?= $iconBg ?> flex items-center justify-center flex-shrink-0">
          <i data-lucide="<?= $icon ?>" class="w-4 h-4 <?= $iconColor ?>"></i>
        </div>
        <div class="flex-1">
          <p class="text-2xl font-bold <?= $textColor ?>"><?= $val ?></p>
          <p class="text-xs text-slate-500 mt-0.5"><?= $label ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Filtre horizon -->
  <div class="flex items-center gap-3 mb-6">
    <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Horizon</span>
    <form method="GET" class="flex items-center gap-2">
      <?php foreach ([30, 60, 90, 180] as $j): ?>
        <a href="?jours=<?= $j ?>"
           class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?= $jours === $j ? 'bg-violet-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
          <?= $j ?> jours
        </a>
      <?php endforeach; ?>
    </form>
  </div>

  <?php if (empty($contrats)): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-14 text-center">
      <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-3">
        <i data-lucide="check-circle-2" class="w-6 h-6 text-emerald-500"></i>
      </div>
      <p class="text-sm font-medium text-slate-600">Aucun contrat n'arrive à échéance</p>
      <p class="text-xs text-slate-400 mt-1">dans les <?= (int)$jours ?> prochains jours.</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($contrats as $c):
        $j = (int)$c['jours_restants'];
        if ($j <= 30)      { $bgClass = 'bg-red-50 border-red-200';      $textClass = 'text-red-700';    $badgeClass = 'bg-red-100 text-red-700';    $ringClass = 'ring-red-200'; }
        elseif ($j <= 60)  { $bgClass = 'bg-amber-50 border-amber-200';  $textClass = 'text-amber-700';  $badgeClass = 'bg-amber-100 text-amber-700'; $ringClass = 'ring-amber-200'; }
        else               { $bgClass = 'bg-yellow-50 border-yellow-100'; $textClass = 'text-yellow-700'; $badgeClass = 'bg-yellow-100 text-yellow-700'; $ringClass = 'ring-yellow-200'; }
        $nameParts = array_filter(explode(' ', trim($c['employe_nom']), 2));
        $initiales = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), $nameParts));
      ?>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex items-center gap-5 hover:shadow-md transition-shadow">

          <!-- Compte à rebours -->
          <div class="flex-shrink-0 text-center w-20 h-20 rounded-full <?= $bgClass ?> border flex flex-col items-center justify-center">
            <span class="text-2xl font-black <?= $textClass ?> leading-none"><?= $j ?></span>
            <span class="text-[10px] font-medium <?= $textClass ?> mt-0.5">jour<?= $j > 1 ? 's' : '' ?></span>
          </div>

          <!-- Avatar employé -->
          <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-sm shrink-0">
            <?= e($initiales) ?>
          </div>

          <!-- Infos -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$c['id'] ?>"
                 class="font-mono font-semibold text-violet-700 hover:underline text-sm"><?= e($c['numero_contrat']) ?></a>
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $model::typeColor($c['type']) ?>"><?= e($model::typeLabel($c['type'])) ?></span>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $badgeClass ?>">J-<?= $j ?></span>
            </div>
            <p class="font-medium text-slate-800 text-sm"><?= e($c['employe_nom']) ?> <span class="text-slate-400 text-xs font-normal"><?= e($c['employe_matricule']) ?></span></p>
            <div class="flex items-center gap-3 text-xs text-slate-500 mt-1.5 flex-wrap">
              <?php if ($c['poste_intitule']): ?>
                <span class="inline-flex items-center gap-1"><i data-lucide="briefcase" class="w-3 h-3"></i><?= e($c['poste_intitule']) ?></span>
              <?php endif; ?>
              <?php if ($c['departement_nom']): ?>
                <span class="inline-flex items-center gap-1"><i data-lucide="building-2" class="w-3 h-3"></i><?= e($c['departement_nom']) ?></span>
              <?php endif; ?>
              <span class="inline-flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i>Fin le <strong class="text-slate-600"><?= e(date('d/m/Y', strtotime($c['date_fin']))) ?></strong></span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-2 flex-shrink-0 w-36">
            <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$c['id'] ?>"
               class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-violet-600 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100 transition-colors">
              <i data-lucide="eye" class="w-3.5 h-3.5"></i> Voir le contrat
            </a>
            <?php if ($canRenew): ?>
              <button onclick="openRenewModal(<?= (int)$c['id'] ?>, '<?= e($c['numero_contrat']) ?>')"
                      class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition-colors">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Renouveler
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<!-- Modal Renouvellement rapide -->
<div id="modal-renew" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-md shadow-xl p-6">
    <div class="flex items-center gap-3 mb-1">
      <div class="w-9 h-9 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="refresh-cw" class="w-4 h-4 text-violet-600"></i>
      </div>
      <h2 class="text-lg font-bold text-slate-900">Renouveler le contrat</h2>
    </div>
    <p id="renew-label" class="text-sm text-slate-500 mb-4 ml-12"></p>
    <form id="renew-form" method="POST">
      <?= \Core\Csrf::field() ?>
      <div class="space-y-4">
        <div>
          <label class="form-label">Nouvelle date de début *</label>
          <input type="date" name="nouvelle_date_debut" required value="<?= e(date('Y-m-d')) ?>"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Nouvelle date de fin <span class="text-slate-400">(vide = CDI)</span></label>
          <input type="date" name="nouvelle_date_fin"
                 class="form-input">
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-renew').classList.add('hidden')"
                class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
          <i data-lucide="check" class="w-4 h-4"></i> Renouveler
        </button>
      </div>
    </form>
  </div>
</div>
<script>
function openRenewModal(id, numero) {
    document.getElementById('renew-form').action = '<?= BASE_URL ?>/v2/rh/contrats/' + id + '/renouveler';
    document.getElementById('renew-label').textContent = 'Contrat : ' + numero;
    document.getElementById('modal-renew').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}
if (window.lucide) lucide.createIcons();
</script>
