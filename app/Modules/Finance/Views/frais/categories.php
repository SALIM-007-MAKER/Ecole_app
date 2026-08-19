<?php
/**
 * Finance V2 — Catégories de frais
 * GET /v2/finance/frais/categories
 */
$result   = $result ?? ['data' => [], 'total' => 0, 'total_pages' => 1, 'page' => 1];
$canManage = $canManage ?? false;
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= BASE_URL ?>/v2/finance/frais" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Catégories de frais</h1>
                <p class="text-sm text-slate-500 mt-1"><?= $result['total'] ?> catégorie(s) au total</p>
            </div>
        </div>
        <?php if ($canManage): ?>
        <button onclick="document.getElementById('modal-create').classList.remove('hidden')"
                class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
            <i data-lucide="plus" class="inline w-4 h-4 mr-1"></i> Nouvelle catégorie
        </button>
        <?php endif; ?>
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
        <?php foreach ($result['data'] as $cat): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background-color:<?= htmlspecialchars($cat->couleur ?? '#6366f1') ?>20">
                <i data-lucide="<?= htmlspecialchars($cat->icone ?? 'tag') ?>"
                   style="color:<?= htmlspecialchars($cat->couleur ?? '#6366f1') ?>"
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
                <div class="text-xs text-slate-500 mt-1"><?= $cat->nb_frais ?> type(s) de frais</div>
            </div>
            <?php if ($canManage): ?>
            <div class="flex flex-col gap-1">
                <form method="POST" action="<?= BASE_URL ?>/v2/finance/frais/categories/<?= $cat->id ?>/toggle">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <button type="submit"
                            class="p-1.5 text-slate-400 hover:text-<?= $cat->actif ? 'amber' : 'emerald' ?>-600 rounded"
                            title="<?= $cat->actif ? 'Désactiver' : 'Activer' ?>">
                        <i data-lucide="<?= $cat->actif ? 'pause' : 'play' ?>" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty($result['data'])): ?>
        <div class="col-span-3 py-12 text-center text-slate-400">
            <i data-lucide="tag" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
            <p>Aucune catégorie. Créez-en une pour organiser vos frais.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal création catégorie -->
<div id="modal-create" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Nouvelle catégorie</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/frais/categories" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Code <span class="form-required">*</span></label>
                    <input type="text" name="code" required placeholder="EX: SCOL"
                           class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">Nom <span class="form-required">*</span></label>
                    <input type="text" name="nom" required placeholder="Frais scolaires"
                           class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Couleur</label>
                    <select name="couleur" class="form-select">
                        <?php foreach ($couleurs as $hex => $label): ?>
                        <option value="<?= $hex ?>"><?= $label ?> (<?= $hex ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Icône</label>
                    <select name="icone" class="form-select">
                        <?php foreach ($icones as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-create').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-slate-600">Annuler</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    Créer la catégorie
                </button>
            </div>
        </form>
    </div>
</div>
