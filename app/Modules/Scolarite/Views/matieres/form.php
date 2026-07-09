<?php
/** @var object|null $matiere @var bool $isEdit @var array $niveaux @var array $filieres @var array $couleurs @var array $professeurs @var array $errors @var array $old */
$v = function(string $field, string $fallback = '') use ($old, $matiere, $isEdit): string {
    if (isset($old[$field]) && $old[$field] !== '') return htmlspecialchars($old[$field]);
    if ($isEdit && $matiere && isset($matiere->$field)) return htmlspecialchars((string)($matiere->$field ?? ''));
    return htmlspecialchars($fallback);
};
$action  = $isEdit ? BASE_URL . '/v2/scolarite/matieres/' . $matiere->id : BASE_URL . '/v2/scolarite/matieres';
$backUrl = $isEdit ? BASE_URL . '/v2/scolarite/matieres/' . $matiere->id : BASE_URL . '/v2/scolarite/matieres';

// Niveaux cochés
$nvsChecked = [];
if (!empty($old['niveaux'])) {
    $nvsChecked = (array)$old['niveaux'];
} elseif ($isEdit && $matiere && !empty($matiere->niveaux)) {
    $nvsChecked = array_filter(explode(',', (string)$matiere->niveaux));
}

// Couleur courante
$currentCouleur = $old['couleur'] ?? ($isEdit && $matiere ? ($matiere->couleur ?? '') : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <?php include BASE_PATH . '/app/Views/layouts/head_assets.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800">
<?php include BASE_PATH . '/app/Views/layouts/sidebar.php'; ?>

<main class="ml-64 p-6 min-h-screen">
    <div class="max-w-4xl mx-auto">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-slate-500 mb-4">
            <a href="<?= BASE_URL ?>/v2/scolarite/matieres" class="hover:text-emerald-600">Matières</a>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
            <?php if ($isEdit): ?>
            <a href="<?= BASE_URL ?>/v2/scolarite/matieres/<?= $matiere->id ?>"
               class="hover:text-emerald-600"><?= htmlspecialchars($matiere->nom) ?></a>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
            <span class="text-slate-800">Modifier</span>
            <?php else: ?>
            <span class="text-slate-800">Nouvelle matière</span>
            <?php endif; ?>
        </nav>

        <!-- Erreur globale -->
        <?php if (!empty($errors['global'])): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-50 text-red-800 border border-red-200">
            <?= htmlspecialchars($errors['global']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $action ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Colonne principale (2/3) -->
                <div class="lg:col-span-2 space-y-5">

                    <!-- Informations de base -->
                    <div class="bg-white border border-slate-200 rounded-xl p-6">
                        <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="book" class="w-4 h-4 text-emerald-500"></i>
                            Informations de la matière
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                            <!-- Nom -->
                            <div class="sm:col-span-3">
                                <label for="nom" class="block text-sm font-medium text-slate-700 mb-1">
                                    Nom <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="nom" name="nom" value="<?= $v('nom') ?>" required autofocus
                                       oninput="updatePreview()"
                                       placeholder="ex. Mathématiques, Langue Française…"
                                       class="w-full text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none
                                           <?= isset($errors['nom'])?'border-red-400 bg-red-50':'border-slate-300' ?>">
                                <?php if (isset($errors['nom'])): ?>
                                <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['nom']) ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Coefficient -->
                            <div>
                                <label for="coefficient" class="block text-sm font-medium text-slate-700 mb-1">
                                    Coefficient <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" id="coefficient" name="coefficient"
                                           value="<?= $v('coefficient', '2') ?>"
                                           min="0.5" max="20" step="0.5" required
                                           oninput="updatePreview()"
                                           class="w-full text-sm border rounded-lg px-3 py-2 pr-8 focus:ring-2 focus:ring-emerald-500 outline-none
                                               <?= isset($errors['coefficient'])?'border-red-400 bg-red-50':'border-slate-300' ?>">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">×</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">Entre 0,5 et 20</p>
                                <?php if (isset($errors['coefficient'])): ?>
                                <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['coefficient']) ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Volume horaire -->
                            <div>
                                <label for="volume_horaire" class="block text-sm font-medium text-slate-700 mb-1">
                                    Volume horaire <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" id="volume_horaire" name="volume_horaire"
                                           value="<?= $v('volume_horaire', '2') ?>"
                                           min="1" max="30" required
                                           oninput="updatePreview()"
                                           class="w-full text-sm border rounded-lg px-3 py-2 pr-14 focus:ring-2 focus:ring-emerald-500 outline-none
                                               <?= isset($errors['volume_horaire'])?'border-red-400 bg-red-50':'border-slate-300' ?>">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">h/sem</span>
                                </div>
                                <?php if (isset($errors['volume_horaire'])): ?>
                                <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['volume_horaire']) ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Filière -->
                            <div>
                                <label for="filiere" class="block text-sm font-medium text-slate-700 mb-1">Filière</label>
                                <select id="filiere" name="filiere"
                                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none">
                                    <?php $selFiliere = $old['filiere'] ?? ($matiere->filiere ?? ''); ?>
                                    <?php foreach (array_keys($filieres) as $fKey): ?>
                                    <?php if ($fKey === '') continue; ?>
                                    <option value="<?= htmlspecialchars($fKey) ?>"
                                        <?= $selFiliere===$fKey?'selected':'' ?>>
                                        <?= htmlspecialchars($filieres[$fKey]) ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <option value="" <?= $selFiliere===''?'selected':'' ?>>— Aucune —</option>
                                </select>
                            </div>

                            <!-- Enseignant responsable -->
                            <div>
                                <label for="responsable_id" class="block text-sm font-medium text-slate-700 mb-1">
                                    Responsable
                                </label>
                                <select id="responsable_id" name="responsable_id"
                                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none">
                                    <option value="">— Aucun —</option>
                                    <?php $selResp = $old['responsable_id'] ?? ($matiere->responsable_id ?? ''); ?>
                                    <?php foreach ($professeurs as $p): ?>
                                    <option value="<?= $p->id ?>" <?= $selResp==$p->id?'selected':'' ?>>
                                        <?= htmlspecialchars($p->label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Description -->
                            <div class="sm:col-span-3">
                                <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                                <textarea id="description" name="description" rows="3"
                                          placeholder="Objectifs, contenu du programme, remarques…"
                                          class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none resize-y"><?= $v('description') ?></textarea>
                            </div>

                        </div>
                    </div>

                    <!-- Niveaux concernés -->
                    <div class="bg-white border border-slate-200 rounded-xl p-6">
                        <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="layers" class="w-4 h-4 text-emerald-500"></i>
                            Niveaux concernés
                            <span class="text-xs font-normal text-slate-400">(optionnel)</span>
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <?php foreach ($niveaux as $groupe => $liste): ?>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2"><?= htmlspecialchars($groupe) ?></p>
                                <div class="space-y-1.5">
                                    <?php foreach ($liste as $nv): ?>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm hover:text-emerald-700 group">
                                        <input type="checkbox" name="niveaux[]" value="<?= $nv ?>"
                                               <?= in_array($nv, $nvsChecked, true) ? 'checked' : '' ?>
                                               class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 group-hover:bg-emerald-100 group-hover:text-emerald-700 transition">
                                            <?= $nv ?>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <!-- Sidebar (1/3) -->
                <div class="space-y-4">

                    <!-- Aperçu en direct -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                            <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i> Aperçu
                        </h3>
                        <div class="text-center">
                            <div id="prevDot" class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3"
                                 style="background:<?= htmlspecialchars($currentCouleur ?: '#6366F120') ?>">
                                <i data-lucide="book" class="w-6 h-6" id="prevIcon" style="color:<?= htmlspecialchars($currentCouleur ?: '#6366F1') ?>"></i>
                            </div>
                            <p id="prevNom" class="font-bold text-slate-900 mb-3 min-h-[1.5rem]">
                                <?= $v('nom') ?: '—' ?>
                            </p>
                            <div class="flex items-center justify-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-700">
                                    ×<span id="prevCoef"><?= $v('coefficient', '2') ?></span>
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">
                                    <span id="prevVh"><?= $v('volume_horaire', '2') ?></span>h/sem
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Couleur -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                            <i data-lucide="palette" class="w-4 h-4 text-slate-400"></i> Couleur d'affichage
                        </h3>
                        <div class="grid grid-cols-5 gap-2 mb-3">
                            <?php foreach ($couleurs as $hex => $label): ?>
                            <button type="button"
                                    onclick="setCouleur('<?= $hex ?>')"
                                    title="<?= htmlspecialchars($label) ?>"
                                    class="color-btn w-8 h-8 rounded-lg border-2 transition-transform hover:scale-110 <?= $currentCouleur===$hex?'border-slate-900 scale-110':'border-transparent' ?>"
                                    data-hex="<?= $hex ?>"
                                    style="background:<?= $hex ?>">
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="color" id="couleurPicker"
                                   value="<?= $currentCouleur ?: '#6366F1' ?>"
                                   onchange="setCouleur(this.value)"
                                   class="w-8 h-8 rounded border border-slate-300 cursor-pointer p-0">
                            <input type="text" id="couleur" name="couleur"
                                   value="<?= htmlspecialchars($currentCouleur) ?>"
                                   placeholder="#6366F1"
                                   oninput="setCouleur(this.value)"
                                   class="flex-1 text-xs border border-slate-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-emerald-500 outline-none font-mono">
                        </div>
                        <?php if (isset($errors['couleur'])): ?>
                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['couleur']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="bg-sky-50 border border-sky-100 rounded-xl p-4 text-sm text-sky-800 space-y-2">
                        <p class="flex items-start gap-2">
                            <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
                            <span><strong>Coefficient</strong> — valeur pour le calcul des moyennes générales.</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <i data-lucide="clock" class="w-4 h-4 shrink-0 mt-0.5"></i>
                            <span><strong>Volume horaire</strong> — heures d'enseignement par semaine.</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <i data-lucide="layers" class="w-4 h-4 shrink-0 mt-0.5"></i>
                            <span><strong>Niveaux</strong> — cochez les classes qui suivent cette matière.</span>
                        </p>
                    </div>

                    <!-- Boutons -->
                    <div class="flex flex-col gap-2">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <?= $isEdit ? 'Enregistrer les modifications' : 'Créer la matière' ?>
                        </button>
                        <a href="<?= $backUrl ?>"
                           class="w-full inline-flex items-center justify-center gap-2 border border-slate-300 text-slate-600 text-sm px-4 py-2.5 rounded-lg hover:bg-slate-50 transition">
                            Annuler
                        </a>
                    </div>

                </div>
            </div>
        </form>
    </div>
</main>

<script>
function updatePreview() {
    document.getElementById('prevNom').textContent  = document.getElementById('nom').value  || '—';
    document.getElementById('prevCoef').textContent = document.getElementById('coefficient').value || '—';
    document.getElementById('prevVh').textContent   = document.getElementById('volume_horaire').value || '—';
}

function setCouleur(hex) {
    if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;
    document.getElementById('couleur').value      = hex;
    document.getElementById('couleurPicker').value = hex;
    document.getElementById('prevDot').style.background = hex + '20';
    document.getElementById('prevIcon').style.color     = hex;
    document.querySelectorAll('.color-btn').forEach(btn => {
        const active = btn.dataset.hex === hex;
        btn.classList.toggle('border-slate-900', active);
        btn.classList.toggle('scale-110', active);
        btn.classList.toggle('border-transparent', !active);
    });
}

updatePreview();
</script>

<?php include BASE_PATH . '/app/Views/layouts/footer_assets.php'; ?>
</body>
</html>
