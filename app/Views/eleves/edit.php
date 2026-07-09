<?php
$errors  = $errors  ?? [];
$old     = $old     ?? [];
$classes = $classes ?? [];
$parents = $parents ?? [];

function editVal(array $old, string $key, $eleve, string $col = ''): string {
    $v = $old[$key] ?? ($eleve->{$col ?: $key} ?? '');
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>
            Modifier — <?= htmlspecialchars($eleve->nom . ' ' . $eleve->prenom, ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5 font-mono"><?= htmlspecialchars($eleve->matricule, ENT_QUOTES) ?></p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/eleves/<?= $eleve->id ?>" class="btn btn-ghost">
            <i data-lucide="eye" class="w-4 h-4"></i>Voir la fiche
        </a>
        <a href="<?= BASE_URL ?>/eleves" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Liste
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <div class="flex-1">
        <div class="font-semibold text-sm mb-1">Veuillez corriger les erreurs :</div>
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

<form method="POST" action="<?= BASE_URL ?>/eleves/<?= $eleve->id ?>"
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
                    <div class="relative w-28 h-28">
                        <?php if (!empty($eleve->photo)): ?>
                        <img id="photoPreview"
                             src="<?= BASE_URL ?>/<?= htmlspecialchars($eleve->photo, ENT_QUOTES) ?>"
                             class="w-28 h-28 rounded-full object-cover border-4 border-slate-100 shadow-sm"
                             alt="Photo">
                        <?php else: ?>
                        <div class="w-28 h-28 rounded-full border-4 border-slate-100 bg-slate-50
                                    flex items-center justify-center" id="photoPlaceholder">
                            <i data-lucide="user" class="w-12 h-12 text-slate-300"></i>
                        </div>
                        <img id="photoPreview" src="" class="hidden w-28 h-28 rounded-full object-cover border-4 border-slate-100 absolute inset-0" alt="">
                        <?php endif; ?>
                    </div>
                    <label class="btn btn-outline cursor-pointer" for="photo">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        <?= !empty($eleve->photo) ? 'Changer la photo' : 'Choisir une photo' ?>
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/*"
                           class="sr-only" onchange="previewPhoto(this)">
                    <p class="text-xs text-slate-400 text-center">JPG, PNG, WebP — max 3 Mo</p>
                    <?php if (isset($errors['photo'])): ?>
                    <p class="text-xs text-red-500 text-center"><?= htmlspecialchars($errors['photo'][0], ENT_QUOTES) ?></p>
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
                        <select id="classe_id" name="classe_id" class="form-input">
                            <option value="">— Non affecté —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c->id ?>"
                                <?= (($old['classe_id'] ?? $eleve->classe_id) == $c->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c->label, ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="parent_id">Parent responsable</label>
                        <select id="parent_id" name="parent_id" class="form-input">
                            <option value="">— Non affecté —</option>
                            <?php foreach ($parents as $p): ?>
                            <option value="<?= $p->id ?>"
                                <?= (($old['parent_id'] ?? $eleve->parent_id) == $p->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim(($p->prenom ?? '') . ' ' . $p->nom), ENT_QUOTES) ?>
                                <?= $p->telephone ? ' — ' . htmlspecialchars($p->telephone, ENT_QUOTES) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Statut -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="p-5 p-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="actif" value="0">
                        <input type="checkbox" id="actif" name="actif" value="1"
                               class="w-4 h-4 accent-violet-600 rounded"
                               <?= (($old['actif'] ?? $eleve->actif) ? 'checked' : '') ?>>
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

                        <div class="mb-4">
                            <label class="form-label" for="matricule">
                                Matricule <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="matricule" name="matricule"
                                   class="form-input font-mono text-sm <?= isset($errors['matricule']) ? 'border-red-400' : '' ?>"
                                   value="<?= editVal($old, 'matricule', $eleve) ?>"
                                   required>
                            <?php if (isset($errors['matricule'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['matricule'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="nom">
                                Nom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nom" name="nom"
                                   class="form-input <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                                   value="<?= editVal($old, 'nom', $eleve) ?>"
                                   required>
                            <?php if (isset($errors['nom'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="prenom">
                                Prénom <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="prenom" name="prenom"
                                   class="form-input <?= isset($errors['prenom']) ? 'border-red-400' : '' ?>"
                                   value="<?= editVal($old, 'prenom', $eleve) ?>"
                                   required>
                            <?php if (isset($errors['prenom'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['prenom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="form-label">Sexe <span class="text-red-500">*</span></label>
                            <div class="flex gap-4 mt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sexe" value="M"
                                           class="accent-violet-600"
                                           <?= (($old['sexe'] ?? $eleve->sexe) === 'M') ? 'checked' : '' ?>>
                                    <span class="text-sm text-slate-700">Masculin</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sexe" value="F"
                                           class="accent-violet-600"
                                           <?= (($old['sexe'] ?? $eleve->sexe) === 'F') ? 'checked' : '' ?>>
                                    <span class="text-sm text-slate-700">Féminin</span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="date_naissance">
                                Date de naissance <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="date_naissance" name="date_naissance"
                                   class="form-input <?= isset($errors['date_naissance']) ? 'border-red-400' : '' ?>"
                                   value="<?= editVal($old, 'date_naissance', $eleve) ?>"
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
                                       value="<?= editVal($old, 'telephone', $eleve) ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="email">Email</label>
                            <div class="relative flex items-stretch">
                                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="mail" class="w-4 h-4"></i></span>
                                <input type="email" id="email" name="email"
                                       class="form-input pl-10 <?= isset($errors['email']) ? 'border-red-400' : '' ?>"
                                       value="<?= editVal($old, 'email', $eleve) ?>">
                            </div>
                            <?php if (isset($errors['email'])): ?>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($errors['email'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-4 sm:col-span-2">
                            <label class="form-label" for="adresse">Adresse</label>
                            <textarea id="adresse" name="adresse" class="w-full min-h-24 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                                      rows="2"><?= editVal($old, 'adresse', $eleve) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les modifications
                    </button>
                    <a href="<?= BASE_URL ?>/eleves/<?= $eleve->id ?>" class="btn btn-secondary">Annuler</a>
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
        let img = document.getElementById('photoPreview');
        img.src = e.target.result;
        img.classList.remove('hidden');
        document.getElementById('photoPlaceholder')?.classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
}
document.getElementById('nom')?.addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});
</script>
