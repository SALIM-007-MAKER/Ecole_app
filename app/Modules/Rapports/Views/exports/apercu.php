<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aperçu rapport</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="/v2/rapports/exports/form" class="text-slate-400 hover:text-violet-600">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($rapport['titre'] ?? 'Rapport') ?></h1>
        </div>
        <div class="flex gap-2">
            <a href="/v2/rapports/exports/csv?<?= http_build_query($filters?->toArray() ?? []) ?>"
               class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg text-sm hover:bg-slate-50">
                <i data-lucide="download" class="w-4 h-4"></i> CSV
            </a>
            <a href="/v2/rapports/exports/excel?<?= http_build_query($filters?->toArray() ?? []) ?>"
               class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg text-sm hover:bg-slate-50">
                <i data-lucide="table" class="w-4 h-4"></i> Excel
            </a>
        </div>
    </div>

    <!-- Méta -->
    <div class="flex flex-wrap gap-4 text-sm text-slate-500 mb-5">
        <span><strong>Période :</strong> <?= htmlspecialchars($rapport['periode'] ?? '') ?></span>
        <span><strong>Lignes :</strong> <?= number_format($rapport['nb_total'] ?? 0) ?></span>
        <span><strong>Généré à :</strong> <?= htmlspecialchars($rapport['genere_a'] ?? '') ?></span>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <?php foreach ($rapport['colonnes'] ?? [] as $col): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">
                        <?= htmlspecialchars($col['label'] ?? $col['key']) ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($rapport['lignes'])): ?>
                <tr>
                    <td colspan="<?= count($rapport['colonnes'] ?? []) ?>" class="px-4 py-8 text-center text-slate-400">
                        Aucune donnée pour les critères sélectionnés.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rapport['lignes'] as $row): ?>
                <tr class="hover:bg-slate-50">
                    <?php foreach ($rapport['colonnes'] as $col): ?>
                    <td class="px-4 py-2.5 text-slate-700">
                        <?= htmlspecialchars((string)($row[$col['key']] ?? '')) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($rapport['footer'])): ?>
            <tfoot class="bg-violet-50">
                <tr>
                    <?php foreach ($rapport['colonnes'] as $col): ?>
                    <td class="px-4 py-2.5 font-semibold text-slate-700">
                        <?= htmlspecialchars((string)($rapport['footer'][$col['key']] ?? '')) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
