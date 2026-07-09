<?php
$perms = (\Core\Session::getUser())['permissions'] ?? [];
function cperm(array $p, string $k): bool { return in_array($k, $p, true); }
$filters   = $filters  ?? [];
$controles = $controles ?? [];
$classes   = $classes  ?? [];
$periodes  = $periodes  ?? [];
$matieres  = $matieres ?? [];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-sky-100 flex items-center justify-center shrink-0">
            <i data-lucide="clipboard-list" class="w-5 h-5 text-sky-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Contrôles et évaluations</h2>
            <p class="text-sm text-slate-500">
                <?= count($controles) ?> contrôle(s) trouvé(s)
            </p>
        </div>
    </div>
    <?php if (cperm($perms, 'notes.create')): ?>
    <a href="<?= BASE_URL ?>/notes/controles/create<?= ($filters['classeId'] ?? 0) ? '?classe_id=' . $filters['classeId'] . '&periode_id=' . ($filters['periodeId'] ?? 0) : '' ?>"
       class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouveau contrôle
    </a>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" action="<?= BASE_URL ?>/notes/controles"
              class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-36">
                <label class="form-label text-xs">Classe</label>
                <select name="classe_id" class="form-input text-sm">
                    <option value="">— Toutes —</option>
                    <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl->id ?>"
                        <?= ($filters['classeId'] ?? 0) == $cl->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="form-label text-xs">Période</label>
                <select name="periode_id" class="form-input text-sm">
                    <option value="">— Toutes —</option>
                    <?php foreach ($periodes as $p): ?>
                    <option value="<?= $p->id ?>"
                        <?= ($filters['periodeId'] ?? 0) == $p->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p->nom . ' — ' . $p->annee_scolaire, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="form-label text-xs">Matière</label>
                <select name="matiere_id" class="form-input text-sm">
                    <option value="">— Toutes —</option>
                    <?php foreach ($matieres as $m): ?>
                    <option value="<?= $m->id ?>"
                        <?= ($filters['matiereId'] ?? 0) == $m->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>Filtrer
                </button>
                <a href="<?= BASE_URL ?>/notes/controles" class="btn btn-secondary p-2 aspect-square" title="Réinitialiser">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <?php if (empty($controles)): ?>
    <div class="p-5 flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-14">
        <i data-lucide="clipboard-x" class="w-14 h-14 text-slate-200 mx-auto mb-3"></i>
        <p class="font-semibold text-slate-500 mb-1">Aucun contrôle trouvé</p>
        <?php if (!($filters['classeId'] ?? 0)): ?>
        <p class="text-sm text-slate-400 mb-4">Sélectionnez une classe et une période pour commencer.</p>
        <?php else: ?>
        <p class="text-sm text-slate-400 mb-4">Aucun contrôle pour les filtres sélectionnés.</p>
        <?php endif; ?>
        <?php if (cperm($perms, 'notes.create')): ?>
        <a href="<?= BASE_URL ?>/notes/controles/create" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>Créer le premier contrôle
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="clipboard-list" class="w-4 h-4 text-sky-500"></i>
        <span class="font-semibold text-slate-700"><?= count($controles) ?> contrôle(s)</span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead><tr>
                <th>Contrôle</th>
                <th>Matière</th>
                <th>Classe</th>
                <th class="text-center">Coef</th>
                <th class="text-center">Max</th>
                <th class="text-center">Notes</th>
                <th class="text-center">Moy.</th>
                <th>Date</th>
                <th class="text-right col-actions">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($controles as $c): ?>
            <tr>
                <td>
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($c->libelle, ENT_QUOTES) ?></p>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 text-xs mt-0.5">
                        <?= htmlspecialchars(\App\Models\ControleModel::TYPES[$c->type] ?? $c->type, ENT_QUOTES) ?>
                    </span>
                </td>
                <td>
                    <p class="font-semibold text-sm text-slate-700"><?= htmlspecialchars($c->matiere_nom, ENT_QUOTES) ?></p>
                    <p class="text-xs text-slate-400">coef. matière <?= $c->matiere_coef ?></p>
                </td>
                <td>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700">
                        <?= htmlspecialchars($c->classe_niveau . ' ' . $c->classe_nom, ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="text-center font-semibold text-sm text-violet-600"><?= $c->coefficient ?></td>
                <td class="text-center text-slate-400 text-sm">/<?= (int)$c->note_max ?></td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700"><?= $c->nb_notes ?></span>
                </td>
                <td class="text-center">
                    <?php if ($c->note_moy !== null): ?>
                    <?php
                    $pct = (float)$c->note_moy / (float)$c->note_max * 20;
                    $mClass = $pct >= 14 ? 'text-emerald-600' : ($pct >= 10 ? 'text-amber-500' : 'text-red-500');
                    ?>
                    <span class="font-bold text-sm <?= $mClass ?>">
                        <?= number_format($c->note_moy, 2) ?><span class="text-slate-400 font-normal">/<?= (int)$c->note_max ?></span>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300 text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-sm text-slate-500 whitespace-nowrap">
                    <?= $c->date_controle ? date('d/m/Y', strtotime($c->date_controle)) : '—' ?>
                </td>
                <td class="text-right col-actions">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/notes/saisie/<?= $c->id ?>"
                           class="btn btn-primary p-2 aspect-square px-2.5 py-1.5 text-xs rounded-md" title="Saisir les notes">
                            <i data-lucide="pencil-line" class="w-4 h-4"></i>
                        </a>
                        <?php if (cperm($perms, 'notes.edit')): ?>
                        <a href="<?= BASE_URL ?>/notes/controles/<?= $c->id ?>/edit"
                           class="btn btn-ghost btn-icon text-amber-500" title="Modifier">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (cperm($perms, 'notes.delete')): ?>
                        <button class="btn btn-ghost btn-icon text-red-400"
                                onclick="openDelCtrl(<?= $c->id ?>, '<?= htmlspecialchars(addslashes($c->libelle), ENT_QUOTES) ?>', <?= $c->nb_notes ?>)"
                                title="Supprimer">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal suppression -->
<?php if (cperm($perms, 'notes.delete')): ?>
<div id="delCtrlModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:440px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-red-500"></i>Supprimer le contrôle
            </h3>
            <button class="btn btn-ghost btn-icon text-slate-400" onclick="closeDelCtrl()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-700">
                Supprimer <strong id="delCtrlNom" class="text-slate-900"></strong> ?
            </p>
            <div id="delCtrlWarn" class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900 mt-3">
                <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
                <span id="delCtrlWarnTxt" class="text-sm"></span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="button" class="btn btn-secondary" onclick="closeDelCtrl()">Annuler</button>
            <form id="delCtrlForm" method="POST" class="inline">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function openDelCtrl(id, libelle, nbNotes) {
    document.getElementById('delCtrlNom').textContent = libelle;
    document.getElementById('delCtrlForm').action = '<?= BASE_URL ?>/notes/controles/' + id + '/delete';
    document.getElementById('delCtrlWarnTxt').textContent =
        'Les ' + nbNotes + ' note(s) saisie(s) et les moyennes seront recalculées.';
    document.getElementById('delCtrlModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDelCtrl() {
    document.getElementById('delCtrlModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>
<?php endif; ?>
