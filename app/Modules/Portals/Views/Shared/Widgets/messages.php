<?php
$data       = $widget->data ?? $data ?? [];
$unread     = (int)($data['unread'] ?? 0);
$recent     = $data['recent'] ?? [];
$composeUrl = $data['compose_url'] ?? '#';
$inboxUrl   = $data['inbox_url'] ?? '#';
$baseUrl    = $baseUrl ?? '';
?>
<div class="h-full flex flex-col">
    <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-medium text-slate-400"><?= $unread > 0 ? $unread . ' non lu(s)' : 'Tout lu' ?></span>
        <a href="<?= htmlspecialchars($baseUrl . $composeUrl) ?>" class="text-xs bg-portal text-white px-2 py-0.5 rounded-md hover:opacity-90 transition-opacity">
            + Nouveau
        </a>
    </div>

    <?php if (empty($recent)): ?>
        <div class="flex-1 flex flex-col items-center justify-center text-slate-300 py-4">
            <i data-lucide="inbox" class="w-8 h-8 mb-2"></i>
            <p class="text-sm">Aucun message</p>
        </div>
    <?php else: ?>
        <ul class="space-y-1.5 flex-1 overflow-y-auto">
            <?php foreach ($recent as $msg): ?>
                <li class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 transition-colors <?= !($msg['lu'] ?? true) ? 'font-medium' : '' ?>">
                    <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center shrink-0">
                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-500"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-700 truncate"><?= htmlspecialchars($msg['sujet'] ?? 'Message') ?></p>
                        <p class="text-xs text-slate-400"><?= date('d/m H:i', strtotime($msg['created_at'] ?? 'now')) ?></p>
                    </div>
                    <?php if (!($msg['lu'] ?? true)): ?>
                        <span class="w-2 h-2 rounded-full bg-portal shrink-0"></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
