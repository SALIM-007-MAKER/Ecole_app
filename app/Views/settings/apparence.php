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
            <i data-lucide="palette" class="w-5 h-5 text-violet-600"></i>
            Apparence
        </h2>
        <p class="text-sm text-slate-500">Logo, couleurs et thème visuel de votre espace</p>
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

<form method="POST" action="<?= BASE_URL ?>/parametres/branding" enctype="multipart/form-data" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <input type="hidden" name="_retour" value="apparence">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="swatch-book" class="w-4 h-4 text-violet-600"></i>Couleurs &amp; thème
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label" for="primary_color">Couleur primaire</label>
                <div class="flex items-center gap-2">
                    <input type="color" id="primary_color_picker" value="<?= $val('primary_color', $branding->primaryColor) ?>"
                           onchange="document.getElementById('primary_color').value=this.value" class="h-10 w-12 rounded border border-slate-300">
                    <input type="text" id="primary_color" name="primary_color" class="form-input font-mono text-sm" value="<?= $val('primary_color', $branding->primaryColor) ?>">
                </div>
            </div>
            <div>
                <label class="form-label" for="secondary_color">Couleur secondaire</label>
                <div class="flex items-center gap-2">
                    <input type="color" id="secondary_color_picker" value="<?= $val('secondary_color', $branding->secondaryColor) ?>"
                           onchange="document.getElementById('secondary_color').value=this.value" class="h-10 w-12 rounded border border-slate-300">
                    <input type="text" id="secondary_color" name="secondary_color" class="form-input font-mono text-sm" value="<?= $val('secondary_color', $branding->secondaryColor) ?>">
                </div>
            </div>
            <div>
                <label class="form-label" for="theme_mode">Thème</label>
                <select id="theme_mode" name="theme_mode" class="form-input">
                    <option value="light" <?= $branding->themeMode === 'light' ? 'selected' : '' ?>>Clair</option>
                    <option value="dark"  <?= $branding->themeMode === 'dark'  ? 'selected' : '' ?>>Sombre</option>
                    <option value="auto"  <?= $branding->themeMode === 'auto'  ? 'selected' : '' ?>>Automatique (système)</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="font_family">Police</label>
                <select id="font_family" name="font_family" class="form-input">
                    <?php foreach (['Inter', 'Roboto', 'Poppins', 'Open Sans'] as $font): ?>
                    <option value="<?= $font ?>" <?= $branding->fontFamily === $font ? 'selected' : '' ?>><?= $font ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="image" class="w-4 h-4 text-violet-600"></i>Logo, favicon &amp; image de connexion
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div>
                <label class="form-label">Logo actuel</label>
                <?php if ($branding->logoUrl): ?>
                <img src="<?= BASE_URL . htmlspecialchars($branding->logoUrl, ENT_QUOTES) ?>" alt="Logo" class="h-16 w-16 object-contain rounded-lg border border-slate-200 p-1 mb-2">
                <?php endif; ?>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-input text-xs">
                <p class="text-xs text-slate-400 mt-1">PNG/JPEG/WEBP/SVG, 2 Mo max</p>
            </div>
            <div>
                <label class="form-label">Favicon actuel</label>
                <?php if ($branding->faviconUrl): ?>
                <img src="<?= BASE_URL . htmlspecialchars($branding->faviconUrl, ENT_QUOTES) ?>" alt="Favicon" class="h-16 w-16 object-contain rounded-lg border border-slate-200 p-1 mb-2">
                <?php endif; ?>
                <input type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon" class="form-input text-xs">
                <p class="text-xs text-slate-400 mt-1">PNG ou ICO, 2 Mo max</p>
            </div>
            <div>
                <label class="form-label">Image de connexion</label>
                <?php if ($branding->loginImageUrl): ?>
                <img src="<?= BASE_URL . htmlspecialchars($branding->loginImageUrl, ENT_QUOTES) ?>" alt="Image de connexion" class="h-16 w-28 object-cover rounded-lg border border-slate-200 mb-2">
                <?php endif; ?>
                <input type="file" name="login_image" accept="image/png,image/jpeg,image/webp" class="form-input text-xs">
                <p class="text-xs text-slate-400 mt-1">Affichée sur l'écran de connexion, optionnelle</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="layout" class="w-4 h-4 text-violet-600"></i>Paramètres d'affichage
        </div>
        <div class="p-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="show_breadcrumbs" value="1" <?= $branding->showBreadcrumbs ? 'checked' : '' ?> class="accent-violet-600">
                <span class="text-sm text-slate-700">Afficher le fil d'Ariane</span>
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
