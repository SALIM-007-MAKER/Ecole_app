<?php
/** @var array $user */
/** @var array $stats */
/** @var int $classeId */
/** @var string $annee */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques Discipline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-slate-800">Statistiques Discipline</h1>
        <a href="/v2/vie-scolaire/discipline" class="text-sm text-slate-500 hover:text-violet-600">← Retour</a>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">ID Classe</label>
            <input type="number" name="classe_id" value="<?= htmlspecialchars($classeId ?: '') ?>"
                   placeholder="ID classe"
                   class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-32">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
            <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($annee) ?>"
                   placeholder="2025-2026"
                   class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <button type="submit"
                class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Afficher
        </button>
    </form>

    <?php if (empty($stats)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-10 text-center text-slate-400">
            Sélectionnez une classe pour afficher les statistiques disciplinaires.
        </div>
    <?php else: ?>
        <!-- Tableau par élève -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800">Bilan par élève — <?= htmlspecialchars($annee) ?></h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Total incidents</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Graves</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Sanctions</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Niveau de risque</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($stats as $s): ?>
                        <?php
                            $total   = (int)$s['total_incidents'];
                            $graves  = (int)$s['incidents_graves'];
                            $risk    = $total === 0 ? 'none' : ($graves >= 2 || $total >= 5 ? 'high' : ($graves >= 1 || $total >= 3 ? 'medium' : 'low'));
                            $riskConf = [
                                'none'   => ['label' => '—',       'class' => 'text-slate-400'],
                                'low'    => ['label' => 'Faible',   'class' => 'text-green-600'],
                                'medium' => ['label' => 'Modéré',   'class' => 'text-amber-600'],
                                'high'   => ['label' => 'Élevé',    'class' => 'text-red-600 font-bold'],
                            ];
                        ?>
                        <tr class="hover:bg-slate-50 <?= $risk === 'high' ? 'bg-red-50' : '' ?>">
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars($s['eleve_nom'] . ' ' . $s['eleve_prenom']) ?>
                            </td>
                            <td class="px-4 py-3 text-center <?= $total >= 3 ? 'text-red-600 font-bold' : 'text-slate-700' ?>">
                                <?= $total ?>
                            </td>
                            <td class="px-4 py-3 text-center <?= $graves >= 1 ? 'text-red-500 font-semibold' : 'text-slate-500' ?>">
                                <?= $graves ?>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-700"><?= (int)$s['total_sanctions'] ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="<?= $riskConf[$risk]['class'] ?> text-xs">
                                    <?= $riskConf[$risk]['label'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Résumé agrégé -->
        <?php
            $totalIncidents  = array_sum(array_column($stats, 'total_incidents'));
            $totalGraves     = array_sum(array_column($stats, 'incidents_graves'));
            $totalSanctions  = array_sum(array_column($stats, 'total_sanctions'));
            $elevesAvec      = count(array_filter($stats, fn($r) => (int)$r['total_incidents'] > 0));
        ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-slate-800"><?= $totalIncidents ?></p>
                <p class="text-xs text-slate-500 mt-1">Total incidents</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-red-600"><?= $totalGraves ?></p>
                <p class="text-xs text-slate-500 mt-1">Incidents graves</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600"><?= $totalSanctions ?></p>
                <p class="text-xs text-slate-500 mt-1">Sanctions prononcées</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-slate-700"><?= $elevesAvec ?></p>
                <p class="text-xs text-slate-500 mt-1">Élèves concernés</p>
            </div>
        </div>
    <?php endif; ?>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
