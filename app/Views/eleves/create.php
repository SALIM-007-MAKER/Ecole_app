<?php
$errors    = $errors    ?? [];
$old       = $old       ?? [];
$classes   = $classes   ?? [];
$parents   = $parents   ?? [];
$matricule = $matricule ?? '';

function oldVal(array $old, string $key, $eleve = null, string $field = ''): string {
    $v = $old[$key] ?? ($eleve ? ($eleve->{$field ?: $key} ?? '') : '');
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="user-plus" class="w-5 h-5 text-violet-600"></i>
            Nouvel élève
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Remplissez toutes les informations requises</p>
    </div>
    <a href="<?= BASE_URL ?>/eleves" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour à la liste
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <div class="flex-1">
        <div class="font-semibold text-sm mb-1">Veuillez corriger les erreurs suivantes :</div>
        <ul class="list-disc list-inside space-y-0.5">
            <?php foreach ($errors as $msgs): foreach ((array)$msgs as $m): ?>
            <li class="text-sm"><?= htmlspecialchars($m, ENT_QUOTES) ?></li>
            <?php endforeach; endforeach; ?>
        </ul>
    </div>
    <button class="ml-auto inline-flex rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100" onclick="this.closest('[role=alert]').remove()">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/eleves/store"
      enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Colonne gauche ─────────────────────────────────────────────── -->
        <div class="space-y-4">

            <!-- Photo -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="camera" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Photo de l'élève</span>
                </div>
                <div class="p-5 p-5 flex flex-col items-center gap-3">
                    <div class="w-28 h-28 rounded-full border-4 border-slate-100 overflow-hidden
                                bg-slate-50 flex items-center justify-center relative" id="photoWrap">
                        <i data-lucide="user" class="w-12 h-12 text-slate-300" id="photoPlaceholder"></i>
                        <img id="photoPreview" src="" class="hidden w-full h-full object-cover absolute inset-0" alt="">
                    </div>
                    <label class="btn btn-outline cursor-pointer" for="photo">
                        <i data-lucide="upload" class="w-4 h-4"></i>Choisir une photo
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/*"
                           class="sr-only" onchange="previewPhoto(this)">
                    <?php if (isset($errors['photo'])): ?>
                    <p class="text-xs text-red-500 text-center"><?= htmlspecialchars($errors['photo'][0], ENT_QUOTES) ?></p>
                    <?php else: ?>
                    <p class="text-xs text-slate-400 text-center">JPG, PNG, WebP — max 3 Mo</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Affectation -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="link" class="w-4 h-4 text-sky-600"></i>
                    <span class="font-semibold text-slate-700">Affectation</span>
                </div>
                <div class="p-5 p-4 space-y-3">
                    <div class="mb-4">
                        <label class="form-label" for="classe_id">Classe</label>
                        <select id="classe_id" name="classe_id"
                                class="form-input <?= isset($errors['classe_id']) ? 'border-red-400' : '' ?>">
                            <option value="">— Non affecté —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c->id ?>" <?= ($old['classe_id'] ?? '') == $c->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c->label, ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['classe_id'])): ?>
                        <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['classe_id'][0], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="parent_id">Parent responsable</label>
                        <select id="parent_id" name="parent_id" class="form-input">
                            <option value="">— Non affecté —</option>
                            <?php foreach ($parents as $p): ?>
                            <option value="<?= $p->id ?>" <?= ($old['parent_id'] ?? '') == $p->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim(($p->prenom ?? '') . ' ' . $p->nom), ENT_QUOTES) ?>
                                <?= $p->telephone ? ' — ' . htmlspecialchars($p->telephone, ENT_QUOTES) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Utilisateur avec le rôle Parent</p>
                    </div>
                </div>
            </div>

            <!-- Statut rapide -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="p-5 p-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="actif" value="0">
                        <input type="checkbox" id="actif" name="actif" value="1"
                               class="w-4 h-4 accent-violet-600 rounded"
                               <?= ($old['actif'] ?? 1) ? 'checked' : '' ?>>
                        <div>
                            <span class="text-sm font-semibold text-slate-700">Élève actif</span>
                            <p class="text-xs text-slate-400">Visible dans les listes et statistiques</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- ── Colonne droite ─────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Informations personnelles -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="user-cog" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Informations personnelles</span>
                </div>
                <div class="p-5 p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <!-- Matricule -->
                        <div class="mb-4">
                            <label class="form-label" for="matricule">
                                Matricule <span class="text-red-500">*</span>
                            </label>
                            <div class="relative flex items-stretch relative">
                                <input type="text" id="matricule" name="matricule"
                                       class="form-input font-mono text-sm pr-9 <?= isset($errors['matricule']) ? 'border-red-400' : '' ?>"
                                       value="<?= oldVal($old, 'matricule') ?: htmlspecialchars($matricule, ENT_QUOTES) ?>"
                                       placeholder="2024-0001" required>
                                <button type="button"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 btn btn-ghost btn-icon text-slate-400"
                                        title="Régénérer" onclick="regenMatricule()">
                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['matricule'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['matricule'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Nom -->
                        <div class="mb-4">
                            <label class="form-label" for="nom">
                                Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nom" name="nom"
                                   class="form-input <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                                   value="<?= oldVal($old, 'nom') ?>"
                                   placeholder="BENALI" required>
                            <?php if (isset($errors['nom'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Prénom -->
                        <div class="mb-4">
                            <label class="form-label" for="prenom">
                                Prénom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="prenom" name="prenom"
                                   class="form-input <?= isset($errors['prenom']) ? 'border-red-400' : '' ?>"
                                   value="<?= oldVal($old, 'prenom') ?>"
                                   placeholder="Khalid" required>
                            <?php if (isset($errors['prenom'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['prenom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Sexe -->
                        <div class="mb-4">
                            <label class="form-label">Sexe <span class="text-red-500">*</span></label>
                            <div class="flex gap-4 mt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sexe" value="M"
                                           class="accent-violet-600"
                                           <?= ($old['sexe'] ?? '') === 'M' ? 'checked' : '' ?>>
                                    <span class="text-sm text-slate-700">Masculin</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sexe" value="F"
                                           class="accent-violet-600"
                                           <?= ($old['sexe'] ?? '') === 'F' ? 'checked' : '' ?>>
                                    <span class="text-sm text-slate-700">Féminin</span>
                                </label>
                            </div>
                            <?php if (isset($errors['sexe'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['sexe'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Date naissance -->
                        <div class="mb-4">
                            <label class="form-label" for="date_naissance">
                                Date de naissance <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="date_naissance" name="date_naissance"
                                   class="form-input <?= isset($errors['date_naissance']) ? 'border-red-400' : '' ?>"
                                   value="<?= oldVal($old, 'date_naissance') ?>"
                                   max="<?= date('Y-m-d') ?>" required>
                            <?php if (isset($errors['date_naissance'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['date_naissance'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coordonnées -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="phone" class="w-4 h-4 text-emerald-600"></i>
                    <span class="font-semibold text-slate-700">Coordonnées</span>
                </div>
                <div class="p-5 p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="form-label" for="telephone">Téléphone</label>
                            <div class="relative flex items-stretch">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="phone" class="w-4 h-4"></i></span>
                                <input type="tel" id="telephone" name="telephone"
                                       class="form-input pl-10"
                                       value="<?= oldVal($old, 'telephone') ?>"
                                       placeholder="05XX XX XX XX">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="email">Email</label>
                            <div class="relative flex items-stretch">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="mail" class="w-4 h-4"></i></span>
                                <input type="email" id="email" name="email"
                                       class="form-input pl-10 <?= isset($errors['email']) ? 'border-red-400' : '' ?>"
                                       value="<?= oldVal($old, 'email') ?>"
                                       placeholder="eleve@edu.dz">
                            </div>
                            <?php if (isset($errors['email'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['email'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-4 sm:col-span-2">
                            <label class="form-label" for="adresse">Adresse</label>
                            <textarea id="adresse" name="adresse" class="w-full min-h-24 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20" rows="2"
                                      placeholder="Numéro, Rue, Cité, Ville…"><?= oldVal($old, 'adresse') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" class="w-4 h-4"></i>Enregistrer l'élève
                    </button>
                    <a href="<?= BASE_URL ?>/eleves" class="btn btn-secondary">Annuler</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function previewPhoto(input) {
    if (!input.files?.[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('photoPreview');
        const placeholder = document.getElementById('photoPlaceholder');
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        placeholder?.classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
}

function regenMatricule() {
    const year = new Date().getFullYear();
    const num  = Math.floor(Math.random() * 9000) + 1000;
    document.getElementById('matricule').value = year + '-' + num;
}

document.getElementById('nom')?.addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});
</script>
