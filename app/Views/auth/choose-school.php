<?php
/** @var array $schools */
/** @var int|null $lastUsedId */
$branding = $branding ?? \Core\Tenant\BrandingService::forCurrentRequest();
?>

<div class="text-center mb-6">
    <h2 class="text-2xl font-bold text-slate-900 mb-2">Choisir un établissement</h2>
    <p class="text-sm text-slate-500">
        Bonjour <?= htmlspecialchars($userName, ENT_QUOTES) ?>, vous appartenez à plusieurs établissements.
        Sélectionnez celui auquel vous souhaitez accéder.
    </p>
</div>

<?php if (\Core\Session::hasFlash('errors')): ?>
<div class="alert alert-danger mb-4" role="alert">
    <i data-lucide="alert-circle" style="width:1rem;height:1rem;flex-shrink:0"></i>
    <div style="flex:1">
        <?php foreach ((array)\Core\Session::getFlash('errors') as $msgs): foreach ((array)$msgs as $msg): ?>
        <div style="font-size:0.875rem"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
        <?php endforeach; endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/choisir-etablissement" class="space-y-3">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <?php foreach ($schools as $school): ?>
    <button type="submit" name="etablissement_id" value="<?= (int)$school['id'] ?>"
            class="w-full flex items-center gap-3 rounded-xl border-2 <?= $school['id'] === $lastUsedId ? 'border-violet-400 bg-violet-50' : 'border-slate-200 bg-white' ?> px-4 py-3 text-left hover:border-violet-400 hover:bg-violet-50 transition-colors">
        <div class="w-10 h-10 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center font-bold shrink-0">
            <?= htmlspecialchars(strtoupper(substr($school['nom_court'], 0, 1)), ENT_QUOTES) ?>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($school['nom'], ENT_QUOTES) ?></div>
            <?php if ($school['id'] === $lastUsedId): ?>
            <div class="text-xs text-violet-600">Dernier établissement utilisé</div>
            <?php endif; ?>
        </div>
        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 shrink-0"></i>
    </button>
    <?php endforeach; ?>
</form>
