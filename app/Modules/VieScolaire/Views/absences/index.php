<?php
$perms   = $perms  ?? [];
$filters = $filters ?? null;
$classes = $classes ?? [];
$result  = $result  ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'per_page' => 25];

function epAbs(array $p, string $k): bool { return in_array($k, $p, true); }

$statutColors = [
    'non_justifiee' => 'red',
    'en_attente'    => 'amber',
    'justifiee'     => 'emerald',
    'refusee'       => 'slate',
];
$statutLabels = [
    'non_justifiee' => 'Non justifiée',
    'en_attente'    => 'En attente',
    'justifiee'     => 'Justifiée',
    'refusee'       => 'Refusée',
];
$typeLabels = [
    'absence'  => 'Absence',
    'retard'   => 'Retard',
    'dispense' => 'Dispense',
];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-x" class="w-5 h-5 text-violet-600"></i>
            Absences
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Gestion des absences</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (epAbs($perms, 'attendance.view')): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/statistiques" class="btn btn-outline">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i>Statistiques
        </a>
        <?php endif; ?>
        <?php if (epAbs($perms, 'attendance.create')): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/pointage" class="btn btn-primary">
            <i data-lucide="check-square" class="w-4 h-4"></i>Pointage journalier
        </a>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/create" class="btn btn-secondary">
            <i data-lucide="plus" class="w-4 h-4"></i>Saisie unitaire
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>
<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classe</label>
            <select name="classe_id" class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c->id ?>" <?= ($filters?->classeId == $c->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
            <select name="type" class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none">
                <option value="">Tous</option>
                <option value="absence"  <?= ($filters?->type === 'absence')  ? 'selected' : '' ?>>Absence</option>
                <option value="retard"   <?= ($filters?->type === 'retard')   ? 'selected' : '' ?>>Retard</option>
                <option value="dispense" <?= ($filters?->type === 'dispense') ? 'selected' : '' ?>>Dispense</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
            <select name="statut" class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none">
                <option value="">Tous</option>
                <option value="non_justifiee" <?= ($filters?->statut === 'non_justifiee') ? 'selected' : '' ?>>Non justifiée</option>
                <option value="en_attente"    <?= ($filters?->statut === 'en_attente')    ? 'selected' : '' ?>>En attente</option>
                <option value="justifiee"     <?= ($filters?->statut === 'justifiee')     ? 'selected' : '' ?>>Justifiée</option>
                <option value="refusee"       <?= ($filters?->statut === 'refusee')       ? 'selected' : '' ?>>Refusée</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($filters?->dateDebut ?? '', ENT_QUOTES) ?>"
                   class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 focus:ring-2 focus:ring-violet-300 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
            <input type="date" name="date_fin" value="<?= htmlspecialchars($filters?->dateFin ?? '', ENT_QUOTES) ?>"
                   class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 focus:ring-2 focus:ring-violet-300 focus:outline-none">
        </div>
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition-colors">
            <i data-lucide="search" class="w-4 h-4"></i>Filtrer
        </button>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences" class="text-sm text-slate-500 hover:text-slate-700 self-end py-1.5">Réinitialiser</a>
    </form>
</div>

<!-- Tableau -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <span class="text-sm font-medium text-slate-700">
            <?= number_format($result['total']) ?> absence<?= $result['total'] > 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($result['data'])): ?>
    <div class="py-16 text-center text-slate-400">
        <i data-lucide="calendar-check" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
        <p class="text-sm">Aucune absence trouvée.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Classe</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Date</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Type</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Saisi par</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($result['data'] as $row):
                $color = $statutColors[$row['statut']] ?? 'slate';
                $label = $statutLabels[$row['statut']] ?? $row['statut'];
                $type  = $typeLabels[$row['type']]    ?? $row['type'];
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-900">
                        <?= htmlspecialchars($row['eleve_prenom'] . ' ' . $row['eleve_nom'], ENT_QUOTES) ?>
                    </div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($row['matricule'] ?? '', ENT_QUOTES) ?></div>
                </td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['classe_nom'] ?? '', ENT_QUOTES) ?></td>
                <td class="px-4 py-3 text-slate-700 whitespace-nowrap">
                    <?= date('d/m/Y', strtotime($row['date_absence'])) ?>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                        <?= htmlspecialchars($type, ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                        <?= htmlspecialchars($label, ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs"><?= htmlspecialchars($row['saisie_par_nom'] ?? '', ENT_QUOTES) ?></td>
                <td class="px-4 py-3">
                    <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $row['id'] ?>"
                       class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>Voir
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($result['last_page'] > 1): ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between text-sm">
        <span class="text-slate-500">
            Page <?= $result['page'] ?> / <?= $result['last_page'] ?>
        </span>
        <div class="flex gap-1">
            <?php if ($result['page'] > 1): ?>
            <a href="?page=<?= $result['page'] - 1 ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
            <?php if ($result['page'] < $result['last_page']): ?>
            <a href="?page=<?= $result['page'] + 1 ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
