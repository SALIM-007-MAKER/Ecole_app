<?php
$errors          = $errors          ?? [];
$prefixeRecu     = $prefixeRecu     ?? 'REC';
$prefixeFacture  = $prefixeFacture  ?? 'FCT';
$canEdit         = $canEdit         ?? false;
$old             = \Core\Session::getFlash('old', []);
$val             = fn(string $key, string $default) => htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="file-text" class="w-5 h-5 text-violet-600"></i>
            Documents
        </h2>
        <p class="text-sm text-slate-500">Numérotation des reçus et factures</p>
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

<form method="POST" action="<?= BASE_URL ?>/parametres/documents" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="hash" class="w-4 h-4 text-violet-600"></i>Préfixes de numérotation
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="prefixe_recu">Préfixe des reçus</label>
                <input type="text" id="prefixe_recu" name="prefixe_recu" maxlength="10"
                       value="<?= $val('prefixe_recu', $prefixeRecu) ?>" class="form-input font-mono uppercase">
                <p class="text-xs text-slate-400 mt-1">Ex : REC-2026-0001</p>
            </div>
            <div>
                <label class="form-label" for="prefixe_facture">Préfixe des factures</label>
                <input type="text" id="prefixe_facture" name="prefixe_facture" maxlength="10"
                       value="<?= $val('prefixe_facture', $prefixeFacture) ?>" class="form-input font-mono uppercase">
                <p class="text-xs text-slate-400 mt-1">Ex : FCT-2026-0001</p>
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
