<?php
$evaluation = $evaluation ?? null;
$user       = $user       ?? [];
if (!$evaluation) return;

$flash = \Core\Session::getFlash();
?>

<div class="max-w-lg mx-auto">

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Import CSV — Notes</h2>
        <p class="text-sm text-slate-500">
            <?= htmlspecialchars($evaluation->libelle, ENT_QUOTES) ?>
            · /<?= number_format((float)$evaluation->note_max, 0) ?>
        </p>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<!-- Format attendu -->
<div class="bg-slate-50 rounded-xl border border-slate-200 p-4 mb-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
        <i data-lucide="info" class="w-4 h-4 text-violet-500"></i>
        Format CSV attendu
    </h3>
    <pre class="text-xs font-mono text-slate-600 bg-white border border-slate-200 rounded p-3 overflow-x-auto">eleve_id,valeur,est_absent,commentaire
42,15.5,0,Bien
57,,1,Absent justifié
83,12,0,</pre>
    <ul class="mt-3 text-xs text-slate-500 space-y-1">
        <li>• Ligne 1 = en-tête (ignorée)</li>
        <li>• <code>eleve_id</code> obligatoire</li>
        <li>• <code>valeur</code> vide si absent</li>
        <li>• <code>est_absent</code> : 1 = absent, 0 = présent</li>
        <li>• Barème max : <?= number_format((float)$evaluation->note_max, 0) ?></li>
    </ul>
</div>

<form method="POST"
      action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes/importer"
      enctype="multipart/form-data"
      class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="mb-5">
        <label class="block text-sm font-medium text-slate-700 mb-2">
            Fichier CSV <span class="text-red-500">*</span>
        </label>
        <input type="file" name="csv" accept=".csv,text/csv"
               class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4
                      file:rounded-lg file:border-0 file:bg-violet-50 file:text-violet-700
                      file:font-medium hover:file:bg-violet-100 cursor-pointer">
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary flex-1">
            <i data-lucide="upload" class="w-4 h-4"></i>Importer
        </button>
        <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes"
           class="btn btn-secondary">Annuler</a>
    </div>
</form>

</div>
