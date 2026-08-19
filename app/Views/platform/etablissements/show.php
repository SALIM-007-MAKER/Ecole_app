<?php
/** @var array $etab, $plans, $canManage */
$statutBadge = [
    'trial' => 'badge-secondary', 'active' => 'badge-success', 'suspended' => 'badge-warning',
    'cancelled' => 'badge-danger', 'archived' => 'badge-secondary',
];
?>
<a href="<?= BASE_URL ?>/platform/etablissements" class="text-sm text-slate-500 hover:underline flex items-center gap-1 mb-4"><i data-lucide="arrow-left" class="w-4 h-4"></i>Retour à la liste</a>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="w-5 h-5 text-violet-600"></i><?= htmlspecialchars($etab['nom'], ENT_QUOTES) ?>
            <span class="badge <?= $statutBadge[$etab['statut']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($etab['statut'], ENT_QUOTES) ?></span>
        </h2>
        <p class="text-sm text-slate-500 font-mono"><?= htmlspecialchars($etab['slug'], ENT_QUOTES) ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/domaines" class="btn btn-secondary btn-sm">
            <i data-lucide="globe" class="w-4 h-4"></i>Domaines
        </a>
        <a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/quotas" class="btn btn-secondary btn-sm">
            <i data-lucide="database" class="w-4 h-4"></i>Quotas
        </a>
        <a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/monitoring" class="btn btn-secondary btn-sm">
            <i data-lucide="activity" class="w-4 h-4"></i>Monitoring
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 grid grid-cols-2 gap-4 text-sm">
            <div><p class="text-slate-400 text-xs">Type</p><p class="text-slate-800"><?= htmlspecialchars($etab['type'], ENT_QUOTES) ?></p></div>
            <div><p class="text-slate-400 text-xs">Pays</p><p class="text-slate-800"><?= htmlspecialchars($etab['pays'], ENT_QUOTES) ?></p></div>
            <div><p class="text-slate-400 text-xs">Quota stockage</p><p class="text-slate-800"><?= (int)$etab['storage_quota_mb'] ?> Mo</p></div>
            <div><p class="text-slate-400 text-xs">Max utilisateurs</p><p class="text-slate-800"><?= $etab['max_users'] ?? '—' ?></p></div>
            <div><p class="text-slate-400 text-xs">Max élèves</p><p class="text-slate-800"><?= $etab['max_eleves'] ?? '—' ?></p></div>
            <div><p class="text-slate-400 text-xs">Créé le</p><p class="text-slate-800"><?= htmlspecialchars((string)$etab['created_at'], ENT_QUOTES) ?></p></div>
            <?php if ($etab['suspension_reason']): ?>
            <div class="col-span-2"><p class="text-slate-400 text-xs">Raison de suspension</p><p class="text-slate-800"><?= htmlspecialchars($etab['suspension_reason'], ENT_QUOTES) ?></p></div>
            <?php endif; ?>
        </div>

        <?php if ($canManage): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
            <p class="text-sm font-semibold text-slate-900 mb-3">Assigner un plan</p>
            <form method="POST" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/plan" class="flex items-end gap-3">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <select name="plan_id" class="form-input">
                    <?php foreach ($plans as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$etab['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nom'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Assigner (copie les quotas)</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
    <div class="space-y-3">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-3">
            <p class="text-sm font-semibold text-slate-900">Cycle de vie</p>
            <?php if ($etab['statut'] !== 'active'): ?>
            <form method="POST" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/activer">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="btn btn-secondary w-full"><i data-lucide="play" class="w-4 h-4"></i>Activer</button>
            </form>
            <?php endif; ?>
            <?php if ($etab['statut'] === 'active'): ?>
            <form method="POST" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/suspendre">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <input type="text" name="reason" class="form-input mb-2" placeholder="Raison (optionnel)">
                <button type="submit" class="btn btn-secondary w-full"><i data-lucide="pause" class="w-4 h-4"></i>Suspendre</button>
            </form>
            <?php endif; ?>
            <?php if ($etab['statut'] !== 'archived'): ?>
            <form method="POST" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/archiver" onsubmit="return confirm('Archiver cet établissement ?');">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="btn btn-secondary w-full"><i data-lucide="archive" class="w-4 h-4"></i>Archiver</button>
            </form>
            <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/restaurer">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="btn btn-secondary w-full"><i data-lucide="rotate-ccw" class="w-4 h-4"></i>Restaurer (actif)</button>
            </form>
            <?php endif; ?>
            <form method="POST" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/supprimer" onsubmit="return confirm('Supprimer logiquement cet établissement ? Les données sont conservées.');">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="btn btn-danger w-full"><i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer (logique)</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
