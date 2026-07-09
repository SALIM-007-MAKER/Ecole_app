<?php /** @var array|null $fournisseur @var string|null $error */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $fournisseur ? 'Modifier fournisseur' : 'Nouveau fournisseur' ?> — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/inventaire/fournisseurs" class="text-slate-500 hover:text-slate-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">
            <?= $fournisseur ? 'Modifier le fournisseur' : 'Nouveau fournisseur' ?>
        </h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST"
          action="<?= $fournisseur ? "/v2/inventaire/fournisseurs/{$fournisseur['id']}/modifier" : '/v2/inventaire/fournisseurs' ?>"
          class="bg-white rounded-xl shadow-sm p-6 space-y-5">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input type="text" name="nom" required
                   value="<?= htmlspecialchars($fournisseur['nom'] ?? '') ?>"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($fournisseur['email'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
                <input type="text" name="telephone"
                       value="<?= htmlspecialchars($fournisseur['telephone'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
            <textarea name="adresse" rows="2"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"><?= htmlspecialchars($fournisseur['adresse'] ?? '') ?></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">SIRET / Numéro fiscal</label>
                <input type="text" name="siret"
                       value="<?= htmlspecialchars($fournisseur['siret'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Délai livraison (jours)</label>
                <input type="number" name="delai_livraison_j" min="0"
                       value="<?= htmlspecialchars($fournisseur['delai_livraison_j'] ?? '7') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"><?= htmlspecialchars($fournisseur['notes'] ?? '') ?></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/v2/inventaire/fournisseurs" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
                <?= $fournisseur ? 'Enregistrer' : 'Créer' ?>
            </button>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
