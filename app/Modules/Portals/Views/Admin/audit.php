<?php /** @var array $logs, int $total, int $page, int $perPage */ ?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <p class="text-sm text-slate-500"><?= number_format($total) ?> entrées au total</p>
    <span class="text-xs text-slate-400">Page <?= $page ?> / <?= max(1, (int)ceil($total / $perPage)) ?></span>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wide">Utilisateur</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wide">Action</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wide">Module</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wide">Date</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($logs as $log): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars(trim(($log['prenom'] ?? '') . ' ' . ($log['nom'] ?? '')) ?: 'Système') ?></td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($log['action'] ?? '') ?></td>
          <td class="px-4 py-3"><span class="bg-violet-100 text-violet-700 text-xs px-2 py-0.5 rounded-full"><?= htmlspecialchars($log['module'] ?? '') ?></span></td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars($log['created_at'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Aucun log trouvé</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="flex justify-between">
    <?php if ($page > 1): ?>
    <a href="?page=<?= $page - 1 ?>" class="text-sm text-violet-600 hover:underline">← Précédent</a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($page * $perPage < $total): ?>
    <a href="?page=<?= $page + 1 ?>" class="text-sm text-violet-600 hover:underline">Suivant →</a>
    <?php endif; ?>
  </div>
</div>
