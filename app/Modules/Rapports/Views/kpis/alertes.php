<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-6 h-6 text-amber-500"></i>
            Alertes KPI — <?= htmlspecialchars($domaine) ?>
        </h1>
        <div class="flex gap-2">
            <?php foreach (['finance','academique','vie_scolaire','inventaire'] as $d): ?>
            <a href="?domaine=<?= $d ?>"
               class="px-3 py-1.5 text-xs rounded-full border <?= $d === $domaine ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-slate-600 border-slate-200' ?>">
                <?= ucfirst($d) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($alertes)): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-8 text-center">
        <i data-lucide="check-circle" class="w-12 h-12 text-green-500 mx-auto mb-3"></i>
        <p class="text-green-700 font-medium">Aucune alerte active pour ce domaine.</p>
        <p class="text-green-600 text-sm mt-1">Tous les indicateurs sont dans les seuils normaux.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($alertes as $a): ?>
        <div class="bg-white rounded-xl border border-amber-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600"></i>
            </div>
            <div class="flex-1">
                <div class="font-semibold text-slate-800 text-sm">
                    <?= htmlspecialchars(str_replace('_', ' ', ucfirst($a['metrique'] ?? ''))) ?>
                </div>
                <div class="text-sm text-slate-600 mt-1">
                    Valeur actuelle : <span class="font-bold text-amber-700"><?= htmlspecialchars((string)($a['valeur'] ?? '')) ?></span>
                    — Seuil : <span class="font-medium"><?= htmlspecialchars((string)($a['seuil'] ?? '')) ?></span>
                    (<?= htmlspecialchars($a['direction'] ?? '') ?>)
                </div>
                <div class="text-xs text-slate-400 mt-1">Période : <?= htmlspecialchars($a['periode'] ?? '') ?></div>
            </div>
            <a href="<?= BASE_URL ?>/v2/rapports/kpis/tendance?domaine=<?= urlencode($domaine) ?>&metrique=<?= urlencode($a['metrique'] ?? '') ?>"
               class="text-xs text-violet-600 hover:underline flex-shrink-0">Voir tendance →</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<script>lucide.createIcons();</script>
