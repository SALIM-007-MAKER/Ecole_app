<?php
$errors  = $errors  ?? [];
$old     = $old     ?? [];
$niveaux = $niveaux ?? [];
$annee   = date('Y') . '-' . (date('Y') + 1);
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="w-5 h-5 text-violet-600"></i>Nouvelle classe
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Créer une classe et définir sa capacité</p>
    </div>
    <a href="<?= BASE_URL ?>/classes" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msgs): foreach ((array)$msgs as $m): ?>
        <li class="text-sm"><?= htmlspecialchars($m, ENT_QUOTES) ?></li>
        <?php endforeach; endforeach; ?>
    </ul>
    <button class="ml-auto inline-flex rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100" onclick="this.closest('[role=alert]').remove()">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/classes/store" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Formulaire principal -->
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="building-2" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Informations de la classe</span>
                </div>
                <div class="p-5 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <div class="sm:col-span-2">
                            <label class="form-label" for="niveau">
                                Niveau <span class="text-red-500">*</span>
                            </label>
                            <select id="niveau" name="niveau"
                                    class="form-input <?= isset($errors['niveau']) ? 'border-red-400' : '' ?>"
                                    required onchange="updatePreview()">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($niveaux as $groupe => $vals): ?>
                                <optgroup label="<?= htmlspecialchars($groupe, ENT_QUOTES) ?>">
                                    <?php foreach ($vals as $v): ?>
                                    <option value="<?= $v ?>" <?= ($old['niveau'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $v ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['niveau'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['niveau'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label" for="nom">
                                Lettre / Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nom" name="nom"
                                   class="form-input uppercase font-bold tracking-widest <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                                   value="<?= htmlspecialchars($old['nom'] ?? '', ENT_QUOTES) ?>"
                                   placeholder="A" maxlength="10" required
                                   oninput="this.value=this.value.toUpperCase();updatePreview()">
                            <?php if (isset($errors['nom'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label" for="annee_scolaire">
                                Année scolaire <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="annee_scolaire" name="annee_scolaire"
                                   class="form-input <?= isset($errors['annee_scolaire']) ? 'border-red-400' : '' ?>"
                                   value="<?= htmlspecialchars($old['annee_scolaire'] ?? $annee, ENT_QUOTES) ?>"
                                   pattern="\d{4}-\d{4}" placeholder="2024-2025" required
                                   oninput="updatePreview()">
                            <?php if (isset($errors['annee_scolaire'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['annee_scolaire'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label" for="max_eleves">Capacité maximale</label>
                            <div class="relative flex items-stretch">
                                <input type="number" id="max_eleves" name="max_eleves"
                                       class="form-input <?= isset($errors['max_eleves']) ? 'border-red-400' : '' ?>"
                                       value="<?= htmlspecialchars($old['max_eleves'] ?? '40', ENT_QUOTES) ?>"
                                       min="1" max="100" oninput="updatePreview()">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none">élèves</span>
                            </div>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="form-label" for="description">
                                Description <span class="text-slate-400 font-normal">(optionnel)</span>
                            </label>
                            <textarea id="description" name="description" class="w-full min-h-24 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20" rows="2"
                                      placeholder="Filière, options, remarques…"><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar : Aperçu + conseil -->
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                    <span class="font-semibold text-slate-700">Aperçu</span>
                </div>
                <div class="p-5 p-5 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-violet-50 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="building-2" class="w-8 h-8 text-violet-500"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 text-lg">
                        <span id="prevNiveau" class="text-slate-400">—</span>
                        <span id="prevNom" class="text-violet-600 ml-1">—</span>
                    </h3>
                    <p id="prevAnnee" class="text-sm text-slate-400 mt-0.5">—</p>
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">
                            0 / <span id="prevMax">40</span> élèves
                        </span>
                    </div>
                </div>
            </div>

            <div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800">
                <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
                <p class="text-sm flex-1">
                    Utilisez une lettre (A, B, C…) ou un identifiant court pour distinguer les groupes d'un même niveau.
                </p>
            </div>
        </div>

    </div>

    <div class="flex items-center gap-3 mt-5">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Créer la classe
        </button>
        <a href="<?= BASE_URL ?>/classes" class="btn btn-secondary">Annuler</a>
    </div>
</form>

<script>
function updatePreview() {
    document.getElementById('prevNiveau').textContent = document.getElementById('niveau').value || '—';
    document.getElementById('prevNom').textContent    = document.getElementById('nom').value || '—';
    document.getElementById('prevAnnee').textContent  = document.getElementById('annee_scolaire').value || '—';
    document.getElementById('prevMax').textContent    = document.getElementById('max_eleves').value || '40';
}
updatePreview();
</script>
