<?php
/** @var object|null $famille @var bool $isEdit @var array $errors @var array $old @var array $liens */
$v = function(string $field, string $fallback = '') use ($old, $famille, $isEdit): string {
    if (!empty($old[$field])) return htmlspecialchars($old[$field]);
    if ($isEdit && $famille && isset($famille->$field)) return htmlspecialchars($famille->$field ?? '');
    return htmlspecialchars($fallback);
};
$action  = $isEdit ? BASE_URL . '/v2/scolarite/familles/' . $famille->id : BASE_URL . '/v2/scolarite/familles';
$backUrl = $isEdit ? BASE_URL . '/v2/scolarite/familles/' . $famille->id : BASE_URL . '/v2/scolarite/familles';
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
    <div class="max-w-3xl mx-auto">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-slate-500 mb-4">
            <a href="<?= BASE_URL ?>/v2/scolarite/familles" class="hover:text-violet-600">Familles</a>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
            <?php if ($isEdit): ?>
            <a href="<?= BASE_URL ?>/v2/scolarite/familles/<?= $famille->id ?>"
               class="hover:text-violet-600"><?= htmlspecialchars($famille->nom) ?></a>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
            <span class="text-slate-800">Modifier</span>
            <?php else: ?>
            <span class="text-slate-800">Nouvelle famille</span>
            <?php endif; ?>
        </nav>

        <!-- Flash errors globaux -->
        <?php $err = \Core\Session::getFlash('error'); if ($err): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-50 text-red-800 border border-red-200">
            <?= htmlspecialchars($err) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $action ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

            <div class="space-y-6">

                <!-- Identité -->
                <div class="bg-white border border-slate-200 rounded-xl p-6">
                    <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="home" class="w-4 h-4 text-violet-500"></i>
                        Identité de la famille
                    </h2>
                    <div>
                        <label for="nom" class="block text-sm font-medium text-slate-700 mb-1">
                            Nom de famille <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="nom" name="nom" value="<?= $v('nom') ?>" required
                               oninput="this.value=this.value.toUpperCase()"
                               placeholder="ex. MARTIN, FAMILLE DUPONT…"
                               class="w-full text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none
                                   <?= isset($errors['nom'])?'border-red-400 bg-red-50':'border-slate-300' ?>">
                        <?php if (isset($errors['nom'])): ?>
                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['nom']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Coordonnées -->
                <div class="bg-white border border-slate-200 rounded-xl p-6">
                    <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-violet-500"></i>
                        Coordonnées
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <div class="sm:col-span-2">
                            <label for="adresse" class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
                            <input type="text" id="adresse" name="adresse" value="<?= $v('adresse') ?>"
                                   placeholder="12 rue des Lilas"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none">
                        </div>

                        <div>
                            <label for="code_postal" class="block text-sm font-medium text-slate-700 mb-1">Code postal</label>
                            <input type="text" id="code_postal" name="code_postal" value="<?= $v('code_postal') ?>"
                                   placeholder="75001"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none">
                        </div>

                        <div>
                            <label for="ville" class="block text-sm font-medium text-slate-700 mb-1">Ville</label>
                            <input type="text" id="ville" name="ville" value="<?= $v('ville') ?>"
                                   placeholder="Paris"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none">
                        </div>

                        <div>
                            <label for="telephone" class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" value="<?= $v('telephone') ?>"
                                   placeholder="06 12 34 56 78"
                                   class="w-full text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none
                                       <?= isset($errors['telephone'])?'border-red-400 bg-red-50':'border-slate-300' ?>">
                            <?php if (isset($errors['telephone'])): ?>
                            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['telephone']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input type="email" id="email" name="email" value="<?= $v('email') ?>"
                                   placeholder="famille@exemple.fr"
                                   class="w-full text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none
                                       <?= isset($errors['email'])?'border-red-400 bg-red-50':'border-slate-300' ?>">
                            <?php if (isset($errors['email'])): ?>
                            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['email']) ?></p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- Contact d'urgence -->
                <div class="bg-red-50 border border-red-100 rounded-xl p-6">
                    <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i>
                        Contact d'urgence
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <div>
                            <label for="contact_urgence_nom" class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
                            <input type="text" id="contact_urgence_nom" name="contact_urgence_nom"
                                   value="<?= $v('contact_urgence_nom') ?>"
                                   placeholder="Prénom NOM"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-400 outline-none bg-white">
                        </div>

                        <div>
                            <label for="contact_urgence_lien" class="block text-sm font-medium text-slate-700 mb-1">Lien</label>
                            <input type="text" id="contact_urgence_lien" name="contact_urgence_lien"
                                   value="<?= $v('contact_urgence_lien') ?>"
                                   placeholder="Oncle, Voisin…"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-400 outline-none bg-white">
                        </div>

                        <div>
                            <label for="contact_urgence_telephone" class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
                            <input type="tel" id="contact_urgence_telephone" name="contact_urgence_telephone"
                                   value="<?= $v('contact_urgence_telephone') ?>"
                                   placeholder="06 XX XX XX XX"
                                   class="w-full text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-400 outline-none bg-white
                                       <?= isset($errors['contact_urgence_telephone'])?'border-red-400':'border-slate-300' ?>">
                            <?php if (isset($errors['contact_urgence_telephone'])): ?>
                            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['contact_urgence_telephone']) ?></p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white border border-slate-200 rounded-xl p-6">
                    <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">Notes internes</label>
                    <textarea id="notes" name="notes" rows="3"
                              placeholder="Informations complémentaires…"
                              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none resize-y"><?= $v('notes') ?></textarea>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <a href="<?= $backUrl ?>"
                       class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                        Annuler
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <?= $isEdit ? 'Enregistrer les modifications' : 'Créer la famille' ?>
                    </button>
                </div>

            </div>
        </form>

    </div>
</main>

<?php include BASE_PATH . '/app/Views/layouts/footer_assets.php'; ?>
</body>
</html>
