<?php
$exercices = $exercices ?? [];
$canGerer  = $canGerer  ?? false;
$user      = $user      ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$statutColors = [
    'ouvert'   => 'bg-emerald-100 text-emerald-700',
    'cloture'  => 'bg-slate-100 text-slate-600',
    'reouvert' => 'bg-amber-100 text-amber-700',
];
$statutLabels = ['ouvert'=>'Ouvert','cloture'=>'Clôturé','reouvert'=>'Réouvert'];
?>
<div class="p-6 space-y-6">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Exercices comptables</h1>
      <p class="text-slate-500 text-sm"><?= count($exercices) ?> exercice(s) — gestion des années fiscales</p>
    </div>
    <div class="flex gap-2">
      <?php if ($canGerer): ?>
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite/exercices/create"
         class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvel exercice
      </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite" class="text-sm text-slate-500 hover:text-slate-700">← Retour</a>
    </div>
  </div>

  <?php if (empty($exercices)): ?>
  <div class="bg-white rounded-xl border border-dashed border-slate-300 p-12 text-center">
    <i data-lucide="calendar-x" class="w-12 h-12 mx-auto mb-3 text-slate-300"></i>
    <p class="text-slate-500 font-medium mb-1">Aucun exercice comptable</p>
    <p class="text-slate-400 text-sm mb-4">Créez votre premier exercice pour commencer à enregistrer des écritures.</p>
    <?php if ($canGerer): ?>
    <a href="<?= BASE_URL ?>/v2/finance/comptabilite/exercices/create"
       class="inline-flex items-center gap-2 bg-violet-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
      <i data-lucide="plus" class="w-4 h-4"></i>Créer un exercice
    </a>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <div class="grid gap-4">
    <?php foreach ($exercices as $ex): ?>
    <?php
    $isCourant = in_array($ex->statut, ['ouvert', 'reouvert'], true);
    $nbOpen    = (int)($ex->nb_periodes_ouvertes ?? 0);
    $nbTotal   = (int)($ex->nb_periodes ?? 0);
    ?>
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden <?= $isCourant ? 'ring-2 ring-violet-200' : '' ?>">
      <div class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 <?= $isCourant ? 'bg-violet-600' : 'bg-slate-400' ?> rounded-xl flex items-center justify-center flex-shrink-0">
              <i data-lucide="calendar" class="w-5 h-5 text-white"></i>
            </div>
            <div>
              <div class="flex items-center gap-2 mb-1">
                <h3 class="font-bold text-slate-800"><?= htmlspecialchars($ex->libelle) ?></h3>
                <?php if ($isCourant): ?>
                <span class="text-xs bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full font-medium">Courant</span>
                <?php endif; ?>
                <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $statutColors[$ex->statut] ?? 'bg-slate-100' ?>">
                  <?= $statutLabels[$ex->statut] ?? $ex->statut ?>
                </span>
              </div>
              <p class="text-sm text-slate-500">
                <?= date('d/m/Y', strtotime($ex->date_debut)) ?> → <?= date('d/m/Y', strtotime($ex->date_fin)) ?>
              </p>
              <div class="flex items-center gap-4 mt-2 text-xs text-slate-500">
                <span><strong class="text-slate-700"><?= $nbTotal ?></strong> période(s)</span>
                <span><strong class="<?= $nbOpen > 0 ? 'text-emerald-700' : 'text-slate-700' ?>"><?= $nbOpen ?></strong> ouverte(s)</span>
                <span><strong class="text-slate-700"><?= (int)($ex->nb_ecritures ?? 0) ?></strong> écriture(s)</span>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2 sm:items-end">
            <a href="<?= BASE_URL ?>/v2/finance/comptabilite/exercices/<?= $ex->id ?>"
               class="inline-flex items-center gap-1.5 text-sm text-violet-600 hover:text-violet-800 font-medium">
              <i data-lucide="eye" class="w-4 h-4"></i>Détail &amp; périodes
            </a>

            <?php if ($canGerer && $isCourant && $nbOpen === 0): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/comptabilite/exercices/<?= $ex->id ?>/cloturer"
                  onsubmit="return confirm('Clôturer définitivement cet exercice ? Cette opération créera une écriture de clôture.')">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
              <button class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-800 font-medium">
                <i data-lucide="lock" class="w-4 h-4"></i>Clôturer
              </button>
            </form>
            <?php elseif ($canGerer && $isCourant && $nbOpen > 0): ?>
            <span class="text-xs text-amber-600">
              <i data-lucide="info" class="w-3 h-3 inline"></i>
              Clôturer d'abord les <?= $nbOpen ?> période(s) ouverte(s)
            </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Barre de progression des périodes -->
        <?php if ($nbTotal > 0): ?>
        <div class="mt-4 pt-4 border-t border-slate-100">
          <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
            <span>Avancement des périodes</span>
            <span><?= $nbTotal - $nbOpen ?>/<?= $nbTotal ?> clôturées</span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-1.5">
            <div class="bg-violet-500 h-1.5 rounded-full transition-all"
                 style="width: <?= $nbTotal > 0 ? round((($nbTotal - $nbOpen) / $nbTotal) * 100) : 0 ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
