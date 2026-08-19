<?php
/** @var array $dept, $services, $historique, $canUpdate, $canArchive */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$title   = 'Département : ' . $dept['nom'];
$archived = $dept['deleted_at'] !== null;
?>

  <!-- Fil d'ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/organisation" class="hover:text-violet-600">Organisation</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="<?= BASE_URL ?>/v2/rh/organisation/departements" class="hover:text-violet-600">Départements</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700"><?= e($dept['nom']) ?></span>
  </div>

  <!-- En-tête -->
  <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="building-2" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2 flex-wrap">
          <?= e($dept['nom']) ?>
          <span class="font-mono text-xs bg-violet-100 text-violet-700 px-2 py-0.5 rounded"><?= e($dept['code']) ?></span>
          <?php if ($archived): ?>
            <span class="px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 rounded-full">Archivé</span>
          <?php elseif ($dept['actif']): ?>
            <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full">Actif</span>
          <?php endif; ?>
        </h1>
        <?php if ($dept['description']): ?>
          <p class="text-slate-500 text-sm mt-1"><?= e($dept['description']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="flex gap-2 flex-shrink-0">
      <?php if ($canUpdate && !$archived): ?>
        <a href="<?= BASE_URL ?>/v2/rh/organisation/departements/<?= (int)$dept['id'] ?>/edit"
           class="px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors">Modifier</a>
      <?php endif; ?>
      <?php if ($canArchive && !$archived): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/organisation/departements/<?= (int)$dept['id'] ?>/archive"
              onsubmit="return confirm('Archiver ce département ?')">
          <?php \Core\Csrf::field(); ?>
          <button type="submit" class="px-4 py-2 bg-amber-50 text-amber-700 border border-amber-200 text-sm rounded-lg hover:bg-amber-100 transition-colors">Archiver</button>
        </form>
      <?php elseif ($canArchive && $archived): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/organisation/departements/<?= (int)$dept['id'] ?>/restore">
          <?php \Core\Csrf::field(); ?>
          <button type="submit" class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm rounded-lg hover:bg-emerald-100 transition-colors">Restaurer</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <!-- Card principal -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <h2 class="flex items-center gap-2 font-semibold text-slate-700 mb-4">
      <i data-lucide="info" class="w-4 h-4 text-violet-500"></i> Informations générales
    </h2>
    <dl class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <div>
        <dt class="text-xs font-medium text-slate-500 uppercase tracking-wide">Responsable</dt>
        <dd class="text-sm text-slate-800 mt-1">
          <?= $dept['resp_nom'] ? e(($dept['resp_prenom'] ?? '') . ' ' . $dept['resp_nom']) : '<span class="text-slate-300">Non défini</span>' ?>
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium text-slate-500 uppercase tracking-wide">Département parent</dt>
        <dd class="text-sm text-slate-800 mt-1">
          <?= $dept['parent_nom'] ? e($dept['parent_nom']) : '<span class="text-slate-300">Racine</span>' ?>
        </dd>
      </div>
      <?php if ($dept['budget_centre']): ?>
        <div>
          <dt class="text-xs font-medium text-slate-500 uppercase tracking-wide">Centre de coût</dt>
          <dd class="text-sm font-mono text-slate-800 mt-1"><?= e($dept['budget_centre']) ?></dd>
        </div>
      <?php endif; ?>
      <div>
        <dt class="text-xs font-medium text-slate-500 uppercase tracking-wide">Créé le</dt>
        <dd class="text-sm text-slate-800 mt-1"><?= e(date('d/m/Y', strtotime($dept['created_at']))) ?></dd>
      </div>
      <div>
        <dt class="text-xs font-medium text-slate-500 uppercase tracking-wide">Dernière modification</dt>
        <dd class="text-sm text-slate-800 mt-1"><?= e(date('d/m/Y H:i', strtotime($dept['updated_at']))) ?></dd>
      </div>
    </dl>
  </div>

  <!-- Services rattachés -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="flex items-center gap-2 font-semibold text-slate-700">
        <i data-lucide="layers" class="w-4 h-4 text-violet-500"></i> Services rattachés (<?= count($services) ?>)
      </h2>
      <?php if ($canUpdate && !$archived): ?>
        <button onclick="document.getElementById('form-new-service').classList.toggle('hidden')"
                class="inline-flex items-center gap-1 text-sm text-violet-600 hover:underline">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un service
        </button>
      <?php endif; ?>
    </div>

    <!-- Formulaire ajout service -->
    <?php if ($canUpdate && !$archived): ?>
      <form id="form-new-service" method="POST"
            action="<?= BASE_URL ?>/v2/rh/organisation/departements/<?= (int)$dept['id'] ?>/services"
            class="hidden bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4">
        <?php \Core\Csrf::field(); ?>
        <div class="grid grid-cols-2 gap-4 mb-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nom *</label>
            <input type="text" name="nom" required placeholder="Ex : Scolarité"
                   class="form-input">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Code *</label>
            <input type="text" name="code" required placeholder="EX_CODE" pattern="[A-Z0-9_]{2,30}"
                   class="form-input font-mono uppercase">
          </div>
        </div>
        <div class="mb-3">
          <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
          <input type="text" name="description" placeholder="Description optionnelle"
                 class="form-input">
        </div>
        <div class="flex gap-2 justify-end">
          <button type="button" onclick="document.getElementById('form-new-service').classList.add('hidden')"
                  class="px-3 py-1.5 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">Annuler</button>
          <button type="submit" class="px-3 py-1.5 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors">Créer le service</button>
        </div>
        <input type="hidden" name="actif" value="1">
      </form>
    <?php endif; ?>

    <?php if (empty($services)): ?>
      <p class="text-slate-400 text-sm">Aucun service rattaché.</p>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($services as $svc): ?>
          <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
            <div>
              <span class="font-medium text-sm text-slate-700"><?= e($svc['nom']) ?></span>
              <span class="ml-2 font-mono text-xs bg-white border border-slate-200 text-slate-500 px-1.5 rounded"><?= e($svc['code']) ?></span>
              <?php if ($svc['resp_nom']): ?>
                <span class="ml-2 text-xs text-slate-500">• <?= e(($svc['resp_prenom'] ?? '') . ' ' . $svc['resp_nom']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($canArchive && $svc['deleted_at'] === null): ?>
              <form method="POST"
                    action="<?= BASE_URL ?>/v2/rh/organisation/departements/<?= (int)$dept['id'] ?>/services/<?= (int)$svc['id'] ?>/archive"
                    onsubmit="return confirm('Archiver ce service ?')">
                <?php \Core\Csrf::field(); ?>
                <button type="submit" class="text-xs text-amber-600 hover:underline">Archiver</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Historique -->
  <?php if (!empty($historique)): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <h2 class="flex items-center gap-2 font-semibold text-slate-700 mb-4">
        <i data-lucide="history" class="w-4 h-4 text-violet-500"></i> Historique des modifications
      </h2>
      <div class="space-y-2">
        <?php foreach ($historique as $h): ?>
          <div class="flex items-start gap-3 text-sm text-slate-600">
            <span class="text-xs font-mono text-slate-400 whitespace-nowrap pt-0.5">
              <?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?>
            </span>
            <span class="px-2 py-0.5 text-xs rounded-full
              <?= match($h['action']) {
                  'creation'     => 'bg-emerald-100 text-emerald-700',
                  'modification' => 'bg-blue-100 text-blue-700',
                  'archivage'    => 'bg-amber-100 text-amber-700',
                  'restauration' => 'bg-violet-100 text-violet-700',
                  default        => 'bg-slate-100 text-slate-600'
              } ?>">
              <?= e(ucfirst($h['action'])) ?>
            </span>
            <?php if ($h['modifie_par_nom']): ?>
              <span class="text-slate-500">par <?= e($h['modifie_par_nom']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
