<?php
/** @var array $stats */
function hStat2(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$statutLabels = ['titulaire'=>'Titulaire','vacataire'=>'Vacataire','remplacant'=>'Remplaçant','stagiaire'=>'Stagiaire','contractuel'=>'Contractuel'];
$statutColors = ['titulaire'=>'emerald','vacataire'=>'blue','remplacant'=>'amber','stagiaire'=>'purple','contractuel'=>'slate'];
$totalStatut  = array_sum($stats['par_statut'] ?? []);
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/rh/enseignants" class="text-slate-400 hover:text-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-5 h-5 text-violet-600"></i>
            Statistiques enseignants
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Vue d'ensemble des profils pédagogiques</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Par statut pédagogique -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="toggle-right" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Par statut pédagogique</h3>
        </div>
        <div class="p-5 space-y-3">
        <?php foreach ($statutLabels as $val => $lbl):
            $n   = (int)($stats['par_statut'][$val] ?? 0);
            $pct = $totalStatut > 0 ? round($n / $totalStatut * 100) : 0;
            $c   = $statutColors[$val] ?? 'slate';
        ?>
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-slate-700"><?= hStat2($lbl) ?></span>
                <span class="text-sm font-semibold text-slate-900"><?= $n ?> <span class="text-xs font-normal text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-<?= $c ?>-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Top matières -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="book-open" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Matières les plus enseignées</h3>
        </div>
        <?php if (empty($stats['par_matiere'])): ?>
        <div class="py-10 text-center text-sm text-slate-400">Aucune habilitation enregistrée.</div>
        <?php else: ?>
        <?php
        $maxM = max(array_column($stats['par_matiere'], 'total'));
        ?>
        <div class="divide-y divide-slate-50">
        <?php foreach (array_slice($stats['par_matiere'], 0, 10) as $row):
            $pct = $maxM > 0 ? round((int)$row['total'] / $maxM * 100) : 0;
        ?>
        <div class="px-5 py-3 flex items-center gap-3 text-sm">
            <span class="flex-1 text-slate-700 truncate"><?= hStat2($row['matiere']) ?></span>
            <div class="w-20 h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-violet-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="text-sm font-semibold text-slate-900 w-8 text-right"><?= (int)$row['total'] ?></span>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Résumé global -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm md:col-span-2">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Résumé</h3>
        </div>
        <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div>
                <div class="text-3xl font-bold text-slate-900"><?= $totalStatut ?></div>
                <div class="text-xs text-slate-400 mt-1">Profils actifs</div>
            </div>
            <div>
                <div class="text-3xl font-bold text-emerald-600"><?= (int)($stats['par_statut']['titulaire'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 mt-1">Titulaires</div>
            </div>
            <div>
                <div class="text-3xl font-bold text-blue-600"><?= (int)($stats['par_statut']['vacataire'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 mt-1">Vacataires</div>
            </div>
            <div>
                <div class="text-3xl font-bold text-slate-400"><?= (int)($stats['archives'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 mt-1">Archivés</div>
            </div>
        </div>
    </div>
</div>
