<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="space-y-6">
  <!-- KPIs -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php foreach ([
      ['label'=>'Actifs','val'=>$stats['actifs']??0,'icon'=>'file-check','color'=>'green'],
      ['label'=>'Expirés','val'=>$stats['expires']??0,'icon'=>'file-x','color'=>'red'],
      ['label'=>'Expirent bientôt','val'=>$stats['expirent_bientot']??0,'icon'=>'clock','color'=>'amber'],
      ['label'=>'Archivés','val'=>$stats['archives']??0,'icon'=>'archive','color'=>'slate'],
    ] as $kpi): ?>
    <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-3 border border-slate-100">
      <div class="w-10 h-10 rounded-lg bg-<?= $e($kpi['color']) ?>-100 flex items-center justify-center">
        <i data-lucide="<?= $e($kpi['icon']) ?>" class="w-5 h-5 text-<?= $e($kpi['color']) ?>-600"></i>
      </div>
      <div>
        <div class="text-2xl font-bold text-slate-800"><?= $e($kpi['val']) ?></div>
        <div class="text-xs text-slate-500"><?= $e($kpi['label']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filtres -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[160px]">
        <label class="block text-xs text-slate-500 mb-1">Recherche</label>
        <input type="text" name="q" value="<?= $e($filters->q) ?>"
               placeholder="Titre, référence, employé…"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div class="min-w-[140px]">
        <label class="block text-xs text-slate-500 mb-1">Type</label>
        <select name="type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">Tous</option>
          <?php foreach ($model::TYPES as $t): ?>
          <option value="<?= $e($t) ?>" <?= $filters->type === $t ? 'selected' : '' ?>><?= $e($model::typeLabel($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Statut</label>
        <select name="statut" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">Actifs+En attente</option>
          <?php foreach ($model::STATUTS as $s): ?>
          <option value="<?= $e($s) ?>" <?= $filters->statut === $s ? 'selected' : '' ?>><?= $e(ucfirst(str_replace('_',' ',$s))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Confidentialité</label>
        <select name="confidentialite" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">Toutes</option>
          <?php foreach ($model::CONFIDENTIALITES as $c): ?>
          <option value="<?= $e($c) ?>" <?= $filters->confidentialite === $c ? 'selected' : '' ?>>
            <?= $e($model::CONFIDENTIALITE_LABELS[$c]) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex items-center gap-1 mt-4">
        <input type="checkbox" id="inc_arch" name="include_archive" value="1" <?= $filters->includeArchive ? 'checked' : '' ?> class="rounded border-slate-300 text-violet-600">
        <label for="inc_arch" class="text-sm text-slate-600">Inclure archivés</label>
      </div>
      <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 transition">
        <i data-lucide="search" class="w-4 h-4 inline mr-1"></i>Filtrer
      </button>
      <a href="/v2/rh/documents" class="border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition">Réinitialiser</a>
    </form>
  </div>

  <!-- Actions -->
  <div class="flex justify-between items-center">
    <h2 class="text-lg font-semibold text-slate-700"><?= $total ?> document(s)</h2>
    <div class="flex gap-2">
      <?php if ($policy->canView($user)): ?>
      <a href="/v2/rh/documents/expirations" class="border border-amber-300 text-amber-700 px-4 py-2 rounded-lg text-sm hover:bg-amber-50 transition flex items-center gap-1">
        <i data-lucide="alert-triangle" class="w-4 h-4"></i>Alertes expiration
        <?php if (($stats['expirent_bientot'] ?? 0) > 0): ?>
        <span class="bg-amber-500 text-white text-xs rounded-full px-1.5"><?= (int)$stats['expirent_bientot'] ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
      <?php if ($policy->canCreate($user)): ?>
      <a href="/v2/rh/documents/create" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 transition flex items-center gap-1">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouveau document
      </a>
      <?php endif; ?>
      <?php if ($policy->canExport($user)): ?>
      <a href="/v2/rh/documents/export?<?= http_build_query($_GET) ?>" class="border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition flex items-center gap-1">
        <i data-lucide="download" class="w-4 h-4"></i>CSV
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Flash -->
  <?php if ($flash = \Core\Session::getFlash('success')): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>

  <!-- Table -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 text-slate-600 font-medium">Type</th>
          <th class="text-left px-4 py-3 text-slate-600 font-medium">Titre</th>
          <th class="text-left px-4 py-3 text-slate-600 font-medium">Employé</th>
          <th class="text-center px-4 py-3 text-slate-600 font-medium">v.</th>
          <th class="text-center px-4 py-3 text-slate-600 font-medium">Expiration</th>
          <th class="text-center px-4 py-3 text-slate-600 font-medium">Confidentialité</th>
          <th class="text-center px-4 py-3 text-slate-600 font-medium">Statut</th>
          <th class="text-right px-4 py-3 text-slate-600 font-medium">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($docs)): ?>
        <tr><td colspan="8" class="text-center py-10 text-slate-400">Aucun document trouvé</td></tr>
        <?php endif; ?>
        <?php foreach ($docs as $d):
          $tc = $model::TYPE_COLORS[$d['type']] ?? 'slate';
          $sc = $model::STATUT_COLORS[$d['statut']] ?? 'slate';
          $cc = $model::CONFIDENTIALITE_COLORS[$d['confidentialite']] ?? 'slate';
          $jours = $model::joursAvantExpiration($d['date_expiration']);
          $expire_soon = $model::isExpiringSoon($d['date_expiration'], (int)($d['alerte_jours'] ?? 30));
        ?>
        <tr class="hover:bg-slate-50 transition <?= $expire_soon ? 'bg-amber-50/50' : '' ?>">
          <td class="px-4 py-3">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($tc) ?>-100 text-<?= $e($tc) ?>-700">
              <?= $e($model::typeLabel($d['type'])) ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800"><?= $e($d['titre']) ?></div>
            <?php if ($d['reference_externe']): ?>
            <div class="text-xs text-slate-400 font-mono mt-0.5"><?= $e($d['reference_externe']) ?></div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= $e($d['employe_nom'] ?? '—') ?></td>
          <td class="px-4 py-3 text-center text-slate-500 text-xs">v<?= (int)$d['version_courante'] ?></td>
          <td class="px-4 py-3 text-center">
            <?php if ($d['date_expiration']): ?>
              <div class="<?= $expire_soon ? 'text-amber-600 font-medium' : ($model::isExpired($d['date_expiration']) ? 'text-red-600 font-medium' : 'text-slate-600') ?>">
                <?= $e(date('d/m/Y', strtotime($d['date_expiration']))) ?>
              </div>
              <?php if ($jours !== null && $jours >= 0): ?>
              <div class="text-xs text-slate-400"><?= $jours ?>j</div>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-slate-300 text-xs">—</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($cc) ?>-100 text-<?= $e($cc) ?>-700">
              <i data-lucide="lock" class="w-3 h-3"></i><?= $e($model::CONFIDENTIALITE_LABELS[$d['confidentialite']]) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($sc) ?>-100 text-<?= $e($sc) ?>-700">
              <?= $e(ucfirst(str_replace('_',' ',$d['statut']))) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="/v2/rh/documents/<?= (int)$d['id'] ?>" class="text-violet-600 hover:text-violet-800 text-xs font-medium">Voir</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="flex justify-center gap-1">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
       class="px-3 py-1.5 rounded text-sm <?= $p === ($filters->page ?? 1) ? 'bg-violet-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
      <?= $p ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
