<?php /** @var array $plans; @var bool $canManage; @var array $errors */ ?>
<div class="flex items-center justify-between gap-4 mb-6">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-violet-600"></i>Plans SaaS
    </h2>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <ul class="list-disc list-inside space-y-0.5"><?php foreach ($errors as $msg): ?><li class="text-sm"><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <?php foreach ($plans as $p): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 <?= $p['actif'] ? '' : 'opacity-50' ?>">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($p['nom'], ENT_QUOTES) ?></p>
            <span class="badge badge-secondary font-mono text-xs"><?= htmlspecialchars($p['code'], ENT_QUOTES) ?></span>
        </div>
        <p class="text-2xl font-bold text-violet-700 mb-3"><?= number_format((float)$p['prix_mensuel'], 0) ?> <span class="text-sm font-normal text-slate-400"><?= htmlspecialchars($p['devise'], ENT_QUOTES) ?>/mois</span></p>
        <ul class="text-xs text-slate-600 space-y-1 mb-4">
            <li><?= (int)$p['max_users'] ?> utilisateurs max</li>
            <li><?= (int)$p['max_eleves'] ?> élèves max</li>
            <li><?= (int)$p['storage_quota_mb'] ?> Mo stockage</li>
            <li><?= (int)$p['api_calls_per_day'] ?> appels API/jour</li>
            <li><?= (int)$p['tenants_count'] ?> établissement(s) sur ce plan</li>
        </ul>
        <?php if ($canManage): ?>
        <form method="POST" action="<?= BASE_URL ?>/platform/plans/<?= (int)$p['id'] ?>/toggle">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <input type="hidden" name="active" value="<?= $p['actif'] ? '0' : '1' ?>">
            <button type="submit" class="btn btn-sm btn-secondary w-full"><?= $p['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($canManage): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="plus-circle" class="w-4 h-4 text-violet-600"></i>Créer un plan
    </div>
    <form method="POST" action="<?= BASE_URL ?>/platform/plans" class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <div><label class="form-label" for="code">Code</label><input type="text" id="code" name="code" class="form-input font-mono" required></div>
        <div><label class="form-label" for="nom">Nom</label><input type="text" id="nom" name="nom" class="form-input" required></div>
        <div><label class="form-label" for="max_users">Max utilisateurs</label><input type="number" id="max_users" name="max_users" class="form-input" value="50" min="1"></div>
        <div><label class="form-label" for="max_eleves">Max élèves</label><input type="number" id="max_eleves" name="max_eleves" class="form-input" value="500" min="1"></div>
        <div><label class="form-label" for="storage_quota_mb">Quota stockage (Mo)</label><input type="number" id="storage_quota_mb" name="storage_quota_mb" class="form-input" value="1024" min="1"></div>
        <div><label class="form-label" for="api_calls_per_day">Appels API/jour</label><input type="number" id="api_calls_per_day" name="api_calls_per_day" class="form-input" value="10000" min="1"></div>
        <div><label class="form-label" for="prix_mensuel">Prix mensuel</label><input type="number" id="prix_mensuel" name="prix_mensuel" class="form-input" value="0" step="0.01" min="0"></div>
        <div><label class="form-label" for="prix_annuel">Prix annuel</label><input type="number" id="prix_annuel" name="prix_annuel" class="form-input" value="0" step="0.01" min="0"></div>
        <div class="sm:col-span-3 flex justify-end">
            <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i>Créer le plan</button>
        </div>
    </form>
</div>
<?php endif; ?>
