<?php
/** @var array $stats */
function hStat(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$statutLabels = ['actif'=>'Actif','inactif'=>'Inactif','suspendu'=>'Suspendu','conge'=>'En congé','retraite'=>'Retraité','demissionnaire'=>'Démissionnaire'];
$typeLabels   = ['enseignant'=>'Enseignant','administratif'=>'Administratif','support'=>'Support','direction'=>'Direction','technique'=>'Technique'];
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="text-slate-400 hover:text-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-5 h-5 text-violet-600"></i>
            Statistiques employés
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Vue d'ensemble du personnel</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Par statut -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="toggle-right" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Par statut</h3>
        </div>
        <div class="p-5 space-y-3">
        <?php
        $statutColors = ['actif'=>'emerald','inactif'=>'slate','suspendu'=>'orange','conge'=>'blue','retraite'=>'purple','demissionnaire'=>'red'];
        $total = array_sum($stats['par_statut'] ?? []);
        foreach ($statutLabels as $val => $lbl):
            $n = (int)($stats['par_statut'][$val] ?? 0);
            $pct = $total > 0 ? round($n / $total * 100) : 0;
            $color = $statutColors[$val] ?? 'slate';
        ?>
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-slate-700"><?= hStat($lbl) ?></span>
                <span class="text-sm font-semibold text-slate-900"><?= $n ?> <span class="text-xs font-normal text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-<?= $color ?>-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Par type -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="briefcase" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Par type de personnel</h3>
        </div>
        <div class="p-5 space-y-3">
        <?php
        $totalType = array_sum($stats['par_type'] ?? []);
        foreach ($typeLabels as $val => $lbl):
            $n = (int)($stats['par_type'][$val] ?? 0);
            $pct = $totalType > 0 ? round($n / $totalType * 100) : 0;
        ?>
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-slate-700"><?= hStat($lbl) ?></span>
                <span class="text-sm font-semibold text-slate-900"><?= $n ?> <span class="text-xs font-normal text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-violet-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Par département -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm md:col-span-2">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="building-2" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Par département (actifs)</h3>
        </div>
        <?php if (empty($stats['par_departement'])): ?>
        <div class="py-10 text-center text-sm text-slate-400">Aucune donnée</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-medium text-slate-600">Département</th>
                        <th class="text-right px-5 py-3 font-medium text-slate-600">Effectif</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($stats['par_departement'] as $row): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 text-slate-700"><?= hStat($row['departement']) ?></td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-900"><?= (int)$row['total'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
