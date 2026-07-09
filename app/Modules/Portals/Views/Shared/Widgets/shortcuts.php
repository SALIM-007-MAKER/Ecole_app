<?php
$data      = $widget->data ?? $data ?? [];
$shortcuts = $data['shortcuts'] ?? [];
$editUrl   = $data['edit_url'] ?? '#';
$baseUrl   = $baseUrl ?? '';

$colorMap = [
    'violet'  => 'bg-violet-100 text-violet-600',
    'indigo'  => 'bg-indigo-100 text-indigo-600',
    'teal'    => 'bg-teal-100 text-teal-600',
    'blue'    => 'bg-blue-100 text-blue-600',
    'emerald' => 'bg-emerald-100 text-emerald-600',
    'amber'   => 'bg-amber-100 text-amber-600',
    'rose'    => 'bg-rose-100 text-rose-600',
    'green'   => 'bg-green-100 text-green-600',
    'red'     => 'bg-red-100 text-red-600',
    'gray'    => 'bg-gray-100 text-gray-600',
    'slate'   => 'bg-slate-100 text-slate-600',
];
?>
<div class="grid grid-cols-4 gap-2">
    <?php foreach ($shortcuts as $sc): ?>
        <?php $cls = $colorMap[$sc['color'] ?? 'violet'] ?? $colorMap['violet']; ?>
        <a href="<?= htmlspecialchars($baseUrl . $sc['url']) ?>"
           class="flex flex-col items-center gap-1.5 p-2.5 rounded-xl hover:bg-slate-50 transition-colors group">
            <div class="w-10 h-10 rounded-xl <?= $cls ?> flex items-center justify-center group-hover:scale-105 transition-transform">
                <i data-lucide="<?= htmlspecialchars($sc['icon'] ?? 'link') ?>" class="w-5 h-5"></i>
            </div>
            <span class="text-xs text-center text-slate-600 leading-tight"><?= htmlspecialchars($sc['label'] ?? '') ?></span>
        </a>
    <?php endforeach; ?>

    <?php if (empty($shortcuts)): ?>
        <div class="col-span-4 text-center text-slate-300 py-4">
            <i data-lucide="zap-off" class="w-8 h-8 mx-auto mb-1"></i>
            <p class="text-sm">Aucun raccourci</p>
        </div>
    <?php endif; ?>
</div>
