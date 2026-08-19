<?php
$errors     = $errors     ?? [];
$emailActif = $emailActif ?? true;
$smsActif   = $smsActif   ?? false;
$canEdit    = $canEdit    ?? false;
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="bell" class="w-5 h-5 text-violet-600"></i>
            Notifications
        </h2>
        <p class="text-sm text-slate-500">Canaux de notification activés pour l'établissement</p>
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
    Ces interrupteurs contrôlent les canaux disponibles pour l'ensemble de l'établissement.
    Chaque utilisateur peut ensuite affiner ses propres préférences depuis son profil.
</div>

<form method="POST" action="<?= BASE_URL ?>/parametres/notifications" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="email_actif" value="1" <?= $emailActif ? 'checked' : '' ?> class="accent-violet-600">
                <span>
                    <span class="block text-sm font-medium text-slate-800">Notifications par email</span>
                    <span class="block text-xs text-slate-400">Bulletins, annonces, rappels de paiement, etc.</span>
                </span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="sms_actif" value="1" <?= $smsActif ? 'checked' : '' ?> class="accent-violet-600">
                <span>
                    <span class="block text-sm font-medium text-slate-800">Notifications par SMS</span>
                    <span class="block text-xs text-slate-400">Nécessite une passerelle SMS configurée</span>
                </span>
            </label>
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
