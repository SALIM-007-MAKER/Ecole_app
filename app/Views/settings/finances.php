<?php
$errors  = $errors  ?? [];
$devise  = $devise  ?? 'XOF';
$devises = $devises ?? [];
$canEdit = $canEdit ?? false;
$old     = \Core\Session::getFlash('old', []);
$val     = fn(string $key, string $default) => htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="coins" class="w-5 h-5 text-violet-600"></i>
            Finances
        </h2>
        <p class="text-sm text-slate-500">Devise par défaut de l'établissement</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msg): ?>
        <li class="text-sm"><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-sky-50 border border-sky-200 rounded-xl p-4 mb-6 text-sm text-sky-700">
    <i data-lucide="info" class="w-4 h-4 inline-block align-text-top mr-1"></i>
    Chaque type de frais peut avoir sa propre devise (configurable dans Finance → Types de frais).
    La devise ci-dessous n'est utilisée qu'en repli lorsqu'un document ne précise pas la sienne.
</div>

<form method="POST" action="<?= BASE_URL ?>/parametres/finances" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="devise_defaut">Devise par défaut</label>
                <select id="devise_defaut" name="devise_defaut" class="form-input">
                    <?php foreach ($devises as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code, ENT_QUOTES) ?>" <?= $val('devise_defaut', $devise) === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-1">
                    Affichée sur les factures et reçus lorsqu'aucune devise spécifique n'est définie.
                </p>
            </div>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
        </button>
    </div>
    <?php endif; ?>

    </fieldset>
</form>
