<?php
/** @var array $quotas */
$titre = 'Administration — Quotas';
?>
<div class="p-6 space-y-6">

  <h1 class="text-2xl font-bold text-slate-800">Quotas de stockage</h1>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Module</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Utilisé</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Quota</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Disponible</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Usage</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($quotas as $q): ?>
        <?php
        $pct = $q->quotaOctets > 0 ? min(100, round($q->utilisOctets / $q->quotaOctets * 100)) : 0;
        $bar = $pct > 80 ? 'bg-red-500' : ($pct > 60 ? 'bg-amber-400' : 'bg-green-500');
        ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($q->moduleSource) ?></td>
          <td class="px-4 py-3 text-slate-600"><?= $q->formatOctets($q->utilisOctets) ?></td>
          <td class="px-4 py-3 text-slate-600">
            <?= $q->quotaOctets > 0 ? $q->formatOctets($q->quotaOctets) : 'Illimité' ?>
          </td>
          <td class="px-4 py-3 text-slate-600">
            <?= $q->quotaOctets > 0 ? $q->formatOctets(max(0, $q->octetsDisponibles())) : '∞' ?>
          </td>
          <td class="px-4 py-3 w-32">
            <div class="bg-slate-100 rounded-full h-2">
              <div class="<?= $bar ?> h-2 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="text-xs text-slate-500 mt-0.5"><?= $pct ?>%</span>
          </td>
          <td class="px-4 py-3 text-right">
            <button onclick="modifierQuota('<?= htmlspecialchars($q->moduleSource) ?>', <?= $q->quotaOctets ?>)"
                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Modifier</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Liens admin -->
  <div class="flex gap-4 text-sm">
    <a href="/v2/documents/admin/expirations" class="text-violet-600 hover:text-violet-800 font-medium">Gérer les expirations →</a>
    <a href="/v2/documents/admin/statistics" class="text-violet-600 hover:text-violet-800 font-medium">Statistiques →</a>
    <a href="/v2/trash" class="text-violet-600 hover:text-violet-800 font-medium">Corbeille →</a>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';

async function modifierQuota(module, actuel) {
  const mb = prompt(`Quota pour "${module}" (Mo, 0 = illimité) :`, Math.round(actuel / 1048576));
  if (mb === null) return;
  const octets = parseInt(mb) * 1048576;
  const fd = new FormData();
  fd.append('csrf_token', csrf);
  fd.append('max_octets', octets);
  const r = await fetch(`/v2/documents/admin/quota/${encodeURIComponent(module)}`, {method:'POST', body: fd});
  const d = await r.json();
  if (d.success) location.reload();
}
</script>
