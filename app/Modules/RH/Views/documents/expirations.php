<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$today = date('Y-m-d');
?>
<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Alertes expiration</h2>
      <p class="text-sm text-slate-500"><?= count($docs) ?> document(s) expirant dans les <?= (int)$jours ?> prochains jours</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/rh/documents" class="border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition">
      <i data-lucide="arrow-left" class="w-4 h-4 inline mr-1"></i>Retour
    </a>
  </div>

  <!-- Filtre durée -->
  <div class="flex gap-2">
    <?php foreach ([15, 30, 60, 90] as $j): ?>
    <a href="<?= BASE_URL ?>/v2/rh/documents/expirations?jours=<?= $j ?>"
       class="px-4 py-2 rounded-lg text-sm <?= $jours === $j ? 'bg-violet-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
      <?= $j ?> jours
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($docs)): ?>
  <div class="bg-green-50 border border-green-200 rounded-xl p-8 text-center">
    <i data-lucide="check-circle" class="w-12 h-12 text-green-400 mx-auto mb-3"></i>
    <p class="text-green-700 font-medium">Aucun document n'expire dans les <?= (int)$jours ?> prochains jours</p>
  </div>
  <?php else: ?>

  <?php
    $expires  = array_filter($docs, fn($d) => $d['date_expiration'] < $today);
    $expirant = array_filter($docs, fn($d) => $d['date_expiration'] >= $today);
  ?>

  <?php if ($expires): ?>
  <div class="bg-red-50 border border-red-200 rounded-xl p-4">
    <div class="flex items-center gap-2 mb-3">
      <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
      <h3 class="font-semibold text-red-700"><?= count($expires) ?> document(s) déjà expiré(s) — action requise</h3>
    </div>
    <div class="space-y-2">
      <?php foreach ($expires as $d): ?>
      <div class="bg-white rounded-lg p-3 flex items-center justify-between">
        <div>
          <span class="text-xs font-medium text-red-600"><?= $e($model::typeLabel($d['type'])) ?></span>
          <div class="font-medium text-slate-800"><?= $e($d['titre']) ?></div>
          <div class="text-xs text-slate-500"><?= $e($d['employe_nom'] ?? '') ?> · Expiré le <?= $e(date('d/m/Y', strtotime($d['date_expiration']))) ?></div>
        </div>
        <a href="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$d['id'] ?>" class="text-violet-600 hover:text-violet-800 text-sm font-medium">Traiter</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($expirant): ?>
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-200 bg-amber-50">
      <h3 class="font-semibold text-amber-700">
        <i data-lucide="clock" class="w-4 h-4 inline mr-1"></i>
        <?= count($expirant) ?> document(s) expirant prochainement
      </h3>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 text-slate-500 font-medium">Type</th>
          <th class="text-left px-4 py-3 text-slate-500 font-medium">Document</th>
          <th class="text-left px-4 py-3 text-slate-500 font-medium">Employé</th>
          <th class="text-center px-4 py-3 text-slate-500 font-medium">Expiration</th>
          <th class="text-center px-4 py-3 text-slate-500 font-medium">Jours restants</th>
          <th class="text-right px-4 py-3 text-slate-500 font-medium">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($expirant as $d):
          $jLeft = (int)ceil((strtotime($d['date_expiration']) - time()) / 86400);
          $urgCol = $jLeft <= 15 ? 'red' : ($jLeft <= 30 ? 'amber' : 'slate');
        ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3">
            <?php $tc = $model::TYPE_COLORS[$d['type']] ?? 'slate'; ?>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($tc) ?>-100 text-<?= $e($tc) ?>-700">
              <?= $e($model::typeLabel($d['type'])) ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800"><?= $e($d['titre']) ?></div>
            <?php if ($d['reference_externe']): ?>
            <div class="text-xs text-slate-400 font-mono"><?= $e($d['reference_externe']) ?></div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= $e($d['employe_nom'] ?? '—') ?></td>
          <td class="px-4 py-3 text-center text-slate-600"><?= $e(date('d/m/Y', strtotime($d['date_expiration']))) ?></td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-<?= $e($urgCol) ?>-100 text-<?= $e($urgCol) ?>-700">
              <?= $jLeft ?>j
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$d['id'] ?>" class="text-violet-600 hover:text-violet-800 text-xs font-medium">Voir</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
