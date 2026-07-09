<?php
$data        = $widget->data ?? $data ?? [];
$unread      = (int)($data['unread'] ?? 0);
$recent      = $data['recent'] ?? [];
$viewAllUrl  = $data['view_all_url'] ?? '#';
?>
<div class="h-full flex flex-col">
    <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-medium text-slate-400"><?= $unread > 0 ? $unread . ' non lue(s)' : 'Tout lu' ?></span>
        <a href="<?= htmlspecialchars($viewAllUrl) ?>" class="text-xs text-portal hover:underline">Tout voir</a>
    </div>

    <?php if (empty($recent)): ?>
        <div class="flex-1 flex flex-col items-center justify-center text-slate-300 py-4">
            <i data-lucide="bell-off" class="w-8 h-8 mb-2"></i>
            <p class="text-sm">Aucune notification</p>
        </div>
    <?php else: ?>
        <ul class="space-y-1.5 overflow-y-auto flex-1">
            <?php foreach ($recent as $notif): ?>
                <li class="flex items-start gap-2.5 p-2 rounded-lg <?= !($notif['lu'] ?? true) ? 'bg-slate-50' : '' ?> hover:bg-slate-50 transition-colors">
                    <span class="mt-1 w-1.5 h-1.5 rounded-full shrink-0 <?= !($notif['lu'] ?? true) ? 'bg-portal' : 'bg-slate-200' ?>"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 truncate"><?= htmlspecialchars($notif['titre'] ?? 'Notification') ?></p>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars(substr($notif['message'] ?? '', 0, 60)) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
