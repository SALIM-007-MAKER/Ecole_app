<?php
// Usage : include avec $kpi = ['label', 'valeur', 'unite', 'variation', 'tendance']
$tendance  = $kpi['tendance']  ?? 'stable';
$variation = $kpi['variation'] ?? null;
$icon = match ($tendance) {
    'hausse' => '<i data-lucide="trending-up"   class="w-4 h-4 text-green-500"></i>',
    'baisse' => '<i data-lucide="trending-down" class="w-4 h-4 text-red-500"></i>',
    default  => '<i data-lucide="minus"         class="w-4 h-4 text-slate-400"></i>',
};
$varClass = $tendance === 'hausse' ? 'text-green-600' : ($tendance === 'baisse' ? 'text-red-600' : 'text-slate-500');
?>
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 flex flex-col gap-2">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-slate-500"><?= htmlspecialchars($kpi['label'] ?? '') ?></span>
        <?= $icon ?>
    </div>
    <div class="flex items-end gap-2">
        <span class="text-2xl font-bold text-slate-800"><?= htmlspecialchars((string)($kpi['valeur'] ?? 0)) ?></span>
        <?php if (!empty($kpi['unite'])): ?>
            <span class="text-sm text-slate-400 mb-0.5"><?= htmlspecialchars($kpi['unite']) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($variation !== null): ?>
        <div class="flex items-center gap-1 text-xs <?= $varClass ?>">
            <?= $icon ?>
            <span><?= $variation > 0 ? '+' : '' ?><?= number_format((float)$variation, 1) ?>% vs période préc.</span>
        </div>
    <?php endif; ?>
</div>
