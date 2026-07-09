<?php
$evaluation      = $evaluation      ?? null;
$elevesAvecNotes = $elevesAvecNotes ?? [];
$colors          = $colors          ?? [];
$policy          = $policy          ?? null;
$user            = $user            ?? [];

if (!$evaluation) return;
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            Saisie des notes — <?= htmlspecialchars($evaluation->libelle, ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= htmlspecialchars($evaluation->classe_nom, ENT_QUOTES) ?>
            · <?= htmlspecialchars($evaluation->matiere_nom, ENT_QUOTES) ?>
            · barème /<?= number_format((float)$evaluation->note_max, 0) ?>
            · coeff ×<?= number_format((float)$evaluation->coefficient, 2) ?>
        </p>
    </div>
</div>

<?php $flash = \Core\Session::getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<?php if (empty($elevesAvecNotes)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-sm text-amber-800 flex items-start gap-3">
    <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
    <div>
        <p class="font-semibold mb-1">Aucun élève trouvé dans cette classe.</p>
        <p>Vérifiez que des élèves sont bien affectés à la classe de l'évaluation.</p>
    </div>
</div>
<?php else: ?>

<form method="POST"
      action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <!-- Barre d'outils rapide -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 mb-4 flex flex-wrap items-center gap-3">
        <span class="text-sm text-slate-500"><?= count($elevesAvecNotes) ?> élève(s)</span>
        <div class="flex gap-2 ml-auto">
            <button type="button" id="btnMarquerTousAbsents"
                    class="btn btn-secondary text-xs">
                <i data-lucide="user-x" class="w-3 h-3"></i>Tout absent
            </button>
            <button type="button" id="btnDecocherAbsents"
                    class="btn btn-secondary text-xs">
                <i data-lucide="user-check" class="w-3 h-3"></i>Tout présent
            </button>
            <button type="submit" class="btn btn-primary text-sm">
                <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 w-1/3">Élève</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600 w-24">Note /<?= number_format((float)$evaluation->note_max, 0) ?></th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600 w-20">Absent</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Commentaire</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600 w-24">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" id="saisieTable">
                <?php foreach ($elevesAvecNotes as $row): ?>
                <?php
                    $hasNote = $row->note_id !== null;
                    $color   = $hasNote ? ($colors[$row->statut] ?? 'slate') : 'slate';
                ?>
                <tr class="hover:bg-slate-50 transition-colors note-row
                           <?= $hasNote && (int)$row->est_absent ? 'absent-row' : '' ?>">
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-slate-900">
                            <?= htmlspecialchars($row->prenom . ' ' . $row->nom, ENT_QUOTES) ?>
                        </div>
                        <?php if ($row->matricule): ?>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($row->matricule, ENT_QUOTES) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <input type="number"
                               name="notes[<?= $row->eleve_id ?>][valeur]"
                               value="<?= $hasNote && !$row->est_absent && $row->valeur !== null ? htmlspecialchars($row->valeur, ENT_QUOTES) : '' ?>"
                               min="0" max="<?= (float)$evaluation->note_max ?>" step="0.25"
                               class="note-input w-20 text-center px-2 py-1 border border-slate-300
                                      rounded-lg text-sm font-mono focus:outline-none focus:ring-2
                                      focus:ring-violet-400 disabled:bg-slate-50 disabled:text-slate-400"
                               placeholder="—"
                               <?= $hasNote && $row->statut === 'verrouillee' ? 'disabled' : '' ?>
                               <?= $hasNote && (int)$row->est_absent ? 'disabled' : '' ?>>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <input type="checkbox"
                               name="notes[<?= $row->eleve_id ?>][est_absent]"
                               value="1"
                               class="absent-checkbox w-4 h-4 rounded accent-amber-500"
                               <?= $hasNote && (int)$row->est_absent ? 'checked' : '' ?>
                               <?= $hasNote && $row->statut === 'verrouillee' ? 'disabled' : '' ?>
                               onchange="toggleAbsent(this)">
                    </td>
                    <td class="px-4 py-2.5">
                        <input type="text"
                               name="notes[<?= $row->eleve_id ?>][commentaire]"
                               value="<?= htmlspecialchars($row->commentaire ?? '', ENT_QUOTES) ?>"
                               maxlength="200"
                               placeholder="Commentaire optionnel…"
                               class="w-full px-2 py-1 border border-slate-200 rounded-lg text-xs
                                      focus:outline-none focus:ring-1 focus:ring-violet-300"
                               <?= $hasNote && $row->statut === 'verrouillee' ? 'disabled' : '' ?>>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <?php if ($hasNote): ?>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full
                              bg-<?= $color ?>-100 text-<?= $color ?>-700">
                            <?= $row->statut ?? '—' ?>
                        </span>
                        <?php else: ?>
                        <span class="text-xs text-slate-300">à saisir</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between mt-4">
        <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes"
           class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les notes
        </button>
    </div>
</form>

<?php endif; ?>

<script>
function toggleAbsent(checkbox) {
    const row      = checkbox.closest('tr');
    const noteInput = row.querySelector('.note-input');
    if (checkbox.checked) {
        noteInput.value    = '';
        noteInput.disabled = true;
        row.classList.add('absent-row');
    } else {
        noteInput.disabled = false;
        row.classList.remove('absent-row');
    }
}

document.getElementById('btnMarquerTousAbsents')?.addEventListener('click', () => {
    document.querySelectorAll('.absent-checkbox:not([disabled])').forEach(cb => {
        cb.checked = true;
        toggleAbsent(cb);
    });
});

document.getElementById('btnDecocherAbsents')?.addEventListener('click', () => {
    document.querySelectorAll('.absent-checkbox:not([disabled])').forEach(cb => {
        cb.checked = false;
        toggleAbsent(cb);
    });
});

// Live validation barème
const noteMax = <?= (float)$evaluation->note_max ?>;
document.querySelectorAll('.note-input').forEach(input => {
    input.addEventListener('input', () => {
        const v = parseFloat(input.value);
        if (!isNaN(v) && v > noteMax) {
            input.classList.add('border-red-400', 'bg-red-50');
        } else {
            input.classList.remove('border-red-400', 'bg-red-50');
        }
    });
});
</script>
