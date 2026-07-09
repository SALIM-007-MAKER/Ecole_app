<?php
$classes   = $classes  ?? [];
$matieres  = $matieres ?? [];
$errors    = $errors   ?? [];
$old       = $old      ?? [];
$csrfToken = \Core\Session::getCsrfToken();
?>

<div class="flex items-center gap-4 mb-6">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences"
       class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="clipboard-plus" class="w-5 h-5 text-violet-600"></i>
        Ouvrir un appel
    </h2>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    <div class="flex items-center gap-2 font-medium mb-2"><i data-lucide="alert-circle" class="w-4 h-4"></i>Erreurs</div>
    <ul class="list-disc list-inside space-y-1">
        <?php foreach ($errors as $msgs): foreach ((array)$msgs as $msg): ?>
        <li><?= htmlspecialchars($msg, ENT_QUOTES) ?></li>
        <?php endforeach; endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 max-w-2xl">
    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/presences">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <input type="hidden" name="annee_scolaire" value="<?= htmlspecialchars($old['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1), ENT_QUOTES) ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

            <!-- Classe -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Classe <span class="text-red-500">*</span>
                </label>
                <select name="classe_id" required
                        class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none <?= isset($errors['classe_id']) ? 'border-red-400' : '' ?>">
                    <option value="">Choisir une classe…</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($old['classe_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nom'], ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Date de l'appel <span class="text-red-500">*</span>
                </label>
                <input type="date" name="date_appel" required
                       value="<?= htmlspecialchars($old['date_appel'] ?? date('Y-m-d'), ENT_QUOTES) ?>"
                       max="<?= date('Y-m-d') ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none">
            </div>

            <!-- Type d'appel -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type d'appel</label>
                <select name="type_appel" id="type_appel"
                        class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none"
                        onchange="toggleSeance(this.value)">
                    <option value="journalier" <?= ($old['type_appel'] ?? 'journalier') === 'journalier' ? 'selected' : '' ?>>Journalier</option>
                    <option value="seance"     <?= ($old['type_appel'] ?? '') === 'seance' ? 'selected' : '' ?>>Par séance (matière)</option>
                </select>
            </div>

            <!-- Matière (conditionnelle) -->
            <div id="matiere_block" class="<?= ($old['type_appel'] ?? 'journalier') !== 'seance' ? 'hidden' : '' ?>">
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Matière <span class="text-red-500" id="matiere_required">*</span>
                </label>
                <select name="matiere_id"
                        class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none <?= isset($errors['matiere_id']) ? 'border-red-400' : '' ?>">
                    <option value="">-- Choisir une matière --</option>
                    <?php foreach ($matieres as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($old['matiere_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nom'], ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Heure début -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Heure de début</label>
                <input type="time" name="heure_debut"
                       value="<?= htmlspecialchars($old['heure_debut'] ?? '', ENT_QUOTES) ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none">
            </div>

            <!-- Heure fin -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Heure de fin</label>
                <input type="time" name="heure_fin"
                       value="<?= htmlspecialchars($old['heure_fin'] ?? '', ENT_QUOTES) ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none">
            </div>

            <!-- Observation -->
            <div class="col-span-full">
                <label class="block text-sm font-medium text-slate-700 mb-1">Observation</label>
                <textarea name="observation" rows="2"
                          class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none"
                          placeholder="Remarques éventuelles…"><?= htmlspecialchars($old['observation'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3 pt-5 border-t border-slate-100">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                <i data-lucide="play" class="w-4 h-4"></i>Ouvrir l'appel
            </button>
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences" class="text-sm text-slate-500 hover:text-slate-700">Annuler</a>
        </div>
    </form>
</div>

<script>
function toggleSeance(val) {
    const block = document.getElementById('matiere_block');
    if (val === 'seance') {
        block.classList.remove('hidden');
    } else {
        block.classList.add('hidden');
    }
}
</script>
