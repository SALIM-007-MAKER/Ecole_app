<?php
/** @var array $user */
/** @var array $data */
/** @var int $classeId */
/** @var string $annee */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement Comportemental</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Classement comportemental</h1>
            <p class="text-slate-500 text-sm mt-1">Score = récompenses × 2 − incidents disciplinaires</p>
        </div>
        <a href="/v2/vie-scolaire/recompenses" class="text-sm text-slate-500 hover:text-violet-600">← Retour</a>
    </div>

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

    <?php if (empty($data)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-10 text-center text-slate-400">
            Sélectionnez une classe pour afficher le classement comportemental.
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-center px-4 py-3 font-medium text-slate-600 w-16">Rang</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">
                            <span class="text-green-600">Récompenses</span>
                        </th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">
                            <span class="text-red-500">Incidents</span>
                        </th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($data as $rank => $row): ?>
                        <?php
                            $position = $rank + 1;
                            $score    = (int)$row['score_comportemental'];
                            $rowBg    = $position === 1 ? 'bg-amber-50'
                                      : ($position === 2 ? 'bg-slate-50'
                                      : ($position === 3 ? 'bg-orange-50' : ''));
                            $scoreClass = $score > 0 ? 'text-green-600 font-bold'
                                        : ($score < 0 ? 'text-red-600 font-bold' : 'text-slate-500');
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $rowBg ?>">
                            <td class="px-4 py-3 text-center">
                                <?php if ($position === 1): ?>
                                    <span class="text-amber-500 font-bold text-lg">🥇</span>
                                <?php elseif ($position === 2): ?>
                                    <span class="text-slate-400 font-bold text-lg">🥈</span>
                                <?php elseif ($position === 3): ?>
                                    <span class="text-orange-400 font-bold text-lg">🥉</span>
                                <?php else: ?>
                                    <span class="text-slate-400 font-medium"><?= $position ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars($row['eleve_nom'] . ' ' . $row['eleve_prenom']) ?>
                            </td>
                            <td class="px-4 py-3 text-center text-green-600 font-semibold">
                                +<?= (int)$row['total_recompenses'] ?>
                            </td>
                            <td class="px-4 py-3 text-center text-red-500 font-semibold">
                                −<?= (int)$row['total_incidents'] ?>
                            </td>
                            <td class="px-4 py-3 text-center text-lg <?= $scoreClass ?>">
                                <?= $score > 0 ? '+' : '' ?><?= $score ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-400 text-center">
            Score comportemental = (récompenses validées × 2) − incidents disciplinaires.
            Les récompenses révoquées et incidents archivés ne sont pas comptabilisés.
        </p>
    <?php endif; ?>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
