<?php
/** @var array $departements */
/** @var array $postes */
/** @var array $users */
/** @var array $types */
/** @var array $statuts */
/** @var array $genres */

$old    = \Core\Session::getFlash('old')    ?? [];
$errors = \Core\Session::getFlash('errors') ?? [];

function hCreate(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function oldCreate(string $key, array $old, string $default = ''): string {
    return hCreate((string)($old[$key] ?? $default));
}
function errCreate(string $key, array $errors): string {
    if (!isset($errors[$key])) return '';
    $msgs = (array)$errors[$key];
    return '<p class="mt-1 text-xs text-red-600">' . hCreate(implode(' ', $msgs)) . '</p>';
}
function hasErrCreate(string $key, array $errors): bool { return isset($errors[$key]); }
$f = fn(string $k) => oldCreate($k, $old);
$e = fn(string $k) => errCreate($k, $errors);
$hasE = fn(string $k) => hasErrCreate($k, $errors);
?>

<div class="flex items-start gap-4 mb-6">
    <a href="<?= BASE_URL ?>/v2/rh/employes"
       class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 flex-shrink-0 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
    </a>
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="user-plus" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Nouvel employé</h2>
        <p class="text-sm text-slate-500 mt-0.5">Créer un dossier employé</p>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hCreate($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/v2/rh/employes" class="space-y-6">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

    <!-- Informations personnelles -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="user" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Informations personnelles</h3>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nom <span class="form-required">*</span></label>
                <input type="text" name="nom" value="<?= $f('nom') ?>" required
                       class="form-input <?= $hasE('nom') ? 'is-invalid' : '' ?>">
                <?= $e('nom') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Prénom <span class="form-required">*</span></label>
                <input type="text" name="prenom" value="<?= $f('prenom') ?>" required
                       class="form-input <?= $hasE('prenom') ? 'is-invalid' : '' ?>">
                <?= $e('prenom') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Genre <span class="form-required">*</span></label>
                <select name="genre" class="form-select <?= $hasE('genre') ? 'is-invalid' : '' ?>">
                    <?php foreach ($genres as $g): ?>
                    <option value="<?= hCreate($g) ?>" <?= $f('genre') === $g ? 'selected' : '' ?>>
                        <?= $g === 'M' ? 'Masculin' : ($g === 'F' ? 'Féminin' : 'Autre') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $e('genre') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date de naissance</label>
                <input type="date" name="date_naissance" value="<?= $f('date_naissance') ?>"
                       class="form-input <?= $hasE('date_naissance') ? 'is-invalid' : '' ?>">
                <?= $e('date_naissance') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Lieu de naissance</label>
                <input type="text" name="lieu_naissance" value="<?= $f('lieu_naissance') ?>"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nationalité</label>
                <input type="text" name="nationalite" value="<?= $f('nationalite') ?: 'Algérienne' ?>"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">N° CNI</label>
                <input type="text" name="cni_numero" value="<?= $f('cni_numero') ?>"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Expiration CNI</label>
                <input type="date" name="cni_expiration" value="<?= $f('cni_expiration') ?>"
                       class="form-input">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Adresse</label>
                <textarea name="adresse" rows="2"
                          class="form-textarea"><?= $f('adresse') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Coordonnées -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="contact" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Coordonnées</h3>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Téléphone</label>
                <input type="tel" name="telephone" value="<?= $f('telephone') ?>"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">E-mail professionnel</label>
                <input type="email" name="email_pro" value="<?= $f('email_pro') ?>"
                       class="form-input <?= $hasE('email_pro') ? 'is-invalid' : '' ?>">
                <?= $e('email_pro') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">E-mail personnel</label>
                <input type="email" name="email_perso" value="<?= $f('email_perso') ?>"
                       class="form-input <?= $hasE('email_perso') ? 'is-invalid' : '' ?>">
                <?= $e('email_perso') ?>
            </div>
        </div>
    </div>

    <!-- Informations professionnelles -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="briefcase" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Informations professionnelles</h3>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Type de personnel <span class="form-required">*</span></label>
                <select name="type_personnel" class="form-select <?= $hasE('type_personnel') ? 'is-invalid' : '' ?>">
                    <option value="">— Choisir —</option>
                    <?php foreach ($types as $val => $lbl): ?>
                    <option value="<?= hCreate($val) ?>" <?= $f('type_personnel') === $val ? 'selected' : '' ?>>
                        <?= hCreate($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $e('type_personnel') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut <span class="form-required">*</span></label>
                <select name="statut" class="form-select <?= $hasE('statut') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statuts as $val => $lbl): ?>
                    <option value="<?= hCreate($val) ?>" <?= ($f('statut') ?: 'actif') === $val ? 'selected' : '' ?>>
                        <?= hCreate($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $e('statut') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Département</label>
                <select name="departement_id" class="form-select">
                    <option value="">— Aucun —</option>
                    <?php foreach ($departements as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $f('departement_id') == $d['id'] ? 'selected' : '' ?>>
                        <?= hCreate($d['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Poste</label>
                <select name="poste_id" class="form-select">
                    <option value="">— Aucun —</option>
                    <?php foreach ($postes as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $f('poste_id') == $p['id'] ? 'selected' : '' ?>>
                        <?= hCreate($p['intitule']) ?> <span class="text-slate-400">(<?= hCreate($p['categorie']) ?>)</span>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date d'entrée <span class="form-required">*</span></label>
                <input type="date" name="date_entree" value="<?= $f('date_entree') ?: date('Y-m-d') ?>" required
                       class="form-input <?= $hasE('date_entree') ? 'is-invalid' : '' ?>">
                <?= $e('date_entree') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Compte utilisateur lié</label>
                <select name="user_id" class="form-select">
                    <option value="">— Aucun —</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= $f('user_id') == $u['id'] ? 'selected' : '' ?>>
                        <?= hCreate($u['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Diplôme</label>
                <input type="text" name="diplome" value="<?= $f('diplome') ?>"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Spécialité</label>
                <input type="text" name="specialite" value="<?= $f('specialite') ?>"
                       class="form-input">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Notes internes</label>
                <textarea name="notes" rows="2"
                          class="form-textarea"><?= $f('notes') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Contacts d'urgence -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="phone-call" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Contacts d'urgence</h3>
            </div>
            <button type="button" id="addContact"
                    class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>Ajouter
            </button>
        </div>
        <div class="p-5 space-y-4" id="contactsContainer">
            <!-- Premier contact toujours visible -->
            <div class="contact-block border border-slate-100 rounded-lg p-4 relative">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Nom complet</label>
                        <input type="text" name="contacts[0][nom_complet]"
                               class="form-input">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Lien</label>
                        <input type="text" name="contacts[0][lien]" placeholder="époux, parent…"
                               class="form-input">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Téléphone</label>
                        <input type="tel" name="contacts[0][telephone]"
                               class="form-input">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Téléphone 2</label>
                        <input type="tel" name="contacts[0][telephone2]"
                               class="form-input">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">E-mail</label>
                        <input type="email" name="contacts[0][email]"
                               class="form-input">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons -->
    <div class="flex items-center justify-end gap-3">
        <a href="<?= BASE_URL ?>/v2/rh/employes"
           class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="save" class="w-4 h-4"></i>Créer l'employé
        </button>
    </div>
</form>

<script>
(function() {
    let idx = 1;
    document.getElementById('addContact').addEventListener('click', function() {
        const tpl = `<div class="contact-block border border-slate-100 rounded-lg p-4 relative">
            <button type="button" onclick="this.closest('.contact-block').remove()"
                    class="absolute top-2 right-2 text-slate-300 hover:text-red-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Nom complet</label>
                    <input type="text" name="contacts[${idx}][nom_complet]" class="form-input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Lien</label>
                    <input type="text" name="contacts[${idx}][lien]" placeholder="époux, parent…" class="form-input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Téléphone</label>
                    <input type="tel" name="contacts[${idx}][telephone]" class="form-input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Téléphone 2</label>
                    <input type="tel" name="contacts[${idx}][telephone2]" class="form-input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">E-mail</label>
                    <input type="email" name="contacts[${idx}][email]" class="form-input">
                </div>
            </div>
        </div>`;
        document.getElementById('contactsContainer').insertAdjacentHTML('beforeend', tpl);
        idx++;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
})();
</script>
