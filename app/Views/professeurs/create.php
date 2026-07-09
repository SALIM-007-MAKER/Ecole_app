<?php
$errors = $errors ?? [];
$old    = $old    ?? [];
$grades = $grades ?? [];
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="user-plus" class="w-5 h-5 text-amber-500"></i>Nouvel enseignant
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Saisir les informations personnelles et professionnelles</p>
    </div>
    <a href="<?= BASE_URL ?>/professeurs" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour à la liste
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

<form method="POST" action="<?= BASE_URL ?>/professeurs/store"
      enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <!-- Colonne gauche : Photo + Statut -->
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="camera" class="w-4 h-4 text-amber-500"></i>
                    <span class="font-semibold text-slate-700">Photo</span>
                </div>
                <div class="p-5 p-5 text-center">
                    <div id="photoPlaceholder"
                         class="w-28 h-28 rounded-full bg-amber-50 flex items-center justify-center border-4 border-white shadow-sm mx-auto mb-4">
                        <i data-lucide="user" class="w-14 h-14 text-amber-400"></i>
                    </div>
                    <img id="photoPreview" src="" alt=""
                         class="hidden w-28 h-28 rounded-full object-cover border-4 border-white shadow-sm mx-auto mb-4">
                    <label for="photo" class="btn btn-outline cursor-pointer">
                        <i data-lucide="upload" class="w-4 h-4"></i>Choisir une photo
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/*" class="sr-only"
                           onchange="previewPhoto(this)">
                    <p class="text-xs text-slate-400 mt-2">JPG, PNG, WebP — max 3 Mo</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="toggle-right" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Statut</span>
                </div>
                <div class="p-5 p-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="actif" value="0">
                        <input type="checkbox" id="actif" name="actif" value="1"
                               <?= (($old['actif'] ?? 1) == 1) ? 'checked' : '' ?>
                               class="w-4 h-4 accent-violet-600">
                        <span class="font-semibold text-slate-700 text-sm">Enseignant actif</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Colonne droite : Formulaires -->
        <div class="lg:col-span-3 space-y-4">

            <!-- Informations personnelles -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="user-cog" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Informations personnelles</span>
                </div>
                <div class="p-5 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label" for="nom">
                                Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nom" name="nom"
                                   class="form-input uppercase <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                                   value="<?= htmlspecialchars($old['nom'] ?? '', ENT_QUOTES) ?>"
                                   required autofocus
                                   oninput="this.value=this.value.toUpperCase()">
                            <?php if (isset($errors['nom'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="prenom">
                                Prénom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="prenom" name="prenom"
                                   class="form-input <?= isset($errors['prenom']) ? 'border-red-400' : '' ?>"
                                   value="<?= htmlspecialchars($old['prenom'] ?? '', ENT_QUOTES) ?>"
                                   required>
                            <?php if (isset($errors['prenom'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['prenom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="date_recrutement">Date de recrutement</label>
                            <input type="date" id="date_recrutement" name="date_recrutement"
                                   class="form-input <?= isset($errors['date_recrutement']) ? 'border-red-400' : '' ?>"
                                   value="<?= htmlspecialchars($old['date_recrutement'] ?? '', ENT_QUOTES) ?>"
                                   max="<?= date('Y-m-d') ?>">
                            <?php if (isset($errors['date_recrutement'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['date_recrutement'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profil professionnel -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="graduation-cap" class="w-4 h-4 text-amber-500"></i>
                    <span class="font-semibold text-slate-700">Profil professionnel</span>
                </div>
                <div class="p-5 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="specialite">
                                Spécialité <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="specialite" name="specialite"
                                   class="form-input <?= isset($errors['specialite']) ? 'border-red-400' : '' ?>"
                                   value="<?= htmlspecialchars($old['specialite'] ?? '', ENT_QUOTES) ?>"
                                   placeholder="Ex : Mathématiques, Sciences…" required>
                            <?php if (isset($errors['specialite'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['specialite'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="grade">Grade</label>
                            <select id="grade" name="grade" class="form-input">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($grades as $g): ?>
                                <option value="<?= htmlspecialchars($g, ENT_QUOTES) ?>"
                                    <?= (($old['grade'] ?? '') === $g) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coordonnées -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="phone" class="w-4 h-4 text-sky-500"></i>
                    <span class="font-semibold text-slate-700">Coordonnées</span>
                </div>
                <div class="p-5 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label" for="telephone">Téléphone</label>
                            <div class="relative">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="phone" class="w-4 h-4"></i></span>
                                <input type="tel" id="telephone" name="telephone"
                                       class="form-input pl-9"
                                       value="<?= htmlspecialchars($old['telephone'] ?? '', ENT_QUOTES) ?>">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label" for="email">Email</label>
                            <div class="relative">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="mail" class="w-4 h-4"></i></span>
                                <input type="email" id="email" name="email"
                                       class="form-input pl-9 <?= isset($errors['email']) ? 'border-red-400' : '' ?>"
                                       value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <?php if (isset($errors['email'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['email'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="form-label" for="adresse">Adresse</label>
                            <textarea id="adresse" name="adresse" class="w-full min-h-24 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20" rows="2"><?= htmlspecialchars($old['adresse'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-success">
                    <i data-lucide="save" class="w-4 h-4"></i>Créer l'enseignant
                </button>
                <a href="<?= BASE_URL ?>/professeurs" class="btn btn-secondary">Annuler</a>
            </div>

        </div>
    </div>
</form>

<script>
function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('photoPreview');
        const ph  = document.getElementById('photoPlaceholder');
        img.src = e.target.result;
        img.classList.remove('hidden');
        ph.classList.add('hidden');
        lucide.createIcons({ nodes: [img] });
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
