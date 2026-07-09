<?php
// Usage : include avec $chart = ['id', 'titre', 'data' => chart.js config]
$chartId   = 'chart_' . preg_replace('/[^a-z0-9]/i', '_', $chart['id'] ?? uniqid());
$chartJson = json_encode($chart['data'] ?? [], JSON_UNESCAPED_UNICODE);
?>
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
    <?php if (!empty($chart['titre'])): ?>
        <h3 class="text-sm font-semibold text-slate-700 mb-3"><?= htmlspecialchars($chart['titre']) ?></h3>
    <?php endif; ?>
    <div class="relative" style="height:220px;">
        <canvas id="<?= $chartId ?>"></canvas>
    </div>
</div>
<script>
(function() {
    const ctx = document.getElementById('<?= $chartId ?>');
    if (!ctx) return;
    new Chart(ctx, <?= $chartJson ?>);
})();
</script>
