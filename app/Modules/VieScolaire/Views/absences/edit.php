<?php
$absence   = $absence ?? [];
$classes   = $classes ?? [];
$errors    = $errors  ?? [];
$csrfToken = \Core\Session::getCsrfToken();

$typeLabels = ['absence' => 'Absence', 'retard' => 'Retard', 'dispense' => 'Dispense'];
?>

<div class="flex items-center gap-4 mb-6">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>"
       class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>
    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>
        Modifier une absence
    </h2>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    <div class="flex items-center gap-2 font-medium mb-2">
        <i data-lucide="alert-circle" class="w-4 h-4"></i>Erreurs
    </div>
    <ul class="list-disc list-inside space-y-1">
        <?php foreach ((array)$errors as $msgs): ?>
            <?php foreach ((array)$msgs as $msg): ?>
            <li><?= htmlspecialchars($msg, ENT_QUOTES) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm text-amber-800 flex items-start gap-2">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 mt-0.5"></i>
    <p>La modification d'une absence est tracée dans le journal d'audit.</p>
</div>

<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 max-w-2xl">
    <div class="text-sm text-slate-600 mb-5 pb-4 border-b border-slate-100">
        Élève : <span class="font-medium text-slate-900">
            <?= htmlspecialchars($absence['eleve_prenom'] . ' ' . $absence['eleve_nom'], ENT_QUOTES) ?>
        </span>
        &nbsp;·&nbsp; Classe : <span class="font-medium text-slate-900">
            <?= htmlspecialchars($absence['classe_nom'] ?? '', ENT_QUOTES) ?>
        </span>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <input type="hidden" name="_method" value="PUT">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                <input type="date" name="date_absence" required
                       value="<?= htmlspecialchars($absence['date_absence'], ENT_QUOTES) ?>"
                       max="<?= date('Y-m-d') ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                <select name="type" class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none">
                    <?php foreach ($typeLabels as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $absence['type'] === $val ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lbl, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Heure de début</label>
                <input type="time" name="heure_debut"
                       value="<?= htmlspecialchars($absence['heure_debut'] ?? '', ENT_QUOTES) ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Heure de fin</label>
                <input type="time" name="heure_fin"
                       value="<?= htmlspecialchars($absence['heure_fin'] ?? '', ENT_QUOTES) ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none">
            </div>
            <div class="col-span-full">
                <label class="block text-sm font-medium text-slate-700 mb-1">Observation</label>
                <textarea name="observation" rows="3"
                          class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none"><?= htmlspecialchars($absence['observation'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3 pt-5 border-t border-slate-100">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les modifications
            </button>
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>"
               class="text-sm text-slate-500 hover:text-slate-700">Annuler</a>
        </div>
    </form>
</div>
