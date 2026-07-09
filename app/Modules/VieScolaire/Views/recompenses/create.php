<?php
/** @var array $user */
/** @var array $categories */

$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attribuer une récompense</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/vie-scolaire/recompenses" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Attribuer une récompense</h1>
    </div>

    <?php if ($flash_error): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg"><?= $flash_error ?></div>
    <?php endif; ?>

    <form method="POST" action="/v2/vie-scolaire/recompenses"
          enctype="multipart/form-data"
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6 max-w-2xl">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ID Élève <span class="text-red-500">*</span></label>
                <input type="number" name="eleve_id" required
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ID Classe <span class="text-red-500">*</span></label>
                <input type="number" name="classe_id" required
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Année scolaire <span class="text-red-500">*</span></label>
                <input type="text" name="annee_scolaire"
                       value="<?= date('Y') . '-' . (date('Y') + 1) ?>"
                       placeholder="2025-2026" required
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date d'attribution <span class="text-red-500">*</span></label>
                <input type="date" name="date_attribution" value="<?= date('Y-m-d') ?>" required
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie <span class="text-red-500">*</span></label>
                <select name="categorie_id" required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
                    <option value="">Sélectionner…</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Niveau <span class="text-red-500">*</span></label>
                <select name="niveau" required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
                    <option value="classe">Classe</option>
                    <option value="etablissement">Établissement</option>
                    <option value="academique">Académique</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Motif <span class="text-red-500">*</span></label>
            <textarea name="motif" rows="4" required minlength="10"
                      placeholder="Décrivez le motif de la récompense (minimum 10 caractères)"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none resize-none"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Pièce jointe (optionnel)</label>
            <input type="file" name="piece_jointe" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/v2/vie-scolaire/recompenses"
               class="px-5 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Annuler</a>
            <button type="submit"
                    class="px-6 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-medium transition-colors">
                Attribuer la récompense
            </button>
        </div>
    </form>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
