<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$tc = $model::TYPE_COLORS[$doc['type']] ?? 'slate';
$sc = $model::STATUT_COLORS[$doc['statut']] ?? 'slate';
$cc = $model::CONFIDENTIALITE_COLORS[$doc['confidentialite']] ?? 'slate';
$jours = $model::joursAvantExpiration($doc['date_expiration']);
$expireSoon = $model::isExpiringSoon($doc['date_expiration'], (int)($doc['alerte_jours'] ?? 30));
$expired = $model::isExpired($doc['date_expiration']);
?>
<div class="space-y-6">
  <!-- Header -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-2 flex-wrap">
          <a href="<?= BASE_URL ?>/v2/rh/documents" class="text-slate-400 hover:text-slate-600 text-xs">Documents RH</a>
          <span class="text-slate-300">/</span>
          <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($tc) ?>-100 text-<?= $e($tc) ?>-700">
            <?= $e($model::typeLabel($doc['type'])) ?>
          </span>
        </div>
        <h2 class="text-xl font-semibold text-slate-800"><?= $e($doc['titre']) ?></h2>
        <div class="flex flex-wrap gap-2 mt-2">
          <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($sc) ?>-100 text-<?= $e($sc) ?>-700">
            <?= $e(ucfirst(str_replace('_',' ',$doc['statut']))) ?>
          </span>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($cc) ?>-100 text-<?= $e($cc) ?>-700">
            <i data-lucide="lock" class="w-3 h-3"></i><?= $e($model::CONFIDENTIALITE_LABELS[$doc['confidentialite']]) ?>
          </span>
          <span class="text-xs text-slate-400">v<?= (int)$doc['version_courante'] ?></span>
        </div>
      </div>
      <div class="flex gap-2 flex-shrink-0">
        <?php if ($policy->canUpdate($user) && $doc['statut'] !== 'archive'): ?>
        <a href="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$doc['id'] ?>/edit"
           class="border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-sm hover:bg-slate-50 transition">
          <i data-lucide="edit" class="w-4 h-4 inline mr-1"></i>Modifier
        </a>
        <?php endif; ?>
        <?php if ($policy->canArchive($user)): ?>
          <?php if ($doc['statut'] !== 'archive'): ?>
          <button onclick="document.getElementById('modal-archiver').classList.remove('hidden')"
                  class="border border-red-200 text-red-600 px-3 py-1.5 rounded-lg text-sm hover:bg-red-50 transition">
            <i data-lucide="archive" class="w-4 h-4 inline mr-1"></i>Archiver
          </button>
          <?php else: ?>
          <form method="POST" action="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$doc['id'] ?>/restaurer">
            <?= \Core\Csrf::field() ?>
            <button class="border border-green-200 text-green-600 px-3 py-1.5 rounded-lg text-sm hover:bg-green-50 transition">
              <i data-lucide="rotate-ccw" class="w-4 h-4 inline mr-1"></i>Restaurer
            </button>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Métadonnées -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-slate-100">
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Employé</div>
        <div class="text-sm font-medium text-slate-700"><?= $e($doc['employe_nom'] ?? '—') ?></div>
        <div class="text-xs text-slate-400"><?= $e($doc['employe_matricule'] ?? '') ?></div>
      </div>
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Date d'émission</div>
        <div class="text-sm font-medium text-slate-700"><?= $e(date('d/m/Y', strtotime($doc['date_emission']))) ?></div>
      </div>
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Date d'expiration</div>
        <?php if ($doc['date_expiration']): ?>
        <div class="text-sm font-medium <?= $expired ? 'text-red-600' : ($expireSoon ? 'text-amber-600' : 'text-slate-700') ?>">
          <?= $e(date('d/m/Y', strtotime($doc['date_expiration']))) ?>
          <?php if ($jours !== null && $jours >= 0): ?>
          <span class="text-xs font-normal">(<?= $jours ?>j restants)</span>
          <?php elseif ($expired): ?>
          <span class="text-xs font-normal">(expirée)</span>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="text-sm text-slate-400">Sans expiration</div>
        <?php endif; ?>
      </div>
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Émetteur</div>
        <div class="text-sm font-medium text-slate-700"><?= $e($doc['emetteur'] ?? '—') ?></div>
      </div>
    </div>

    <?php if ($doc['reference_externe']): ?>
    <div class="mt-3 pt-3 border-t border-slate-100">
      <div class="text-xs text-slate-400 mb-0.5">Référence externe (DocumentService)</div>
      <div class="font-mono text-xs text-violet-700 bg-violet-50 px-2 py-1 rounded inline-block"><?= $e($doc['reference_externe']) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($doc['notes']): ?>
    <div class="mt-3 pt-3 border-t border-slate-100">
      <div class="text-xs text-slate-400 mb-1">Notes</div>
      <p class="text-sm text-slate-600"><?= nl2br($e($doc['notes'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Liens entités -->
    <?php if ($doc['contrat_id'] || $doc['contrat_numero'] || $doc['session_code'] || $doc['evaluation_id']): ?>
    <div class="mt-3 pt-3 border-t border-slate-100">
      <div class="text-xs text-slate-400 mb-2">Lié à</div>
      <div class="flex gap-2 flex-wrap">
        <?php if ($doc['contrat_numero']): ?>
        <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$doc['contrat_id'] ?>"
           class="text-xs border border-violet-200 text-violet-700 px-2 py-1 rounded hover:bg-violet-50">
          <i data-lucide="file-text" class="w-3 h-3 inline"></i> Contrat <?= $e($doc['contrat_numero']) ?>
        </a>
        <?php endif; ?>
        <?php if ($doc['session_code']): ?>
        <a href="<?= BASE_URL ?>/v2/rh/formations/sessions/<?= (int)$doc['formation_session_id'] ?>"
           class="text-xs border border-blue-200 text-blue-700 px-2 py-1 rounded hover:bg-blue-50">
          <i data-lucide="book-open" class="w-3 h-3 inline"></i> Session <?= $e($doc['session_code']) ?>
        </a>
        <?php endif; ?>
        <?php if ($doc['evaluation_id']): ?>
        <a href="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$doc['evaluation_id'] ?>"
           class="text-xs border border-indigo-200 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-50">
          <i data-lucide="star" class="w-3 h-3 inline"></i> Évaluation #<?= (int)$doc['evaluation_id'] ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Flash -->
  <?php if ($flash = \Core\Session::getFlash('success')): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Historique versions -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
      <h3 class="font-semibold text-slate-700 mb-4">Versions (<?= count($versions) ?>)</h3>
      <div class="space-y-3">
        <?php if (empty($versions)): ?>
        <p class="text-slate-400 text-sm">Aucune version enregistrée.</p>
        <?php endif; ?>
        <?php foreach ($versions as $v): ?>
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-7 h-7 rounded-full bg-violet-100 flex items-center justify-center text-xs font-bold text-violet-700">
            v<?= (int)$v['version'] ?>
          </div>
          <div class="flex-1">
            <div class="text-sm font-medium text-slate-700">
              <?= $e($v['notes_version'] ?: 'Version ' . $v['version']) ?>
            </div>
            <?php if ($v['reference_externe']): ?>
            <div class="text-xs text-slate-400 font-mono mt-0.5"><?= $e($v['reference_externe']) ?></div>
            <?php endif; ?>
            <div class="text-xs text-slate-400 mt-0.5">
              <?= $e($v['created_by_nom'] ?? '') ?> · <?= $e(date('d/m/Y H:i', strtotime($v['created_at']))) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Journal des actions -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
      <h3 class="font-semibold text-slate-700 mb-4">Historique</h3>
      <div class="space-y-3">
        <?php if (empty($historique)): ?>
        <p class="text-slate-400 text-sm">Aucune entrée.</p>
        <?php endif; ?>
        <?php foreach ($historique as $h): ?>
        <?php
          $hColor = match($h['action']) {
              'creer'         => 'green',
              'mettre_a_jour' => 'blue',
              'archiver'      => 'slate',
              'restaurer'     => 'violet',
              'expirer'       => 'red',
              default         => 'slate',
          };
          $hLabel = match($h['action']) {
              'creer'         => 'Création',
              'mettre_a_jour' => 'Mise à jour',
              'archiver'      => 'Archivage',
              'restaurer'     => 'Restauration',
              'expirer'       => 'Expiration auto',
              default         => ucfirst($h['action']),
          };
        ?>
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-2 h-2 rounded-full bg-<?= $e($hColor) ?>-400 mt-2"></div>
          <div class="flex-1">
            <div class="text-sm font-medium text-slate-700"><?= $e($hLabel) ?></div>
            <?php if ($h['notes']): ?>
            <div class="text-xs text-slate-500 mt-0.5"><?= $e($h['notes']) ?></div>
            <?php endif; ?>
            <div class="text-xs text-slate-400 mt-0.5">
              <?= $e($h['created_by_nom'] ?? 'Système') ?> · <?= $e(date('d/m/Y H:i', strtotime($h['created_at']))) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal archivage -->
<div id="modal-archiver" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-1">Archiver le document</h3>
    <p class="text-sm text-slate-500 mb-4">Le document sera archivé et ne sera plus actif. Il peut être restauré ultérieurement.</p>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$doc['id'] ?>/archiver">
      <?= \Core\Csrf::field() ?>
      <div class="mb-4">
        <label class="form-label">Motif (optionnel)</label>
        <textarea name="notes" rows="2"
                  class="form-textarea resize-none"
                  placeholder="Raison de l'archivage…"></textarea>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-red-700 transition">Archiver</button>
        <button type="button" onclick="document.getElementById('modal-archiver').classList.add('hidden')"
                class="border border-slate-200 text-slate-600 px-5 py-2 rounded-lg text-sm hover:bg-slate-50 transition">Annuler</button>
      </div>
    </form>
  </div>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
