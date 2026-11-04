<?php
$pagination = $pagination ?? ['data'=>[],'total'=>0,'last_page'=>1,'current_page'=>1];
$filters    = $filters    ?? null;
$statuts    = $statuts    ?? [];
$colors     = $colors     ?? [];
$eleve      = $eleve      ?? null;
$user       = $user       ?? [];
$stats      = $stats      ?? null;
$policy     = $policy     ?? null;
$periodeActive = $periodeActive ?? null;

$initiales = $eleve
    ? mb_strtoupper(mb_substr($eleve->prenom, 0, 1) . mb_substr($eleve->nom, 0, 1))
    : '?';
$flash = \Core\Session::getFlash();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<!-- En-tête élève -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <?php if ($eleve): ?>
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-violet-100 text-violet-700 flex items-center justify-center
                    text-lg font-bold shrink-0">
            <?= htmlspecialchars($initiales, ENT_QUOTES) ?>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="pen-line" class="w-5 h-5 text-violet-500"></i>
                <?= htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) ?>
            </h2>
            <p class="text-sm text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                <?php if (!empty($eleve->matricule)): ?>
                <span class="font-mono"><?= htmlspecialchars($eleve->matricule, ENT_QUOTES) ?></span>
                <span class="text-slate-300">·</span>
                <?php endif; ?>
                <?php if (!empty($eleve->classe_nom)): ?>
                <span><?= htmlspecialchars($eleve->classe_nom, ENT_QUOTES) ?></span>
                <?php if (!empty($eleve->classe_niveau)): ?>
                <span class="text-slate-300">·</span>
                <span><?= htmlspecialchars($eleve->classe_niveau, ENT_QUOTES) ?></span>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-slate-400 italic">Aucune classe affectée</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <h2 class="text-xl font-bold text-slate-900">Notes</h2>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2">
        <?php if ($eleve): ?>
        <a href="<?= BASE_URL ?>/eleves/<?= $eleve->id ?>" class="btn btn-secondary text-sm">
            <i data-lucide="user" class="w-4 h-4"></i>Fiche élève
        </a>
        <?php if ($periodeActive):
            $csEleveId = $eleve->id; $csSemestres = $semestresDisponibles ?? []; $csLabel = 'Imprimer bulletin'; $csIcon = 'printer';
            include ROOT_PATH . '/app/Views/bulletins/_choix_semestre.php';
        endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($eleve && $stats): ?>
<!-- Statistiques -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <?php $moy = $stats->moyenne_generale ?? null; ?>
        <p class="text-2xl font-bold <?= $moy !== null && (float)$moy >= 10 ? 'text-emerald-600' : 'text-slate-700' ?>">
            <?= $moy !== null ? number_format((float)$moy, 2) : '—' ?><span class="text-sm text-slate-400">/20</span>
        </p>
        <p class="text-xs text-slate-500 mt-0.5">Moyenne générale</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-violet-600"><?= (int)($stats->total ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Notes au total</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-emerald-600"><?= (int)($stats->publiees ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Publiées</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-amber-600"><?= (int)($stats->absents ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Absences</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-sky-600"><?= (int)($stats->nb_matieres ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Matières notées</p>
    </div>
</div>
<?php endif; ?>

<!-- Filtres -->
<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <?php if ($eleve): ?>
    <input type="hidden" name="eleve_id" value="<?= $eleve->id ?>">
    <?php endif; ?>
    <div>
        <label class="block text-xs text-slate-500 mb-1">Statut</label>
        <select name="statut" class="form-input w-40">
            <option value="">Tous statuts</option>
            <?php foreach ($statuts as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($filters?->statut ?? '') === $val ? 'selected' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs text-slate-500 mb-1">Présence</label>
        <select name="presence" class="form-input w-36">
            <option value="all" <?= ($filters?->presence ?? 'all') === 'all' ? 'selected' : '' ?>>Toutes</option>
            <option value="present" <?= ($filters?->presence ?? '') === 'present' ? 'selected' : '' ?>>Présent</option>
            <option value="absent"  <?= ($filters?->presence ?? '') === 'absent'  ? 'selected' : '' ?>>Absent</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>Filtrer
    </button>
    <?php if (($filters?->statut ?? '') !== '' || ($filters?->presence ?? 'all') !== 'all'): ?>
    <a href="?<?= $eleve ? 'eleve_id=' . $eleve->id : '' ?>" class="btn btn-secondary" title="Réinitialiser les filtres">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>
    <?php endif; ?>
    <div class="ml-auto text-sm text-slate-500 self-center">
        <?= (int)($pagination['total'] ?? 0) ?> note(s)
    </div>
</form>

<?php if (empty($pagination['data'])): ?>
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400 shadow-sm">
    <i data-lucide="pen-line" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
    <p class="font-medium">Aucune note trouvée<?= $eleve ? ' pour cet élève' : '' ?>.</p>
    <?php if (($filters?->statut ?? '') !== '' || ($filters?->presence ?? 'all') !== 'all'): ?>
    <p class="text-sm mt-1">Essayez d'élargir les filtres appliqués.</p>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600">Date</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600">Matière</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600">Évaluation</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Coeff.</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Note</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Présence</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600 col-actions">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($pagination['data'] as $note): ?>
            <?php
            $pct = $note->valeur !== null && (float)$note->note_max > 0
                ? round((float)$note->valeur / (float)$note->note_max * 100) : null;
            $noteColor = $colors[$note->statut] ?? 'slate';
            $typeColor = $note->type_couleur ?? 'slate';
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                    <?= $note->date_evaluation ? date('d/m/Y', strtotime($note->date_evaluation)) : '—' ?>
                </td>
                <td class="px-4 py-3">
                    <span class="font-medium text-slate-900">
                        <?= htmlspecialchars($note->matiere_nom ?? '—', ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="text-slate-700"><?= htmlspecialchars($note->eval_libelle ?? '—', ENT_QUOTES) ?></div>
                    <?php if (!empty($note->type_nom)): ?>
                    <span class="inline-flex items-center gap-1 text-xs mt-0.5 px-1.5 py-0.5 rounded
                          bg-<?= $typeColor ?>-50 text-<?= $typeColor ?>-700">
                        <?= htmlspecialchars($note->type_nom, ENT_QUOTES) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center text-slate-500 font-mono">
                    ×<?= number_format((float)($note->coefficient ?? 1), 2) ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <div id="note-view-<?= $note->id ?>">
                        <?php if ((int)$note->est_absent): ?>
                            <span class="text-amber-600 font-medium text-xs">ABS</span>
                        <?php elseif ($note->valeur !== null): ?>
                            <div class="font-bold text-slate-900 font-mono"><?= number_format((float)$note->valeur, 2) ?></div>
                            <?php if ($pct !== null): ?>
                            <div class="w-16 h-1.5 bg-slate-100 rounded-full mx-auto mt-1">
                                <div class="h-1.5 rounded-full <?= $pct >= 50 ? 'bg-emerald-500' : 'bg-red-400' ?>"
                                     style="width:<?= $pct ?>%"></div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($policy && $policy->canModifier($user, $note)): ?>
                    <form id="note-edit-<?= $note->id ?>" method="POST"
                          action="<?= BASE_URL ?>/v2/academique/notes/<?= $note->id ?>"
                          class="hidden flex flex-col items-center gap-1.5">
                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                        <input type="hidden" name="commentaire" value="<?= htmlspecialchars($note->commentaire ?? '', ENT_QUOTES) ?>">
                        <input type="number" name="valeur" step="0.25" min="0" max="<?= (float)$note->note_max ?>"
                               value="<?= $note->valeur !== null ? htmlspecialchars((string)$note->valeur, ENT_QUOTES) : '' ?>"
                               placeholder="/<?= number_format((float)$note->note_max, 0) ?>"
                               class="w-16 px-1.5 py-1 border border-slate-300 rounded text-sm font-mono text-center
                                      focus:outline-none focus:ring-2 focus:ring-violet-400">
                        <label class="flex items-center gap-1 text-xs text-slate-500 whitespace-nowrap">
                            <input type="checkbox" name="est_absent" value="1"
                                   <?= (int)$note->est_absent ? 'checked' : '' ?>
                                   class="w-3.5 h-3.5 rounded accent-amber-500"> Absent
                        </label>
                        <div class="flex gap-1">
                            <button type="submit" title="Enregistrer"
                                    class="px-2 py-1 text-xs bg-violet-600 hover:bg-violet-700 text-white rounded transition">
                                <i data-lucide="check" class="w-3 h-3"></i>
                            </button>
                            <button type="button" title="Annuler" onclick="toggleNoteEdit(<?= $note->id ?>)"
                                    class="px-2 py-1 text-xs border border-slate-300 rounded hover:bg-slate-50 transition">
                                <i data-lucide="x" class="w-3 h-3"></i>
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <?php if ((int)$note->est_absent): ?>
                    <span class="inline-flex items-center gap-1 text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="user-x" class="w-3 h-3"></i>Absent
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="check" class="w-3 h-3"></i>Présent
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5
                          rounded-full bg-<?= $noteColor ?>-100 text-<?= $noteColor ?>-700">
                        <?= htmlspecialchars($statuts[$note->statut] ?? $note->statut, ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-right col-actions">
                    <div class="flex items-center justify-end gap-1">
                        <?php if ($policy && $policy->canModifier($user, $note)): ?>
                        <button type="button" onclick="toggleNoteEdit(<?= $note->id ?>)"
                                class="btn btn-ghost btn-icon text-slate-500"
                                title="Modifier la note">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/v2/academique/notes/<?= $note->id ?>"
                           class="btn btn-ghost btn-icon text-slate-500"
                           title="Voir détail">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <?php if ($policy && $policy->canSupprimer($user, $note)): ?>
                        <form method="POST" action="<?= BASE_URL ?>/v2/academique/notes/<?= $note->id ?>/delete"
                              onsubmit="return confirm('Supprimer définitivement cette note ? Action irréversible et auditée.')">
                            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                            <button type="submit"
                                    class="btn btn-ghost btn-icon text-red-400 hover:text-red-600"
                                    title="Supprimer la note">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($pagination['last_page'] > 1): ?>
    <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-500">
        <span><?= $pagination['total'] ?> note(s)</span>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
               class="px-3 py-1 rounded <?= $i === $pagination['current_page'] ? 'bg-violet-600 text-white' : 'hover:bg-slate-100' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
function toggleNoteEdit(id) {
    document.getElementById('note-view-' + id)?.classList.toggle('hidden');
    document.getElementById('note-edit-' + id)?.classList.toggle('hidden');
}
</script>
<?php endif; ?>
