<?php
$currentUser = \Core\Session::getUser();
$result  = $result  ?? ['items'=>[],'total'=>0,'pages'=>1,'currentPage'=>1];
$filters = $filters ?? [];
$classes = $classes ?? [];
$types   = $types   ?? [];
$statuts = $statuts ?? [];
$isParent= $isParent?? false;
$isEleve = $isEleve ?? false;

$items = $result['items']       ?? [];
$total = $result['total']       ?? 0;
$pages = $result['pages']       ?? 1;
$page  = $result['currentPage'] ?? 1;

function absStatutBadgeTw(string $statut, array $statuts): string {
    $s  = $statuts[$statut] ?? ['label' => $statut, 'class' => 'secondary'];
    $cl = match($s['class'] ?? '') {
        'success'  => 'bg-emerald-100 text-emerald-700',
        'danger'   => 'bg-red-100 text-red-700',
        'warning'  => 'bg-amber-100 text-amber-800',
        'info'     => 'bg-sky-100 text-sky-700',
        default    => 'bg-slate-100 text-slate-600',
    };
    return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap ' . $cl . '">' . htmlspecialchars($s['label'], ENT_QUOTES) . '</span>';
}
$csrfToken = \Core\Session::getCsrfToken();
$jourSem   = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
?>

<!-- ── Page header ───────────────────────────────────────────────────────── -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="page-icon"
             style="background:#ede9fe">
            <i data-lucide="list" class="w-5 h-5" style="color:#7c3aed"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900" style="letter-spacing:-.03em">
                <?= htmlspecialchars($title ?? 'Absences', ENT_QUOTES) ?>
            </h2>
            <p class="text-sm text-slate-400">
                <?= $total ?> enregistrement(s) trouv&eacute;(s)
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (!$isParent && !$isEleve && in_array('absences.create', $currentUser['permissions'], true)): ?>
        <a href="<?= BASE_URL ?>/absences/pointage" class="btn btn-primary">
            <i data-lucide="check-square" class="w-4 h-4"></i>Pointage
        </a>
        <a href="<?= BASE_URL ?>/absences/create" class="btn btn-success">
            <i data-lucide="plus" class="w-4 h-4"></i>Manuelle
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/absences" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Dashboard
        </a>
    </div>
</div>

<!-- ── Filtres (admin/enseignant seulement) ───────────────────────────────── -->
<?php if (!$isParent && !$isEleve): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="filter" class="w-4 h-4" style="color:#7c3aed"></i>
        <span class="font-semibold text-slate-700">Filtres</span>
    </div>
    <div class="p-5">
        <form method="GET" action="<?= BASE_URL ?>/absences/liste"
              class="flex flex-wrap items-end gap-3">

            <div class="flex-1 min-w-36">
                <label class="form-label">Recherche</label>
                <input type="text" name="q" class="form-input"
                       placeholder="&#201;l&egrave;ve, matricule&hellip;"
                       value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES) ?>">
            </div>

            <div class="flex-1 min-w-32">
                <label class="form-label">Classe</label>
                <select name="classe_id" class="form-input">
                    <option value="">&mdash; Classe &mdash;</option>
                    <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl->id ?>"
                        <?= ($filters['classe_id'] ?? '') == $cl->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1 min-w-28">
                <label class="form-label">Type</label>
                <select name="type" class="form-input">
                    <option value="">&mdash; Type &mdash;</option>
                    <?php foreach ($types as $k => $v): ?>
                    <option value="<?= $k ?>"
                        <?= ($filters['type'] ?? '') === $k ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1 min-w-28">
                <label class="form-label">Justification</label>
                <select name="statut_justif" class="form-input">
                    <option value="">&mdash; Toutes &mdash;</option>
                    <?php foreach ($statuts as $k => $s): ?>
                    <option value="<?= $k ?>"
                        <?= ($filters['statut_justif'] ?? '') === $k ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['label'], ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1 min-w-28">
                <label class="form-label">Du</label>
                <input type="date" name="date_debut" class="form-input"
                       value="<?= htmlspecialchars($filters['date_debut'] ?? '', ENT_QUOTES) ?>">
            </div>

            <div class="flex-1 min-w-28">
                <label class="form-label">Au</label>
                <input type="date" name="date_fin" class="form-input"
                       value="<?= htmlspecialchars($filters['date_fin'] ?? '', ENT_QUOTES) ?>">
            </div>

            <div class="flex gap-2 shrink-0" style="padding-bottom:.05rem">
                <button type="submit" class="btn btn-primary p-2 aspect-square" title="Rechercher">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= BASE_URL ?>/absences/liste"
                   class="btn btn-secondary p-2 aspect-square" title="R&eacute;initialiser">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>

        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Tableau des absences ───────────────────────────────────────────────── -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead>
                <tr>
                    <th style="width:6.5rem">Date</th>
                    <th>&#201;l&egrave;ve</th>
                    <?php if (!$isEleve): ?><th>Classe</th><?php endif; ?>
                    <th class="text-center" style="width:7rem">Session</th>
                    <th class="text-center" style="width:8rem">Type</th>
                    <th>Motif</th>
                    <th class="text-center" style="width:9rem">Justification</th>
                    <th class="text-right" style="width:6rem">Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php if (empty($items)): ?>
            <tr>
                <td colspan="8" style="padding:3.5rem 1rem;text-align:center">
                    <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500" style="padding:0">
                        <i data-lucide="calendar-check" class="w-12 h-12" style="color:#e4e4ec"></i>
                        <p class="text-sm font-medium text-slate-500">Aucune absence enregistr&eacute;e</p>
                        <p class="text-xs text-slate-400">Aucun r&eacute;sultat pour les filtres s&eacute;lectionn&eacute;s.</p>
                    </div>
                </td>
            </tr>

            <?php else: ?>
            <?php foreach ($items as $abs): ?>
            <tr>
                <!-- Date -->
                <td style="white-space:nowrap">
                    <p class="font-semibold text-slate-800 text-sm">
                        <?= date('d/m/Y', strtotime($abs->date_absence)) ?>
                    </p>
                    <p class="text-xs text-slate-400">
                        <?= $jourSem[date('w', strtotime($abs->date_absence))] ?>
                    </p>
                </td>

                <!-- Élève -->
                <td>
                    <p class="font-semibold text-slate-800 text-sm leading-tight">
                        <?= htmlspecialchars($abs->eleve_nom ?? '', ENT_QUOTES) ?>
                    </p>
                    <p class="mono text-xs text-slate-400">
                        <?= htmlspecialchars($abs->matricule ?? '', ENT_QUOTES) ?>
                    </p>
                </td>

                <!-- Classe (si pas vue élève) -->
                <?php if (!$isEleve): ?>
                <td class="text-xs text-slate-500">
                    <?= htmlspecialchars(($abs->classe_niveau ?? '') . ' ' . ($abs->classe_nom ?? ''), ENT_QUOTES) ?>
                </td>
                <?php endif; ?>

                <!-- Session -->
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">
                        <?= match($abs->session) {
                            'matin'      => 'Matin',
                            'apres_midi' => 'Apr&egrave;s-midi',
                            default      => 'Journ&eacute;e',
                        } ?>
                    </span>
                </td>

                <!-- Type -->
                <td class="text-center">
                    <?php if ($abs->type === 'retard'): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">
                        <i data-lucide="clock" class="w-3 h-3"></i>Retard<?= $abs->duree_retard ? '&nbsp;' . $abs->duree_retard . 'min' : '' ?>
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">
                        <i data-lucide="x-circle" class="w-3 h-3"></i>Absence
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Motif -->
                <td class="text-xs text-slate-500" style="max-width:12rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= $abs->motif ? htmlspecialchars($abs->motif, ENT_QUOTES) : '&mdash;' ?>
                </td>

                <!-- Justification -->
                <td class="text-center">
                    <?= absStatutBadgeTw($abs->statut_justif ?? 'non_justifiee', $statuts) ?>
                </td>

                <!-- Actions -->
                <td class="text-right">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/absences/<?= $abs->id ?>"
                           class="btn btn-ghost btn-icon" style="color:#7c3aed"
                           title="D&eacute;tail">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <?php if (in_array('absences.edit', $currentUser['permissions'], true)): ?>
                        <button type="button"
                                class="btn btn-ghost btn-icon" style="color:#ef4444"
                                onclick="openDelAbs(<?= $abs->id ?>, '<?= htmlspecialchars($abs->eleve_nom ?? '', ENT_QUOTES) ?>')"
                                title="Supprimer">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>

            </tbody>
        </table>
    </div>

    <!-- ── Pagination ──────────────────────────────────────────────────── -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4" style="justify-content:center">
        <?php
        $q = $_GET; unset($q['page']);
        $base = BASE_URL . '/absences/liste?' . http_build_query($q) . '&page=';
        ?>
        <nav class="flex items-center gap-1">
            <a href="<?= $base . ($page - 1) ?>"
               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 <?= $page <= 1 ? 'disabled' : '' ?>"
               aria-label="Page pr&eacute;c&eacute;dente">&laquo;</a>
            <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
            <a href="<?= $base . $p ?>"
               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 <?= $p === $page ? 'active' : '' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
            <a href="<?= $base . ($page + 1) ?>"
               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 <?= $page >= $pages ? 'disabled' : '' ?>"
               aria-label="Page suivante">&raquo;</a>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- ── Modal suppression ─────────────────────────────────────────────────── -->
<?php if (in_array('absences.edit', $currentUser['permissions'], true)): ?>
<div class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex" id="delAbsModal">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2">
                <i data-lucide="trash-2" class="w-4 h-4" style="color:#ef4444"></i>
                Supprimer l&rsquo;absence
            </h3>
            <button class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    onclick="document.getElementById('delAbsModal').classList.remove('active')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-600">
                Supprimer l&rsquo;absence de <strong id="delAbsNom"></strong>&nbsp;?
            </p>
            <p class="text-xs text-slate-400 mt-1.5">
                <i data-lucide="alert-triangle" class="w-3 h-3 inline-block mr-1" style="color:#d97706"></i>
                Cette action est irr&eacute;versible.
            </p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button class="btn btn-secondary"
                    onclick="document.getElementById('delAbsModal').classList.remove('active')">
                Annuler
            </button>
            <form id="delAbsForm" method="POST" style="display:inline">
                <input type="hidden" name="_csrf_token"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <button type="submit" class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openDelAbs(id, nom) {
    document.getElementById('delAbsNom').textContent = nom;
    document.getElementById('delAbsForm').action = '<?= BASE_URL ?>/absences/' + id + '/delete';
    document.getElementById('delAbsModal').classList.add('active');
}
</script>
<?php endif; ?>
