<?php
/**
 * @var array $limits  {storage_quota_mb,max_users,max_eleves,max_documents,max_attachments}
 * @var array $usage   {storage_used_bytes,users_count,eleves_count,documents_count,attachments_count}
 * @var array $alerts  list<{type,level,message}>
 * @var array $history list<{module,context,path,size_bytes,mime_type,created_at,deleted_at}>
 */
$fmtMb = fn(int $bytes) => number_format($bytes / 1024 / 1024, 2, ',', ' ') . ' Mo';
$pct = fn(?int $used, ?int $max) => ($max !== null && $max > 0) ? min(100, (int)round(($used ?? 0) / $max * 100)) : null;

$storageQuotaBytes = $limits['storage_quota_mb'] * 1024 * 1024;
$storagePct = $pct($usage['storage_used_bytes'], $storageQuotaBytes);
?>

<!-- Page header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="database" class="w-5 h-5 text-violet-600"></i>
            Stockage &amp; Quotas
        </h2>
        <p class="text-sm text-slate-500">Consommation de ressources de votre établissement</p>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/parametres/quotas/recalculer">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <button type="submit" class="btn btn-secondary">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>Recalculer
        </button>
    </form>
</div>

<?php if (!empty($alerts)): ?>
<div class="space-y-2 mb-6">
    <?php foreach ($alerts as $alert): ?>
    <div class="alert <?= $alert['level'] === 'critical' ? 'alert-danger' : 'alert-warning' ?>" role="alert">
        <i data-lucide="<?= $alert['level'] === 'critical' ? 'alert-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
        <span class="text-sm"><?= htmlspecialchars($alert['message'], ENT_QUOTES) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Jauges -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['label' => 'Stockage', 'icon' => 'hard-drive', 'used' => $usage['storage_used_bytes'], 'max' => $storageQuotaBytes, 'fmt' => fn($v) => $fmtMb((int)$v)],
        ['label' => 'Utilisateurs', 'icon' => 'users', 'used' => $usage['users_count'], 'max' => $limits['max_users'], 'fmt' => fn($v) => (string)$v],
        ['label' => 'Élèves', 'icon' => 'graduation-cap', 'used' => $usage['eleves_count'], 'max' => $limits['max_eleves'], 'fmt' => fn($v) => (string)$v],
        ['label' => 'Documents', 'icon' => 'file-text', 'used' => $usage['documents_count'], 'max' => $limits['max_documents'], 'fmt' => fn($v) => (string)$v],
    ];
    foreach ($cards as $card):
        $p = $card['used'] === null ? null : $pct($card['used'], $card['max']);
    ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-3">
            <i data-lucide="<?= $card['icon'] ?>" class="w-4 h-4 text-violet-600"></i><?= $card['label'] ?>
        </div>
        <?php if ($card['used'] === null): ?>
        <p class="text-xs text-slate-400">Non applicable dans cet environnement (module non activé)</p>
        <?php else: ?>
        <p class="text-2xl font-bold text-slate-900 mb-2">
            <?= $card['fmt']($card['used']) ?>
            <?php if ($card['max'] !== null): ?>
            <span class="text-sm font-normal text-slate-400">/ <?= $card['fmt']($card['max']) ?></span>
            <?php else: ?>
            <span class="text-sm font-normal text-slate-400">/ illimité</span>
            <?php endif; ?>
        </p>
        <?php if ($p !== null): ?>
        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full <?= $p >= 100 ? 'bg-red-500' : ($p >= 80 ? 'bg-amber-500' : 'bg-violet-600') ?>" style="width: <?= $p ?>%"></div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Historique -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="history" class="w-4 h-4 text-violet-600"></i>Historique des écritures récentes
    </div>
    <?php if (empty($history)): ?>
    <div class="p-6 text-sm text-slate-500">Aucun fichier enregistré pour le moment.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-left">Module</th>
                    <th class="px-5 py-3 text-left">Contexte</th>
                    <th class="px-5 py-3 text-left">Taille</th>
                    <th class="px-5 py-3 text-left">Date</th>
                    <th class="px-5 py-3 text-left">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($history as $h): ?>
                <tr>
                    <td class="px-5 py-3 text-slate-700"><?= htmlspecialchars($h['module'], ENT_QUOTES) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($h['context'], ENT_QUOTES) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= $fmtMb((int)$h['size_bytes']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars((string)$h['created_at'], ENT_QUOTES) ?></td>
                    <td class="px-5 py-3">
                        <?php if ($h['deleted_at']): ?>
                        <span class="badge badge-secondary">Supprimé</span>
                        <?php else: ?>
                        <span class="badge badge-success">Actif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
