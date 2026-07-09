<?php
$classes = $classes ?? [];
$result  = $result  ?? null;
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="upload" class="w-5 h-5 text-violet-600"></i>
            Importer des élèves
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Importation en masse depuis un fichier CSV</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/scolarite/eleves" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour à la liste
    </a>
</div>

<?php if ($result !== null): ?>
<!-- Résultats de l'import -->
<div class="rounded-xl border <?= $result['imported'] > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' ?> p-5 mb-6">
    <div class="flex items-start gap-3">
        <i data-lucide="<?= $result['imported'] > 0 ? 'check-circle' : 'alert-triangle' ?>"
           class="w-5 h-5 shrink-0 <?= $result['imported'] > 0 ? 'text-emerald-600' : 'text-amber-500' ?>"></i>
        <div>
            <p class="font-semibold text-slate-900 mb-1">Import terminé</p>
            <p class="text-sm text-slate-700">
                <span class="font-bold text-emerald-700"><?= $result['imported'] ?></span> élève(s) importé(s) avec succès,
                <span class="font-bold text-amber-700"><?= $result['skipped'] ?></span> ignoré(s).
            </p>
            <?php if (!empty($result['errors'])): ?>
            <ul class="mt-3 space-y-1">
                <?php foreach (array_slice($result['errors'], 0, 10) as $err): ?>
                <li class="text-sm text-amber-800 flex items-start gap-1.5">
                    <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                    <?= htmlspecialchars($err, ENT_QUOTES) ?>
                </li>
                <?php endforeach; ?>
                <?php if (count($result['errors']) > 10): ?>
                <li class="text-sm text-slate-500">… et <?= count($result['errors']) - 10 ?> autre(s) erreur(s).</li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Formulaire d'upload -->
    <div class="lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <i data-lucide="file-up" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700 text-sm">Fichier CSV</span>
            </div>
            <div class="p-5">
                <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/eleves/import"
                      enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

                    <div class="mb-5">
                        <label class="form-label" for="csv_file">
                            Fichier CSV <span class="text-red-500">*</span>
                        </label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt"
                               class="block w-full text-sm text-slate-500
                                      file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                      file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700
                                      hover:file:bg-violet-100 cursor-pointer"
                               required>
                        <p class="text-xs text-slate-400 mt-1">Fichiers CSV ou TXT — séparateur ; ou , — encodage UTF-8</p>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="upload" class="w-4 h-4"></i>Lancer l'importation
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <i data-lucide="info" class="w-4 h-4 text-sky-600"></i>
                <span class="font-semibold text-slate-700 text-sm">Format attendu</span>
            </div>
            <div class="p-4">
                <p class="text-xs text-slate-500 mb-3">Le fichier CSV doit contenir les colonnes suivantes :</p>
                <div class="space-y-1.5">
                    <?php
                    $cols = [
                        ['nom',            'Obligatoire'],
                        ['prenom',         'Obligatoire'],
                        ['sexe',           'M ou F'],
                        ['date_naissance', 'YYYY-MM-DD'],
                        ['matricule',      'Auto si vide'],
                        ['classe',         'Nom de la classe'],
                        ['telephone',      'Optionnel'],
                        ['email',          'Optionnel'],
                        ['adresse',        'Optionnel'],
                    ];
                    foreach ($cols as [$col, $hint]):
                    ?>
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-mono text-violet-700 bg-violet-50 px-1.5 py-0.5 rounded"><?= $col ?></span>
                        <span class="text-slate-400"><?= $hint ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($classes)): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <i data-lucide="building-2" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700 text-sm">Classes disponibles</span>
            </div>
            <div class="p-4 max-h-48 overflow-y-auto">
                <ul class="space-y-1">
                    <?php foreach ($classes as $c): ?>
                    <li class="text-xs text-slate-600 font-mono">
                        <?= htmlspecialchars($c->nom, ENT_QUOTES) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
