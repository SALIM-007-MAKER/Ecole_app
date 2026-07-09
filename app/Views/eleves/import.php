<?php
$results = \Core\Session::getFlash('import_results');
$classes = $classes ?? [];
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="upload-cloud" class="w-5 h-5 text-violet-600"></i>
            Importer des élèves
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Import en masse depuis un fichier CSV</p>
    </div>
    <a href="<?= BASE_URL ?>/eleves" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour à la liste
    </a>
</div>

<?php if ($results): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm <?= $results['imported'] > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900' ?> mb-5" role="alert">
    <i data-lucide="<?= $results['imported'] > 0 ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <div class="flex-1">
        <div class="font-semibold text-sm mb-2">Résultats de l'import</div>
        <div class="flex flex-wrap gap-2 mb-1">
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700"><?= $results['imported'] ?> importé(s)</span>
            <?php if ($results['skipped'] > 0): ?>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800"><?= $results['skipped'] ?> ignoré(s)</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($results['errors'])): ?>
        <details class="mt-2">
            <summary class="text-sm cursor-pointer hover:text-slate-700 font-medium">
                Voir les erreurs (<?= count($results['errors']) ?>)
            </summary>
            <ul class="mt-2 space-y-0.5 list-disc list-inside text-sm text-slate-600 bg-white/60 rounded-lg p-3">
                <?php foreach ($results['errors'] as $err): ?>
                <li><?= htmlspecialchars($err, ENT_QUOTES) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

    <!-- ── Étape 1 : Modèle + Étape 2 : Upload ───────────────────────────── -->
    <div class="lg:col-span-3 space-y-4">

        <!-- Étape 1 : Télécharger le modèle -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-violet-100 text-violet-600 text-xs font-bold flex items-center justify-center">1</span>
                    <i data-lucide="download" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Télécharger le modèle</span>
                </div>
            </div>
            <div class="p-5 p-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                        <i data-lucide="file-spreadsheet" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-700">Modèle CSV élèves</p>
                        <p class="text-xs text-slate-400 mt-0.5">Remplissez le modèle avec vos données avant l'import</p>
                    </div>
                    <a href="<?= BASE_URL ?>/eleves/import?template=1"
                       class="btn btn-outline shrink-0">
                        <i data-lucide="download" class="w-4 h-4"></i>Télécharger
                    </a>
                </div>
            </div>
        </div>

        <!-- Étape 2 : Uploader le fichier -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-violet-100 text-violet-600 text-xs font-bold flex items-center justify-center">2</span>
                    <i data-lucide="file-up" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Sélectionner le fichier CSV</span>
                </div>
            </div>
            <div class="p-5 p-5">
                <form method="POST" action="<?= BASE_URL ?>/eleves/import"
                      enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

                    <!-- Drop zone -->
                    <div id="dropZone"
                         class="border-2 border-dashed border-slate-200 rounded-xl p-8 text-center
                                hover:border-violet-300 hover:bg-violet-50/50 transition-colors cursor-pointer mb-4"
                         ondragover="event.preventDefault();this.classList.add('border-violet-400','bg-violet-50')"
                         ondragleave="this.classList.remove('border-violet-400','bg-violet-50')"
                         ondrop="handleDrop(event)"
                         onclick="document.getElementById('csv_file').click()">
                        <i data-lucide="upload-cloud" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                        <p class="font-semibold text-slate-600 mb-1">Glissez votre fichier ici</p>
                        <p class="text-sm text-slate-400">ou <span class="text-violet-600 font-medium">cliquez pour parcourir</span></p>
                        <p class="text-xs text-slate-300 mt-2">Formats acceptés : .csv, .txt</p>
                        <div id="fileNameDisplay" class="mt-3 hidden">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-lg text-sm font-medium text-emerald-700">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                <span id="fileNameText"></span>
                            </span>
                        </div>
                    </div>
                    <input type="file" id="csv_file" name="csv_file"
                           accept=".csv,.txt" class="sr-only"
                           onchange="showFileName(this)">

                    <label class="flex items-center gap-3 cursor-pointer mb-5 p-3 rounded-lg bg-slate-50 border border-slate-100">
                        <input type="checkbox" id="skip_errors" name="skip_errors" value="1"
                               checked class="w-4 h-4 accent-violet-600">
                        <div>
                            <span class="text-sm font-medium text-slate-700">Ignorer les lignes avec erreurs</span>
                            <p class="text-xs text-slate-400">Les lignes invalides seront ignorées et l'import continue</p>
                        </div>
                    </label>

                    <button type="submit" class="btn btn-success w-full">
                        <i data-lucide="upload" class="w-4 h-4"></i>Lancer l'import
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Instructions ──────────────────────────────────────────────────── -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Format du fichier -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="info" class="w-4 h-4 text-sky-600"></i>
                <span class="font-semibold text-slate-700">Format du fichier CSV</span>
            </div>
            <div class="p-5 p-4 space-y-3">
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-xs">
                        <thead>
                            <tr>
                                <th>Colonne</th>
                                <th class="text-center">Requis</th>
                                <th>Exemple</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $cols = [
                                ['matricule',      false, '2024-0001'],
                                ['nom',            true,  'BENALI'],
                                ['prenom',         true,  'Khalid'],
                                ['sexe',           true,  'M ou F'],
                                ['date_naissance', true,  '2007-06-15'],
                                ['classe',         false, '1ère AS'],
                                ['telephone',      false, '0550000001'],
                                ['email',          false, 'e@edu.dz'],
                                ['adresse',        false, '1 Rue Alger'],
                            ];
                            foreach ($cols as [$col, $req, $ex]):
                            ?>
                            <tr>
                                <td class="font-mono text-violet-600"><?= $col ?></td>
                                <td class="text-center">
                                    <?php if ($req): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">Oui</span>
                                    <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-slate-500"><?= $ex ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800 text-xs">
                    <i data-lucide="lightbulb" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                    <ul class="flex-1 list-disc list-inside space-y-1">
                        <li>Séparateur : <span class="font-mono bg-white/60 px-1 rounded">;</span> (point-virgule)</li>
                        <li>Encodage : UTF-8 avec ou sans BOM</li>
                        <li>Dates au format <span class="font-mono bg-white/60 px-1 rounded">YYYY-MM-DD</span></li>
                        <li>Matricule auto-généré si absent</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Classes disponibles -->
        <?php if (!empty($classes)): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="building-2" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Classes disponibles</span>
            </div>
            <div class="p-5 p-4">
                <p class="text-xs text-slate-400 mb-2">Utilisez ces noms exacts dans la colonne <span class="font-mono text-violet-600">classe</span> :</p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($classes as $c): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700">
                        <?= htmlspecialchars($c->niveau . ' — ' . $c->nom, ENT_QUOTES) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showFileName(input) {
    const d = document.getElementById('fileNameDisplay');
    const t = document.getElementById('fileNameText');
    if (input.files?.[0]) {
        t.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' Ko)';
        d.classList.remove('hidden');
    }
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('dropZone');
    zone.classList.remove('border-violet-400','bg-violet-50');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const input = document.getElementById('csv_file');
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
        showFileName(input);
    }
}
</script>
