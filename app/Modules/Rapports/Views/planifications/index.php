<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports planifiés</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
            <i data-lucide="calendar-clock" class="w-6 h-6 text-violet-600"></i>
            Rapports planifiés
        </h1>
        <a href="/v2/rapports/planifications/create"
           class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle planification
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">
        <?= $_GET['success'] === 'deleted' ? 'Planification supprimée.' : 'Planification enregistrée avec succès.' ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Nom</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Domaine</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Fréquence</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Heure</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Format</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Actif</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($liste['data'])): ?>
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune planification configurée.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($liste['data'] as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($p['nom']) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-violet-100 text-violet-700">
                            <?= htmlspecialchars($p['domaine']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600 capitalize"><?= htmlspecialchars($p['frequence']) ?></td>
                    <td class="px-4 py-3 text-slate-500 text-xs"><?= htmlspecialchars(substr($p['heure_execution'], 0, 5)) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs rounded font-mono bg-slate-100 text-slate-600">
                            <?= strtoupper(htmlspecialchars($p['type_export'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="w-2 h-2 rounded-full inline-block <?= $p['actif'] ? 'bg-green-500' : 'bg-slate-300' ?>"></span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 justify-end">
                            <a href="/v2/rapports/planifications/<?= $p['id'] ?>" class="text-slate-400 hover:text-violet-600" title="Voir">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <a href="/v2/rapports/planifications/<?= $p['id'] ?>/edit" class="text-slate-400 hover:text-violet-600" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <form method="post" action="/v2/rapports/planifications/<?= $p['id'] ?>/executer"
                                  onsubmit="return confirm('Exécuter maintenant ?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                <button class="text-slate-400 hover:text-green-600" title="Exécuter">
                                    <i data-lucide="play" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (($liste['total'] ?? 0) > ($liste['per_page'] ?? 20)): ?>
    <div class="flex justify-center gap-2 mt-4">
        <?php for ($p = 1; $p <= ceil($liste['total'] / $liste['per_page']); $p++): ?>
        <a href="?page=<?= $p ?>"
           class="px-3 py-1.5 text-sm rounded-lg <?= ($liste['page'] ?? 1) == $p ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
