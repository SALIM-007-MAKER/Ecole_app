<?php
$data    = $widget->data ?? $data ?? [];
$items   = $data['items'] ?? [];
$allUrl  = $data['all_url'] ?? '#';
$baseUrl = $baseUrl ?? '';

$priorityColors = [
    'haute'  => 'bg-red-100 text-red-700 border-red-200',
    'normal' => 'bg-blue-50 text-blue-700 border-blue-100',
    'basse'  => 'bg-slate-50 text-slate-600 border-slate-100',
];
?>
<div class="space-y-2">
    <?php if (empty($items)): ?>
        <div class="flex flex-col items-center justify-center text-slate-300 py-6">
            <i data-lucide="megaphone" class="w-8 h-8 mb-2"></i>
            <p class="text-sm">Aucune annonce</p>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <?php
            $prio = $item['priorite'] ?? 'normal';
            $cls  = $priorityColors[$prio] ?? $priorityColors['normal'];
            ?>
            <div class="border rounded-lg p-3 <?= $cls ?>">
                <p class="text-sm font-semibold"><?= htmlspecialchars($item['titre'] ?? '') ?></p>
                <p class="text-xs mt-0.5 line-clamp-2"><?= htmlspecialchars(strip_tags($item['contenu'] ?? '')) ?></p>
                <p class="text-xs mt-1 opacity-60"><?= date('d/m/Y', strtotime($item['created_at'] ?? 'now')) ?></p>
            </div>
        <?php endforeach; ?>
        <a href="<?= htmlspecialchars($baseUrl . $allUrl) ?>" class="block text-center text-xs text-slate-400 hover:text-slate-600 pt-1">
            Voir toutes les annonces →
        </a>
    <?php endif; ?>
</div>
