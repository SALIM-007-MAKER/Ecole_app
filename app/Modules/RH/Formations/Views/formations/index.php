<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$statuts = $model::SESSION_STATUTS ?? [];
$colors  = $model::SESSION_STATUT_COLORS ?? [];
?>
<div class="space-y-6">
  <!-- KPIs -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php foreach ([
      ['label'=>'Sessions totales','val'=>$stats['total_sessions']??0,'icon'=>'calendar','color'=>'violet'],
      ['label'=>'En cours','val'=>$stats['sessions_en_cours']??0,'icon'=>'play-circle','color'=>'blue'],
      ['label'=>'Inscriptions actives','val'=>$stats['total_inscriptions']??0,'icon'=>'users','color'=>'green'],
      ['label'=>'Attestations délivrées','val'=>$stats['total_attestations']??0,'icon'=>'award','color'=>'amber'],
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
               placeholder="Code session, formation…"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div class="min-w-[140px]">
        <label class="block text-xs text-slate-500 mb-1">Statut</label>
        <select name="statut" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">Tous</option>
          <?php foreach ($model::SESSION_STATUTS as $s): ?>
          <option value="<?= $e($s) ?>" <?= $filters->statut === $s ? 'selected' : '' ?>><?= $e(ucfirst(str_replace('_',' ',$s))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[130px]">
        <label class="block text-xs text-slate-500 mb-1">Type</label>
        <select name="type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">Tous</option>
          <?php foreach ($model::TYPES_FORMATION as $t): ?>
          <option value="<?= $e($t) ?>" <?= $filters->type === $t ? 'selected' : '' ?>><?= $e(ucfirst($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="min-w-[100px]">
        <label class="block text-xs text-slate-500 mb-1">Année</label>
        <input type="number" name="annee" value="<?= $e($filters->annee) ?>"
               min="2020" max="2035"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 transition">
        <i data-lucide="search" class="w-4 h-4 inline mr-1"></i>Filtrer
      </button>
      <a href="/v2/rh/formations" class="border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition">Réinitialiser</a>
    </form>
  </div>

  <!-- Actions -->
  <div class="flex justify-between items-center">
    <h2 class="text-lg font-semibold text-slate-700"><?= $total ?> session(s)</h2>
    <div class="flex gap-2">
      <?php if ($policy->canView($user)): ?>
      <a href="/v2/rh/formations/catalogue" class="border border-violet-300 text-violet-700 px-4 py-2 rounded-lg text-sm hover:bg-violet-50 transition flex items-center gap-1">
        <i data-lucide="book-open" class="w-4 h-4"></i>Catalogue
      </a>
      <?php endif; ?>
      <?php if ($policy->canCreate($user)): ?>
      <a href="/v2/rh/formations/sessions/create" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 transition flex items-center gap-1">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle session
      </a>
      <?php endif; ?>
      <?php if ($policy->canExport($user)): ?>
      <a href="/v2/rh/formations/export?<?= http_build_query($_GET) ?>" class="border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition flex items-center gap-1">
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
          <th class="text-left px-4 py-3 text-slate-600 font-medium">Session</th>
          <th class="text-left px-4 py-3 text-slate-600 font-medium">Formation</th>
          <th class="text-left px-4 py-3 text-slate-600 font-medium">Dates</th>
          <th class="text-left px-4 py-3 text-slate-600 font-medium">Lieu</th>
          <th class="text-center px-4 py-3 text-slate-600 font-medium">Inscrits</th>
          <th class="text-center px-4 py-3 text-slate-600 font-medium">Statut</th>
          <th class="text-right px-4 py-3 text-slate-600 font-medium">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($sessions)): ?>
        <tr><td colspan="7" class="text-center py-10 text-slate-400">Aucune session trouvée</td></tr>
        <?php endif; ?>
        <?php foreach ($sessions as $s): ?>
        <?php $color = $model::SESSION_STATUT_COLORS[$s['statut']] ?? 'slate'; ?>
        <tr class="hover:bg-slate-50 transition">
          <td class="px-4 py-3 font-mono text-xs font-medium text-slate-700"><?= $e($s['code_session']) ?></td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800"><?= $e($s['formation_titre']) ?></div>
            <div class="text-xs text-slate-400"><?= $e($s['formation_type']) ?></div>
          </td>
          <td class="px-4 py-3 text-slate-600">
            <?= $e(date('d/m/Y', strtotime($s['date_debut']))) ?>
            <?php if ($s['date_fin']): ?> → <?= $e(date('d/m/Y', strtotime($s['date_fin']))) ?><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= $e($s['lieu'] ?? '—') ?></td>
          <td class="px-4 py-3 text-center">
            <span class="text-slate-700"><?= (int)$s['nb_inscrits'] ?></span>
            <span class="text-slate-400">/<?= (int)$s['max_participants'] ?></span>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($color) ?>-100 text-<?= $e($color) ?>-700">
              <?= $e(ucfirst(str_replace('_',' ',$s['statut']))) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="/v2/rh/formations/sessions/<?= (int)$s['id'] ?>"
               class="text-violet-600 hover:text-violet-800 text-xs font-medium">Détail</a>
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
<?php if (function_exists('lucide_init')) lucide_init(); ?>
<script>if(window.lucide)lucide.createIcons();</script>
