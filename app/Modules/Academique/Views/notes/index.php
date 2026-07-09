<?php
$evaluation = $evaluation ?? null;
$pagination = $pagination ?? ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];
$stats      = $stats      ?? [];
$nbEleves   = $nbEleves   ?? 0;
$filters    = $filters    ?? null;
$statuts    = $statuts    ?? [];
$colors     = $colors     ?? [];
$policy     = $policy     ?? null;
$user       = $user       ?? [];

if (!$evaluation) return;
?>

<!-- En-tête évaluation -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
            <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>"
               class="hover:text-violet-600 transition">
                <?= htmlspecialchars($evaluation->libelle, ENT_QUOTES) ?>
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3"></i>
            <span>Notes</span>
        </div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="pen-line" class="w-5 h-5 text-violet-500"></i>
            Notes — <?= htmlspecialchars($evaluation->libelle, ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= htmlspecialchars($evaluation->classe_nom, ENT_QUOTES) ?>
            · <?= htmlspecialchars($evaluation->matiere_nom, ENT_QUOTES) ?>
            · /<?= number_format((float)$evaluation->note_max, 0) ?>
            <?php if ((int)$evaluation->notes_saisie_ouverte): ?>
            · <span class="text-emerald-600 font-medium">Saisie ouverte</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <?php if ($policy && $policy->canSaisir($user, $evaluation)): ?>
        <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes/saisie"
           class="btn btn-primary text-sm">
            <i data-lucide="edit-3" class="w-4 h-4"></i>Saisir les notes
        </a>
        <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes/importer"
           class="btn btn-secondary text-sm">
            <i data-lucide="upload" class="w-4 h-4"></i>Import CSV
        </a>
        <?php endif; ?>
        <?php if ($policy && $policy->canPublier($user) && ($stats['saisies'] ?? 0) > 0): ?>
        <form method="POST"
              action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes/publier"
              onsubmit="return confirm('Publier toutes les notes en statut « saisie » ?')">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" class="btn btn-secondary text-sm">
                <i data-lucide="send" class="w-4 h-4"></i>Publier tout (<?= $stats['saisies'] ?>)
            </button>
        </form>
        <?php endif; ?>
        <?php if ($policy && $policy->canVerrouiller($user) && ($stats['publiees'] ?? 0) > 0
                && $evaluation->statut === 'verrouillee'): ?>
        <form method="POST"
              action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes/verrouiller"
              onsubmit="return confirm('Verrouiller définitivement toutes les notes publiées ?')">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" class="btn btn-secondary text-sm">
                <i data-lucide="lock" class="w-4 h-4 text-red-500"></i>Verrouiller (<?= $stats['publiees'] ?>)
            </button>
        </form>
        <?php endif; ?>
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

<!-- Statistiques -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-violet-600"><?= (int)($stats['total'] ?? 0) ?>/<?= $nbEleves ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Notes saisies</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-blue-600"><?= (int)($stats['saisies'] ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5">En cours</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-emerald-600"><?= (int)($stats['publiees'] ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Publiées</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-amber-600"><?= (int)($stats['absents'] ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Absents</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <?php $moy = $stats['note_moyenne'] ?? null; ?>
        <p class="text-2xl font-bold text-slate-700">
            <?= $moy !== null ? number_format((float)$moy, 2) : '—' ?>
        </p>
        <p class="text-xs text-slate-500 mt-0.5">Moyenne brute</p>
    </div>
</div>

<!-- Filtres -->
<form method="GET" class="flex flex-wrap gap-3 mb-4">
    <input type="hidden" name="evaluation_id" value="<?= $evaluation->id ?>">
    <select name="statut" class="form-input w-36">
        <option value="">Tous statuts</option>
        <?php foreach ($statuts as $val => $label): ?>
        <option value="<?= $val ?>" <?= ($filters?->statut ?? '') === $val ? 'selected' : '' ?>>
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <select name="presence" class="form-input w-36">
        <option value="all" <?= ($filters?->presence ?? 'all') === 'all' ? 'selected' : '' ?>>Tous</option>
        <option value="present"  <?= ($filters?->presence ?? '') === 'present'  ? 'selected' : '' ?>>Présents</option>
        <option value="absent"   <?= ($filters?->presence ?? '') === 'absent'   ? 'selected' : '' ?>>Absents</option>
    </select>
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>Filtrer
    </button>
    <a href="?evaluation_id=<?= $evaluation->id ?>" class="btn btn-secondary">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>
</form>

<!-- Tableau -->
<?php if (empty($pagination['data'])): ?>
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400 shadow-sm">
    <i data-lucide="pen-line" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
    <p class="font-medium">Aucune note trouvée.</p>
    <?php if ($policy && $policy->canSaisir($user, $evaluation)): ?>
    <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/notes/saisie"
       class="btn btn-primary mt-4 inline-flex">
        <i data-lucide="edit-3" class="w-4 h-4"></i>Saisir les notes
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Note</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Présence</th>
                <th class="text-left px-4 py-3 font-medium text-slate-600">Commentaire</th>
                <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                <th class="text-right px-4 py-3 font-medium text-slate-600">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $note): ?>
            <?php
                $color = $colors[$note->statut] ?? 'slate';
                $pct   = $note->valeur !== null && (float)$note->note_max > 0
                    ? round((float)$note->valeur / (float)$note->note_max * 100) : null;
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-900">
                        <?= htmlspecialchars($note->eleve_prenom . ' ' . $note->eleve_nom, ENT_QUOTES) ?>
                    </div>
                    <?php if ($note->matricule): ?>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($note->matricule, ENT_QUOTES) ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <?php if ((int)$note->est_absent): ?>
                    <span class="text-amber-600 font-medium text-xs">ABS</span>
                    <?php elseif ($note->valeur !== null): ?>
                    <div class="font-bold text-slate-900 font-mono">
                        <?= number_format((float)$note->valeur, 2) ?>
                    </div>
                    <?php if ($pct !== null): ?>
                    <div class="w-16 h-1.5 bg-slate-100 rounded-full mx-auto mt-1">
                        <div class="h-1.5 rounded-full
                            <?= $pct >= 50 ? 'bg-emerald-500' : 'bg-red-400' ?>"
                             style="width:<?= $pct ?>%"></div>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-slate-300 text-xs">—</span>
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
                <td class="px-4 py-3 text-xs text-slate-500 max-w-32 truncate">
                    <?= htmlspecialchars($note->commentaire ?? '', ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5
                          rounded-full bg-<?= $color ?>-100 text-<?= $color ?>-700">
                        <?= $statuts[$note->statut] ?? $note->statut ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="<?= BASE_URL ?>/v2/academique/notes/<?= $note->id ?>"
                       class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded transition"
                       title="Voir détail">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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
<?php endif; ?>
