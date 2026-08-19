<?php
$errors                 = $errors                 ?? [];
$passwordMinLength      = $passwordMinLength       ?? 8;
$sessionLifetimeMinutes = $sessionLifetimeMinutes  ?? 120;
$canEdit                = $canEdit                 ?? false;
$old                    = \Core\Session::getFlash('old', []);
$val                    = fn(string $key, mixed $default) => htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="shield" class="w-5 h-5 text-violet-600"></i>
            Sécurité
        </h2>
        <p class="text-sm text-slate-500">Politique de mot de passe et durée de session</p>
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

<form method="POST" action="<?= BASE_URL ?>/parametres/securite" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="key" class="w-4 h-4 text-violet-600"></i>Politique de mot de passe
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="password_min_length">Longueur minimale</label>
                <input type="number" id="password_min_length" name="password_min_length" min="6" max="32"
                       value="<?= $val('password_min_length', $passwordMinLength) ?>" class="form-input">
                <p class="text-xs text-slate-400 mt-1">S'applique à la création et au changement de mot de passe</p>
            </div>
            <div>
                <label class="form-label" for="session_lifetime_minutes">Durée de session (minutes)</label>
                <input type="number" id="session_lifetime_minutes" name="session_lifetime_minutes" min="15" max="1440"
                       value="<?= $val('session_lifetime_minutes', $sessionLifetimeMinutes) ?>" class="form-input">
                <p class="text-xs text-slate-400 mt-1">Durée d'inactivité avant déconnexion automatique</p>
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
