<?php
/** @var array $stats, $history, $restoreHistory, $etablissements */
$fmtSize = fn(int $b) => number_format($b / 1024 / 1024, 2, ',', ' ') . ' Mo';
$statutBadge = ['pending' => 'badge-secondary', 'running' => 'badge-warning', 'success' => 'badge-success', 'failed' => 'badge-danger'];
$typeLabel = ['global' => 'Globale', 'manual' => 'Manuelle', 'pre_migration' => 'Pré-migration', 'tenant' => 'Tenant', 'differential' => 'Différentielle'];
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="shield-check" class="w-5 h-5 text-violet-600"></i>Sauvegardes &amp; Reprise après incident
    </h2>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center"><p class="text-xl font-bold text-slate-900"><?= (int)$stats['total'] ?></p><p class="text-xs text-slate-500">Total</p></div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center"><p class="text-xl font-bold text-emerald-600"><?= (int)$stats['success'] ?></p><p class="text-xs text-slate-500">Réussies</p></div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center"><p class="text-xl font-bold text-red-600"><?= (int)$stats['failed'] ?></p><p class="text-xs text-slate-500">Échouées</p></div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center"><p class="text-xl font-bold text-slate-900"><?= $stats['avg_duration_ms'] !== null ? (int)round($stats['avg_duration_ms']) . ' ms' : '—' ?></p><p class="text-xs text-slate-500">Durée moyenne</p></div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center"><p class="text-xl font-bold text-slate-900"><?= $fmtSize((int)$stats['total_size_bytes']) ?></p><p class="text-xs text-slate-500">Volume total</p></div>
</div>

<!-- Actions -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <p class="text-sm font-semibold text-slate-900 mb-3"><i data-lucide="database" class="w-4 h-4 text-violet-600 inline"></i> Sauvegarde globale</p>
        <form method="POST" action="<?= BASE_URL ?>/platform/backups/global">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" class="btn btn-primary w-full"><i data-lucide="play" class="w-4 h-4"></i>Lancer maintenant</button>
        </form>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <p class="text-sm font-semibold text-slate-900 mb-3"><i data-lucide="building-2" class="w-4 h-4 text-violet-600 inline"></i> Sauvegarde par établissement</p>
        <form method="POST" action="<?= BASE_URL ?>/platform/backups/tenant" class="space-y-2">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <select name="etablissement_id" class="form-input" required>
                <?php foreach ($etablissements as $e): ?>
                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['nom'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary w-full"><i data-lucide="play" class="w-4 h-4"></i>Sauvegarder ce tenant</button>
        </form>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <p class="text-sm font-semibold text-slate-900 mb-3"><i data-lucide="git-compare" class="w-4 h-4 text-violet-600 inline"></i> Sauvegarde différentielle</p>
        <form method="POST" action="<?= BASE_URL ?>/platform/backups/differentiel" class="space-y-2">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <select name="etablissement_id" class="form-input">
                <option value="">Toute la plateforme</option>
                <?php foreach ($etablissements as $e): ?>
                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['nom'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="since_days" class="form-input" value="1" min="1" placeholder="Depuis N jours">
            <button type="submit" class="btn btn-secondary w-full"><i data-lucide="play" class="w-4 h-4"></i>Lancer</button>
        </form>
    </div>
</div>

<!-- Historique des sauvegardes -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-8 overflow-x-auto">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="history" class="w-4 h-4 text-violet-600"></i>Historique des sauvegardes
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-2 text-left">Type</th>
                <th class="px-4 py-2 text-left">Établissement</th>
                <th class="px-4 py-2 text-left">Statut</th>
                <th class="px-4 py-2 text-left">Taille</th>
                <th class="px-4 py-2 text-left">Chiffrée</th>
                <th class="px-4 py-2 text-left">Créée le</th>
                <th class="px-4 py-2 text-left col-actions">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($history as $b): ?>
            <tr>
                <td class="px-4 py-2"><?= $typeLabel[$b['type']] ?? $b['type'] ?></td>
                <td class="px-4 py-2 text-slate-500"><?= $b['etablissement_id'] ? '#' . (int)$b['etablissement_id'] : 'Plateforme' ?></td>
                <td class="px-4 py-2"><span class="badge <?= $statutBadge[$b['statut']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($b['statut'], ENT_QUOTES) ?></span></td>
                <td class="px-4 py-2 text-slate-600"><?= $b['size_bytes'] ? $fmtSize((int)$b['size_bytes']) : '—' ?></td>
                <td class="px-4 py-2"><?= $b['encrypted'] ? '<i data-lucide="lock" class="w-3.5 h-3.5 text-emerald-600"></i>' : '<i data-lucide="unlock" class="w-3.5 h-3.5 text-slate-300"></i>' ?></td>
                <td class="px-4 py-2 text-slate-500 text-xs"><?= htmlspecialchars((string)$b['created_at'], ENT_QUOTES) ?></td>
                <td class="px-4 py-2 col-actions">
                    <?php if ($b['statut'] === 'success'): ?>
                    <div class="flex items-center gap-1.5">
                        <a href="<?= BASE_URL ?>/platform/backups/<?= (int)$b['id'] ?>/telecharger" class="btn btn-sm btn-secondary" title="Télécharger"><i data-lucide="download" class="w-3.5 h-3.5"></i></a>
                        <form method="POST" action="<?= BASE_URL ?>/platform/backups/<?= (int)$b['id'] ?>/verifier" class="inline">
                            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" title="Vérifier l'intégrité"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i></button>
                        </form>
                        <?php if ($b['type'] === 'global' || $b['type'] === 'manual'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/platform/backups/<?= (int)$b['id'] ?>/restaurer-verif" class="inline" onsubmit="return confirm('Restaurer dans une base temporaire de vérification (supprimée immédiatement après) ?');">
                            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" title="Vérifier par restauration"><i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($b['type'] === 'tenant'): ?>
                        <button type="button" class="btn btn-sm btn-danger" title="Restaurer chez ce tenant" onclick="document.getElementById('restore-<?= (int)$b['id'] ?>').classList.toggle('hidden')"><i data-lucide="upload" class="w-3.5 h-3.5"></i></button>
                        <?php endif; ?>
                    </div>
                    <?php if ($b['type'] === 'tenant'): ?>
                    <form id="restore-<?= (int)$b['id'] ?>" method="POST" action="<?= BASE_URL ?>/platform/backups/<?= (int)$b['id'] ?>/restaurer-tenant" class="hidden mt-2 flex items-center gap-2">
                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                        <input type="hidden" name="etablissement_id" value="<?= (int)$b['etablissement_id'] ?>">
                        <input type="text" name="confirmation" class="form-input text-xs" placeholder="Tapez le slug pour confirmer" required>
                        <button type="submit" class="btn btn-sm btn-danger">Confirmer la restauration</button>
                    </form>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-xs text-slate-400"><?= $b['error_message'] ? htmlspecialchars(substr($b['error_message'], 0, 60), ENT_QUOTES) : '' ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
            <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Aucune sauvegarde pour le moment.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Historique des restaurations -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="rotate-ccw" class="w-4 h-4 text-violet-600"></i>Historique des restaurations
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-2 text-left">Type</th>
                <th class="px-4 py-2 text-left">Sauvegarde</th>
                <th class="px-4 py-2 text-left">Statut</th>
                <th class="px-4 py-2 text-left">Lignes</th>
                <th class="px-4 py-2 text-left">Durée</th>
                <th class="px-4 py-2 text-left">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($restoreHistory as $r): ?>
            <tr>
                <td class="px-4 py-2"><?= htmlspecialchars($r['type'], ENT_QUOTES) ?></td>
                <td class="px-4 py-2 text-slate-500">#<?= (int)$r['backup_id'] ?> (<?= $typeLabel[$r['backup_type']] ?? $r['backup_type'] ?>)</td>
                <td class="px-4 py-2"><span class="badge <?= $statutBadge[$r['statut']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($r['statut'], ENT_QUOTES) ?></span></td>
                <td class="px-4 py-2 text-slate-600"><?= $r['rows_restored'] ?? '—' ?></td>
                <td class="px-4 py-2 text-slate-500"><?= $r['duration_ms'] ? (int)$r['duration_ms'] . ' ms' : '—' ?></td>
                <td class="px-4 py-2 text-slate-500 text-xs"><?= htmlspecialchars((string)$r['created_at'], ENT_QUOTES) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($restoreHistory)): ?>
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucune restauration effectuée.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
