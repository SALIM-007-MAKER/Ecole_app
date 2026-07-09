<?php
/** @var array $user */
/** @var array $dossiers */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var \App\Modules\VieScolaire\Discipline\DTO\DisciplineFiltersDTO $filters */

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$statutLabels = [
    'ouvert'   => ['label' => 'Ouvert',   'class' => 'bg-amber-100 text-amber-800'],
    'en_cours' => ['label' => 'En cours', 'class' => 'bg-blue-100 text-blue-800'],
    'clos'     => ['label' => 'Clos',     'class' => 'bg-slate-100 text-slate-600'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discipline — Dossiers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Discipline</h1>
            <p class="text-slate-500 text-sm mt-1"><?= $total ?> dossier<?= $total > 1 ? 's' : '' ?></p>
        </div>
        <a href="/v2/vie-scolaire/discipline/create"
           class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Signaler un incident
        </a>
    </div>

    <?php if ($flash_success): ?>
        <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
            <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($filters->anneeScolaire ?? '') ?>"
                   placeholder="2025-2026" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
            <select name="statut" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <option value="ouvert"   <?= ($filters->statut === 'ouvert')   ? 'selected' : '' ?>>Ouvert</option>
                <option value="en_cours" <?= ($filters->statut === 'en_cours') ? 'selected' : '' ?>>En cours</option>
                <option value="clos"     <?= ($filters->statut === 'clos')     ? 'selected' : '' ?>>Clos</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="flex items-end gap-2">
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
                <input type="date" name="date_fin" value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap">
                Filtrer
            </button>
        </div>
    </form>

    <!-- Tableau -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Classe</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Année</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Incidents</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Ouvert le</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($dossiers)): ?>
                    <tr><td colspan="7" class="text-center py-10 text-slate-400">Aucun dossier disciplinaire</td></tr>
                <?php else: ?>
                    <?php foreach ($dossiers as $d): ?>
                        <?php $info = $statutLabels[$d['statut']] ?? ['label' => $d['statut'], 'class' => 'bg-slate-100 text-slate-600']; ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars($d['eleve_nom'] . ' ' . $d['eleve_prenom']) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($d['classe_nom']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($d['annee_scolaire']) ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold <?= $d['nb_incidents'] >= 3 ? 'text-red-600' : 'text-slate-700' ?>">
                                    <?= (int)$d['nb_incidents'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $info['class'] ?>">
                                    <?= $info['label'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="/v2/vie-scolaire/discipline/<?= $d['id'] ?>"
                                   class="text-violet-600 hover:text-violet-800 font-medium text-xs">Voir →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <div class="flex justify-center gap-2 mt-6">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="px-3 py-1 rounded-lg text-sm <?= $i === $page ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <div class="mt-4 text-right">
        <a href="/v2/vie-scolaire/discipline/export?<?= http_build_query($_GET) ?>"
           class="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-violet-600">
            <i data-lucide="download" class="w-4 h-4"></i> Exporter CSV
        </a>
    </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
