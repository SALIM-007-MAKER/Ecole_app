<?php
/** @var array $contrat, $avenants */
/** @var string $model */
/** @var bool $canUpdate, $canRenew, $canTerminate, $canArchive */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$jours      = $contrat['jours_restants'] !== null ? (int)$contrat['jours_restants'] : null;
$alertClass = $model::alerteColor($jours);
?>

  <!-- En-tête -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="hover:text-violet-600">RH</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="<?= BASE_URL ?>/v2/rh/contrats" class="hover:text-violet-600">Contrats</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="font-mono text-slate-700"><?= e($contrat['numero_contrat']) ?></span>
  </div>
  <div class="flex items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="file-signature" class="w-5 h-5 text-violet-600"></i>
      </div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3 flex-wrap">
        <?= e($contrat['numero_contrat']) ?>
        <span class="px-3 py-1 text-sm font-medium rounded-full <?= $model::statutColor($contrat['statut']) ?>">
          <?= e($model::statutLabel($contrat['statut'])) ?>
        </span>
        <span class="px-3 py-1 text-sm font-medium rounded-full <?= $model::typeColor($contrat['type']) ?>">
          <?= e($model::typeLabel($contrat['type'])) ?>
        </span>
      </h1>
    </div>
    <div class="flex gap-2 flex-shrink-0">
      <?php if ($canUpdate && in_array($contrat['statut'], ['brouillon','actif'], true)): ?>
        <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/edit" class="btn btn-outline">
          <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
        </a>
      <?php endif; ?>
      <?php if ($canUpdate && $contrat['statut'] === 'brouillon'): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/activer">
          <?= \Core\Csrf::field() ?>
          <button class="btn btn-success">Activer</button>
        </form>
      <?php endif; ?>
      <?php if ($canUpdate && $contrat['statut'] === 'actif'): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/suspendre">
          <?= \Core\Csrf::field() ?>
          <button onclick="return confirm('Suspendre ce contrat ?')" class="btn btn-warning">Suspendre</button>
        </form>
      <?php endif; ?>
      <?php if ($canUpdate && $contrat['statut'] === 'suspendu'): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/reactiver">
          <?= \Core\Csrf::field() ?>
          <button onclick="return confirm('Réactiver ce contrat ?')" class="btn btn-primary">Réactiver</button>
        </form>
      <?php endif; ?>
      <?php if ($canTerminate && in_array($contrat['statut'], ['actif','suspendu'], true)): ?>
        <button onclick="document.getElementById('modal-resilier').classList.remove('hidden')" class="btn btn-danger">Résilier</button>
      <?php endif; ?>
      <?php if ($canRenew && in_array($contrat['statut'], ['actif','expire'], true)): ?>
        <button onclick="document.getElementById('modal-renouveler').classList.remove('hidden')" class="btn btn-primary">Renouveler</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <!-- Alerte échéance -->
  <?php if ($jours !== null && $alertClass && $contrat['statut'] === 'actif'): ?>
    <div class="mb-6 p-4 <?= $alertClass ?> rounded-lg text-sm flex items-center gap-2">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <?= $jours > 0 ? "Ce contrat expire dans <strong>{$jours} jours</strong> (" . e($contrat['date_fin']) . ")." : "Ce contrat a <strong>expiré</strong>." ?>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-3 gap-6">

    <!-- Infos principales -->
    <div class="col-span-2 space-y-6">
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-700 mb-4">Informations contractuelles</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
          <div>
            <dt class="text-slate-400">Employé</dt>
            <dd class="font-medium text-slate-800 mt-0.5">
              <?= e($contrat['employe_nom']) ?>
              <span class="text-xs text-slate-400"><?= e($contrat['employe_matricule'] ?? '') ?></span>
            </dd>
          </div>
          <div>
            <dt class="text-slate-400">Poste</dt>
            <dd class="mt-0.5"><?= e($contrat['poste_intitule'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Département</dt>
            <dd class="mt-0.5"><?= e($contrat['departement_nom'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Salaire brut</dt>
            <dd class="mt-0.5 font-medium">
              <?= $contrat['salaire_brut'] ? number_format((float)$contrat['salaire_brut'], 2, ',', ' ') . ' ' . e($contrat['devise']) : '—' ?>
            </dd>
          </div>
          <div>
            <dt class="text-slate-400">Date de début</dt>
            <dd class="mt-0.5"><?= e(date('d/m/Y', strtotime($contrat['date_debut']))) ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Date de fin</dt>
            <dd class="mt-0.5"><?= $contrat['date_fin'] ? e(date('d/m/Y', strtotime($contrat['date_fin']))) : '<span class="text-slate-400">Indéterminée (CDI)</span>' ?></dd>
          </div>
          <?php if ($contrat['date_signature']): ?>
          <div>
            <dt class="text-slate-400">Date de signature</dt>
            <dd class="mt-0.5"><?= e(date('d/m/Y', strtotime($contrat['date_signature']))) ?></dd>
          </div>
          <?php endif; ?>
          <?php if ($contrat['renouvelle_depuis_numero']): ?>
          <div>
            <dt class="text-slate-400">Renouvellement de</dt>
            <dd class="mt-0.5">
              <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['renouvelle_depuis'] ?>"
                 class="font-mono text-violet-600 hover:underline text-xs"><?= e($contrat['renouvelle_depuis_numero']) ?></a>
            </dd>
          </div>
          <?php endif; ?>
          <?php if ($contrat['motif_creation']): ?>
          <div class="col-span-2">
            <dt class="text-slate-400">Motif de création</dt>
            <dd class="mt-0.5 text-slate-600"><?= e($contrat['motif_creation']) ?></dd>
          </div>
          <?php endif; ?>
          <?php if ($contrat['motif_fin']): ?>
          <div class="col-span-2">
            <dt class="text-slate-400">Motif de fin</dt>
            <dd class="mt-0.5 text-red-700"><?= e($contrat['motif_fin']) ?></dd>
          </div>
          <?php endif; ?>
          <?php if ($contrat['notes']): ?>
          <div class="col-span-2">
            <dt class="text-slate-400">Notes</dt>
            <dd class="mt-0.5 text-slate-600"><?= nl2br(e($contrat['notes'])) ?></dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>

      <!-- Avenants -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-slate-700">Avenants (<?= count($avenants) ?>)</h2>
          <?php if ($canUpdate && in_array($contrat['statut'], ['actif','suspendu'], true)): ?>
            <button onclick="document.getElementById('modal-avenant').classList.remove('hidden')" class="btn btn-outline btn-sm">
              + Avenant
            </button>
          <?php endif; ?>
        </div>
        <?php if (empty($avenants)): ?>
          <p class="text-sm text-slate-400">Aucun avenant enregistré.</p>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($avenants as $av): ?>
              <div class="border border-slate-100 rounded-lg p-4">
                <div class="flex items-start justify-between">
                  <div>
                    <span class="text-xs font-semibold text-slate-500">Avenant n° <?= (int)$av['numero'] ?></span>
                    <span class="ml-2 px-2 py-0.5 text-xs bg-slate-100 text-slate-600 rounded-full"><?= e(ucfirst($av['type'])) ?></span>
                  </div>
                  <span class="text-xs text-slate-400">Effet le <?= e(date('d/m/Y', strtotime($av['date_effet']))) ?></span>
                </div>
                <p class="mt-2 text-sm font-medium text-slate-700"><?= e($av['objet']) ?></p>
                <?php if ($av['description']): ?>
                  <p class="mt-1 text-xs text-slate-500"><?= e($av['description']) ?></p>
                <?php endif; ?>
                <?php if ($av['ancienne_valeur'] || $av['nouvelle_valeur']): ?>
                  <div class="mt-2 flex gap-4 text-xs">
                    <?php if ($av['ancienne_valeur']): ?>
                      <div class="text-red-600 line-through"><?= e(is_string($av['ancienne_valeur']) ? $av['ancienne_valeur'] : json_encode(json_decode($av['ancienne_valeur'], true), JSON_UNESCAPED_UNICODE)) ?></div>
                    <?php endif; ?>
                    <?php if ($av['nouvelle_valeur']): ?>
                      <div class="text-emerald-700">→ <?= e(is_string($av['nouvelle_valeur']) ? $av['nouvelle_valeur'] : json_encode(json_decode($av['nouvelle_valeur'], true), JSON_UNESCAPED_UNICODE)) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Colonne droite -->
    <div class="space-y-4">
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">État machine</h3>
        <?php $transitions = $model::TRANSITIONS[$contrat['statut']] ?? []; ?>
        <?php if (empty($transitions)): ?>
          <p class="text-xs text-slate-400">État terminal — aucune transition possible.</p>
        <?php else: ?>
          <p class="text-xs text-slate-500 mb-2">Transitions disponibles :</p>
          <div class="flex flex-wrap gap-1">
            <?php foreach ($transitions as $t): ?>
              <span class="px-2 py-0.5 text-xs rounded-full <?= $model::statutColor($t) ?>"><?= e($model::statutLabel($t)) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-5 text-sm">
        <h3 class="font-semibold text-slate-700 mb-3">Métadonnées</h3>
        <dl class="space-y-2 text-xs">
          <div>
            <dt class="text-slate-400">Créé le</dt>
            <dd class="mt-0.5"><?= e(date('d/m/Y H:i', strtotime($contrat['created_at']))) ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Modifié le</dt>
            <dd class="mt-0.5"><?= e(date('d/m/Y H:i', strtotime($contrat['updated_at']))) ?></dd>
          </div>
        </dl>
      </div>

      <?php if ($canArchive && in_array($contrat['statut'], ['brouillon','expire','resilie'], true)): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/archive"
              onsubmit="return confirm('Archiver définitivement ce contrat ?')">
          <?= \Core\Csrf::field() ?>
          <button class="w-full px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">
            Archiver
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

<!-- Modal : Avenant -->
<div id="modal-avenant" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-lg shadow-xl p-6">
    <h2 class="text-lg font-bold text-slate-900 mb-4">Ajouter un avenant</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/avenants">
      <?= \Core\Csrf::field() ?>
      <div class="space-y-4">
        <div>
          <label class="form-label">Type d'avenant *</label>
          <select name="type" required class="form-select">
            <?php foreach (\App\Modules\RH\Contrats\DTO\AvenantDTO::TYPES as $t): ?>
              <option value="<?= e($t) ?>"><?= e(ucfirst($t)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Objet *</label>
          <input type="text" name="objet" maxlength="200" required
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Date d'effet *</label>
          <input type="date" name="date_effet" required
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Description</label>
          <textarea name="description" rows="2"
                    class="form-textarea"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-avenant').classList.add('hidden')"
                class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal : Résiliation -->
<div id="modal-resilier" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-md shadow-xl p-6">
    <h2 class="text-lg font-bold text-red-700 mb-4">Résilier le contrat</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/resilier">
      <?= \Core\Csrf::field() ?>
      <div>
        <label class="form-label">Motif de résiliation *</label>
        <textarea name="motif" rows="3" required placeholder="Ex : démission, fin de mission, licenciement..."
                  class="form-textarea"></textarea>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-resilier').classList.add('hidden')"
                class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-danger">Confirmer la résiliation</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal : Renouvellement -->
<div id="modal-renouveler" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-md shadow-xl p-6">
    <h2 class="text-lg font-bold text-violet-700 mb-4">Renouveler le contrat</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>/renouveler">
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
        <p class="text-xs text-slate-500">L'ancien contrat sera marqué comme expiré et un nouveau contrat sera créé avec les mêmes conditions salariales.</p>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-renouveler').classList.add('hidden')"
                class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Renouveler</button>
      </div>
    </form>
  </div>
</div>
