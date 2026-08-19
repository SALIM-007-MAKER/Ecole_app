<?php /** @var array|null $article @var array $categories @var array $fournisseurs @var string|null $error */ ?>
<div class="max-w-3xl mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/v2/inventaire/articles" class="text-violet-600 hover:underline text-sm">&larr; Retour aux articles</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1"><?= $article ? 'Modifier l\'article' : 'Nouvel article' ?></h1>
    </div>

    <?php if (!empty($error)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $article ? "/v2/inventaire/articles/{$article['id']}/modifier" : '/v2/inventaire/articles' ?>"
          class="bg-white rounded-xl shadow-sm p-6 space-y-5">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Référence *</label>
                <input type="text" name="reference" value="<?= htmlspecialchars($article['reference'] ?? '') ?>"
                       required class="w-full border rounded-lg px-3 py-2 text-sm" <?= $article ? 'readonly' : '' ?>>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type *</label>
                <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <?php foreach (['consommable','durable','equipement'] as $t): ?>
                    <option value="<?=$t?>" <?= ($article['type'] ?? 'consommable') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Désignation *</label>
            <input type="text" name="designation" value="<?= htmlspecialchars($article['designation'] ?? '') ?>"
                   required class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie</label>
                <select name="categorie_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="">— Sans catégorie —</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?=$c['id']?>" <?= ($article['categorie_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Fournisseur</label>
                <select name="fournisseur_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="">— Sans fournisseur —</option>
                    <?php foreach ($fournisseurs as $f): ?>
                    <option value="<?=$f['id']?>" <?= ($article['fournisseur_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Seuil alerte</label>
                <input type="number" name="seuil_alerte" step="0.01" value="<?= $article['seuil_alerte'] ?? 0 ?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Seuil critique</label>
                <input type="number" name="seuil_critique" step="0.01" value="<?= $article['seuil_critique'] ?? 0 ?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Valeur unitaire (€)</label>
                <input type="number" name="valeur_unitaire" step="0.01" value="<?= $article['valeur_unitaire'] ?? 0 ?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Unité de mesure</label>
                <input type="text" name="unite_mesure" value="<?= htmlspecialchars($article['unite_mesure'] ?? 'unité') ?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Garantie (mois)</label>
                <input type="number" name="garantie_mois" value="<?= $article['garantie_mois'] ?? 0 ?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Code-barres</label>
                <input type="text" name="barcode" value="<?= htmlspecialchars($article['barcode'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Numéro de série</label>
                <input type="text" name="numero_serie" value="<?= htmlspecialchars($article['numero_serie'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars($article['description'] ?? '') ?></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
            <a href="<?= BASE_URL ?>/v2/inventaire/articles" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
            <button type="submit" class="px-6 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
                <?= $article ? 'Enregistrer les modifications' : 'Créer l\'article' ?>
            </button>
        </div>
    </form>
</div>
