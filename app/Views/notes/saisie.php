<?php
$types    = $types ?? [];
$ct       = $controle;
$note_max = (float)($ct->note_max ?? 20);
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
            <i data-lucide="pencil-line" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Saisie des notes</h2>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700">
                    <i data-lucide="book" class="w-3 h-3 mr-1"></i>
                    <?= htmlspecialchars($ct->matiere_nom, ENT_QUOTES) ?> (coef. <?= $ct->matiere_coef ?>)
                </span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700">
                    <i data-lucide="building-2" class="w-3 h-3 mr-1"></i>
                    <?= htmlspecialchars($ct->classe_niveau . ' ' . $ct->classe_nom, ENT_QUOTES) ?>
                </span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">
                    <i data-lucide="calendar" class="w-3 h-3 mr-1"></i>
                    <?= htmlspecialchars($ct->periode_nom, ENT_QUOTES) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/notes/controles/<?= $ct->id ?>/edit" class="btn btn-warning">
            <i data-lucide="pencil" class="w-4 h-4"></i>Modifier le contrôle
        </a>
        <a href="<?= BASE_URL ?>/notes/controles?classe_id=<?= $ct->classe_id ?>&periode_id=<?= $ct->periode_id ?>"
           class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
        </a>
    </div>
</div>

<!-- Carte du contrôle -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="p-5 p-5">
        <div class="flex flex-wrap items-center gap-6">
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-900 text-base"><?= htmlspecialchars($ct->libelle, ENT_QUOTES) ?></h3>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 mt-1">
                    <?= htmlspecialchars($types[$ct->type] ?? $ct->type, ENT_QUOTES) ?>
                </span>
            </div>
            <div class="flex items-center gap-6 text-center shrink-0">
                <div>
                    <p class="text-xs text-slate-400 mb-1">Coefficient</p>
                    <p class="text-2xl font-bold text-violet-600"><?= $ct->coefficient ?></p>
                </div>
                <div class="w-px h-10 bg-slate-100"></div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Note max</p>
                    <p class="text-2xl font-bold text-slate-700">/<?= (int)$note_max ?></p>
                </div>
                <div class="w-px h-10 bg-slate-100"></div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Saisies</p>
                    <p class="text-2xl font-bold text-emerald-600"><?= $ct->nb_notes ?></p>
                </div>
                <div class="w-px h-10 bg-slate-100"></div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Moy. classe</p>
                    <p class="text-2xl font-bold text-sky-500">
                        <?= $ct->note_moy !== null ? number_format($ct->note_moy, 2) : '—' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($success)): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-emerald-200 bg-emerald-50 text-emerald-800 mb-4" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1"><?= htmlspecialchars($success, ENT_QUOTES) ?></span>
    <button class="ml-auto inline-flex rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100" onclick="this.closest('[role=alert]').remove()">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<?php if (empty($eleves)): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-14">
    <i data-lucide="users" class="w-14 h-14 text-slate-200 mx-auto mb-3"></i>
    <p class="font-semibold text-slate-500 mb-1">Aucun élève dans cette classe</p>
    <p class="text-sm text-slate-400">Vérifiez l'affectation des élèves à cette classe.</p>
</div>
<?php else: ?>
<form method="POST" action="<?= BASE_URL ?>/notes/saisie/<?= $ct->id ?>" novalidate id="saisieForm">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="users" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700"><?= count($eleves) ?> élève(s)</span>
            <div class="flex items-center gap-2 ml-auto">
                <button type="button" class="btn btn-outline" id="btnTousAbsents">
                    <i data-lucide="calendar-x" class="w-4 h-4"></i>Tous absents
                </button>
                <button type="button" class="btn btn-secondary" id="btnReset">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>Réinitialiser
                </button>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50" id="notesTable">
                <thead><tr>
                    <th class="w-10 text-center">#</th>
                    <th>Élève</th>
                    <th class="text-center w-20">
                        <span class="flex items-center justify-center gap-1">
                            <i data-lucide="calendar-x" class="w-3.5 h-3.5 text-amber-500"></i>Absent
                        </span>
                    </th>
                    <th class="text-center w-44">Note <span class="text-slate-400 font-normal">(/ <?= (int)$note_max ?>)</span></th>
                    <th>Appréciation</th>
                    <th class="text-center w-28">Statut</th>
                </tr></thead>
                <tbody>
                <?php foreach ($eleves as $i => $el): ?>
                <?php
                    $hasNote  = $el->note_id !== null;
                    $isAbsent = (bool)($el->absent ?? false);
                    $noteVal  = $el->note ?? '';
                ?>
                <tr class="note-row <?= $isAbsent ? 'bg-amber-50' : '' ?>" id="row<?= $el->eleve_id ?>">
                    <td class="text-center text-slate-400 text-sm"><?= $i + 1 ?></td>
                    <td>
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($el->nom . ' ' . $el->prenom, ENT_QUOTES) ?></p>
                        <p class="font-mono text-xs text-slate-400"><?= htmlspecialchars($el->matricule, ENT_QUOTES) ?></p>
                    </td>
                    <td class="text-center">
                        <input type="checkbox"
                               class="w-4 h-4 accent-amber-500 absent-cb cursor-pointer"
                               id="absent_<?= $el->eleve_id ?>"
                               name="absents[<?= $el->eleve_id ?>]"
                               value="1"
                               data-row="<?= $el->eleve_id ?>"
                               <?= $isAbsent ? 'checked' : '' ?>>
                    </td>
                    <td class="text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <input type="number"
                                   class="form-input text-center text-sm w-24 note-input"
                                   id="note_<?= $el->eleve_id ?>"
                                   name="notes[<?= $el->eleve_id ?>]"
                                   min="0" max="<?= $note_max ?>" step="0.25"
                                   value="<?= $hasNote && !$isAbsent ? $noteVal : '' ?>"
                                   placeholder="—"
                                   <?= $isAbsent ? 'disabled' : '' ?>>
                            <span class="text-xs text-slate-400 shrink-0">/<?= (int)$note_max ?></span>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-input text-sm"
                               name="appreciations[<?= $el->eleve_id ?>]"
                               value="<?= htmlspecialchars($el->appreciation ?? '', ENT_QUOTES) ?>"
                               placeholder="Facultatif…"
                               <?= $isAbsent ? 'disabled' : '' ?>>
                    </td>
                    <td class="text-center">
                        <?php if ($isAbsent): ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">Absent</span>
                        <?php elseif ($hasNote && $noteVal !== null && $noteVal !== ''): ?>
                        <?php
                        $moy = (float)$noteVal;
                        $pct = round($moy / $note_max * 20, 2);
                        $cl  = $pct >= 16 ? 'bg-emerald-100 text-emerald-700' : ($pct >= 12 ? 'bg-violet-100 text-violet-700' : ($pct >= 10 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700'));
                        ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $cl ?>"><?= number_format($pct, 2) ?>/20</span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 flex items-center justify-between py-3 px-4">
            <p class="text-xs text-slate-400 flex items-center gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 text-sky-400"></i>
                Les cellules vides ne modifient pas les notes existantes.
            </p>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les notes
            </button>
        </div>
    </div>
</form>
<?php endif; ?>

<script>
document.querySelectorAll('.absent-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const eleveId = this.dataset.row;
        const row     = document.getElementById('row' + eleveId);
        const noteIn  = document.getElementById('note_' + eleveId);
        const appIn   = row.querySelector('input[name^="appreciations"]');

        if (this.checked) {
            row.classList.add('bg-amber-50');
            if (noteIn) { noteIn.value = ''; noteIn.disabled = true; }
            if (appIn)  { appIn.disabled = true; }
        } else {
            row.classList.remove('bg-amber-50');
            if (noteIn) { noteIn.disabled = false; }
            if (appIn)  { appIn.disabled = false; }
        }
    });
});

document.getElementById('btnTousAbsents')?.addEventListener('click', () => {
    document.querySelectorAll('.absent-cb').forEach(cb => {
        if (!cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change')); }
    });
});

document.getElementById('btnReset')?.addEventListener('click', () => {
    document.querySelectorAll('.absent-cb').forEach(cb => {
        if (cb.checked) { cb.checked = false; cb.dispatchEvent(new Event('change')); }
    });
    document.querySelectorAll('.note-input').forEach(inp => inp.value = '');
});

document.getElementById('saisieForm')?.addEventListener('submit', function(e) {
    let ok = true;
    document.querySelectorAll('.note-input').forEach(inp => {
        if (inp.disabled || inp.value === '') return;
        const v = parseFloat(inp.value);
        const max = parseFloat(inp.max);
        if (isNaN(v) || v < 0 || v > max) {
            inp.classList.add('border-red-400');
            ok = false;
        } else {
            inp.classList.remove('border-red-400');
        }
    });
    if (!ok) {
        e.preventDefault();
        EcoleApp.showToast('Certaines notes sont hors limites (0 – ' + <?= $note_max ?> + ').', 'danger');
    }
});
</script>
