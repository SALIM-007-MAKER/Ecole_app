<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes exports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
            <i data-lucide="download" class="w-6 h-6 text-violet-600"></i>
            Mes exports
        </h1>
        <a href="/v2/rapports/exports/form"
           class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvel export
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">Export lancé avec succès.</div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Domaine</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Format</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Fichier</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Lignes</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Généré le</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Expire</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($exports)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun export généré.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($exports as $e): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-violet-100 text-violet-700">
                            <?= htmlspecialchars($e['domaine']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs rounded font-mono bg-slate-100 text-slate-600">
                            <?= strtoupper(htmlspecialchars($e['type_export'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($e['fichier_nom'] ?? '') ?></td>
                    <td class="px-4 py-3 text-right text-slate-600"><?= number_format((int)($e['nb_lignes'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-slate-500 text-xs"><?= htmlspecialchars($e['created_at'] ?? '') ?></td>
                    <td class="px-4 py-3 text-xs <?= strtotime($e['expire_at'] ?? '') < time() ? 'text-red-500' : 'text-slate-400' ?>">
                        <?= htmlspecialchars($e['expire_at'] ?? 'N/A') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
