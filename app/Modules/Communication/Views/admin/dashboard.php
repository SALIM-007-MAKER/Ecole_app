<?php /** @var array $queueStats @var array $channelStats @var array $recentLogs @var array $failedJobs */ ?>
<div class="max-w-6xl mx-auto py-8 px-4">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Administration — Communication</h1>
    <button onclick="processBatch()" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">
      Traiter la file (50)
    </button>
  </div>

  <!-- Stats file -->
  <div class="grid grid-cols-5 gap-4 mb-6">
    <?php
    $cols = [
      ['pending',    'En attente',  'yellow'],
      ['processing', 'En cours',    'blue'],
      ['sent',       'Envoyés',     'green'],
      ['failed',     'Échoués',     'red'],
      ['cancelled',  'Annulés',     'slate'],
    ];
    foreach ($cols as [$key, $label, $color]):
    ?>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 text-center">
      <div class="text-2xl font-bold text-<?= $color ?>-600"><?= $queueStats[$key] ?? 0 ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= $label ?></div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Stats par canal -->
  <div class="grid grid-cols-4 gap-4 mb-6">
    <?php foreach (['internal','email','sms','push'] as $canal): ?>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100">
      <div class="text-xs text-slate-400 uppercase mb-2"><?= strtoupper($canal) ?></div>
      <div class="text-lg font-bold text-slate-800"><?= $channelStats[$canal]['sent'] ?? 0 ?> <span class="text-sm font-normal text-slate-400">envoyés</span></div>
      <div class="text-xs text-red-400 mt-1"><?= $channelStats[$canal]['failed'] ?? 0 ?> échecs</div>
    </div>
    <?php endforeach ?>
  </div>

  <div class="grid grid-cols-2 gap-6">
    <!-- Jobs en échec -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center">
        <h3 class="font-medium text-slate-700 text-sm">Messages en échec</h3>
        <button onclick="retryFailed()" class="text-xs text-violet-600 hover:underline">Relancer tous</button>
      </div>
      <table class="w-full text-xs">
        <thead class="bg-slate-50 text-slate-400 uppercase">
          <tr>
            <th class="text-left px-4 py-2">Canal</th>
            <th class="text-left px-4 py-2">Tentatives</th>
            <th class="text-left px-4 py-2">Erreur</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($failedJobs as $j): ?>
          <tr>
            <td class="px-4 py-2 uppercase"><?= htmlspecialchars($j['canal']) ?></td>
            <td class="px-4 py-2"><?= $j['tentatives'] ?>/<?= $j['max_tentatives'] ?></td>
            <td class="px-4 py-2 text-red-500 max-w-xs truncate"><?= htmlspecialchars($j['erreur'] ?? '—') ?></td>
            <td class="px-4 py-2">
              <button onclick="retryJob(<?= $j['id'] ?>)" class="text-violet-600 hover:underline">Relancer</button>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($failedJobs)): ?>
          <tr><td colspan="4" class="px-4 py-4 text-center text-slate-400">Aucun échec</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>

    <!-- Journal récent -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100">
        <h3 class="font-medium text-slate-700 text-sm">Journal d'activité récent</h3>
      </div>
      <div class="divide-y divide-slate-50 max-h-80 overflow-y-auto">
        <?php foreach ($recentLogs as $log): ?>
        <div class="px-4 py-2 flex justify-between items-start">
          <div>
            <span class="text-xs font-medium text-slate-700"><?= htmlspecialchars($log['type']) ?></span>
            <span class="text-xs text-slate-400 ml-2 uppercase"><?= htmlspecialchars($log['canal']) ?></span>
            <?php if ($log['erreur']): ?>
            <div class="text-xs text-red-400 mt-0.5"><?= htmlspecialchars($log['erreur']) ?></div>
            <?php endif ?>
          </div>
          <span class="text-xs text-slate-300"><?= date('d/m H:i', strtotime($log['created_at'])) ?></span>
        </div>
        <?php endforeach ?>
        <?php if (empty($recentLogs)): ?>
        <div class="px-4 py-4 text-center text-slate-400 text-sm">Aucun log</div>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div id="batch-result" class="hidden mt-4 bg-green-50 text-green-700 text-sm p-4 rounded-xl"></div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function processBatch() {
  const r = await fetch('<?= BASE_URL ?>/v2/communication/admin/queue/process', { method: 'POST', headers: { 'X-CSRF-Token': csrf } });
  const j = await r.json();
  const el = document.getElementById('batch-result');
  el.classList.remove('hidden');
  el.textContent = `Traités: ${j.processed ?? 0} — Succès: ${j.success ?? 0} — Échecs: ${j.failed ?? 0}`;
  setTimeout(() => location.reload(), 2000);
}

async function retryJob(id) {
  await fetch(`<?= BASE_URL ?>/v2/communication/admin/queue/${id}/retry`, { method: 'POST', headers: { 'X-CSRF-Token': csrf } });
  location.reload();
}

async function retryFailed() {
  if (!confirm('Relancer tous les messages en échec ?')) return;
  await fetch('<?= BASE_URL ?>/v2/communication/admin/queue/retry-failed', { method: 'POST', headers: { 'X-CSRF-Token': csrf } });
  location.reload();
}
</script>
