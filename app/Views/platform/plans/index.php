<?php /** @var array $plans; @var bool $canManage; @var string $devise; @var array $errors */
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$montant = static fn(array $p, string $k): string => $p['devise'] === 'XOF'
    ? number_format((float)$p[$k], 0, ',', ' ')
    : number_format((float)$p[$k], 2, ',', ' ');
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-violet-600"></i>Plans SaaS
    </h2>
    <?php if ($canManage): ?>
    <a href="<?= BASE_URL ?>/platform/plans/create" class="btn btn-primary"><i data-lucide="plus-circle" class="w-4 h-4"></i>Nouveau plan</a>
    <?php endif; ?>
</div>

<?php if (!$canManage): ?>
<p class="text-sm text-slate-500 mb-4">Lecture seule : seul un super-administrateur de la plateforme peut créer, modifier ou archiver un plan.</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <ul class="list-disc list-inside space-y-0.5"><?php foreach ($errors as $msg): ?><li class="text-sm"><?= $e($msg) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($plans === []): ?>
<p class="text-sm text-slate-500">Aucun plan défini.</p>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <?php foreach ($plans as $p):
        $modules = json_decode((string)$p['modules_inclus'], true) ?: []; ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 <?= $p['actif'] ? '' : 'opacity-60' ?>">
        <div class="flex items-center justify-between mb-2 gap-2">
            <p class="text-sm font-bold text-slate-900"><?= $e($p['nom']) ?></p>
            <span class="badge badge-secondary font-mono text-xs"><?= $e($p['code']) ?></span>
        </div>
        <div class="flex flex-wrap gap-1.5 mb-3">
            <?php if (!$p['actif']): ?><span class="badge badge-secondary text-xs">Archivé</span><?php endif; ?>
            <?php if (!$p['visible_catalogue']): ?><span class="badge badge-secondary text-xs">Masqué</span><?php endif; ?>
            <?php if ($p['devise'] !== $devise): ?><span class="badge badge-warning text-xs" title="Devise de référence de la plateforme : <?= $e($devise) ?>. Aucun montant n'est converti automatiquement.">Devise à vérifier</span><?php endif; ?>
        </div>
        <p class="text-2xl font-bold text-violet-700 mb-1"><?= $montant($p, 'prix_mensuel') ?> <span class="text-sm font-normal text-slate-400"><?= $e($p['devise']) ?>/mois</span></p>
        <p class="text-xs text-slate-500 mb-3"><?= $montant($p, 'prix_annuel') ?> <?= $e($p['devise']) ?>/an</p>
        <ul class="text-xs text-slate-600 space-y-1 mb-4">
            <li><?= (int)$p['max_users'] ?> utilisateurs max</li>
            <li><?= (int)$p['max_eleves'] ?> élèves max</li>
            <li><?= (int)$p['storage_quota_mb'] ?> Mo stockage</li>
            <li><?= (int)$p['api_calls_per_day'] ?> appels API/jour</li>
            <li>Essai : <?= (int)$p['duree_essai_jours'] > 0 ? (int)$p['duree_essai_jours'] . ' jour(s)' : 'aucun' ?> · Grâce : <?= (int)$p['jours_grace'] ?> jour(s)</li>
            <li>Modules : <?= $modules === [] ? '—' : $e(implode(', ', $modules)) ?></li>
            <li><?= (int)$p['tenants_count'] ?> établissement(s) sur ce plan</li>
        </ul>
        <?php if ($canManage): ?>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/platform/plans/<?= (int)$p['id'] ?>/modifier" class="btn btn-sm btn-primary flex-1 text-center">Modifier</a>
            <form method="POST" action="<?= BASE_URL ?>/platform/plans/<?= (int)$p['id'] ?>/toggle" class="flex-1">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <input type="hidden" name="active" value="<?= $p['actif'] ? '0' : '1' ?>">
                <button type="submit" class="btn btn-sm btn-secondary w-full"><?= $p['actif'] ? 'Archiver' : 'Restaurer' ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
