<?php
$session   = $session ?? [];
$stats     = $stats   ?? [];
$csrfToken = \Core\Session::getCsrfToken();

$taux = $stats['taux_presence'] ?? 0;
$tauxColor = $taux >= 80 ? 'emerald' : ($taux >= 60 ? 'amber' : 'red');
?>

<div class="flex items-center gap-4 mb-6">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>"
       class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à l'appel
    </a>
    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
        Valider l'appel
    </h2>
</div>

<div class="max-w-lg">
    <!-- Récapitulatif session -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 mb-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Récapitulatif de la session</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">Classe</dt>
                <dd class="font-medium text-slate-900"><?= htmlspecialchars($session['classe_nom'] ?? '', ENT_QUOTES) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Date</dt>
                <dd class="font-medium text-slate-900"><?= date('d/m/Y', strtotime($session['date_appel'])) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Enseignant</dt>
                <dd class="font-medium text-slate-900"><?= htmlspecialchars($session['enseignant_nom'] ?? '', ENT_QUOTES) ?></dd>
            </div>
        </dl>
    </div>

    <!-- Stats -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 mb-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Résultats du pointage</h3>
        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-slate-600">Total élèves pointés</span>
                <span class="font-bold text-slate-900"><?= $stats['total'] ?? 0 ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-emerald-600">Présents</span>
                <span class="font-bold text-emerald-700"><?= $stats['presents'] ?? 0 ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-red-600">Absents</span>
                <span class="font-bold text-red-700"><?= $stats['absents'] ?? 0 ?></span>
            </div>
            <?php if (($stats['retards'] ?? 0) > 0): ?>
            <div class="flex items-center justify-between">
                <span class="text-amber-600">Retards</span>
                <span class="font-bold text-amber-700"><?= $stats['retards'] ?></span>
            </div>
            <?php endif; ?>
            <div class="pt-3 border-t border-slate-100">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-slate-600">Taux de présence</span>
                    <span class="font-bold text-<?= $tauxColor ?>-700 text-lg"><?= $taux ?>%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="bg-<?= $tauxColor ?>-500 h-2 rounded-full transition-all"
                         style="width: <?= min((float)$taux, 100) ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Avertissement -->
    <div class="flex items-start gap-2 p-4 mb-5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 mt-0.5"></i>
        <p>Une fois validé, cet appel ne pourra plus être modifié. Les absences enregistrées ont déjà été transmises au domaine Absences.</p>
    </div>

    <!-- Actions -->
    <div class="flex gap-3">
        <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>/valider">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors">
                <i data-lucide="check" class="w-4 h-4"></i>Confirmer la validation
            </button>
        </form>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $session['id'] ?>"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
            <i data-lucide="x" class="w-4 h-4"></i>Annuler
        </a>
    </div>
</div>
