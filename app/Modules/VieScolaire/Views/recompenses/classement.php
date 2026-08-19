<?php
/** @var array $user */
/** @var array $classement */
/** @var int $classeId */
/** @var string $annee */
/** @var array $classes */

$title = 'Classement comportemental';
?>
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses" class="hover:text-violet-600">Récompenses</a>
        <i data-lucide="chevron-right" class="w-3 h-3"></i>
        <span class="text-slate-700">Classement</span>
    </div>

    <div class="flex items-center justify-between gap-4 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="trophy" class="w-5 h-5 text-amber-600"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Classement comportemental</h1>
                <p class="text-slate-500 text-sm mt-0.5">Score = récompenses × 2 − incidents disciplinaires</p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses"
           class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
        </a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classe <span class="form-required">*</span></label>
            <select name="classe_id"
                    class="border border-slate-200 rounded-lg px-3 py-2 text-sm w-52 focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Sélectionner une classe</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c->id ?>" <?= $classeId === (int)$c->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c->nom) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
            <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($annee) ?>"
                   placeholder="2025-2026"
                   class="form-input">
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Afficher
        </button>
    </form>

    <?php if (empty($classement)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-14 text-center">
            <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="trophy" class="w-5 h-5 text-slate-400"></i>
            </div>
            <p class="text-sm text-slate-400">Sélectionnez une classe pour afficher le classement comportemental.</p>
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
                    <?php foreach ($classement as $rank => $row): ?>
                        <?php
                            $position = $rank + 1;
                            $score    = (int)$row['score_comportemental'];
                            $rowBg    = $position === 1 ? 'bg-amber-50'
                                      : ($position === 2 ? 'bg-slate-50'
                                      : ($position === 3 ? 'bg-orange-50' : ''));
                            $scoreClass = $score > 0 ? 'text-green-600 font-bold'
                                        : ($score < 0 ? 'text-red-600 font-bold' : 'text-slate-500');
                            $initiales = mb_strtoupper(mb_substr(trim($row['eleve_prenom']), 0, 1) . mb_substr(trim($row['eleve_nom']), 0, 1));
                        ?>
                        <tr class="hover:bg-slate-100 transition-colors <?= $rowBg ?>">
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
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                                        <?= htmlspecialchars($initiales) ?>
                                    </div>
                                    <span class="font-medium text-slate-800">
                                        <?= htmlspecialchars($row['eleve_nom'] . ' ' . $row['eleve_prenom']) ?>
                                    </span>
                                </div>
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
