<?php /** @var array|null $commande @var array $fournisseurs @var array $articles @var string|null $error */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle commande — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/inventaire/commandes" class="text-slate-500 hover:text-slate-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Nouvelle commande fournisseur</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/v2/inventaire/commandes" class="space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-slate-700 mb-2">Informations générales</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fournisseur <span class="text-red-500">*</span></label>
                    <select name="fournisseur_id" required
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($fournisseurs as $f): ?>
                        <option value="<?=$f['id']?>"><?= htmlspecialchars($f['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">TVA (%)</label>
                    <input type="number" name="tva_taux" step="0.01" value="20"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date commande <span class="text-red-500">*</span></label>
                    <input type="date" name="date_commande" required value="<?= date('Y-m-d') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Livraison prévue</label>
                    <input type="date" name="date_livraison_prevue"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"></textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6" id="lignes-section">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-slate-700">Lignes de commande</h2>
                <button type="button" onclick="addLigne()"
                        class="inline-flex items-center gap-1 text-sm text-violet-600 hover:text-violet-800 font-medium">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Ajouter un article
                </button>
            </div>
            <div id="lignes-container" class="space-y-3"></div>
            <p id="no-lignes" class="text-sm text-slate-400 text-center py-4">Aucun article ajouté.</p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="/v2/inventaire/commandes" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
                Créer la commande
            </button>
        </div>
    </form>
</div>

<template id="ligne-tpl">
    <div class="grid grid-cols-12 gap-2 items-end p-3 bg-slate-50 rounded-lg border border-slate-200">
        <div class="col-span-5">
            <label class="block text-xs font-medium text-slate-600 mb-1">Article</label>
            <select name="lignes[__IDX__][article_id]" required
                    class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-500">
                <option value="">— Choisir —</option>
                <?php foreach ($articles as $a): ?>
                <option value="<?=$a['id']?>"><?= htmlspecialchars($a['designation']) ?> (<?= htmlspecialchars($a['reference']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Quantité</label>
            <input type="number" name="lignes[__IDX__][quantite_commandee]" min="0.01" step="0.01" value="1" required
                   class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-500">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">PU HT</label>
            <input type="number" name="lignes[__IDX__][prix_unitaire_ht]" min="0" step="0.01" value="0" required
                   class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-500">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">TVA (%)</label>
            <input type="number" name="lignes[__IDX__][tva_taux]" step="0.01" value="20"
                   class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-500">
        </div>
        <div class="col-span-1 flex justify-end">
            <button type="button" onclick="this.closest('.grid').remove(); updateLignesVisibility()"
                    class="text-red-400 hover:text-red-600 p-1">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
        </div>
    </div>
</template>

<script>
let ligneIdx = 0;
function addLigne() {
    const tpl = document.getElementById('ligne-tpl').content.cloneNode(true);
    tpl.querySelectorAll('[name]').forEach(el => {
        el.name = el.name.replace('__IDX__', ligneIdx);
    });
    document.getElementById('lignes-container').appendChild(tpl);
    ligneIdx++;
    updateLignesVisibility();
    lucide.createIcons();
}
function updateLignesVisibility() {
    const hasLignes = document.getElementById('lignes-container').children.length > 0;
    document.getElementById('no-lignes').style.display = hasLignes ? 'none' : '';
}
lucide.createIcons();
</script>
</body>
</html>
