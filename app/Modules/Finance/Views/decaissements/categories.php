<?php
/**
 * Finance V2 — Catégories de dépenses
 * GET /v2/finance/decaissements/categories
 */
$categories = $categories ?? [];
$couleurs   = $couleurs   ?? [];
$icones     = $icones     ?? [];
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= BASE_URL ?>/v2/finance/decaissements" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Catégories de dépenses</h1>
                <p class="text-sm text-slate-500 mt-1"><?= count($categories) ?> catégorie(s) au total</p>
            </div>
        </div>
        <button onclick="ouvrirCreation()" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle catégorie
        </button>
    </div>

    <!-- Flash messages -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Grille des catégories -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($categories as $cat): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background-color:<?= htmlspecialchars($cat->couleur ?? '#64748b') ?>20">
                <i data-lucide="<?= htmlspecialchars($cat->icone ?? 'receipt') ?>"
                   style="color:<?= htmlspecialchars($cat->couleur ?? '#64748b') ?>"
                   class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($cat->nom) ?></span>
                    <?php if (!$cat->actif): ?>
                    <span class="text-xs px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded-full">Inactif</span>
                    <?php endif; ?>
                </div>
                <code class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($cat->code) ?></code>
                <?php if ($cat->description): ?>
                <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($cat->description) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex flex-col gap-1">
                <button onclick='ouvrirEdition(<?= json_encode([
                    "id" => $cat->id, "code" => $cat->code, "nom" => $cat->nom,
                    "description" => $cat->description, "couleur" => $cat->couleur, "icone" => $cat->icone,
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                        class="p-1.5 text-slate-400 hover:text-violet-600 rounded" title="Modifier">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                </button>
                <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/categories/<?= $cat->id ?>/toggle">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <button type="submit"
                            class="p-1.5 text-slate-400 hover:text-<?= $cat->actif ? 'amber' : 'emerald' ?>-600 rounded"
                            title="<?= $cat->actif ? 'Désactiver' : 'Activer' ?>">
                        <i data-lucide="<?= $cat->actif ? 'pause' : 'play' ?>" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($categories)): ?>
        <div class="col-span-3 py-12 text-center text-slate-400">
            <i data-lucide="tag" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
            <p>Aucune catégorie. Créez-en une pour organiser vos dépenses.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal création/édition catégorie -->
<div id="modal-cat" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 id="modal-cat-title" class="text-lg font-bold text-slate-800 mb-4">Nouvelle catégorie</h3>
        <form method="POST" id="form-cat" action="<?= BASE_URL ?>/v2/finance/decaissements/categories" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Code <span class="form-required">*</span></label>
                    <input type="text" name="code" id="cat-code" required placeholder="EX: TRANSPORT"
                           class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">Nom <span class="form-required">*</span></label>
                    <input type="text" name="nom" id="cat-nom" required placeholder="Transport"
                           class="form-input">
                </div>
            </div>

            <div>
                <label class="form-label">Description</label>
                <input type="text" name="description" id="cat-description"
                       class="form-input">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Couleur</label>
                    <select name="couleur" id="cat-couleur" class="form-select">
                        <?php foreach ($couleurs as $hex => $label): ?>
                        <option value="<?= $hex ?>"><?= $label ?> (<?= $hex ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Icône</label>
                    <select name="icone" id="cat-icone" class="form-select">
                        <?php foreach ($icones as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-cat').classList.add('hidden')"
                        class="btn btn-secondary">Annuler</button>
                <button type="submit" id="modal-cat-submit" class="btn btn-primary">
                    Créer la catégorie
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirCreation() {
    document.getElementById('modal-cat-title').textContent = 'Nouvelle catégorie';
    document.getElementById('modal-cat-submit').textContent = 'Créer la catégorie';
    document.getElementById('form-cat').action = '<?= BASE_URL ?>/v2/finance/decaissements/categories';
    document.getElementById('cat-code').value = '';
    document.getElementById('cat-code').removeAttribute('readonly');
    document.getElementById('cat-nom').value = '';
    document.getElementById('cat-description').value = '';
    document.getElementById('modal-cat').classList.remove('hidden');
}
function ouvrirEdition(cat) {
    document.getElementById('modal-cat-title').textContent = 'Modifier la catégorie';
    document.getElementById('modal-cat-submit').textContent = 'Enregistrer';
    document.getElementById('form-cat').action = '<?= BASE_URL ?>/v2/finance/decaissements/categories/' + cat.id;
    document.getElementById('cat-code').value = cat.code;
    document.getElementById('cat-nom').value = cat.nom;
    document.getElementById('cat-description').value = cat.description || '';
    document.getElementById('cat-couleur').value = cat.couleur;
    document.getElementById('cat-icone').value = cat.icone;
    document.getElementById('modal-cat').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
</script>
