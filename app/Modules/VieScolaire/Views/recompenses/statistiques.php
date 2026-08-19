<?php
/** @var array $user */
/** @var array $stats */
/** @var int $classeId */
/** @var string $annee */
?>
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-slate-800">Statistiques Récompenses</h1>
        <div class="flex gap-3">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/classement<?= $classeId ? '?classe_id=' . $classeId . '&annee_scolaire=' . urlencode($annee) : '' ?>"
               class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i data-lucide="trophy" class="w-4 h-4"></i> Voir classement comportemental
            </a>
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses" class="text-sm text-slate-500 hover:text-violet-600">← Retour</a>
        </div>
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

    <?php if (empty($stats)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-10 text-center text-slate-400">
            Sélectionnez une classe pour afficher les statistiques.
        </div>
    <?php else: ?>
        <?php
            $totalRecus      = array_sum(array_column($stats, 'total_recompenses'));
            $totalValides    = array_sum(array_column($stats, 'validees'));
            $totalDistincts  = array_sum(array_column($stats, 'distinctions_hautes'));
            $elevesRecompenses = count(array_filter($stats, fn($s) => (int)$s['total_recompenses'] > 0));
        ?>

        <!-- KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-violet-600"><?= $totalRecus ?></p>
                <p class="text-xs text-slate-500 mt-1">Total récompenses</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-green-600"><?= $totalValides ?></p>
                <p class="text-xs text-slate-500 mt-1">Validées</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600"><?= $totalDistincts ?></p>
                <p class="text-xs text-slate-500 mt-1">Distinctions hautes</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-slate-700"><?= $elevesRecompenses ?></p>
                <p class="text-xs text-slate-500 mt-1">Élèves récompensés</p>
            </div>
        </div>

        <!-- Tableau par élève -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800">Bilan par élève — <?= htmlspecialchars($annee) ?></h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Total</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Validées</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Distinctions hautes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($stats as $s): ?>
                        <tr class="hover:bg-slate-50 <?= (int)$s['total_recompenses'] >= 3 ? 'bg-green-50' : '' ?>">
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars($s['eleve_nom'] . ' ' . $s['eleve_prenom']) ?>
                            </td>
                            <td class="px-4 py-3 text-center font-bold <?= (int)$s['total_recompenses'] >= 3 ? 'text-green-600' : 'text-slate-700' ?>">
                                <?= (int)$s['total_recompenses'] ?>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-600"><?= (int)$s['validees'] ?></td>
                            <td class="px-4 py-3 text-center <?= (int)$s['distinctions_hautes'] >= 1 ? 'text-amber-600 font-semibold' : 'text-slate-500' ?>">
                                <?= (int)$s['distinctions_hautes'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<script>lucide.createIcons();</script>
