<?php
/** @var ?array $plan; @var array $old; @var int $usage; @var list<string> $modules; @var string $devise; @var array $errors */
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$creation = $plan === null;
$val = static function (string $k, $defaut = '') use ($plan, $old) {
    if (array_key_exists($k, $old)) { return $old[$k]; }
    return $plan[$k] ?? $defaut;
};
$devisePlan = (string)$val('devise', $devise);
$prix = static function (string $k) use ($val, $devisePlan) {
    $v = $val($k, '0');
    return $devisePlan === 'XOF' && is_numeric($v) && fmod((float)$v, 1.0) === 0.0 ? (string)(int)$v : (string)$v;
};
$modulesCoches = array_key_exists('modules_present', $old)
    ? (array)($old['modules_inclus'] ?? [])
    : (json_decode((string)($plan['modules_inclus'] ?? '[]'), true) ?: []);
$action = $creation ? BASE_URL . '/platform/plans' : BASE_URL . '/platform/plans/' . (int)$plan['id'];
$visible = (string)$val('visible_catalogue', 1) === '1';
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-violet-600"></i><?= $creation ? 'Nouveau plan' : 'Modifier le plan « ' . $e($plan['nom']) . ' »' ?>
    </h2>
    <a href="<?= BASE_URL ?>/platform/plans" class="btn btn-secondary">Retour</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <ul class="list-disc list-inside space-y-0.5"><?php foreach ($errors as $msg): ?><li class="text-sm"><?= $e($msg) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="<?= $action ?>" class="rounded-xl border border-slate-200 bg-white shadow-sm p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <input type="hidden" name="modules_present" value="1">

    <div>
        <label class="form-label" for="code">Code</label>
        <?php if ($creation): ?>
        <input type="text" id="code" name="code" class="form-input font-mono" value="<?= $e($val('code')) ?>" required pattern="[a-z0-9_\-]{2,30}">
        <?php else: ?>
        <input type="text" id="code" class="form-input font-mono" value="<?= $e($plan['code']) ?>" disabled>
        <?php endif; ?>
    </div>
    <div class="sm:col-span-2"><label class="form-label" for="nom">Nom</label><input type="text" id="nom" name="nom" class="form-input" value="<?= $e($val('nom')) ?>" required maxlength="100"></div>
    <div class="sm:col-span-3"><label class="form-label" for="description">Description</label><textarea id="description" name="description" class="form-input" rows="2" maxlength="2000"><?= $e($val('description', '')) ?></textarea></div>

    <div><label class="form-label" for="max_users">Max utilisateurs</label><input type="number" id="max_users" name="max_users" class="form-input" value="<?= (int)$val('max_users', 50) ?>" min="1" max="65535"></div>
    <div><label class="form-label" for="max_eleves">Max élèves</label><input type="number" id="max_eleves" name="max_eleves" class="form-input" value="<?= (int)$val('max_eleves', 500) ?>" min="1" max="65535"></div>
    <div><label class="form-label" for="storage_quota_mb">Quota stockage (Mo)</label><input type="number" id="storage_quota_mb" name="storage_quota_mb" class="form-input" value="<?= (int)$val('storage_quota_mb', 1024) ?>" min="1"></div>
    <div><label class="form-label" for="api_calls_per_day">Appels API/jour</label><input type="number" id="api_calls_per_day" name="api_calls_per_day" class="form-input" value="<?= (int)$val('api_calls_per_day', 10000) ?>" min="1"></div>

    <div><label class="form-label" for="devise">Devise (ISO 4217)</label><input type="text" id="devise" name="devise" class="form-input font-mono uppercase" value="<?= $e($devisePlan) ?>" maxlength="3" pattern="[A-Za-z]{3}" required></div>
    <div><label class="form-label" for="prix_mensuel">Prix mensuel</label><input type="text" inputmode="decimal" id="prix_mensuel" name="prix_mensuel" class="form-input" value="<?= $e($prix('prix_mensuel')) ?>" required></div>
    <div><label class="form-label" for="prix_annuel">Prix annuel</label><input type="text" inputmode="decimal" id="prix_annuel" name="prix_annuel" class="form-input" value="<?= $e($prix('prix_annuel')) ?>" required></div>
    <p class="sm:col-span-3 text-xs text-slate-500">Le XOF n'a pas de décimales. Changer la devise ne convertit aucun montant : saisissez les prix dans la nouvelle devise.</p>

    <div><label class="form-label" for="duree_essai_jours">Durée d'essai (jours, 0 = aucun)</label><input type="number" id="duree_essai_jours" name="duree_essai_jours" class="form-input" value="<?= (int)$val('duree_essai_jours', 0) ?>" min="0" max="365"></div>
    <div><label class="form-label" for="jours_grace">Jours de grâce après expiration</label><input type="number" id="jours_grace" name="jours_grace" class="form-input" value="<?= (int)$val('jours_grace', 0) ?>" min="0" max="90"></div>
    <div><label class="form-label" for="ordre">Ordre d'affichage</label><input type="number" id="ordre" name="ordre" class="form-input" value="<?= (int)$val('ordre', 0) ?>" min="-32768" max="32767"></div>

    <div class="sm:col-span-3">
        <input type="hidden" name="visible_catalogue" value="0">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="visible_catalogue" value="1" <?= $visible ? 'checked' : '' ?>>
            Visible dans le catalogue (un plan masqué reste assignable par un opérateur)
        </label>
    </div>

    <fieldset class="sm:col-span-3">
        <legend class="form-label">Modules inclus</legend>
        <p class="text-xs text-slate-500 mb-2">Cocher un module l'inclut dans le plan ; cela n'active pas le module sur la plateforme.</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <?php foreach ($modules as $m): ?>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="modules_inclus[]" value="<?= $e($m) ?>" <?= in_array($m, $modulesCoches, true) ? 'checked' : '' ?>><?= $e($m) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <?php if (!$creation && $usage > 0): ?>
    <div class="sm:col-span-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="mb-2">Ce plan est utilisé par <strong><?= (int)$usage ?></strong> établissement(s). Changer le prix ou la devise est audité (avant / après) et exige une confirmation explicite.</p>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="confirmer_conditions" value="1">Je confirme la modification du prix ou de la devise de ce plan utilisé.</label>
    </div>
    <?php endif; ?>

    <div class="sm:col-span-3 flex justify-end">
        <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i><?= $creation ? 'Créer le plan' : 'Enregistrer' ?></button>
    </div>
</form>
