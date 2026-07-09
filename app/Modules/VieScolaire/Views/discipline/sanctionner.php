<?php
/** @var array $user */
/** @var array $dossier */
/** @var array $incidents */
/** @var array $types */

$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prononcer une sanction</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Prononcer une sanction</h1>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-800">
        <strong><?= htmlspecialchars($dossier['eleve_prenom'] . ' ' . $dossier['eleve_nom']) ?></strong>
        — Classe <?= htmlspecialchars($dossier['classe_nom']) ?>
        — <?= (int)$dossier['nb_incidents'] ?> incident(s) enregistré(s)
    </div>

    <?php if ($flash_error): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg"><?= $flash_error ?></div>
    <?php endif; ?>

    <form method="POST" action="/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>/sanctionner"
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5 max-w-2xl">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Lier à un incident (optionnel)</label>
            <select name="incident_id"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
                <option value="">Aucun incident spécifique</option>
                <?php foreach ($incidents as $inc): ?>
                    <option value="<?= $inc['id'] ?>">
                        #<?= $inc['id'] ?> — <?= date('d/m/Y', strtotime($inc['date_incident'])) ?>
                        — <?= ucfirst($inc['gravite']) ?> — <?= htmlspecialchars(mb_strimwidth($inc['description'], 0, 50, '…')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Type de sanction <span class="text-red-500">*</span></label>
            <select name="type_sanction" id="type_sanction" required
                    onchange="toggleDuree(this.value)"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
                <option value="">Sélectionner…</option>
                <?php foreach ($types as $val => $label): ?>
                    <option value="<?= $val ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="bloc_duree" class="hidden">
            <label class="block text-sm font-medium text-slate-700 mb-1">Durée (jours) <span class="text-red-500">*</span></label>
            <input type="number" name="duree_jours" min="1" max="365"
                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date sanction <span class="text-red-500">*</span></label>
                <input type="date" name="date_sanction" value="<?= date('Y-m-d') ?>" required
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date début d'effet</label>
                <input type="date" name="date_debut"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Motif <span class="text-red-500">*</span></label>
            <textarea name="motif" rows="3" required minlength="10"
                      placeholder="Exposé des motifs de la sanction (min. 10 caractères)"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none resize-none"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description complémentaire</label>
            <textarea name="description" rows="2"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none resize-none"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>"
               class="px-5 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Annuler</a>
            <button type="submit"
                    class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
                Prononcer la sanction
            </button>
        </div>
    </form>
</main>

<script>
lucide.createIcons();
function toggleDuree(val) {
    const bloc = document.getElementById('bloc_duree');
    const needs = ['exclusion_temp'].includes(val);
    bloc.classList.toggle('hidden', !needs);
    bloc.querySelector('input').required = needs;
}
</script>
</body>
</html>
