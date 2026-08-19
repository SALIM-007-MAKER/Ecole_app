<?php
/** @var \Core\Tenant\BrandingData $branding */
$errors = $errors ?? [];
$old    = \Core\Session::getFlash('old', []);
$val    = fn(string $key, mixed $default) => htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
?>

<!-- Page header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="school" class="w-5 h-5 text-violet-600"></i>
            Établissement
        </h2>
        <p class="text-sm text-slate-500">Identité, adresse et coordonnées de votre établissement — affichées sur les documents officiels</p>
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

<form method="POST" action="<?= BASE_URL ?>/parametres/branding" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <input type="hidden" name="_retour" value="etablissement">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="badge-check" class="w-4 h-4 text-violet-600"></i>Identité
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="form-label" for="app_name">Nom de l'établissement</label>
                <input type="text" id="app_name" name="app_name" class="form-input" value="<?= $val('app_name', $branding->appName) ?>">
                <p class="text-xs text-slate-400 mt-1">Utilisé sur les bulletins, factures, reçus et communications</p>
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="welcome_message">Message de bienvenue (page de connexion)</label>
                <textarea id="welcome_message" name="welcome_message" class="form-input" rows="2"><?= $val('welcome_message', (string)$branding->welcomeMessage) ?></textarea>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="contact" class="w-4 h-4 text-violet-600"></i>Adresse &amp; contacts
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="contact_phone">Téléphone</label>
                <input type="text" id="contact_phone" name="contact_phone" class="form-input" value="<?= $val('contact_phone', (string)$branding->contactPhone) ?>">
            </div>
            <div>
                <label class="form-label" for="contact_email">Email</label>
                <input type="email" id="contact_email" name="contact_email" class="form-input" value="<?= $val('contact_email', (string)$branding->contactEmail) ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="contact_address">Adresse</label>
                <input type="text" id="contact_address" name="contact_address" class="form-input" value="<?= $val('contact_address', (string)$branding->contactAddress) ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="footer_text">Texte du pied de page (documents &amp; emails)</label>
                <input type="text" id="footer_text" name="footer_text" class="form-input" value="<?= $val('footer_text', (string)$branding->footerText) ?>">
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
