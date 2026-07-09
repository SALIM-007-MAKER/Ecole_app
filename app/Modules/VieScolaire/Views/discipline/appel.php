<?php
/** @var array $user */
/** @var array $sanction */

$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faire appel d'une sanction</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/vie-scolaire/discipline/<?= $sanction['dossier_id'] ?>" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Faire appel d'une sanction</h1>
    </div>

    <div class="bg-purple-50 border border-purple-200 rounded-lg px-4 py-3 mb-6 text-sm text-purple-800">
        <strong>Sanction :</strong> <?= htmlspecialchars(str_replace('_', ' ', $sanction['type_sanction'])) ?>
        — <strong>Motif :</strong> <?= htmlspecialchars($sanction['motif']) ?>
    </div>

    <?php if ($flash_error): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg"><?= $flash_error ?></div>
    <?php endif; ?>

    <form method="POST" action="/v2/vie-scolaire/discipline/sanctions/<?= $sanction['id'] ?>/appel"
          enctype="multipart/form-data"
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5 max-w-2xl">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Motif de l'appel <span class="text-red-500">*</span></label>
            <textarea name="description" rows="5" required minlength="20"
                      placeholder="Exposez clairement les raisons de cet appel (minimum 20 caractères)"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none resize-none"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Pièce jointe (optionnel)</label>
            <input type="file" name="piece_jointe" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800">
            Un seul appel est possible par sanction. L'appel sera examiné par la direction.
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/v2/vie-scolaire/discipline/<?= $sanction['dossier_id'] ?>"
               class="px-5 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Annuler</a>
            <button type="submit"
                    class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors">
                Soumettre l'appel
            </button>
        </div>
    </form>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
